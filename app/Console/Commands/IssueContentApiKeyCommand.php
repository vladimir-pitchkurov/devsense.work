<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssueContentApiKeyCommand extends Command
{
    protected $signature = 'content-api:key-issue
        {name : Human-friendly name (e.g. "Cursor MCP")}
        {--scope=* : Scopes (repeatable), e.g. --scope=content:read}
        {--rate= : Optional per-key requests per minute override}';

    protected $description = 'Issue a DevSense Content API key (printed once; stored as hash only)';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        /** @var list<string> $scopes */
        $scopes = array_values(array_filter($this->option('scope') ?? [], fn ($v): bool => is_string($v) && $v !== ''));
        $rate = $this->option('rate');
        $rateLimit = is_numeric($rate) ? max(1, (int) $rate) : null;

        $prefix = 'ds_live_';
        $secret = Str::random(48);
        $key = $prefix.$secret;

        $record = ApiKey::query()->create([
            'name' => $name,
            'prefix' => $prefix,
            'key_hash' => hash('sha256', $key),
            'scopes' => $scopes === [] ? null : $scopes,
            'rate_limit_per_minute' => $rateLimit,
        ]);

        $this->info('API key created.');
        $this->line('Name: '.$record->name);
        $this->line('ID: '.$record->id);
        $this->line('Scopes: '.($scopes === [] ? '(none)' : implode(', ', $scopes)));
        $this->newLine();
        $this->warn('Copy this key now. It will not be shown again:');
        $this->line($key);

        return self::SUCCESS;
    }
}

