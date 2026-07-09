<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleAuthorMetaTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;
    private User $publicAuthor;
    private User $privateAuthor;
    private User $unapprovedAuthor;
    private User $blockedAuthor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['slug' => 'tools']);

        // Public approved author
        $this->publicAuthor = User::factory()->create([
            'role' => User::ROLE_AUTHOR,
            'slug' => 'public-author',
            'name' => 'John Public',
            'is_public' => true,
            'is_approved' => true,
            'is_blocked' => false,
        ]);

        // Private author
        $this->privateAuthor = User::factory()->create([
            'role' => User::ROLE_AUTHOR,
            'slug' => 'private-author',
            'name' => 'Jane Private',
            'is_public' => false,
            'is_approved' => true,
            'is_blocked' => false,
        ]);

        // Unapproved author
        $this->unapprovedAuthor = User::factory()->create([
            'role' => User::ROLE_AUTHOR,
            'slug' => 'unapproved-author',
            'name' => 'Jim Unapproved',
            'is_public' => true,
            'is_approved' => false,
            'is_blocked' => false,
        ]);

        // Blocked author
        $this->blockedAuthor = User::factory()->create([
            'role' => User::ROLE_AUTHOR,
            'slug' => 'blocked-author',
            'name' => 'Jack Blocked',
            'is_public' => true,
            'is_approved' => true,
            'is_blocked' => true,
        ]);
    }

    public function test_article_with_public_approved_author_renders_name_and_link(): void
    {
        $article = Article::create([
            'slug' => 'test-guide-1',
            'author_id' => $this->publicAuthor->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'is_approved' => true,
            'published_at' => now(),
        ]);
        $article->translations()->create([
            'locale' => 'en',
            'title' => 'Test Guide 1 Title',
            'content' => 'Test Guide 1 content',
        ]);

        $response = $this->get('/en/tools/test-guide-1');
        $response->assertOk();
        $response->assertSee('John Public');
        $response->assertSee(route('authors.show', ['locale' => 'en', 'slug' => 'public-author']));
    }

    public function test_article_with_private_author_renders_anonymous_without_link(): void
    {
        $article = Article::create([
            'slug' => 'test-guide-2',
            'author_id' => $this->privateAuthor->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'is_approved' => true,
            'published_at' => now(),
        ]);
        $article->translations()->create([
            'locale' => 'en',
            'title' => 'Test Guide 2 Title',
            'content' => 'Test Guide 2 content',
        ]);

        $response = $this->get('/en/tools/test-guide-2');
        $response->assertOk();
        $response->assertSee('Anonymous Author');
        $response->assertDontSee('Jane Private');
        $response->assertDontSee('/en/authors/private-author');
    }

    public function test_article_with_unapproved_author_renders_anonymous_without_link(): void
    {
        $article = Article::create([
            'slug' => 'test-guide-3',
            'author_id' => $this->unapprovedAuthor->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'is_approved' => true,
            'published_at' => now(),
        ]);
        $article->translations()->create([
            'locale' => 'en',
            'title' => 'Test Guide 3 Title',
            'content' => 'Test Guide 3 content',
        ]);

        // Unapproved author's article is visible to public if the article itself is approved
        $response = $this->get('/en/tools/test-guide-3');
        $response->assertOk();
        $response->assertSee('Anonymous Author');
        $response->assertDontSee('Jim Unapproved');
        $response->assertDontSee('/en/authors/unapproved-author');
    }

    public function test_article_with_blocked_author_is_inaccessible_to_guests(): void
    {
        $article = Article::create([
            'slug' => 'test-guide-4',
            'author_id' => $this->blockedAuthor->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'is_approved' => true,
            'published_at' => now(),
        ]);
        $article->translations()->create([
            'locale' => 'en',
            'title' => 'Test Guide 4 Title',
            'content' => 'Test Guide 4 content',
        ]);

        // Blocked author's articles return 404 to guests (as checked in controller)
        $response = $this->get('/en/tools/test-guide-4');
        $response->assertNotFound();
    }
}
