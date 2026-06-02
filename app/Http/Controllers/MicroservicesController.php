<?php

namespace App\Http\Controllers;

use App\Services\MarkdownContentService;
use App\Support\SiteUrl;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Serves localized microservices architecture guides backed by Markdown content.
 */
class MicroservicesController extends Controller
{
    /**
     * Ordered list of guide slugs under `/{locale}/microservices/{slug}`.
     *
     * @var list<string>
     */
    private const MICROSERVICES_SLUG_ORDER = ['api-gateway'];

    /**
     * Route constraint regex fragment for `{slug}` (alternation of known slugs).
     */
    public static function slugRoutePattern(): string
    {
        return implode('|', self::MICROSERVICES_SLUG_ORDER);
    }

    /**
     * Display the microservices section index.
     */
    public function index(): View
    {
        $cards = [];
        foreach (self::MICROSERVICES_SLUG_ORDER as $slug) {
            $key = str_replace('-', '_', $slug);
            $cards[] = [
                'slug' => $slug,
                'title' => __('ui.microservices_index.cards.'.$key.'.title'),
                'excerpt' => __('ui.microservices_index.cards.'.$key.'.excerpt'),
            ];
        }

        return view('microservices.index', ['cards' => $cards]);
    }

    /**
     * Render a single guide from Markdown.
     *
     * @param  string  $slug  URL segment; must be one of {@see MicroservicesController::MICROSERVICES_SLUG_ORDER}.
     */
    public function show(string $slug, MarkdownContentService $markdownService): View
    {
        $existsInDb = \App\Models\Article::where('slug', $slug)
            ->whereHas('category', function ($q) {
                $q->where('slug', 'microservices');
            })
            ->exists();

        if (! in_array($slug, self::MICROSERVICES_SLUG_ORDER, true) && !$existsInDb) {
            abort(404);
        }

        $locale = app()->getLocale();
        $data = $markdownService->getParsedContent($locale, 'microservices', $slug);

        if (! $data) {
            abort(404, __('ui.errors.microservices_guide_missing', ['slug' => $slug]));
        }

        $meta = $data['meta'];
        $cardKey = str_replace('-', '_', $slug);
        $pageTitle = $this->scalarMetaString($meta, 'title') ?? __('ui.microservices_index.cards.'.$cardKey.'.title');
        $pageDescription = $this->scalarMetaString($meta, 'description') ?? '';
        $canonicalUrl = SiteUrl::route('microservices.show', ['locale' => $locale, 'slug' => $slug]);
        $hrefLangMap = config('seo.hreflang', []);
        $modified = Carbon::createFromTimestamp($data['source_modified_at']);
        $published = $this->publishedCarbon($meta, $modified);
        $breadcrumbCurrent = Str::headline(str_replace('-', ' ', $slug));

        $articleObj = \App\Models\Article::where('slug', $slug)
            ->with(['tags.translations', 'author'])
            ->first();

        if ($articleObj) {
            $currentUser = auth()->user();
            $isOwnerOrAdmin = $currentUser && ($currentUser->isAdmin() || $currentUser->id === $articleObj->author_id);

            // Check if author is blocked
            if ($articleObj->author && $articleObj->author->is_blocked) {
                if (!$currentUser || !$currentUser->isAdmin()) {
                    abort(404, __('ui.errors.microservices_guide_missing', ['slug' => $slug]));
                }
            }

            if (!$articleObj->is_approved || !$articleObj->is_published) {
                if (!$isOwnerOrAdmin) {
                    abort(404, __('ui.errors.microservices_guide_missing', ['slug' => $slug]));
                }
            }
        }

        $tags = $articleObj ? $articleObj->tags : collect();

        return view('microservices.show', [
            'article'          => $articleObj,
            'content'          => $data['html'],
            'meta'             => $meta,
            'tags'             => $tags,
            'pageTitle'        => $pageTitle,
            'pageDescription'  => $pageDescription,
            'canonicalUrl'     => $canonicalUrl,
            'breadcrumbCurrent'=> $breadcrumbCurrent,
            'ogImage'          => $this->resolveOgImage($meta, 'microservices', $slug),
            'structuredData'   => [
                '@type'            => 'TechArticle',
                'headline'         => $pageTitle,
                'description'      => $pageDescription,
                'inLanguage'       => $hrefLangMap[$locale] ?? $locale,
                'datePublished'    => $published->toIso8601String(),
                'dateModified'     => $modified->toIso8601String(),
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id'   => $canonicalUrl,
                ],
                'faq' => $meta['faq'] ?? null,
            ],
        ]);
    }
}
