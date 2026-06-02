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
        
        // Assert translation drafts were created in pending table
        $this->assertDatabaseHas('pending_article_translations', [
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'New Cool Guide EN',
        ]);
        $this->assertDatabaseHas('pending_article_translations', [
            'article_id' => $article->id,
            'locale' => 'ru',
            'title' => 'Новый классный гайд RU',
        ]);
        
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

        // Add direct live translation first so we can see it remains unchanged
        $article->translations()->create([
            'locale' => 'en',
            'title' => 'Old Live Title EN',
            'content' => '# Old content',
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
        
        // Live translation remains unchanged
        $this->assertSame('Old Live Title EN', $article->translate('en')->title);

        // Pending translation contains updates
        $this->assertDatabaseHas('pending_article_translations', [
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'Updated Title EN',
        ]);
        $this->assertDatabaseHas('pending_article_translations', [
            'article_id' => $article->id,
            'locale' => 'ru',
            'title' => 'Обновленный заголовок RU',
        ]);
    }

    public function test_admin_can_create_article_with_translations_directly(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->post('/en/admin/articles', [
            'slug' => 'admin-guide',
            'category_id' => $this->category->id,
            'is_published' => 1,
            'tags' => [$this->tag->id],
            'translations' => [
                'en' => [
                    'title' => 'Admin Guide EN',
                    'description' => 'Cool description EN',
                    'content' => '# Cool guide content',
                    'faq' => json_encode([['question' => 'Q?', 'answer' => 'A']])
                ],
                'ru' => [
                    'title' => 'Админ Гайд RU',
                    'description' => 'Классное описание RU',
                    'content' => '# Контент гайда',
                    'faq' => ''
                ],
                'ua' => [
                    'title' => 'Адмін Гайд UA',
                    'description' => '',
                    'content' => '# Контент UA',
                    'faq' => ''
                ],
                'bg' => [
                    'title' => 'Админ Гайд BG',
                    'description' => '',
                    'content' => '# Контент BG',
                    'faq' => ''
                ]
            ]
        ]);

        $response->assertRedirect('/en/admin/articles');
        
        $article = Article::where('slug', 'admin-guide')->first();
        $this->assertNotNull($article);
        
        // Assert live translations were created directly
        $this->assertSame('Admin Guide EN', $article->translate('en')->title);
        $this->assertSame('Админ Гайд RU', $article->translate('ru')->title);
        
        // Assert no pending translations are created
        $this->assertDatabaseMissing('pending_article_translations', [
            'article_id' => $article->id,
        ]);
    }

    public function test_admin_can_update_article_and_translations_directly(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $article = Article::factory()->create([
            'author_id' => $admin->id,
            'slug' => 'old-admin-slug',
            'category_id' => $this->category->id,
        ]);
        $article->translations()->create([
            'locale' => 'en',
            'title' => 'Old EN',
            'content' => 'Old content',
        ]);

        $response = $this->put("/en/admin/articles/{$article->id}", [
            'slug' => 'updated-admin-slug',
            'category_id' => $this->category->id,
            'is_published' => 1,
            'translations' => [
                'en' => [
                    'title' => 'New EN',
                    'content' => 'New content',
                ],
                'ru' => [
                    'title' => 'Новый RU',
                    'content' => 'Новый контент',
                ],
                'ua' => [
                    'title' => 'Новий UA',
                    'content' => 'Новий контент',
                ],
                'bg' => [
                    'title' => 'Нов BG',
                    'content' => 'Нов контент',
                ]
            ]
        ]);

        $response->assertRedirect('/en/admin/articles');

        $article->refresh();
        $this->assertSame('updated-admin-slug', $article->slug);
        
        // Live translation is updated directly
        $this->assertSame('New EN', $article->translate('en')->title);
        
        // Assert no pending translations are created
        $this->assertDatabaseMissing('pending_article_translations', [
            'article_id' => $article->id,
        ]);
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
 
     public function test_author_can_download_article_template(): void
     {
         $this->actingAs($this->author);
 
         $response = $this->get('/en/admin/articles/template');
         $response->assertStatus(200);
         $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
         $response->assertHeader('Content-Disposition', 'attachment; filename="devsense-article-template.md"');
         $response->assertSee('title: "How to Use Property Hooks in PHP 8.4"', false);
         $response->assertSee('faq:', false);
         $response->assertSee('## Table of Contents', false);
     }

     public function test_article_validation_requires_at_least_one_translation(): void
     {
         $this->actingAs($this->author);

         $response = $this->post('/en/admin/articles', [
             'slug' => 'invalid-no-translations',
             'category_id' => $this->category->id,
             'translations' => [
                 'en' => ['title' => '', 'content' => ''],
                 'ru' => ['title' => '', 'content' => ''],
                 'ua' => ['title' => '', 'content' => ''],
                 'bg' => ['title' => '', 'content' => ''],
             ]
         ]);

         $response->assertSessionHasErrors(['translations']);
     }

     public function test_article_validation_rejects_partially_filled_translation(): void
     {
         $this->actingAs($this->author);

         $response = $this->post('/en/admin/articles', [
             'slug' => 'invalid-partial-translation',
             'category_id' => $this->category->id,
             'translations' => [
                 'en' => ['title' => 'Only Title', 'content' => ''],
                 'ru' => ['title' => '', 'content' => ''],
                 'ua' => ['title' => '', 'content' => ''],
                 'bg' => ['title' => '', 'content' => ''],
             ]
         ]);

         $response->assertSessionHasErrors(['translations.en.content']);
     }

     public function test_article_creation_ignores_empty_translations(): void
     {
         $this->actingAs($this->author);

         $response = $this->post('/en/admin/articles', [
             'slug' => 'single-translation-article',
             'category_id' => $this->category->id,
             'translations' => [
                 'en' => ['title' => 'Title EN', 'content' => 'Content EN'],
                 'ru' => ['title' => '', 'content' => ''],
                 'ua' => ['title' => '', 'content' => ''],
                 'bg' => ['title' => '', 'content' => ''],
             ]
         ]);

         $response->assertRedirect('/en/admin/articles');
         
         $article = Article::where('slug', 'single-translation-article')->first();
         $this->assertNotNull($article);
         
         $this->assertDatabaseHas('pending_article_translations', [
             'article_id' => $article->id,
             'locale' => 'en',
             'title' => 'Title EN',
         ]);
         $this->assertDatabaseMissing('pending_article_translations', [
             'article_id' => $article->id,
             'locale' => 'ru',
         ]);
     }

     public function test_article_update_deletes_cleared_translations(): void
     {
         $admin = User::factory()->admin()->create();
         $this->actingAs($admin);

         $article = Article::factory()->create([
             'author_id' => $admin->id,
             'slug' => 'test-clear-slug',
             'category_id' => $this->category->id,
         ]);
         $article->translations()->create([
             'locale' => 'en',
             'title' => 'Title EN',
             'content' => 'Content EN',
         ]);
         $article->translations()->create([
             'locale' => 'ru',
             'title' => 'Title RU',
             'content' => 'Content RU',
         ]);

         $response = $this->put("/en/admin/articles/{$article->id}", [
             'slug' => 'test-clear-slug',
             'category_id' => $this->category->id,
             'translations' => [
                 'en' => ['title' => 'Title EN', 'content' => 'Content EN'],
                 'ru' => ['title' => '', 'content' => ''], // Cleared RU
                 'ua' => ['title' => '', 'content' => ''],
                 'bg' => ['title' => '', 'content' => ''],
             ]
         ]);

         $response->assertRedirect('/en/admin/articles');

         $this->assertDatabaseHas('article_translations', [
             'article_id' => $article->id,
             'locale' => 'en',
         ]);
         $this->assertDatabaseMissing('article_translations', [
             'article_id' => $article->id,
             'locale' => 'ru',
         ]);
     }

     public function test_article_translation_fallback(): void
     {
         $article = Article::factory()->create([
             'slug' => 'fallback-slug',
             'category_id' => $this->category->id,
         ]);
         $article->translations()->create([
             'locale' => 'ru',
             'title' => 'Title RU Only',
             'content' => 'Content RU Only',
         ]);

         // translate('en') falls back to RU translation since EN translation doesn't exist
         $translation = $article->translate('en');
         $this->assertNotNull($translation);
         $this->assertSame('ru', $translation->locale);
         $this->assertSame('Title RU Only', $translation->title);
     }
 }
