<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleTranslation extends Model
{
    protected $fillable = [
        'article_id',
        'locale',
        'title',
        'description',
        'content',
        'faq'
    ];

    /**
     * Cast attributes.
     */
    protected function casts(): array
    {
        return [
            'faq' => 'array',
        ];
    }

    /**
     * Get the article that owns this translation.
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
