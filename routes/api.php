<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['api.accesslog', 'throttle:content-api'])
    ->group(function (): void {
        Route::get('/manifest', [\App\Http\Controllers\Api\V1\ManifestController::class, 'show'])->name('api.v1.manifest');

        Route::get('/articles', [\App\Http\Controllers\Api\V1\ArticlesController::class, 'index'])->name('api.v1.articles.index');
        Route::get('/articles/{id}', [\App\Http\Controllers\Api\V1\ArticlesController::class, 'show'])->name('api.v1.articles.show');
        Route::get('/articles/{id}/content', [\App\Http\Controllers\Api\V1\ArticlesController::class, 'content'])
            ->middleware(['auth.apikey:content:read'])
            ->middleware(['throttle:content-api-content'])
            ->name('api.v1.articles.content');

        Route::get('/search', [\App\Http\Controllers\Api\V1\SearchController::class, 'search'])
            ->middleware(['throttle:content-api-search'])
            ->name('api.v1.search');
    });

