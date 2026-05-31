<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAndFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic user, category, tag, and articles
        $author = User::factory()->admin()->create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
        ]);

        $phpCategory = Category::create(['slug' => 'php']);
        $phpCategory->translations()->create(['locale' => 'en', 'name' => 'PHP Guides']);

        $archCategory = Category::create(['slug' => 'architecture']);
        $archCategory->translations()->create(['locale' => 'en', 'name' => 'Architecture']);

        $oopTag = Tag::create(['slug' => 'oop']);
        $oopTag->translations()->create(['locale' => 'en', 'name' => 'OOP']);

        // Create 2 articles
        $article1 = Article::create([
            'slug' => '8.4',
            'author_id' => $author->id,
            'category_id' => $phpCategory->id,
            'is_published' => true,
            'published_at' => now()->subDays(2),
        ]);
        $article1->translations()->create([
            'locale' => 'en',
            'title' => 'PHP 8.4 New Features',
            'description' => 'A guide to PHP 8.4',
            'content' => 'Full details on property hooks and asymmetric visibility.',
        ]);
        $article1->tags()->sync([$oopTag->id]);

        $article2 = Article::create([
            'slug' => 'indexes',
            'author_id' => $author->id,
            'category_id' => $archCategory->id,
            'is_published' => true,
            'published_at' => now(),
        ]);
        $article2->translations()->create([
            'locale' => 'en',
            'title' => 'Database Indexes Deep Dive',
            'description' => 'Understanding database indexes.',
            'content' => 'B-Tree leaf node structures.',
        ]);
    }

    public function test_homepage_lists_all_published_articles(): void
    {
        $response = $this->get('/en');

        $response->assertStatus(200);
        $response->assertSee('PHP 8.4 New Features');
        $response->assertSee('Database Indexes Deep Dive');
    }

    public function test_filtering_by_category(): void
    {
        // Query Category PHP
        $response = $this->get('/en?category=php');
        $response->assertStatus(200);
        $response->assertSee('PHP 8.4 New Features');
        $response->assertDontSee('Database Indexes Deep Dive');

        // Query Category Architecture
        $response = $this->get('/en?category=architecture');
        $response->assertStatus(200);
        $response->assertSee('Database Indexes Deep Dive');
        $response->assertDontSee('PHP 8.4 New Features');
    }

    public function test_searching_by_query_string(): void
    {
        // Search "hooks"
        $response = $this->get('/en?q=hooks');
        $response->assertStatus(200);
        $response->assertSee('PHP 8.4 New Features');
        $response->assertDontSee('Database Indexes Deep Dive');

        // Search "Indexes"
        $response = $this->get('/en?q=Indexes');
        $response->assertStatus(200);
        $response->assertSee('Database Indexes Deep Dive');
        $response->assertDontSee('PHP 8.4 New Features');
    }

    public function test_filtering_by_tag(): void
    {
        $response = $this->get('/en?tag=oop');
        $response->assertStatus(200);
        $response->assertSee('PHP 8.4 New Features');
        $response->assertDontSee('Database Indexes Deep Dive');
    }

    public function test_sorting_by_date(): void
    {
        // Latest (default): Indexes (now) then PHP 8.4 (2 days ago)
        $response = $this->get('/en?sort=latest');
        $response->assertStatus(200);
        $html = $response->getContent();
        $idxPos = strpos($html, 'Database Indexes Deep Dive');
        $phpPos = strpos($html, 'PHP 8.4 New Features');
        $this->assertTrue($idxPos < $phpPos);

        // Oldest: PHP 8.4 then Indexes
        $response = $this->get('/en?sort=oldest');
        $response->assertStatus(200);
        $html = $response->getContent();
        $idxPos = strpos($html, 'Database Indexes Deep Dive');
        $phpPos = strpos($html, 'PHP 8.4 New Features');
        $this->assertTrue($phpPos < $idxPos);
    }
}
