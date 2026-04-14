<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiAccessLog
{
    /**
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        $ms = (int) round((microtime(true) - $start) * 1000);
        $path = '/'.ltrim($request->path(), '/');

        $context = [
            'method' => $request->method(),
            'path' => $path,
            'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
            'duration_ms' => $ms,
            'ip' => $request->ip(),
        ];

        // Useful for monitoring abuse patterns while keeping logs bounded.
        if ($path === '/api/v1/search') {
            $q = $request->query('q');
            if (is_string($q) && $q !== '') {
                $context['q'] = mb_substr($q, 0, 120);
            }
        }

        Log::channel('content_api')->info('request', $context);

        return $response;
    }
}

