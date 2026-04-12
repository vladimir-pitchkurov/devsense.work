<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Tests for robots.txt and XML sitemap (environment-aware).
 */
class SeoEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_sitemap_xml_is_well_formed_and_lists_localized_urls(): void
    {
        config([
            'app.url' => 'https://example.test',
            'seo.sitemap_cache_ttl' => 60,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        $content = (string) $response->getContent();
        $this->assertStringContainsString('https://example.test', $content);
        $this->assertStringContainsString('/en/php/8.5', $content);
        $this->assertStringContainsString('/ua/php/8.5', $content);
        $this->assertStringContainsString('hreflang="uk"', $content);
        $this->assertStringContainsString('hreflang="x-default"', $content);
        $this->assertStringContainsString('/en/tools/sail', $content);
        $this->assertStringContainsString('/en/microservices/api-gateway', $content);
        $this->assertStringContainsString('/en/architecture/high-load-event-ingestion', $content);
    }

    public function test_robots_allows_indexing_and_points_sitemap_to_app_url_when_enabled(): void
    {
        config([
            'app.url' => 'https://devsense.work',
            'seo.allow_indexing' => true,
        ]);

        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $body = (string) $response->getContent();
        $this->assertStringContainsString('Allow: /', $body);
        $this->assertStringContainsString('Sitemap: https://devsense.work/sitemap.xml', $body);
        $this->assertStringNotContainsString('Disallow: /', $body);
    }

    public function test_robots_blocks_all_crawlers_when_indexing_disabled(): void
    {
        config([
            'app.url' => 'https://dev.devsense.work',
            'seo.allow_indexing' => false,
        ]);

        $response = $this->get('/robots.txt');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Disallow: /', $body);
        $this->assertStringNotContainsString('Sitemap:', $body);
    }

    public function test_sitemap_write_command_generates_public_xml_file(): void
    {
        config(['app.url' => 'https://cli.test']);

        $path = public_path('sitemap.xml');
        if (is_file($path)) {
            unlink($path);
        }

        $this->artisan('sitemap:write')->assertSuccessful();

        $this->assertFileExists($path);
        $this->assertStringContainsString('https://cli.test', (string) file_get_contents($path));

        unlink($path);
    }
}
