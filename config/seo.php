<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allow search engine indexing
    |--------------------------------------------------------------------------
    |
    | Set to false on staging (e.g. dev.devsense.work) so robots.txt blocks
    | crawlers. Production (devsense.work) should use true.
    |
    */

    'allow_indexing' => filter_var(env('SEO_ALLOW_INDEXING', env('APP_ENV') === 'production'), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Brand / Open Graph
    |--------------------------------------------------------------------------
    */

    'site_name' => env('SEO_SITE_NAME') ?: env('APP_NAME', 'DevSense'),

    /** Absolute URL to a default share image (1200×630 recommended). */
    'default_og_image' => env('SEO_DEFAULT_OG_IMAGE'),

    /** Twitter @handle (with or without leading @). */
    'twitter_site' => env('SEO_TWITTER_SITE'),

    /** Declared Open Graph image dimensions (match real asset when possible). */
    'og_image_width' => (int) env('SEO_OG_IMAGE_WIDTH', 1200),

    'og_image_height' => (int) env('SEO_OG_IMAGE_HEIGHT', 630),

    /** Browser chrome color (hex). */
    'theme_color' => env('SEO_THEME_COLOR', '#312e81'),

    /*
    |--------------------------------------------------------------------------
    | Sitemap HTTP cache (seconds)
    |--------------------------------------------------------------------------
    */

    'sitemap_cache_ttl' => (int) env('SEO_SITEMAP_CACHE_TTL', 3600),

    'sitemap_cache_key' => 'seo.sitemap.xml',

    /*
    |--------------------------------------------------------------------------
    | hreflang codes (URL segment → BCP 47)
    |--------------------------------------------------------------------------
    |
    | Ukrainian content lives under /ua/ but hreflang must be "uk" for Google.
    |
    */

    'hreflang' => [
        'ru' => 'ru',
        'en' => 'en',
        'ua' => 'uk',
        'bg' => 'bg',
    ],

    /*
    |--------------------------------------------------------------------------
    | Open Graph locale tags (URL segment → og:locale)
    |--------------------------------------------------------------------------
    */

    'og_locale' => [
        'ru' => 'ru_RU',
        'en' => 'en_US',
        'ua' => 'uk_UA',
        'bg' => 'bg_BG',
    ],

    /*
    |--------------------------------------------------------------------------
    | Author Profile & E-E-A-T Configuration
    |--------------------------------------------------------------------------
    */
    'author' => [
        'name'      => env('SEO_AUTHOR_NAME', 'Vladimir Pichkurov'),
        'job_title' => env('SEO_AUTHOR_JOB_TITLE', 'Senior PHP Developer & Backend Architect'),
        'sameAs'    => [
            'https://github.com/vladimir-pitchkurov',
            'https://www.linkedin.com/in/volodimir-pichkurov-626a46150',
            'https://devsense.work/en/authors/vladimir-pichkurov',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IndexNow API Configurations
    |--------------------------------------------------------------------------
    */
    'indexnow_key' => env('SEO_INDEXNOW_KEY', '8d2f7850a1e34bcf9db7519bb8d2ef5a'),
    'indexnow_enabled' => filter_var(env('SEO_INDEXNOW_ENABLED', false), FILTER_VALIDATE_BOOL),

];
