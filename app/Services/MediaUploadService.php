<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MediaUploadService
{
    /**
     * Upload an image, strip its metadata (EXIF/XMP) using GD, and save it to the configured disk.
     *
     * @param UploadedFile $file
     * @return array{0: string, 1: string} Tuple of [publicUrl, storedPath]
     *   - publicUrl: the full absolute URL for display
     *   - storedPath: the path/key to persist in the database (disk-relative for S3, or relative for local)
     */
    public function uploadAndStrip(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid() . '.' . $extension;

        // Use a temporary file for the metadata stripping process
        $tempFile = tempnam(sys_get_temp_dir(), 'avatar_') . '.' . $extension;
        $tempPath = $file->getRealPath();

        // Strip metadata using GD depending on image type
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $this->stripJpeg($tempPath, $tempFile);
                break;
            case 'png':
                $this->stripPng($tempPath, $tempFile);
                break;
            case 'webp':
                $this->stripWebp($tempPath, $tempFile);
                break;
            default:
                copy($tempPath, $tempFile);
                break;
        }

        // Determine environment prefix to separate dev and prod
        $environment = app()->environment();
        $envPrefix = in_array($environment, ['local', 'testing', 'dev', 'development'], true) ? 'dev' : 'prod';
        $path = "uploads/{$envPrefix}/{$filename}";

        if (config('filesystems.default') === 's3' || env('FILESYSTEM_DISK') === 's3') {
            // Upload to S3/DO Spaces
            \Illuminate\Support\Facades\Storage::disk('s3')->put(
                $path,
                file_get_contents($tempFile),
                'public'
            );
            $url = \Illuminate\Support\Facades\Storage::disk('s3')->url($path);
            $storedPath = $path; // Store the S3 key, not the full URL
        } else {
            // Local fallback
            $destinationPath = public_path("uploads/{$envPrefix}");
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            rename($tempFile, $destinationPath . '/' . $filename);
            $url = asset("uploads/{$envPrefix}/" . $filename);
            $storedPath = "uploads/{$envPrefix}/{$filename}";
        }

        // Clean up temp file
        if (file_exists($tempFile)) {
            @unlink($tempFile);
        }

        return [$url, $storedPath];
    }

    /**
     * Recreate JPEG to strip metadata.
     */
    private function stripJpeg(string $source, string $target): void
    {
        $image = @imagecreatefromjpeg($source);
        if ($image) {
            imagejpeg($image, $target, 90); // 90% quality
            imagedestroy($image);
        } else {
            // Fallback if GD fails
            copy($source, $target);
        }
    }

    /**
     * Recreate PNG to strip metadata.
     */
    private function stripPng(string $source, string $target): void
    {
        $image = @imagecreatefrompng($source);
        if ($image) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, $target, 6); // compression level 6 (0-9)
            imagedestroy($image);
        } else {
            copy($source, $target);
        }
    }

    /**
     * Recreate WebP to strip metadata.
     */
    private function stripWebp(string $source, string $target): void
    {
        $image = @imagecreatefromwebp($source);
        if ($image) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagewebp($image, $target, 85); // 85% quality
            imagedestroy($image);
        } else {
            copy($source, $target);
        }
    }
}
