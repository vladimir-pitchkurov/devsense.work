<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Smoke tests for framework entrypoints outside the localized site group.
 */
class HealthAndConsoleTest extends TestCase
{
    public function test_health_check_endpoint_responds_successfully(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_inspire_console_command_outputs_a_quote(): void
    {
        $exitCode = Artisan::call('inspire');

        $this->assertSame(0, $exitCode);
        $this->assertNotSame('', trim(Artisan::output()));
    }
}
