<?php

return [
    'nav' => [
        'home' => 'Начало',
        'php_short' => 'PHP',
        'php_guides' => 'PHP ръководства',
        'tools' => 'Инструменти',
    ],
    'footer' => [
        'branch' => 'Клон',
    ],
    'a11y' => [
        'theme_switcher' => 'Текуща тема',
        'language_select' => 'Изберете език',
    ],
    'errors' => [
        'php_guide_missing' => 'Ръководството за PHP :version все още не е публикувано или не е налично.',
    ],
    'php_index' => [
        'title' => 'Ръководства по версии на PHP | DevSense',
        'description' => 'Материали за надграждане от PHP 7.4 до 8.5: синтаксис, миграция, deprecations и несъвместимости — с примери.',
        'hero_title' => 'Ръководства по версии на PHP',
        'hero_lead' => 'Проследяваме еволюцията на езика от късния PHP 7.x до актуалните PHP 8.x — с практични примери и чеклисти за миграция.',
        'cta' => 'Отвори ръководството',
        'cards' => [
            'v74' => [
                'title' => 'PHP 7.4 — Последният минор в клона 7.x',
                'excerpt' => 'Типизирани свойства, arrow functions, FFI, preload на OPcache, двойката `__serialize` / `__unserialize` и типични BC капани.',
            ],
            'v80' => [
                'title' => 'PHP 8.0 — Голямо обновяване на езика',
                'excerpt' => 'Именувани аргументи, match, атрибути, JIT, union типове, nullsafe `?->` и по-строго поведение на стандартната библиотека.',
            ],
            'v81' => [
                'title' => 'PHP 8.1 — Изброявания и readonly',
                'excerpt' => 'Enums, readonly свойства, fibers, сечение на типове, first-class callable и по-строги правила спрямо 8.0.',
            ],
            'v82' => [
                'title' => 'PHP 8.2 — Типове и динамични свойства',
                'excerpt' => 'Readonly класове, DNF типове, самостоятелни `null`/`false`/`true`, `#[SensitiveParameter]`, Random разширение, deprecations за динамични свойства.',
            ],
            'v83' => [
                'title' => 'PHP 8.3 — Точност и JSON',
                'excerpt' => '`#[Override]`, типизирани константи на класове, `json_validate`, `str_increment` / `str_decrement` и дребни runtime промени под натоварване.',
            ],
            'v84' => [
                'title' => 'PHP 8.4 — Property hooks и lazy objects',
                'excerpt' => 'Куки на свойства, асиметрична видимост, lazy objects, нов `Dom\*` API, `#[Deprecated]` — и какво да почистите преди следващата стъпка.',
            ],
            'v85' => [
                'title' => 'PHP 8.5 — Конвейери и стягане на платформата',
                'excerpt' => 'Оператор `|>`, `#[\\NoDiscard]`, closures в константни изрази, ext/uri, по-строги filter/PDO/Opcache.',
            ],
        ],
    ],
    'tools' => [
        'sail' => [
            'title' => 'Laravel Sail: еволюция на локалната среда | DevSense',
            'heading' => 'Еволюция на локалната разработка: защо Laravel Sail',
            'lead' => 'От разпокъсана PHP настройка към Docker-базиран поток и защо обвивката на Laravel се оказа практичен избор.',
        ],
    ],
];
