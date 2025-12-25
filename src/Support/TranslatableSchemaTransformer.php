<?php

namespace Levgenij\FilamentTranslatable\Support;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Tabs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use ReflectionClass;

/**
 * Automatically transforms Filament form schema to handle translatable fields.
 *
 * Features:
 * - Detects translatable fields from model's $translatable property
 * - Wraps them in language tabs (when multiple locales are configured)
 * - Adds locale badges to field labels
 * - Non-translatable fields remain unchanged
 *
 * The transformation is called automatically by HasTranslatableFields trait.
 * Your Resource form schema stays clean - just use normal field names.
 */
final class TranslatableSchemaTransformer
{
    /**
     * Transform form schema to handle translatable fields.
     *
     * @param  array<Component>  $schema
     * @param  array<string>  $translatableAttributes
     * @return array<Component>
     */
    public static function transform(array $schema, array $translatableAttributes): array
    {
        $locales = self::getLocales();

        // Single locale - just modify state paths, no tabs
        if (count($locales) <= 1) {
            $locale = array_key_first($locales) ?? app()->getLocale();

            return self::transformSchemaForSingleLocale($schema, $translatableAttributes, $locale);
        }

        // Multiple locales - create tabs for translatable fields
        return self::transformSchemaForMultipleLocales($schema, $translatableAttributes);
    }

    /**
     * Transform schema for single locale mode (no badges).
     *
     * @param  array<Component>  $schema
     * @param  array<string>  $translatableAttributes
     * @return array<Component>
     */
    private static function transformSchemaForSingleLocale(array $schema, array $translatableAttributes, string $locale): array
    {
        $result = [];

        foreach ($schema as $component) {
            $result[] = self::processComponent($component, $translatableAttributes, $locale, false);
        }

        return $result;
    }

    /**
     * Transform schema for multiple locales mode.
     *
     * @param  array<Component>  $schema
     * @param  array<string>  $translatableAttributes
     * @return array<Component>
     */
    private static function transformSchemaForMultipleLocales(array $schema, array $translatableAttributes): array
    {
        $result = [];
        $translatableGroup = [];

        foreach ($schema as $component) {
            if (self::isTranslatableField($component, $translatableAttributes)) {
                // Collect translatable fields
                $translatableGroup[] = $component;
            } else {
                // Insert accumulated translatable fields as tabs
                if (! empty($translatableGroup)) {
                    $result[] = self::createLocaleTabs($translatableGroup, $translatableAttributes);
                    $translatableGroup = [];
                }

                // Process nested containers recursively
                $result[] = self::processContainerComponent($component, $translatableAttributes);
            }
        }

        // Don't forget remaining translatable fields
        if (! empty($translatableGroup)) {
            $result[] = self::createLocaleTabs($translatableGroup, $translatableAttributes);
        }

        return $result;
    }

    /**
     * Check if component is a translatable field.
     *
     * @param  array<string>  $translatableAttributes
     */
    private static function isTranslatableField(Component $component, array $translatableAttributes): bool
    {
        if (! $component instanceof Field) {
            return false;
        }

        return in_array($component->getName(), $translatableAttributes, true);
    }

    /**
     * Process a single component for a specific locale.
     *
     * @param  array<string>  $translatableAttributes
     */
    private static function processComponent(Component $component, array $translatableAttributes, string $locale, bool $addBadge): Component
    {
        if ($component instanceof Field && in_array($component->getName(), $translatableAttributes, true)) {
            return self::cloneFieldForLocale($component, $locale, $addBadge);
        }

        // Process nested containers
        return self::processContainerComponent($component, $translatableAttributes, $locale, $addBadge);
    }

    /**
     * Process container components (Section, Grid, Group, etc.) recursively.
     *
     * @param  array<string>  $translatableAttributes
     */
    private static function processContainerComponent(Component $component, array $translatableAttributes, ?string $locale = null, bool $addBadge = false): Component
    {
        if (! method_exists($component, 'getChildComponents') || ! method_exists($component, 'schema')) {
            return $component;
        }

        $children = $component->getChildComponents();

        if (empty($children)) {
            return $component;
        }

        if ($locale !== null) {
            // Single locale mode - transform children for that locale
            $transformedChildren = [];
            foreach ($children as $child) {
                $transformedChildren[] = self::processComponent($child, $translatableAttributes, $locale, $addBadge);
            }
            $component->schema($transformedChildren);
        } else {
            // Multiple locale mode - full transformation
            $transformedChildren = self::transformSchemaForMultipleLocales($children, $translatableAttributes);
            $component->schema($transformedChildren);
        }

        return $component;
    }

    /**
     * Create locale tabs for a group of translatable fields.
     *
     * @param  array<Component>  $fields
     * @param  array<string>  $translatableAttributes
     */
    private static function createLocaleTabs(array $fields, array $translatableAttributes): Tabs
    {
        $locales = self::getLocales();
        $tabs = [];

        foreach ($locales as $code => $name) {
            $tabSchema = [];

            foreach ($fields as $field) {
                $tabSchema[] = self::cloneFieldForLocale($field, $code, true);
            }

            $tabs[] = Tabs\Tab::make($code)
                ->label(mb_strtoupper($code).' - '.$name)
                ->schema($tabSchema);
        }

        return Tabs::make('locale_tabs_'.uniqid())
            ->tabs($tabs)
            ->contained(false)
            ->extraAttributes(['class' => 'translatable-locale-tabs']);
    }

