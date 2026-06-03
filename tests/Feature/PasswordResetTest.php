<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordQueued;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/en/password/reset');
        $response->assertStatus(200);
        $response->assertSee('Forgot Password?');

        $responseRu = $this->get('/ru/password/reset');
        $responseRu->assertStatus(200);
        $responseRu->assertSee('Забыли пароль?');
    }

    public function test_password_reset_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'author@devsense.work',
        ]);

        $response = $this->post('/en/password/email', [
            'email' => 'author@devsense.work',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordQueued::class);
    }

    public function test_password_reset_link_request_validation_fails_for_invalid_email(): void
    {
        $response = $this->post('/en/password/email', [
            'email' => 'invalid-email',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create([
            'email' => 'author@devsense.work',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->get("/en/password/reset/{$token}?email=author@devsense.work");

        $response->assertStatus(200);
        $response->assertSee('Reset Password');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'email' => 'author@devsense.work',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post('/en/password/reset', [
            'token' => $token,
            'email' => 'author@devsense.work',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect('/en/admin');
        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);

        Event::assertDispatched(PasswordReset::class);
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'author@devsense.work',
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->post('/en/password/reset', [
            'token' => 'invalid-token',
            'email' => 'author@devsense.work',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertFalse(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_authenticated_user_can_change_password_from_profile_settings(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-secure-password'),
            'email_verified_at' => now(),
            'role' => User::ROLE_AUTHOR,
        ]);

        $response = $this->actingAs($user)->put('/en/admin/profile/password', [
            'current_password' => 'current-secure-password',
            'password' => 'new-secure-password-123',
            'password_confirmation' => 'new-secure-password-123',
        ]);

        $response->assertRedirect('/en/admin/profile');
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('new-secure-password-123', $user->fresh()->password));
    }

    public function test_authenticated_user_cannot_change_password_with_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-secure-password'),
            'email_verified_at' => now(),
            'role' => User::ROLE_AUTHOR,
        ]);

        $response = $this->actingAs($user)->put('/en/admin/profile/password', [
            'current_password' => 'wrong-current-password',
            'password' => 'new-secure-password-123',
            'password_confirmation' => 'new-secure-password-123',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->assertFalse(Hash::check('new-secure-password-123', $user->fresh()->password));
    }
}
