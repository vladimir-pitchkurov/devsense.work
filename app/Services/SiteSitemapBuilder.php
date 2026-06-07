<?php

namespace App\Services;

use App\Http\Middleware\SetLocale;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url as SitemapUrl;

/**
 * Builds a URL sitemap with hreflang alternates for all localized public pages.
 */
class SiteSitemapBuilder
{
    private PublicContentApiService $apiService;

    public function __construct(PublicContentApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * @return list<string>
     */
    private function supportedLocales(): array
    {
        return SetLocale::SUPPORTED_LOCALES;
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

        // 1. Base Clusters
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

        $this->addLocalizedCluster(
            $sitemap,
            $root,
            fn (string $locale): string => route('jobs.index', ['locale' => $locale], false),
            null,
            $locales,
            $hreflangMap,
            $canonical,
            $xDefault,
            0.8,
        );

        // 2. Dynamic Article Clusters
        $entries = $this->apiService->scanIndex();
        $articlesByGroup = [];
        foreach ($entries as $entry) {
            $key = $entry['category'] . ':' . $entry['slug'];
            if (!isset($articlesByGroup[$key])) {
                $articlesByGroup[$key] = [
                    'category' => $entry['category'],
                    'slug' => $entry['slug'],
                    'modified' => 0,
                ];
            }
            $articlesByGroup[$key]['modified'] = max($articlesByGroup[$key]['modified'], $entry['modified']);
        }

        foreach ($articlesByGroup as $group) {
            $category = $group['category'];
            $slug = $group['slug'];
            $lastMod = $group['modified'] > 0 ? Carbon::createFromTimestamp($group['modified']) : null;

            if ($category === 'php') {
                $this->addLocalizedCluster(
                    $sitemap,
                    $root,
                    fn (string $locale): string => route('php.show', ['locale' => $locale, 'version' => $slug], false),
                    $lastMod,
                    $locales,
                    $hreflangMap,
                    $canonical,
                    $xDefault,
                );
            } else {
                $routeName = "{$category}.show";
                if (\Route::has($routeName)) {
                    $this->addLocalizedCluster(
                        $sitemap,
                        $root,
                        fn (string $locale): string => route($routeName, ['locale' => $locale, 'slug' => $slug], false),
                        $lastMod,
                        $locales,
                        $hreflangMap,
                        $canonical,
                        $xDefault,
                    );
                }
            }
        }

        // 3. Public Authors Index
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

        // 4. Public Author Profiles
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
