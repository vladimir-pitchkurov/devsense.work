<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeTranslation extends Model
{
    protected $fillable = ['badge_id', 'locale', 'title', 'description'];

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}
