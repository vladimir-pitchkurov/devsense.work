<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('content-api:key-issue {name} {--scope=*} {--rate=}', function () {
    $this->call(\App\Console\Commands\IssueContentApiKeyCommand::class, [
        'name' => $this->argument('name'),
        '--scope' => (array) $this->option('scope'),
        '--rate' => $this->option('rate'),
    ]);
})->purpose('Issue a DevSense Content API key (printed once; stored as hash only)');

Artisan::command('content-api:key-list {--all}', function () {
    $this->call(\App\Console\Commands\ListContentApiKeysCommand::class, [
        '--all' => (bool) $this->option('all'),
    ]);
})->purpose('List DevSense Content API keys');

Artisan::command('content-api:key-revoke {id} {--reason=}', function () {
    $this->call(\App\Console\Commands\RevokeContentApiKeyCommand::class, [
        'id' => $this->argument('id'),
        '--reason' => $this->option('reason'),
    ]);
})->purpose('Revoke a DevSense Content API key');

Artisan::command('content-api:key-rotate {id} {--name=}', function () {
    $this->call(\App\Console\Commands\RotateContentApiKeyCommand::class, [
        'id' => $this->argument('id'),
        '--name' => $this->option('name'),
    ]);
})->purpose('Rotate a DevSense Content API key (issues a new one and revokes the old)');

use Illuminate\Support\Facades\Schedule;

// Schedule database backup and cleanup tasks to run only in production
Schedule::command('backup:clean')->daily()->at('01:00')->environments('production');
Schedule::command('backup:run --only-db')->daily()->at('02:00')->environments('production');
