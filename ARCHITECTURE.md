# Architecture

## Boundaries

- `SourceRegistry` owns explicit semantic source contracts.
- `SourceCatalog` adds adapter-discovered Craft and Freeform sources for the CP.
- `TemplateRepository` owns drafts, variants, mappings, immutable revisions, and fallback resolution.
- `TemplateRenderer` is the only HTML/plain-text rendering boundary.
- `ManagedMailableFactory` validates a source and pins a revision snapshot before queueing or sending.
- `Integrations` and `Listeners` are thin adapters around alpha or third-party APIs.

The editor sends versioned JSON documents. It never sends executable templates, CSS, or arbitrary HTML. Rich-text HTML is sanitized server-side even when it originates from Tiptap.

## Data Model

`mailstudio_templates` is the stable identity. A template owns one or more site/locale variants. Each variant has a mutable draft and an active immutable revision. Brand configuration is copied into every revision, so a queued mailable cannot change when a brand profile is edited later.

Mappings connect `(adapter, source key, site, locale)` to a template. Resolution uses this order:

1. Exact site and locale.
2. Site and its configured language.
3. Global template and Mail Studio's default locale.
4. Global default.
5. The original source email or registered API fallback.

## Security

- CP endpoints require explicit Mail Studio permissions and Craft CSRF protection.
- Source API calls validate required variables before creating a mailable.
- Tokens are simple dotted paths and can be restricted to a source allowlist.
- Rich text has a fixed tag/attribute allowlist; URL schemes are validated.
- Mail Studio stores definitions and revisions, never recipient data or rendered mail payloads.
- Render/adapter failures are logged without replacing an unknown or failed source email.

## Alpha Isolation

Craft system-message interception and Freeform event handling live in separate adapter classes. When either upstream API changes, core persistence, renderer, public source contracts, and the CP DTOs remain isolated.
