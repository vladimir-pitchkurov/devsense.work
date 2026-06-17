<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * Application user record with author profile fields for E-E-A-T compliance.
 */
#[Fillable([
    'name', 'email', 'password', 'role',
    'slug', 'job_title', 'bio', 'avatar_path',
    'github_url', 'linkedin_url', 'twitter_url', 'website_url',
    'is_public', 'is_approved', 'is_blocked', 'points',
    'intro', 'experience', 'job_status', 'is_anonymous', 'portfolio',
    'google_id', 'locale', 'notify_articles_quizzes', 'notify_comments',
    'last_notified_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super-admin';
    public const ROLE_AUTHOR = 'author';
    public const ROLE_READER = 'reader';

    /**
     * Check if the user is a super administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Check if the user is an author or higher.
     */
    public function isAuthor(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_AUTHOR]);
    }

    /**
     * Absolute public URL for the author's avatar.
     * Falls back to a UI-Avatars.com generated placeholder using initials.
     */
    public function avatarUrl(): string
    {
        if ($this->avatar_path) {
            $path = $this->avatar_path;

            // Already an absolute URL (S3 / CDN)
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            if (config('filesystems.default') === 's3' || env('FILESYSTEM_DISK') === 's3') {
                return \Illuminate\Support\Facades\Storage::disk('s3')->url($path);
            }

            return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
        }

        // Deterministic colorful fallback generated from initials
        $initials = collect(explode(' ', $this->name))
            ->map(fn ($w) => strtoupper(mb_substr($w, 0, 1)))
            ->take(2)
            ->implode('+');

        return 'https://ui-avatars.com/api/?name='.urlencode($initials)
            .'&size=256&background=6366f1&color=ffffff&bold=true&format=png';
    }

    /**
     * URL-friendly slug derived from name if the slug column is empty.
     */
    public function getSlugAttribute(?string $value): string
    {
        return $value ?: Str::slug($this->name);
    }

    /**
     * Generate a unique slug based on name.
     */
    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug ?: 'user';
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Social link helper — returns an array of non-empty social links.
     *
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        return array_filter([
            'github'   => $this->github_url,
            'linkedin' => $this->linkedin_url,
            'twitter'  => $this->twitter_url,
            'website'  => $this->website_url,
        ]);
    }

    /**
     * Get the articles written by this user.
     */
    public function articles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    /**
     * Get the categories/topics this user is interested in.
     */
    public function interests(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_user');
    }

    /**
     * Attribute casting configuration.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_public'         => 'boolean',
            'is_approved'       => 'boolean',
            'is_blocked'        => 'boolean',
            'is_anonymous'      => 'boolean',
            'portfolio'         => 'array',
            'notify_articles_quizzes' => 'boolean',
            'notify_comments'   => 'boolean',
            'last_notified_at'  => 'datetime',
        ];
    }

    /**
     * Absolute public URL for a portfolio project image.
     */
    public function getPortfolioImageUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (config('filesystems.default') === 's3' || env('FILESYSTEM_DISK') === 's3') {
            return \Illuminate\Support\Facades\Storage::disk('s3')->url($path);
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifyEmailQueued);
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordQueued($token));
    }

    /**
     * Scope a query to only include approved users.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Get the pending profile update for the user.
     */
    public function pendingProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PendingUserProfile::class);
    }

    /**
     * Get the badges unlocked by this user.
     */
    public function badges(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    /**
     * Get the likes/dislikes given by this user.
     */
    public function likes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Like::class);
    }

    /**
     * Get the quizzes completed by this user.
     */
    public function quizzes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'user_quizzes')
            ->withPivot('score', 'completed_at')
            ->withTimestamps();
    }

    /**
     * Check and award points-based and articles-based badges to the user.
     * Returns an array of newly unlocked Badge models.
     */
    public function checkAndAwardBadges(): array
    {
        $newBadges = [];
        $currentBadgeIds = $this->badges()->pluck('badges.id')->toArray();

        // 1. Points-based badges
        $pointsBadges = Badge::where(function($query) {
            $query->where('points_required', '>', 0)
                  ->where('points_required', '<=', $this->points);
        })->get();

        // 2. Articles-based badges
        $articleCount = $this->articles()->where('is_approved', true)->where('is_published', true)->count();
        $articlesBadges = Badge::where(function($query) use ($articleCount) {
            $query->where('articles_required', '>', 0)
                  ->where('articles_required', '<=', $articleCount);
        })->get();

        // Merge qualified badges
        $qualifiedBadges = $pointsBadges->merge($articlesBadges);

        // 3. Quiz-percentage-based badges
        $userQuizzes = \DB::table('user_quizzes')
            ->where('user_id', $this->id)
            ->get();

        foreach ($userQuizzes as $uq) {
            $quiz = Quiz::find($uq->quiz_id);
            if (!$quiz) {
                continue;
            }

            $totalPoints = $quiz->questions()->sum('points');
            $score = $uq->score;

            $percentage = $totalPoints > 0 ? ($score / $totalPoints) * 100 : 0;

            $percentageBadges = Badge::where('quiz_slug', $quiz->slug)
                ->where('min_percentage', '<=', $percentage)
                ->get();

            $qualifiedBadges = $qualifiedBadges->merge($percentageBadges);
        }

        foreach ($qualifiedBadges as $badge) {
            if (!in_array($badge->id, $currentBadgeIds, true)) {
                $this->badges()->attach($badge->id, ['unlocked_at' => now()]);
                $newBadges[] = $badge;
            }
        }

        return $newBadges;
    }
}
