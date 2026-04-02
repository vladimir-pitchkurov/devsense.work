<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * HTML head SEO: canonical, hreflang, JSON-LD, robots meta.
 */
class SeoLayoutMetaTest extends TestCase
{
    public function test_php_guide_page_exposes_canonical_hreflang_and_tech_article_ld_json(): void
    {
        config(['app.url' => 'https://seo.test']);

        $response = $this->get('/en/php/8.5');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('href="https://seo.test/en/php/8.5"', $html);
        $this->assertStringContainsString('hreflang="uk"', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('TechArticle', $html);
        $this->assertStringContainsString('og:type', $html);
        $this->assertStringContainsString('property="og:url"', $html);
    }

    public function test_home_includes_index_follow_when_seo_indexing_enabled(): void
    {
        config([
            'app.url' => 'https://seo.test',
            'seo.allow_indexing' => true,
        ]);

        $response = $this->get('/en/');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('name="robots"', $html);
        $this->assertStringContainsString('index, follow', $html);
    }

    public function test_home_emits_noindex_when_seo_indexing_disabled(): void
    {
        config([
            'app.url' => 'https://staging.test',
            'seo.allow_indexing' => false,
        ]);

        $response = $this->get('/en/');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('name="robots"', $html);
        $this->assertStringContainsString('noindex, nofollow', $html);
    }
}
