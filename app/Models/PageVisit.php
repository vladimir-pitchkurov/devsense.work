<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_hash',
        'url',
        'path',
        'user_agent',
        'crawler_name',
        'is_bot',
        'is_ai',
        'locale',
        'referer',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'is_ai' => 'boolean',
        ];
    }
}
