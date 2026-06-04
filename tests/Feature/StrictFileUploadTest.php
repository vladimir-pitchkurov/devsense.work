<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MediaUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StrictFileUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private MediaUploadService $uploadService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->author()->create();
        $this->uploadService = new MediaUploadService();
    }

    public function test_rejects_files_with_multiple_dots_or_double_extensions(): void
    {
        $this->actingAs($this->author);

        // 1. Double extension with .php
        $file = UploadedFile::fake()->create('malicious.php.png', 10, 'image/png');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Double extensions or multiple dots are not allowed in filenames.');

        $this->uploadService->uploadAndStrip($file);
    }

    public function test_rejects_files_with_multiple_dots_standard_name(): void
    {
        $this->actingAs($this->author);

        // 2. Multiple dots in name (e.g., photo.backup.png)
        $file = UploadedFile::fake()->create('photo.backup.png', 10, 'image/png');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Double extensions or multiple dots are not allowed in filenames.');

        $this->uploadService->uploadAndStrip($file);
    }

    public function test_fails_when_gd_cannot_process_image_content(): void
    {
        $this->actingAs($this->author);

        // Create a fake file with image extension but containing random script/text bytes instead of valid image binary.
        $file = UploadedFile::fake()->createWithContent('exploit.png', '<?php echo "malicious content"; ?>');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid PNG image content.');

        $this->uploadService->uploadAndStrip($file);
    }
}
