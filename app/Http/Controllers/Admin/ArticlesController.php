<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\PendingArticleTranslation;
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
    public function index(Request $request)
    {
        $query = Article::with(['author', 'categories', 'translations', 'pendingTranslations']);

        if (!Auth::user()->isAdmin()) {
            $query->where('author_id', Auth::id());
        } else {
            // Admin can filter by author
            if ($request->filled('author')) {
                $query->where('author_id', $request->input('author'));
            }
        }

        // Filter by Category
        if ($request->filled('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->input('category'));
            });
        }

        // Filter by Status
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'pending_approval') {
                $query->where('is_approved', false);
            } elseif ($status === 'pending_update') {
                $query->where('is_approved', true)
                      ->whereHas('pendingTranslations');
            } elseif ($status === 'published') {
                $query->where('is_approved', true)
                      ->where('is_published', true);
            } elseif ($status === 'draft') {
                $query->where('is_approved', true)
                      ->where('is_published', false);
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('slug', 'like', "%{$search}%")
                  ->orWhereHas('translations', function ($sq) use ($search) {
                      $sq->where('title', 'like', "%{$search}%")
                         ->orWhere('content', 'like', "%{$search}%");
                  })
                  ->orWhereHas('pendingTranslations', function ($sq) use ($search) {
                      $sq->where('title', 'like', "%{$search}%")
                         ->orWhere('content', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at_desc');
        switch ($sortBy) {
            case 'created_at_asc':
                $query->orderBy('created_at');
                break;
            case 'published_at_desc':
                $query->orderByDesc('published_at');
                break;
            case 'published_at_asc':
                $query->orderBy('published_at');
                break;
            case 'slug_asc':
                $query->orderBy('slug');
                break;
            case 'slug_desc':
                $query->orderByDesc('slug');
                break;
            case 'created_at_desc':
            default:
                $query->latest(); // Default order
                break;
        }

        $articles = $query->paginate(15)->withQueryString();

        $categories = Category::all();
        $authors = [];
        if (Auth::user()->isAdmin()) {
            $authors = \App\Models\User::whereIn('role', [\App\Models\User::ROLE_SUPER_ADMIN, \App\Models\User::ROLE_AUTHOR])->get();
        }

        return view('admin.articles.index', compact('articles', 'categories', 'authors'));
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
    public function store(Request $request, \App\Services\ContentSanitizer $sanitizer)
    {
        if ($request->has('category_id') && !$request->has('categories')) {
            $request->merge(['categories' => [$request->input('category_id')]]);
        } elseif (!$request->has('categories')) {
            $defaultCat = \App\Models\Category::first();
            if (!$defaultCat) {
                $defaultCat = \App\Models\Category::create(['slug' => 'default']);
            }
            $request->merge(['categories' => [$defaultCat->id]]);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'slug' => ['required', 'string', 'unique:articles,slug', 'max:255'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['exists:categories,id'],
            'custom_url' => ['nullable', 'string', 'max:255', 'unique:articles,custom_url'],
            'is_published' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
            
            // Translations validation
            'translations' => ['required', 'array'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.content' => ['nullable', 'string'],
            'translations.*.faq' => ['nullable', 'string'], // JSON string from form
        ]);

        $validator->after(function ($validator) use ($request) {
            $translations = $request->input('translations', []);
            $hasAtLeastOne = false;

            foreach ($translations as $locale => $data) {
                $title = isset($data['title']) ? trim($data['title']) : '';
                $content = isset($data['content']) ? trim($data['content']) : '';

                $hasTitle = $title !== '';
                $hasContent = $content !== '';

                if ($hasTitle && $hasContent) {
                    $hasAtLeastOne = true;
                } elseif ($hasTitle && !$hasContent) {
                    $validator->errors()->add("translations.{$locale}.content", "The content field is required when title is filled for the " . strtoupper($locale) . " translation.");
                } elseif (!$hasTitle && $hasContent) {
                    $validator->errors()->add("translations.{$locale}.title", "The title field is required when content is filled for the " . strtoupper($locale) . " translation.");
                }
            }

            if (!$hasAtLeastOne) {
                $validator->errors()->add('translations', 'At least one language translation (both Title and Content) must be filled.');
            }
        });

        $validator->validate();

        $isAdmin = Auth::user()->isAdmin();

        $article = Article::create([
            'slug' => Str::slug($request->slug),
            'author_id' => Auth::id(),
            'custom_url' => $request->custom_url ? '/' . ltrim($request->custom_url, '/') : null,
            'is_published' => (bool) $request->is_published,
            'published_at' => $request->is_published ? now() : null,
            'is_approved' => $isAdmin,
        ]);

        $article->categories()->sync($request->categories);

        if ($request->tags) {
            $article->tags()->sync($request->tags);
        }

        foreach ($request->translations as $locale => $data) {
            $title = isset($data['title']) ? trim($data['title']) : '';
            $content = isset($data['content']) ? trim($data['content']) : '';

            if ($title === '' && $content === '') {
                continue;
            }

            $sanitizedTitle = $sanitizer->sanitizePlainText($title);
            $description = $sanitizer->sanitizePlainText($data['description'] ?? null);
            $sanitizedContent = $sanitizer->sanitizeMarkdown($content);
            $faqJson = $sanitizer->sanitizeFaq($data['faq'] ?? null);

            $faqArray = null;
            if (!empty($faqJson)) {
                $faqArray = json_decode($faqJson, true);
            }

            if ($isAdmin) {
                ArticleTranslation::create([
                    'article_id' => $article->id,
                    'locale' => $locale,
                    'title' => $sanitizedTitle,
                    'description' => $description,
                    'content' => $sanitizedContent,
                    'faq' => $faqArray,
                ]);
            } else {
                PendingArticleTranslation::create([
                    'article_id' => $article->id,
                    'locale' => $locale,
                    'title' => $sanitizedTitle,
                    'description' => $description,
                    'content' => $sanitizedContent,
                    'faq' => $faqArray,
                ]);
            }
        }

        $message = $isAdmin 
            ? 'Article created successfully.' 
            : 'Article submitted for moderation. It will become visible once approved by an administrator.';

        return redirect()
            ->route('admin.articles.index', ['locale' => app()->getLocale()])
            ->with('success', $message);
    }

    /**
     * Show the form for editing the specified article.
     */
    public function edit(Article $article)
    {
        if (!Auth::user()->isAdmin() && $article->author_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $categories = Category::all();
        $tags = Tag::all();
        
        $article->load(['translations', 'tags', 'categories']);

        return view('admin.articles.edit', compact('article', 'categories', 'tags'));
    }

    /**
     * Update the specified article in storage.
     */
    public function update(Request $request, Article $article, \App\Services\ContentSanitizer $sanitizer)
    {
        if (!Auth::user()->isAdmin() && $article->author_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->has('category_id') && !$request->has('categories')) {
            $request->merge(['categories' => [$request->input('category_id')]]);
        } elseif (!$request->has('categories')) {
            $request->merge(['categories' => $article->categories->pluck('id')->toArray()]);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'slug' => ['required', 'string', 'unique:articles,slug,' . $article->id, 'max:255'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['exists:categories,id'],
            'custom_url' => ['nullable', 'string', 'max:255', 'unique:articles,custom_url,' . $article->id],
            'is_published' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
            
            // Translations validation
            'translations' => ['required', 'array'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'translations.*.content' => ['nullable', 'string'],
            'translations.*.faq' => ['nullable', 'string'], // JSON string from form
        ]);

        $validator->after(function ($validator) use ($request) {
            $translations = $request->input('translations', []);
            $hasAtLeastOne = false;

            foreach ($translations as $locale => $data) {
                $title = isset($data['title']) ? trim($data['title']) : '';
                $content = isset($data['content']) ? trim($data['content']) : '';

                $hasTitle = $title !== '';
                $hasContent = $content !== '';

                if ($hasTitle && $hasContent) {
                    $hasAtLeastOne = true;
                } elseif ($hasTitle && !$hasContent) {
                    $validator->errors()->add("translations.{$locale}.content", "The content field is required when title is filled for the " . strtoupper($locale) . " translation.");
                } elseif (!$hasTitle && $hasContent) {
                    $validator->errors()->add("translations.{$locale}.title", "The title field is required when content is filled for the " . strtoupper($locale) . " translation.");
                }
            }

            if (!$hasAtLeastOne) {
                $validator->errors()->add('translations', 'At least one language translation (both Title and Content) must be filled.');
            }
        });

        $validator->validate();

        $isAdmin = Auth::user()->isAdmin();

        $wasPublished = $article->is_published;
        $isPublished = (bool) $request->is_published;

        $article->update([
            'slug' => Str::slug($request->slug),
            'custom_url' => $request->custom_url ? '/' . ltrim($request->custom_url, '/') : null,
            'is_published' => $isPublished,
            'published_at' => $isPublished ? ($wasPublished ? $article->published_at : now()) : null,
        ]);

        $article->categories()->sync($request->categories ?? []);
        $article->tags()->sync($request->tags ?? []);

        foreach ($request->translations as $locale => $data) {
            $title = isset($data['title']) ? trim($data['title']) : '';
            $content = isset($data['content']) ? trim($data['content']) : '';

            if ($title === '' && $content === '') {
                if ($isAdmin) {
                    ArticleTranslation::where('article_id', $article->id)
                        ->where('locale', $locale)
                        ->delete();
                }
                PendingArticleTranslation::where('article_id', $article->id)
                    ->where('locale', $locale)
                    ->delete();
                continue;
            }

            $sanitizedTitle = $sanitizer->sanitizePlainText($title);
            $description = $sanitizer->sanitizePlainText($data['description'] ?? null);
            $sanitizedContent = $sanitizer->sanitizeMarkdown($content);
            $faqJson = $sanitizer->sanitizeFaq($data['faq'] ?? null);

            $faqArray = null;
            if (!empty($faqJson)) {
                $faqArray = json_decode($faqJson, true);
            }

            if ($isAdmin) {
                ArticleTranslation::updateOrCreate([
                    'article_id' => $article->id,
                    'locale' => $locale,
                ], [
                    'title' => $sanitizedTitle,
                    'description' => $description,
                    'content' => $sanitizedContent,
                    'faq' => $faqArray,
                ]);
                PendingArticleTranslation::where('article_id', $article->id)
                    ->where('locale', $locale)
                    ->delete();
            } else {
                PendingArticleTranslation::updateOrCreate([
                    'article_id' => $article->id,
                    'locale' => $locale,
                ], [
                    'title' => $sanitizedTitle,
                    'description' => $description,
                    'content' => $sanitizedContent,
                    'faq' => $faqArray,
                ]);
            }
        }

        $message = $isAdmin 
            ? 'Article updated successfully.' 
            : 'Article updates submitted for moderation. Live version remains online with previous content.';

        return redirect()
            ->route('admin.articles.index', ['locale' => app()->getLocale()])
            ->with('success', $message);
    }

    /**
     * Remove the specified article from storage.
     */
    public function destroy(Article $article)
    {
        if (!Auth::user()->isAdmin() && $article->author_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }

        $article->delete();
 
         return redirect()
             ->route('admin.articles.index', ['locale' => app()->getLocale()])
             ->with('success', 'Article deleted successfully.');
     }
 
     /**
      * Download the Markdown article template.
      */
     public function downloadTemplate()
     {
         $content = <<<'EOD'
---
title: "How to Use Property Hooks in PHP 8.4"
description: "A comprehensive guide on PHP 8.4 property hooks, showcasing syntax, use cases, and best practices."
slug: "php-8-4-property-hooks"
faq:
  - question: "What are PHP 8.4 property hooks?"
    answer: "Property hooks allow you to intercept and customize property read and write operations directly inside the class definition, eliminating the need for boilerplate getter/setter methods."
  - question: "Can I use property hooks with readonly properties?"
    answer: "No, property hooks cannot be defined on readonly properties."
---

# How to Use Property Hooks in PHP 8.4

DevSense articles follow a clean, readable layout. Here is how to structure your guides:

## Table of Contents
- [Introduction](#introduction)
- [Basic Syntax](#basic-syntax)
- [Common Mistakes](#common-mistakes)
- [Self-Check Quiz](#self-check-quiz)

## Introduction {#introduction}
Property hooks are one of the most exciting additions to PHP 8.4. They allow you to define `get` and `set` operations directly inside property declarations.

> [!NOTE]
> Property hooks are supported starting from PHP 8.4. Make sure your environment is updated.

## Basic Syntax {#basic-syntax}
Here is a code example showing property hooks in action. Note the filename comment at the very beginning of the block:

```php
// app/DTOs/UserDTO.php
class UserDTO
{
    public string $name {
        set => trim($value);
    }
}
```

> [!IMPORTANT]
> Always include a file path comment as the first line of code blocks when explaining code modifications.

## Common Mistakes {#common-mistakes}
Avoid these pitfalls:
1. Defining hooks on `readonly` properties.
2. Referencing the property itself directly inside its own `get`/`set` hooks without using `$value` or backing values, which causes infinite recursion.

> [!WARNING]
> Infinite loops are easy to trigger if you reference `$this->prop` inside the hook instead of the hook-specific syntax.

## Self-Check Quiz {#self-check-quiz}
Test your knowledge with these quick questions:

<details>
<summary>Can a virtual property (a property with no backing value) have a set hook without a get hook?</summary>
No, if a virtual property has a set hook, it must also have a get hook, or it must be a backed property.
</details>

<details>
<summary>What variable name represents the new value in a set hook?</summary>
The variable `$value` is automatically provided to set hooks.
</details>

EOD;

         return response($content, 200, [
             'Content-Type' => 'text/markdown',
             'Content-Disposition' => 'attachment; filename="devsense-article-template.md"',
         ]);
     }
 }
