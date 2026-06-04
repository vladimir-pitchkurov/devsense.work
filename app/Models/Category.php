<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['slug'];

    /**
     * Get the translations for the category.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    /**
     * Get the articles for this category.
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class);
    }

    /**
     * Get translation for a specific locale.
     */
    public function translate(?string $locale = null): ?CategoryTranslation
    {
        $locale = $locale ?: app()->getLocale();
        return $this->translations()->where('locale', $locale)->first()
            ?: $this->translations()->where('locale', 'en')->first(); // fallback to english
    }
}
