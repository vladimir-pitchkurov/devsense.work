<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Display the homepage landing layout.
     */
    public function index(): View
    {
        $locale = app()->getLocale();

        // Latest 3 articles
        $latestArticles = Article::where('is_published', true)
            ->where('is_approved', true)
            ->whereHas('author', function ($q) {
                $q->where('is_approved', true)->where('is_blocked', false);
            })
            ->with([
                'categories.translations', 
                'author', 
                'translations' => function ($q) use ($locale) {
                    $q->where('locale', $locale);
                }
            ])
            ->latest('published_at')
            ->limit(3)
            ->get();

        // Latest 3 quizzes
        $latestQuizzes = \App\Models\Quiz::with(['translations'])
            ->latest()
            ->limit(3)
            ->get();

        // Latest 3 suggestions
        $latestSuggestions = \App\Models\ArticleSuggestion::with(['user', 'article'])
            ->latest()
            ->limit(3)
            ->get();

        return view('welcome', compact('latestArticles', 'latestQuizzes', 'latestSuggestions'));
    }

    /**
     * Display a listing of the articles with search, filtering, and sorting.
     */
    public function catalog(Request $request): View
    {
        $locale = app()->getLocale();
        
        $query = Article::where('is_published', true)
            ->where('is_approved', true)
            ->whereHas('author', function ($q) {
                $q->where('is_approved', true)->where('is_blocked', false);
            })
            ->with([
                'categories.translations', 
                'author', 
                'tags.translations', 
                'translations' => function ($q) use ($locale) {
                    $q->where('locale', $locale);
                }
            ]);

        // Filter by Category
        $selectedCategory = null;
        $categorySlug = $request->query('category') ?: $request->route('category_slug');
        if ($categorySlug) {
            $selectedCategory = Category::where('slug', $categorySlug)->first();
            if ($selectedCategory) {
                $query->whereHas('categories', function ($q) use ($selectedCategory) {
                    $q->where('categories.id', $selectedCategory->id);
                });
            }
        }

        // Filter by Tag
        $selectedTag = null;
        if ($request->filled('tag')) {
            $selectedTag = Tag::where('slug', $request->query('tag'))->first();
            if ($selectedTag) {
                $query->whereHas('tags', function ($q) use ($selectedTag) {
                    $q->where('tags.id', $selectedTag->id);
                });
            }
        }

        // Filter by Author
        $selectedAuthor = null;
        if ($request->filled('author')) {
            $selectedAuthor = User::where('slug', $request->query('author'))->first();
            if ($selectedAuthor) {
                $query->where('author_id', $selectedAuthor->id);
            }
        }

        // Search Query
        $search = $request->query('q');
        if ($request->filled('q')) {
            $query->whereHas('translations', function ($q) use ($locale, $search) {
                $q->where('locale', $locale)
                  ->where(function ($sub) use ($search) {
                      $sub->where('title', 'like', "%{$search}%")
                           ->orWhere('description', 'like', "%{$search}%")
                           ->orWhere('content', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $sort = $request->query('sort', 'latest');
        if ($categorySlug === 'php' && $sort === 'latest') {
            $orderCases = [];
            foreach (\App\Http\Controllers\PhpVersionController::PHP_VERSION_ORDER as $index => $version) {
                $orderCases[] = "WHEN '" . addslashes($version) . "' THEN " . ($index + 1);
            }
            $query->orderByRaw("CASE articles.slug " . implode(' ', $orderCases) . " ELSE 999 END ASC");
        } elseif ($categorySlug === 'php' && $sort === 'oldest') {
            $orderCases = [];
            foreach (\App\Http\Controllers\PhpVersionController::PHP_VERSION_ORDER as $index => $version) {
                $orderCases[] = "WHEN '" . addslashes($version) . "' THEN " . ($index + 1);
            }
            $query->orderByRaw("CASE articles.slug " . implode(' ', $orderCases) . " ELSE 999 END DESC");
        } elseif ($sort === 'oldest') {
            $query->orderBy('published_at', 'asc');
        } elseif ($sort === 'alphabetical') {
            $query->join('article_translations', 'articles.id', '=', 'article_translations.article_id')
                ->where('article_translations.locale', $locale)
                ->orderBy('article_translations.title', 'asc')
                ->select('articles.*'); // avoid ID collision
        } else {
            // default latest
            $query->orderBy('published_at', 'desc');
        }

        $articles = $query->paginate(12)->withQueryString();

        // Get categories, tags, authors for filter panels
        $categories = Category::with(['translations'])->get();
        
        // Only show tags that have at least one published and approved article from an approved, non-blocked author
        $tags = Tag::whereHas('articles', function($q) {
            $q->where('is_published', true)
              ->where('is_approved', true)
              ->whereHas('author', function ($aq) {
                  $aq->where('is_approved', true)->where('is_blocked', false);
              });
        })->with(['translations'])->get();

        // Only show approved and non-blocked authors who have published and approved articles
        $authors = User::where('is_approved', true)
            ->where('is_blocked', false)
            ->whereHas('articles', function($q) {
                $q->where('is_published', true)->where('is_approved', true);
            })->get();

        return view('catalog', [
            'articles' => $articles,
            'categories' => $categories,
            'tags' => $tags,
            'authors' => $authors,
            'selectedCategory' => $selectedCategory,
            'selectedTag' => $selectedTag,
            'selectedAuthor' => $selectedAuthor,
            'search' => $search,
            'sort' => $sort,
        ]);
    }
}
