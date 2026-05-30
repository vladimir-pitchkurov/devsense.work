<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ArticlesController extends Controller
{
    /**
     * Display a listing of the articles.
     */
    public function index()
    {
        $articles = Article::with(['author', 'category'])
            ->latest()
            ->paginate(15);

        return view('admin.articles.index', compact('articles'));
    }

    /**
     * Show the form for creating a new article.
     */
    public function create()
    {
        $categories = Category::all();
        $tags = Tag::all();

        return view('admin.articles.create', compact('categories', 'tags'));
    }

    /**
     * Store a newly created article in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'slug' => ['required', 'string', 'unique:articles,slug', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'is_published' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
            
            // Translations validation
            'translations' => ['required', 'array'],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.content' => ['required', 'string'],
            'translations.*.faq' => ['nullable', 'string'], // JSON string from form
        ]);

        $article = Article::create([
            'slug' => Str::slug($request->slug),
            'author_id' => Auth::id(),
            'category_id' => $request->category_id,
            'is_published' => (bool) $request->is_published,
            'published_at' => $request->is_published ? now() : null,
        ]);

        if ($request->tags) {
            $article->tags()->sync($request->tags);
        }

        foreach ($request->translations as $locale => $data) {
            $faqArray = null;
            if (!empty($data['faq'])) {
                $faqArray = json_decode($data['faq'], true);
            }

            ArticleTranslation::create([
                'article_id' => $article->id,
                'locale' => $locale,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'content' => $data['content'],
                'faq' => $faqArray,
            ]);
        }

        return redirect()
            ->route('admin.articles.index', ['locale' => app()->getLocale()])
            ->with('success', 'Article created successfully.');
    }

    /**
     * Show the form for editing the specified article.
     */
    public function edit(Article $article)
    {
        $categories = Category::all();
        $tags = Tag::all();
        
        $article->load(['translations', 'tags']);

        return view('admin.articles.edit', compact('article', 'categories', 'tags'));
    }

    /**
     * Update the specified article in storage.
     */
    public function update(Request $request, Article $article)
    {
        $request->validate([
            'slug' => ['required', 'string', 'unique:articles,slug,' . $article->id, 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'is_published' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
            
            // Translations validation
            'translations' => ['required', 'array'],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.content' => ['required', 'string'],
            'translations.*.faq' => ['nullable', 'string'], // JSON string from form
        ]);

        $wasPublished = $article->is_published;
        $isPublished = (bool) $request->is_published;

        $article->update([
            'slug' => Str::slug($request->slug),
            'category_id' => $request->category_id,
            'is_published' => $isPublished,
            'published_at' => $isPublished ? ($wasPublished ? $article->published_at : now()) : null,
        ]);

        $article->tags()->sync($request->tags ?? []);

        foreach ($request->translations as $locale => $data) {
            $faqArray = null;
            if (!empty($data['faq'])) {
                $faqArray = json_decode($data['faq'], true);
            }

            ArticleTranslation::updateOrCreate([
                'article_id' => $article->id,
                'locale' => $locale,
            ], [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'content' => $data['content'],
                'faq' => $faqArray,
            ]);
        }

        return redirect()
            ->route('admin.articles.index', ['locale' => app()->getLocale()])
            ->with('success', 'Article updated successfully.');
    }

    /**
     * Remove the specified article from storage.
     */
    public function destroy(Article $article)
    {
        $article->delete();

        return redirect()
            ->route('admin.articles.index', ['locale' => app()->getLocale()])
            ->with('success', 'Article deleted successfully.');
    }
}
