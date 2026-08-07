<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Yxanu\MailStudio\Models\BrandProfile;
use Yxanu\MailStudio\Models\EmailTemplate;
use Yxanu\MailStudio\Models\SourceMapping;
use Yxanu\MailStudio\Models\TemplateRevision;
use Yxanu\MailStudio\Models\TemplateVariant;

class ImportExportService
{
    public function __construct(private readonly BrandProfileRepository $brands) {}

    /** @return array<string, mixed> */
    public function export(): array
    {
        $brands = BrandProfile::query()->orderBy('id')->get();
        $templates = EmailTemplate::query()
            ->with(['brandProfile', 'variants.revisions', 'mappings'])
            ->orderBy('id')
            ->get();

        return [
            'format' => 'craft-mail-studio',
            'version' => 1,
            'exportedAt' => now()->toIso8601String(),
            'brands' => $brands->map(fn (BrandProfile $brand): array => [
                'uid' => $brand->uid,
                'name' => $brand->name,
                'handle' => $brand->handle,
                'isDefault' => $brand->is_default,
                'config' => array_replace(BrandProfileRepository::defaultConfig(), $brand->config ?? []),
            ])->values()->all(),
            'templates' => $templates->map(fn (EmailTemplate $template): array => [
                'uid' => $template->uid,
                'name' => $template->name,
                'handle' => $template->handle,
                'description' => $template->description,
                'presetKey' => $template->preset_key,
                'brandUid' => $template->brandProfile?->uid,
                'variants' => $template->variants->map(fn (TemplateVariant $variant): array => [
                    'siteId' => $variant->site_id,
                    'locale' => $variant->locale,
                    'subject' => $variant->draft_subject,
                    'preheader' => $variant->draft_preheader,
                    'document' => $variant->draft_document,
                    'activeRevisionNumber' => $variant->activeRevision?->number,
                    'revisions' => $variant->revisions->map(fn (TemplateRevision $revision): array => [
                        'number' => $revision->number,
                        'subject' => $revision->subject,
                        'preheader' => $revision->preheader,
                        'document' => $revision->document,
                        'brand' => $revision->brand_snapshot,
                        'createdAt' => $revision->created_at?->toIso8601String(),
                    ])->values()->all(),
                ])->values()->all(),
                'mappings' => $template->mappings->map(fn (SourceMapping $mapping): array => [
                    'adapter' => $mapping->adapter,
                    'sourceKey' => $mapping->source_key,
                    'siteId' => $mapping->site_id,
                    'locale' => $mapping->locale,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /** @param array<string, mixed> $payload @return array{brands: int, templates: int, revisions: int} */
    public function import(array $payload): array
    {
        if (($payload['format'] ?? null) !== 'craft-mail-studio' || ($payload['version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Unsupported Mail Studio import format.');
        }
        if (! is_array($payload['brands'] ?? null) || ! is_array($payload['templates'] ?? null)) {
            throw new InvalidArgumentException('Mail Studio import must contain brands and templates.');
        }

        return DB::transaction(function () use ($payload): array {
            $brandCount = 0;
            $templateCount = 0;
            $revisionCount = 0;
            $brandsByUid = [];

            foreach ($payload['brands'] as $item) {
                if (! is_array($item) || ! is_string($item['uid'] ?? null) || ! is_string($item['name'] ?? null)) {
                    continue;
                }
                $brand = BrandProfile::query()->where('uid', $item['uid'])->first();
                if (! $brand) {
                    $brand = $this->brands->create($item['name'], is_array($item['config'] ?? null) ? $item['config'] : []);
                    $brand->uid = $item['uid'];
                    $brand->save();
                } else {
                    $this->brands->update($brand, is_array($item['config'] ?? null) ? $item['config'] : [], $item['name']);
                }
                $brandsByUid[$item['uid']] = $brand->refresh();
                $brandCount++;
            }

            foreach ($payload['templates'] as $item) {
                if (! is_array($item) || ! is_string($item['uid'] ?? null) || ! is_string($item['name'] ?? null)) {
                    continue;
                }
                $template = EmailTemplate::query()->where('uid', $item['uid'])->first();
                if (! $template) {
                    $template = new EmailTemplate([
                        'uid' => $item['uid'],
                        'handle' => $this->availableHandle((string) ($item['handle'] ?? $item['name'])),
                    ]);
                }
                $template->fill([
                    'name' => trim($item['name']),
                    'description' => isset($item['description']) ? trim((string) $item['description']) : null,
                    'preset_key' => isset($item['presetKey']) ? (string) $item['presetKey'] : null,
                    'brand_profile_id' => $brandsByUid[$item['brandUid'] ?? '']->id ?? $this->brands->default()->id,
                ])->save();

                foreach (($item['variants'] ?? []) as $variantData) {
                    if (! is_array($variantData) || ! is_array($variantData['document'] ?? null)) {
                        continue;
                    }
                    $siteId = isset($variantData['siteId']) ? (int) $variantData['siteId'] : null;
                    $locale = isset($variantData['locale']) ? trim((string) $variantData['locale']) : null;
                    $variant = TemplateVariant::query()
                        ->where('template_id', $template->id)
                        ->where('site_id', $siteId)
                        ->where('locale', $locale)
                        ->first() ?? new TemplateVariant([
                            'template_id' => $template->id,
                            'site_id' => $siteId,
                            'locale' => $locale,
                        ]);
                    $variant->fill([
                        'draft_subject' => trim((string) ($variantData['subject'] ?? $template->name)),
                        'draft_preheader' => trim((string) ($variantData['preheader'] ?? '')),
                        'draft_document' => $this->document($variantData['document']),
                    ])->save();

                    foreach (($variantData['revisions'] ?? []) as $revisionData) {
                        if (! is_array($revisionData) || ! is_array($revisionData['document'] ?? null)) {
                            continue;
                        }
                        $number = max(1, (int) ($revisionData['number'] ?? 1));
                        $revision = TemplateRevision::query()
                            ->where('variant_id', $variant->id)
                            ->where('number', $number)
                            ->first();
                        if (! $revision) {
                            $revision = TemplateRevision::query()->create([
                                'template_id' => $template->id,
                                'variant_id' => $variant->id,
                                'number' => $number,
                                'subject' => trim((string) ($revisionData['subject'] ?? $variant->draft_subject)),
                                'preheader' => trim((string) ($revisionData['preheader'] ?? '')),
                                'document' => $this->document($revisionData['document']),
                                'brand_snapshot' => array_replace(BrandProfileRepository::defaultConfig(), is_array($revisionData['brand'] ?? null) ? $revisionData['brand'] : []),
                                'created_by' => null,
                                'created_at' => $revisionData['createdAt'] ?? now(),
                            ]);
                            $revisionCount++;
                        }
                        if ((int) ($variantData['activeRevisionNumber'] ?? 0) === $number) {
                            $variant->active_revision_id = $revision->id;
                            $variant->save();
                        }
                    }
                }

                foreach (($item['mappings'] ?? []) as $mappingData) {
                    if (! is_array($mappingData) || ! is_string($mappingData['adapter'] ?? null) || ! is_string($mappingData['sourceKey'] ?? null)) {
                        continue;
                    }
                    SourceMapping::query()->updateOrCreate([
                        'adapter' => $mappingData['adapter'],
                        'source_key' => $mappingData['sourceKey'],
                        'site_id' => isset($mappingData['siteId']) ? (int) $mappingData['siteId'] : null,
                        'locale' => isset($mappingData['locale']) ? (string) $mappingData['locale'] : null,
                    ], ['template_id' => $template->id]);
                }
                $templateCount++;
            }

            $this->brands->ensureDefault();

            return ['brands' => $brandCount, 'templates' => $templateCount, 'revisions' => $revisionCount];
        });
    }

    private function availableHandle(string $value): string
    {
        $base = Str::slug($value) ?: 'email-template';
        $handle = $base;
        $suffix = 2;
        while (EmailTemplate::query()->where('handle', $handle)->exists()) {
            $handle = $base.'-'.$suffix++;
        }

        return $handle;
    }

    /** @param array<string, mixed> $document @return array<string, mixed> */
    private function document(array $document): array
    {
        return [
            'version' => 1,
            'blocks' => array_values(array_filter(
                $document['blocks'] ?? [],
                fn (mixed $block): bool => is_array($block),
            )),
        ];
    }
}
