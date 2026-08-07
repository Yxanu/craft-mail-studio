<?php

declare(strict_types=1);

namespace Yxanu\MailStudio;

use CraftCms\Cms\Plugin\PluginSettings;

class Settings extends PluginSettings
{
    public bool $enableSystemMessages = true;

    public bool $enableFreeform = true;

    public string $defaultLocale = 'en-US';

    public function getRules(): array
    {
        return [
            'enableSystemMessages' => ['boolean'],
            'enableFreeform' => ['boolean'],
            'defaultLocale' => ['required', 'string', 'max:32'],
        ];
    }
}
