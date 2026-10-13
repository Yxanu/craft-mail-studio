<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yxanu\MailStudio\MailStudio;
use Yxanu\MailStudio\Settings;

class SettingsTest extends TestCase
{
    public function test_craft_6_settings_factory_returns_independent_defaults(): void
    {
        $settings = MailStudio::config();

        self::assertInstanceOf(Settings::class, $settings);
        self::assertTrue($settings->enableSystemMessages);
        self::assertTrue($settings->enableFreeform);
        self::assertSame('en-US', $settings->defaultLocale);

        $settings->defaultLocale = 'de-DE';
        self::assertSame('en-US', MailStudio::config()->defaultLocale);
    }
}
