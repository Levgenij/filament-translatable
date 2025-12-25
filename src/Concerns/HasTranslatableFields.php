<?php

namespace Levgenij\FilamentTranslatable\Concerns;

use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Levgenij\FilamentTranslatable\Support\TranslatableSchemaTransformer;

/**
 * Universal trait for CreateRecord and EditRecord pages to enable automatic translation support.
 *
 * Automatically detects if used in CreateRecord or EditRecord and applies appropriate logic.
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
 *
 * class EditCategory extends EditRecord
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
     * Override form to transform schema for translatable fields.
     */
    public function form(Form $form): Form
    {
        $form = parent::form($form);

        $translatableAttributes = static::getResource()::getTranslatableAttributes();

        if (empty($translatableAttributes)) {
            return $form;
        }

        // Transform the schema to add locale tabs for translatable fields
        $schema = $form->getComponents();
        $transformedSchema = TranslatableSchemaTransformer::transform($schema, $translatableAttributes);

        return $form->schema($transformedSchema);
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

