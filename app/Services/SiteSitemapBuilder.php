<?php

namespace App\Services;

use App\Http\Controllers\PhpVersionController;
use App\Http\Middleware\SetLocale;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use ReflectionClass;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url as SitemapUrl;

/**
 * Builds a URL sitemap with hreflang alternates for all localized public pages.
 */
class SiteSitemapBuilder
{
    /**
     * @return list<string>
     */
    private function supportedLocales(): array
    {
        return SetLocale::SUPPORTED_LOCALES;
    }

    /**
     * @return list<string>
     */
    private function phpVersions(): array
    {
        $reflection = new ReflectionClass(PhpVersionController::class);
        $constant = $reflection->getReflectionConstant('PHP_VERSION_ORDER');
        if ($constant === false) {
            return [];
        }

        /** @var list<string> */
        return $constant->getValue();
    }

    /**
     * @return list<string>
     */
    private function toolSlugs(): array
    {
        return ['sail', 'sail-databases', 'sail-queues', 'sail-env-deploy', 'sail-troubleshooting'];
    }

    /**
     * @return list<string>
     */
    private function microservicesSlugs(): array
    {
        return ['api-gateway'];
    }

    /**
     * @return list<string>
     */
    private function architectureSlugs(): array
    {
        return [
            'web-attacks-and-prevention',
            'high-load-event-ingestion',
            'message-queues-compared',
            'database-performance-and-scaling',
            'database-indexes-deep-dive',
            'database-query-optimization',
            'php-database-connection-pooling',
            'observability-monitoring-laravel',
        ];
    }

    /**
     * Locale used as the primary `<loc>` for each URL cluster (must be supported).
     */
    private function canonicalLocale(): string
    {
        $locales = $this->supportedLocales();
        $preferred = (string) config('app.default_site_locale', 'en');

        if (in_array($preferred, $locales, true)) {
            return $preferred;
        }

        return in_array('en', $locales, true) ? 'en' : $locales[0];
    }

    /**
     * Locale for the `x-default` alternate (usually the same as canonical).
     */
    private function xDefaultLocale(): string
    {
        return $this->canonicalLocale();
    }

    /**
     * Build an absolute URL from `config('app.url')` so sitemap output does not depend on the
     * current HTTP request host (fixes tests and CLI generation).
     */
    private function absoluteSiteUrl(string $root, string $pathOrUrl): string
    {
        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return $pathOrUrl;
        }

