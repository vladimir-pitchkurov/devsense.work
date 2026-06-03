<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleSuggestionComment extends Model
{
    protected $fillable = [
        'user_id',
        'suggestion_id',
        'content',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(ArticleSuggestion::class, 'suggestion_id');
    }
}
