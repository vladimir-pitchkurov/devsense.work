<?php

namespace App\Console\Commands;

use App\Services\SiteSitemapBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Writes public/sitemap.xml and clears the HTTP cache entry (optional deploy step).
 */
class WriteSitemapCommand extends Command
{
    protected $signature = 'sitemap:write';

    protected $description = 'Write public/sitemap.xml (optional: web server may serve this file instead of the /sitemap.xml route)';

    public function handle(SiteSitemapBuilder $builder): int
    {
        $path = public_path('sitemap.xml');
        $builder->build()->writeToFile($path);

        $cacheKey = config('seo.sitemap_cache_key').'.'.md5((string) config('app.url'));
        Cache::forget($cacheKey);

        $this->info("Wrote {$path}");

        return self::SUCCESS;
    }
}
