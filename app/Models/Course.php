<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'points',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(CourseTranslation::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(CourseChapter::class)->orderBy('order');
    }

    public function translate(?string $locale = null): ?CourseTranslation
    {
        $locale = $locale ?: app()->getLocale();
        $newLocales = ['de', 'fr', 'es', 'it'];

        if ($this->relationLoaded('translations')) {
            $trans = $this->translations->where('locale', $locale)->first();
            if ($trans) {
                return $trans;
            }

            if (in_array($locale, $newLocales, true)) {
                $fallbackEn = $this->translations->where('locale', 'en')->first();
                if ($fallbackEn) {
                    return $fallbackEn;
                }
                $fallbackUa = $this->translations->where('locale', 'ua')->first();
                if ($fallbackUa) {
                    return $fallbackUa;
                }
            } else {
                $fallbackEn = $this->translations->where('locale', 'en')->first();
                if ($fallbackEn) {
                    return $fallbackEn;
                }
            }

            return $this->translations->first();
        }

        $trans = $this->translations()->where('locale', $locale)->first();
        if ($trans) {
            return $trans;
        }

        if (in_array($locale, $newLocales, true)) {
            return $this->translations()->where('locale', 'en')->first()
                ?: $this->translations()->where('locale', 'ua')->first()
                ?: $this->translations()->first();
        }

        return $this->translations()->where('locale', 'en')->first()
            ?: $this->translations()->first();
    }
}
