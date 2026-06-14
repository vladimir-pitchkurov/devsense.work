<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Quiz;
use App\Models\ArticleSuggestion;
use App\Models\ArticleSuggestionComment;
use App\Models\User;
use App\Mail\NewArticleMail;
use App\Mail\NewQuizMail;
use App\Mail\NewCommentMail;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send email notifications about a new article to interested users.
     */
    public function notifyNewArticle(Article $article): void
    {
        if ($article->notified) {
            return;
        }

        $categoryIds = $article->categories->pluck('id');
        if ($categoryIds->isEmpty()) {
            return;
        }

        $users = User::whereNotNull('email_verified_at')
            ->where('notify_articles_quizzes', true)
            ->where(function ($query) {
                $query->whereNull('last_notified_at')
                    ->orWhere('last_notified_at', '<=', now()->subDays(7));
            })
            ->whereHas('interests', function ($query) use ($categoryIds) {
                $query->whereIn('categories.id', $categoryIds);
            })
            ->get();

        foreach ($users as $user) {
            Mail::to($user)
                ->locale($user->locale)
                ->send(new NewArticleMail($article, $user));

            $user->update(['last_notified_at' => now()]);
        }

        $article->update(['notified' => true]);
    }

    /**
     * Send email notifications about a new quiz to interested users.
     */
    public function notifyNewQuiz(Quiz $quiz): void
    {
        if ($quiz->notified) {
            return;
        }

        $categoryId = $quiz->category_id;
        if (!$categoryId) {
            return;
        }

        $users = User::whereNotNull('email_verified_at')
            ->where('notify_articles_quizzes', true)
            ->where(function ($query) {
                $query->whereNull('last_notified_at')
                    ->orWhere('last_notified_at', '<=', now()->subDays(7));
            })
            ->whereHas('interests', function ($query) use ($categoryId) {
                $query->where('categories.id', $categoryId);
            })
            ->get();

        foreach ($users as $user) {
            Mail::to($user)
                ->locale($user->locale)
                ->send(new NewQuizMail($quiz, $user));

            $user->update(['last_notified_at' => now()]);
        }

        $quiz->update(['notified' => true]);
    }

    /**
     * Send email notification to the article author about a new suggestion.
     */
    public function notifyNewSuggestion(ArticleSuggestion $suggestion): void
    {
        $article = $suggestion->article;
        if (!$article || !$article->id) {
            return;
        }

        $author = $article->author;
        if (!$author) {
            return;
        }

        // Avoid notifying self
        if ($author->id === $suggestion->user_id) {
            return;
        }

        if ($author->email_verified_at && $author->notify_comments) {
            if ($author->last_notified_at === null || $author->last_notified_at->lte(now()->subDays(7))) {
                Mail::to($author)
                    ->locale($author->locale)
                    ->send(new NewCommentMail(
                        $author,
                        $suggestion->user,
                        'suggestion',
                        $suggestion->content,
                        $article
                    ));

                $author->update(['last_notified_at' => now()]);
            }
        }
    }

    /**
     * Send email notification to the suggestion owner about a new comment.
     */
    public function notifyNewComment(ArticleSuggestionComment $comment): void
    {
        $suggestion = $comment->suggestion;
        if (!$suggestion) {
            return;
        }

        $owner = $suggestion->user;
        if (!$owner) {
            return;
        }

        // Avoid notifying self
        if ($owner->id === $comment->user_id) {
            return;
        }

        if ($owner->email_verified_at && $owner->notify_comments) {
            if ($owner->last_notified_at === null || $owner->last_notified_at->lte(now()->subDays(7))) {
                Mail::to($owner)
                    ->locale($owner->locale)
                    ->send(new NewCommentMail(
                        $owner,
                        $comment->user,
                        'comment',
                        $comment->content,
                        $suggestion->article
                    ));

                $owner->update(['last_notified_at' => now()]);
            }
        }
    }
}
