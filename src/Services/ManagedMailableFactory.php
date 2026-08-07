<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Services;

use Yxanu\MailStudio\Exceptions\TemplateNotPublished;
use Yxanu\MailStudio\Mailables\ManagedMailable;

class ManagedMailableFactory
{
    public function __construct(
        private readonly SourceRegistry $sources,
        private readonly TemplateRepository $templates,
        private readonly BrandProfileRepository $brands,
    ) {}

    /** @param array<string, mixed> $variables */
    public function make(string $sourceKey, array $variables, ?int $siteId = null, ?string $locale = null): ManagedMailable
    {
        $source = $this->sources->get($sourceKey);
        $this->sources->validate($source, $variables);
        $revision = $this->templates->resolveRevision($source->adapter, $source->key, $siteId, $locale);

        if ($revision) {
            $snapshot = $this->templates->snapshot($revision);
        } elseif ($source->defaultTemplate) {
            $profile = $this->brands->default();
            $snapshot = [
                'revisionId' => null,
                'templateId' => null,
                'subject' => $source->defaultTemplate['subject'] ?? $source->label,
                'preheader' => $source->defaultTemplate['preheader'] ?? '',
                'document' => $source->defaultTemplate['document'] ?? ['version' => 1, 'blocks' => []],
                'brand' => array_replace(BrandProfileRepository::defaultConfig(), $profile->config ?? []),
            ];
        } else {
            throw new TemplateNotPublished("No published template or source fallback exists for [$sourceKey].");
        }
        $snapshot['allowedTokens'] = array_keys($source->variables);

        return new ManagedMailable(
            sourceKey: $sourceKey,
            snapshot: $snapshot,
            variables: $variables,
            siteId: $siteId,
            language: $locale,
        );
    }
}
