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
            $body = "User-agent: *\n" .
                    "Allow: /\n" .
                    "Disallow: */vote\n" .
                    "Disallow: */reports\n" .
                    "Disallow: */likes\n" .
                    "Disallow: */complete\n" .
                    "Disallow: */progress\n" .
                    "Disallow: /partytown-proxy\n" .
                    "Disallow: /vote\n\n" .
                    "Sitemap: {$root}/sitemap.xml\n";
        }

        return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
