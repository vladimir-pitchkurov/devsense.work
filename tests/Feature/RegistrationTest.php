<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to prepare valid registration session parameters.
     */
    private function prepareRegistrationSession(): void
    {
        $this->withSession([
            'register_form_loaded_at' => microtime(true) - 10.0, // 10 seconds ago (passes time-lock)
            'register_captcha_answer' => 15,                     // sum is 15
        ]);
    }

    public function test_registration_page_renders_successfully(): void
    {
        $response = $this->get('/en/register');
        $response->assertStatus(200);
        $response->assertSee('Create Account');
        $response->assertSessionHas('register_form_loaded_at');
        $response->assertSessionHas('register_captcha_answer');
    }

    public function test_registration_validation_fails_on_empty_fields(): void
    {
        $this->prepareRegistrationSession();

        $response = $this->post('/register', [
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
            'captcha' => '',
            'test_bot_protection' => '1',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'captcha']);
        $this->assertGuest();
    }

    public function test_registration_validation_fails_on_password_mismatch(): void
    {
        $this->prepareRegistrationSession();

        $response = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john@devsense.work',
            'password' => 'secret-pwd-1',
            'password_confirmation' => 'secret-pwd-2',
            'captcha' => '15',
            'test_bot_protection' => '1',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertGuest();
    }

    public function test_registration_succeeds_creates_author_and_redirects(): void
    {
        $this->prepareRegistrationSession();

        $response = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
            'terms' => 'on',
            'captcha' => '15',
            'test_bot_protection' => '1',
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
        $this->prepareRegistrationSession();

        $response = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
            'captcha' => '15',
            'test_bot_protection' => '1',
        ]);

        $response->assertSessionHasErrors(['terms']);
        $this->assertGuest();
    }

    public function test_duplicate_name_registration_generates_unique_slug(): void
    {
        // First registration
        $this->prepareRegistrationSession();
        $response = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
            'terms' => 'on',
            'captcha' => '15',
            'test_bot_protection' => '1',
        ]);
        $response->assertRedirect('/en/admin');
        
        $user1 = User::where('email', 'john@devsense.work')->first();
        $this->assertEquals('john-author', $user1->slug);

        // Logout
        auth()->logout();

        // Second registration
        $this->prepareRegistrationSession();
        $response2 = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john2@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
            'terms' => 'on',
            'captcha' => '15',
            'test_bot_protection' => '1',
        ]);
        $response2->assertRedirect('/en/admin');

        $user2 = User::where('email', 'john2@devsense.work')->first();
        $this->assertNotNull($user2);
        $this->assertEquals('john-author-1', $user2->slug);
    }

    public function test_registration_fails_if_honeypot_filled(): void
    {
        $this->prepareRegistrationSession();

        $response = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
            'terms' => 'on',
            'captcha' => '15',
            'middle_name' => 'Spam Bot Text',
            'test_bot_protection' => '1',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_registration_fails_if_submitted_too_fast(): void
    {
        // Set load time to now (instant submission check)
        $this->withSession([
            'register_form_loaded_at' => microtime(true),
            'register_captcha_answer' => 15,
        ]);

        $response = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
            'terms' => 'on',
            'captcha' => '15',
            'test_bot_protection' => '1',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_registration_fails_if_captcha_incorrect(): void
    {
        $this->prepareRegistrationSession();

        $response = $this->post('/register', [
            'name' => 'John Author',
            'email' => 'john@devsense.work',
            'password' => 'secret-pwd-123',
            'password_confirmation' => 'secret-pwd-123',
            'terms' => 'on',
            'captcha' => '99', // Incorrect answer
            'test_bot_protection' => '1',
        ]);

        $response->assertSessionHasErrors(['captcha']);
        $this->assertGuest();
    }

    public function test_cleanup_unverified_users_command_removes_old_unverified_registrations(): void
    {
        // 1. Create a user who is verified
        User::factory()->create([
            'email' => 'verified@devsense.work',
            'email_verified_at' => now(),
            'created_at' => now()->subDays(2),
        ]);

        // 2. Create a user who is unverified but recent (6 hours old)
        User::factory()->create([
            'email' => 'recent_unverified@devsense.work',
            'email_verified_at' => null,
            'created_at' => now()->subHours(6),
        ]);

        // 3. Create a user who is unverified and old (30 hours old)
        $oldUnverified = User::factory()->create([
            'email' => 'old_unverified@devsense.work',
            'email_verified_at' => null,
            'created_at' => now()->subHours(30),
        ]);

        // Run the clean command
        $exitCode = Artisan::call('app:clean-unverified-users', ['--hours' => 24]);
        $this->assertEquals(0, $exitCode);

        // Assert only the old unverified user is deleted
        $this->assertDatabaseHas('users', ['email' => 'verified@devsense.work']);
        $this->assertDatabaseHas('users', ['email' => 'recent_unverified@devsense.work']);
        $this->assertDatabaseMissing('users', ['email' => 'old_unverified@devsense.work']);
    }
}
