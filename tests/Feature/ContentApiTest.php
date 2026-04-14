<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['app.url' => 'https://example.test']);
    }

    public function test_manifest_is_public_and_supports_etag_304(): void
    {
        $first = $this->get('/api/v1/manifest');
        $first->assertOk();
        $first->assertHeader('Cache-Control');
        $this->assertNotNull($first->headers->get('ETag'));

        $etag = (string) $first->headers->get('ETag');
        $second = $this->withHeaders(['If-None-Match' => $etag])->get('/api/v1/manifest');
        $second->assertStatus(304);
    }

    public function test_articles_list_is_public(): void
    {
        $response = $this->get('/api/v1/articles?locale=en&category=architecture&limit=5');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'locale', 'category', 'slug', 'title', 'description', 'canonical_url', 'excerpt', 'citation'],
            ],
            'pagination' => ['next_cursor', 'limit'],
        ]);
    }

    public function test_article_show_is_public(): void
    {
        $id = 'en:architecture:high-load-event-ingestion';

        $response = $this->get("/api/v1/articles/{$id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $id);
        $response->assertJsonPath('data.citation.canonical_url', 'https://example.test/en/architecture/high-load-event-ingestion');
    }

    public function test_article_content_requires_api_key(): void
    {
        $id = 'en:architecture:high-load-event-ingestion';

        $this->get("/api/v1/articles/{$id}/content?format=markdown")
            ->assertStatus(401);
    }

    public function test_article_content_rejects_key_without_scope(): void
    {
        $raw = 'ds_live_test_without_scope';

        ApiKey::query()->create([
            'name' => 'test',
            'prefix' => 'ds_live_',
            'key_hash' => hash('sha256', $raw),
            'scopes' => ['search:read'],
        ]);

        $id = 'en:architecture:high-load-event-ingestion';
        $this->withHeaders(['Authorization' => "Bearer {$raw}"])
            ->get("/api/v1/articles/{$id}/content?format=markdown")
            ->assertStatus(403);
    }

    public function test_article_content_allows_key_with_scope(): void
    {
        $raw = 'ds_live_test_with_scope';

        ApiKey::query()->create([
            'name' => 'test',
            'prefix' => 'ds_live_',
            'key_hash' => hash('sha256', $raw),
            'scopes' => ['content:read'],
        ]);

        $id = 'en:architecture:high-load-event-ingestion';
        $response = $this->withHeaders(['Authorization' => "Bearer {$raw}"])
            ->get("/api/v1/articles/{$id}/content?format=markdown");

        $response->assertOk();
        $response->assertJsonPath('data.id', $id);
        $response->assertJsonPath('data.format', 'markdown');
        $response->assertJsonPath('data.citation.canonical_url', 'https://example.test/en/architecture/high-load-event-ingestion');
    }
}

