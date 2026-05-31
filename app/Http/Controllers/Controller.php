<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Base HTTP controller for the application.
 *
 * Concrete controllers extend this class to inherit standard controller behaviour.
 */
abstract class Controller
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function scalarMetaString(array $meta, string $key): ?string
    {
        if (! isset($meta[$key])) {
            return null;
        }

        $value = $meta[$key];

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Resolve the OG image URL for a page.
     *
     * Priority:
     *  1. `og_image` key from YAML front matter (absolute URL or /images/… path)
     *  2. Category-specific image at /images/og/{category}-fallback.png (if it exists)
     *  3. null — the Layout component will fall back to config('seo.default_og_image')
     *
     * @param  array<string, mixed>  $meta
     */
    protected function resolveOgImage(array $meta, string $category, ?string $articleSlug = null): ?string
    {
        // 1. Explicit og_image in front matter
        if (! empty($meta['og_image']) && is_string($meta['og_image'])) {
            return $meta['og_image'];
        }

        // 2. Category-level fallback images
        $map = [
            'php:8.4'         => '/images/og/og-php-84.png',
            'php:8.5'         => '/images/og/og-php-85.png',
            'php:runtimes'    => '/images/og/og-runtimes.png',
            'php:8.3'         => '/images/og/og-php-modern.png',
            'php:8.2'         => '/images/og/og-php-modern.png',
            'php:8.1'         => '/images/og/og-php-modern.png',
            'php:8.0'         => '/images/og/og-php-modern.png',
            'php:7.4'         => '/images/og/og-php-legacy.png',
            'php:7.3'         => '/images/og/og-php-legacy.png',
            'php:7.2'         => '/images/og/og-php-legacy.png',
            'php:7.1'         => '/images/og/og-php-legacy.png',
            'php:7.0'         => '/images/og/og-php-legacy.png',
            'php:5.6'         => '/images/og/og-php-legacy.png',
            'php:5.5'         => '/images/og/og-php-legacy.png',
            'php:5.4'         => '/images/og/og-php-legacy.png',
            'php:5.3'         => '/images/og/og-php-legacy.png',
            'tools'           => '/images/og/og-tools.png',
            'architecture'    => '/images/og/og-architecture.png',
            'microservices'   => '/images/og/og-microservices.png',
        ];

        // Try specific slug lookup first (php:8.4, php:8.5, …)
        if ($articleSlug !== null) {
            $specific = $category . ':' . $articleSlug;
            if (isset($map[$specific])) {
                return $map[$specific];
            }
        }

        // Then try category lookup
        return $map[$category] ?? null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function publishedCarbon(array $meta, Carbon $fallback): Carbon
    {
        $raw = $this->scalarMetaString($meta, 'published');
        if ($raw === null) {
            return $fallback;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
