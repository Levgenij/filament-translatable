# Changelog

All notable changes to `filament-translatable` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

