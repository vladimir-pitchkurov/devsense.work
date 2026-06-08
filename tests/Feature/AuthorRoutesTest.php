<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP tests for public author profile routes.
 *
 * Covers:
 *  - /en/authors  (index)
 *  - /en/authors/{slug} (show)
 *  - Person JSON-LD on the profile page
 *  - 404 for non-existent slugs
 */
class AuthorRoutesTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create([
            'name'        => 'Jane Doe',
            'email'       => 'jane@example.com',
            'role'        => User::ROLE_AUTHOR,
            'slug'        => 'jane-doe',
            'job_title'   => 'Senior PHP Developer',
            'bio'         => 'Writes about PHP and clean code.',
            'github_url'  => 'https://github.com/janedoe',
            'linkedin_url'=> null,
            'twitter_url' => null,
            'website_url' => null,
        ]);
    }

    public function test_authors_index_renders_successfully(): void
    {
        $this->get('/en/authors')->assertOk();
    }

    public function test_authors_index_lists_author_names(): void
    {
        $response = $this->get('/en/authors');

        $response->assertOk();
        $response->assertSee('Jane Doe');
    }

    public function test_authors_show_renders_profile(): void
    {
        $response = $this->get('/en/authors/jane-doe');

        $response->assertOk();
        $response->assertSee('Jane Doe');
        $response->assertSee('Senior PHP Developer');
    }

    public function test_authors_show_includes_person_json_ld(): void
    {
        $response = $this->get('/en/authors/jane-doe');

        $response->assertOk();
        $response->assertSee('"@type":"Person"', false);
        $response->assertSee('Jane Doe', false);
    }

    public function test_authors_show_displays_bio(): void
    {
        $response = $this->get('/en/authors/jane-doe');

        $response->assertOk();
        $response->assertSee('Writes about PHP and clean code.');
    }

    public function test_authors_show_shows_github_link(): void
    {
        $response = $this->get('/en/authors/jane-doe');

        $response->assertOk();
        $response->assertSee('https://github.com/janedoe');
    }

    public function test_authors_show_returns_404_for_unknown_slug(): void
    {
        $this->get('/en/authors/nobody-here')->assertNotFound();
    }

    public function test_public_approved_readers_are_included_in_candidates_index(): void
    {
        $reader = User::factory()->create([
            'name'  => 'Plain Reader',
            'email' => 'reader@example.com',
            'role'  => User::ROLE_READER,
            'slug'  => 'plain-reader',
            'is_public' => true,
            'is_approved' => true,
        ]);

        // The index should show public approved readers
        $this->get('/en/authors')->assertSee('Plain Reader');

        // Their slug should render successfully
        $this->get('/en/authors/plain-reader')->assertOk();
    }

    public function test_author_avatar_url_returns_ui_avatars_fallback_when_no_avatar(): void
    {
        $this->assertStringContainsString('ui-avatars.com', $this->author->avatarUrl());
    }

    public function test_author_avatar_url_returns_full_url_for_local_path(): void
    {
        $this->author->avatar_path = 'uploads/avatars/jane.jpg';
        $expected = rtrim(config('app.url'), '/').'/uploads/avatars/jane.jpg';
        $this->assertSame($expected, $this->author->avatarUrl());
    }

    public function test_author_social_links_returns_only_non_empty_links(): void
    {
        $links = $this->author->socialLinks();

        $this->assertArrayHasKey('github', $links);
        $this->assertArrayNotHasKey('linkedin', $links);
        $this->assertArrayNotHasKey('twitter', $links);
    }

    public function test_author_slug_attribute_falls_back_to_name_slug_when_null(): void
    {
        $user = User::factory()->make(['name' => 'John Smith', 'slug' => null]);
        $this->assertSame('john-smith', $user->slug);
    }

    public function test_authors_show_lists_published_articles_and_hides_drafts(): void
    {
        $category = Category::create(['slug' => 'php']);

        // 1. Create a published article
        $pubArticle = Article::create([
            'slug' => 'pub-article',
            'author_id' => $this->author->id,
            'category_id' => $category->id,
            'is_published' => true,
            'published_at' => now(),
            'is_approved' => true,
        ]);
        $pubArticle->translations()->create([
            'locale' => 'en',
            'title' => 'Published Article Title',
            'content' => 'Content here',
        ]);

        // 2. Create a draft article
        $draftArticle = Article::create([
            'slug' => 'draft-article',
            'author_id' => $this->author->id,
            'category_id' => $category->id,
            'is_published' => false,
            'published_at' => null,
        ]);
        $draftArticle->translations()->create([
            'locale' => 'en',
            'title' => 'Draft Article Title',
            'content' => 'Content here',
        ]);

        $response = $this->get('/en/authors/jane-doe');
        $response->assertOk();
        $response->assertSee('Published Article Title');
        $response->assertDontSee('Draft Article Title');
    }

    public function test_private_author_excluded_from_index(): void
    {
        $privateAuthor = User::factory()->author()->create([
            'name' => 'Secret Author',
            'slug' => 'secret-author',
            'is_public' => false,
        ]);

        // Secret author shouldn't show up in index for guests
        $response = $this->get('/en/authors');
        $response->assertOk();
        $response->assertDontSee('Secret Author');
    }

    public function test_private_author_profile_returns_404_for_guests(): void
    {
        $privateAuthor = User::factory()->author()->create([
            'name' => 'Secret Author',
            'slug' => 'secret-author',
            'is_public' => false,
        ]);

        $this->get('/en/authors/secret-author')->assertNotFound();
    }

    public function test_private_author_profile_viewable_by_owner(): void
    {
        $privateAuthor = User::factory()->author()->create([
            'name' => 'Secret Author',
            'slug' => 'secret-author',
            'is_public' => false,
        ]);

        $this->actingAs($privateAuthor);
        $this->get('/en/authors/secret-author')->assertOk();
    }

    public function test_private_author_profile_viewable_by_admin(): void
    {
        $privateAuthor = User::factory()->author()->create([
            'name' => 'Secret Author',
            'slug' => 'secret-author',
            'is_public' => false,
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        $this->get('/en/authors/secret-author')->assertOk();
    }
}
