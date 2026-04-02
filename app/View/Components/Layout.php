<?php

namespace App\View\Components;

use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\URL;
use Illuminate\View\Component;

/**
 * Site layout with canonical URL, hreflang, Open Graph, Twitter cards, and JSON-LD.
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
    ) {
        $this->canonical = $canonical ?? URL::current();
        $this->ogImage = $ogImage ?? config('seo.default_og_image');
    }

    public function render(): View
    {
        return view('components.layout', [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'ogType' => $this->ogType,
            'ogImage' => $this->ogImage,
            'htmlLang' => $this->htmlLang(),
            'hreflangLinks' => $this->hreflangLinks(),
            'robotsContent' => config('seo.allow_indexing', true) ? 'index, follow' : 'noindex, nofollow',
            'ogLocale' => $this->ogLocale(),
            'ogAlternateLocales' => $this->ogAlternateLocales(),
            'siteName' => (string) config('seo.site_name', config('app.name')),
            'twitterSite' => config('seo.twitter_site'),
            'jsonLd' => $this->mergedJsonLd(),
        ]);
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
                'url' => URL::route($name, $p, true),
            ];
        }

        $xDefault = $this->xDefaultLocale($locales);
        $links[] = [
            'code' => 'x-default',
            'url' => URL::route($name, array_merge($params, ['locale' => $xDefault]), true),
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
     * @return array<string, mixed>
     */
    private function mergedJsonLd(): array
    {
        $root = rtrim((string) config('app.url'), '/');
        $name = (string) config('seo.site_name', config('app.name'));

        $graph = [
            [
                '@type' => 'Organization',
                'name' => $name,
                'url' => $root,
            ],
            [
                '@type' => 'WebSite',
                'name' => $name,
                'url' => $root,
            ],
        ];

        if ($this->structuredData !== null) {
            $graph[] = $this->structuredData;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }
}
