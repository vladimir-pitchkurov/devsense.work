<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['slug'];

    /**
     * Get the translations for the tag.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(TagTranslation::class);
    }

    /**
     * Get the articles associated with this tag.
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }

    /**
     * Get translation for a specific locale.
     */
    public function translate(?string $locale = null): ?TagTranslation
    {
        $locale = $locale ?: app()->getLocale();
        return $this->translations()->where('locale', $locale)->first()
            ?: $this->translations()->where('locale', 'en')->first(); // fallback to english
    }
}
