<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Quiz extends Model
{
    protected $fillable = ['slug', 'points', 'category_id', 'notified'];

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(QuizTranslation::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function chapter(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CourseChapter::class, 'quiz_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_quizzes')
            ->withPivot('score', 'completed_at')
            ->withTimestamps();
    }

    public function translate(?string $locale = null): ?QuizTranslation
    {
        $locale = $locale ?: app()->getLocale();
        return $this->translations->where('locale', $locale)->first()
            ?: $this->translations->where('locale', 'en')->first()
            ?: $this->translations->first();
    }
}
