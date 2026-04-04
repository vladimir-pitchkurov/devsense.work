<?php

namespace App\Http\Controllers;

use App\Services\SiteSitemapBuilder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Serves the XML sitemap (cached; URLs follow APP_URL for the current environment).
 */
class SitemapController extends Controller
{
    public function __invoke(SiteSitemapBuilder $builder): Response
    {
        $cacheKey = config('seo.sitemap_cache_key').'.'.md5((string) config('app.url'));
        $ttl = (int) config('seo.sitemap_cache_ttl', 3600);

        $xml = Cache::remember($cacheKey, $ttl, fn (): string => $builder->build()->render());

        return response($xml, 200)->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}
