<?php

namespace App\Listeners;

use App\Events\ArticlePublished;
use App\Events\VacancyPublished;
use App\Services\IndexNowService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listener that responds to publishing events by purging cache and pinging IndexNow API.
 */
class PingIndexNowListener implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct(private readonly IndexNowService $indexNowService)
    {
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if ($event instanceof ArticlePublished || $event instanceof VacancyPublished) {
            $this->indexNowService->purgeSitemapCache();
            $this->indexNowService->pingIndexNow([$event->url]);
        }
    }
}
