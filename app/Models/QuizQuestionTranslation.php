<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestionTranslation extends Model
{
    protected $fillable = ['quiz_question_id', 'locale', 'question_text', 'options'];

    protected $casts = [
        'options' => 'array',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }
}
