<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Integrations;

use Illuminate\Support\Facades\Log;
use Solspace\Freeform\Events\Notifications\PrepareNotificationEvent;
use Solspace\Freeform\Notifications\NotificationInterface;
use Throwable;
use yii\base\Event;
use Yxanu\MailStudio\Services\TemplateRenderer;
use Yxanu\MailStudio\Services\TemplateRepository;

class FreeformAdapter
{
    private static bool $registered = false;

    public function __construct(
        private readonly TemplateRepository $templates,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function register(): void
    {
        if (self::$registered || ! interface_exists(NotificationInterface::class)) {
            return;
        }

        self::$registered = true;

        Event::on(
            NotificationInterface::class,
            NotificationInterface::PREPARE_NOTIFICATION,
            $this->prepare(...),
        );
    }

    private function prepare(PrepareNotificationEvent $event): void
    {
        $original = $event->getTemplate();
        if (! $original) {
            return;
        }

        $sourceKey = 'freeform.'.$event->getForm()->getUid().'.'.$event->getNotification()->getUid();

        try {
            $siteId = $event->getForm()->getSiteId();
            $locale = null;
            if ($siteId) {
                $locale = \Craft::$app->getSites()->getSiteById($siteId)?->language;
            }

            $revision = $this->templates->resolveRevision('freeform', $sourceKey, $siteId, $locale);
            if (! $revision) {
                return;
            }

            $rendered = $this->renderer->render($this->templates->snapshot($revision), resolveTokens: false);
            $template = clone $original;
            $template->subject = $rendered->subject;
            $template->body = $rendered->html;
            $template->textBody = $rendered->text;
            $template->autoText = false;
            $template->wrapperId = null;
            $event->setTemplate($template);
        } catch (Throwable $exception) {
            Log::warning('Mail Studio left a Freeform notification unchanged after a render failure.', [
                'sourceKey' => $sourceKey,
                'exception' => $exception,
            ]);
        }
    }
}
