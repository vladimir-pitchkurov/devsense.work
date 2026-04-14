<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RotateContentApiKeyCommand extends Command
{
    protected $signature = 'content-api:key-rotate {id : api_keys.id} {--name= : Optional new name}';

    protected $description = 'Rotate a DevSense Content API key (issues a new key, revokes the old one)';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $old = ApiKey::query()->find($id);
        if (! $old) {
            $this->error("API key #{$id} not found.");
            return self::FAILURE;
        }

        if ($old->revoked_at !== null) {
            $this->error("API key #{$id} is revoked; rotate from an active key.");
            return self::FAILURE;
        }

        $prefix = $old->prefix;
        $secret = Str::random(48);
        $raw = $prefix.$secret;

        $name = $this->option('name');
        $newName = is_string($name) && $name !== '' ? $name : ($old->name.' (rotated)');

        $new = ApiKey::query()->create([
            'name' => $newName,
            'prefix' => $prefix,
            'key_hash' => hash('sha256', $raw),
            'scopes' => $old->scopes,
            'rate_limit_per_minute' => $old->rate_limit_per_minute,
        ]);

        $old->forceFill(['revoked_at' => Carbon::now()])->save();

        $this->info("Rotated API key #{$old->id} -> #{$new->id}");
        $this->line('Old key revoked.');
        $this->newLine();
        $this->warn('Copy the new key now. It will not be shown again:');
        $this->line($raw);

        return self::SUCCESS;
    }
}

