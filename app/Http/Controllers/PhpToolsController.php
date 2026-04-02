<?php

namespace App\Http\Controllers;

use App\Services\MarkdownContentService;
use Illuminate\View\View;

class PhpToolsController extends Controller
{
    /** @var list<string> */
    private const TOOL_SLUG_ORDER = ['sail', 'sail-databases', 'sail-queues', 'sail-env-deploy'];

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
