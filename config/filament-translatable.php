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

    /*
    |--------------------------------------------------------------------------
    | Missing Translation Badge
    |--------------------------------------------------------------------------
    |
    | Show a badge on a locale tab when all of its fields are blank, using
    | the same rules as saving (see "Blank Values").
    | The tab button also gets a `data-missing-translation` attribute
    | that can be used to restyle the badge.
    |
    | - enabled: set to false to hide the badge.
    | - label: badge text.
    | - color: any Filament color name (e.g. "warning", "danger", "gray").
    |
    */

    'missing_translation_badge' => [
        'enabled' => true,
        'label' => '•',
        'color' => 'warning',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blank Values
    |--------------------------------------------------------------------------
    |
    | Define what counts as a blank form value. A locale whose fields are all
    | blank is not stored, and on edit its existing translation row is removed.
    |
    | - strip_tags: treat HTML markup without text as blank (e.g. "<p></p>").
    |   Disabled by default, so any markup counts as content. An empty
    |   RichEditor submits "<p></p>", so enable this when forms use it.
    | - content_tags: used with strip_tags; tags that count as content even
    |   without text, so a locale with only an image or an embed is not removed.
    |   Add "div" for RichEditor custom blocks and "span" for mentions.
    | - invisible_characters: characters ignored when looking for text.
    |   Leading and trailing whitespace is always ignored.
    |
    */

    'blank' => [
        'strip_tags' => false,
        'content_tags' => ['img', 'iframe', 'video', 'audio', 'embed', 'object', 'svg'],
        'invisible_characters' => ["\u{00A0}", "\u{200B}"],
    ],

];

