<?php

namespace App\Http\Controllers;

use App\Services\MarkdownContentService;
use Illuminate\View\View;

class PhpToolsController extends Controller
{
    public function sail(MarkdownContentService $markdownService): View
    {
        $locale = app()->getLocale();
        $data = $markdownService->getParsedContent($locale, 'tools', 'sail');

        if (!$data) {
            abort(404);
        }

        return view('tools.sail', [
            'content' => $data['html'],
            'meta' => $data['meta'],
        ]);
    }
}
