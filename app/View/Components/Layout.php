<?php

namespace App\View\Components;

use App\Http\Middleware\SetLocale;
use App\Support\SiteUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\URL;
use Illuminate\View\Component;

/**
 * Site layout with canonical URL, hreflang, Open Graph, Twitter cards, JSON-LD graph, and breadcrumbs.
 */
class Layout extends Component
{
    public string $canonical;

    public ?string $ogImage;

    /**
     * @param  array<string, mixed>|null  $structuredData  Extra JSON-LD node (e.g. TechArticle), merged into @graph.
     */
    public function __construct(
        public string $title,
        public string $description = '',
        ?string $canonical = null,
        public string $ogType = 'website',
        ?string $ogImage = null,
        public ?array $structuredData = null,
        public ?string $breadcrumbCurrent = null,
    ) {
        $this->canonical = $canonical ?? URL::current();
        $this->ogImage = $this->normalizeAbsoluteUrl($ogImage ?? config('seo.default_og_image'));
    }

    public function render(): View
    {
        $breadcrumbItems = $this->breadcrumbNavItems();

        // Use layouts.site (not components/layout) so data from render() is not shadowed by the
        // class component's default view path (same name as resources/views/components/layout.blade.php).
        return view('layouts.site', [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'ogType' => $this->ogType,
            'ogImage' => $this->ogImage,
            'ogImageWidth' => config('seo.og_image_width'),
            'ogImageHeight' => config('seo.og_image_height'),
            'themeColor' => (string) config('seo.theme_color'),
            'htmlLang' => $this->htmlLang(),
            'hreflangLinks' => $this->hreflangLinks(),
            'robotsContent' => config('seo.allow_indexing', true) ? 'index, follow' : 'noindex, nofollow',
            'ogLocale' => $this->ogLocale(),
            'ogAlternateLocales' => $this->ogAlternateLocales(),
            'siteName' => (string) config('seo.site_name', config('app.name')),
            'twitterSite' => config('seo.twitter_site'),
            'jsonLd' => $this->mergedJsonLd(),
            'breadcrumbItems' => $breadcrumbItems,
        ]);
    }

    private function normalizeAbsoluteUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($value, '/');
    }

    private function htmlLang(): string
    {
        $locale = app()->getLocale();
        $map = config('seo.hreflang', []);

        return $map[$locale] ?? $locale;
    }

    /**
     * @return list<array{code: string, url: string}>
     */
    private function hreflangLinks(): array
    {
        $route = request()->route();
        if ($route === null) {
            return [];
        }

        $name = $route->getName();
        if (! is_string($name)) {
            return [];
        }

        $allowed = ['home', 'php.index', 'php.show', 'tools.index', 'tools.show'];
        if (! in_array($name, $allowed, true)) {
            return [];
        }

        return $this->hreflangLinksForNamedRoute($name);
    }

    /**
     * @return list<array{code: string, url: string}>
     */
    private function hreflangLinksForNamedRoute(string $name): array
    {
        $route = request()->route();
        $params = $route->parameters();
        $hreflang = config('seo.hreflang', []);
        $locales = SetLocale::SUPPORTED_LOCALES;
        $links = [];

        foreach ($locales as $locale) {
            $p = array_merge($params, ['locale' => $locale]);
            $links[] = [
                'code' => $hreflang[$locale] ?? $locale,
                'url' => SiteUrl::route($name, $p),
            ];
        }

        $xDefault = $this->xDefaultLocale($locales);
        $links[] = [
            'code' => 'x-default',
            'url' => SiteUrl::route($name, array_merge($params, ['locale' => $xDefault])),
        ];

        return $links;
    }

    /**
     * @param  list<string>  $locales
     */
    private function xDefaultLocale(array $locales): string
    {
        $preferred = (string) config('app.default_site_locale', 'en');
        if (in_array($preferred, $locales, true)) {
            return $preferred;
        }

        return in_array('en', $locales, true) ? 'en' : $locales[0];
    }

    private function ogLocale(): string
    {
        $locale = app()->getLocale();
        $map = config('seo.og_locale', []);

        return $map[$locale] ?? 'en_US';
    }

    /**
     * @return list<string>
     */
    private function ogAlternateLocales(): array
    {
        $current = app()->getLocale();
        $map = config('seo.og_locale', []);
        $out = [];

        foreach (SetLocale::SUPPORTED_LOCALES as $loc) {
            if ($loc !== $current) {
                $out[] = $map[$loc] ?? 'en_US';
            }
        }

        return $out;
    }

    /**
     * Visible breadcrumb trail (last item has null URL = current page).
     *
     * @return list<array{label: string, url: ?string}>
     */
    private function breadcrumbNavItems(): array
    {
        $route = request()->route();
        if ($route === null) {
            return [];
        }

        $name = $route->getName();
        if (! is_string($name)) {
            return [];
        }

        $locale = app()->getLocale();

        return match ($name) {
            'php.index' => [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_php_guides'), 'url' => null],
            ],
            'php.show' => $this->breadcrumbCurrent !== null ? [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_php_guides'), 'url' => SiteUrl::route('php.index', ['locale' => $locale])],
                ['label' => $this->breadcrumbCurrent, 'url' => null],
            ] : [],
            'tools.index' => [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_tools'), 'url' => null],
            ],
            'tools.show' => $this->breadcrumbCurrent !== null ? [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_tools'), 'url' => SiteUrl::route('tools.index', ['locale' => $locale])],
                ['label' => $this->breadcrumbCurrent, 'url' => null],
            ] : [],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function breadcrumbJsonLd(): ?array
    {
        $items = $this->breadcrumbNavItems();
        if ($items === []) {
            return null;
        }

        $elements = [];
        $position = 1;
        foreach ($items as $item) {
            $url = $item['url'] ?? $this->canonical;
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $item['label'],
                'item' => $url,
            ];
            $position++;
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedJsonLd(): array
    {
        $root = rtrim((string) config('app.url'), '/');
        $name = (string) config('seo.site_name', config('app.name'));
        $orgId = $root.'#organization';
        $websiteId = $root.'#website';
        $webPageId = $this->canonical.'#webpage';

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => $orgId,
                'name' => $name,
                'url' => $root,
            ],
            [
                '@type' => 'WebSite',
                '@id' => $websiteId,
                'name' => $name,
                'url' => $root,
                'publisher' => ['@id' => $orgId],
            ],
        ];

        $breadcrumb = $this->breadcrumbJsonLd();
        if ($breadcrumb !== null) {
            $graph[] = $breadcrumb;
        }

        if ($this->structuredData === null) {
            $graph[] = [
                '@type' => 'WebPage',
                '@id' => $webPageId,
                'url' => $this->canonical,
                'name' => $this->title,
                'description' => $this->description,
                'inLanguage' => $this->htmlLang(),
                'isPartOf' => ['@id' => $websiteId],
                'publisher' => ['@id' => $orgId],
            ];
        } else {
            $article = $this->structuredData;
            $article['@id'] = $this->canonical.'#article';
            $article['publisher'] = ['@id' => $orgId];
            $article['isPartOf'] = ['@id' => $websiteId];
            $graph[] = $article;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }
}
