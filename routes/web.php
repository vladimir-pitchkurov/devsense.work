<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PhpVersionController;
use App\Http\Controllers\PhpToolsController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('php')->group(function () {
    Route::get('/', [PhpVersionController::class, 'index'])->name('php.index');
    Route::get('/{version}', [PhpVersionController::class, 'show'])->name('php.show');
});

Route::prefix('tools')->group(function () {
    Route::get('/sail', [PhpToolsController::class, 'sail'])->name('tools.sail');
});
