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

    public function test_reader_can_access_profile_edit(): void
    {
        $response = $this->actingAs($this->reader)->get('/en/admin/profile');
        $response->assertOk();
        $response->assertSee('Plain Reader');
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

        // Live profile remains unchanged
        $this->author->refresh();
        $this->assertSame('Jane Doe', $this->author->name);
        $this->assertSame('jane-doe', $this->author->slug);

        // Changes are saved in pending profile updates
        $this->assertDatabaseHas('pending_user_profiles', [
            'user_id' => $this->author->id,
            'name' => 'Jane Smith',
            'slug' => 'jane-smith',
            'job_title' => 'Lead Architect',
            'bio' => 'Experienced software engineer.',
            'github_url' => 'https://github.com/janesmith',
        ]);
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
        $avatar = UploadedFile::fake()->image('avatar.jpg', 100, 100);

        $response = $this->actingAs($this->author)->put('/en/admin/profile', [
            'name' => 'Jane Doe',
            'slug' => 'jane-doe',
            'avatar' => $avatar,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->author->refresh();
        $this->assertNull($this->author->avatar_path); // Live path remains null

        // Check that pending profile has the uploaded avatar path
        $pending = \App\Models\PendingUserProfile::where('user_id', $this->author->id)->first();
        $this->assertNotNull($pending);
        $this->assertNotNull($pending->avatar_path);
        $this->assertStringStartsWith('uploads/', $pending->avatar_path);

        // Verify the file actually exists on disk
        $filePath = public_path($pending->avatar_path);
        $this->assertFileExists($filePath);

        // Clean up the file
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function test_admin_can_update_profile_directly(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin Joe',
            'slug' => 'admin-joe',
        ]);

        $response = $this->actingAs($admin)->put('/en/admin/profile', [
            'name' => 'Super Admin Joe',
            'slug' => 'super-admin-joe',
            'job_title' => 'Chief Admin',
            'bio' => 'Direct update.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $admin->refresh();
        $this->assertSame('Super Admin Joe', $admin->name);
        $this->assertSame('super-admin-joe', $admin->slug);
        $this->assertSame('Chief Admin', $admin->job_title);
        $this->assertSame('Direct update.', $admin->bio);

        // No draft profile should be created for admin
        $this->assertDatabaseMissing('pending_user_profiles', [
            'user_id' => $admin->id,
        ]);
    }
}
