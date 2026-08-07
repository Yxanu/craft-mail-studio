<?php

declare(strict_types=1);

namespace Yxanu\MailStudio;

use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\Plugin\Plugin;
use CraftCms\Cms\User\Data\Permission;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Yxanu\MailStudio\Integrations\FreeformAdapter;
use Yxanu\MailStudio\Listeners\OverrideSystemMessage;
use Yxanu\MailStudio\Services\BrandProfileRepository;

use function CraftCms\Cms\template;

class MailStudio extends Plugin
{
    public const string EDITION_FREE = 'free';

    public const string EDITION_PRO = 'pro';

    public string $schemaVersion = '0.1.0';

    public bool $hasCpSection = true;

    public bool $hasCpSettings = true;

    protected array $vite = [
        'input' => ['resources/js/app.ts'],
        'publicDirectory' => 'public',
        'buildDirectory' => 'build',
    ];

    public static function editions(): array
    {
        return [self::EDITION_FREE, self::EDITION_PRO];
    }

    public function boot(): void
    {
        /** @var Settings $settings */
        $settings = $this->getSettings();

        if ($settings->enableSystemMessages) {
            Event::listen(MessageSending::class, OverrideSystemMessage::class);
        }

        if ($settings->enableFreeform && $this->is(self::EDITION_PRO)) {
            app(FreeformAdapter::class)->register();
        }
    }

    public function getCpNavItem(): NavItem
    {
        return parent::getCpNavItem()
            ->label('Mail Studio')
            ->url('mail-studio')
            ->subnav([
                new NavItem(['label' => 'Templates', 'url' => 'mail-studio', 'id' => 'templates']),
                new NavItem(['label' => 'Sources', 'url' => 'mail-studio?view=sources', 'id' => 'sources']),
                new NavItem(['label' => 'Brand', 'url' => 'mail-studio?view=brand', 'id' => 'brand']),
            ]);
    }

    protected function getPermissions(): array
    {
        return [
            new Permission('mailStudio:view', 'View Mail Studio'),
            new Permission('mailStudio:edit', 'Edit template drafts'),
            new Permission('mailStudio:publish', 'Publish templates'),
            new Permission('mailStudio:test', 'Send test emails'),
            new Permission('mailStudio:manage', 'Manage sources, brands, and imports'),
        ];
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings;
    }

    protected function settingsHtml(): ?string
    {
        return template('mail-studio/_settings.twig', [
            'settings' => $this->getSettings(),
        ]);
    }

    protected function afterInstall(): void
    {
        app(BrandProfileRepository::class)->ensureDefault();
    }
}
