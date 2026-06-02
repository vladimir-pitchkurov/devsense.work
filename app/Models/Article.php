<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id',
        'category_id',
        'slug',
        'is_published',
        'published_at',
        'is_approved'
    ];

    /**
     * Cast attributes.
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'is_approved' => 'boolean',
        ];
    }

    /**
     * Get the author of the article.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the category of the article.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the tags associated with the article.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Get the translations for this article.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ArticleTranslation::class);
    }

    /**
     * Get translation for a specific locale.
     */
    public function translate(?string $locale = null): ?ArticleTranslation
    {
        $locale = $locale ?: app()->getLocale();
        return $this->translations()->where('locale', $locale)->first()
            ?: $this->translations()->where('locale', 'en')->first(); // fallback to english
    }

    /**
     * Get the public frontend URL for the article.
     */
    public function url(): string
    {
        $categorySlug = $this->category?->slug;

        if ($categorySlug === 'php') {
            return route('php.show', ['version' => $this->slug, 'locale' => app()->getLocale()]);
        }

        if (in_array($categorySlug, ['tools', 'microservices', 'architecture'], true)) {
            return route($categorySlug . '.show', ['slug' => $this->slug, 'locale' => app()->getLocale()]);
        }

        return '#';
    }

    /**
     * Scope a query to only include approved articles.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Get the pending translations for the article.
     */
    public function pendingTranslations(): HasMany
    {
        return $this->hasMany(PendingArticleTranslation::class);
    }

    /**
     * Get either the pending draft or the live translation for a specific locale.
     */
    public function getTranslationOrDraft(?string $locale = null)
    {
        $locale = $locale ?: app()->getLocale();
        
        $draft = $this->pendingTranslations()->where('locale', $locale)->first();
        if ($draft) {
            return (object) [
                'title' => $draft->title,
                'description' => $draft->description,
                'content' => $draft->content,
                'faq' => $draft->faq,
                'is_draft' => true,
            ];
        }
        
        $live = $this->translate($locale);
        if ($live) {
            return (object) [
                'title' => $live->title,
                'description' => $live->description,
                'content' => $live->content,
                'faq' => $live->faq,
                'is_draft' => false,
            ];
        }
        
        return null;
    }
}
