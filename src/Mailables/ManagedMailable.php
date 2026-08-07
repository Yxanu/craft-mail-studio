<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Mailables;

use CraftCms\Cms\Email\Mailables\CraftMailable;
use Yxanu\MailStudio\Services\TemplateRenderer;

class ManagedMailable extends CraftMailable
{
    /** @param array<string, mixed> $snapshot @param array<string, mixed> $variables */
    public function __construct(
        public string $sourceKey,
        public array $snapshot,
        public array $variables,
        public ?int $siteId = null,
        public ?string $language = null,
    ) {
        if ($language) {
            $this->locale($language);
        }
    }

    public function build(): static
    {
        $rendered = app(TemplateRenderer::class)->render($this->snapshot, $this->variables);

        return $this
            ->subject($rendered->subject)
            ->html($rendered->html)
            ->text('mail-studio::plain', ['body' => $rendered->text]);
    }
}
