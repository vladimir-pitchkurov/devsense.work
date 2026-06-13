<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFiltersAndSortingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $author1;
    private User $author2;
    private Category $category1;
    private Category $category2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@devsense.work',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_approved' => true,
        ]);

        $this->author1 = User::factory()->create([
            'name' => 'Author Alpha',
            'email' => 'alpha@devsense.work',
            'role' => User::ROLE_AUTHOR,
            'is_approved' => true,
            'is_blocked' => false,
            'points' => 500,
        ]);

        $this->author2 = User::factory()->create([
            'name' => 'Author Beta',
            'email' => 'beta@devsense.work',
            'role' => User::ROLE_AUTHOR,
            'is_approved' => false,
            'is_blocked' => true,
            'points' => 1000,
        ]);

        $this->category1 = Category::factory()->create(['slug' => 'php']);
        $this->category2 = Category::factory()->create(['slug' => 'tools']);
    }

    public function test_admin_can_filter_articles_by_search(): void
    {
        $this->actingAs($this->admin);

        $article1 = Article::factory()->create([
            'author_id' => $this->author1->id,
            'slug' => 'php-basics',
        ]);
        $article1->translations()->create([
            'locale' => 'en',
            'title' => 'Learn PHP Basics',
            'description' => 'PHP basics guide',
            'content' => 'Content here',
        ]);

        $article2 = Article::factory()->create([
            'author_id' => $this->author2->id,
            'slug' => 'laravel-sail',
        ]);
        $article2->translations()->create([
            'locale' => 'en',
            'title' => 'Docker Sail Guide',
            'description' => 'Docker sail guide',
            'content' => 'Content here',
        ]);

        // Search for 'PHP'
        $response = $this->get('/en/admin/articles?search=PHP');
        $response->assertOk();
        $response->assertSee('php-basics');
        $response->assertDontSee('laravel-sail');

        // Search for 'Docker'
        $response = $this->get('/en/admin/articles?search=Docker');
        $response->assertOk();
        $response->assertSee('laravel-sail');
        $response->assertDontSee('php-basics');
    }

    public function test_admin_can_filter_articles_by_category(): void
    {
        $this->actingAs($this->admin);

        $article1 = Article::factory()->create(['author_id' => $this->author1->id, 'slug' => 'art-1']);
        $article1->categories()->attach($this->category1);

        $article2 = Article::factory()->create(['author_id' => $this->author2->id, 'slug' => 'art-2']);
        $article2->categories()->attach($this->category2);

        $response = $this->get('/en/admin/articles?category=' . $this->category1->id);
        $response->assertOk();
        $response->assertSee('art-1');
        $response->assertDontSee('art-2');
    }

    public function test_admin_can_filter_articles_by_status(): void
    {
        $this->actingAs($this->admin);

        // Awaiting approval
        $article1 = Article::factory()->create([
            'author_id' => $this->author1->id,
            'slug' => 'in-review-article',
            'is_approved' => false,
        ]);

        // Published
        $article2 = Article::factory()->create([
            'author_id' => $this->author1->id,
            'slug' => 'published-article',
            'is_approved' => true,
            'is_published' => true,
        ]);

        // Draft
        $article3 = Article::factory()->create([
            'author_id' => $this->author1->id,
            'slug' => 'draft-article',
            'is_approved' => true,
            'is_published' => false,
        ]);

        // In review
        $response = $this->get('/en/admin/articles?status=pending_approval');
        $response->assertOk();
        $response->assertSee('in-review-article');
        $response->assertDontSee('published-article');
        $response->assertDontSee('draft-article');

        // Published
        $response = $this->get('/en/admin/articles?status=published');
        $response->assertOk();
        $response->assertSee('published-article');
        $response->assertDontSee('in-review-article');
        $response->assertDontSee('draft-article');
    }

    public function test_admin_can_sort_articles(): void
    {
        $this->actingAs($this->admin);

        $article1 = Article::factory()->create([
            'slug' => 'alpha-slug',
            'created_at' => now()->subDays(2),
        ]);
        $article2 = Article::factory()->create([
            'slug' => 'beta-slug',
            'created_at' => now()->subDay(),
        ]);

        // Sort by slug asc
        $response = $this->get('/en/admin/articles?sort_by=slug_asc');
        $response->assertOk();
        // Check order of occurrence in response HTML: alpha-slug should appear before beta-slug
        $this->assertTrue(strpos($response->getContent(), 'alpha-slug') < strpos($response->getContent(), 'beta-slug'));

        // Sort by slug desc
        $response = $this->get('/en/admin/articles?sort_by=slug_desc');
        $response->assertOk();
        $this->assertTrue(strpos($response->getContent(), 'beta-slug') < strpos($response->getContent(), 'alpha-slug'));
    }

    public function test_admin_can_filter_users_by_role_and_search(): void
    {
        $this->actingAs($this->admin);

        // Filter by role = author
        $response = $this->get('/en/admin/users?role=' . User::ROLE_AUTHOR);
        $response->assertOk();
        $response->assertSee('Author Alpha');
        $response->assertSee('Author Beta');
        $response->assertDontSee('Admin User');

        // Search for 'Alpha'
        $response = $this->get('/en/admin/users?search=Alpha');
        $response->assertOk();
        $response->assertSee('Author Alpha');
        $response->assertDontSee('Author Beta');
    }

    public function test_admin_can_filter_users_by_approved_and_blocked_statuses(): void
    {
        $this->actingAs($this->admin);

        // Filter approved
        $response = $this->get('/en/admin/users?approved=approved');
        $response->assertOk();
        $response->assertSee('Author Alpha');
        $response->assertDontSee('Author Beta');

        // Filter suspended
        $response = $this->get('/en/admin/users?status=suspended');
        $response->assertOk();
        $response->assertSee('Author Beta');
        $response->assertDontSee('Author Alpha');
    }

    public function test_admin_can_sort_users_by_xp_points(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/en/admin/users?sort_by=xp_desc');
        $response->assertOk();

        // Author Beta (1000 points) should appear before Author Alpha (500 points)
        $this->assertTrue(strpos($response->getContent(), 'Author Beta') < strpos($response->getContent(), 'Author Alpha'));
    }
}
