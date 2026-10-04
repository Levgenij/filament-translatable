<?php

namespace Levgenij\FilamentTranslatable\Tests;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Unique;
use Levgenij\FilamentTranslatable\FilamentTranslatableServiceProvider;
use Levgenij\FilamentTranslatable\Support\TranslatableSchemaTransformer;
use Levgenij\FilamentTranslatable\Tests\Fixtures\Category;
use Levgenij\LaravelTranslatable\TranslatableServiceProvider;
use Orchestra\Testbench\TestCase;

class UniqueRuleTest extends TestCase
{
    private Category $stil;

    private Category $lampa;

    protected function getPackageProviders($app): array
    {
        return [TranslatableServiceProvider::class, FilamentTranslatableServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('categories_i18n', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 6);
            $table->string('slug');
            $table->primary(['category_id', 'locale']);
        });

        $this->stil = Category::create([], ['uk' => ['slug' => 'stil'], 'en' => ['slug' => 'table']]);
        $this->lampa = Category::create([], ['uk' => ['slug' => 'lampa']]);
    }

    public function test_duplicate_in_the_same_locale_fails_on_create(): void
    {
        $this->assertFalse($this->passes(TextInput::make('slug')->unique(), 'uk', 'stil'));
    }

    public function test_same_value_in_another_locale_passes(): void
    {
        $this->assertTrue($this->passes(TextInput::make('slug')->unique(), 'en', 'stil'));
    }

    public function test_own_record_is_ignored_on_edit(): void
    {
        $this->assertTrue($this->passes(TextInput::make('slug')->unique(ignoreRecord: true), 'uk', 'stil', $this->stil));
    }

    public function test_duplicate_of_another_record_fails_on_edit(): void
    {
        $this->assertFalse($this->passes(TextInput::make('slug')->unique(ignoreRecord: true), 'uk', 'stil', $this->lampa));
    }

    public function test_explicit_attribute_column_is_scoped_to_the_translations_table(): void
    {
        $field = TextInput::make('slug')->unique(column: 'slug', ignoreRecord: true);

        $this->assertFalse($this->passes($field, 'uk', 'lampa', $this->stil));
        $this->assertTrue($this->passes($field, 'uk', 'stil', $this->stil));
    }

    public function test_modify_rule_using_is_kept(): void
    {
        $field = TextInput::make('slug')->unique(modifyRuleUsing: fn (Unique $rule): Unique => $rule->whereNot('slug', 'stil'));

        $this->assertTrue($this->passes($field, 'uk', 'stil'));
        $this->assertFalse($this->passes($field, 'uk', 'lampa'));
    }

    public function test_rule_for_another_table_is_used_as_written(): void
    {
        $field = TextInput::make('slug')->unique(table: 'users', column: 'email');

        $this->assertSame(['unique:users,email,NULL,id'], $this->rules($field, 'uk'));
    }

    private function passes(Field $field, string $locale, string $value, ?Category $record = null): bool
    {
        return Validator::make(['value' => $value], ['value' => $this->rules($field, $locale, $record)])->passes();
    }

    /**
     * The `unique` rules of the field clone for the locale, as strings.
     *
     * @return array<string>
     */
    private function rules(Field $field, string $locale, ?Category $record = null): array
    {
        config(['filament-translatable.locales' => [$locale]]);

        [$clone] = TranslatableSchemaTransformer::transform([$field->model($record ?? Category::class)], ['slug']);

        return array_values(array_map(
            fn (Unique $rule): string => (string) $rule,
            array_filter($clone->getValidationRules(), fn (mixed $rule): bool => $rule instanceof Unique),
        ));
    }
}
