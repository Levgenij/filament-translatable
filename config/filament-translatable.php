<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Define the locales that should be available in Filament forms.
    | If left empty (null), the package will fall back to the locales
    | defined in config/translatable.php (supportedLocales).
    |
    | You can use either the detailed format:
    |
    |   'locales' => [
    |       'en' => ['name' => 'En', 'native' => 'English'],
    |       'uk' => ['name' => 'Uk', 'native' => 'українська'],
    |   ],
    |
    | Or a simple array format:
    |
    |   'locales' => ['en', 'uk', 'de'],
    |
    */

    'locales' => null,

    /*
    |--------------------------------------------------------------------------
    | Locale Badge Style
    |--------------------------------------------------------------------------
    |
    | Customize the inline CSS style for the locale badge that appears
    | next to translatable field labels. Set to null to use the default style.
    |
    | Default style uses Filament's primary color with a pill-shaped badge.
    |
    */

    'badge_style' => null,

    /*
    |--------------------------------------------------------------------------
    | Tab Label Format
    |--------------------------------------------------------------------------
    |
    | Define how the locale tabs should be labeled. Available placeholders:
    | - {CODE} - uppercase locale code (e.g., "EN")
    | - {code} - lowercase locale code (e.g., "en")
    | - {name} - native locale name (e.g., "English")
    |
    | Default: "{CODE} - {name}"
    |
    */

    'tab_label_format' => '{CODE} - {name}',

];

