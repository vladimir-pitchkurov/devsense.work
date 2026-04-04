<?php

namespace Tests\Unit;

use App\Models\User;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Tests Eloquent configuration on the User model.
 */
class UserModelTest extends TestCase
{
    public function test_casts_method_declares_expected_attribute_casts(): void
    {
        $user = new User;
        $method = new ReflectionMethod(User::class, 'casts');
        $method->setAccessible(true);

        /** @var array<string, string> $casts */
        $casts = $method->invoke($user);

        $this->assertSame('datetime', $casts['email_verified_at']);
        $this->assertSame('hashed', $casts['password']);
    }

    public function test_factory_builds_model_with_core_attributes(): void
    {
        $user = User::factory()->make();

        $this->assertNotSame('', $user->name);
        $this->assertNotSame('', $user->email);
    }

    public function test_factory_unverified_state_clears_email_verified_at(): void
    {
        $user = User::factory()->unverified()->make();

        $this->assertNull($user->email_verified_at);
    }
}
