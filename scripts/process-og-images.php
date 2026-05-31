<?php

/**
 * scripts/process-og-images.php
 *
 * Processes PNG source images: re-encodes them via GD to strip all
 * EXIF/XMP/IPTC metadata (including AI-generation watermarks), and saves
 * the results to public/images/og/.
 *
 * Usage (from project root inside container):
 *   php scripts/process-og-images.php
 */

$sourceDir  = __DIR__ . '/og-source';
$targetDir  = dirname(__DIR__) . '/public/images/og';

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
    echo "✅ Created directory: {$targetDir}\n";
}

$files = glob($sourceDir . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE);

if (empty($files)) {
    echo "⚠️  No source images found in {$sourceDir}\n";
    exit(0);
}

// Fallback if GD extension is not installed on the host
if (!extension_loaded('gd')) {
    echo "⚠️  GD extension is not loaded. Copying files directly without metadata stripping.\n";
    foreach ($files as $sourcePath) {
        $filename  = basename($sourcePath);
        $targetPath = $targetDir . '/' . $filename;
        echo "Processing (Copy): {$filename} ... ";
        $result = copy($sourcePath, $targetPath);
        if ($result) {
            echo "✅ Done (Copied)\n";
        } else {
            echo "❌ Failed!\n";
        }
    }
    echo "\n✨ All images copied. Output: {$targetDir}\n";
    exit(0);
}

foreach ($files as $sourcePath) {
    $filename  = basename($sourcePath);
    $ext       = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $targetPath = $targetDir . '/' . $filename;

    echo "Processing: {$filename} ... ";

    switch ($ext) {
        case 'png':
            $img = @imagecreatefrompng($sourcePath);
            if ($img) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                $result = imagepng($img, $targetPath, 6);
                imagedestroy($img);
            } else {
                $result = copy($sourcePath, $targetPath);
            }
            break;

        case 'jpg':
        case 'jpeg':
            $img = @imagecreatefromjpeg($sourcePath);
            if ($img) {
                $result = imagejpeg($img, $targetPath, 92);
                imagedestroy($img);
            } else {
                $result = copy($sourcePath, $targetPath);
            }
            break;

        case 'webp':
            $img = @imagecreatefromwebp($sourcePath);
            if ($img) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                $result = imagewebp($img, $targetPath, 88);
                imagedestroy($img);
            } else {
                $result = copy($sourcePath, $targetPath);
            }
            break;

        default:
            $result = copy($sourcePath, $targetPath);
    }

    if ($result) {
        $sizeBefore = number_format(filesize($sourcePath) / 1024, 1);
        $sizeAfter  = number_format(filesize($targetPath) / 1024, 1);
        echo "✅ Done ({$sizeBefore}KB → {$sizeAfter}KB)\n";
    } else {
        echo "❌ Failed!\n";
    }
}

echo "\n✨ All images processed. Output: {$targetDir}\n";
