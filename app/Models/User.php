<?php

namespace App\Models;

use Database\Factories\UserFactory;
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
    'is_public', 'is_approved',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
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
        ];
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
}
