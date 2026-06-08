<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_auth_redirects_to_google(): void
    {
        $provider = \Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com'));

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google');

        $response->assertRedirect('https://accounts.google.com');
        $this->assertEquals('en', session('auth_locale'));
    }

    public function test_google_auth_registers_new_user(): void
    {
        $socialiteUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $socialiteUser->shouldReceive('getId')->andReturn('google-id-123');
        $socialiteUser->shouldReceive('getName')->andReturn('John Doe');
        $socialiteUser->shouldReceive('getEmail')->andReturn('john.doe@example.com');

        $provider = \Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/en/admin');
        
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'google_id' => 'google-id-123',
            'role' => User::ROLE_READER,
        ]);

        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_auth_links_existing_user_by_email(): void
    {
        $user = User::factory()->create([
            'email' => 'john.doe@example.com',
            'google_id' => null,
            'email_verified_at' => null,
        ]);

        $socialiteUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $socialiteUser->shouldReceive('getId')->andReturn('google-id-123');
        $socialiteUser->shouldReceive('getName')->andReturn('John Doe');
        $socialiteUser->shouldReceive('getEmail')->andReturn('john.doe@example.com');

        $provider = \Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/en/admin');
        
        $user->refresh();
        $this->assertEquals('google-id-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_auth_logs_in_existing_user_by_google_id(): void
    {
        $user = User::factory()->create([
            'email' => 'john.doe@example.com',
            'google_id' => 'google-id-123',
        ]);

        $socialiteUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $socialiteUser->shouldReceive('getId')->andReturn('google-id-123');
        $socialiteUser->shouldReceive('getName')->andReturn('John Doe');
        $socialiteUser->shouldReceive('getEmail')->andReturn('john.doe@example.com');

        $provider = \Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/en/admin');
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_auth_syncs_guest_quiz_progress(): void
    {
        // Seed Quiz & Questions
        $quiz = Quiz::create([
            'slug' => 'php-basics-interview',
            'points' => 100,
        ]);
        $quiz->translations()->create([
            'locale' => 'en',
            'title' => 'PHP Basics Interview',
            'description' => 'Test your basics.',
        ]);

        $q1 = $quiz->questions()->create([
            'type' => 'multiple_choice',
            'points' => 50,
            'correct_answer_index' => 1,
            'explanation' => 'Explain 1',
        ]);

        $q2 = $quiz->questions()->create([
            'type' => 'multiple_choice',
            'points' => 50,
            'correct_answer_index' => 0,
            'explanation' => 'Explain 2',
        ]);

        $socialiteUser = \Mockery::mock('Laravel\Socialite\Two\User');
        $socialiteUser->shouldReceive('getId')->andReturn('google-id-123');
        $socialiteUser->shouldReceive('getName')->andReturn('John Doe');
        $socialiteUser->shouldReceive('getEmail')->andReturn('john.doe@example.com');

        $provider = \Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->withSession([
            'pending_quiz' => [
                'slug' => 'php-basics-interview',
                'answers' => [
                    $q1->id => 1, // correct
                    $q2->id => 0, // correct
                ]
            ]
        ])->get('/auth/google/callback');

        $response->assertRedirect('/en/quizzes/php-basics-interview');

        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals(100, $user->points);
        $this->assertDatabaseHas('user_quizzes', [
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'score' => 100,
        ]);
    }

    public function test_google_registered_user_can_set_password_without_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'john.doe@example.com',
            'password' => null, // Google registered
            'google_id' => 'google-id-123',
        ]);

        $response = $this->actingAs($user)
            ->put('/en/admin/profile/password', [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ]);

        $response->assertRedirect('/en/admin/profile');
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertNotNull($user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-secure-password', $user->password));
    }

    public function test_user_with_existing_password_must_provide_current_password_to_change(): void
    {
        $user = User::factory()->create([
            'email' => 'john.doe@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('old-secure-password'),
        ]);

        $response = $this->actingAs($user)
            ->put('/en/admin/profile/password', [
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ]);

        $response->assertSessionHasErrors('current_password');

        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('old-secure-password', $user->password));
    }
}
