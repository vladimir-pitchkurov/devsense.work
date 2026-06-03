<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    protected $fillable = [
        'user_id',
        'reportable_type',
        'reportable_id',
        'type',
        'reason',
        'screenshot_path',
        'status',
    ];

    /**
     * Get the user who submitted the report (optional/nullable).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent reportable model (Article or User).
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }
}
