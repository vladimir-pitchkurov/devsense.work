<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $prefix
 * @property string $key_hash
 * @property array<int, string>|null $scopes
 * @property int|null $rate_limit_per_minute
 * @property \Carbon\CarbonInterface|null $last_used_at
 * @property string|null $last_ip
 * @property \Carbon\CarbonInterface|null $revoked_at
 */
class ApiKey extends Model
{
    protected $table = 'api_keys';

    protected $fillable = [
        'name',
        'prefix',
        'key_hash',
        'scopes',
        'rate_limit_per_minute',
        'last_used_at',
        'last_ip',
        'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}

