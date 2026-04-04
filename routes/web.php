<?php

use App\Http\Controllers\PhpToolsController;
use App\Http\Controllers\PhpVersionController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/', function () {
    $locale = config('app.default_site_locale', 'en');
    if (! in_array($locale, SetLocale::SUPPORTED_LOCALES, true)) {
        $locale = 'en';
    }

    return redirect('/'.$locale);
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
            Route::get('/{version}', [PhpVersionController::class, 'show'])->name('php.show');
        });

        Route::prefix('tools')->group(function () {
            Route::get('/', [PhpToolsController::class, 'index'])->name('tools.index');
            Route::get('/{slug}', [PhpToolsController::class, 'show'])
                ->name('tools.show')
                ->where('slug', 'sail|sail-databases|sail-queues|sail-env-deploy|sail-troubleshooting');
        });

    });
