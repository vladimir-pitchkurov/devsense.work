<?php

namespace Tests\Feature;

use App\Rules\ActiveMxRecord;
use App\Rules\DisposableEmail;
use App\Services\EmailIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EmailIntegrityValidationTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // EmailIntegrityService unit tests
    // -----------------------------------------------------------------------

    public function test_disposable_domain_is_detected(): void
    {
        $service = app(EmailIntegrityService::class);

        $this->assertTrue($service->isDisposable('user@mailinator.com'));
        $this->assertTrue($service->isDisposable('someone@guerrillamail.com'));
        $this->assertTrue($service->isDisposable('me@10minutemail.com'));
        $this->assertTrue($service->isDisposable('test@yopmail.com'));
    }

    public function test_real_domain_is_not_flagged_as_disposable(): void
    {
        $service = app(EmailIntegrityService::class);

        $this->assertFalse($service->isDisposable('user@gmail.com'));
        $this->assertFalse($service->isDisposable('dev@github.com'));
        $this->assertFalse($service->isDisposable('hello@example.org'));
    }

    public function test_disposable_check_is_case_insensitive(): void
    {
        $service = app(EmailIntegrityService::class);

        $this->assertTrue($service->isDisposable('user@MAILINATOR.COM'));
        $this->assertTrue($service->isDisposable('user@Guerrillamail.COM'));
    }

    public function test_mx_record_check_caches_result(): void
    {
        Cache::flush();

        $service = app(EmailIntegrityService::class);

        // Use a domain that definitely has MX records.
        $hasRecord = $service->hasMxRecord('test@gmail.com');
        $this->assertTrue($hasRecord);

        // The result should now be in the cache.
        $cacheKey = 'mx_check_' . md5('gmail.com');
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_domain_without_mx_record_fails(): void
    {
        $service = app(EmailIntegrityService::class);

        // This domain is intentionally nonexistent.
        $result = $service->hasMxRecord('user@this-domain-does-not-exist-xyz-12345.com');
        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // DisposableEmail validation rule
    // -----------------------------------------------------------------------

    public function test_disposable_email_rule_fails_for_known_domains(): void
    {
        $rule = new DisposableEmail();

        $failed = false;
        $rule->validate('email', 'user@mailinator.com', function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Expected DisposableEmail rule to fail for mailinator.com');
    }

    public function test_disposable_email_rule_passes_for_real_domains(): void
    {
        $rule = new DisposableEmail();

        $failed = false;
        $rule->validate('email', 'user@gmail.com', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Expected DisposableEmail rule to pass for gmail.com');
    }

    // -----------------------------------------------------------------------
    // ActiveMxRecord validation rule
    // -----------------------------------------------------------------------

    public function test_active_mx_rule_passes_for_real_domain(): void
    {
        $rule = new ActiveMxRecord();

        $failed = false;
        $rule->validate('email', 'user@gmail.com', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Expected ActiveMxRecord to pass for gmail.com');
    }

    public function test_active_mx_rule_fails_for_nonexistent_domain(): void
    {
        $rule = new ActiveMxRecord();

        $failed = false;
        $rule->validate('email', 'user@this-domain-does-not-exist-xyz-12345.com', function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Expected ActiveMxRecord to fail for a nonexistent domain');
    }

    // -----------------------------------------------------------------------
    // Registration form integration tests
    // -----------------------------------------------------------------------

    public function test_registration_fails_with_disposable_email(): void
    {
        $response = $this->post('/en/register', [
            'name'                  => 'Test User',
            'email'                 => 'throwaway@mailinator.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'terms'                 => '1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'throwaway@mailinator.com']);
    }

    public function test_registration_succeeds_with_valid_email(): void
    {
        // We mock the EmailIntegrityService so we don't hit real DNS in CI.
        $this->mock(EmailIntegrityService::class, function ($mock) {
            $mock->shouldReceive('isDisposable')->andReturn(false);
            $mock->shouldReceive('hasMxRecord')->andReturn(true);
        });

        $response = $this->post('/en/register', [
            'name'                  => 'Valid User',
            'email'                 => 'valid@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'terms'                 => '1',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'valid@example.com']);
        $response->assertRedirect();
    }

    public function test_registration_fails_with_no_mx_record(): void
    {
        // Mock: not disposable, but no MX.
        $this->mock(EmailIntegrityService::class, function ($mock) {
            $mock->shouldReceive('isDisposable')->andReturn(false);
            $mock->shouldReceive('hasMxRecord')->andReturn(false);
        });

        $response = $this->post('/en/register', [
            'name'                  => 'Ghost User',
            'email'                 => 'ghost@no-mx-domain.xyz',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'terms'                 => '1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'ghost@no-mx-domain.xyz']);
    }
}
