<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Tests\TestCase;

class FallbackLocaleRedirectTest extends TestCase
{
    public function test_non_localized_route_redirects_to_default_locale(): void
    {
        config(['app.default_site_locale' => 'en']);

        $response = $this->get('/suggestions');
        $response->assertRedirect('/en/suggestions');
        $response->assertStatus(301);
    }

    public function test_non_localized_route_with_trailing_slash_redirects_to_default_locale(): void
    {
        config(['app.default_site_locale' => 'en']);

        $response = $this->get('/suggestions/');
        $response->assertRedirect('/en/suggestions/');
        $response->assertStatus(301);
    }

    public function test_non_localized_architecture_route_redirects_to_default_locale(): void
    {
        config(['app.default_site_locale' => 'en']);

        $response = $this->get('/architecture/database-performance-and-scaling');
        $response->assertRedirect('/en/architecture/database-performance-and-scaling');
        $response->assertStatus(301);
    }

    public function test_localized_invalid_route_returns_404_without_redirect_loop(): void
    {
        $response = $this->get('/en/this-path-does-not-exist-anywhere');
        $response->assertNotFound();
    }

    public function test_robots_txt_contains_new_disallow_rules(): void
    {
        config(['seo.allow_indexing' => true]);

        $response = $this->get('/robots.txt');
        $response->assertOk();
        $response->assertSee('Disallow: */vote');
        $response->assertSee('Disallow: */reports');
        $response->assertSee('Disallow: */likes');
        $response->assertSee('Disallow: */complete');
        $response->assertSee('Disallow: */progress');
        $response->assertSee('Disallow: /partytown-proxy');
        $response->assertSee('Disallow: /vote');
    }
}
