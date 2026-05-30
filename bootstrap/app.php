<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.accesslog' => \App\Http\Middleware\ApiAccessLog::class,
            'auth.apikey' => \App\Http\Middleware\RequireApiKey::class,
            'llm.friendly' => \App\Http\Middleware\LlmFriendlyMiddleware::class,
        ]);

        $trusted = env('TRUSTED_PROXIES');
        if (is_string($trusted) && $trusted !== '') {
            $at = $trusted === '*' ? '*' : array_map(trim(...), explode(',', $trusted));
            $middleware->trustProxies(at: $at);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
