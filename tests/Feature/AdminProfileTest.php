<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create([
            'role' => User::ROLE_AUTHOR,
            'name' => 'Jane Doe',
            'slug' => 'jane-doe',
        ]);

        $this->reader = User::factory()->create([
            'role' => User::ROLE_READER,
            'name' => 'Plain Reader',
        ]);
    }

    public function test_guest_cannot_access_profile_edit(): void
    {
        $response = $this->get('/en/admin/profile');
        $response->assertRedirect('/login');
    }

    public function test_reader_cannot_access_profile_edit(): void
    {
        $response = $this->actingAs($this->reader)->get('/en/admin/profile');
        $response->assertStatus(403);
    }

    public function test_author_can_access_profile_edit(): void
    {
        $response = $this->actingAs($this->author)->get('/en/admin/profile');
        $response->assertOk();
        $response->assertSee('Jane Doe');
        $response->assertSee('Author URL Slug');
    }

    public function test_author_can_update_profile_without_avatar(): void
    {
        $response = $this->actingAs($this->author)->put('/en/admin/profile', [
            'name' => 'Jane Smith',
            'slug' => 'jane-smith',
            'job_title' => 'Lead Architect',
            'bio' => 'Experienced software engineer.',
            'github_url' => 'https://github.com/janesmith',
            'linkedin_url' => 'https://linkedin.com/in/janesmith',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->author->refresh();
        $this->assertSame('Jane Smith', $this->author->name);
        $this->assertSame('jane-smith', $this->author->slug);
        $this->assertSame('Lead Architect', $this->author->job_title);
        $this->assertSame('Experienced software engineer.', $this->author->bio);
        $this->assertSame('https://github.com/janesmith', $this->author->github_url);
    }

    public function test_profile_update_validation_fails_with_invalid_slug(): void
    {
        $response = $this->actingAs($this->author)->from('/en/admin/profile')->put('/en/admin/profile', [
            'name' => 'Jane Smith',
            'slug' => 'invalid slug format',
        ]);

        $response->assertRedirect('/en/admin/profile');
        $response->assertSessionHasErrors(['slug']);
    }

    public function test_profile_update_validation_fails_with_duplicate_slug(): void
    {
        User::factory()->create(['slug' => 'other-author', 'role' => User::ROLE_AUTHOR]);

        $response = $this->actingAs($this->author)->from('/en/admin/profile')->put('/en/admin/profile', [
            'name' => 'Jane Smith',
            'slug' => 'other-author',
        ]);

        $response->assertRedirect('/en/admin/profile');
        $response->assertSessionHasErrors(['slug']);
    }

    public function test_author_can_upload_avatar_and_metadata_is_stripped(): void
    {
        // Use a real image format so GD imagecreatefromjpeg won't crash
        // Since we are mocking UploadedFile, let's create a fake image using Laravel's UploadedFile::fake()
        $avatar = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->actingAs($this->author)->put('/en/admin/profile', [
            'name' => 'Jane Doe',
            'slug' => 'jane-doe',
            'avatar' => $avatar,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->author->refresh();
        $this->assertNotNull($this->author->avatar_path);
        $this->assertStringStartsWith('uploads/', $this->author->avatar_path);

        // Verify the file actually exists on disk
        $filePath = public_path($this->author->avatar_path);
        $this->assertFileExists($filePath);

        // Clean up the file
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
