<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizInProgress extends Model
{
    public $timestamps = false;

    protected $table = 'quiz_in_progress';

    protected $fillable = ['user_id', 'quiz_id', 'question_index', 'answers', 'saved_at'];

    protected $casts = [
        'answers'    => 'array',
        'saved_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
