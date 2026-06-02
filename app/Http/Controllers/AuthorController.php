<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SiteUrl;
use Illuminate\View\View;

/**
 * Serves public author profile pages.
 *
 * Routes:
 *   GET /{locale}/authors         → AuthorController@index
 *   GET /{locale}/authors/{slug}  → AuthorController@show
 */
class AuthorController extends Controller
{
    /**
     * List all users with an author or super-admin role.
     */
    public function index(): View
    {
        $query = User::whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_AUTHOR]);

        if (!auth()->check() || !auth()->user()->isAdmin()) {
            $query->where('is_public', true)->where('is_approved', true);
        }

        $authors = $query->orderBy('name')->get();

        $locale       = app()->getLocale();
        $canonicalUrl = SiteUrl::route('authors.index', ['locale' => $locale]);

        return view('authors.index', [
            'authors'      => $authors,
            'pageTitle'    => __('ui.authors_index.title').' — DevSense',
            'pageDescription' => __('ui.authors_index.description'),
            'canonicalUrl' => $canonicalUrl,
        ]);
    }

    /**
     * Show a single author's public profile with their articles.
     *
     * @param  string  $slug  Author's URL slug (e.g. "vladimir-pitchkurov")
     */
    public function show(string $slug): View
    {
        // Match by slug column first, then fall back to slug generated from name
        $author = User::whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_AUTHOR])
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug)
                    ->orWhereRaw("LOWER(REPLACE(REPLACE(name, ' ', '-'), '.', '')) = ?", [strtolower($slug)]);
            })
            ->firstOrFail();

        $currentUser = auth()->user();

        if (!$author->is_approved) {
            if (!$currentUser || (!$currentUser->isAdmin() && $currentUser->id !== $author->id)) {
                abort(404);
            }
        }

        if (!$author->is_public) {
            if (!$currentUser || (!$currentUser->isAdmin() && $currentUser->id !== $author->id)) {
                abort(404);
            }
        }

        $locale       = app()->getLocale();
        $canonicalUrl = SiteUrl::route('authors.show', ['locale' => $locale, 'slug' => $author->slug]);
        $hrefLangMap  = config('seo.hreflang', []);
        $langCode     = $hrefLangMap[$locale] ?? $locale;

        $pageTitle       = $author->name.' — '.(__('ui.authors_show.title_suffix').' DevSense');
        $pageDescription = $author->bio
            ? mb_substr(strip_tags($author->bio), 0, 160)
            : __('ui.authors_show.description_fallback', ['name' => $author->name]);

        $structuredData = [
            '@type'       => 'Person',
            'name'        => $author->name,
            'description' => $pageDescription,
            'image'       => $author->avatarUrl(),
            'url'         => $canonicalUrl,
            'inLanguage'  => $langCode,
            'sameAs'      => array_values($author->socialLinks()),
        ];

        if ($author->job_title) {
            $structuredData['jobTitle'] = $author->job_title;
        }

        $isOwnerOrAdmin = $currentUser && ($currentUser->isAdmin() || $currentUser->id === $author->id);
        $articlesQuery = $author->articles()
            ->where('is_published', true)
            ->with(['translations', 'category'])
            ->latest('published_at');

        if (!$isOwnerOrAdmin) {
            $articlesQuery->where('is_approved', true);
        }

        $articles = $articlesQuery->get();

        return view('authors.show', [
            'author'         => $author,
            'articles'       => $articles,
            'pageTitle'      => $pageTitle,
            'pageDescription'=> $pageDescription,
            'canonicalUrl'   => $canonicalUrl,
            'structuredData' => $structuredData,
        ]);
    }
}
