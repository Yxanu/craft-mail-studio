# Release Notes for Mail Studio

## Unreleased

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
