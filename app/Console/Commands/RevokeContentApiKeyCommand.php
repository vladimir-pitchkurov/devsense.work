<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RevokeContentApiKeyCommand extends Command
{
    protected $signature = 'content-api:key-revoke {id : api_keys.id} {--reason= : Optional note for logs}';

    protected $description = 'Revoke a DevSense Content API key';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $key = ApiKey::query()->find($id);
        if (! $key) {
            $this->error("API key #{$id} not found.");
            return self::FAILURE;
        }

        if ($key->revoked_at !== null) {
            $this->warn("API key #{$id} is already revoked (revoked_at={$key->revoked_at}).");
            return self::SUCCESS;
        }

        $key->forceFill(['revoked_at' => Carbon::now()])->save();

        $reason = $this->option('reason');
        if (is_string($reason) && $reason !== '') {
            $this->line("Reason: {$reason}");
        }

        $this->info("Revoked API key #{$id} ({$key->name}).");

        return self::SUCCESS;
    }
}

