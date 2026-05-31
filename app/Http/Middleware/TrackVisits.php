<?php

namespace App\Http\Middleware;

use App\Models\PageVisit;
use App\Support\CrawlerDetector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackVisits
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Don't track admin panel, API routes, or AJAX requests
        if ($request->is('*/admin*') || $request->is('admin*') || $request->is('api*') || $request->ajax()) {
            return $next($request);
        }

        $userAgent = $request->header('User-Agent') ?: '';
        $detection = CrawlerDetector::detect($userAgent);

        try {
            PageVisit::create([
                'ip_hash' => hash('sha256', $request->ip() ?: '127.0.0.1'),
                'url' => substr($request->fullUrl(), 0, 500),
                'path' => substr($request->path(), 0, 255),
                'user_agent' => substr($userAgent, 0, 500),
                'crawler_name' => $detection['name'],
                'is_bot' => $detection['is_bot'],
                'is_ai' => $detection['is_ai'],
                'locale' => app()->getLocale(),
                'referer' => substr($request->headers->get('referer') ?: '', 0, 500),
            ]);
        } catch (\Exception $e) {
            // Silently fail to ensure visitors are never blocked by logging issues
        }

        return $next($request);
    }
}
