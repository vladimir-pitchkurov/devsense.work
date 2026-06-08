<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Tag;
use App\Models\TagTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SeedTranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_translations_command_populates_database_from_translated_markdown(): void
    {
        // Assert initial state is clean for the new locales
        $this->assertSame(0, ArticleTranslation::whereIn('locale', ['de', 'fr', 'es', 'it'])->count());

        // Run the seeding command
        $exitCode = Artisan::call('app:seed-translations');
        $this->assertSame(0, $exitCode);

        // Assert that translations were populated for the target locales
        $this->assertGreaterThan(0, ArticleTranslation::where('locale', 'de')->count());
        $this->assertGreaterThan(0, ArticleTranslation::where('locale', 'fr')->count());
        $this->assertGreaterThan(0, ArticleTranslation::where('locale', 'es')->count());
        $this->assertGreaterThan(0, ArticleTranslation::where('locale', 'it')->count());

        // Check if category translations were successfully populated
        $this->assertGreaterThan(0, CategoryTranslation::where('locale', 'de')->count());
        $this->assertGreaterThan(0, CategoryTranslation::where('locale', 'fr')->count());

        // Check tag translations
        $this->assertGreaterThan(0, TagTranslation::where('locale', 'de')->count());

        // Verify a specific article translation
        $articleTranslation = ArticleTranslation::where('locale', 'de')
            ->whereHas('article', function ($query) {
                $query->where('slug', '8.4');
            })
            ->first();

        $this->assertNotNull($articleTranslation);
    }

    public function test_seed_translations_command_does_not_overwrite_by_default_but_overwrites_with_update_flag(): void
    {
        Artisan::call('app:seed-translations');

        $translation = ArticleTranslation::where('locale', 'de')
            ->whereHas('article', function ($query) {
                $query->where('slug', '8.4');
            })
            ->first();
        
        $this->assertNotNull($translation);
        $originalTitle = $translation->title;
        $translation->title = 'Manual German Title Edit';
        $translation->save();

        Artisan::call('app:seed-translations');

        $translation->refresh();
        $this->assertSame('Manual German Title Edit', $translation->title);

        Artisan::call('app:seed-translations', ['--update' => true]);

        $translation->refresh();
        $this->assertSame($originalTitle, $translation->title);
    }
}
