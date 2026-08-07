<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Services;

use CraftCms\Cms\Site\Sites;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yxanu\MailStudio\MailStudio;
use Yxanu\MailStudio\Models\EmailTemplate;
use Yxanu\MailStudio\Models\SourceMapping;
use Yxanu\MailStudio\Models\TemplateRevision;
use Yxanu\MailStudio\Models\TemplateVariant;
use Yxanu\MailStudio\Settings;

class TemplateRepository
{
    public function __construct(
        private readonly PresetLibrary $presets,
        private readonly BrandProfileRepository $brands,
    ) {}

    /** @return Collection<int, EmailTemplate> */
    public function all(): Collection
    {
        return EmailTemplate::query()
            ->with(['brandProfile', 'variants.activeRevision', 'variants.revisions', 'mappings'])
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): EmailTemplate
    {
        return EmailTemplate::query()
            ->with(['brandProfile', 'variants.activeRevision', 'variants.revisions', 'mappings'])
            ->findOrFail($id);
    }

    public function create(string $name, string $presetKey = 'simple-message', ?int $siteId = null, ?string $locale = null): EmailTemplate
    {
        $preset = $this->presets->get($presetKey);

        return DB::transaction(function () use ($name, $presetKey, $preset, $siteId, $locale): EmailTemplate {
            $profile = $this->brands->default();
            $baseHandle = Str::slug($name) ?: 'email-template';
            $handle = $baseHandle;
            $suffix = 2;
            while (EmailTemplate::query()->where('handle', $handle)->exists()) {
                $handle = $baseHandle.'-'.$suffix++;
            }

            $template = EmailTemplate::query()->create([
                'uid' => (string) Str::uuid(),
                'name' => trim($name),
                'handle' => $handle,
                'description' => $preset['description'] ?? null,
                'preset_key' => $presetKey,
                'brand_profile_id' => $profile->id,
            ]);

            TemplateVariant::query()->create([
                'template_id' => $template->id,
                'site_id' => $siteId,
                'locale' => $locale,
                'draft_subject' => $preset['subject'],
                'draft_preheader' => $preset['preheader'] ?? '',
                'draft_document' => $this->freshDocument($preset['document']),
            ]);

            return $this->find($template->id);
        });
    }

    /** @param array<string, mixed> $payload */
    public function saveDraft(EmailTemplate $template, array $payload): EmailTemplate
    {
        return DB::transaction(function () use ($template, $payload): EmailTemplate {
            $template->fill([
                'name' => trim((string) ($payload['name'] ?? $template->name)),
                'description' => trim((string) ($payload['description'] ?? $template->description)),
                'brand_profile_id' => isset($payload['brandProfileId'])
                    ? (int) $payload['brandProfileId']
                    : $template->brand_profile_id,
            ])->save();

            $variant = $this->variant(
                $template,
                isset($payload['siteId']) ? (int) $payload['siteId'] : null,
                isset($payload['locale']) ? (string) $payload['locale'] : null,
            );
            $variant->fill([
                'draft_subject' => trim((string) ($payload['subject'] ?? $variant->draft_subject)),
                'draft_preheader' => trim((string) ($payload['preheader'] ?? $variant->draft_preheader)),
                'draft_document' => $this->normalizeDocument($payload['document'] ?? $variant->draft_document),
            ])->save();

            return $this->find($template->id);
        });
    }

    public function publish(EmailTemplate $template, ?int $siteId = null, ?string $locale = null, ?int $userId = null): TemplateRevision
    {
        return DB::transaction(function () use ($template, $siteId, $locale, $userId): TemplateRevision {
            $variant = $this->variant($template, $siteId, $locale);
            $number = ((int) $variant->revisions()->max('number')) + 1;
            $brand = $template->brandProfile ?? $this->brands->default();

            $revision = TemplateRevision::query()->create([
                'template_id' => $template->id,
                'variant_id' => $variant->id,
                'number' => $number,
                'subject' => $variant->draft_subject,
                'preheader' => $variant->draft_preheader,
                'document' => $this->normalizeDocument($variant->draft_document),
                'brand_snapshot' => array_replace(BrandProfileRepository::defaultConfig(), $brand->config ?? []),
                'created_by' => $userId,
            ]);

            $variant->active_revision_id = $revision->id;
            $variant->save();

            return $revision;
        });
    }

