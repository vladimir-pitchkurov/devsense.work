<?php

namespace App\Http\Controllers;

use App\Services\MarkdownContentService;
use Illuminate\View\View;

class PhpVersionController extends Controller
{
    private const PHP_VERSION_ORDER = ['7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'];

    public function index(): View
    {
        $cards = [];
        foreach (self::PHP_VERSION_ORDER as $slug) {
            $key = 'v'.str_replace('.', '', $slug);
            $cards[] = [
                'version' => $slug,
                'title' => __("ui.php_index.cards.{$key}.title"),
                'excerpt' => __("ui.php_index.cards.{$key}.excerpt"),
            ];
        }

        return view('php.index', ['cards' => $cards]);
    }

    public function show(string $version, MarkdownContentService $markdownService)
    {
        $locale = app()->getLocale();
        $data = $markdownService->getParsedContent($locale, 'php', $version);

        if (!$data) {
            abort(404, __('ui.errors.php_guide_missing', ['version' => $version]));
        }

        return view('php.show', [
            'content' => $data['html'],
            'meta' => $data['meta'],
            'version' => $version
        ]);
    }
}
