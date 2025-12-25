<?php

namespace Levgenij\FilamentTranslatable\Concerns;

use Levgenij\LaravelTranslatable\Translatable as TranslatableTrait;

/**
 * Trait for Filament Resources to enable translation support.
 *
 * Usage:
 * 1. Add `use TranslatableResource;` to your Resource class
 * 2. Add `use HasTranslatableFields;` to your CreateRecord and EditRecord pages
 *
 * The form schema stays the same - translatable fields are detected automatically
 * from the model's `$translatable` property.
 *
 * @example
 * ```php
 * class CategoryResource extends Resource
 * {
 *     use TranslatableResource;
 *
 *     public static function form(Form $form): Form
 *     {
 *         return $form->schema([
 *             // Translatable fields (auto-detected from model)
 *             TextInput::make('title')->required(),
 *             TextInput::make('slug'),
 *             Textarea::make('description'),
 *
 *             // Non-translatable fields (remain unchanged)
 *             Toggle::make('is_active'),
 *         ]);
 *     }
 * }
 * ```
 */
trait TranslatableResource
{
    /**
     * Get all available translatable locales.
     *
     * @return array<string>
     */
    public static function getTranslatableLocales(): array
    {
        // First check package-specific config
        $supportedLocales = config('filament-translatable.locales');

        // Fall back to parent translatable config
        if (empty($supportedLocales)) {
            $supportedLocales = config('translatable.supportedLocales', []);
        }

        if (empty($supportedLocales)) {
            return [app()->getLocale()];
        }

        return array_keys($supportedLocales);
    }

    /**
     * Get the default translatable locale.
     */
    public static function getDefaultTranslatableLocale(): string
    {
        return static::getTranslatableLocales()[0] ?? app()->getLocale();
    }

    /**
     * Get the list of translatable attributes from the model.
     *
     * @return array<string>
     */
    public static function getTranslatableAttributes(): array
    {
        $model = static::getModel();
        $instance = new $model;

        if (! in_array(TranslatableTrait::class, class_uses_recursive($instance))) {
            return [];
        }

        return $instance->translatable ?? [];
    }

    /**
     * Check if multiple locales are configured.
     */
    public static function hasMultipleTranslatableLocales(): bool
    {
        return count(static::getTranslatableLocales()) > 1;
    }
}

