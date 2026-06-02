<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingArticleTranslation extends Model
{
    protected $fillable = [
        'article_id',
        'locale',
        'title',
        'description',
        'content',
        'faq',
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
     * Get the article that owns this pending translation.
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
