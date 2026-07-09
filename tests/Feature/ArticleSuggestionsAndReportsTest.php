<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Models\ArticleSuggestion;
use App\Models\ArticleSuggestionComment;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticleSuggestionsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user
        $this->user = User::factory()->create([
            'is_blocked' => false,
        ]);

        // Create article
        $this->article = Article::create([
            'slug' => 'test-article',
            'author_id' => $this->user->id,
            'is_approved' => true,
            'is_published' => true,
        ]);
    }

    public function test_guest_cannot_submit_suggestion(): void
    {
        $response = $this->post("/en/articles/{$this->article->id}/suggestions", [
            'content' => 'This is a test suggestion content that is long enough.',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_submit_suggestion(): void
    {
        $response = $this->actingAs($this->user)->post("/en/articles/{$this->article->id}/suggestions", [
            'content' => 'This is a valid suggestion from an authenticated user.',
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('article_suggestions', [
            'article_id' => $this->article->id,
            'user_id' => $this->user->id,
            'content' => 'This is a valid suggestion from an authenticated user.',
            'status' => 'pending',
        ]);
    }

    public function test_suggestion_content_validation(): void
    {
        // Content too short (min:10)
        $response = $this->actingAs($this->user)->post("/en/articles/{$this->article->id}/suggestions", [
            'content' => 'Short',
        ]);

        $response->assertSessionHasErrors('content');
    }

    public function test_guest_cannot_vote_on_suggestion(): void
    {
        $suggestion = ArticleSuggestion::create([
            'article_id' => $this->article->id,
            'user_id' => $this->user->id,
            'content' => 'This is a valid suggestion content.',
        ]);

        $response = $this->postJson("/en/suggestions/{$suggestion->id}/vote");
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_toggle_vote_on_suggestion(): void
    {
        $suggestion = ArticleSuggestion::create([
            'article_id' => $this->article->id,
            'user_id' => $this->user->id,
            'content' => 'This is a valid suggestion content.',
        ]);

        // First vote (adds upvote)
        $response = $this->actingAs($this->user)->postJson("/en/suggestions/{$suggestion->id}/vote");
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'voted' => true,
            'count' => 1,
        ]);

        $this->assertDatabaseHas('article_suggestion_votes', [
            'user_id' => $this->user->id,
            'suggestion_id' => $suggestion->id,
        ]);

        // Second vote (removes upvote)
        $response = $this->actingAs($this->user)->postJson("/en/suggestions/{$suggestion->id}/vote");
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'voted' => false,
            'count' => 0,
        ]);

        $this->assertDatabaseMissing('article_suggestion_votes', [
            'user_id' => $this->user->id,
            'suggestion_id' => $suggestion->id,
        ]);
    }

    public function test_guest_cannot_comment_on_suggestion(): void
    {
        $suggestion = ArticleSuggestion::create([
            'article_id' => $this->article->id,
            'user_id' => $this->user->id,
            'content' => 'This is a valid suggestion content.',
        ]);

        $response = $this->post("/en/suggestions/{$suggestion->id}/comments", [
            'content' => 'This is a comment.',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_comment_on_suggestion(): void
    {
        $suggestion = ArticleSuggestion::create([
            'article_id' => $this->article->id,
            'user_id' => $this->user->id,
            'content' => 'This is a valid suggestion content.',
        ]);

        $response = $this->actingAs($this->user)->post("/en/suggestions/{$suggestion->id}/comments", [
            'content' => 'This is a valid comment content.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('article_suggestion_comments', [
            'user_id' => $this->user->id,
            'suggestion_id' => $suggestion->id,
            'content' => 'This is a valid comment content.',
        ]);
    }

    public function test_user_can_submit_complaint_report_with_screenshot(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('screenshot.png');

        $response = $this->postJson("/en/reports", [
            'reason' => 'This content violates rules by containing spam.',
            'reportable_type' => 'article',
            'reportable_id' => $this->article->id,
            'type' => 'spam',
            'screenshot' => $file,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        // Assert report exists in DB
        $report = Report::latest()->first();
        $this->assertNotNull($report);
        $this->assertEquals('spam', $report->type);
        $this->assertEquals('This content violates rules by containing spam.', $report->reason);
        $this->assertNotNull($report->screenshot_path);

        // Check if screenshot file was stored
        $filePath = public_path($report->screenshot_path);
        $this->assertFileExists($filePath);

        // Clean up the file
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function test_user_can_report_suggestion_comment(): void
    {
        $suggestion = ArticleSuggestion::create([
            'article_id' => $this->article->id,
            'user_id' => $this->user->id,
            'content' => 'This is a valid suggestion content.',
        ]);

        $comment = ArticleSuggestionComment::create([
            'user_id' => $this->user->id,
            'suggestion_id' => $suggestion->id,
            'content' => 'Bad comment content.',
        ]);

        $response = $this->postJson("/en/reports", [
            'reason' => 'This is highly offensive.',
            'reportable_type' => 'comment',
            'reportable_id' => $comment->id,
            'type' => 'insult',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('reports', [
            'reportable_type' => ArticleSuggestionComment::class,
            'reportable_id' => $comment->id,
            'type' => 'insult',
            'reason' => 'This is highly offensive.',
        ]);
    }
}
