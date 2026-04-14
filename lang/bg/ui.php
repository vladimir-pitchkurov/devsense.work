<?php

return [
    'nav' => [
        'home' => 'Начало',
        'php_short' => 'PHP',
        'php_guides' => 'PHP ръководства',
        'tools' => 'Инструменти',
        'microservices' => 'Микросервиси',
        'microservices_short' => 'MS',
        'architecture' => 'Архитектура',
        'architecture_short' => 'Арх.',
    ],
    'footer' => [
        'branch' => 'Клон',
        'nav_aria' => 'Основни раздели',
        'locales_aria' => 'Същата страница на други езици',
        'locales_label' => 'Език:',
    ],
    'a11y' => [
        'theme_switcher' => 'Текуща тема',
        'language_select' => 'Изберете език',
    ],
    'errors' => [
        'php_guide_missing' => 'Ръководството за PHP :version все още не е публикувано или не е налично.',
        'tools_guide_missing' => 'Ръководството за инструмента „:slug“ все още не е публикувано или не е налично.',
        'microservices_guide_missing' => 'Ръководството за микросервиси „:slug“ все още не е публикувано или не е налично.',
        'architecture_guide_missing' => 'Ръководството по архитектура „:slug“ все още не е публикувано или не е налично.',
    ],
    'welcome' => [
        'title' => 'DevSense — PHP ръководства, Sail и микросервиси',
        'description' => 'Ръководства по PHP, Laravel Sail и микросервиси: API gateway, gRPC и опашки за съобщения.',
        'hero_title' => 'DevSense',
        'hero_lead' => 'Миграция от PHP 5.3 до 8.x, Laravel Sail за локален Docker, микросервиси на периметъра и архитектурни бележки за натоварване и данни.',
        'section_aria' => 'Основни раздели',
        'card_php_title' => 'Ръководства по версии на PHP',
        'card_php_excerpt' => 'Синтаксис, deprecations и несъвместимости по релизи — с примери и чеклисти при надграждане.',
        'card_php_cta' => 'Към PHP ръководствата',
        'card_tools_title' => 'Инструменти — Laravel Sail',
        'card_tools_excerpt' => 'Compose, бази, опашки, .env и CI, плюс какво да правите, когато контейнерите се държат странно.',
        'card_tools_cta' => 'Към инструментите',
        'card_microservices_title' => 'Микросервиси — API gateway и съобщения',
        'card_microservices_excerpt' => 'PHP на периметъра и алтернативи на Node, Go и Rust; gRPC и RabbitMQ между услуги — плюсове, минуси и рецепти.',
        'card_microservices_cta' => 'Към микросервисите',
        'card_architecture_title' => 'Архитектура — натоварване, потоци от събития и аналитика',
        'card_architecture_excerpt' => 'Как да не сринете транзакционната БД с милиони събития: буфери, брокери и разделяне на OLTP и отчети.',
        'card_architecture_cta' => 'Към раздела архитектура',
    ],
    'php_show' => [
        'back_to_guides' => 'Всички ръководства по версии на PHP',
        'nav_aria' => 'Навигация в PHP ръководствата',
    ],
    'php_runtime' => [
        'breadcrumb' => 'PHP на сървъра: FPM, Swoole, асинхронност',
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
            'vruntimes' => [
                'title' => 'PHP на сървъра — FPM, Swoole, workers, event loop',
                'excerpt' => 'Как PHP работи зад nginx: моделът PHP-FPM, дългоживеещи сървъри (Swoole, RoadRunner, FrankenPHP), async I/O в стил ReactPHP/AMPHP — плюсове и минуси, рецепти и течове на памет.',
            ],
        ],
    ],
    'tools_index' => [
        'title' => 'Инструменти и ръководства | DevSense',
        'description' => 'Задълбочени материали за Laravel Sail: Docker услуги, бази данни, опашки, среди, диагностика и разлики между локален стек и production.',
        'hero_title' => 'Инструменти',
        'hero_lead' => 'Практични ръководства за Laravel Sail — compose, .env, опашки и бележки с мисъл за production.',
        'cta' => 'Отвори ръководството',
        'cards' => [
            'sail' => [
                'title' => 'Laravel Sail — пълен гайд',
                'excerpt' => 'Какво е Sail, команди, версии PHP, Redis/RabbitMQ/Postgres/Mongo, опашки, Mailpit, Xdebug, compose, env, локално vs деплой.',
            ],
            'sail_databases' => [
                'title' => 'Sail: бази данни и Docker услуги',
                'excerpt' => 'Compose за Redis, смяна MySQL → PostgreSQL, MongoDB и PHP разширение, RabbitMQ, Mailpit/Meilisearch, healthcheck и томове.',
            ],
            'sail_queues' => [
                'title' => 'Sail: опашки и workers',
                'excerpt' => 'sync/database/redis, queue:work в Sail, Horizon локално, RabbitMQ и пакети за Laravel, failed jobs, рестарт, контраст с прод.',
            ],
            'sail_env_deploy' => [
                'title' => 'Sail: среди и деплой',
                'excerpt' => '.env и .env.example, тайни в CI, портове FORWARD_*, APP_URL в Docker, пример с GitHub Actions, чеклист: Sail ≠ production.',
            ],
            'sail_troubleshooting' => [
                'title' => 'Sail: диагностика и производителност',
                'excerpt' => 'WSL2 и синхронизация на файлове, права, конфликти на портове, пресборка, OPcache и Xdebug в контейнери, Vite/npm, кога да нулирате томове.',
            ],
        ],
    ],
    'tools_show' => [
        'back' => 'Всички инструменти',
        'nav_aria' => 'Навигация в ръководствата за инструменти',
    ],
    'microservices_index' => [
        'title' => 'Микросервиси | DevSense',
        'description' => 'API gateway на PHP и на други рунтайми; вътрешна комуникация с gRPC или RabbitMQ — кога какво е уместно и как се експлоатира.',
        'hero_title' => 'Микросервиси',
        'hero_lead' => 'Шлюзове на периметъра, протоколи между услуги и опашки — за екипи на PHP, които взимат инструменти от други екосистеми.',
        'cta' => 'Отвори ръководството',
        'cards' => [
            'api_gateway' => [
                'title' => 'API gateway: PHP, Node, Go, Rust — gRPC и RabbitMQ',
                'excerpt' => 'Да сглобите или купите edge слой, да сравните рунтайми за шлюз и BFF, да изберете между синхронен gRPC и асинхронен RabbitMQ в контура.',
            ],
        ],
    ],
    'microservices_show' => [
        'back' => 'Всички ръководства за микросервиси',
        'nav_aria' => 'Навигация в ръководствата за микросервиси',
    ],
    'architecture_index' => [
        'title' => 'Софтуерна архитектура | DevSense',
        'description' => 'Системен дизайн под натоварване: приемане на събития, буфериране, брокери, производителност на БД, пулове връзки, наблюдаемост и мониторинг на Laravel и микросервиси, индекси, мащабиране, разделяне на OLTP и аналитика.',
        'hero_title' => 'Архитектура',
        'hero_lead' => 'Материали за устойчиви модели на данни и услуги — без магии, с фокус върху това, което реално чупи продукшъна.',
        'cta' => 'Отвори ръководството',
        'cards' => [
            'web_attacks_and_prevention' => [
                'title' => 'Уеб атаки и защита: XSS, CSRF, SQLi, SSRF, IDOR, upload',
                'excerpt' => 'Най-честите заплахи в реални проекти: инжекции, XSS/CSRF, контрол на достъпа, качване на файлове, SSRF и конфигурационни капани. Практични защити и чеклисти.',
            ],
            'high_load_event_ingestion' => [
                'title' => 'Потоци от събития при високо натоварване: буфери, Redis Streams, Kafka и разделяне на OLTP от OLAP',
                'excerpt' => 'Милиони кликове, залози и завъртания: къде първо да кацне трафикът, как да пазите основната БД и къде да живеят таблата.',
            ],
            'message_queues_compared' => [
                'title' => 'Опашки и брокери: Redis, RabbitMQ, Kafka и какво още има',
                'excerpt' => 'Сравнение на бекенди за фонови задачи и потоци съобщения: Laravel и други стекове, излишна сложност и оперативни подводни камъни.',
            ],
            'database_performance_and_scaling' => [
                'title' => 'Бази под натоварване: заявки, индекси, MySQL срещу Postgres и цената на мащабирането',
                'excerpt' => 'Оптимизация с EXPLAIN, видове индекси, защо тежката логика в СУБД забавя екипа, репликация, шардиране и практически разлики между MySQL и PostgreSQL.',
            ],
            'php_database_connection_pooling' => [
                'title' => 'PHP и тесният участък „връзки към базата“: пулери, прокси и работещи решения',
                'excerpt' => 'Защо FPM и работниците множат сесии, как PgBouncer, ProxySQL и управлявани прокси стоят между PHP и Postgres/MySQL, и бележки за Laravel — transaction pooling и prepared statements.',
            ],
            'observability_monitoring_laravel' => [
                'title' => 'Наблюдаемост: логове, метрики и състояние — Laravel монолит и микросервиси',
                'excerpt' => 'Какво да събирате по среди и натоварване, correlation ID и траси между услуги, преглед от syslog и Nagios до Prometheus, Loki, OpenTelemetry и SaaS APM.',
            ],
        ],
    ],
    'architecture_show' => [
        'back' => 'Всички ръководства по архитектура',
        'nav_aria' => 'Навигация в ръководствата по архитектура',
    ],
    'seo' => [
        'breadcrumb_aria' => 'Вторична навигация',
        'breadcrumb_home' => 'Начало',
        'breadcrumb_php_guides' => 'PHP ръководства',
        'breadcrumb_tools' => 'Инструменти',
        'breadcrumb_microservices' => 'Микросервиси',
        'breadcrumb_architecture' => 'Архитектура',
    ],
    'tools' => [
        'sail' => [
            'title' => 'Laravel Sail: еволюция на локалната среда | DevSense',
            'heading' => 'Еволюция на локалната разработка: защо Laravel Sail',
            'lead' => 'От разпокъсана PHP настройка към Docker-базиран поток и защо обвивката на Laravel се оказа практичен избор.',
            'back' => 'Към началото',
            'nav_aria' => 'Навигация в инструменти',
        ],
    ],
];
