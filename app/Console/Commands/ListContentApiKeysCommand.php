<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;

class ListContentApiKeysCommand extends Command
{
    protected $signature = 'content-api:key-list {--all : Include revoked keys}';

    protected $description = 'List DevSense Content API keys';

    public function handle(): int
    {
        $query = ApiKey::query()->orderByDesc('id');
        if (! $this->option('all')) {
            $query->whereNull('revoked_at');
        }

        $rows = $query->limit(200)->get()->map(function (ApiKey $k): array {
            $scopes = is_array($k->scopes) ? implode(', ', $k->scopes) : '';

            return [
                'id' => $k->id,
                'name' => $k->name,
                'prefix' => $k->prefix,
                'scopes' => $scopes,
                'rate' => $k->rate_limit_per_minute,
                'last_used_at' => $k->last_used_at?->toDateTimeString(),
                'revoked_at' => $k->revoked_at?->toDateTimeString(),
                'created_at' => $k->created_at?->toDateTimeString(),
            ];
        })->all();

        $this->table(
            ['id', 'name', 'prefix', 'scopes', 'rate', 'last_used_at', 'revoked_at', 'created_at'],
            $rows
        );

        return self::SUCCESS;
    }
}

