<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MigrateArticlesToDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrate_articles_command_populates_database_from_markdown(): void
    {
        // Ensure database is empty before run
        $this->assertSame(0, Article::count());
        $this->assertSame(0, Category::count());

        // Create the default admin user
        $admin = User::factory()->admin()->create([
            'email' => 'admin@devsense.work',
        ]);

        // Run the command
        $exitCode = Artisan::call('app:migrate-articles-to-database');
        $this->assertSame(0, $exitCode);

        // Assert tables are populated
        $this->assertGreaterThan(0, Article::count());
        $this->assertGreaterThan(0, Category::count());

        // Assert specific expected article is migrated (e.g. 8.4 guide)
        $article = Article::where('slug', '8.4')->first();
        $this->assertNotNull($article);
        $this->assertSame($admin->id, $article->author_id);
        $this->assertSame('php', $article->category->slug);

        // Assert translations are correct
        $this->assertNotNull($article->translate('en'));
        $this->assertNotNull($article->translate('ru'));
        $this->assertStringContainsString('Property Hooks', $article->translate('en')->title);
        $this->assertStringContainsString('Property Hooks', $article->translate('en')->content);
        $this->assertStringContainsString('Свойства', $article->translate('ru')->content);
    }
}
