<?php

namespace Tests\Unit;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Tests locale detection from the `{locale}` route parameter.
 */
class SetLocaleMiddlewareTest extends TestCase
{
    public function test_sets_application_locale_when_route_parameter_is_supported(): void
    {
        App::setLocale('en');

        $middleware = new SetLocale;
        $request = Request::create('/ru/welcome', 'GET');
        $route = new Route('GET', '{locale}/welcome', []);
        $route->bind($request);
        $route->setParameter('locale', 'ru');
        $request->setRouteResolver(static fn () => $route);

        $middleware->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('ru', App::getLocale());
        $this->assertFalse($route->hasParameter('locale'));
    }

    public function test_leaves_default_locale_when_route_parameter_is_not_supported(): void
    {
        App::setLocale('en');

        $middleware = new SetLocale;
        $request = Request::create('/xx/welcome', 'GET');
        $route = new Route('GET', '{locale}/welcome', []);
        $route->bind($request);
        $route->setParameter('locale', 'xx');
        $request->setRouteResolver(static fn () => $route);

        $middleware->handle($request, static fn (): Response => new Response('next'));

        $this->assertSame('en', App::getLocale());
    }
}
