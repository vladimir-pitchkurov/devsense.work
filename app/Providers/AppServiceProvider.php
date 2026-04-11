<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Registers application-level bindings and bootstraps framework hooks.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the service container.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services after all providers have been registered.
     */
    public function boot(): void
    {
        $appUrl = config('app.url');
        if (is_string($appUrl) && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }
    }
}
