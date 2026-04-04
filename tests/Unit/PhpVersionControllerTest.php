<?php

namespace Tests\Unit;

use App\Http\Controllers\PhpVersionController;
use App\Services\MarkdownContentService;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Direct controller tests complementing HTTP feature coverage.
 */
class PhpVersionControllerTest extends TestCase
{
    public function test_show_aborts_when_markdown_service_returns_null(): void
    {
        $markdown = Mockery::mock(MarkdownContentService::class);
        $markdown->shouldReceive('getParsedContent')->once()->andReturn(null);

        $this->expectException(NotFoundHttpException::class);

        app(PhpVersionController::class)->show('0.0.1', $markdown);
    }
}
