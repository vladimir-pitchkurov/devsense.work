<?php

return [
    'nav' => [
        'home' => 'Главная',
        'php_short' => 'PHP',
        'php_guides' => 'PHP Гайды',
        'tools' => 'Инструменты',
    ],
    'footer' => [
        'branch' => 'Ветка',
    ],
    'a11y' => [
        'theme_switcher' => 'Текущая тема',
        'language_select' => 'Выберите язык',
    ],
    'errors' => [
        'php_guide_missing' => 'Гайд для PHP :version ещё не опубликован или недоступен.',
    ],
    'php_show' => [
        'back_to_guides' => 'Все гайды по версиям PHP',
        'nav_aria' => 'Навигация по гайдам PHP',
    ],
    'php_index' => [
        'title' => 'Гайды по версиям PHP | DevSense',
        'description' => 'Материалы по обновлению с PHP 7.3 до 8.5: синтаксис, миграция, deprecations и обратная несовместимость — с примерами.',
        'hero_title' => 'Гайды по версиям PHP',
        'hero_lead' => 'Прослеживаем эволюцию языка от PHP 7.3 до актуальных релизов PHP 8.x — с рабочими примерами и чек-листами миграции.',
        'cta' => 'Читать гайд',
        'cards' => [
            'v73' => [
                'title' => 'PHP 7.3 — Полировка синтаксиса перед 7.4',
                'excerpt' => 'Гибкий heredoc/nowdoc, хвостовые запятые в вызовах, `JsonException`, `is_countable`, `array_key_first`/`last`, PCRE2, Argon2id и тонкие BC (`ArrayAccess`, ссылки, `continue` в `switch`).',
            ],
            'v74' => [
                'title' => 'PHP 7.4 — Последний минор в линейке 7.x',
                'excerpt' => 'Типизированные свойства, стрелочные функции, FFI, preload OPcache, пара `__serialize` / `__unserialize` и типичные ловушки BC.',
            ],
            'v80' => [
                'title' => 'PHP 8.0 — Крупное обновление языка',
                'excerpt' => 'Именованные аргументы, match, атрибуты, JIT, union-типы, nullsafe `?->` и более строгое поведение стандартной библиотеки.',
            ],
            'v81' => [
                'title' => 'PHP 8.1 — Перечисления и readonly',
                'excerpt' => 'Enums, readonly-свойства, fibers, пересечение типов, first-class callable и ужесточения относительно 8.0.',
            ],
            'v82' => [
                'title' => 'PHP 8.2 — Типы и динамические свойства',
                'excerpt' => 'Readonly-классы, DNF-типы, автономные `null`/`false`/`true`, `#[SensitiveParameter]`, расширение Random, deprecations динамических свойств.',
            ],
            'v83' => [
                'title' => 'PHP 8.3 — Точность и JSON',
                'excerpt' => '`#[Override]`, типизированные константы классов, `json_validate`, `str_increment` / `str_decrement` и мелкие runtime-изменения под нагрузкой.',
            ],
            'v84' => [
                'title' => 'PHP 8.4 — Property hooks и lazy objects',
                'excerpt' => 'Хуки свойств, асимметричная видимость, lazy objects, новый API `Dom\*`, `#[Deprecated]` — и что почистить до следующего шага.',
            ],
            'v85' => [
                'title' => 'PHP 8.5 — Конвейеры и ужесточение платформы',
                'excerpt' => 'Оператор `|>`, `#[\\NoDiscard]`, замыкания в константных выражениях, ext/uri, более строгие filter/PDO/Opcache.',
            ],
        ],
    ],
    'tools' => [
        'sail' => [
            'title' => 'Laravel Sail: эволюция среды разработки | DevSense',
            'heading' => 'Эволюция локальной разработки: зачем Laravel Sail',
            'lead' => 'Как я пришёл от разрозненной настройки PHP к Docker-окружению и почему обёртка от Laravel оказалась удачным практичным решением.',
        ],
    ],
];
