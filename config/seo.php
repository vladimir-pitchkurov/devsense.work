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

];
