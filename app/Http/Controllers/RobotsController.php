<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves robots.txt; blocks indexing when SEO_ALLOW_INDEXING is false (staging).
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $allow = (bool) config('seo.allow_indexing', true);

        if (! $allow) {
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $root = rtrim((string) config('app.url'), '/');
            $body = "User-agent: *\nAllow: /\n\nSitemap: {$root}/sitemap.xml\n";
        }

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
