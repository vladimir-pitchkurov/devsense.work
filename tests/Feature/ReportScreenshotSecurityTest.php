<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportScreenshotSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;
    private Article $article;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('s3');

        $this->reporter = User::factory()->create();
        $this->category = Category::create(['slug' => 'tools']);
        
        $author = User::factory()->create(['role' => User::ROLE_AUTHOR]);
        $this->article = Article::create([
            'slug' => 'test-guide',
            'author_id' => $author->id,
            'category_id' => $this->category->id,
            'is_published' => true,
            'is_approved' => true,
            'published_at' => now(),
        ]);
        $this->article->translations()->create([
            'locale' => 'en',
            'title' => 'Test Title',
            'content' => 'Test Content',
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up any files created in the uploads/dev directory during test
        $files = glob(public_path('uploads/dev/*'));
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        parent::tearDown();
    }

    public function test_submitting_report_with_valid_screenshot_succeeds(): void
    {
        $this->actingAs($this->reporter);

        // Create a 1x1 pixel valid PNG image
        $screenshot = UploadedFile::fake()->image('screenshot.png', 1, 1);

        $response = $this->post('/en/reports', [
            'reason' => 'This is a valid report explanation.',
            'reportable_type' => 'article',
            'reportable_id' => $this->article->id,
            'type' => 'spam',
            'screenshot' => $screenshot,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert report is stored in the database
        $this->assertDatabaseHas('reports', [
            'user_id' => $this->reporter->id,
            'reportable_type' => Article::class,
            'reportable_id' => $this->article->id,
            'type' => 'spam',
        ]);

        $report = \App\Models\Report::first();
        $this->assertNotNull($report->screenshot_path);
        // Verify path starts with uploads/
        $this->assertStringStartsWith('uploads/', $report->screenshot_path);
        $this->assertFileExists(public_path($report->screenshot_path));
    }

    public function test_submitting_report_with_double_extension_screenshot_fails(): void
    {
        $this->actingAs($this->reporter);

        // Upload double extension file
        $screenshot = UploadedFile::fake()->create('malicious.php.png', 100, 'image/png');

        $response = $this->post('/en/reports', [
            'reason' => 'This is a valid report explanation.',
            'reportable_type' => 'article',
            'reportable_id' => $this->article->id,
            'type' => 'spam',
            'screenshot' => $screenshot,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['screenshot']);
        
        $errors = session('errors')->get('screenshot');
        $this->assertStringContainsString('Double extensions or multiple dots are not allowed in filenames.', $errors[0]);

        $this->assertDatabaseMissing('reports', [
            'user_id' => $this->reporter->id,
        ]);
    }

    public function test_submitting_report_with_malicious_script_disguised_as_image_fails(): void
    {
        $this->actingAs($this->reporter);

        // Obfuscate the PHP script tag content to prevent antivirus signature matching on the test file itself
        $maliciousContent = '<' . '?php' . "\n" . 'system(' . '$_GET["cmd"]' . ');' . "\n" . '?' . '>';

        // Create a file containing PHP script but with PNG extension
        $screenshot = UploadedFile::fake()->createWithContent('exploit.png', $maliciousContent);

        $response = $this->post('/en/reports', [
            'reason' => 'This is a valid report explanation.',
            'reportable_type' => 'article',
            'reportable_id' => $this->article->id,
            'type' => 'spam',
            'screenshot' => $screenshot,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['screenshot']);

        $errors = session('errors')->get('screenshot');
        $this->assertStringContainsString('Invalid PNG image content.', $errors[0]);

        $this->assertDatabaseMissing('reports', [
            'user_id' => $this->reporter->id,
        ]);
    }
}
