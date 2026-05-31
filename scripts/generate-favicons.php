<?php
// scripts/generate-favicons.php

$publicPath = dirname(__DIR__) . '/public';
$tempSvgPath = sys_get_temp_dir() . '/temp_solid_favicon.svg';

// 1. Write the modern vector favicon.svg (with prefers-color-scheme support)
$svgTemplate = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="none">
    <defs>
        <linearGradient id="logo-ds-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#6366f1" />
            <stop offset="100%" stop-color="#4f46e5" />
        </linearGradient>
        <style>
            .icon-bg { stroke: #e2e8f0; }
            .icon-s { stroke: #0f172a; }
            @media (prefers-color-scheme: dark) {
                .icon-bg { stroke: #1e293b; }
                .icon-s { stroke: #f8fafc; }
            }
        </style>
    </defs>
    <rect x="2" y="2" width="28" height="28" rx="8" class="icon-bg" stroke-width="1.5" />
    <rect x="2" y="2" width="28" height="28" rx="8" stroke="url(#logo-ds-gradient)" stroke-width="1.5" stroke-dasharray="24 64" stroke-linecap="round" />
    <!-- D character styled as cursor and bracket -->
    <path d="M8 8v16" stroke="url(#logo-ds-gradient)" stroke-width="2.5" stroke-linecap="round" />
    <path d="M8 8h4.5c4 0 6.5 3 6.5 8s-2.5 8-6.5 8H8" stroke="url(#logo-ds-gradient)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
    <!-- S character styled as a curly tag -->
    <path d="M24 10c0-1.2-1-2-2.2-2H19.5c-1.2 0-2 .8-2 2v1.5c0 1.2.8 2 2 2h1c1.2 0 2 .8 2 2v1.5c0 1.2-.8 2-2 2H18c-1.2 0-2.2-.8-2.2-2" class="icon-s" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
    <circle cx="15.5" cy="16" r="1.5" fill="#6366f1" />
</svg>
SVG;

file_put_contents("$publicPath/favicon.svg", $svgTemplate);
echo "✅ Generated: public/favicon.svg\n";

// Helper function to create solid background SVG
function getSolidSvg() {
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="none">
    <defs>
        <linearGradient id="logo-ds-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#6366f1" />
            <stop offset="100%" stop-color="#4f46e5" />
        </linearGradient>
    </defs>
    <!-- Deep premium slate background -->
    <rect width="32" height="32" fill="#0f172a" />
    <!-- Outer accent ring -->
    <rect x="2" y="2" width="28" height="28" rx="8" stroke="#1e293b" stroke-width="1.5" />
    <rect x="2" y="2" width="28" height="28" rx="8" stroke="url(#logo-ds-gradient)" stroke-width="1.5" stroke-dasharray="24 64" stroke-linecap="round" />
    <!-- D character styled as cursor and bracket -->
    <path d="M8 8v16" stroke="url(#logo-ds-gradient)" stroke-width="2.5" stroke-linecap="round" />
    <path d="M8 8h4.5c4 0 6.5 3 6.5 8s-2.5 8-6.5 8H8" stroke="url(#logo-ds-gradient)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
    <!-- S character (white) -->
    <path d="M24 10c0-1.2-1-2-2.2-2H19.5c-1.2 0-2 .8-2 2v1.5c0 1.2.8 2 2 2h1c1.2 0 2 .8 2 2v1.5c0 1.2-.8 2-2 2H18c-1.2 0-2.2-.8-2.2-2" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
    <circle cx="15.5" cy="16" r="1.5" fill="#6366f1" />
</svg>
SVG;
}

// Write the solid version temporarily
file_put_contents($tempSvgPath, getSolidSvg());

// 2. Generate PNG images using rsvg-convert
$pngSizes = [
    'favicon-16x16.png' => 16,
    'favicon-32x32.png' => 32,
    'apple-touch-icon.png' => 180,
    'android-chrome-192x192.png' => 192,
    'android-chrome-512x512.png' => 512,
];

foreach ($pngSizes as $filename => $size) {
    $outPath = "$publicPath/$filename";
    $cmd = sprintf("rsvg-convert -w %d -h %d -o %s %s 2>&1", $size, $size, escapeshellarg($outPath), escapeshellarg($tempSvgPath));
    exec($cmd, $output, $resultCode);
    
    if ($resultCode !== 0) {
        fwrite(STDERR, "Error generating $filename: " . implode("\n", $output) . "\n");
        @unlink($tempSvgPath);
        exit(1);
    }
    
    echo "✅ Generated: public/$filename ({$size}x{$size})\n";
}

// 3. Generate multi-resolution favicon.ico using Imagick
if (extension_loaded('imagick')) {
    $icoSizes = [16, 32, 48];
    $ico = new Imagick();
    
    foreach ($icoSizes as $size) {
        // Create temporary PNG for ICO compilation
        $tempPng = sys_get_temp_dir() . "/temp_ico_$size.png";
        $cmd = sprintf("rsvg-convert -w %d -h %d -o %s %s 2>&1", $size, $size, escapeshellarg($tempPng), escapeshellarg($tempSvgPath));
        exec($cmd, $output, $resultCode);
        
        if ($resultCode === 0 && file_exists($tempPng)) {
            $im = new Imagick();
            $im->readImage($tempPng);
            $ico->addImage($im);
            @unlink($tempPng);
        } else {
            fwrite(STDERR, "Warning: Failed to render temporary PNG for size $size\n");
        }
    }
    
    if ($ico->getNumberImages() > 0) {
        $ico->writeImages("$publicPath/favicon.ico", true);
        echo "✅ Generated: public/favicon.ico (multi-resolution via Imagick)\n";
    } else {
        fwrite(STDERR, "Error: No images added to ICO.\n");
    }
    $ico->clear();
    $ico->destroy();
} else {
    // Fallback: Copy the 32x32 PNG as favicon.ico if Imagick is not loaded
    copy("$publicPath/favicon-32x32.png", "$publicPath/favicon.ico");
    echo "⚠️ Warning: Imagick not found. Copied 32x32 PNG as public/favicon.ico fallback.\n";
}

// Cleanup
@unlink($tempSvgPath);
echo "🎉 Favicon generation completed successfully!\n";
