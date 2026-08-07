<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Solspace\Freeform\Events\Notifications\PrepareNotificationEvent;
use Solspace\Freeform\Form\Form;
use Solspace\Freeform\Library\DataObjects\NotificationTemplate;
use Solspace\Freeform\Notifications\NotificationInterface;
use Yxanu\MailStudio\Data\RenderedTemplate;
use Yxanu\MailStudio\Integrations\FreeformAdapter;
use Yxanu\MailStudio\Models\TemplateRevision;
use Yxanu\MailStudio\Services\TemplateRenderer;
use Yxanu\MailStudio\Services\TemplateRepository;

class FreeformAdapterTest extends TestCase
{
    public function test_it_replaces_a_cloned_notification_template(): void
    {
        if (! class_exists(PrepareNotificationEvent::class)) {
            self::markTestSkipped('Freeform is not installed in this test environment.');
        }

        $form = $this->createStub(Form::class);
        $form->method('getUid')->willReturn('form-uid');
        $form->method('getSiteId')->willReturn(null);

        $notification = $this->createStub(NotificationInterface::class);
        $notification->method('getUid')->willReturn('notification-uid');

        $original = new NotificationTemplate;
        $original->id = 'original';
        $original->uid = 'original';
        $original->handle = 'original';
        $original->name = 'Original';
        $original->fromEmail = 'sender@example.test';
        $original->fromName = 'Sender';
        $original->subject = 'Original subject';
        $original->body = '<p>Original</p>';
        $original->textBody = 'Original';
        $original->autoText = true;
        $original->includeAttachments = false;

        $revision = $this->createStub(TemplateRevision::class);
        $templates = $this->createMock(TemplateRepository::class);
        $templates->expects(self::once())
            ->method('resolveRevision')
            ->with('freeform', 'freeform.form-uid.notification-uid', null, null)
            ->willReturn($revision);
        $templates->method('snapshot')->with($revision)->willReturn(['subject' => 'Snapshot']);

        $renderer = $this->createMock(TemplateRenderer::class);
        $renderer->expects(self::once())
            ->method('render')
            ->with(['subject' => 'Snapshot'], [], false)
            ->willReturn(new RenderedTemplate(
                subject: 'Managed notification',
                preheader: '',
                html: '<p>Managed HTML</p>',
                text: 'Managed text',
            ));

        $event = new PrepareNotificationEvent($form, $notification, null, $original);
        $method = new ReflectionMethod(FreeformAdapter::class, 'prepare');
        $method->invoke(new FreeformAdapter($templates, $renderer), $event);

        $managed = $event->getTemplate();
        self::assertNotSame($original, $managed);
        self::assertSame('Managed notification', $managed?->subject);
        self::assertSame('<p>Managed HTML</p>', $managed?->body);
        self::assertSame('Managed text', $managed?->textBody);
        self::assertFalse($managed?->autoText);
        self::assertNull($managed?->wrapperId);
    }
}
