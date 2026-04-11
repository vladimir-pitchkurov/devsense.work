<?php

namespace Tests\Unit;

use App\Http\Controllers\MicroservicesController;
use App\Services\MarkdownContentService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Direct controller tests for branches not reachable via constrained HTTP routes.
 */
class MicroservicesControllerTest extends TestCase
{
    public function test_show_aborts_when_slug_is_not_in_catalog(): void
    {
        $this->expectException(NotFoundHttpException::class);

        app(MicroservicesController::class)->show(
            'not-a-valid-slug',
            app(MarkdownContentService::class),
        );
    }
}
