# Filament Translatable

[![Latest Version on Packagist](https://img.shields.io/packagist/v/levgenij/filament-translatable.svg?style=flat-square)](https://packagist.org/packages/levgenij/filament-translatable)
[![License](https://img.shields.io/packagist/l/levgenij/filament-translatable.svg?style=flat-square)](https://github.com/levgenij/filament-translatable/blob/main/LICENSE)

Seamless multilingual support for Filament Resources with automatic translatable field generation based on [levgenij/laravel-translatable](https://github.com/levgenij/laravel-translatable).

![Filament Translatable Screenshot](docs/images/screenshot.png)

## 📦 Version Compatibility

Please install the version that matches your Filament version:

| Filament Version | Package Version | Branch | Installation |
|------------------|-----------------|--------|--------------|
| **Filament v5.x** | `^2.0`          | `main`   | `composer require levgenij/filament-translatable:^2.0` |
| **Filament v3/v4** | `^1.0`          | `v1` | `composer require levgenij/filament-translatable:^1.0` |

> **Note:** This documentation is for **Filament v5**. If you are using Filament v3 or v4, please switch to the v1 documentation.

## Features

- **Zero Configuration** - Translatable fields are detected automatically from the model
- **Language Tabs** - Fields are grouped into tabs for each locale
- **Locale Badges** - Visual indicators next to field labels: `Title [EN]`
- **Clean Form Schema** - No wrappers or special syntax needed
- **Single Locale Mode** - No tabs or badges when only one locale is configured
- **Filament 5** - Built for Filament 5 (Schema API)

## Requirements

- PHP 8.2+
- Laravel 11+
- Filament 5.0+
- levgenij/laravel-translatable 3.0+

## Installation

Install the package via Composer:

```bash
composer require levgenij/filament-translatable
```

The package will auto-register its service provider.

### Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=filament-translatable-config
```

## Quick Start

### 1. Configure Your Model

Your model must use the `Translatable` trait from `levgenij/laravel-translatable`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Levgenij\LaravelTranslatable\Translatable;

class Category extends Model
{
    use Translatable;

    protected $fillable = [
        'parent_id',
        'is_active',
        'sort',
        // Translatable fields must also be in fillable
        'title',
        'slug',
        'description',
    ];

    /**
     * Translatable attributes.
     */
    public array $translatable = [
        'title',
        'slug',
        'description',
        'meta_title',
        'meta_description',
    ];
}
```

### 2. Add Trait to Resource

```php
<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Levgenij\FilamentTranslatable\Concerns\TranslatableResource;

class CategoryResource extends Resource
{
    use TranslatableResource;

    protected static ?string $model = Category::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                // Translatable fields (detected from $model->translatable)
                TextInput::make('title')
                    ->label('Title')
                    ->required(),

                TextInput::make('slug')
                    ->label('Slug'),

                Textarea::make('description')
                    ->label('Description'),

                // Non-translatable fields (remain unchanged)
                Toggle::make('is_active')
                    ->label('Active'),

                Select::make('parent_id')
                    ->label('Parent Category')
                    ->options(fn () => Category::pluck('title', 'id')),
            ]),
        ]);
    }
}
```

### 3. Add Trait to Create/Edit Pages

**CreateRecord:**

```php
<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Levgenij\FilamentTranslatable\Concerns\HasTranslatableFields;

class CreateCategory extends CreateRecord
{
    use HasTranslatableFields;

    protected static string $resource = CategoryResource::class;
}
```

**EditRecord:**

```php
<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\EditRecord;
use Levgenij\FilamentTranslatable\Concerns\HasTranslatableFields;

class EditCategory extends EditRecord
{
    use HasTranslatableFields;

    protected static string $resource = CategoryResource::class;
}
```

That's it! Your form will now automatically display translatable fields in language tabs.

## Configuration

### Locales

By default, the package reads locales from `config/translatable.php` (the parent package). You can override this in `config/filament-translatable.php`:

```php
<?php

return [
    // Detailed format
    'locales' => [
        'en' => ['name' => 'En', 'native' => 'English'],
        'uk' => ['name' => 'Uk', 'native' => 'українська'],
        'de' => ['name' => 'De', 'native' => 'Deutsch'],
    ],

    // Or simple array format
    'locales' => ['en', 'uk', 'de'],
];
```

### Badge Style

Customize the locale badge appearance:

```php
'badge_style' => 'display: inline-flex; padding: 2px 6px; font-size: 10px; background-color: #3b82f6; color: white; border-radius: 4px;',
```

## How It Works

### Architecture

```
src/
├── Concerns/
│   ├── TranslatableResource.php      # Trait for Resource class
│   └── HasTranslatableFields.php     # Trait for Create/Edit pages
├── Support/
│   └── TranslatableSchemaTransformer.php  # Form schema transformation
└── FilamentTranslatableServiceProvider.php
```

### Data Flow

1. **Resource** - The `TranslatableResource` trait provides the `getTranslatableAttributes()` method that reads the model's `$translatable` property.

2. **Page** - The `HasTranslatableFields` trait intercepts the `form()` method and transforms the schema:
   - Finds fields that exist in `$model->translatable`
   - Groups them into language tabs (when multiple locales)
   - Adds locale badges to labels
   - Non-translatable fields remain unchanged

3. **Saving** - The trait automatically:
   - Extracts translation data from the form
   - Saves them via `$model->saveTranslation($locale, $data)`

### Form Data Structure

Data in the form is stored in this structure:

```php
[
    // Non-translatable
    'is_active' => true,
    'parent_id' => 1,

    // Translatable
    'translations' => [
        'en' => [
            'title' => 'English Title',
            'slug' => 'english-title',
            'description' => 'English description',
        ],
        'uk' => [
            'title' => 'Ukrainian Title',
            'slug' => 'ukrainian-title',
            'description' => 'Ukrainian description',
        ],
    ],
]
```

## UI Behavior

### Multiple Languages

When multiple languages are configured, translatable fields are grouped into tabs:

```
┌─────────────────────────────────────────────────┐
│  [EN - English]  [UK - українська]              │
├─────────────────────────────────────────────────┤
│  Title [EN]                                     │
│  ┌─────────────────────────────────────────┐   │
│  │ Product Name                             │   │
│  └─────────────────────────────────────────┘   │
│                                                 │
│  Description [EN]                               │
│  ┌─────────────────────────────────────────┐   │
│  │ Product description text...              │   │
│  └─────────────────────────────────────────┘   │
└─────────────────────────────────────────────────┘

│  Is Active                                      │
│  [✓]                                            │
```

### Single Language

When only one language is configured:
- Tabs are not shown
- Badges are not shown
- Fields are displayed normally

## Advanced Usage

### Overriding mutateFormDataBeforeFill

If you need additional logic when filling the form:

```php
class EditCategory extends EditRecord
{
    use HasTranslatableFields;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // First call parent method to load translations
        $data = parent::mutateFormDataBeforeFill($data);

        // Your additional logic
        $data['custom_field'] = $this->record->computeSomething();

        return $data;
    }
}
```

### Overriding mutateFormDataBeforeCreate/Save

```php
class CreateCategory extends CreateRecord
{
    use HasTranslatableFields;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Your logic before creation
        if (! empty($data['image'])) {
            $data['image'] = $this->processImage($data['image']);
        }

        return $data;
    }
}
```

### Custom Validation

Validation works as expected. Use the `translations.{locale}.{field}` path for custom rules:

```php
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        TextInput::make('title')
            ->required()
            ->maxLength(255)
            ->rules(['unique:categories_translations,title']),
    ]);
}
```

## Database Structure

The package works with `levgenij/laravel-translatable` which uses separate translation tables:

```
categories                    categories_translations
┌────┬───────────┬────────┐   ┌─────────────┬────────┬────────────────┬───────┐
│ id │ is_active │ parent │   │ category_id │ locale │ title          │ slug  │
├────┼───────────┼────────┤   ├─────────────┼────────┼────────────────┼───────┤
│ 1  │ true      │ null   │   │ 1           │ en     │ Electronics    │ elec  │
│ 2  │ true      │ 1      │   │ 1           │ uk     │ Електроніка    │ elek  │
└────┴───────────┴────────┘   │ 2           │ en     │ Phones         │ phone │
                              │ 2           │ uk     │ Телефони       │ tel   │
                              └─────────────┴────────┴────────────────┴───────┘
```

## Troubleshooting

### Fields are not transformed

Make sure that:
1. Resource has `use TranslatableResource;`
2. Page has `use HasTranslatableFields;`
3. Model has `$translatable` property with correct fields
4. Model uses `use Levgenij\LaravelTranslatable\Translatable`

### Translations are not saved

Make sure that:
1. Table `{table}_translations` exists
2. Fields are in model's `$fillable`
3. Fields are in model's `$translatable`

### Error "Call to undefined method saveTranslation"

Model does not use the `Levgenij\LaravelTranslatable\Translatable` trait.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email levgenij@example.com instead of using the issue tracker.

## Credits

- [Levgenij](https://github.com/levgenij)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

