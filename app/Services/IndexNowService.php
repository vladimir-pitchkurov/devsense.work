<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service to handle IndexNow engine ping requests and sitemap cache purging.
 */
class IndexNowService
{
    /**
     * Clear sitemap cache key.
     */
    public function purgeSitemapCache(): void
    {
        $cacheKey = config('seo.sitemap_cache_key').'.'.md5((string) config('app.url'));
        Cache::forget($cacheKey);
        Log::info("SEO: Sitemap cache cleared via IndexNowService.");
    }

    /**
     * Send list of URLs to IndexNow search engine nodes.
     *
     * @param array<string> $urls
     */
    public function pingIndexNow(array $urls): bool
    {
        if (empty($urls)) {
            return true;
        }

        $enabled = config('seo.indexnow_enabled', false);
        $key = config('seo.indexnow_key');
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (app()->environment() !== 'production') {
            Log::info("SEO: IndexNow ping skipped because environment is " . app()->environment());
            return false;
        }

        if (! $enabled || ! $key || ! $host) {
            Log::info("SEO: IndexNow ping skipped. Enabled: " . ($enabled ? 'true' : 'false') . ", Has Key: " . ($key ? 'true' : 'false'));
            return false;
        }

        // Limit ping batch to maximum 10,000 URLs per protocol standard
        $urlList = array_slice(array_values(array_unique($urls)), 0, 10000);
        $keyLocation = rtrim((string) config('app.url'), '/') . '/' . $key . '.txt';

        try {
            $response = Http::timeout(10)->post('https://api.indexnow.org/indexnow', [
                'host' => $host,
                'key' => $key,
                'keyLocation' => $keyLocation,
                'urlList' => $urlList,
            ]);

            if ($response->successful()) {
                Log::info("SEO: IndexNow ping completed successfully for " . count($urlList) . " URLs.");
                return true;
            }

            Log::error("SEO: IndexNow API responded with error status: " . $response->status() . ", Body: " . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error("SEO: IndexNow API connection failed: " . $e->getMessage());
            return false;
        }
    }
}
