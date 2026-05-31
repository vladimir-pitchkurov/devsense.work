<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Support\SiteUrl;
use Illuminate\View\View;

/**
 * Serves public tag pages.
 *
 * Routes:
 *   GET /{locale}/tags        → TagController@index
 *   GET /{locale}/tags/{slug} → TagController@show
 */
class TagController extends Controller
{
    /**
     * Display listing of all tags that have published articles.
     */
    public function index(): View
    {
        // Get tags that have at least one published article
        $tags = Tag::whereHas('articles', function ($query) {
            $query->where('is_published', true);
        })
        ->with('translations')
        ->withCount(['articles' => function ($query) {
            $query->where('is_published', true);
        }])
        ->orderBy('slug')
        ->get();

        $locale = app()->getLocale();
        $canonicalUrl = SiteUrl::route('tags.index', ['locale' => $locale]);

        return view('tags.index', [
            'tags' => $tags,
            'pageTitle' => __('ui.tags_index.title') . ' — DevSense',
            'pageDescription' => __('ui.tags_index.description'),
            'canonicalUrl' => $canonicalUrl,
        ]);
    }

    /**
     * Show a tag and all articles linked to it.
     */
    public function show(string $slug): View
    {
        $tag = Tag::where('slug', $slug)
            ->with(['translations'])
            ->firstOrFail();

        $articles = $tag->articles()
            ->where('is_published', true)
            ->with(['translations', 'category'])
            ->latest('published_at')
            ->paginate(12);

        $locale = app()->getLocale();
        $canonicalUrl = SiteUrl::route('tags.show', ['locale' => $locale, 'slug' => $tag->slug]);
        $hrefLangMap = config('seo.hreflang', []);
        $langCode = $hrefLangMap[$locale] ?? $locale;

        $tagName = $tag->translate($locale)?->name ?? $tag->slug;
        $pageTitle = $tagName . ' ' . __('ui.tags_show.title_suffix') . ' — DevSense';
        $pageDescription = __('ui.tags_index.hero_lead') . ' - #' . $tagName;

        $structuredData = [
            '@type' => 'WebPage',
            'name' => $pageTitle,
            'description' => $pageDescription,
            'url' => $canonicalUrl,
            'inLanguage' => $langCode,
        ];

        return view('tags.show', [
            'tag' => $tag,
            'articles' => $articles,
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'canonicalUrl' => $canonicalUrl,
            'structuredData' => $structuredData,
        ]);
    }
}
