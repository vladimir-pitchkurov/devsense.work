<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Loads Markdown from `resources/content`, parses CommonMark + GFM-style tables + optional YAML front matter, and caches HTML output.
 */
class MarkdownContentService
{
    /**
     * Resolve, parse, and return HTML plus front matter for a content document.
     *
     * Looks under `resources/content/{locale}/{category}/{slug}.md`, falling back to English when the locale file is missing.
     *
     * @param  string  $locale  Requested locale (e.g. `en`, `ru`).
     * @param  string  $category  Content subdirectory (e.g. `php`, `tools`).
     * @param  string  $slug  File basename without `.md`.
     * @return array{html: string, meta: array<string, mixed>, source_modified_at: int}|null Null when neither locale nor English file exists. `source_modified_at` is a Unix timestamp (seconds) so cached payloads deserialize reliably.
     */
    public function getParsedContent(string $locale, string $category, string $slug): ?array
    {
        $path = resource_path("content/{$locale}/{$category}/{$slug}.md");

        if (! File::exists($path)) {
            $path = resource_path("content/en/{$category}/{$slug}.md");
            if (! File::exists($path)) {
                return null;
            }
        }

        $modified = File::lastModified($path);
        $cacheKey = "content_meta_v3_{$locale}_{$category}_{$slug}_{$modified}";

        return Cache::rememberForever($cacheKey, function () use ($path) {
            $environment = new Environment([
                'html_input' => 'allow',
            ]);

            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new TableExtension);
            $environment->addExtension(new FrontMatterExtension);

            $converter = new MarkdownConverter($environment);
            $markdown = File::get($path);

            $result = $converter->convert($markdown);

            $frontMatter = [];
            if ($result instanceof RenderedContentWithFrontMatter) {
                $frontMatter = $result->getFrontMatter();
            }

            return [
                'html' => $result->getContent(),
                'meta' => $frontMatter,
                'source_modified_at' => File::lastModified($path),
            ];
        });
    }
}
