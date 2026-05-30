<?php

use App\Http\Controllers\ArchitectureController;
use App\Http\Controllers\MicroservicesController;
use App\Http\Controllers\PhpToolsController;
use App\Http\Controllers\PhpVersionController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

if ($indexNowKey = config('seo.indexnow_key')) {
    Route::get("/{$indexNowKey}.txt", function () use ($indexNowKey) {
        return response($indexNowKey, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    })->name('seo.indexnow.key');
}

Route::get('/', function () {
    $locale = config('app.default_site_locale', 'en');
    if (! in_array($locale, SetLocale::SUPPORTED_LOCALES, true)) {
        $locale = 'en';
    }

    return redirect('/'.$locale, 301);
});

Route::prefix('{locale}')
    ->whereIn('locale', SetLocale::SUPPORTED_LOCALES)
    ->middleware(SetLocale::class)
    ->group(function () {

        Route::get('/', function () {
            return view('welcome');
        })->name('home');

        Route::prefix('php')->group(function () {
            Route::get('/', [PhpVersionController::class, 'index'])->name('php.index');
            Route::get('/{version}', [PhpVersionController::class, 'show'])
                ->name('php.show')
                ->middleware('llm.friendly');
        });

        Route::prefix('tools')->group(function () {
            Route::get('/', [PhpToolsController::class, 'index'])->name('tools.index');
            Route::get('/{slug}', [PhpToolsController::class, 'show'])
                ->name('tools.show')
                ->where('slug', 'sail|sail-databases|sail-queues|sail-env-deploy|sail-troubleshooting')
                ->middleware('llm.friendly');
        });

        Route::prefix('microservices')->group(function () {
            Route::get('/', [MicroservicesController::class, 'index'])->name('microservices.index');
            Route::get('/{slug}', [MicroservicesController::class, 'show'])
                ->name('microservices.show')
                ->where('slug', MicroservicesController::slugRoutePattern())
                ->middleware('llm.friendly');
        });

        Route::prefix('architecture')->group(function () {
            Route::get('/', [ArchitectureController::class, 'index'])->name('architecture.index');
            Route::get('/{slug}', [ArchitectureController::class, 'show'])
                ->name('architecture.show')
                ->where('slug', ArchitectureController::slugRoutePattern())
                ->middleware('llm.friendly');
        });

        Route::prefix('jobs')->group(function () {
            Route::get('/', [JobsController::class, 'index'])->name('jobs.index');
            Route::get('/{slug}', [JobsController::class, 'show'])
                ->name('jobs.show')
                ->middleware('llm.friendly');
        });

    });
