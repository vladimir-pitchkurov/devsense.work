<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    protected $fillable = ['quiz_id', 'type', 'points', 'correct_answer_index', 'explanation'];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(QuizQuestionTranslation::class);
    }

    public function translate(?string $locale = null): ?QuizQuestionTranslation
    {
        $locale = $locale ?: app()->getLocale();
        return $this->translations->where('locale', $locale)->first()
            ?: $this->translations->where('locale', 'en')->first()
            ?: $this->translations->first();
    }
}
