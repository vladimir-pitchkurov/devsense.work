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
        'tools_guide_missing' => 'Гайд для инструмента «:slug» ещё не опубликован или недоступен.',
    ],
    'welcome' => [
        'title' => 'DevSense — PHP-гайды и инструменты Sail',
        'description' => 'Материалы по версиям PHP и практические гайды по Laravel Sail: Docker, очереди, окружения и типичные сбои.',
        'hero_title' => 'DevSense',
        'hero_lead' => 'Гайды по миграции PHP с 5.3 до 8.x и углублённые материалы по Laravel Sail для локального Docker-стека.',
        'section_aria' => 'Основные разделы',
        'card_php_title' => 'Гайды по версиям PHP',
        'card_php_excerpt' => 'Синтаксис, deprecations и обратная несовместимость по релизам — с примерами и чек-листами при обновлении.',
        'card_php_cta' => 'К гайдам по PHP',
        'card_tools_title' => 'Инструменты — Laravel Sail',
        'card_tools_excerpt' => 'Compose, базы, очереди, .env и CI, а также что делать, когда контейнеры ведут себя странно.',
        'card_tools_cta' => 'К инструментам',
    ],
    'php_show' => [
        'back_to_guides' => 'Все гайды по версиям PHP',
        'nav_aria' => 'Навигация по гайдам PHP',
    ],
    'php_index' => [
        'title' => 'Гайды по версиям PHP | DevSense',
        'description' => 'Материалы по обновлению с PHP 5.3 до 8.5: синтаксис, миграция, deprecations и обратная несовместимость — с примерами.',
        'hero_title' => 'Гайды по версиям PHP',
        'hero_lead' => 'Прослеживаем эволюцию языка от PHP 5.3 до актуальных релизов PHP 8.x — с рабочими примерами и чек-листами миграции.',
        'cta' => 'Читать гайд',
        'cards' => [
            'v53' => [
                'title' => 'PHP 5.3 — Пространства имён и замыкания',
                'excerpt' => 'Namespaces, `use`, позднее статическое связывание, замыкания, `goto`, NOWDOC, опциональный GC циклов, Phar — и BC (новые ключевые слова, deprecation `ereg*`) на пути от привычек PHP 4.',
            ],
            'v54' => [
                'title' => 'PHP 5.4 — Трейты и `[]`',
                'excerpt' => 'Трейты, краткий синтаксис массивов `[]`, подсказка `callable`, `$this` в замыканиях, встроенный веб-сервер — удалены magic quotes и `register_globals`, deprecated `mysql`.',
            ],
            'v55' => [
                'title' => 'PHP 5.5 — Генераторы и `password_*`',
                'excerpt' => 'Генераторы `yield`, `finally`, API `password_hash`, `array_column`, `ClassName::class` — и тонкая BC у `foreach`/`list()`, которую стоит прогнать регрессией.',
            ],
            'v56' => [
                'title' => 'PHP 5.6 — Вариадика и `**`',
                'excerpt' => 'Вариадический `...`, распаковка аргументов, возведение `**`, `use function`/`const`, константные выражения — последняя остановка перед скачком движка PHP 7.',
            ],
            'v70' => [
                'title' => 'PHP 7.0 — Разрыв с PHP 5',
                'excerpt' => 'Скалярные и возвращаемые типы, `??` и `<=>`, анонимные классы, `Closure::call`, генераторы, `random_bytes`, фильтруемый `unserialize` и `Throwable`/BC эпохи 5.x.',
            ],
            'v71' => [
                'title' => 'PHP 7.1 — Nullable, void, iterable',
                'excerpt' => '`?Type`, `void`, `iterable`, видимость констант, multi-catch, `list()` с ключами — плюс `ArgumentCountError`, удалённые session INI и BC строк.',
            ],
            'v72' => [
                'title' => 'PHP 7.2 — Тип object и libsodium',
                'excerpt' => 'Подсказка `object`, расширение типов параметров, Sodium в ядре, LDAP EXOP, addrinfo sockets — предупреждения `count()`/`get_class(null)` и mcrypt в PECL.',
            ],
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
    'tools_index' => [
        'title' => 'Инструменты и гайды | DevSense',
        'description' => 'Углублённые материалы по Laravel Sail: Docker-сервисы, БД, очереди, окружения, диагностика и отличия локального стека от продакшена.',
        'hero_title' => 'Инструменты',
        'hero_lead' => 'Практические гайды по Laravel Sail — фрагменты compose, .env, очереди и заметки с прицелом на production.',
        'cta' => 'Читать гайд',
        'cards' => [
            'sail' => [
                'title' => 'Laravel Sail — полный гайд',
                'excerpt' => 'Что такое Sail, команды, версии PHP, Redis/RabbitMQ/Postgres/Mongo, очереди, Mailpit, Xdebug, compose, разделение env, локально vs деплой.',
            ],
            'sail_databases' => [
                'title' => 'Sail: БД и Docker-сервисы',
                'excerpt' => 'Compose под Redis, замена MySQL на PostgreSQL, MongoDB и расширение PHP, RabbitMQ как сервис, Mailpit/Meilisearch, healthcheck и тома.',
            ],
            'sail_queues' => [
                'title' => 'Sail: очереди и воркеры',
                'excerpt' => 'sync/database/redis, queue:work в Sail, Horizon локально, RabbitMQ и пакеты Laravel, failed jobs, перезапуск, контраст с продом.',
            ],
            'sail_env_deploy' => [
                'title' => 'Sail: окружения и деплой',
                'excerpt' => '.env и .env.example, секреты в CI, FORWARD_* порты, APP_URL в Docker, пример GitHub Actions, чеклист: Sail не равно production.',
            ],
            'sail_troubleshooting' => [
                'title' => 'Sail: диагностика и производительность',
                'excerpt' => 'WSL2 и синхронизация файлов, права, конфликты портов, пересборка, OPcache и Xdebug в контейнерах, Vite/npm, когда сбрасывать тома.',
            ],
        ],
    ],
    'tools_show' => [
        'back' => 'Все инструменты',
        'nav_aria' => 'Навигация по гайдам инструментов',
    ],
    'seo' => [
        'breadcrumb_aria' => 'Навигационная цепочка',
        'breadcrumb_home' => 'Главная',
        'breadcrumb_php_guides' => 'PHP-гайды',
        'breadcrumb_tools' => 'Инструменты',
    ],
    'tools' => [
        'sail' => [
            'title' => 'Laravel Sail: эволюция среды разработки | DevSense',
            'heading' => 'Эволюция локальной разработки: зачем Laravel Sail',
            'lead' => 'Как я пришёл от разрозненной настройки PHP к Docker-окружению и почему обёртка от Laravel оказалась удачным практичным решением.',
            'back' => 'На главную',
            'nav_aria' => 'Навигация по инструментам',
        ],
    ],
];
