<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ArticleSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'user_id',
        'content',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class)->withDefault();
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ArticleSuggestionVote::class, 'suggestion_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ArticleSuggestionComment::class, 'suggestion_id');
    }

    public function votedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->votes()->where('user_id', $user->id)->exists();
    }
}
