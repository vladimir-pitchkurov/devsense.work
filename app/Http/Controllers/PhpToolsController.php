<?php

namespace App\Http\Controllers;

use App\Services\MarkdownContentService;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Serves localized Laravel Sail / tooling guides backed by Markdown content.
 */
class PhpToolsController extends Controller
{
    /**
     * Ordered list of tool slugs exposed under `/{locale}/tools/{slug}`.
     *
     * @var list<string>
     */
    private const TOOL_SLUG_ORDER = ['sail', 'sail-databases', 'sail-queues', 'sail-env-deploy', 'sail-troubleshooting'];

    /**
     * Display the tools index with cards for each documented tool.
     */
    public function index(): View
    {
        $cards = [];
        foreach (self::TOOL_SLUG_ORDER as $slug) {
            $key = str_replace('-', '_', $slug);
            $cards[] = [
                'slug' => $slug,
                'title' => __('ui.tools_index.cards.'.$key.'.title'),
                'excerpt' => __('ui.tools_index.cards.'.$key.'.excerpt'),
            ];
        }

        return view('tools.index', ['cards' => $cards]);
    }

    /**
     * Render a single tool guide parsed from Markdown.
     *
     * @param  string  $slug  URL segment; must be one of {@see PhpToolsController::TOOL_SLUG_ORDER}.
     */
    public function show(string $slug, MarkdownContentService $markdownService): View
    {
        if (! in_array($slug, self::TOOL_SLUG_ORDER, true)) {
            abort(404);
        }

        $locale = app()->getLocale();
        $data = $markdownService->getParsedContent($locale, 'tools', $slug);

        if (! $data) {
            abort(404, __('ui.errors.tools_guide_missing', ['slug' => $slug]));
        }

        $meta = $data['meta'];
        $cardKey = str_replace('-', '_', $slug);
        $pageTitle = $this->scalarMetaString($meta, 'title') ?? __('ui.tools_index.cards.'.$cardKey.'.title');
        $pageDescription = $this->scalarMetaString($meta, 'description') ?? '';
        $canonicalUrl = route('tools.show', ['locale' => $locale, 'slug' => $slug], true);
        $hrefLangMap = config('seo.hreflang', []);
        $modified = $data['source_modified_at'];
        $published = $this->publishedCarbon($meta, $modified);
        $breadcrumbCurrent = Str::headline(str_replace('-', ' ', $slug));

        return view('tools.show', [
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
