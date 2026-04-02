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
    ],
    'php_index' => [
        'title' => 'PHP version guides | DevSense',
        'description' => 'Upgrade guides for PHP 7.4 through 8.5: syntax, migration notes, deprecations, and breaking changes—with examples.',
        'hero_title' => 'PHP version guides',
        'hero_lead' => 'Trace the language from late PHP 7.x to current PHP 8.x releases, with practical examples and migration checklists.',
        'cta' => 'Open guide',
        'cards' => [
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
    'tools' => [
        'sail' => [
            'title' => 'Laravel Sail: evolving the local environment | DevSense',
            'heading' => 'Local development, evolved: why Laravel Sail',
            'lead' => 'From ad-hoc PHP setups to a Docker-based workflow—and why Laravel\'s wrapper became a practical default.',
        ],
    ],
];
