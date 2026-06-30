<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Services\MarkdownContentService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicArticleController extends Controller
{
    /**
     * Display listing of articles for a specific category.
     */
    public function categoryIndex(string $category_slug)
    {
        $locale = app()->getLocale();
        $category = Category::where('slug', $category_slug)->firstOrFail();
        
        $articles = Article::where('is_published', true)
            ->where('is_approved', true)
            ->whereHas('author', function ($q) {
                $q->where('is_approved', true)->where('is_blocked', false);
            })
            ->whereHas('categories', function ($q) use ($category) {
                $q->where('categories.id', $category->id);
            })
            ->with([
                'categories.translations',
                'author',
                'tags.translations',
                'translations' => function ($q) use ($locale) {
                    $q->where('locale', $locale);
                }
            ])
            ->orderBy('published_at', 'desc')
            ->paginate(12);

        $categories = Category::where('slug', '!=', 'jobs')->with(['translations'])->get();
        
        $tags = Tag::whereHas('articles', function($q) use ($category) {
            $q->where('is_published', true)
              ->where('is_approved', true)
              ->whereHas('author', function ($aq) {
                  $aq->where('is_approved', true)->where('is_blocked', false);
              })
              ->whereHas('categories', function ($cq) use ($category) {
                  $cq->where('categories.id', $category->id);
              });
        })->with(['translations'])->get();

        $authors = User::where('is_approved', true)
            ->where('is_blocked', false)
            ->whereHas('articles', function($q) use ($category) {
                $q->where('is_published', true)
                  ->where('is_approved', true)
                  ->whereHas('categories', function ($cq) use ($category) {
                      $cq->where('categories.id', $category->id);
                  });
            })->get();

        return view('catalog', [
            'articles' => $articles,
            'categories' => $categories,
            'tags' => $tags,
            'authors' => $authors,
            'selectedCategory' => $category,
            'selectedTag' => null,
            'selectedAuthor' => null,
            'search' => null,
            'sort' => 'latest',
        ]);
    }

    /**
     * Display a specific article under a category.
     */
    public function show(string $category_slug, string $article_slug = null, string $slug = null)
    {
        $locale = app()->getLocale();

        $route = request()->route();
        if ($route && ($route->parameter('category_slug') || $route->parameter('slug') || $route->parameter('article_slug'))) {
            $category_slug = $route->parameter('category_slug') ?: $category_slug;
            $article_slug = $route->parameter('article_slug') ?: $route->parameter('slug') ?: $article_slug;
        } else {
            // Direct calls in Unit Tests or route parameter shifts when $category_slug is the locale prefix
            if (in_array($category_slug, ['ru', 'en', 'ua', 'bg'], true)) {
                $locale = $category_slug;
                $category_slug = $article_slug;
                $article_slug = $slug;
            } else {
                $article_slug = $article_slug ?: $slug;
            }
        }

        $markdownService = app(MarkdownContentService::class);

        $category = Category::where('slug', $category_slug)->first();

        $articleObj = null;
        if ($category) {
            $articleObj = Article::where('slug', $article_slug)
                ->whereHas('categories', function ($q) use ($category) {
                    $q->where('categories.id', $category->id);
                })
                ->with(['tags.translations', 'author'])
                ->first();
        }

        // Fetch parsed markdown/DB content
        $data = $markdownService->getParsedContent($locale, $category_slug, $article_slug);

        if (!$data) {
            abort(404);
        }

        if ($articleObj) {
            $currentUser = auth()->user();
            $isOwnerOrAdmin = $currentUser && ($currentUser->isAdmin() || $currentUser->id === $articleObj->author_id);

            // Check if author is blocked
            if ($articleObj->author && $articleObj->author->is_blocked) {
                if (!$currentUser || !$currentUser->isAdmin()) {
                    abort(404);
                }
            }

            if (!$articleObj->is_approved || !$articleObj->is_published) {
                if (!$isOwnerOrAdmin) {
                    abort(404);
                }
            }
        }

        $meta = $data['meta'];
        $pageTitle = $this->scalarMetaString($meta, 'title') 
            ?? ($articleObj ? $articleObj->translate($locale)?->title : null) 
            ?? Str::headline(str_replace('-', ' ', $article_slug));
        
        $pageDescription = $this->scalarMetaString($meta, 'description') 
            ?? ($articleObj ? $articleObj->translate($locale)?->description : null) 
            ?? '';

        if ($articleObj && !empty($articleObj->custom_url)) {
            $canonicalUrl = $articleObj->url();
        } else {
            $canonicalUrl = url('/' . $locale . '/' . $category_slug . '/' . $article_slug);
        }

        $hrefLangMap = config('seo.hreflang', []);
        $modified = Carbon::createFromTimestamp($data['source_modified_at']);
        $published = $this->publishedCarbon($meta, $modified);
        $breadcrumbCurrent = Str::headline(str_replace('-', ' ', $article_slug));

        $tags = $articleObj ? $articleObj->tags : collect();

        $viewName = 'tools.show';
        if (view()->exists("{$category_slug}.show")) {
            $viewName = "{$category_slug}.show";
        }

        return view($viewName, [
            'article'         => $articleObj,
            'content'         => $data['html'],
            'meta'            => $meta,
            'tags'            => $tags,
            'pageTitle'       => $pageTitle,
            'pageDescription' => $pageDescription,
            'canonicalUrl'    => $canonicalUrl,
            'breadcrumbCurrent'=> $breadcrumbCurrent,
            'ogImage'         => $this->resolveOgImage($meta, $category_slug, $article_slug),
            'structuredData'  => [
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

    /**
     * Display an article matching a custom URL route.
     */
    public function showCustom(string $any)
    {
        $locale = app()->getLocale();
        $markdownService = app(MarkdownContentService::class);
        $path = '/' . ltrim($any, '/');
        
        $articleObj = Article::where('custom_url', $path)
            ->with(['categories', 'tags.translations', 'author'])
            ->first();

        if (!$articleObj) {
            abort(404);
        }

        // Authorization checks
        $currentUser = auth()->user();
        $isOwnerOrAdmin = $currentUser && ($currentUser->isAdmin() || $currentUser->id === $articleObj->author_id);

        if ($articleObj->author && $articleObj->author->is_blocked) {
            if (!$currentUser || !$currentUser->isAdmin()) {
                abort(404);
            }
        }

        if (!$articleObj->is_approved || !$articleObj->is_published) {
            if (!$isOwnerOrAdmin) {
                abort(404);
            }
        }

        // Resolve slug category directory mapping
        $category = $articleObj->categories->first();
        $category_slug = $category ? $category->slug : 'tools';

        $data = $markdownService->getParsedContent($locale, $category_slug, $articleObj->slug);

        if (!$data) {
            abort(404);
        }

        $meta = $data['meta'];
        $pageTitle = $this->scalarMetaString($meta, 'title') 
            ?? $articleObj->translate($locale)?->title 
            ?? Str::headline(str_replace('-', ' ', $articleObj->slug));
        
        $pageDescription = $this->scalarMetaString($meta, 'description') 
            ?? $articleObj->translate($locale)?->description 
            ?? '';
            
        $canonicalUrl = $articleObj->url();

        $hrefLangMap = config('seo.hreflang', []);
        $modified = Carbon::createFromTimestamp($data['source_modified_at']);
        $published = $this->publishedCarbon($meta, $modified);
        $breadcrumbCurrent = Str::headline(str_replace('-', ' ', $articleObj->slug));

        $tags = $articleObj->tags;

        $viewName = 'tools.show';
        if (view()->exists("{$category_slug}.show")) {
            $viewName = "{$category_slug}.show";
        }

        return view($viewName, [
            'article'         => $articleObj,
            'content'         => $data['html'],
            'meta'            => $meta,
            'tags'            => $tags,
            'pageTitle'       => $pageTitle,
            'pageDescription' => $pageDescription,
            'canonicalUrl'    => $canonicalUrl,
            'breadcrumbCurrent'=> $breadcrumbCurrent,
            'ogImage'         => $this->resolveOgImage($meta, $category_slug, $articleObj->slug),
            'structuredData'  => [
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
