<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminArticlesCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private Category $category;
    private Tag $tag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->author()->create();
        $this->category = Category::factory()->create(['slug' => 'php']);
        $this->tag = Tag::factory()->create(['slug' => 'laravel']);
    }

    public function test_guest_cannot_access_admin_articles(): void
    {
        $this->get('/en/admin/articles')->assertRedirect('/login');
        $this->get('/en/admin/articles/create')->assertRedirect('/login');
    }

    public function test_reader_cannot_access_admin_articles(): void
    {
        $reader = User::factory()->create(['role' => User::ROLE_READER]);

        $this->actingAs($reader);

        $this->get('/en/admin/articles')->assertStatus(403);
    }

    public function test_author_can_view_articles_list(): void
    {
        $this->actingAs($this->author);

        $article = Article::factory()->create([
            'author_id' => $this->author->id,
            'slug' => 'some-article',
        ]);

        $response = $this->get('/en/admin/articles');
        $response->assertStatus(200);
        $response->assertSee('some-article');
    }

    public function test_author_can_create_article_with_translations(): void
    {
        $this->actingAs($this->author);

        $response = $this->post('/en/admin/articles', [
            'slug' => 'new-cool-guide',
            'category_id' => $this->category->id,
            'is_published' => 1,
            'tags' => [$this->tag->id],
            'translations' => [
                'en' => [
                    'title' => 'New Cool Guide EN',
                    'description' => 'Cool description EN',
                    'content' => '# Cool guide content',
                    'faq' => json_encode([['question' => 'Q?', 'answer' => 'A']])
                ],
                'ru' => [
                    'title' => 'Новый классный гайд RU',
                    'description' => 'Классное описание RU',
                    'content' => '# Контент гайда',
                    'faq' => ''
                ],
                'ua' => [
                    'title' => 'Новий класний гайд UA',
                    'description' => '',
                    'content' => '# Контент гайда UA',
                    'faq' => ''
                ],
                'bg' => [
                    'title' => 'Нов як гайд BG',
                    'description' => '',
                    'content' => '# Контент гайда BG',
                    'faq' => ''
                ]
            ]
        ]);

        $response->assertRedirect('/en/admin/articles');
        
        $article = Article::where('slug', 'new-cool-guide')->first();
        $this->assertNotNull($article);
        $this->assertSame($this->category->id, $article->category_id);
        $this->assertTrue($article->is_published);
        
        // Assert translations were created
        $this->assertSame('New Cool Guide EN', $article->translate('en')->title);
        $this->assertSame('Новый классный гайд RU', $article->translate('ru')->title);
        $this->assertSame('Q?', $article->translate('en')->faq[0]['question']);
        
        // Assert tag relation
        $this->assertTrue($article->tags->contains($this->tag->id));
    }

    public function test_author_can_update_article_and_translations(): void
    {
        $this->actingAs($this->author);

        $article = Article::factory()->create([
            'author_id' => $this->author->id,
            'slug' => 'old-slug',
            'category_id' => $this->category->id,
        ]);

        $response = $this->put("/en/admin/articles/{$article->id}", [
            'slug' => 'updated-slug',
            'category_id' => $this->category->id,
            'is_published' => 0,
            'translations' => [
                'en' => [
                    'title' => 'Updated Title EN',
                    'content' => '# Updated content',
                ],
                'ru' => [
                    'title' => 'Обновленный заголовок RU',
                    'content' => '# Обновленный контент',
                ],
                'ua' => [
                    'title' => 'Оновлений заголовок UA',
                    'content' => '# Оновлений контент UA',
                ],
                'bg' => [
                    'title' => 'Обновен заголовок BG',
                    'content' => '# Обновен контент BG',
                ]
            ]
        ]);

        $response->assertRedirect('/en/admin/articles');

        $article->refresh();
        $this->assertSame('updated-slug', $article->slug);
        $this->assertFalse($article->is_published);
        $this->assertSame('Updated Title EN', $article->translate('en')->title);
        $this->assertSame('Обновленный заголовок RU', $article->translate('ru')->title);
    }

    public function test_author_can_delete_article(): void
    {
        $this->actingAs($this->author);

        $article = Article::factory()->create([
            'author_id' => $this->author->id,
        ]);

        $this->assertDatabaseHas('articles', ['id' => $article->id]);

        $response = $this->delete("/en/admin/articles/{$article->id}");
        $response->assertRedirect('/en/admin/articles');

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }
}
