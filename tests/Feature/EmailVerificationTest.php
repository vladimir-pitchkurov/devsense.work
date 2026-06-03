<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'role' => User::ROLE_AUTHOR,
        ]);

        $response = $this->actingAs($user)->get('/en/admin/articles');

        $response->assertRedirect('/en/email/verify');
    }

    public function test_verified_user_can_access_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'role' => User::ROLE_AUTHOR,
        ]);

        $response = $this->actingAs($user)->get('/en/admin/articles');

        $response->assertStatus(200);
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get('/en/email/verify');

        $response->assertStatus(200);
        $response->assertSee('Verify Your Email');
    }

    public function test_verified_user_is_redirected_away_from_verification_screen(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'role' => User::ROLE_AUTHOR,
        ]);

        $response = $this->actingAs($user)->get('/en/email/verify');

        $response->assertRedirect('/en/admin');
    }

    public function test_registered_event_dispatches_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        event(new Registered($user));

        Notification::assertSentTo($user, \App\Notifications\VerifyEmailQueued::class);
    }

    public function test_email_can_be_verified(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
            'role' => User::ROLE_AUTHOR,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'locale' => 'en',
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect('/en/admin');
    }

    public function test_verification_link_can_be_resent(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->post('/en/email/verification-notification');

        $response->assertRedirect();
        $response->assertSessionHas('resent');
        Notification::assertSentTo($user, \App\Notifications\VerifyEmailQueued::class);
    }
}
