<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class JobsRecruitmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_jobs_index_page_loads_and_displays_listings(): void
    {
        $response = $this->get('/en/jobs');

        $response->assertStatus(200);
        $response->assertSee('Senior PHP Developer');
        $response->assertSee('London');
    }

    public function test_jobs_show_page_renders_markdown_and_job_metadata(): void
    {
        $response = $this->get('/en/jobs/senior-php-developer');

        $response->assertStatus(200);
        $response->assertSee('Senior PHP Developer');
        $response->assertSee('Responsibilities');
    }

    public function test_jobs_show_page_includes_valid_jobposting_json_ld_schema(): void
    {
        $response = $this->get('/en/jobs/senior-php-developer');

        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertStringContainsString('"@type":"JobPosting"', $html);
        $this->assertStringContainsString('"title":"Senior PHP Developer (Laravel)"', $html);
        $this->assertStringContainsString('"hiringOrganization":{"@id":"' . config('app.url') . '#organization"}', $html);
        $this->assertStringContainsString('"author":{"@id":"' . config('app.url') . '#author"}', $html);
    }

    public function test_jobs_show_page_serves_raw_markdown_to_llm_crawlers(): void
    {
        // Test query param format
        $response = $this->get('/en/jobs/senior-php-developer?format=markdown');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $response->assertSee('# Senior PHP Developer (Laravel)');

        // Test User-Agent check
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 OAI-SearchBot/1.0',
        ])->get('/en/jobs/senior-php-developer');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $response->assertSee('# Senior PHP Developer (Laravel)');
    }
}
