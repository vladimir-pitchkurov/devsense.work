<?php

namespace App\Console\Commands;

use App\Services\IndexNowService;
use App\Services\PublicContentApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Artisan command to scan markdown articles and ping IndexNow API.
 */
class PingIndexNowCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seo:ping-indexnow {--force : Ping all URLs regardless of modification time}';

    /**
     * The console command description.
     */
    protected $description = 'Scan localized markdown articles and submit new/modified URLs to the IndexNow API';

    /**
     * Execute the console command.
     */
    public function handle(PublicContentApiService $apiService, IndexNowService $indexNowService): int
    {
        $flagPath = storage_path('app/.indexnow_last_run');
        $lastRun = $this->option('force') ? 0 : (file_exists($flagPath) ? filemtime($flagPath) : 0);
        
        $entries = $apiService->scanIndex();
        $urls = [];
        $modifiedCount = 0;

        foreach ($entries as $entry) {
            if ($this->option('force') || $entry['modified'] > $lastRun) {
                $urls[] = $apiService->canonicalUrl($entry['locale'], $entry['category'], $entry['slug']);
                $modifiedCount++;
            }
        }

        if (empty($urls)) {
            $this->info('No modified articles found to submit to IndexNow.');
            return self::SUCCESS;
        }

        $this->info("Found {$modifiedCount} modified articles. Purging sitemap cache...");
        $indexNowService->purgeSitemapCache();

        $this->info("Submitting " . count($urls) . " URLs to IndexNow API...");
        
        if ($indexNowService->pingIndexNow($urls)) {
            $this->info('IndexNow API ping completed successfully.');
            
            $dir = dirname($flagPath);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            touch($flagPath);
        } else {
            $this->warn('IndexNow API submission completed with warnings (ensure key matches config and indexnow_enabled is set in .env).');
        }

        return self::SUCCESS;
    }
}
