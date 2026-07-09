<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    protected $fillable = [
        'user_id',
        'reportable_type',
        'reportable_id',
        'type',
        'reason',
        'screenshot_path',
        'status',
    ];

    /**
     * Get the user who submitted the report (optional/nullable).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent reportable model (Article or User).
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the absolute URL of the screenshot.
     */
    public function screenshotUrl(): ?string
    {
        if (!$this->screenshot_path) {
            return null;
        }

        $path = $this->screenshot_path;

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (config('filesystems.default') === 's3' || env('FILESYSTEM_DISK') === 's3') {
            return \Illuminate\Support\Facades\Storage::disk('s3')->url($path);
        }

        if (str_starts_with($path, 'uploads/')) {
            return asset($path);
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }
}
