<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        $supportedLocales = ['ru', 'en', 'ua', 'bg'];

        if (in_array($locale, $supportedLocales)) {
            App::setLocale($locale);
            URL::defaults(['locale' => $locale]);
            $request->route()->forgetParameter('locale');
        }

        return $next($request);
    }
}
