<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\PendingUserProfile;
use App\Models\ArticleSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CandidateResumeAndAnonymityTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create([
            'role' => User::ROLE_AUTHOR,
            'name' => 'John Candidate',
            'slug' => 'john-candidate',
            'is_approved' => true,
            'is_public' => true,
        ]);

        $this->admin = User::factory()->admin()->create();
    }

    public function test_candidate_can_submit_resume_fields_and_portfolio_for_moderation(): void
    {
        Storage::fake('public');

        $projectImage1 = UploadedFile::fake()->image('project1.png', 100, 100);
        $projectImage2 = UploadedFile::fake()->image('project2.jpg', 120, 120);

        $response = $this->actingAs($this->author)->put('/en/admin/profile', [
            'name' => 'John Candidate Updated',
            'slug' => 'john-candidate',
            'job_title' => 'Senior Backend Engineer',
            'bio' => 'Likes building APIs.',
            'intro' => 'Hello, I am a backend developer.',
            'experience' => '5 years of Laravel.',
            'job_status' => 'seeking',
            'is_anonymous' => true,
            'portfolio' => [
                [
                    'title' => 'My First Project',
                    'description' => 'A great project description.',
                    'new_images' => [$projectImage1, $projectImage2]
                ]
            ]
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert pending draft contains all candidate details
        $pending = PendingUserProfile::where('user_id', $this->author->id)->first();
        $this->assertNotNull($pending);
        $this->assertSame('Hello, I am a backend developer.', $pending->intro);
        $this->assertSame('5 years of Laravel.', $pending->experience);
        $this->assertSame('seeking', $pending->job_status);
        $this->assertTrue($pending->is_anonymous);
        
        $portfolio = $pending->portfolio;
        $this->assertIsArray($portfolio);
        $this->assertCount(1, $portfolio);
        $this->assertSame('My First Project', $portfolio[0]['title']);
        $this->assertSame('A great project description.', $portfolio[0]['description']);
        $this->assertCount(2, $portfolio[0]['images']);
        $this->assertStringStartsWith('uploads/', $portfolio[0]['images'][0]);

        // Clean up stored files
        foreach ($portfolio[0]['images'] as $path) {
            $filePath = public_path($path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    public function test_admin_approving_profile_draft_propagates_candidate_fields(): void
    {
        $pending = PendingUserProfile::create([
            'user_id' => $this->author->id,
            'name' => 'John Candidate Approved',
            'slug' => 'john-candidate-approved',
            'job_title' => 'Lead Backend Engineer',
            'bio' => 'New bio.',
            'intro' => 'Intro approved.',
            'experience' => 'Experience approved.',
            'job_status' => 'not_looking',
            'is_anonymous' => true,
            'portfolio' => [
                [
                    'title' => 'Project Approved',
                    'description' => 'Project details.',
                    'images' => ['uploads/dev/project.png']
                ]
            ]
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/en/admin/moderation/profiles/{$pending->id}/approve");

        $response->assertRedirect();
        
        $this->author->refresh();
        $this->assertSame('John Candidate Approved', $this->author->name);
        $this->assertSame('john-candidate-approved', $this->author->slug);
        $this->assertSame('Lead Backend Engineer', $this->author->job_title);
        $this->assertSame('Intro approved.', $this->author->intro);
        $this->assertSame('Experience approved.', $this->author->experience);
        $this->assertSame('not_looking', $this->author->job_status);
        $this->assertTrue($this->author->is_anonymous);
        $this->assertIsArray($this->author->portfolio);
        $this->assertSame('Project Approved', $this->author->portfolio[0]['title']);

        // Check draft was deleted
        $this->assertDatabaseMissing('pending_user_profiles', ['id' => $pending->id]);
    }

    public function test_anonymous_candidate_details_are_masked_for_guests(): void
    {
        $this->author->update([
            'name' => 'Secret Dev',
            'is_anonymous' => true,
            'job_title' => 'Hacker',
            'intro' => 'Top secret intro.',
            'experience' => 'Confidential.',
            'job_status' => 'passively_seeking',
            'github_url' => 'https://github.com/secret',
            'email' => 'secret@example.com',
            'portfolio' => [
                [
                    'title' => 'Classified Project',
                    'description' => 'Details.',
                    'images' => ['uploads/dev/secret.png']
                ]
            ]
        ]);

        // 1. Check Index page
        $response = $this->get('/en/authors');
        $response->assertStatus(200);
        $response->assertDontSee('Secret Dev');
        $response->assertSee('Anonymous Candidate');
        $response->assertSee('Hacker');

        // 2. Check Detail page
        $response = $this->get("/en/authors/{$this->author->slug}");
        $response->assertStatus(200);
        $response->assertDontSee('Secret Dev');
        $response->assertDontSee('secret@example.com');
        $response->assertDontSee('https://github.com/secret');
        $response->assertSee('Anonymous Candidate');
        $response->assertSee('Top secret intro.');
        $response->assertSee('Classified Project');
    }

    public function test_anonymous_candidate_details_visible_to_owner_and_admins(): void
    {
        $this->author->update([
            'name' => 'Secret Dev',
            'is_anonymous' => true,
            'email' => 'secret@example.com',
            'github_url' => 'https://github.com/secret',
        ]);

        // 1. Owner can see their own real details
        $response = $this->actingAs($this->author)->get("/en/authors/{$this->author->slug}");
        $response->assertStatus(200);
        $response->assertSee('Secret Dev');
        $response->assertSee('secret@example.com');
        $response->assertSee('https://github.com/secret');

        // 2. Admin can see candidate's real details
        $response = $this->actingAs($this->admin)->get("/en/authors/{$this->author->slug}");
        $response->assertStatus(200);
        $response->assertSee('Secret Dev');
        $response->assertSee('secret@example.com');
    }

    public function test_nullable_article_general_suggestions_flow(): void
    {
        // 1. Authenticated user can submit a general suggestion
        $response = $this->actingAs($this->author)->post('/en/suggestions', [
            'content' => 'We should add a Dark Mode toggler to the homepage hero block.'
        ]);

        $response->assertRedirect();

        // 2. Suggestion exists with null article_id
        $this->assertDatabaseHas('article_suggestions', [
            'user_id' => $this->author->id,
            'article_id' => null,
            'content' => 'We should add a Dark Mode toggler to the homepage hero block.',
        ]);

        $suggestion = ArticleSuggestion::latest()->first();
        $this->assertNotNull($suggestion);
        $this->assertNull($suggestion->article_id);
        $this->assertFalse($suggestion->article->exists);

        // 3. Appears in index view
        $response = $this->get('/en/suggestions');
        $response->assertStatus(200);
        $response->assertSee('We should add a Dark Mode toggler to the homepage hero block.');
    }
}
