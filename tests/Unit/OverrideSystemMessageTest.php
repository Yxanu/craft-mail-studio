<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Tests\Unit;

use CraftCms\Cms\SystemMessage\Mailables\SystemMessageMailable;
use Illuminate\Mail\Events\MessageSending;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;
use Yxanu\MailStudio\Data\RenderedTemplate;
use Yxanu\MailStudio\Listeners\OverrideSystemMessage;
use Yxanu\MailStudio\Models\TemplateRevision;
use Yxanu\MailStudio\Services\TemplateRenderer;
use Yxanu\MailStudio\Services\TemplateRepository;

class OverrideSystemMessageTest extends TestCase
{
    public function test_it_accepts_the_laravel_13_mailable_instance_in_event_data(): void
    {
        $revision = $this->createStub(TemplateRevision::class);
        $templates = $this->createMock(TemplateRepository::class);
        $templates->expects(self::once())
            ->method('resolveRevision')
            ->with('craft-system', 'craft.system.test_email', null, 'en-US')
            ->willReturn($revision);
        $templates->expects(self::once())
            ->method('snapshot')
            ->with($revision)
            ->willReturn(['subject' => 'Snapshot']);

        $renderer = $this->createMock(TemplateRenderer::class);
        $renderer->expects(self::once())
            ->method('render')
            ->with(['subject' => 'Snapshot'], ['settings' => 'SMTP'])
            ->willReturn(new RenderedTemplate(
                subject: 'Mail Studio system override',
                preheader: '',
                html: '<p>Managed HTML</p>',
                text: 'Managed text',
            ));

        $message = new Email;
        $event = new MessageSending($message, [
            '__laravel_mailable' => new SystemMessageMailable('test_email'),
            'key' => 'test_email',
            'variables' => ['settings' => 'SMTP'],
            'language' => 'en-US',
            'siteId' => null,
        ]);

        (new OverrideSystemMessage($templates, $renderer))->handle($event);

        self::assertSame('Mail Studio system override', $message->getSubject());
        self::assertSame('<p>Managed HTML</p>', $message->getHtmlBody());
        self::assertSame('Managed text', $message->getTextBody());
    }
}
