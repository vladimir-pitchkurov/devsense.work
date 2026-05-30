<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * HTML head SEO: canonical, hreflang, JSON-LD, robots meta.
 */
class SeoLayoutMetaTest extends TestCase
{
    public function test_footer_includes_crawlable_cross_locale_links_on_localized_pages(): void
    {
        config(['app.url' => 'https://seo.test']);

        $response = $this->get('/ru/php');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('https://seo.test/en/php', $html);
        $this->assertStringContainsString('https://seo.test/ua/php', $html);
        $this->assertStringContainsString('footer__locales-link', $html);
    }

    public function test_home_uses_app_url_for_canonical_and_og_url(): void
    {
        config(['app.url' => 'https://seo.test']);

        $response = $this->get('/en');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('rel="canonical" href="https://seo.test/en"', $html);
        $this->assertStringContainsString('property="og:url" content="https://seo.test/en"', $html);
    }

    public function test_php_index_uses_app_url_for_canonical(): void
    {
        config(['app.url' => 'https://seo.test']);

        $response = $this->get('/en/php');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('rel="canonical" href="https://seo.test/en/php"', $html);
    }

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
        $this->assertStringContainsString('BreadcrumbList', $html);
        $this->assertStringContainsString('datePublished', $html);
        $this->assertStringContainsString('dateModified', $html);
        $this->assertStringContainsString('breadcrumb', $html);
        $this->assertStringContainsString('name="theme-color"', $html);
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

    public function test_php_index_includes_breadcrumb_schema(): void
    {
        config(['app.url' => 'https://seo.test']);

        $response = $this->get('/en/php');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('BreadcrumbList', $html);
        $this->assertStringContainsString('breadcrumb__list', $html);
    }

    public function test_resolves_relative_og_image_url(): void
    {
        config([
            'app.url' => 'https://img.test',
            'seo.default_og_image' => '/images/share.png',
        ]);

        $response = $this->get('/en/');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('og:image', $html);
        $this->assertStringContainsString('https://img.test/images/share.png', $html);
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

    public function test_php_guide_page_includes_faq_page_schema(): void
    {
        config(['app.url' => 'https://seo.test']);

        $response = $this->get('/en/php/8.5');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('FAQPage', $html);
        $this->assertStringContainsString('What is the Pipe Operator in PHP 8.5?', $html);
    }
}
