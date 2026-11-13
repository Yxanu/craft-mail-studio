<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Tests\Unit;

use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Nodes\Field;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Yxanu\MailStudio\MailStudio;
use Yxanu\MailStudio\Settings;

class SettingsTest extends TestCase
{
    public function test_craft_beta_settings_ui_exposes_all_three_fields(): void
    {
        $nodes = (new MailStudio(new Container))->settingsUi()->nodes();

        self::assertCount(3, $nodes);
        self::assertContainsOnlyInstancesOf(Field::class, $nodes);
        self::assertInstanceOf(Lightswitch::class, $nodes[0]->getControl());
        self::assertInstanceOf(Lightswitch::class, $nodes[1]->getControl());
        self::assertInstanceOf(Text::class, $nodes[2]->getControl());
    }

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