    public function rollback(EmailTemplate $template, TemplateRevision $revision, ?int $userId = null): TemplateRevision
    {
        if ((int) $revision->template_id !== (int) $template->id) {
            abort(404);
        }

        $variant = $revision->variant;
        $variant->fill([
            'draft_subject' => $revision->subject,
            'draft_preheader' => $revision->preheader,
            'draft_document' => $revision->document,
        ])->save();

        return $this->publish($template, $variant->site_id, $variant->locale, $userId);
    }

    public function delete(EmailTemplate $template): void
    {
        $template->delete();
    }

    public function mapSource(string $adapter, string $sourceKey, EmailTemplate $template, ?int $siteId = null, ?string $locale = null): SourceMapping
    {
        $query = SourceMapping::query()
            ->where('adapter', $adapter)
            ->where('source_key', $sourceKey)
            ->where('site_id', $siteId)
            ->where('locale', $locale);

        $mapping = $query->first() ?? new SourceMapping([
            'adapter' => $adapter,
            'source_key' => $sourceKey,
            'site_id' => $siteId,
            'locale' => $locale,
        ]);
        $mapping->template_id = $template->id;
        $mapping->save();

        return $mapping;
    }

    public function unmapSource(string $adapter, string $sourceKey, ?int $siteId = null, ?string $locale = null): void
    {
        SourceMapping::query()
            ->where('adapter', $adapter)
            ->where('source_key', $sourceKey)
            ->where('site_id', $siteId)
            ->where('locale', $locale)
            ->delete();
    }

    public function resolveRevision(string $adapter, string $sourceKey, ?int $siteId = null, ?string $locale = null): ?TemplateRevision
    {
        foreach ($this->fallbacks($siteId, $locale) as [$candidateSiteId, $candidateLocale]) {
            $mapping = SourceMapping::query()
                ->where('adapter', $adapter)
                ->where('source_key', $sourceKey)
                ->where('site_id', $candidateSiteId)
                ->where('locale', $candidateLocale)
                ->first();
            if (! $mapping) {
                continue;
            }

            $template = EmailTemplate::query()->with('brandProfile')->find($mapping->template_id);
            if (! $template) {
                continue;
            }

            $revision = $this->activeRevisionFor($template, $siteId, $locale);
            if ($revision) {
                return $revision;
            }
        }

        return null;
    }

