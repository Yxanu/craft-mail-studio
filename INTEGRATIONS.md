# Integrations

## Craft System Messages

Mail Studio discovers current system message keys and maps them using `craft.system.<key>`. A global Laravel `MessageSending` listener only handles `SystemMessageMailable`. With no active mapping, or after any render failure, the original Craft message remains untouched.

## Freeform 6

The Pro adapter uses `NotificationInterface::PREPARE_NOTIFICATION` and identifies a notification as:

```text
freeform.<formUid>.<notificationUid>
```

Admin and customer notifications therefore remain independent. Mail Studio replaces a cloned `NotificationTemplate`; it does not alter Freeform records. If Freeform is unavailable, uninstalled, or fails during discovery, the rest of Mail Studio continues to work.

The currently tested harness package is Freeform `6.0.0-beta.4`. Its Craft-6 installer currently requires a local migration workaround documented in the harness README.

## Custom Application Sources

Application code must register a `SourceDefinition` and explicitly request a mailable from `ManagedMailableFactory`. Mail Studio deliberately does not rewrite arbitrary Laravel mailables, because they do not expose a reliable business source key.

## Project Presets

Projects may register reusable presets without adding branded content to this package:

```php
app(\Yxanu\MailStudio\Services\PresetLibrary::class)->register('project-receipt', [
    'name' => 'Project receipt',
    'description' => 'Project-local starting point.',
    'subject' => 'Receipt {{ reference }}',
    'preheader' => '',
    'document' => ['version' => 1, 'blocks' => []],
]);
```
