<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorCabinetTest extends TestCase
{
    use RefreshDatabase;

    private User $author1;
    private User $author2;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author1 = User::factory()->author()->create();
        $this->author2 = User::factory()->author()->create();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_author_can_only_see_their_own_articles_on_index(): void
    {
        $article1 = Article::factory()->create([
            'author_id' => $this->author1->id,
            'slug' => 'author1-article',
        ]);

        $article2 = Article::factory()->create([
            'author_id' => $this->author2->id,
            'slug' => 'author2-article',
        ]);

        $this->actingAs($this->author1);
        $response = $this->get('/en/admin/articles');
        $response->assertStatus(200);
        $response->assertSee('author1-article');
        $response->assertDontSee('author2-article');
    }

    public function test_author_cannot_edit_another_authors_article(): void
    {
        $article2 = Article::factory()->create([
            'author_id' => $this->author2->id,
            'slug' => 'author2-article',
        ]);

        $this->actingAs($this->author1);
        $response = $this->get("/en/admin/articles/{$article2->id}/edit");
        $response->assertStatus(403);
    }

    public function test_author_cannot_update_another_authors_article(): void
    {
        $article2 = Article::factory()->create([
            'author_id' => $this->author2->id,
            'slug' => 'author2-article',
        ]);

        $this->actingAs($this->author1);
        $response = $this->put("/en/admin/articles/{$article2->id}", [
            'slug' => 'updated-slug',
            'translations' => [
                'en' => [
                    'title' => 'Updated Title EN',
                    'content' => '# Updated content',
                ],
            ]
        ]);
        $response->assertStatus(403);
    }

    public function test_author_cannot_delete_another_authors_article(): void
    {
        $article2 = Article::factory()->create([
            'author_id' => $this->author2->id,
            'slug' => 'author2-article',
        ]);

        $this->actingAs($this->author1);
        $response = $this->delete("/en/admin/articles/{$article2->id}");
        $response->assertStatus(403);
        
        $this->assertDatabaseHas('articles', ['id' => $article2->id]);
    }

    public function test_super_admin_can_view_edit_update_delete_any_article(): void
    {
        $article1 = Article::factory()->create([
            'author_id' => $this->author1->id,
            'slug' => 'author1-article',
        ]);

        $article2 = Article::factory()->create([
            'author_id' => $this->author2->id,
            'slug' => 'author2-article',
        ]);

        $this->actingAs($this->admin);

        // Can index both
        $response = $this->get('/en/admin/articles');
        $response->assertStatus(200);
        $response->assertSee('author1-article');
        $response->assertSee('author2-article');

        // Can edit
        $response = $this->get("/en/admin/articles/{$article1->id}/edit");
        $response->assertStatus(200);

        // Can update
        $response = $this->put("/en/admin/articles/{$article1->id}", [
            'slug' => 'updated-slug-by-admin',
            'translations' => [
                'en' => [
                    'title' => 'Updated Title By Admin',
                    'content' => '# Updated content',
                ],
                'ru' => [
                    'title' => 'Updated Title By Admin RU',
                    'content' => '# Updated content RU',
                ],
                'ua' => [
                    'title' => 'Updated Title By Admin UA',
                    'content' => '# Updated content UA',
                ],
                'bg' => [
                    'title' => 'Updated Title By Admin BG',
                    'content' => '# Updated content BG',
                ],
            ]
        ]);
        $response->assertRedirect('/en/admin/articles');

        // Can delete
        $response = $this->delete("/en/admin/articles/{$article2->id}");
        $response->assertRedirect('/en/admin/articles');
        $this->assertDatabaseMissing('articles', ['id' => $article2->id]);
    }
}
