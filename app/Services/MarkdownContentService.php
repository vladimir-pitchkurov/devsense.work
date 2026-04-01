<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\MarkdownConverter;

class MarkdownContentService
{
    public function getParsedContent(string $locale, string $category, string $slug): ?array
    {
        $path = resource_path("content/{$locale}/{$category}/{$slug}.md");

        if (!File::exists($path)) {
            $path = resource_path("content/en/{$category}/{$slug}.md");
            if (!File::exists($path)) {
                return null;
            }
        }

        $modified = File::lastModified($path);
        $cacheKey = "content_meta_{$locale}_{$category}_{$slug}_{$modified}";

        return Cache::rememberForever($cacheKey, function () use ($path) {
            $environment = new Environment([
                'html_input' => 'allow',
            ]);

            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new FrontMatterExtension());

            $converter = new MarkdownConverter($environment);
            $markdown = File::get($path);

            $result = $converter->convert($markdown);

            $frontMatter = [];
            if ($result instanceof RenderedContentWithFrontMatter) {
                $frontMatter = $result->getFrontMatter();
            }

            return [
                'html' => $result->getContent(),
                'meta' => $frontMatter
            ];
        });
    }
}
