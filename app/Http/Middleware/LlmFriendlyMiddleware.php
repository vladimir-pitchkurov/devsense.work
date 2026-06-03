<?php

namespace App\Http\Middleware;

use App\Services\PublicContentApiService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that serves raw markdown content to LLMs/AI bots.
 */
class LlmFriendlyMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private readonly PublicContentApiService $apiService
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldServeMarkdown($request)) {
            $route = $request->route();
            if ($route !== null) {
                $routeName = $route->getName();
                
                $category = null;
                $slug = null;
                
                if ($routeName === 'articles.show_custom') {
                    $any = $route->parameter('any');
                    $path = '/' . ltrim($any, '/');
                    $article = \App\Models\Article::where('custom_url', $path)->with('categories')->first();
                    if ($article) {
                        $category = $article->categories->first()?->slug ?? 'tools';
                        $slug = $article->slug;
                    }
                } else {
                    $category = match ($routeName) {
                        'php.show' => 'php',
                        'tools.show' => 'tools',
                        'microservices.show' => 'microservices',
                        'architecture.show' => 'architecture',
                        'jobs.show' => 'jobs',
                        'articles.show' => $route->parameter('category_slug'),
                        default => null,
                    };
                    
                    $slug = match ($routeName) {
                        'php.show' => $route->parameter('version'),
                        'articles.show' => $route->parameter('article_slug'),
                        default => $route->parameter('slug'),
                    };
                }

                if ($category !== null && $slug !== null) {
                    $locale = App::getLocale();
                    $doc = $this->apiService->getMarkdownDocument($locale, $category, $slug);
                    
                    if ($doc !== null) {
                        return response($doc['markdown'], 200)
                            ->header('Content-Type', 'text/markdown; charset=UTF-8');
                    }
                }
            }
        }

        return $next($request);
    }

    /**
     * Check if the request is from an AI bot or explicitly requests Markdown.
     */
    private function shouldServeMarkdown(Request $request): bool
    {
        // 1. Explicit request parameters or headers
        if ($request->query('format') === 'markdown') {
            return true;
        }

        $acceptHeader = $request->header('Accept');
        if (is_string($acceptHeader) && str_contains(strtolower($acceptHeader), 'text/markdown')) {
            return true;
        }

        // 2. User-Agent checks for known search & LLM bots
        $userAgent = $request->userAgent() ?? '';
        $aiBots = [
            'OAI-SearchBot',
            'ChatGPT-User',
            'ChatGPT',
            'PerplexityBot',
            'ClaudeBot',
            'Claude-Web',
            'Google-Extended',
            'GPTBot',
            'Applebot-Extended',
            'cohere-ai',
            'Bingbot', // Bing uses LLM ingestion
        ];

        foreach ($aiBots as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return true;
            }
        }

        return false;
    }
}
