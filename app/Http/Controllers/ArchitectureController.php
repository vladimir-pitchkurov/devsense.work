<?php

namespace App\Http\Controllers;

use App\Services\MarkdownContentService;
use App\Support\SiteUrl;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Serves localized software architecture guides backed by Markdown content.
 */
class ArchitectureController extends Controller
{
    /**
     * Ordered list of guide slugs under `/{locale}/architecture/{slug}`.
     *
     * @var list<string>
     */
    private const ARCHITECTURE_SLUG_ORDER = [
        'high-load-event-ingestion',
        'message-queues-compared',
        'database-performance-and-scaling',
        'php-database-connection-pooling',
        'observability-monitoring-laravel',
    ];

    /**
     * Route constraint regex fragment for `{slug}` (alternation of known slugs).
     */
    public static function slugRoutePattern(): string
    {
        return implode('|', self::ARCHITECTURE_SLUG_ORDER);
    }

    /**
     * Display the architecture section index.
     */
    public function index(): View
    {
        $cards = [];
        foreach (self::ARCHITECTURE_SLUG_ORDER as $slug) {
            $key = str_replace('-', '_', $slug);
            $cards[] = [
                'slug' => $slug,
                'title' => __('ui.architecture_index.cards.'.$key.'.title'),
                'excerpt' => __('ui.architecture_index.cards.'.$key.'.excerpt'),
            ];
        }

        return view('architecture.index', ['cards' => $cards]);
    }

    /**
     * Render a single guide from Markdown.
     *
     * @param  string  $slug  URL segment; must be one of {@see ArchitectureController::ARCHITECTURE_SLUG_ORDER}.
     */
    public function show(string $slug, MarkdownContentService $markdownService): View
    {
        if (! in_array($slug, self::ARCHITECTURE_SLUG_ORDER, true)) {
            abort(404);
        }

        $locale = app()->getLocale();
        $data = $markdownService->getParsedContent($locale, 'architecture', $slug);

        if (! $data) {
            abort(404, __('ui.errors.architecture_guide_missing', ['slug' => $slug]));
        }

        $meta = $data['meta'];
        $cardKey = str_replace('-', '_', $slug);
        $pageTitle = $this->scalarMetaString($meta, 'title') ?? __('ui.architecture_index.cards.'.$cardKey.'.title');
        $pageDescription = $this->scalarMetaString($meta, 'description') ?? '';
        $canonicalUrl = SiteUrl::route('architecture.show', ['locale' => $locale, 'slug' => $slug]);
        $hrefLangMap = config('seo.hreflang', []);
        $modified = Carbon::createFromTimestamp($data['source_modified_at']);
        $published = $this->publishedCarbon($meta, $modified);
        $breadcrumbCurrent = Str::headline(str_replace('-', ' ', $slug));

        return view('architecture.show', [
            'content' => $data['html'],
            'meta' => $meta,
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'canonicalUrl' => $canonicalUrl,
            'breadcrumbCurrent' => $breadcrumbCurrent,
            'structuredData' => [
                '@type' => 'TechArticle',
                'headline' => $pageTitle,
                'description' => $pageDescription,
                'inLanguage' => $hrefLangMap[$locale] ?? $locale,
                'datePublished' => $published->toIso8601String(),
                'dateModified' => $modified->toIso8601String(),
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => $canonicalUrl,
                ],
            ],
        ]);
    }
}
