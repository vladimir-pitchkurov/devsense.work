<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_readers_cannot_access_user_management(): void
    {
        $reader = User::factory()->create(['role' => User::ROLE_READER]);

        $this->actingAs($reader);

        $this->get(route('admin.users.index', ['locale' => 'en']))->assertStatus(403);
        $this->get(route('admin.users.edit', ['locale' => 'en', 'user' => $reader->id]))->assertStatus(403);
        $this->put(route('admin.users.update', ['locale' => 'en', 'user' => $reader->id]), [])->assertStatus(403);
    }

    public function test_authors_cannot_access_user_management(): void
    {
        $author = User::factory()->author()->create();

        $this->actingAs($author);

        $this->get(route('admin.users.index', ['locale' => 'en']))->assertStatus(403);
        $this->get(route('admin.users.edit', ['locale' => 'en', 'user' => $author->id]))->assertStatus(403);
        $this->put(route('admin.users.update', ['locale' => 'en', 'user' => $author->id]), [])->assertStatus(403);
    }

    public function test_super_admins_can_access_user_management_index_and_edit(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin);

        $response = $this->get(route('admin.users.index', ['locale' => 'en']));
        $response->assertStatus(200);
        $response->assertSee($user->name);

        $editResponse = $this->get(route('admin.users.edit', ['locale' => 'en', 'user' => $user->id]));
        $editResponse->assertStatus(200);
    }

    public function test_super_admins_can_update_user_role_and_block_status(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'role' => User::ROLE_READER,
            'is_blocked' => false,
            'is_approved' => false,
        ]);

        $this->actingAs($admin);

        $response = $this->put(route('admin.users.update', ['locale' => 'en', 'user' => $user->id]), [
            'role' => User::ROLE_AUTHOR,
            'is_blocked' => '1',
            'is_approved' => '1',
        ]);

        $response->assertRedirect(route('admin.users.index', ['locale' => 'en']));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals(User::ROLE_AUTHOR, $user->role);
        $this->assertTrue($user->is_blocked);
        $this->assertTrue($user->is_approved);
    }

    public function test_super_admins_cannot_modify_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $response = $this->put(route('admin.users.update', ['locale' => 'en', 'user' => $admin->id]), [
            'role' => User::ROLE_READER,
            'is_blocked' => '1',
        ]);

        $response->assertSessionHasErrors(['error']);
        $admin->refresh();
        $this->assertEquals(User::ROLE_SUPER_ADMIN, $admin->role);
        $this->assertFalse($admin->is_blocked);
    }

    public function test_blocked_user_is_logged_out_by_middleware(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_AUTHOR,
            'is_blocked' => false,
        ]);

        $this->actingAs($user);

        // First request is allowed (not blocked)
        $this->get(route('admin.dashboard', ['locale' => 'en']))->assertStatus(200);

        // Block the user in database
        $user->update(['is_blocked' => true]);

        // Next request should trigger middleware, log out the user, and redirect
        $response = $this->get(route('admin.dashboard', ['locale' => 'en']));
        
        $response->assertRedirect(route('login.locale', ['locale' => 'en']));
        $this->assertFalse(auth()->check());
    }
}
