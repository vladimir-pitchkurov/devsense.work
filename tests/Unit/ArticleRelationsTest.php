<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Tag;
use App\Models\TagTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_article_with_category_author_tags_and_translations(): void
    {
        // 1. Create Author
        $author = User::factory()->author()->create();

        // 2. Create Category and Translations
        $category = Category::factory()->create(['slug' => 'php']);
        CategoryTranslation::create([
            'category_id' => $category->id,
            'locale' => 'en',
            'name' => 'PHP Language'
        ]);
        CategoryTranslation::create([
            'category_id' => $category->id,
            'locale' => 'ru',
            'name' => 'Язык PHP'
        ]);

        // 3. Create Tag and Translations
        $tag = Tag::factory()->create(['slug' => 'oop']);
        TagTranslation::create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'name' => 'OOP principles'
        ]);
        TagTranslation::create([
            'tag_id' => $tag->id,
            'locale' => 'ru',
            'name' => 'Принципы ООП'
        ]);

        // 4. Create Article and Translations
        $article = Article::factory()->create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'slug' => 'php-8-4-new-features',
            'is_published' => true,
            'published_at' => now(),
        ]);

        ArticleTranslation::create([
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'What is new in PHP 8.4',
            'description' => 'A detailed guide about PHP 8.4',
            'content' => '# PHP 8.4 Guide content',
            'faq' => [['question' => 'Q1', 'answer' => 'A1']]
        ]);

        ArticleTranslation::create([
            'article_id' => $article->id,
            'locale' => 'ru',
            'title' => 'Что нового в PHP 8.4',
            'description' => 'Подробный гайд по PHP 8.4',
            'content' => '# Руководство по PHP 8.4',
            'faq' => [['question' => 'В1', 'answer' => 'О1']]
        ]);

        // 5. Link Tag
        $article->tags()->attach($tag);

        // --- ASSERTIONS ---

        // Retrieve from DB
        $dbArticle = Article::with(['author', 'category.translations', 'tags.translations', 'translations'])->find($article->id);

        $this->assertNotNull($dbArticle);
        $this->assertSame('php-8-4-new-features', $dbArticle->slug);
        $this->assertTrue($dbArticle->is_published);

        // Check Author relation
        $this->assertSame($author->id, $dbArticle->author->id);
        $this->assertSame($author->name, $dbArticle->author->name);

        // Check Category relation and translations
        $this->assertSame($category->id, $dbArticle->category->id);
        $this->assertSame('PHP Language', $dbArticle->category->translate('en')->name);
        $this->assertSame('Язык PHP', $dbArticle->category->translate('ru')->name);

        // Check Tag relation and translations
        $this->assertCount(1, $dbArticle->tags);
        $this->assertSame($tag->id, $dbArticle->tags->first()->id);
        $this->assertSame('OOP principles', $dbArticle->tags->first()->translate('en')->name);
        $this->assertSame('Принципы ООП', $dbArticle->tags->first()->translate('ru')->name);

        // Check Article translations
        $this->assertSame('What is new in PHP 8.4', $dbArticle->translate('en')->title);
        $this->assertSame('Что нового в PHP 8.4', $dbArticle->translate('ru')->title);
        $this->assertSame('# PHP 8.4 Guide content', $dbArticle->translate('en')->content);
        $this->assertSame('# Руководство по PHP 8.4', $dbArticle->translate('ru')->content);

        // Check FAQ cast
        $this->assertSame('Q1', $dbArticle->translate('en')->faq[0]['question']);
        $this->assertSame('В1', $dbArticle->translate('ru')->faq[0]['question']);
    }
}
