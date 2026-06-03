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

    public ?int $temp_category_id = null;

    protected $fillable = [
        'author_id',
        'slug',
        'custom_url',
        'is_published',
        'published_at',
        'is_approved',
        'category_id', // for backwards compatibility in tests/factories
        'points_awarded',
    ];

    /**
     * Booted method to handle backwards compatibility for category_id write operations.
     */
    protected static function booted(): void
    {
        static::saving(function (Article $article) {
            if (array_key_exists('category_id', $article->attributes)) {
                $article->temp_category_id = $article->attributes['category_id'];
                unset($article->attributes['category_id']);
            }
        });

        static::saved(function (Article $article) {
            if (isset($article->temp_category_id)) {
                $article->categories()->syncWithoutDetaching([$article->temp_category_id]);
                unset($article->temp_category_id);
            }

            if ($article->is_approved && $article->is_published) {
                $author = $article->author;
                if ($author) {
                    $userChanged = false;

                    if (!$article->points_awarded) {
                        $article->updateQuietly(['points_awarded' => true]);
                        $author->points += 100;
                        $userChanged = true;
                    }

                    if ($author->role === User::ROLE_READER) {
                        $author->role = User::ROLE_AUTHOR;
                        $userChanged = true;
                    }

                    if ($userChanged) {
                        $author->save();
                    }

                    $author->checkAndAwardBadges();
                }
            }
        });
    }

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
     * Get the suggestions/feedback for the article.
     */
    public function suggestions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ArticleSuggestion::class);
    }

    /**
     * Get the likes/dislikes for the article.
     */
    public function likes(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\Like::class, 'likeable');
    }

    /**
     * Get the total count of likes.
     */
    public function likesCount(): int
    {
        return $this->likes()->where('is_dislike', false)->count();
    }

    /**
     * Get the total count of dislikes.
     */
    public function dislikesCount(): int
    {
        return $this->likes()->where('is_dislike', true)->count();
    }

    /**
     * Get the categories of the article.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Backward compatibility helper for category relationship.
     */
    public function category(): BelongsToMany
    {
        return $this->categories();
    }

    /**
     * Intercept relation value for category to return a single model.
     */
    public function getRelationValue($key)
    {
        if ($key === 'category') {
            $relation = parent::getRelationValue('category');
            if ($relation instanceof \Illuminate\Database\Eloquent\Collection) {
                return $relation->first();
            }
            return $relation;
        }
        return parent::getRelationValue($key);
    }

    /**
     * Backward compatibility helper for getting the primary category.
     */
    public function getCategoryAttribute(): ?Category
    {
        if ($this->relationLoaded('category')) {
            $relation = $this->relations['category'];
            return $relation instanceof \Illuminate\Database\Eloquent\Collection ? $relation->first() : $relation;
        }
        if ($this->relationLoaded('categories')) {
            return $this->categories->first();
        }
        return $this->categories->first();
    }

    /**
     * Backward compatibility helper for getting the primary category ID.
     */
    public function getCategoryIdAttribute(): ?int
    {
        return $this->category?->id;
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
        
        if ($this->relationLoaded('translations')) {
            $trans = $this->translations->where('locale', $locale)->first();
            if ($trans) {
                return $trans;
            }
            $fallback = $this->translations->where('locale', 'en')->first();
            if ($fallback) {
                return $fallback;
            }
            return $this->translations->first();
        }

        return $this->translations()->where('locale', $locale)->first()
            ?: $this->translations()->where('locale', 'en')->first()
            ?: $this->translations()->first(); // fallback to any available translation
    }

    public function url(): string
    {
        if (!empty($this->custom_url)) {
            $path = '/' . ltrim($this->custom_url, '/');
            if (str_starts_with($this->custom_url, 'http://') || str_starts_with($this->custom_url, 'https://')) {
                return $this->custom_url;
            }
            return '/' . app()->getLocale() . $path;
        }

        $categorySlug = $this->category?->slug;

        if ($categorySlug === 'php') {
            return route('php.show', ['version' => $this->slug, 'locale' => app()->getLocale()]);
        }

        if ($categorySlug) {
            return '/' . app()->getLocale() . '/' . $categorySlug . '/' . $this->slug;
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
