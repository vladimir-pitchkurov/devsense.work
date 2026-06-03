<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    protected $fillable = ['slug', 'points_required', 'image_path'];

    public function translations(): HasMany
    {
        return $this->hasMany(BadgeTranslation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    public function translate(?string $locale = null): ?BadgeTranslation
    {
        $locale = $locale ?: app()->getLocale();
        return $this->translations->where('locale', $locale)->first()
            ?: $this->translations->where('locale', 'en')->first()
            ?: $this->translations->first();
    }
}
