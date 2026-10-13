# Release Notes for Mail Studio

## Unreleased

## 0.1.0-alpha.4 - 2026-10-13

### Fixed

- Restricted rendered button, image, rich-text link, and brand-logo URLs to HTTP, HTTPS, and root-relative paths. Unsafe schemes, protocol-relative URLs, backslashes, and control characters are rejected, including URLs supplied through tokens.
- Preserved the selected preview source when saving or publishing the same template, preventing source-specific tokens from breaking the refreshed preview.
- Fixed test bootstrap discovery when running the packaged tests from a Composer vendor installation.

### QA

- Verified a fresh Packagist ZIP installation without path repositories or symlinks on Craft `6.0.0-alpha.20`, PHP `8.5.8`, and Laravel `13.36.0`.
- Exercised draft/publish, token previews, mobile layout, Mailpit test delivery, required-variable validation, HTML escaping, and queued revision pinning with CC, Reply-To, and attachments.
- Freeform integration is not included in this clean-install QA environment.

### Compatibility

- Requires PHP `^8.5` and Craft CMS `^6.0.0-alpha.20`; no database schema changes.
- Alpha software for development use. Plugin Store approval and edition configuration remain pending.

## 0.1.0-alpha.3 - 2026-10-13

### Added

- Added the wolkano cloud-envelope plugin icon and control panel navigation mask.
- Added a larger email preview, scaled desktop/mobile previews, and preview loading feedback.
- Added regression tests for the Craft settings factory and source-independent starter templates.

### Changed

- Raised the minimum Craft version to `6.0.0-alpha.20` and adopted its native settings factory and form API.
- Integrated the editor with Craft's native Inertia control panel screen and navigation lifecycle.
- Updated Vue and Tiptap dependencies and rebuilt the committed frontend assets.
- Added npm security auditing and verification of both changed and untracked build assets to CI.

### Fixed

- Fixed editor mounting after control panel navigation and cleaned up the app when leaving the screen.
- Added unsaved-change protection for template selection, view switching, import, and control panel navigation.
- Fixed mobile layout clipping, preview sizing, keyboard focus visibility, and read-only editor controls.
- Prevented stale preview responses from replacing newer content.
- Removed undeclared source-specific tokens from generic starter templates.
- Required authentication and control panel access for the editor and API routes.
- Removed a duplicate Tiptap link extension registration.

### Compatibility

- Requires PHP `^8.5` and Craft CMS `^6.0.0-alpha.20`.
- Craft 6 and Mail Studio remain pre-release software; production use is not recommended.
- No database schema changes are included in this release.

## 0.1.0-alpha.2 - 2026-10-12

- Renamed the Composer package to `wolkano/craft-mail-studio` and identified wolkano as the publisher.
- Added `dev@tosa.io` as the package support contact.
- Allowed alpha dependencies when resolving this package for development and CI, preferring stable releases where available.
- Documented installation from Packagist and the pending Craft Plugin Store approval.

## 0.1.0-alpha.1 - 2026-08-07

- Added structured transactional email templates with HTML and plain-text rendering.
- Added source registry, managed mailable factory, Craft system-message adapter, and Freeform adapter.
- Added draft/publish revisions, site/language fallback, brand profiles, previews, test sends, and JSON import/export.
- Added Vue 3, Tiptap, and SortableJS control panel editor.
