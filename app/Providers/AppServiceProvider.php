<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        RateLimiter::for('content-api', function (Request $request): Limit {
            return Limit::perMinute(120)->by((string) $request->ip());
        });

        RateLimiter::for('content-api-search', function (Request $request): Limit {
            $apiKeyId = $request->attributes->get('api_key_id');
            $id = is_int($apiKeyId) ? 'key:'.$apiKeyId : (string) $request->ip();

            return Limit::perMinute(60)->by($id);
        });

        RateLimiter::for('content-api-content', function (Request $request): Limit {
            $apiKeyId = $request->attributes->get('api_key_id');
            $id = is_int($apiKeyId) ? 'key:'.$apiKeyId : (string) $request->ip();

            return Limit::perMinute(30)->by($id);
        });
    }
}
