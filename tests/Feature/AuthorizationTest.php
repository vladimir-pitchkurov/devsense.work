<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reader_cannot_access_admin_or_manage_users(): void
    {
        $reader = User::factory()->create(['role' => User::ROLE_READER]);

        $this->actingAs($reader);

        $this->assertTrue(Gate::allows('access-admin'));
        $this->assertFalse(Gate::allows('manage-users'));
    }

    public function test_author_can_access_admin_but_cannot_manage_users(): void
    {
        $author = User::factory()->author()->create();

        $this->actingAs($author);

        $this->assertTrue(Gate::allows('access-admin'));
        $this->assertFalse(Gate::allows('manage-users'));
    }

    public function test_admin_can_access_admin_and_manage_users(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->assertTrue(Gate::allows('access-admin'));
        $this->assertTrue(Gate::allows('manage-users'));
    }
}
