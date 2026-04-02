<?php

namespace App\Http\Controllers;

use App\Services\MarkdownContentService;
use Illuminate\View\View;

/**
 * Serves localized Laravel Sail / tooling guides backed by Markdown content.
 */
class PhpToolsController extends Controller
{
    /**
     * Ordered list of tool slugs exposed under `/{locale}/tools/{slug}`.
     *
     * @var list<string>
     */
    private const TOOL_SLUG_ORDER = ['sail', 'sail-databases', 'sail-queues', 'sail-env-deploy', 'sail-troubleshooting'];

    /**
     * Display the tools index with cards for each documented tool.
     */
    public function index(): View
    {
        $cards = [];
        foreach (self::TOOL_SLUG_ORDER as $slug) {
            $key = str_replace('-', '_', $slug);
            $cards[] = [
                'slug' => $slug,
                'title' => __('ui.tools_index.cards.'.$key.'.title'),
                'excerpt' => __('ui.tools_index.cards.'.$key.'.excerpt'),
            ];
        }

        return view('tools.index', ['cards' => $cards]);
    }

    /**
     * Render a single tool guide parsed from Markdown.
     *
     * @param  string  $slug  URL segment; must be one of {@see PhpToolsController::TOOL_SLUG_ORDER}.
     */
    public function show(string $slug, MarkdownContentService $markdownService): View
    {
        if (! in_array($slug, self::TOOL_SLUG_ORDER, true)) {
            abort(404);
        }

        $locale = app()->getLocale();
        $data = $markdownService->getParsedContent($locale, 'tools', $slug);

        if (! $data) {
            abort(404, __('ui.errors.tools_guide_missing', ['slug' => $slug]));
        }

        return view('tools.show', [
            'content' => $data['html'],
            'meta' => $data['meta'],
        ]);
    }
}
