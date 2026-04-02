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
    'php_show' => [
        'back_to_guides' => 'Всички ръководства по версии на PHP',
        'nav_aria' => 'Навигация в PHP ръководствата',
    ],
    'php_index' => [
        'title' => 'Ръководства по версии на PHP | DevSense',
        'description' => 'Материали за надграждане от PHP 5.3 до 8.5: синтаксис, миграция, deprecations и несъвместимости — с примери.',
        'hero_title' => 'Ръководства по версии на PHP',
        'hero_lead' => 'Проследяваме еволюцията на езика от PHP 5.3 до актуалните PHP 8.x — с практични примери и чеклисти за миграция.',
        'cta' => 'Отвори ръководството',
        'cards' => [
            'v53' => [
                'title' => 'PHP 5.3 — Пространства от имена и затваряния',
                'excerpt' => 'Namespaces, `use`, късно статично свързване, анонимни функции, `goto`, NOWDOC, по избор GC на цикли, Phar — и BC (нови ключови думи, deprecation на `ereg*`) далеч от навици от PHP 4.',
            ],
            'v54' => [
                'title' => 'PHP 5.4 — Трейтове и `[]`',
                'excerpt' => 'Трейтове, кратък синтаксис на масиви `[]`, подсказка `callable`, `$this` в затваряния, вграден уеб сървър — премахнати magic quotes и `register_globals`, deprecated `mysql`.',
            ],
            'v55' => [
                'title' => 'PHP 5.5 — Генератори и `password_*`',
                'excerpt' => 'Генератори с `yield`, `finally`, API `password_hash`, `array_column`, `ClassName::class` — и деликатна BC при `foreach`/`list()`, която си струва регресионни тестове.',
            ],
            'v56' => [
                'title' => 'PHP 5.6 — Вариадика и `**`',
                'excerpt' => 'Вариадичен `...`, разопаковане на аргументи, степенуване `**`, `use function`/`const`, константни изрази — последна спирка преди скока на ядрото в PHP 7.',
            ],
            'v70' => [
                'title' => 'PHP 7.0 — Разрив с PHP 5',
                'excerpt' => 'Скаларни и връщани типове, `??` и `<=>`, анонимни класове, `Closure::call`, генератори, `random_bytes`, филтриран `unserialize` и `Throwable`/BC от ерата 5.x.',
            ],
            'v71' => [
                'title' => 'PHP 7.1 — Nullable, void, iterable',
                'excerpt' => '`?Type`, `void`, `iterable`, видимост на константи, multi-catch, `list()` с ключове — плюс `ArgumentCountError`, премахнати session INI и BC при низове.',
            ],
            'v72' => [
                'title' => 'PHP 7.2 — Тип object и libsodium',
                'excerpt' => '`object` hint, разширяване на типове на параметри, Sodium в ядрото, LDAP EXOP, addrinfo sockets — предупреждения `count()`/`get_class(null)` и mcrypt в PECL.',
            ],
            'v73' => [
                'title' => 'PHP 7.3 — Синтактична полировка преди 7.4',
                'excerpt' => 'Гъвкав heredoc/nowdoc, завършващи запетаи в извиквания, `JsonException`, `is_countable`, `array_key_first`/`last`, PCRE2, Argon2id и фини BC (`ArrayAccess`, референции, `continue` в `switch`).',
            ],
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
