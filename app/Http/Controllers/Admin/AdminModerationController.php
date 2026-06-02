<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Article;
use App\Models\PendingUserProfile;
use App\Models\PendingArticleTranslation;
use App\Models\ArticleTranslation;
use App\Models\Report;
use Illuminate\Http\Request;

class AdminModerationController extends Controller
{
    /**
     * Approve a newly registered author.
     */
    public function approveAuthor(User $user)
    {
        $user->update(['is_approved' => true]);

        return back()->with('success', "Author '{$user->name}' has been approved and is now active.");
    }

    /**
     * Reject a newly registered author.
     */
    public function rejectAuthor(User $user)
    {
        if ($user->isAdmin()) {
            return back()->with('error', "Cannot reject an administrator.");
        }

        $name = $user->name;
        $user->delete();

        return back()->with('success', "Author registration for '{$name}' has been rejected and the account deleted.");
    }

    /**
     * Approve a pending profile update.
     */
    public function approveProfile(PendingUserProfile $pendingUserProfile)
    {
        $user = $pendingUserProfile->user;

        $user->update([
            'name' => $pendingUserProfile->name,
            'slug' => $pendingUserProfile->slug,
            'job_title' => $pendingUserProfile->job_title,
            'bio' => $pendingUserProfile->bio,
            'avatar_path' => $pendingUserProfile->avatar_path,
            'github_url' => $pendingUserProfile->github_url,
            'linkedin_url' => $pendingUserProfile->linkedin_url,
            'twitter_url' => $pendingUserProfile->twitter_url,
            'website_url' => $pendingUserProfile->website_url,
        ]);

        $pendingUserProfile->delete();

        return back()->with('success', "Profile updates for '{$user->name}' have been approved and published.");
    }

    /**
     * Reject a pending profile update.
     */
    public function rejectProfile(PendingUserProfile $pendingUserProfile)
    {
        $userName = $pendingUserProfile->user->name;
        $pendingUserProfile->delete();

        return back()->with('success', "Profile updates for '{$userName}' have been rejected.");
    }

    /**
     * Approve a pending article translation.
     */
    public function approveArticle(PendingArticleTranslation $pendingArticleTranslation)
    {
        $article = $pendingArticleTranslation->article;

        ArticleTranslation::updateOrCreate([
            'article_id' => $pendingArticleTranslation->article_id,
            'locale' => $pendingArticleTranslation->locale,
        ], [
            'title' => $pendingArticleTranslation->title,
            'description' => $pendingArticleTranslation->description,
            'content' => $pendingArticleTranslation->content,
            'faq' => $pendingArticleTranslation->faq,
        ]);

        // Auto-approve the article container itself if it was new
        if (!$article->is_approved) {
            $article->update(['is_approved' => true]);
        }

        $pendingArticleTranslation->delete();

        return back()->with('success', "Article '{$pendingArticleTranslation->title}' ({$pendingArticleTranslation->locale}) draft approved and published.");
    }

    /**
     * Reject a pending article translation.
     */
    public function rejectArticle(PendingArticleTranslation $pendingArticleTranslation)
    {
        $title = $pendingArticleTranslation->title;
        $article = $pendingArticleTranslation->article;
        
        $pendingArticleTranslation->delete();

        // If the article is not approved and has no other pending or live translations, clean up the article record.
        $hasLive = ArticleTranslation::where('article_id', $article->id)->exists();
        $hasPending = PendingArticleTranslation::where('article_id', $article->id)->exists();

        if (!$article->is_approved && !$hasLive && !$hasPending) {
            $article->delete();
            return back()->with('success', "Article draft '{$title}' was rejected. Since this was a new article with no other translations, the article has been deleted.");
        }

        return back()->with('success', "Article draft '{$title}' has been rejected.");
    }

    /**
     * Dismiss a user report.
     */
    public function dismissReport(Report $report)
    {
        $report->update(['status' => 'dismissed']);

        return back()->with('success', "Report ID #{$report->id} has been dismissed.");
    }

    /**
     * Take moderation action on reported content.
     */
    public function actionReport(Report $report)
    {
        $reportable = $report->reportable;

        if ($reportable instanceof Article) {
            // Suspend article (mark as unapproved and unpublished)
            $reportable->update([
                'is_approved' => false,
                'is_published' => false,
            ]);
            $report->update(['status' => 'resolved']);
            return back()->with('success', "Content action completed: Article '{$reportable->slug}' has been suspended (marked unapproved and unpublished).");
        }

        if ($reportable instanceof User) {
            if ($reportable->isAdmin()) {
                return back()->with('error', "Cannot suspend an administrator account.");
            }
            // Suspend user (mark as unapproved)
            $reportable->update([
                'is_approved' => false,
            ]);
            $report->update(['status' => 'resolved']);
            return back()->with('success', "Content action completed: Author '{$reportable->name}' has been suspended.");
        }

        return back()->with('error', "Unsupported report target.");
    }
}
