<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Listeners;

use CraftCms\Cms\SystemMessage\Mailables\SystemMessageMailable;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Throwable;
use Yxanu\MailStudio\Services\TemplateRenderer;
use Yxanu\MailStudio\Services\TemplateRepository;

class OverrideSystemMessage
{
    public function __construct(
        private readonly TemplateRepository $templates,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function handle(MessageSending $event): void
    {
        $mailable = $event->data['__laravel_mailable'] ?? null;
        if (! $mailable instanceof SystemMessageMailable && $mailable !== SystemMessageMailable::class) {
            return;
        }

        $key = $event->data['key'] ?? null;
        if (! is_string($key) || $key === '') {
            return;
        }

        try {
            $revision = $this->templates->resolveRevision(
                'craft-system',
                'craft.system.'.$key,
                isset($event->data['siteId']) ? (int) $event->data['siteId'] : null,
                isset($event->data['language']) ? (string) $event->data['language'] : null,
            );
            if (! $revision) {
                return;
            }

            $variables = is_array($event->data['variables'] ?? null) ? $event->data['variables'] : [];
            $rendered = $this->renderer->render($this->templates->snapshot($revision), $variables);
            $event->message
                ->subject($rendered->subject)
                ->html($rendered->html)
                ->text($rendered->text);
        } catch (Throwable $exception) {
            Log::warning('Mail Studio left a Craft system message unchanged after a render failure.', [
                'systemMessageKey' => $key,
                'exception' => $exception,
            ]);
        }
    }
}
