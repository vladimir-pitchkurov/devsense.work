<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Feature extends Model
{
    protected $fillable = [
        'slug',
        'title_en',
        'title_ru',
        'description_en',
        'description_ru',
    ];

    /**
     * Get the votes for this feature.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(FeatureVote::class);
    }

    /**
     * Check if a specific user has voted for this feature.
     */
    public function votedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->votes()->where('user_id', $user->id)->exists();
    }

    /**
     * Get the total count of votes.
     */
    public function totalVotesCount(): int
    {
        return $this->votes()->count();
    }

    /**
     * Get the total weight of votes based on voters' XP.
     */
    public function totalVotesWeight(): int
    {
        return (int) $this->votes()
            ->join('users', 'feature_votes.user_id', '=', 'users.id')
            ->sum(DB::raw('1 + floor(users.points / 100)'));
    }

    /**
     * Localized title accessor.
     */
    public function getTitleAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ru' ? $this->title_ru : $this->title_en;
    }

    /**
     * Localized description accessor.
     */
    public function getDescriptionAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ru' ? $this->description_ru : $this->description_en;
    }
}
