<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseChapterTranslation extends Model
{
    protected $fillable = [
        'course_chapter_id',
        'locale',
        'title',
        'content_markdown',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(CourseChapter::class, 'course_chapter_id');
    }
}
