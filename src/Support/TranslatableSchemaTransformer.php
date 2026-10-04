<?php

namespace Levgenij\FilamentTranslatable\Support;

use Filament\Schemas\Components\Component;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use ReflectionClass;

/**
 * Automatically transforms Filament 5 form schema to handle translatable fields.
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
     * @param  array<Field>  $fields
     * @param  array<string>  $translatableAttributes
     */
    private static function createLocaleTabs(array $fields, array $translatableAttributes): Tabs
    {
        $locales = self::getLocales();
        $labelFormat = config('filament-translatable.tab_label_format');
        $missingTranslationBadge = config('filament-translatable.missing_translation_badge');
        $tabs = [];

        foreach ($locales as $code => $name) {
            $tabSchema = [];

            foreach ($fields as $field) {
                $tabSchema[] = self::cloneFieldForLocale($field, $code, true);
            }

            $tab = Tabs\Tab::make($code)
                ->label(strtr($labelFormat, ['{CODE}' => mb_strtoupper($code), '{code}' => $code, '{name}' => $name]))
                ->schema($tabSchema);

            if ($missingTranslationBadge['enabled']) {
                self::addMissingTranslationBadge($tab, $missingTranslationBadge);
            }

            $tabs[] = $tab;
        }

        return Tabs::make('locale_tabs_'.uniqid())
            ->tabs($tabs)
            ->contained(false)
            ->extraAttributes(['class' => 'translatable-locale-tabs']);
    }

    /**
     * Mark a locale tab whose fields are all blank with a badge and a `data-missing-translation` attribute.
     *
     * @param  array{label: string, color: string}  $badge
     */
    private static function addMissingTranslationBadge(Tabs\Tab $tab, array $badge): void
    {
        $tab
            ->badge(fn (Tabs\Tab $component): ?string => self::isMissingTranslation($component) ? $badge['label'] : null)
            ->badgeColor($badge['color'])
            ->badgeTooltip(fn (): string => __('filament-translatable::translations.missing_translation'))
            ->extraAttributes(fn (Tabs\Tab $component): array => self::isMissingTranslation($component)
                ? ['data-missing-translation' => 'true']
                : []);
    }

    /**
     * Uses the state with casts applied (a RichEditor document becomes HTML), so the same
     * blank rules apply as in `saveTranslations()`.
     */
    private static function isMissingTranslation(Tabs\Tab $tab): bool
    {
        $fields = $tab->getChildComponents();

        // ponytail: Filament reads the badge and attributes about 5 times per render, so a blank
        // tab converts its RichEditor document to HTML each time. Cheap for empty documents;
        // memoize per tab and raw state if large rich content in blank tabs shows up in profiling.
        return $fields !== [] && collect($fields)->every(
            fn (Field $field): bool => self::isBlank($field->getState()),
        );
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
     * Values come from stored translation rows as saved: no fallback locale, no model accessors.
     * A locale without a stored row stays empty.
     *
     * @param  array<string>  $translatableAttributes
     * @return array<string, array<string, mixed>>
     */
    public static function prepareTranslationsForForm(Model $model, array $translatableAttributes): array
    {
        $rows = $model->translations->keyBy($model->getLocaleKey());
        $translations = [];

        foreach (self::getLocaleCodes() as $locale) {
            $stored = $rows->get($locale)?->getAttributes() ?? [];

            foreach ($translatableAttributes as $attribute) {
                $translations[$locale][$attribute] = $stored[$attribute] ?? '';
            }
        }

        return $translations;
    }

    /**
     * Save form translations for each locale.
     * A locale whose fields are all blank is not stored, and its existing row is removed.
     * On create, empty strings are dropped. On edit, they are kept so a field can be cleared.
     *
     * @param  array<string, array<string, mixed>>  $translations
     * @param  array<string>  $translatableAttributes
     */
    public static function saveTranslations(Model $model, array $translations, array $translatableAttributes, bool $isCreate): void
    {
        foreach ($translations as $locale => $attributes) {
            $attributes = Arr::only($attributes, $translatableAttributes);

            if (self::isBlank($attributes)) {
                if (! $isCreate) {
                    $model->translations()->where($model->getLocaleKey(), $locale)->delete();
                }

                continue;
            }

            $model->saveTranslation($locale, array_filter(
                $attributes,
                fn (mixed $value): bool => $value !== null && (! $isCreate || $value !== ''),
            ));
        }
    }

    /**
     * Whether a form value has no visible content.
     * Rules come from `filament-translatable.blank`.
     */
    public static function isBlank(mixed $value): bool
    {
        if (is_array($value)) {
            return collect($value)->every(fn (mixed $item): bool => self::isBlank($item));
        }

        if (is_string($value)) {
            $rules = config('filament-translatable.blank');

            if ($rules['strip_tags']) {
                $value = strip_tags($value, $rules['content_tags']);
            }

            $value = str_replace($rules['invisible_characters'], '', html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
        }

        return blank($value);
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

