# Mail Studio for Craft CMS 6

Published by **wolkano**. Support: [dev@tosa.io](mailto:dev@tosa.io).

Mail Studio manages source-aware transactional email content in the Craft control panel. Editors compose constrained blocks, preview HTML and plain text, send tests, then publish immutable revisions for Craft system messages, Freeform notifications, or explicit application sources.

> Craft CMS 6 is unreleased. Mail Studio `0.1.x` is an alpha development line and its adapters may change with Craft.

## Requirements

- Craft CMS `^6.0.0-alpha.20` (native settings factory and form API)
- PHP `^8.5`
- Node.js 22+ only when rebuilding control panel assets

## Installation

For local path development:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "plugins/*",
      "options": { "symlink": true }
    }
  ]
}
```

```bash
composer require wolkano/craft-mail-studio:@dev
php artisan craft:plugin:install mail-studio pro
```

The compiled files under `public/build` are part of the package. Consumers do not need Node.js.

For the public alpha package on Packagist, the consuming Craft 6 project must permit alpha dependencies:

```bash
composer config minimum-stability alpha
composer config prefer-stable true
composer require wolkano/craft-mail-studio:^0.1@alpha
```

## Public API

Register a semantic source during application boot:

```php
use Yxanu\MailStudio\Data\SourceDefinition;
use Yxanu\MailStudio\Services\SourceRegistry;

app(SourceRegistry::class)->register(new SourceDefinition(
    key: 'musicshop.download-link',
    label: 'Music download link',
    variables: [
        'customer.name' => ['label' => 'Customer', 'type' => 'string', 'required' => true],
        'downloadUrl' => ['label' => 'Download URL', 'type' => 'url', 'required' => true],
    ],
    sampleData: fn () => [
        'customer' => ['name' => 'Ada Lovelace'],
        'downloadUrl' => 'https://example.test/download/demo',
    ],
    defaultTemplate: [
        'subject' => 'Your download is ready',
        'document' => ['version' => 1, 'blocks' => []],
    ],
));
```

Create a mailable from the active published revision:

```php
use Illuminate\Support\Facades\Mail;
use Yxanu\MailStudio\Services\ManagedMailableFactory;

$mailable = app(ManagedMailableFactory::class)->make(
    'musicshop.download-link',
    $variables,
    siteId: $siteId,
    locale: 'de-DE',
);

$mailable->replyTo('support@example.test')->attach($invoicePath);
Mail::to($recipient)->send($mailable);
```

The factory validates required variables before sending and pins the returned mailable to the active revision snapshot. Unknown sources, missing variables, and missing published fallbacks throw explicit exceptions.

## Editions

**Free** includes Craft system messages, the public PHP API, one brand profile, starter templates, draft/publish, previews, and test delivery.

**Pro** adds Freeform mappings, multiple brand profiles, site/language variants and fallback, revision rollback, reusable project presets, and JSON import/export. Development and staging environments can exercise Pro through Craft's standard trial behavior.

## Content Model

Templates contain a subject, preheader, and versioned JSON document. Supported blocks are heading, rich text, button, image, callout, divider/spacing, and key/value list. Mail Studio renders deterministic one-column table HTML with inline styles and an independent plain-text body.

Free HTML, arbitrary CSS, scripts, Twig control structures, and MJML are intentionally excluded. Rich text uses a fixed element allowlist. Tokens must be simple paths declared by the selected source.

## Development

```bash
composer install
npm ci
npm run build
vendor/bin/phpunit
vendor/bin/pint --test
```

Built assets must be committed after editor changes. See [ARCHITECTURE.md](ARCHITECTURE.md) and [INTEGRATIONS.md](INTEGRATIONS.md) for extension boundaries.

## Status

The current pre-release is [`0.1.0-alpha.3`](https://github.com/Yxanu/craft-mail-studio/releases/tag/0.1.0-alpha.3), tested with Craft `6.0.0-alpha.20`. See [CHANGELOG.md](CHANGELOG.md) for changes and compatibility notes.

The `0.1.x` alpha line is published as `wolkano/craft-mail-studio`. The original `0.1.0-alpha.1` tag predates the publisher change. Craft Plugin Store approval and edition pricing remain pending until Craft 6 APIs stabilize and Pixel & Tonic approves the alpha-only dependencies and edition split.
