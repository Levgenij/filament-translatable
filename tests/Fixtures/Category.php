<?php

namespace Levgenij\FilamentTranslatable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Levgenij\LaravelTranslatable\Translatable;

class Category extends Model
{
    use Translatable;

    public $timestamps = false;

    protected $guarded = [];

    public array $translatable = ['slug'];
}
