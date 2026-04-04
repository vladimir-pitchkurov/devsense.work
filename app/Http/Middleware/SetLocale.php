<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale from the `{locale}` route parameter and applies it to URL generation defaults.
 */
class SetLocale
{
    /**
     * Supported URL locale prefixes (used by `routes/web.php` and this middleware).
     *
     * @var list<string>
     */
    public const SUPPORTED_LOCALES = ['ru', 'en', 'ua', 'bg'];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (in_array($locale, self::SUPPORTED_LOCALES, true)) {
            App::setLocale($locale);
            URL::defaults(['locale' => $locale]);
            $request->route()->forgetParameter('locale');
        }

        return $next($request);
    }
}