    public function activeRevisionFor(EmailTemplate $template, ?int $siteId = null, ?string $locale = null): ?TemplateRevision
    {
        foreach ($this->fallbacks($siteId, $locale) as [$candidateSiteId, $candidateLocale]) {
            $variant = TemplateVariant::query()
                ->where('template_id', $template->id)
                ->where('site_id', $candidateSiteId)
                ->where('locale', $candidateLocale)
                ->first();
            if ($variant?->active_revision_id) {
                return TemplateRevision::query()->find($variant->active_revision_id);
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function snapshot(TemplateRevision $revision): array
    {
        return [
            'revisionId' => $revision->id,
            'templateId' => $revision->template_id,
            'subject' => $revision->subject,
            'preheader' => $revision->preheader,
            'document' => $revision->document,
            'brand' => array_replace(BrandProfileRepository::defaultConfig(), $revision->brand_snapshot ?? []),
        ];
    }

    /** @return array<string, mixed> */
    public function draftSnapshot(EmailTemplate $template, ?int $siteId = null, ?string $locale = null): array
    {
        $variant = $this->variant($template, $siteId, $locale);
        $brand = $template->brandProfile ?? $this->brands->default();

        return [
            'revisionId' => null,
            'templateId' => $template->id,
            'subject' => $variant->draft_subject,
            'preheader' => $variant->draft_preheader,
            'document' => $variant->draft_document,
            'brand' => array_replace(BrandProfileRepository::defaultConfig(), $brand->config ?? []),
        ];
    }

    /** @return array<string, mixed> */
    public function serialize(EmailTemplate $template): array
    {
        $variants = $template->variants
            ->sortBy(fn (TemplateVariant $variant): string => sprintf(
                '%010d:%s',
                $variant->site_id ?? 0,
                $variant->locale ?? '',
            ))
            ->values();
        $variant = $variants->first(fn (TemplateVariant $candidate): bool => $candidate->site_id === null && $candidate->locale === null)
            ?? $variants->first();

        return [
            'id' => $template->id,
            'uid' => $template->uid,
            'name' => $template->name,
            'handle' => $template->handle,
            'description' => $template->description ?? '',
            'presetKey' => $template->preset_key,
            'brandProfileId' => $template->brand_profile_id,
            'variant' => $variant ? $this->serializeVariant($variant) : null,
            'variants' => $variants->map($this->serializeVariant(...))->all(),
            'mappings' => $template->mappings->map(fn (SourceMapping $mapping): array => [
                'id' => $mapping->id,
                'adapter' => $mapping->adapter,
                'sourceKey' => $mapping->source_key,
                'siteId' => $mapping->site_id,
                'locale' => $mapping->locale,
            ])->values()->all(),
            'updatedAt' => $template->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeVariant(TemplateVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'siteId' => $variant->site_id,
            'locale' => $variant->locale,
            'subject' => $variant->draft_subject,
            'preheader' => $variant->draft_preheader,
            'document' => $variant->draft_document,
            'activeRevisionId' => $variant->active_revision_id,
            'revisions' => $variant->revisions->map(fn (TemplateRevision $revision): array => [
                'id' => $revision->id,
                'number' => $revision->number,
                'createdAt' => $revision->created_at?->toIso8601String(),
                'createdBy' => $revision->created_by,
                'active' => (int) $variant->active_revision_id === (int) $revision->id,
            ])->values()->all(),
        ];
    }

    private function variant(EmailTemplate $template, ?int $siteId = null, ?string $locale = null): TemplateVariant
    {
        $variant = TemplateVariant::query()
            ->where('template_id', $template->id)
            ->where('site_id', $siteId)
            ->where('locale', $locale)
            ->first();

        if ($variant) {
            return $variant;
        }

        $fallback = $template->variants()->first();

        return TemplateVariant::query()->create([
            'template_id' => $template->id,
            'site_id' => $siteId,
            'locale' => $locale,
            'draft_subject' => $fallback?->draft_subject ?? $template->name,
            'draft_preheader' => $fallback?->draft_preheader ?? '',
            'draft_document' => $fallback?->draft_document ?? ['version' => 1, 'blocks' => []],
        ]);
    }

    /** @param mixed $document @return array<string, mixed> */
    private function normalizeDocument(mixed $document): array
    {
        if (! is_array($document)) {
            return ['version' => 1, 'blocks' => []];
        }

        $blocks = array_values(array_filter($document['blocks'] ?? [], fn (mixed $block): bool => is_array($block)));

        return ['version' => 1, 'blocks' => $blocks];
    }

    /** @param array<string, mixed> $document @return array<string, mixed> */
    private function freshDocument(array $document): array
    {
        $document = $this->normalizeDocument($document);
        foreach ($document['blocks'] as &$block) {
            $block['id'] = ($block['type'] ?? 'block').'-'.bin2hex(random_bytes(4));
        }

        return $document;
    }

    /** @return array<int, array{int|null, string|null}> */
    private function fallbacks(?int $siteId, ?string $locale): array
    {
        $siteLanguage = $siteId ? app(Sites::class)->getSiteById($siteId)?->language : null;
        /** @var Settings $settings */
        $settings = MailStudio::getInstance()->getSettings();

        return array_values(array_unique([
            [$siteId, $locale],
            [$siteId, $siteLanguage],
            [null, $settings->defaultLocale],
            [null, null],
        ], SORT_REGULAR));
    }
}
