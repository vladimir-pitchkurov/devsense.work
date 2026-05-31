<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create(['role' => User::ROLE_AUTHOR]);
        $this->reader = User::factory()->create(['role' => User::ROLE_READER]);
    }

    /*
    |--------------------------------------------------------------------------
    | Category CRUD Tests
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_access_categories(): void
    {
        $this->get('/en/admin/categories')->assertRedirect('/login');
    }

    public function test_reader_cannot_access_categories(): void
    {
        $this->actingAs($this->reader)->get('/en/admin/categories')->assertStatus(403);
    }

    public function test_author_can_view_categories_list(): void
    {
        $category = Category::create(['slug' => 'test-cat']);
        $category->translations()->create(['locale' => 'en', 'name' => 'Test Cat Name']);

        $response = $this->actingAs($this->author)->get('/en/admin/categories');
        $response->assertOk();
        $response->assertSee('test-cat');
        $response->assertSee('Test Cat Name');
    }

    public function test_author_can_create_category(): void
    {
        $response = $this->actingAs($this->author)->post('/en/admin/categories', [
            'slug' => 'new-cat-slug',
            'translations' => [
                'en' => ['name' => 'English Name'],
                'ru' => ['name' => 'Russian Name'],
                'ua' => ['name' => 'Ukrainian Name'],
                'bg' => ['name' => 'Bulgarian Name'],
            ],
        ]);

        $response->assertRedirect('/en/admin/categories');

        $this->assertDatabaseHas('categories', ['slug' => 'new-cat-slug']);
        $this->assertDatabaseHas('category_translations', [
            'locale' => 'en',
            'name' => 'English Name',
        ]);
    }

    public function test_author_can_update_category(): void
    {
        $category = Category::create(['slug' => 'old-cat']);
        $category->translations()->create(['locale' => 'en', 'name' => 'Old Name']);

        $response = $this->actingAs($this->author)->put("/en/admin/categories/{$category->id}", [
            'slug' => 'updated-cat',
            'translations' => [
                'en' => ['name' => 'Updated Name'],
                'ru' => ['name' => 'Ru Name'],
                'ua' => ['name' => 'Ua Name'],
                'bg' => ['name' => 'Bg Name'],
            ],
        ]);

        $response->assertRedirect('/en/admin/categories');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => 'updated-cat',
        ]);
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->id,
            'locale' => 'en',
            'name' => 'Updated Name',
        ]);
    }

    public function test_cannot_delete_category_with_articles(): void
    {
        $category = Category::create(['slug' => 'busy-cat']);
        $category->translations()->create(['locale' => 'en', 'name' => 'Busy Category']);

        $article = Article::create([
            'slug' => 'some-article',
            'author_id' => $this->author->id,
            'category_id' => $category->id,
            'is_published' => true,
        ]);

        $response = $this->actingAs($this->author)->from('/en/admin/categories')->delete("/en/admin/categories/{$category->id}");
        $response->assertRedirect('/en/admin/categories');
        $response->assertSessionHasErrors(['error']);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_author_can_delete_empty_category(): void
    {
        $category = Category::create(['slug' => 'empty-cat']);
        $category->translations()->create(['locale' => 'en', 'name' => 'Empty Category']);

        $response = $this->actingAs($this->author)->delete("/en/admin/categories/{$category->id}");
        $response->assertRedirect('/en/admin/categories');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tag CRUD Tests
    |--------------------------------------------------------------------------
    */

    public function test_guest_cannot_access_tags(): void
    {
        $this->get('/en/admin/tags')->assertRedirect('/login');
    }

    public function test_reader_cannot_access_tags(): void
    {
        $this->actingAs($this->reader)->get('/en/admin/tags')->assertStatus(403);
    }

    public function test_author_can_view_tags_list(): void
    {
        $tag = Tag::create(['slug' => 'test-tag']);
        $tag->translations()->create(['locale' => 'en', 'name' => 'Test Tag Name']);

        $response = $this->actingAs($this->author)->get('/en/admin/tags');
        $response->assertOk();
        $response->assertSee('test-tag');
        $response->assertSee('Test Tag Name');
    }

    public function test_author_can_create_tag(): void
    {
        $response = $this->actingAs($this->author)->post('/en/admin/tags', [
            'slug' => 'new-tag-slug',
            'translations' => [
                'en' => ['name' => 'English Tag'],
                'ru' => ['name' => 'Russian Tag'],
                'ua' => ['name' => 'Ukrainian Tag'],
                'bg' => ['name' => 'Bulgarian Tag'],
            ],
        ]);

        $response->assertRedirect('/en/admin/tags');

        $this->assertDatabaseHas('tags', ['slug' => 'new-tag-slug']);
        $this->assertDatabaseHas('tag_translations', [
            'locale' => 'en',
            'name' => 'English Tag',
        ]);
    }

    public function test_author_can_update_tag(): void
    {
        $tag = Tag::create(['slug' => 'old-tag']);
        $tag->translations()->create(['locale' => 'en', 'name' => 'Old Tag Name']);

        $response = $this->actingAs($this->author)->put("/en/admin/tags/{$tag->id}", [
            'slug' => 'updated-tag',
            'translations' => [
                'en' => ['name' => 'Updated Tag Name'],
                'ru' => ['name' => 'Ru Tag'],
                'ua' => ['name' => 'Ua Tag'],
                'bg' => ['name' => 'Bg Tag'],
            ],
        ]);

        $response->assertRedirect('/en/admin/tags');

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'slug' => 'updated-tag',
        ]);
    }

    public function test_author_can_delete_tag(): void
    {
        $tag = Tag::create(['slug' => 'delete-me-tag']);
        $tag->translations()->create(['locale' => 'en', 'name' => 'Delete Me']);

        $response = $this->actingAs($this->author)->delete("/en/admin/tags/{$tag->id}");
        $response->assertRedirect('/en/admin/tags');

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }
}
