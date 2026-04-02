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
        'tools_guide_missing' => 'Гайд для інструмента «:slug» ще не опублікований або недоступний.',
    ],
    'welcome' => [
        'title' => 'DevSense — PHP-гайди та інструменти Sail',
        'description' => 'Матеріали з версій PHP і практичні гайди з Laravel Sail: Docker, черги, оточення та типові збої.',
        'hero_title' => 'DevSense',
        'hero_lead' => 'Гайди з міграції PHP від 5.3 до 8.x і глибокі матеріали з Laravel Sail для локального Docker-стеку.',
        'section_aria' => 'Основні розділи',
        'card_php_title' => 'Гайди з версій PHP',
        'card_php_excerpt' => 'Синтаксис, deprecations і зворотна несумісність по релізах — із прикладами та чеклістами під час оновлення.',
        'card_php_cta' => 'До PHP-гайдів',
        'card_tools_title' => 'Інструменти — Laravel Sail',
        'card_tools_excerpt' => 'Compose, БД, черги, .env і CI, а також що робити, коли контейнери поводяться дивно.',
        'card_tools_cta' => 'До інструментів',
    ],
    'php_show' => [
        'back_to_guides' => 'Усі гайди з версій PHP',
        'nav_aria' => 'Навігація гайдами PHP',
    ],
    'php_index' => [
        'title' => 'Гайди з версій PHP | DevSense',
        'description' => 'Матеріали з оновлення з PHP 5.3 до 8.5: синтаксис, міграція, deprecations і зворотна несумісність — із прикладами.',
        'hero_title' => 'Гайди з версій PHP',
        'hero_lead' => 'Прослідковуємо еволюцію мови від PHP 5.3 до актуальних релізів PHP 8.x — із практичними прикладами та чеклістами міграції.',
        'cta' => 'Відкрити гайд',
        'cards' => [
            'v53' => [
                'title' => 'PHP 5.3 — Простори імен і замикання',
                'excerpt' => 'Namespaces, `use`, пізнє статичне зв’язування, замикання, `goto`, NOWDOC, опційний GC циклів, Phar — і BC (нові ключові слова, deprecation `ereg*`) на шляху від звичок PHP 4.',
            ],
            'v54' => [
                'title' => 'PHP 5.4 — Трейти й `[]`',
                'excerpt' => 'Трейти, короткий синтаксис масивів `[]`, підказка `callable`, `$this` у замиканнях, вбудований вебсервер — прибрано magic quotes і `register_globals`, deprecated `mysql`.',
            ],
            'v55' => [
                'title' => 'PHP 5.5 — Генератори й `password_*`',
                'excerpt' => 'Генератори з `yield`, `finally`, API `password_hash`, `array_column`, `ClassName::class` — і тонка BC для `foreach`/`list()`, яку варто прогнати регресією.',
            ],
            'v56' => [
                'title' => 'PHP 5.6 — Варіадика й `**`',
                'excerpt' => 'Варіадичний `...`, розпаковка аргументів, степінь `**`, `use function`/`const`, константні вирази — остання зупинка перед стрибком рушія PHP 7.',
            ],
            'v70' => [
                'title' => 'PHP 7.0 — Розрив з PHP 5',
                'excerpt' => 'Скалярні та типи повернення, `??` і `<=>`, анонімні класи, `Closure::call`, генератори, `random_bytes`, фільтрований `unserialize` і `Throwable`/BC епохи 5.x.',
            ],
            'v71' => [
                'title' => 'PHP 7.1 — Nullable, void, iterable',
                'excerpt' => '`?Type`, `void`, `iterable`, видимість констант, multi-catch, `list()` з ключами — плюс `ArgumentCountError`, видалені session INI і BC рядків.',
            ],
            'v72' => [
                'title' => 'PHP 7.2 — Тип object і libsodium',
                'excerpt' => 'Підказка `object`, розширення типів параметрів, Sodium у ядрі, LDAP EXOP, addrinfo sockets — попередження `count()`/`get_class(null)` і mcrypt у PECL.',
            ],
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
    'tools_index' => [
        'title' => 'Інструменти та гайди | DevSense',
        'description' => 'Глибокі матеріали з Laravel Sail: Docker-сервіси, БД, черги, оточення, діагностика та відмінності локального стеку від продакшену.',
        'hero_title' => 'Інструменти',
        'hero_lead' => 'Практичні гайди з Laravel Sail — compose, .env, черги та нотатки з огляду на production.',
        'cta' => 'Відкрити гайд',
        'cards' => [
            'sail' => [
                'title' => 'Laravel Sail — повний гайд',
                'excerpt' => 'Що таке Sail, команди, версії PHP, Redis/RabbitMQ/Postgres/Mongo, черги, Mailpit, Xdebug, compose, env, локально vs деплой.',
            ],
            'sail_databases' => [
                'title' => 'Sail: БД і Docker-сервіси',
                'excerpt' => 'Compose для Redis, заміна MySQL на PostgreSQL, MongoDB і розширення PHP, RabbitMQ, Mailpit/Meilisearch, healthcheck і томи.',
            ],
            'sail_queues' => [
                'title' => 'Sail: черги та воркери',
                'excerpt' => 'sync/database/redis, queue:work у Sail, Horizon локально, RabbitMQ і пакети Laravel, failed jobs, перезапуск, контраст із продом.',
            ],
            'sail_env_deploy' => [
                'title' => 'Sail: оточення та деплой',
                'excerpt' => '.env і .env.example, секрети в CI, порти FORWARD_*, APP_URL у Docker, приклад GitHub Actions, чеклист: Sail ≠ production.',
            ],
            'sail_troubleshooting' => [
                'title' => 'Sail: діагностика та продуктивність',
                'excerpt' => 'WSL2 і синхронізація файлів, права, конфлікти портів, перезбірка, OPcache й Xdebug у контейнерах, Vite/npm, коли скидати томи.',
            ],
        ],
    ],
    'tools_show' => [
        'back' => 'Усі інструменти',
        'nav_aria' => 'Навігація гайдами інструментів',
    ],
    'tools' => [
        'sail' => [
            'title' => 'Laravel Sail: еволюція локального середовища | DevSense',
            'heading' => 'Еволюція локальної розробки: навіщо Laravel Sail',
            'lead' => 'Як я прийшов від розрізненого налаштування PHP до Docker-оточення і чому обгортка від Laravel виявилась зручним практичним рішенням.',
            'back' => 'На головну',
            'nav_aria' => 'Навігація інструментами',
        ],
    ],
];
