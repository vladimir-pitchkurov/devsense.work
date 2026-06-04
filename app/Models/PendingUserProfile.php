<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingUserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'job_title',
        'bio',
        'avatar_path',
        'github_url',
        'linkedin_url',
        'twitter_url',
        'website_url',
    ];

    /**
     * Get the user that owns this pending profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Absolute public URL for the pending avatar.
     */
    public function avatarUrl(): string
    {
        if ($this->avatar_path) {
            $path = $this->avatar_path;

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            if (config('filesystems.default') === 's3' || env('FILESYSTEM_DISK') === 's3') {
                return \Illuminate\Support\Facades\Storage::disk('s3')->url($path);
            }

            return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
        }

        $initials = collect(explode(' ', $this->name))
            ->map(fn ($w) => strtoupper(mb_substr($w, 0, 1)))
            ->take(2)
            ->implode('+');

        return 'https://ui-avatars.com/api/?name='.urlencode($initials)
            .'&size=256&background=6366f1&color=ffffff&bold=true&format=png';
    }
}
