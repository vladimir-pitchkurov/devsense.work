<?php

namespace App\Http\Controllers;

use App\Services\MarkdownContentService;
use App\Support\SiteUrl;
use Carbon\Carbon;
use Illuminate\View\View;

/**
 * Serves localized PHP version changelog / guide pages from Markdown.
 */
class PhpVersionController extends Controller
{
    /**
     * PHP versions with documentation, ordered for display on the index.
     *
     * @var list<string>
     */
    private const PHP_VERSION_ORDER = ['8.5', 'runtimes', '8.4', '8.3', '8.2', '8.1', '8.0', '7.4', '7.3', '7.2', '7.1', '7.0', '5.6', '5.5', '5.4', '5.3'];

    /**
     * Display the PHP guides index (cards per version).
     */
    public function index(): View
    {
        $cards = [];
        foreach (self::PHP_VERSION_ORDER as $slug) {
            $key = 'v'.str_replace('.', '', $slug);
            $cards[] = [
                'version' => $slug,
                'title' => __("ui.php_index.cards.{$key}.title"),
                'excerpt' => __("ui.php_index.cards.{$key}.excerpt"),
            ];
        }

        return view('php.index', ['cards' => $cards]);
    }

    /**
     * Render a single PHP version guide parsed from Markdown.
     *
     * @param  string  $version  Version segment (e.g. `8.5`).
     */
    public function show(string $version, MarkdownContentService $markdownService): View
    {
        $locale = app()->getLocale();
        $data = $markdownService->getParsedContent($locale, 'php', $version);

        if (! $data) {
            abort(404, __('ui.errors.php_guide_missing', ['version' => $version]));
        }

        $meta = $data['meta'];
        $pageTitle = $this->scalarMetaString($meta, 'title') ?? 'PHP '.$version.' - DevSense';
        $pageDescription = $this->scalarMetaString($meta, 'description') ?? '';
        $canonicalUrl = SiteUrl::route('php.show', ['locale' => $locale, 'version' => $version]);
        $hrefLangMap = config('seo.hreflang', []);
        $modified = Carbon::createFromTimestamp($data['source_modified_at']);
        $published = $this->publishedCarbon($meta, $modified);

        $articleObj = \App\Models\Article::where('slug', $version)
            ->with(['tags.translations'])
            ->first();

        if ($articleObj && !$articleObj->is_approved) {
            $currentUser = auth()->user();
            if (!$currentUser || (!$currentUser->isAdmin() && $currentUser->id !== $articleObj->author_id)) {
                abort(404, __('ui.errors.php_guide_missing', ['version' => $version]));
            }
        }

        $tags = $articleObj ? $articleObj->tags : collect();

        return view('php.show', [
            'article'        => $articleObj,
            'content'        => $data['html'],
            'meta'           => $meta,
            'version'        => $version,
            'tags'           => $tags,
            'pageTitle'      => $pageTitle,
            'pageDescription'=> $pageDescription,
            'canonicalUrl'   => $canonicalUrl,
            'ogImage'        => $this->resolveOgImage($meta, 'php', $version),
            'structuredData' => [
                '@type'           => 'TechArticle',
                'headline'        => $pageTitle,
                'description'     => $pageDescription,
                'inLanguage'      => $hrefLangMap[$locale] ?? $locale,
                'datePublished'   => $published->toIso8601String(),
                'dateModified'    => $modified->toIso8601String(),
                'mainEntityOfPage'=> [
                    '@type' => 'WebPage',
                    '@id'   => $canonicalUrl,
                ],
                'faq' => $meta['faq'] ?? null,
            ],
        ]);
    }
}
