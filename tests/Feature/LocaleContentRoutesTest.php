<?php

namespace Tests\Feature;

use App\Http\Controllers\PhpToolsController;
use App\Http\Controllers\PhpVersionController;
use App\Http\Middleware\SetLocale;
use App\Services\MarkdownContentService;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ReadsPrivateConstants;
use Tests\TestCase;

/**
 * HTTP tests for root redirect, localized shell routes, and Markdown-backed guides.
 */
class LocaleContentRoutesTest extends TestCase
{
    use ReadsPrivateConstants;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_root_redirects_to_configured_default_site_locale(): void
    {
        config(['app.default_site_locale' => 'en']);

        $this->get('/')->assertRedirect('/en');
    }

    public function test_root_redirects_to_russian_when_configured(): void
    {
        config(['app.default_site_locale' => 'ru']);

        $this->get('/')->assertRedirect('/ru');
    }

    public function test_root_falls_back_to_english_when_default_site_locale_is_invalid(): void
    {
        config(['app.default_site_locale' => 'not-a-locale']);

        $this->get('/')->assertRedirect('/en');
    }

    public function test_home_renders_for_each_supported_locale(): void
    {
        foreach (SetLocale::SUPPORTED_LOCALES as $locale) {
            $this->get("/{$locale}/")->assertOk();
        }
    }

    public function test_php_index_renders(): void
    {
        $this->get('/en/php')->assertOk();
    }

    public function test_php_show_renders_existing_guide(): void
    {
        $this->get('/en/php/8.5')->assertOk();
    }

    public function test_php_show_renders_first_and_last_catalogued_versions(): void
    {
        /** @var list<string> $order */
        $order = $this->privateClassConstant(PhpVersionController::class, 'PHP_VERSION_ORDER');
        $this->assertNotEmpty($order);

        $first = $order[0];
        $last = $order[array_key_last($order)];

        $this->get('/en/php/'.$first)->assertOk();
        $this->get('/en/php/'.$last)->assertOk();
    }

    public function test_php_show_returns_404_when_guide_does_not_exist(): void
    {
        $this->get('/en/php/99.99')->assertNotFound();
    }

    public function test_tools_index_renders(): void
    {
        $this->get('/en/tools')->assertOk();
    }

    public function test_tools_show_renders_existing_guide(): void
    {
        $this->get('/en/tools/sail')->assertOk();
    }

    public function test_each_catalogued_tool_slug_renders_successfully(): void
    {
        /** @var list<string> $slugs */
        $slugs = $this->privateClassConstant(PhpToolsController::class, 'TOOL_SLUG_ORDER');

        foreach ($slugs as $slug) {
            $this->get('/en/tools/'.$slug)->assertOk();
        }
    }

    public function test_tools_show_returns_404_for_unknown_slug(): void
    {
        $this->get('/en/tools/unknown-tool')->assertNotFound();
    }

    public function test_tools_show_returns_404_when_markdown_service_returns_no_content(): void
    {
        $this->mock(MarkdownContentService::class, function ($mock): void {
            $mock->shouldReceive('getParsedContent')->once()->andReturn(null);
        });

        $this->get('/en/tools/sail')->assertNotFound();
    }

    public function test_php_show_returns_404_when_markdown_service_returns_no_content(): void
    {
        $this->mock(MarkdownContentService::class, function ($mock): void {
            $mock->shouldReceive('getParsedContent')->once()->andReturn(null);
        });

        $this->get('/en/php/8.5')->assertNotFound();
    }
}
