<?php

return [
    'nav' => [
        'home' => 'Home',
        'php_short' => 'PHP',
        'php_guides' => 'PHP Guides',
        'tools' => 'Tools',
    ],
    'footer' => [
        'branch' => 'Branch',
    ],
    'a11y' => [
        'theme_switcher' => 'Current theme',
        'language_select' => 'Choose language',
    ],
    'errors' => [
        'php_guide_missing' => 'No guide for PHP :version is available yet.',
        'tools_guide_missing' => 'No tool guide for “:slug” is available yet.',
    ],
    'welcome' => [
        'title' => 'DevSense — PHP guides & Sail tools',
        'description' => 'Version-by-version PHP upgrade notes and practical Laravel Sail guides: Docker services, queues, environments, and troubleshooting.',
        'hero_title' => 'DevSense',
        'hero_lead' => 'PHP migration guides from 5.3 through 8.x, plus deep dives on Laravel Sail for local Docker stacks.',
        'section_aria' => 'Main sections',
        'card_php_title' => 'PHP version guides',
        'card_php_excerpt' => 'Syntax, deprecations, and breaking changes per release—with examples and checklists when you upgrade.',
        'card_php_cta' => 'Browse PHP guides',
        'card_tools_title' => 'Tools — Laravel Sail',
        'card_tools_excerpt' => 'Compose recipes, databases, queues, env/CI patterns, and common fixes when containers misbehave.',
        'card_tools_cta' => 'Browse tools',
    ],
    'php_show' => [
        'back_to_guides' => 'All PHP version guides',
        'nav_aria' => 'PHP guides navigation',
    ],
    'php_index' => [
        'title' => 'PHP version guides | DevSense',
        'description' => 'Upgrade guides for PHP 5.3 through 8.5: syntax, migration notes, deprecations, and breaking changes—with examples.',
        'hero_title' => 'PHP version guides',
        'hero_lead' => 'Trace the language from PHP 5.3 through current PHP 8.x releases, with practical examples and migration checklists.',
        'cta' => 'Open guide',
        'cards' => [
            'v53' => [
                'title' => 'PHP 5.3 — Namespaces & closures',
                'excerpt' => 'Namespaces, `use`, late static binding, closures, `goto`, NOWDOC, optional cycle GC, Phar—plus BC (new keywords, `ereg*` deprecated) on the road away from PHP 4 habits.',
            ],
            'v54' => [
                'title' => 'PHP 5.4 — Traits & `[]`',
                'excerpt' => 'Traits, short array syntax `[]`, callable type hint, `$this` in closures, built-in web server—magic quotes & `register_globals` removed, `mysql` deprecated.',
            ],
            'v55' => [
                'title' => 'PHP 5.5 — Generators & `password_*`',
                'excerpt' => '`yield` generators, `finally`, `password_hash` API, `array_column`, `ClassName::class`—and subtle `foreach`/`list()` BC worth regression-testing.',
            ],
            'v56' => [
                'title' => 'PHP 5.6 — Variadic & `**`',
                'excerpt' => 'Variadic `...`, argument unpacking, `**` exponentiation, `use function`/`const`, constant expressions—last stop before PHP 7’s engine leap.',
            ],
            'v70' => [
                'title' => 'PHP 7.0 — The PHP 5 break',
                'excerpt' => 'Scalar & return types, `??` and `<=>`, anonymous classes, `Closure::call`, generators, `random_bytes`, filtered `unserialize`—and `Throwable`/BC from the 5.x era.',
            ],
            'v71' => [
                'title' => 'PHP 7.1 — Nullable, void, iterable',
                'excerpt' => '`?Type`, `void`, `iterable`, constant visibility, multi-catch, keyed `list()`—plus `ArgumentCountError`, session INI removals, and string offset BC.',
            ],
            'v72' => [
                'title' => 'PHP 7.2 — object type & libsodium',
                'excerpt' => '`object` hint, parameter type widening, Sodium in core, LDAP EXOP, addrinfo sockets—`count()`/`get_class(null)` warnings and mcrypt moved to PECL.',
            ],
            'v73' => [
                'title' => 'PHP 7.3 — Syntax polish before 7.4',
                'excerpt' => 'Flexible heredoc/nowdoc, trailing commas in calls, `JsonException`, `is_countable`, `array_key_first`/`last`, PCRE2, Argon2id—and subtle BC (ArrayAccess keys, references, `continue` in `switch`).',
            ],
            'v74' => [
                'title' => 'PHP 7.4 — The last 7.x feature release',
                'excerpt' => 'Typed properties, arrow functions, FFI, OPcache preloading, `__serialize` / `__unserialize`, and BC traps (typed props, password constants, extensions).',
            ],
            'v80' => [
                'title' => 'PHP 8.0 — Major language reboot',
                'excerpt' => 'Named arguments, match, attributes, JIT, union types, nullsafe `?->`, and stricter behavior across the standard library.',
            ],
            'v81' => [
                'title' => 'PHP 8.1 — Enums & readonly',
                'excerpt' => 'Enums, readonly properties, fibers, intersection types, first-class callables, and migration tightening vs 8.0.',
            ],
            'v82' => [
                'title' => 'PHP 8.2 — Types & dynamic properties',
                'excerpt' => 'Readonly classes, DNF types, standalone `null`/`false`/`true`, `#[SensitiveParameter]`, Random extension, dynamic property deprecations.',
            ],
            'v83' => [
                'title' => 'PHP 8.3 — Precision & JSON',
                'excerpt' => '`#[Override]`, typed class constants, `json_validate`, `str_increment` / `str_decrement`, and runtime fixes that show up under load.',
            ],
            'v84' => [
                'title' => 'PHP 8.4 — Property hooks & lazy objects',
                'excerpt' => 'Property hooks, asymmetric visibility, lazy objects, new `Dom\*` API, `#[Deprecated]`, and deprecations to clear before the next jump.',
            ],
            'v85' => [
                'title' => 'PHP 8.5 — Pipes & platform tightening',
                'excerpt' => 'Pipe operator `|>`, `#[\\NoDiscard]`, closures in constant expressions, ext/uri, stricter filter/PDO/Opcache behavior.',
            ],
        ],
    ],
    'tools_index' => [
        'title' => 'Tools & guides | DevSense',
        'description' => 'Laravel Sail deep dives: Docker services, databases, queues, environments, troubleshooting, and how local stacks differ from real servers.',
        'hero_title' => 'Tools',
        'hero_lead' => 'Practical guides for Laravel Sail—compose snippets, env layout, queues, and production-minded notes.',
        'cta' => 'Open guide',
        'cards' => [
            'sail' => [
                'title' => 'Laravel Sail — full guide',
                'excerpt' => 'What Sail is, daily commands, PHP versions, Redis/RabbitMQ/Postgres/Mongo overviews, queues, Mailpit, Xdebug, compose tweaks, env split, local vs deploy.',
            ],
            'sail_databases' => [
                'title' => 'Sail: databases & Docker services',
                'excerpt' => 'Ready-to-adapt compose for Redis, PostgreSQL swap, MongoDB + PHP extension, RabbitMQ sidecar, Mailpit/Meilisearch hooks, healthchecks and volumes.',
            ],
            'sail_queues' => [
                'title' => 'Sail: queues & workers',
                'excerpt' => 'sync vs database vs redis, `queue:work` inside Sail, Horizon locally, RabbitMQ + Laravel packages, failed jobs, restarts after code changes, prod contrast.',
            ],
            'sail_env_deploy' => [
                'title' => 'Sail: environments & deployment',
                'excerpt' => '.env vs .env.example vs CI secrets, FORWARD_* ports, APP_URL in Docker, GitHub Actions pattern, checklists when Sail is not production.',
            ],
            'sail_troubleshooting' => [
                'title' => 'Sail: troubleshooting & performance',
                'excerpt' => 'WSL2 and file sync, permissions, port conflicts, rebuilds, OPcache and Xdebug inside containers, Vite/npm, and when to reset volumes.',
            ],
        ],
    ],
    'tools_show' => [
        'back' => 'All tools',
        'nav_aria' => 'Tools guides navigation',
    ],
    'seo' => [
        'breadcrumb_aria' => 'Breadcrumb',
        'breadcrumb_home' => 'Home',
        'breadcrumb_php_guides' => 'PHP guides',
        'breadcrumb_tools' => 'Tools',
    ],
    'tools' => [
        'sail' => [
            'title' => 'Laravel Sail: evolving the local environment | DevSense',
            'heading' => 'Local development, evolved: why Laravel Sail',
            'lead' => 'From ad-hoc PHP setups to a Docker-based workflow—and why Laravel\'s wrapper became a practical default.',
            'back' => 'Back to home',
            'nav_aria' => 'Tools navigation',
        ],
    ],
];
