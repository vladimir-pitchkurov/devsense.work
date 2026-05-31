<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Sign In');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'author@devsense.work',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'author@devsense.work',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_succeeds_with_valid_credentials_and_redirects(): void
    {
        $user = User::factory()->author()->create([
            'email' => 'author@devsense.work',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'author@devsense.work',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/en/admin/articles');
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_terminates_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/en');
        $this->assertGuest();
    }
}
