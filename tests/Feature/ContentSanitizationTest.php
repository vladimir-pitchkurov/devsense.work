<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\ContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_sanitizer_service_filters_xss(): void
    {
        $sanitizer = new ContentSanitizer();

        // 1. Plain text HTML stripping
        $badTitle = 'Hello <script>alert("XSS")</script> World <p>nice</p>';
        $this->assertEquals('Hello alert("XSS") World nice', $sanitizer->sanitizePlainText($badTitle));

        // 2. Markdown sanitization - script tags
        $badMarkdown = 'This is a test <script>alert("hack")</script> content';
        $this->assertEquals('This is a test  content', $sanitizer->sanitizeMarkdown($badMarkdown));

        // 3. Markdown sanitization - inline event handlers
        $badInline = '<img src="x" onerror="alert(1)"> and <div onclick="run()">Click</div>';
        $this->assertEquals('<img src="x"> and <div>Click</div>', $sanitizer->sanitizeMarkdown($badInline));

        // 4. Markdown sanitization - javascript URIs
        $badUri = '[Link](javascript:alert("XSS")) or <a href="javascript:void(0)">Link</a>';
        $this->assertEquals('[Link](#) or <a href="#">Link</a>', $sanitizer->sanitizeMarkdown($badUri));
    }

    public function test_xss_inputs_are_sanitized_on_article_creation(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $faqJson = json_encode([
            ['question' => 'Q <script>alert("XSS")</script>', 'answer' => 'A <strong>bold</strong>']
        ]);

        $response = $this->post(route('admin.articles.store', ['locale' => 'en']), [
            'slug' => 'test-xss-sanitization',
            'translations' => [
                'en' => [
                    'title' => 'Title <i>HTML</i>',
                    'description' => 'Desc <script>alert(1)</script>',
                    'content' => 'Content with <script>alert("XSS")</script> and [click](javascript:alert(1))',
                    'faq' => $faqJson,
                ]
            ]
        ]);

        $response->assertRedirect();
        
        $article = Article::where('slug', 'test-xss-sanitization')->first();
        $this->assertNotNull($article);

        $translation = $article->translate('en');
        
        // Assertions verifying that markup is sanitized
        $this->assertEquals('Title HTML', $translation->title);
        $this->assertEquals('Desc alert(1)', $translation->description);
        $this->assertEquals('Content with  and [click](#)', $translation->content);
        
        $this->assertEquals('Q alert("XSS")', $translation->faq[0]['question']);
        $this->assertEquals('A bold', $translation->faq[0]['answer']);
    }
}
