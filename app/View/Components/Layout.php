<?php

namespace App\View\Components;

use App\Http\Middleware\SetLocale;
use App\Support\SiteUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Request;
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
        $this->canonical = $canonical ?? self::resolveCanonicalFromRequest();
        $this->ogImage = $this->normalizeAbsoluteUrl($ogImage ?? config('seo.default_og_image'));
    }

    /**
     * Prefer canonical URLs built from APP_URL + named routes so scheme/host stay consistent
     * behind proxies and match hreflang/sitemap (avoids http vs https duplicates in Search Console).
     */
    private static function resolveCanonicalFromRequest(): string
    {
        $route = Request::route();
        if ($route === null) {
            return URL::current();
        }

        $name = $route->getName();
        if (! is_string($name)) {
            return URL::current();
        }

        $routable = [
            'home', 'php.index', 'php.show', 'tools.index', 'tools.show', 
            'microservices.index', 'microservices.show', 'architecture.index', 'architecture.show',
            'jobs.index', 'jobs.show', 'authors.index', 'authors.show',
            'courses.index', 'courses.show', 'courses.chapter',
        ];
        if (! in_array($name, $routable, true)) {
            return URL::current();
        }

        $params = array_merge($route->parameters(), ['locale' => app()->getLocale()]);
        unset($params['category_slug']);

        return SiteUrl::route($name, $params);
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

        $allowed = [
            'home', 'php.index', 'php.show', 'tools.index', 'tools.show', 
            'microservices.index', 'microservices.show', 'architecture.index', 'architecture.show',
            'jobs.index', 'jobs.show', 'authors.index', 'authors.show',
            'courses.index', 'courses.show', 'courses.chapter',
        ];
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
            'microservices.index' => [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_microservices'), 'url' => null],
            ],
            'microservices.show' => $this->breadcrumbCurrent !== null ? [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_microservices'), 'url' => SiteUrl::route('microservices.index', ['locale' => $locale])],
                ['label' => $this->breadcrumbCurrent, 'url' => null],
            ] : [],
            'architecture.index' => [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_architecture'), 'url' => null],
            ],
            'architecture.show' => $this->breadcrumbCurrent !== null ? [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_architecture'), 'url' => SiteUrl::route('architecture.index', ['locale' => $locale])],
                ['label' => $this->breadcrumbCurrent, 'url' => null],
            ] : [],
            'jobs.index' => [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_jobs'), 'url' => null],
            ],
            'jobs.show' => $this->breadcrumbCurrent !== null ? [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_jobs'), 'url' => SiteUrl::route('jobs.index', ['locale' => $locale])],
                ['label' => $this->breadcrumbCurrent, 'url' => null],
            ] : [],
            'authors.index' => [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_authors'), 'url' => null],
            ],
            'authors.show' => $this->breadcrumbCurrent !== null ? [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_authors'), 'url' => SiteUrl::route('authors.index', ['locale' => $locale])],
                ['label' => $this->breadcrumbCurrent, 'url' => null],
            ] : [],
            'tags.index' => [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_tags'), 'url' => null],
            ],
            'tags.show' => $this->breadcrumbCurrent !== null ? [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_tags'), 'url' => SiteUrl::route('tags.index', ['locale' => $locale])],
                ['label' => $this->breadcrumbCurrent, 'url' => null],
            ] : [],
            'courses.index' => [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_courses'), 'url' => null],
            ],
            'courses.show' => $this->breadcrumbCurrent !== null ? [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_courses'), 'url' => SiteUrl::route('courses.index', ['locale' => $locale])],
                ['label' => $this->breadcrumbCurrent, 'url' => null],
            ] : [],
            'courses.chapter' => $this->breadcrumbCurrent !== null ? [
                ['label' => __('ui.seo.breadcrumb_home'), 'url' => SiteUrl::route('home', ['locale' => $locale])],
                ['label' => __('ui.seo.breadcrumb_courses'), 'url' => SiteUrl::route('courses.index', ['locale' => $locale])],
                ['label' => request()->route('course_slug') ? ucwords(str_replace('-', ' ', request()->route('course_slug'))) : __('ui.seo.breadcrumb_syllabus'), 'url' => request()->route('course_slug') ? SiteUrl::route('courses.show', ['locale' => $locale, 'course_slug' => request()->route('course_slug')]) : null],
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
        $authorId = $root.'#author';

        $authorConfig = config('seo.author', []);
        $authorNode = [
            '@type' => 'Person',
            '@id' => $authorId,
            'name' => $authorConfig['name'] ?? 'Vladimir Pitchkurov',
            'jobTitle' => $authorConfig['job_title'] ?? 'Software Engineer',
            'sameAs' => $authorConfig['sameAs'] ?? [],
        ];

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
            $authorNode,
        ];

        $breadcrumb = $this->breadcrumbJsonLd();
        if ($breadcrumb !== null) {
            $graph[] = $breadcrumb;
        }

        $faqData = null;
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

            // Extract FAQ data if present in metadata
            if (isset($article['faq'])) {
                $faqData = $article['faq'];
                unset($article['faq']);
            }

            $article['@id'] = $this->canonical.'#article';
            $article['publisher'] = ['@id' => $orgId];
            $article['isPartOf'] = ['@id' => $websiteId];

            // Add image to article structured data for Google Discover / rich results
            if ($this->ogImage !== null) {
                $article['image'] = [
                    '@type'  => 'ImageObject',
                    'url'    => $this->ogImage,
                    'width'  => (int) config('seo.og_image_width', 1200),
                    'height' => (int) config('seo.og_image_height', 630),
                ];
            }

            if (in_array($article['@type'] ?? '', ['TechArticle', 'BlogPosting', 'JobPosting'], true)) {
                $article['author'] = ['@id' => $authorId];
            }

            if (($article['@type'] ?? '') === 'JobPosting') {
                $article['hiringOrganization'] = ['@id' => $orgId];
            }

            $graph[] = $article;
        }

        if (is_array($faqData) && $faqData !== []) {
            $questions = [];
            foreach ($faqData as $item) {
                if (isset($item['question'], $item['answer'])) {
                    $questions[] = [
                        '@type' => 'Question',
                        'name' => $item['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $item['answer'],
                        ],
                    ];
                }
            }
            if ($questions !== []) {
                $graph[] = [
                    '@type' => 'FAQPage',
                    'mainEntity' => $questions,
                ];
            }
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }
}
