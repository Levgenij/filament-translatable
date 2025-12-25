<?php

namespace Levgenij\FilamentTranslatable;

use Illuminate\Support\ServiceProvider;

class FilamentTranslatableServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-translatable.php',
            'filament-translatable'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-translatable.php' => config_path('filament-translatable.php'),
            ], 'filament-translatable-config');
        }
    }
}