    /**
     * Clone a field for a specific locale.
     */
    private static function cloneFieldForLocale(Field $field, string $locale, bool $addBadge): Field
    {
        $originalName = $field->getName();
        $newStatePath = "translations.{$locale}.{$originalName}";

        // Clone the field
        $cloned = clone $field;

        // Update state path using reflection (statePath is protected)
        $reflection = new ReflectionClass($cloned);

        // Set the statePath property
        if ($reflection->hasProperty('statePath')) {
            $property = $reflection->getProperty('statePath');
            $property->setAccessible(true);
            $property->setValue($cloned, $newStatePath);
        }

        // Also need to update the name for proper form binding
        if ($reflection->hasProperty('name')) {
            $property = $reflection->getProperty('name');
            $property->setAccessible(true);
            $property->setValue($cloned, $newStatePath);
        }

        // Add locale badge
        if ($addBadge) {
            self::addLocaleBadge($cloned, $locale);
        }

        return $cloned;
    }

    /**
     * Add locale badge to field label (after label text).
     */
    private static function addLocaleBadge(Field $field, string $locale): void
    {
        if (! method_exists($field, 'label') || ! method_exists($field, 'getLabel')) {
            return;
        }

        $badge = self::localeBadge($locale);
        $existingLabel = $field->getLabel();
        $originalLabelText = '';

        // Extract original label text for validation attribute (before adding badge)
        if ($existingLabel instanceof HtmlString) {
            // Remove any existing badges and get clean text
            $htmlContent = $existingLabel->toHtml();
            $originalLabelText = strip_tags(preg_replace('/<span[^>]*class="locale-badge"[^>]*>.*?<\/span>/', '', $htmlContent));
            $originalLabelText = trim($originalLabelText);
        } elseif (is_string($existingLabel) && ! empty($existingLabel)) {
            $originalLabelText = $existingLabel;
        } else {
            $originalLabelText = mb_strtoupper($locale);
        }

        // Set validation attribute to use clean text without HTML badge
        if (method_exists($field, 'validationAttribute') && ! empty($originalLabelText)) {
            $field->validationAttribute($originalLabelText);
        }

        // Add badge to display label
        if ($existingLabel instanceof HtmlString) {
            $field->label(new HtmlString($existingLabel->toHtml().' '.$badge));
        } elseif (is_string($existingLabel) && ! empty($existingLabel)) {
            $field->label(new HtmlString($existingLabel.' '.$badge));
        } else {
            $field->label(new HtmlString(mb_strtoupper($locale).' '.$badge));
        }
    }

    /**
     * Generate locale badge HTML.
     */
    private static function localeBadge(string $locale): string
    {
        $code = mb_strtoupper($locale);
        $badgeStyle = config('filament-translatable.badge_style', self::getDefaultBadgeStyle());

        return '<span class="locale-badge" style="'.$badgeStyle.'">'.$code.'</span>';
    }

    /**
     * Get default badge inline style.
     */
    private static function getDefaultBadgeStyle(): string
    {
        return 'display: inline-flex; align-items: center; padding: 2px 8px; font-size: 11px; font-weight: 600; line-height: 1; border-radius: 9999px; background-color: rgb(var(--primary-500)); color: white; margin-left: 8px;';
    }

    /**
     * Get available locales.
     *
     * @return array<string, string>
     */
    public static function getLocales(): array
    {
        // First check package-specific config
        $supportedLocales = config('filament-translatable.locales');

        // Fall back to parent translatable config
        if (empty($supportedLocales)) {
            $supportedLocales = config('translatable.supportedLocales', []);
        }

        if (empty($supportedLocales)) {
            return [app()->getLocale() => app()->getLocale()];
        }

        $locales = [];
        foreach ($supportedLocales as $code => $data) {
            if (is_array($data)) {
                $locales[$code] = $data['native'] ?? $data['name'] ?? $code;
            } else {
                // Support simple array format: ['en', 'uk']
                $locales[$data] = $data;
            }
        }

        return $locales;
    }

    /**
     * Get locale codes only.
     *
     * @return array<string>
     */
    public static function getLocaleCodes(): array
    {
        return array_keys(self::getLocales());
    }

    /**
     * Prepare translations data for form fill.
     *
     * @param  array<string>  $translatableAttributes
     * @return array<string, array<string, mixed>>
     */
    public static function prepareTranslationsForForm(Model $model, array $translatableAttributes): array
    {
        $translations = [];
        $locales = self::getLocaleCodes();

        foreach ($locales as $locale) {
            $translation = $model->translate($locale);
            $translations[$locale] = [];

            foreach ($translatableAttributes as $attribute) {
                $translations[$locale][$attribute] = $translation?->{$attribute} ?? '';
            }
        }

        return $translations;
    }

    /**
     * Extract translations from form data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>>
     */
    public static function extractTranslations(array $data): array
    {
        return $data['translations'] ?? [];
    }

    /**
     * Remove translations key from form data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function removeTranslationsFromData(array $data): array
    {
        unset($data['translations']);

        return $data;
    }
}

