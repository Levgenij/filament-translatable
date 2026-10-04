# Changelog

All notable changes to `filament-translatable` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Missing translation badge on locale tabs whose fields are all blank, with a "No translation" tooltip. Blank is decided by the `blank` config, the same rules as saving. The marked tab button gets a `data-missing-translation` attribute for styling.
- `missing_translation_badge` config option (`enabled`, `label`, `color`).
- Package translations (`en`, `uk`) under the `filament-translatable` namespace.

### Fixed
- `tab_label_format` config option is now applied to locale tab labels.

### Upgrade notes
- The badge is enabled by default. Set `missing_translation_badge.enabled` to `false` to hide it.
- An empty RichEditor submits `<p></p>`, which is content while `blank.strip_tags` is `false`. Enable `strip_tags` so empty rich text locales are marked and not stored.

## [3.0.0] - 2026-10-04

### Changed (breaking)
- `TranslatableSchemaTransformer::prepareTranslationsForForm()` reads stored translation rows only. Missing locales stay empty instead of showing the fallback locale, and model accessors are no longer applied to form values.
- Saving a locale whose fields are all blank no longer creates an empty translation row. On edit, the existing row for that locale is deleted.

### Upgrade notes
- Required fields stay required in every locale tab. Before, the fallback pre-filled missing locales, so a record could be saved without entering them. Now each missing translation must be filled in before saving.

### Added
- `TranslatableSchemaTransformer::saveTranslations()` - shared save logic for pages with custom record handling.
- `TranslatableSchemaTransformer::isBlank()` - blank check driven by the `blank` config.
- `blank` config option (`strip_tags`, `content_tags`, `invisible_characters`) to define what counts as a blank value. `strip_tags` is disabled by default, so any HTML markup counts as content. When enabled, a value with only an image or an embed is still not blank.

## [2.0.0] - 2026-01-29

### Changed
- **Filament 5 only** – Dropped support for Filament 3 and 4. This version targets Filament 5 (Schema API).
- `HasTranslatableFields::form()` now uses `Filament\Schemas\Schema` instead of `Filament\Forms\Form`.
- PHP 8.2+ and Laravel 11+ required.

## [1.0.0] - 2024-12-25

### Added
- Initial release
- Automatic detection of translatable fields from model's `$translatable` property
- Language tabs for multiple locales
- Locale badges next to field labels
- Support for Filament v3 and v4
- Single locale mode (no tabs/badges when only one locale configured)
- `TranslatableResource` trait for Resource classes
- `HasTranslatableFields` trait for CreateRecord and EditRecord pages
- Configuration file for custom locales and badge styling
- Support for container components (Section, Grid, Group, etc.)
- Recursive schema transformation for nested components

### Dependencies
- PHP 8.1+
- Filament 3.0+ or 4.0+
- levgenij/laravel-translatable 3.0+

