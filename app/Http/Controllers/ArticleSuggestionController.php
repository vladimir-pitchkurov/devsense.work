<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleSuggestion;
use Illuminate\Http\Request;

class ArticleSuggestionController extends Controller
{
    /**
     * Display a global list of suggestions.
     */
    public function index()
    {
        $suggestions = ArticleSuggestion::with(['user', 'article'])
            ->withCount('votes')
            ->orderByDesc('votes_count')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('suggestions.index', compact('suggestions'));
    }

    /**
     * Store a general/site suggestion (without a specific article).
     */
    public function storeGeneral(Request $request)
    {
        $request->validate([
            'content' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        ArticleSuggestion::create([
            'article_id' => null,
            'user_id' => auth()->id(),
            'content' => $request->content,
            'status'  => 'pending',
        ]);

        return back()->with('success', __('ui.suggestions.created_success') ?: 'Your suggestion has been submitted successfully.');
    }

    /**
     * Store a new suggestion for the article.
     */
    public function store(Request $request, Article $article)
    {
        $request->validate([
            'content' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $suggestion = $article->suggestions()->create([
            'user_id' => auth()->id(),
            'content' => $request->content,
            'status'  => 'pending',
        ]);

        app(\App\Services\NotificationService::class)->notifyNewSuggestion($suggestion);

        return back()->with('success', __('ui.suggestions.created_success'));
    }

    /**
     * Toggle upvote for the suggestion.
     */
    public function vote(ArticleSuggestion $suggestion)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $vote = $suggestion->votes()->where('user_id', $user->id)->first();

        if ($vote) {
            $vote->delete();
            $voted = false;
        } else {
            $suggestion->votes()->create([
                'user_id' => $user->id,
            ]);
            $voted = true;
        }

        return response()->json([
            'success' => true,
            'voted'   => $voted,
            'count'   => $suggestion->votes()->count(),
        ]);
    }

    /**
     * Store a comment/supplement for the suggestion.
     */
    public function storeComment(Request $request, ArticleSuggestion $suggestion)
    {
        $request->validate([
            'content' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $comment = $suggestion->comments()->create([
            'user_id' => auth()->id(),
            'content' => $request->content,
        ]);

        app(\App\Services\NotificationService::class)->notifyNewComment($comment);

        return back()->with('success', __('ui.suggestions.comment_created_success'));
    }
}
