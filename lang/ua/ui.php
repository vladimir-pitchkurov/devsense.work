<?php

return [
    'nav' => [
        'home' => 'Головна',
        'php_short' => 'PHP',
        'php_guides' => 'PHP-гайди',
        'tools' => 'Інструменти',
    ],
    'footer' => [
        'branch' => 'Гілка',
    ],
    'a11y' => [
        'theme_switcher' => 'Поточна тема',
        'language_select' => 'Оберіть мову',
    ],
    'errors' => [
        'php_guide_missing' => 'Гайд для PHP :version ще не опублікований або недоступний.',
    ],
    'php_show' => [
        'back_to_guides' => 'Усі гайди з версій PHP',
        'nav_aria' => 'Навігація гайдами PHP',
    ],
    'php_index' => [
        'title' => 'Гайди з версій PHP | DevSense',
        'description' => 'Матеріали з оновлення з PHP 7.3 до 8.5: синтаксис, міграція, deprecations і зворотна несумісність — із прикладами.',
        'hero_title' => 'Гайди з версій PHP',
        'hero_lead' => 'Прослідковуємо еволюцію мови від PHP 7.3 до актуальних релізів PHP 8.x — із практичними прикладами та чеклістами міграції.',
        'cta' => 'Відкрити гайд',
        'cards' => [
            'v73' => [
                'title' => 'PHP 7.3 — Шліфування синтаксису перед 7.4',
                'excerpt' => 'Гнучкий heredoc/nowdoc, кінцеві коми у викликах, `JsonException`, `is_countable`, `array_key_first`/`last`, PCRE2, Argon2id і тонкі BC (`ArrayAccess`, посилання, `continue` у `switch`).',
            ],
            'v74' => [
                'title' => 'PHP 7.4 — Останній мінор у гілці 7.x',
                'excerpt' => 'Типізовані властивості, arrow functions, FFI, preload OPcache, пара `__serialize` / `__unserialize` і типові пастки BC.',
            ],
            'v80' => [
                'title' => 'PHP 8.0 — Велике оновлення мови',
                'excerpt' => 'Іменовані аргументи, match, атрибути, JIT, union-типи, nullsafe `?->` і суворіша поведінка стандартної бібліотеки.',
            ],
            'v81' => [
                'title' => 'PHP 8.1 — Переліки та readonly',
                'excerpt' => 'Enums, readonly-властивості, fibers, перетин типів, first-class callable і посилення вимог порівняно з 8.0.',
            ],
            'v82' => [
                'title' => 'PHP 8.2 — Типи та динамічні властивості',
                'excerpt' => 'Readonly-класи, DNF-типи, окремі `null`/`false`/`true`, `#[SensitiveParameter]`, розширення Random, deprecations динамічних властивостей.',
            ],
            'v83' => [
                'title' => 'PHP 8.3 — Точність і JSON',
                'excerpt' => '`#[Override]`, типізовані константи класів, `json_validate`, `str_increment` / `str_decrement` і дрібні зміни runtime під навантаженням.',
            ],
            'v84' => [
                'title' => 'PHP 8.4 — Property hooks і lazy objects',
                'excerpt' => 'Хуки властивостей, асиметрична видимість, lazy objects, новий API `Dom\*`, `#[Deprecated]` — і що прибрати до наступного кроку.',
            ],
            'v85' => [
                'title' => 'PHP 8.5 — Конвеєри та посилення платформи',
                'excerpt' => 'Оператор `|>`, `#[\\NoDiscard]`, замикання в константних виразах, ext/uri, суворіші filter/PDO/Opcache.',
            ],
        ],
    ],
    'tools' => [
        'sail' => [
            'title' => 'Laravel Sail: еволюція локального середовища | DevSense',
            'heading' => 'Еволюція локальної розробки: навіщо Laravel Sail',
            'lead' => 'Як я прийшов від розрізненого налаштування PHP до Docker-оточення і чому обгортка від Laravel виявилась зручним практичним рішенням.',
        ],
    ],
];
