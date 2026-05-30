<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MediaUploadService
{
    /**
     * Upload an image, strip its metadata (EXIF/XMP) using GD, and save it locally.
     *
     * @param UploadedFile $file
     * @return string Public URL of the uploaded image
     */
    public function uploadAndStrip(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid() . '.' . $extension;
        $destinationPath = public_path('uploads');

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $tempPath = $file->getRealPath();
        $targetPath = $destinationPath . '/' . $filename;

        // Strip metadata using GD depending on image type
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $this->stripJpeg($tempPath, $targetPath);
                break;
            case 'png':
                $this->stripPng($tempPath, $targetPath);
                break;
            case 'webp':
                $this->stripWebp($tempPath, $targetPath);
                break;
            default:
                // Fallback: move file directly if unsupported but allowed extension (like gif)
                $file->move($destinationPath, $filename);
                break;
        }

        return asset('uploads/' . $filename);
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