        return rtrim($root, '/').'/'.ltrim($pathOrUrl, '/');
    }

    /**
     * @param  array<string, string>  $hreflangMap
     */
    private function addLocalizedCluster(
        Sitemap $sitemap,
        string $root,
        callable $relativePath,
        ?Carbon $lastMod,
        array $locales,
        array $hreflangMap,
        string $canonicalLocale,
        string $xDefaultLocale,
        float $priority = 0.8,
    ): void {
        $paths = [];
        foreach ($locales as $locale) {
            $paths[$locale] = $this->absoluteSiteUrl($root, $relativePath($locale));
        }

        $primaryPath = $paths[$canonicalLocale];
        $tag = SitemapUrl::create($primaryPath)
            ->setChangeFrequency(SitemapUrl::CHANGE_FREQUENCY_WEEKLY)
            ->setPriority($priority);

        if ($lastMod !== null) {
            $tag->setLastModificationDate($lastMod);
        }

        foreach ($locales as $locale) {
            $tag->addAlternate($paths[$locale], $hreflangMap[$locale] ?? $locale);
        }

        $tag->addAlternate($paths[$xDefaultLocale], 'x-default');

        $sitemap->add($tag);
    }

    private function lastModifiedAcrossLocales(string $category, string $slug, array $locales): ?Carbon
    {
        $timestamps = [];

        foreach ($locales as $locale) {
            $path = $this->markdownPath($locale, $category, $slug);
            if ($path !== null) {
                $timestamps[] = File::lastModified($path);
            }
        }

        if ($timestamps === []) {
            return null;
        }

        return Carbon::createFromTimestamp(max($timestamps));
    }

    private function markdownPath(string $locale, string $category, string $slug): ?string
    {
        $path = resource_path("content/{$locale}/{$category}/{$slug}.md");
        if (File::exists($path)) {
            return $path;
        }

        $fallback = resource_path("content/en/{$category}/{$slug}.md");

        return File::exists($fallback) ? $fallback : null;
    }

    public function build(): Sitemap
    {
        $root = rtrim((string) config('app.url'), '/');
        URL::forceRootUrl($root);

        $locales = $this->supportedLocales();
        /** @var array<string, string> $hreflangMap */
        $hreflangMap = config('seo.hreflang', []);
        $canonical = $this->canonicalLocale();
        $xDefault = $this->xDefaultLocale();

        $sitemap = Sitemap::create();

        $this->addLocalizedCluster(
            $sitemap,
            $root,
            fn (string $locale): string => route('home', ['locale' => $locale], false),
            null,
            $locales,
            $hreflangMap,
            $canonical,
            $xDefault,
            1.0,
        );

        $this->addLocalizedCluster(
            $sitemap,
            $root,
            fn (string $locale): string => route('php.index', ['locale' => $locale], false),
            null,
            $locales,
            $hreflangMap,
            $canonical,
            $xDefault,
            0.9,
        );

        foreach ($this->phpVersions() as $version) {
            $this->addLocalizedCluster(
                $sitemap,
                $root,
                fn (string $locale): string => route('php.show', ['locale' => $locale, 'version' => $version], false),
                $this->lastModifiedAcrossLocales('php', $version, $locales),
                $locales,
                $hreflangMap,
                $canonical,
                $xDefault,
            );
        }

        $this->addLocalizedCluster(
            $sitemap,
            $root,
            fn (string $locale): string => route('tools.index', ['locale' => $locale], false),
            null,
            $locales,
            $hreflangMap,
            $canonical,
            $xDefault,
            0.9,
        );

        foreach ($this->toolSlugs() as $slug) {
            $this->addLocalizedCluster(
                $sitemap,
                $root,
                fn (string $locale): string => route('tools.show', ['locale' => $locale, 'slug' => $slug], false),
                $this->lastModifiedAcrossLocales('tools', $slug, $locales),
                $locales,
                $hreflangMap,
                $canonical,
                $xDefault,
            );
        }

        $this->addLocalizedCluster(
            $sitemap,
            $root,
            fn (string $locale): string => route('microservices.index', ['locale' => $locale], false),
            null,
            $locales,
            $hreflangMap,
            $canonical,
            $xDefault,
            0.85,
        );

        foreach ($this->microservicesSlugs() as $slug) {
            $this->addLocalizedCluster(
                $sitemap,
                $root,
                fn (string $locale): string => route('microservices.show', ['locale' => $locale, 'slug' => $slug], false),
                $this->lastModifiedAcrossLocales('microservices', $slug, $locales),
                $locales,
                $hreflangMap,
                $canonical,
                $xDefault,
            );
        }

        $this->addLocalizedCluster(
            $sitemap,
            $root,
            fn (string $locale): string => route('architecture.index', ['locale' => $locale], false),
            null,
            $locales,
            $hreflangMap,
            $canonical,
            $xDefault,
            0.85,
        );

        foreach ($this->architectureSlugs() as $slug) {
            $this->addLocalizedCluster(
                $sitemap,
                $root,
                fn (string $locale): string => route('architecture.show', ['locale' => $locale, 'slug' => $slug], false),
                $this->lastModifiedAcrossLocales('architecture', $slug, $locales),
                $locales,
                $hreflangMap,
                $canonical,
                $xDefault,
            );
        }

        // 6. Public Authors Index
        $this->addLocalizedCluster(
            $sitemap,
            $root,
            fn (string $locale): string => route('authors.index', ['locale' => $locale], false),
            null,
            $locales,
            $hreflangMap,
            $canonical,
            $xDefault,
            0.6,
        );

        // 7. Public Author Profiles
        try {
            $publicAuthors = \App\Models\User::whereIn('role', [\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_AUTHOR])
                ->where('is_approved', true)
                ->where('is_public', true)
                ->get();

            foreach ($publicAuthors as $author) {
                $this->addLocalizedCluster(
                    $sitemap,
                    $root,
                    fn (string $locale): string => route('authors.show', ['locale' => $locale, 'slug' => $author->slug], false),
                    $author->updated_at,
                    $locales,
                    $hreflangMap,
                    $canonical,
                    $xDefault,
                    0.6,
                );
            }
        } catch (\Throwable $e) {
            // Ignore database errors during setup
        }

        return $sitemap;
    }
}
