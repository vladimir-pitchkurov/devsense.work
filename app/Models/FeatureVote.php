<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureVote extends Model
{
    protected $fillable = [
        'user_id',
        'feature_id',
    ];

    /**
     * Get the user who cast the vote.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the feature being voted for.
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
