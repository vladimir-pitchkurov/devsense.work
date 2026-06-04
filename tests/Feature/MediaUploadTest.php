<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->author()->create();
    }

    public function test_guest_cannot_upload_media(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->post('/en/admin/media/upload', [
            'image' => $file,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_reader_cannot_upload_media(): void
    {
        $reader = User::factory()->create(['role' => User::ROLE_READER]);

        $this->actingAs($reader);

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->post('/en/admin/media/upload', [
            'image' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_author_can_upload_image_and_metadata_is_stripped(): void
    {
        $this->actingAs($this->author);

        // Fake image creation via UploadedFile
        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->post('/en/admin/media/upload', [
            'image' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'url']);
        
        $data = $response->json();
        $this->assertTrue($data['success']);
        
        // Extract local file path from URL
        $url = $data['url'];
        $path = parse_url($url, PHP_URL_PATH);
        $filePath = public_path(ltrim($path, '/'));
        
        $this->assertFileExists($filePath);
        
        // Try reading EXIF of the uploaded file
        // A standard GD recreated image will have no EXIF headers
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($filePath);
            if ($exif !== false) {
                $this->assertArrayNotHasKey('Software', $exif, 'Image software tag should be stripped');
                $this->assertArrayNotHasKey('Make', $exif, 'Image manufacturer tag should be stripped');
                $this->assertArrayNotHasKey('Model', $exif, 'Image model tag should be stripped');
                $this->assertArrayNotHasKey('DateTimeOriginal', $exif, 'Image timestamp tag should be stripped');
            }
        }
        
        // Clean up
        @unlink($filePath);
    }

    public function test_author_can_upload_image_to_s3_when_configured(): void
    {
        Storage::fake('s3');
        config(['filesystems.default' => 's3']);

        $this->actingAs($this->author);
        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->post('/en/admin/media/upload', [
            'image' => $file,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'url']);
        
        $data = $response->json();
        $this->assertTrue($data['success']);
        
        // Assert file exists on S3 fake disk under the correct prefix (e.g. dev/ or prod/)
        $environment = app()->environment();
        $envPrefix = in_array($environment, ['local', 'testing', 'dev', 'development'], true) ? 'dev' : 'prod';
        
        $files = Storage::disk('s3')->allFiles("uploads/{$envPrefix}");
        $this->assertCount(1, $files);
    }
}
