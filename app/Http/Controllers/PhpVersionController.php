<?php

namespace App\Http\Controllers;

use App\Services\MarkdownContentService;
use Illuminate\Http\Request;

class PhpVersionController extends Controller
{
    public function index()
    {
        return redirect()->route('php.show', ['version' => '8.0']);
    }

    public function show(string $version, MarkdownContentService $markdownService)
    {
        $locale = app()->getLocale();
        $data = $markdownService->getParsedContent($locale, 'php', $version);

        if (!$data) {
            abort(404, "Гайд для PHP {$version} не найден или еще не написан.");
        }

        return view('php.show', [
            'content' => $data['html'],
            'meta' => $data['meta'],
            'version' => $version
        ]);
    }
}
