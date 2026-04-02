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

    'allow_indexing' => filter_var(env('SEO_ALLOW_INDEXING', true), FILTER_VALIDATE_BOOL),

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

];
