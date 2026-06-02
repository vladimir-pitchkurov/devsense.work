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
        // 1. Query the database first
        $article = \App\Models\Article::where('slug', $slug)
            ->whereHas('category', function ($q) use ($category) {
                $q->where('slug', $category);
            })
            ->first();

        if ($article) {
            $currentUser = auth()->user();
            $isOwnerOrAdmin = $currentUser && ($currentUser->isAdmin() || $currentUser->id === $article->author_id);

            $translation = null;
            if ($isOwnerOrAdmin) {
                $translation = $article->pendingTranslations()->where('locale', $locale)->first();
                if (!$translation) {
                    $translation = $article->pendingTranslations()->where('locale', 'en')->first();
                }
                if (!$translation) {
                    $translation = $article->pendingTranslations()->first();
                }
            }

            if (!$translation) {
                $translation = $article->translations()->where('locale', $locale)->first();
                if (!$translation) {
                    $translation = $article->translations()->where('locale', 'en')->first();
                }
                if (!$translation) {
                    $translation = $article->translations()->first();
                }
            }

            if ($translation) {
                $modified = max($article->updated_at?->timestamp ?? 0, $translation->updated_at?->timestamp ?? 0);
                $isDraft = $translation instanceof \App\Models\PendingArticleTranslation;
                $cacheKey = "db_content_meta_v4_{$locale}_{$category}_{$slug}_{$modified}" . ($isDraft ? '_draft' : '');

                $data = Cache::rememberForever($cacheKey, function () use ($translation, $modified) {
                    $environment = new Environment([
                        'html_input' => 'allow',
                    ]);

                    $environment->addExtension(new CommonMarkCoreExtension);
                    $environment->addExtension(new TableExtension);
                    $environment->addExtension(new FrontMatterExtension);

                    $environment->addRenderer(
                        \League\CommonMark\Extension\CommonMark\Node\Block\FencedCode::class,
                        new \App\Support\CommonMark\CustomCodeBlockRenderer(),
                        100
                    );

                    $converter = new MarkdownConverter($environment);
                    $markdown = $translation->content;

                    $result = $converter->convert($markdown);

                    $frontMatter = [];
                    if ($result instanceof RenderedContentWithFrontMatter) {
                        $frontMatter = $result->getFrontMatter();
                    }

                    $html = $result->getContent();

                    // Convert GitHub-style alerts: > [!NOTE], > [!WARNING], > [!IMPORTANT]
                    $html = preg_replace_callback(
                        '/<blockquote>\s*<p>\s*\[!(NOTE|WARNING|IMPORTANT)\]([\s\S]*?)<\/blockquote>/i',
                        function ($matches) {
                            $type = strtoupper($matches[1]);
                            $cleanType = strtolower($type);
                            $label = ucfirst($cleanType);
                            $content = trim($matches[2]);
                            
                            return '<div class="markdown-alert markdown-alert-' . $cleanType . '">' .
                                   '<p class="markdown-alert-title">' . $label . '</p>' .
                                   '<p>' . $content . '</div>';
                        },
                        $html
                    );

                    $meta = array_merge([
                        'title' => $translation->title,
                        'description' => $translation->description,
                        'faq' => $translation->faq,
                    ], $frontMatter);

                    return [
                        'html' => $html,
                        'meta' => $meta,
                        'source_modified_at' => $modified,
                    ];
                });

                $data['html'] = $this->localizeLinks($data['html'], $locale);

                return $data;
            }
        }

        // 2. Filesystem fallback
        $path = resource_path("content/{$locale}/{$category}/{$slug}.md");

        if (! File::exists($path)) {
            $path = resource_path("content/en/{$category}/{$slug}.md");
            if (! File::exists($path)) {
                return null;
            }
        }

        $modified = File::lastModified($path);
        $cacheKey = "content_meta_v4_{$locale}_{$category}_{$slug}_{$modified}";

        $data = Cache::rememberForever($cacheKey, function () use ($path) {
            $environment = new Environment([
                'html_input' => 'allow',
            ]);

            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new TableExtension);
            $environment->addExtension(new FrontMatterExtension);

            $environment->addRenderer(
                \League\CommonMark\Extension\CommonMark\Node\Block\FencedCode::class,
                new \App\Support\CommonMark\CustomCodeBlockRenderer(),
                100
            );

            $converter = new MarkdownConverter($environment);
            $markdown = File::get($path);

            $result = $converter->convert($markdown);

            $frontMatter = [];
            if ($result instanceof RenderedContentWithFrontMatter) {
                $frontMatter = $result->getFrontMatter();
            }

            $html = $result->getContent();

            // Convert GitHub-style alerts: > [!NOTE], > [!WARNING], > [!IMPORTANT]
            $html = preg_replace_callback(
                '/<blockquote>\s*<p>\s*\[!(NOTE|WARNING|IMPORTANT)\]([\s\S]*?)<\/blockquote>/i',
                function ($matches) {
                    $type = strtoupper($matches[1]);
                    $cleanType = strtolower($type);
                    $label = ucfirst($cleanType);
                    $content = trim($matches[2]);
                    
                    return '<div class="markdown-alert markdown-alert-' . $cleanType . '">' .
                           '<p class="markdown-alert-title">' . $label . '</p>' .
                           '<p>' . $content . '</div>';
                },
                $html
            );

            return [
                'html' => $html,
                'meta' => $frontMatter,
                'source_modified_at' => File::lastModified($path),
            ];
        });

        $data['html'] = $this->localizeLinks($data['html'], $locale);

        return $data;
    }

    /**
     * Rewrite internal URLs in the parsed HTML to use the active locale.
     * For example, href="/en/php/8.4" -> href="/ru/php/8.4" if active locale is ru.
     */
    protected function localizeLinks(string $html, string $locale): string
    {
        $supported = implode('|', \App\Http\Middleware\SetLocale::SUPPORTED_LOCALES);
        
        return preg_replace_callback(
            '/href=["\']\/(' . $supported . ')(\/|["\']|$)(.*?)["\']/i',
            function ($matches) use ($locale) {
                $quote = str_contains($matches[0], '"') ? '"' : "'";
                $rest = $matches[3] ?? '';
                if ($matches[2] === '/') {
                    return 'href=' . $quote . '/' . $locale . '/' . $rest . $quote;
                } else {
                    return 'href=' . $quote . '/' . $locale . $quote;
                }
            },
            $html
        );
    }
}
