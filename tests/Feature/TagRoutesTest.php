<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagRoutesTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private Tag $activeTag;
    private Tag $emptyTag;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create(['role' => User::ROLE_AUTHOR]);
        $this->category = Category::create(['slug' => 'php']);

        $this->activeTag = Tag::create(['slug' => 'oop']);
        $this->activeTag->translations()->create(['locale' => 'en', 'name' => 'OOP principles']);

        $this->emptyTag = Tag::create(['slug' => 'unused']);
        $this->emptyTag->translations()->create(['locale' => 'en', 'name' => 'Unused Tag']);

        // Create published article for activeTag
        $article = Article::create([
            'slug' => 'oop-guide',
            'author_id' => $this->author->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'published_at' => now(),
        ]);
        $article->translations()->create([
            'locale' => 'en',
            'title' => 'OOP Guide Title',
            'content' => 'OOP guide content',
        ]);
        $article->tags()->attach($this->activeTag->id);
    }

    public function test_tags_index_renders_successfully(): void
    {
        $response = $this->get('/en/tags');
        $response->assertOk();
        $response->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_tags_index_lists_active_tags(): void
    {
        $response = $this->get('/en/tags');
        $response->assertOk();
        $response->assertSee('OOP principles');
        $response->assertSee('1'); // Article count count badge
    }

    public function test_tags_index_excludes_empty_tags(): void
    {
        $response = $this->get('/en/tags');
        $response->assertOk();
        $response->assertDontSee('Unused Tag');
    }

    public function test_tags_show_renders_guides(): void
    {
        $response = $this->get('/en/tags/oop');
        $response->assertOk();
        $response->assertSee('OOP principles');
        $response->assertSee('OOP Guide Title');
        $response->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_tags_show_hides_draft_articles(): void
    {
        $draft = Article::create([
            'slug' => 'draft-oop',
            'author_id' => $this->author->id,
            'category_id' => $this->category->id,
            'is_published' => false,
        ]);
        $draft->translations()->create([
            'locale' => 'en',
            'title' => 'Hidden Draft Title',
            'content' => 'hidden',
        ]);
        $draft->tags()->attach($this->activeTag->id);

        $response = $this->get('/en/tags/oop');
        $response->assertOk();
        $response->assertSee('OOP Guide Title');
        $response->assertDontSee('Hidden Draft Title');
    }

    public function test_tags_show_returns_404_for_unknown_slug(): void
    {
        $this->get('/en/tags/missing-tag')->assertNotFound();
    }

    public function test_guide_details_page_renders_associated_tag_badges(): void
    {
        $article = Article::firstOrCreate([
            'slug' => '8.4',
        ], [
            'author_id' => $this->author->id,
            'category_id' => $this->category->id,
            'is_published' => true,
        ]);
        $article->tags()->syncWithoutDetaching([$this->activeTag->id]);

        $response = $this->get('/en/php/8.4');
        $response->assertOk();
        $response->assertSee('OOP principles');
        $response->assertSee(route('tags.show', ['slug' => 'oop', 'locale' => 'en']));
    }
}
