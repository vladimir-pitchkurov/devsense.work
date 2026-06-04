<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Article;
use App\Models\Category;
use App\Models\PendingUserProfile;
use App\Models\PendingArticleTranslation;
use App\Models\ArticleTranslation;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $author;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        
        $this->author = User::factory()->author()->create([
            'name' => 'Jane Doe',
            'slug' => 'jane-doe',
            'is_approved' => true, // Already approved author
        ]);

        $this->category = Category::create(['slug' => 'php']);
    }

    /**
     * Test newly registered authors start unapproved and hidden.
     */
    public function test_newly_registered_author_is_unapproved_and_hidden(): void
    {
        $response = $this->post('/register', [
            'name' => 'New Author',
            'email' => 'newauthor@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
            'terms' => 'on',
        ]);

        $response->assertRedirect('/en/admin');

        $author = User::where('email', 'newauthor@devsense.work')->first();
        $this->assertNotNull($author);
        $this->assertFalse($author->is_approved);

        // Guests cannot view their public profile page
        auth()->logout();
        $this->get('/en/authors/new-author')->assertNotFound();

        // Guest index does not show them
        $indexResponse = $this->get('/en/authors');
        $indexResponse->assertOk();
        $indexResponse->assertDontSee('New Author');
    }

    /**
     * Test admin approves new author.
     */
    public function test_admin_approves_new_author(): void
    {
        $unapproved = User::factory()->author()->create([
            'name' => 'Unapproved Author',
            'slug' => 'unapproved-author',
            'is_approved' => false,
            'is_public' => true,
        ]);

        // Submit approval
        $response = $this->actingAs($this->admin)
            ->post(route('admin.moderation.authors.approve', ['locale' => 'en', 'user' => $unapproved->id]));

        $response->assertRedirect();
        
        $unapproved->refresh();
        $this->assertTrue($unapproved->is_approved);

        // Profile is now public
        $this->get('/en/authors/unapproved-author')->assertOk();
    }

    /**
     * Test admin rejects new author.
     */
    public function test_admin_rejects_new_author(): void
    {
        $unapproved = User::factory()->author()->create([
            'is_approved' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.moderation.authors.reject', ['locale' => 'en', 'user' => $unapproved->id]));

        $response->assertRedirect();
        
        $this->assertDatabaseMissing('users', ['id' => $unapproved->id]);
    }

    /**
     * Test editing an approved profile creates a pending draft.
     */
    public function test_editing_approved_profile_creates_pending_draft(): void
    {
        $response = $this->actingAs($this->author)->put('/en/admin/profile', [
            'name' => 'Jane Edited',
            'slug' => 'jane-edited',
            'job_title' => 'Senior Editor',
            'bio' => 'Brand new bio text.',
        ]);

        $response->assertRedirect();

        // Live profile remains unchanged
        $this->author->refresh();
        $this->assertSame('Jane Doe', $this->author->name);
        $this->assertSame('jane-doe', $this->author->slug);

        // Pending update is stored in pending_user_profiles
        $this->assertDatabaseHas('pending_user_profiles', [
            'user_id' => $this->author->id,
            'name' => 'Jane Edited',
            'slug' => 'jane-edited',
            'job_title' => 'Senior Editor',
            'bio' => 'Brand new bio text.',
        ]);
    }

    /**
     * Test admin approves profile draft.
     */
    public function test_admin_approves_profile_draft(): void
    {
        $pending = PendingUserProfile::create([
            'user_id' => $this->author->id,
            'name' => 'Jane Smith',
            'slug' => 'jane-smith',
            'job_title' => 'Director',
            'bio' => 'New Director Bio',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.moderation.profiles.approve', ['locale' => 'en', 'pendingUserProfile' => $pending->id]));

        $response->assertRedirect();

        // Changes applied to live user profile
        $this->author->refresh();
        $this->assertSame('Jane Smith', $this->author->name);
        $this->assertSame('jane-smith', $this->author->slug);
        $this->assertSame('Director', $this->author->job_title);
        $this->assertSame('New Director Bio', $this->author->bio);

        // Draft record is deleted
        $this->assertDatabaseMissing('pending_user_profiles', ['id' => $pending->id]);
    }

    /**
     * Test creating a new article creates a pending draft and remains hidden.
     */
    public function test_creating_new_article_creates_pending_draft_and_hides_it(): void
    {
        $response = $this->actingAs($this->author)->post('/en/admin/articles', [
            'slug' => 'new-article-slug',
            'category_id' => $this->category->id,
            'is_published' => true,
            'translations' => [
                'en' => [
                    'title' => 'New Article Title',
                    'description' => 'New Article Description',
                    'content' => 'New Article Content',
                    'faq' => null,
                ]
            ]
        ]);

        $response->assertRedirect();

        $article = Article::where('slug', 'new-article-slug')->first();
        $this->assertNotNull($article);
        $this->assertFalse($article->is_approved);

        // Pending translation is created
        $this->assertDatabaseHas('pending_article_translations', [
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'New Article Title',
            'content' => 'New Article Content',
        ]);

        // No live translation is created yet
        $this->assertDatabaseMissing('article_translations', [
            'article_id' => $article->id,
        ]);

        // Hidden from homepage listing
        $this->get('/en')->assertDontSee('New Article Title');

        // Author can view the preview
        $this->get('/en/php/new-article-slug')->assertStatus(200);

        // Page returns 404 to guests
        auth()->logout();
        $this->get('/en/php/new-article-slug')->assertNotFound();
    }

    /**
     * Test editing an approved article creates a pending draft.
     */
    public function test_editing_approved_article_creates_pending_draft(): void
    {
        $article = Article::create([
            'slug' => 'approved-article',
            'author_id' => $this->author->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'is_approved' => true,
        ]);
        $article->translations()->create([
            'locale' => 'en',
            'title' => 'Live Title',
            'description' => 'Live Description',
            'content' => 'Live Content',
        ]);

        $response = $this->actingAs($this->author)->put("/en/admin/articles/{$article->id}", [
            'slug' => 'approved-article',
            'category_id' => $this->category->id,
            'is_published' => true,
            'translations' => [
                'en' => [
                    'title' => 'Proposed Title Update',
                    'description' => 'Live Description',
                    'content' => 'Proposed Content Update',
                    'faq' => null,
                ]
            ]
        ]);

        $response->assertRedirect();

        // Live article translation is unchanged
        $liveTranslation = ArticleTranslation::where('article_id', $article->id)->first();
        $this->assertSame('Live Title', $liveTranslation->title);
        $this->assertSame('Live Content', $liveTranslation->content);

        // Pending draft is saved
        $this->assertDatabaseHas('pending_article_translations', [
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'Proposed Title Update',
            'content' => 'Proposed Content Update',
        ]);
    }

    /**
     * Test admin approves article draft.
     */
    public function test_admin_approves_article_draft(): void
    {
        $article = Article::create([
            'slug' => 'approved-article-2',
            'author_id' => $this->author->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'is_approved' => true,
        ]);
        $article->translations()->create([
            'locale' => 'en',
            'title' => 'Original Live Title',
            'content' => 'Original Live Content',
        ]);

        $pending = PendingArticleTranslation::create([
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'Approved Title Update',
            'content' => 'Approved Content Update',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.moderation.articles.approve', ['locale' => 'en', 'pendingArticleTranslation' => $pending->id]));

        $response->assertRedirect();

        // Live translation is updated
        $liveTranslation = ArticleTranslation::where('article_id', $article->id)->first();
        $this->assertSame('Approved Title Update', $liveTranslation->title);
        $this->assertSame('Approved Content Update', $liveTranslation->content);

        // Draft record is deleted
        $this->assertDatabaseMissing('pending_article_translations', ['id' => $pending->id]);
    }

    /**
     * Test users can submit content reports.
     */
    public function test_guest_can_submit_report_on_article(): void
    {
        $article = Article::create([
            'slug' => 'reportable-article',
            'author_id' => $this->author->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'is_approved' => true,
        ]);

        $response = $this->post('/en/reports', [
            'reportable_type' => 'article',
            'reportable_id' => $article->id,
            'reason' => 'This article contains copyright infringement.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('reports', [
            'reportable_type' => Article::class,
            'reportable_id' => $article->id,
            'reason' => 'This article contains copyright infringement.',
            'status' => 'pending',
        ]);
    }

    /**
     * Test admin actions a content report.
     */
    public function test_admin_actions_report_suspends_content(): void
    {
        $article = Article::create([
            'slug' => 'suspended-article',
            'author_id' => $this->author->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'is_approved' => true,
        ]);

        $report = Report::create([
            'reportable_type' => Article::class,
            'reportable_id' => $article->id,
            'reason' => 'Copyright violation',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.moderation.reports.action', ['locale' => 'en', 'report' => $report->id]));

        $response->assertRedirect();

        $article->refresh();
        $this->assertFalse($article->is_approved);
        $this->assertFalse($article->is_published);

        $report->refresh();
        $this->assertSame('resolved', $report->status);
    }

    /**
     * Test author can preview their own unapproved article (which is in review / draft).
     */
    public function test_author_can_preview_unapproved_draft_article(): void
    {
        $article = Article::create([
            'slug' => 'preview-draft-slug',
            'author_id' => $this->author->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'is_approved' => false,
        ]);
        $article->pendingTranslations()->create([
            'locale' => 'en',
            'title' => 'Draft Preview Title',
            'content' => '# Draft Preview Content',
        ]);

        // Non-logged user gets 404
        $this->get('/en/php/preview-draft-slug')->assertNotFound();

        // Different author gets 404
        $otherAuthor = User::factory()->author()->create(['is_approved' => true]);
        $this->actingAs($otherAuthor)->get('/en/php/preview-draft-slug')->assertNotFound();

        // The owner author gets 200 and sees the draft content
        $this->actingAs($this->author);
        $response = $this->get('/en/php/preview-draft-slug');
        $response->assertStatus(200);
        $response->assertSee('Draft Preview Title');
    }

    /**
     * Test author can preview unapproved article under architecture category (dynamic route matching).
     */
    public function test_author_can_preview_architecture_draft_article(): void
    {
        $architectureCategory = Category::create(['slug' => 'architecture']);
        
        $article = Article::create([
            'slug' => 'dynamic-architecture-slug',
            'author_id' => $this->author->id,
            'category_id' => $architectureCategory->id,
            'is_published' => true,
            'is_approved' => false,
        ]);
        $article->pendingTranslations()->create([
            'locale' => 'en',
            'title' => 'Architecture Title',
            'content' => '# Architecture Content',
        ]);

        // The owner author gets 200 and sees the draft content on the architecture prefix route
        $this->actingAs($this->author);
        $response = $this->get('/en/architecture/dynamic-architecture-slug');
        $response->assertStatus(200);
        $response->assertSee('Architecture Title');
        
        // Guest gets 404
        auth()->logout();
        $this->get('/en/architecture/dynamic-architecture-slug')->assertNotFound();
    }

    /**
     * Test correct review status badges are displayed on the article index page.
     */
    public function test_admin_and_author_see_correct_badges_on_articles_index(): void
    {
        $this->actingAs($this->author);

        // 1. Article awaiting initial approval
        $article1 = Article::create([
            'slug' => 'awaiting-approval',
            'author_id' => $this->author->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'is_approved' => false,
        ]);
        $article1->pendingTranslations()->create([
            'locale' => 'en',
            'title' => 'Initial Title',
            'content' => 'Content',
        ]);

        // 2. Article approved, but has translation updates awaiting approval
        $article2 = Article::create([
            'slug' => 'approved-with-updates',
            'author_id' => $this->author->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'is_approved' => true,
        ]);
        $article2->translations()->create([
            'locale' => 'en',
            'title' => 'Live Title',
            'content' => 'Live Content',
        ]);
        $article2->pendingTranslations()->create([
            'locale' => 'en',
            'title' => 'Pending Update Title',
            'content' => 'Pending Update Content',
        ]);

        $response = $this->get('/en/admin/articles');
        $response->assertStatus(200);
        $response->assertSee('In Review');
        $response->assertSee('Update in Review');
    }
}
