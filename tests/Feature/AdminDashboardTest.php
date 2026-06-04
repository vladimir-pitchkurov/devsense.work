<?php

namespace Tests\Feature;

use App\Models\PageVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test guests/unauthenticated users are redirected to login.
     */
    public function test_guest_is_redirected_away_from_dashboard(): void
    {
        $response = $this->get('/en/admin');
        $response->assertRedirect(route('login'));

        $responseDashboard = $this->get('/en/admin/dashboard');
        $responseDashboard->assertRedirect(route('login'));
    }

    /**
     * Test readers can access their own cabinet dashboard.
     */
    public function test_reader_can_access_own_dashboard(): void
    {
        $reader = User::factory()->create(['role' => User::ROLE_READER]);

        $response = $this->actingAs($reader)->get('/en/admin');
        $response->assertStatus(200);
        $response->assertSee($reader->name);
        $response->assertSee('Experience Points');
        $response->assertDontSee('Analytics Dashboard');

        $responseDashboard = $this->actingAs($reader)->get('/en/admin/dashboard');
        $responseDashboard->assertStatus(200);
        $responseDashboard->assertSee($reader->name);
        $responseDashboard->assertSee('Experience Points');
        $responseDashboard->assertDontSee('Analytics Dashboard');
    }

    /**
     * Test authors can access their own cabinet dashboard.
     */
    public function test_author_can_access_own_dashboard(): void
    {
        $author = User::factory()->author()->create();

        $response = $this->actingAs($author)->get('/en/admin');
        $response->assertStatus(200);
        $response->assertSee($author->name);
        $response->assertSee('Experience Points');
        $response->assertDontSee('Analytics Dashboard');
    }

    /**
     * Test super admin can access the analytics dashboard.
     */
    public function test_super_admin_can_access_analytics_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        // Create some sample traffic records to render
        PageVisit::create([
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'url' => 'http://localhost/en/php/8.3',
            'path' => 'en/php/8.3',
            'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'crawler_name' => 'Googlebot',
            'is_bot' => true,
            'is_ai' => false,
            'locale' => 'en',
            'referer' => 'https://google.com',
        ]);

        $response = $this->actingAs($admin)->get('/en/admin');
        $response->assertStatus(200);
        $response->assertSee('Analytics Dashboard');
        $response->assertSee('Total Visits');
        $response->assertSee('Googlebot');
    }

    /**
     * Test middleware logs public hits and handles bot classifications.
     */
    public function test_middleware_records_public_visits_and_classifies_bots(): void
    {
        // 1. Test regular visitor (Human)
        $this->get('/en', [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $this->assertDatabaseHas('page_visits', [
            'path' => 'en',
            'is_bot' => false,
            'is_ai' => false,
            'crawler_name' => null,
            'locale' => 'en',
        ]);

        // 2. Test AI Bot (e.g. GPTBot)
        $this->get('/en/php', [
            'User-Agent' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/2.0; +https://openai.com/gptbot)',
        ]);

        $this->assertDatabaseHas('page_visits', [
            'path' => 'en/php',
            'is_bot' => true,
            'is_ai' => true,
            'crawler_name' => 'GPTBot (OpenAI)',
        ]);

        // 3. Test Search Engine Bot (e.g. Bingbot)
        $this->get('/en', [
            'User-Agent' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        ]);

        $this->assertDatabaseHas('page_visits', [
            'is_bot' => true,
            'is_ai' => false,
            'crawler_name' => 'Bingbot',
        ]);

        // 4. Test admin routes are ignored (no logging)
        $author = User::factory()->author()->create();
        $initialCount = PageVisit::count();

        $this->actingAs($author)->get('/en/admin');
        $this->assertEquals($initialCount, PageVisit::count());
    }

    /**
     * Test GTM Partytown Proxy route behavior.
     */
    public function test_partytown_proxy_endpoint_cors_and_whitelisting(): void
    {
        // Mock GTM script fetch
        Http::fake([
            'https://www.googletagmanager.com/*' => Http::response('console.log("gtm");', 200, ['Content-Type' => 'application/javascript']),
        ]);

        // 1. Success case: Whitelisted domain
        $response = $this->get('/partytown-proxy?url=' . urlencode('https://www.googletagmanager.com/gtm.js?id=GTM-TEST'));
        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $response->assertSee('console.log("gtm");', false);

        // 2. Forbidden case: Domain not whitelisted
        $responseForbidden = $this->get('/partytown-proxy?url=' . urlencode('https://evil-hacker.com/malicious.js'));
        $responseForbidden->assertStatus(403);

        // 3. Bad request: Missing url parameter
        $responseBad = $this->get('/partytown-proxy');
        $responseBad->assertStatus(400);
    }
}
