<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PhpVersionController;
use App\Http\Controllers\PhpToolsController;
use App\Http\Middleware\SetLocale;

Route::get('/', function () {
    return redirect('/ru');
});

Route::prefix('{locale}')
    ->whereIn('locale', ['ru', 'en', 'ua', 'bg'])
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
            Route::get('/sail', [PhpToolsController::class, 'sail'])->name('tools.sail');
        });

    });
