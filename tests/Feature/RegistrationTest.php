<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_renders_successfully(): void
    {
        $response = $this->get('/en/register');
        $response->assertStatus(200);
        $response->assertSee('Create Account');
    }


    public function test_registration_validation_fails_on_empty_fields(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertGuest();
    }

    public function test_registration_validation_fails_on_password_mismatch(): void
    {
        $response = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john@devsense.work',
            'password' => 'secret-pwd-1',
            'password_confirmation' => 'secret-pwd-2',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertGuest();
    }

    public function test_registration_succeeds_creates_author_and_redirects(): void
    {
        $response = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
            'terms' => 'on',
        ]);

        $response->assertRedirect('/en/admin');
        
        $user = User::where('email', 'john@devsense.work')->first();
        $this->assertNotNull($user);
        $this->assertEquals(User::ROLE_READER, $user->role);
        $this->assertFalse($user->is_approved); // Should start unapproved
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_validation_fails_without_terms_acceptance(): void
    {
        $response = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
        ]);

        $response->assertSessionHasErrors(['terms']);
        $this->assertGuest();
    }

    public function test_duplicate_name_registration_generates_unique_slug(): void
    {
        // Register first user
        $response = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
            'terms' => 'on',
        ]);
        $response->assertRedirect('/en/admin');
        
        $user1 = User::where('email', 'john@devsense.work')->first();
        $this->assertEquals('john-author', $user1->slug);

        // Logout
        auth()->logout();

        // Register second user with same name but different email
        $response2 = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john2@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
            'terms' => 'on',
        ]);
        $response2->assertRedirect('/en/admin');

        $user2 = User::where('email', 'john2@devsense.work')->first();
        $this->assertNotNull($user2);
        // Verify unique slug was generated successfully
        $this->assertEquals('john-author-1', $user2->slug);
    }
}
