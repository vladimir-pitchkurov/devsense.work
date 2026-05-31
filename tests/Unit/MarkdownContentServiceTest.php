<?php

namespace Tests\Unit;

use App\Services\MarkdownContentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Tests for Markdown loading, English fallback, caching, and front matter parsing.
 */
class MarkdownContentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_second_request_reuses_cached_parse_result(): void
    {
        $service = new MarkdownContentService;

        $first = $service->getParsedContent('en', 'php', '8.5');
        $second = $service->getParsedContent('en', 'php', '8.5');

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first['html'], $second['html']);
        $this->assertSame($first['meta'], $second['meta']);
    }

    public function test_returns_html_for_existing_locale_file(): void
    {
        $service = new MarkdownContentService;
        $result = $service->getParsedContent('en', 'php', '8.5');

        $this->assertNotNull($result);
        $this->assertStringContainsString('PHP 8.5', $result['html']);
        $this->assertArrayHasKey('title', $result['meta']);
        $this->assertIsInt($result['source_modified_at']);
        $this->assertGreaterThan(0, $result['source_modified_at']);
    }

    public function test_pipe_tables_render_as_html_tables(): void
    {
        $service = new MarkdownContentService;
        $result = $service->getParsedContent('en', 'architecture', 'message-queues-compared');

        $this->assertNotNull($result);
        $this->assertStringContainsString('<table>', $result['html']);
        $this->assertStringContainsString('<thead>', $result['html']);
        $this->assertStringContainsString('<tbody>', $result['html']);
    }

    public function test_falls_back_to_english_when_locale_file_is_missing(): void
    {
        $service = new MarkdownContentService;
        $result = $service->getParsedContent('zz', 'php', '8.5');

        $this->assertNotNull($result);
        $this->assertStringContainsString('PHP 8.5', $result['html']);
    }

    public function test_returns_null_when_neither_locale_nor_english_file_exists(): void
    {
        $service = new MarkdownContentService;

        $this->assertNull($service->getParsedContent('en', 'php', 'nonexistent-guide-slug-xyz'));
    }

    public function test_plain_markdown_without_front_matter_yields_empty_meta_array(): void
    {
        Cache::flush();

        $path = resource_path('content/en/php/plain-meta-fixture.md');

        File::shouldReceive('exists')
            ->once()
            ->with($path)
            ->andReturnTrue();
        File::shouldReceive('lastModified')
            ->twice()
            ->with($path)
            ->andReturn(42);
        File::shouldReceive('get')
            ->once()
            ->with($path)
            ->andReturn("# Heading\n\nBody.");

        Cache::shouldReceive('rememberForever')
            ->once()
            ->andReturnUsing(static fn (string $key, callable $callback): array => $callback());

        $service = new MarkdownContentService;
        $result = $service->getParsedContent('en', 'php', 'plain-meta-fixture');

        $this->assertNotNull($result);
        $this->assertSame([], $result['meta']);
        $this->assertSame(42, $result['source_modified_at']);
        $this->assertStringContainsString('Heading', $result['html']);
    }

    public function test_gfm_alerts_are_processed(): void
    {
        Cache::flush();

        $path = resource_path('content/en/php/alert-fixture.md');

        File::shouldReceive('exists')
            ->once()
            ->with($path)
            ->andReturnTrue();
        File::shouldReceive('lastModified')
            ->twice()
            ->with($path)
            ->andReturn(42);
        File::shouldReceive('get')
            ->once()
            ->with($path)
            ->andReturn("> [!NOTE]\n> This is a test note.");

        Cache::shouldReceive('rememberForever')
            ->once()
            ->andReturnUsing(static fn (string $key, callable $callback): array => $callback());

        $service = new MarkdownContentService;
        $result = $service->getParsedContent('en', 'php', 'alert-fixture');

        $this->assertNotNull($result);
        $this->assertStringContainsString('class="markdown-alert markdown-alert-note"', $result['html']);
        $this->assertStringContainsString('class="markdown-alert-title"', $result['html']);
        $this->assertStringContainsString('Note', $result['html']);
        $this->assertStringContainsString('This is a test note.', $result['html']);
    }
}
