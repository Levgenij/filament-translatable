<?php

namespace Levgenij\FilamentTranslatable\Concerns;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Levgenij\FilamentTranslatable\Support\TranslatableSchemaTransformer;

/**
 * Trait for CreateRecord and EditRecord pages (Filament 5) using Schema API.
 *
 * Usage:
 * 1. Add `use TranslatableResource;` to your Resource class
 * 2. Add `use HasTranslatableFields;` to your CreateRecord or EditRecord page
 * 3. Write your form schema as normal - translatable fields are detected automatically!
 *
 * @example
 * ```php
 * class CreateCategory extends CreateRecord
 * {
 *     use HasTranslatableFields;
 *
 *     protected static string $resource = CategoryResource::class;
 * }
 * ```
 */
trait HasTranslatableFields
{
    /**
     * Pending translations to be saved after record creation/update.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $pendingTranslations = [];

    /**
     * Override form to transform schema for translatable fields (Schema API – Filament 5).
     */
    public function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);

        $translatableAttributes = static::getResource()::getTranslatableAttributes();

        if (empty($translatableAttributes)) {
            return $schema;
        }

        $components = $schema->getComponents();
        $transformedComponents = TranslatableSchemaTransformer::transform($components, $translatableAttributes);

        return $schema->components($transformedComponents);
    }

    /**
     * Handle record creation (for CreateRecord pages).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Extract and store translations
        $this->pendingTranslations = TranslatableSchemaTransformer::extractTranslations($data);
        $data = TranslatableSchemaTransformer::removeTranslationsFromData($data);

        // Create the record
        $record = static::getModel()::create($data);

        // Save translations
        $this->saveTranslations($record);

        return $record;
    }

    /**
     * Handle record update (for EditRecord pages).
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Extract and store translations
        $this->pendingTranslations = TranslatableSchemaTransformer::extractTranslations($data);
        $data = TranslatableSchemaTransformer::removeTranslationsFromData($data);

        // Update the record
        $record->update($data);

        // Save translations
        $this->saveTranslations($record);

        return $record;
    }

    /**
     * Mutate form data before fill (for EditRecord pages).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Only apply for EditRecord pages
        if (! $this instanceof EditRecord) {
            return $data;
        }

        $record = $this->getRecord();
        $translatableAttributes = static::getResource()::getTranslatableAttributes();

        if (empty($translatableAttributes)) {
            return $data;
        }

        // Load translations for all locales
        $data['translations'] = TranslatableSchemaTransformer::prepareTranslationsForForm(
            $record,
            $translatableAttributes
        );

        return $data;
    }

    /**
     * Save pending translations to the record.
     */
    protected function saveTranslations(Model $record): void
    {
        if (empty($this->pendingTranslations)) {
            return;
        }

        $translatableAttributes = static::getResource()::getTranslatableAttributes();
        $isCreate = $this instanceof CreateRecord;

        foreach ($this->pendingTranslations as $locale => $attributes) {
            $filteredAttributes = Arr::only($attributes, $translatableAttributes);

            // For create - filter empty values, for edit - allow clearing
            if ($isCreate) {
                $filteredAttributes = array_filter($filteredAttributes, fn ($value) => $value !== null && $value !== '');
            } else {
                $filteredAttributes = array_filter($filteredAttributes, fn ($value) => $value !== null);
            }

            if (! empty($filteredAttributes)) {
                $record->saveTranslation($locale, $filteredAttributes);
            }
        }

        $this->pendingTranslations = [];
    }
}

