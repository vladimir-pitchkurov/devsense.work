<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Read-only content access optimized for a public API:
 * - meta + plaintext excerpt by default
 * - full Markdown content via a dedicated endpoint
 */
class PublicContentApiService
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_LOCALES = ['en', 'ru', 'ua', 'bg'];

    /**
     * @var list<string>
     */
    private const SUPPORTED_CATEGORIES = ['php', 'tools', 'microservices', 'architecture', 'jobs'];

    /**
     * @return list<array{locale:string,category:string,slug:string,modified:int,path:string}>
     */
    public function scanIndex(): array
    {
        $entries = [];

        // 1. Fetch articles from database
        try {
            $dbArticles = \App\Models\Article::with(['category', 'translations'])
                ->where('is_published', true)
                ->get();

            foreach ($dbArticles as $article) {
                $categorySlug = $article->category?->slug;
                if (!$categorySlug || !in_array($categorySlug, self::SUPPORTED_CATEGORIES, true)) {
                    continue;
                }

                foreach ($article->translations as $translation) {
                    if (!in_array($translation->locale, self::SUPPORTED_LOCALES, true)) {
                        continue;
                    }

                    $modified = max(
                        $article->updated_at?->timestamp ?? 0,
                        $translation->updated_at?->timestamp ?? 0
                    );

                    $entries[] = [
                        'locale' => $translation->locale,
                        'category' => $categorySlug,
                        'slug' => $article->slug,
                        'modified' => $modified,
                        'path' => 'db://' . $categorySlug . '/' . $article->slug . '/' . $translation->locale,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Safe fallback during migrations / testing
        }

        // 2. Fetch fallback markdown files from filesystem
        foreach (self::SUPPORTED_LOCALES as $locale) {
            foreach (self::SUPPORTED_CATEGORIES as $category) {
                $dir = resource_path("content/{$locale}/{$category}");
                if (! File::isDirectory($dir)) {
                    continue;
                }

                /** @var \Symfony\Component\Finder\SplFileInfo $file */
                foreach (File::files($dir) as $file) {
                    if ($file->getExtension() !== 'md') {
                        continue;
                    }
                    $slug = $file->getBasename('.md');

                    // Skip duplicates from DB
                    $exists = false;
                    foreach ($entries as $entry) {
                        if ($entry['locale'] === $locale && $entry['category'] === $category && $entry['slug'] === $slug) {
                            $exists = true;
                            break;
                        }
                    }

                    if (!$exists) {
                        $entries[] = [
                            'locale' => $locale,
                            'category' => $category,
                            'slug' => $slug,
                            'modified' => $file->getMTime(),
                            'path' => $file->getPathname(),
                        ];
                    }
                }
            }
        }

        usort($entries, fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $entries;
    }

    private function indexCacheKey(): string
    {
        $max = 0;

        // 1. Database maximum modified timestamp
        try {
            $maxDbArticle = \App\Models\Article::max('updated_at');
            if ($maxDbArticle) {
                $max = max($max, strtotime($maxDbArticle));
            }
            $maxDbTranslation = \App\Models\ArticleTranslation::max('updated_at');
            if ($maxDbTranslation) {
                $max = max($max, strtotime($maxDbTranslation));
            }
        } catch (\Throwable $e) {
            // Safe fallback during migrations
        }

        // 2. Filesystem maximum modified timestamp
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $dir = resource_path("content/{$locale}");
            if (! File::isDirectory($dir)) {
                continue;
            }
            foreach (File::allFiles($dir) as $file) {
                $max = max($max, $file->getMTime());
            }
        }

        return "public_content_api_index_v2_{$max}";
    }

    /**
     * Changes whenever any markdown file mtime changes.
     */
    public function indexVersion(): string
    {
        return $this->indexCacheKey();
    }

    /**
     * @return list<array{locale:string,category:string,slug:string,modified:int,path:string}>
     */
    private function index(): array
    {
        return Cache::remember($this->indexCacheKey(), now()->addMinutes(10), function (): array {
            return $this->scanIndex();
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function parseFrontMatter(string $markdown): array
    {
        if (! str_starts_with($markdown, "---\n")) {
            return [];
        }

        $end = strpos($markdown, "\n---", 4);
        if ($end === false) {
            return [];
        }

        $yaml = substr($markdown, 4, $end - 4);
        try {
            $data = Yaml::parse($yaml);
        } catch (\Throwable) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    private function stripFrontMatter(string $markdown): string
    {
        if (! str_starts_with($markdown, "---\n")) {
            return $markdown;
        }

        $end = strpos($markdown, "\n---", 4);
        if ($end === false) {
            return $markdown;
        }

        $after = strpos($markdown, "\n", $end + 4);
        if ($after === false) {
            return '';
        }

        return ltrim(substr($markdown, $after + 1));
    }

    /**
     * @return list<array{id:string,text:string,level:int}>
     */
    private function extractHeadings(string $markdown): array
    {
        $body = $this->stripFrontMatter($markdown);

        $headings = [];

        // Pattern used across this codebase:
        // <a id="foo"></a>
        // ## Heading title
        $pattern = '/<a\s+id="([^"]+)"><\/a>\s*\R(#{2,3})\s+(.+)\R/m';
        if (preg_match_all($pattern, $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $level = strlen($m[2]);
                $text = trim($m[3]);
                $headings[] = [
                    'id' => $m[1],
                    'text' => $text,
                    'level' => $level,
                ];
            }
        }

        return $headings;
    }

    private function plaintextExcerptFromHtml(string $html, int $maxChars = 1100): string
    {
        $text = trim(html_entity_decode(strip_tags($html)));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        $cut = mb_substr($text, 0, $maxChars);
        $pos = max(
            mb_strrpos($cut, '. ') ?: 0,
            mb_strrpos($cut, '…') ?: 0,
            mb_strrpos($cut, '! ') ?: 0,
            mb_strrpos($cut, '? ') ?: 0,
        );

        if ($pos > 200) {
            return rtrim(mb_substr($cut, 0, $pos + 1));
        }

        return rtrim($cut).'…';
    }

    private function searchTextCacheKey(string $locale, string $category, string $slug, int $modified): string
    {
        return "public_content_api_search_text_v1_{$locale}_{$category}_{$slug}_{$modified}";
    }

    /**
     * @return array{title:string,description:string,text:string,excerpt:string}
     */
    private function searchableText(string $locale, string $category, string $slug, MarkdownContentService $markdownService): array
    {
        $doc = $this->getMarkdownDocument($locale, $category, $slug);
        $modified = $doc ? (int) $doc['modified'] : 0;

        return Cache::rememberForever(
            $this->searchTextCacheKey($locale, $category, $slug, $modified),
            function () use ($locale, $category, $slug, $doc, $markdownService): array {
                $meta = $doc['meta'] ?? [];

                $parsed = $markdownService->getParsedContent($locale, $category, $slug);
                $html = is_array($parsed) ? ($parsed['html'] ?? '') : '';

                $title = is_string($meta['title'] ?? null) ? (string) $meta['title'] : '';
                $description = is_string($meta['description'] ?? null) ? (string) $meta['description'] : '';
                $text = $this->plaintextExcerptFromHtml((string) $html, 10_000);
                $excerpt = $this->plaintextExcerptFromHtml((string) $html, 1100);

                return [
                    'title' => $title,
                    'description' => $description,
                    'text' => $text,
                    'excerpt' => $excerpt,
                ];
            }
        );
    }

    public function isSupportedLocale(?string $locale): bool
    {
        return $locale === null || in_array($locale, self::SUPPORTED_LOCALES, true);
    }

    public function isSupportedCategory(?string $category): bool
    {
        return $category === null || in_array($category, self::SUPPORTED_CATEGORIES, true);
    }

    /**
     * @return array{locale:string,category:string,slug:string}|null
     */
    public function parseId(string $id): ?array
    {
        $parts = explode(':', $id);
        if (count($parts) !== 3) {
            return null;
        }

        [$locale, $category, $slug] = $parts;
        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            return null;
        }
        if (! in_array($category, self::SUPPORTED_CATEGORIES, true)) {
            return null;
        }
        if ($slug === '' || str_contains($slug, '/')) {
            return null;
        }

        return compact('locale', 'category', 'slug');
    }

    public function makeId(string $locale, string $category, string $slug): string
    {
        return "{$locale}:{$category}:{$slug}";
    }

    /**
     * Get the canonical URL for a content page.
     */
    public function canonicalUrl(string $locale, string $category, string $slug): string
    {
        $base = rtrim((string) config('app.url', 'https://devsense.work'), '/');

        return match ($category) {
            'php' => "{$base}/{$locale}/php/{$slug}",
            'tools' => "{$base}/{$locale}/tools/{$slug}",
            'microservices' => $slug === 'index'
                ? "{$base}/{$locale}/microservices"
                : "{$base}/{$locale}/microservices/{$slug}",
            'architecture' => "{$base}/{$locale}/architecture/{$slug}",
            'jobs' => "{$base}/{$locale}/jobs/{$slug}",
            default => "{$base}/{$locale}",
        };
    }

    /**
     * @return array{path:string,markdown:string,modified:int,meta:array<string,mixed>}|null
     */
    public function getMarkdownDocument(string $locale, string $category, string $slug): ?array
    {
        // 1. Query the database first
        $article = \App\Models\Article::where('slug', $slug)
            ->whereHas('category', function ($q) use ($category) {
                $q->where('slug', $category);
            })
            ->first();

        if ($article) {
            $translation = $article->translations()->where('locale', $locale)->first();
            if (!$translation) {
                $translation = $article->translations()->where('locale', 'en')->first();
            }

            if ($translation) {
                $meta = [
                    'title' => $translation->title,
                    'description' => $translation->description,
                    'faq' => $translation->faq,
                    'published' => $article->published_at?->format('Y-m-d') ?? $article->created_at?->format('Y-m-d'),
                ];

                $yaml = Yaml::dump($meta);
                $markdown = "---\n" . $yaml . "---\n" . $translation->content;
                $modified = max($article->updated_at?->timestamp ?? 0, $translation->updated_at?->timestamp ?? 0);

                return [
                    'path' => 'db://' . $category . '/' . $slug . '/' . $translation->locale,
                    'markdown' => $markdown,
                    'modified' => $modified,
                    'meta' => $meta,
                ];
            }
        }

        // 2. Filesystem fallback
        $path = resource_path("content/{$locale}/{$category}/{$slug}.md");
        if (! File::exists($path)) {
            $path = resource_path("content/en/{$category}/{$slug}.md");
            if (! File::exists($path)) {
                return null;
            }
            $locale = 'en';
        }

        $markdown = File::get($path);

        return [
            'path' => $path,
            'markdown' => $markdown,
            'modified' => File::lastModified($path),
            'meta' => $this->parseFrontMatter($markdown),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listArticles(?string $locale, ?string $category, int $limit, ?string $cursor, MarkdownContentService $markdownService): array
    {
        $offset = 0;
        if (is_string($cursor) && $cursor !== '') {
            $decoded = json_decode(base64_decode($cursor, true) ?: '', true);
            if (is_array($decoded) && isset($decoded['offset']) && is_int($decoded['offset']) && $decoded['offset'] >= 0) {
                $offset = $decoded['offset'];
            }
        }

        $filtered = array_values(array_filter($this->index(), function (array $e) use ($locale, $category): bool {
            if ($locale !== null && $e['locale'] !== $locale) {
                return false;
            }
            if ($category !== null && $e['category'] !== $category) {
                return false;
            }
            return true;
        }));

        $slice = array_slice($filtered, $offset, $limit);

        $out = [];
        foreach ($slice as $e) {
            $doc = $this->getMarkdownDocument($e['locale'], $e['category'], $e['slug']);
            $meta = $doc['meta'] ?? [];

            $parsed = $markdownService->getParsedContent($e['locale'], $e['category'], $e['slug']);
            $html = is_array($parsed) ? ($parsed['html'] ?? '') : '';

            $title = is_string($meta['title'] ?? null) ? (string) $meta['title'] : Str::headline(str_replace('-', ' ', $e['slug']));
            $description = is_string($meta['description'] ?? null) ? (string) $meta['description'] : '';

            $out[] = [
                'id' => $this->makeId($e['locale'], $e['category'], $e['slug']),
                'locale' => $e['locale'],
                'category' => $e['category'],
                'slug' => $e['slug'],
                'title' => $title,
                'description' => $description,
                'published_at' => is_string($meta['published'] ?? null) ? (string) $meta['published'] : null,
                'updated_at' => gmdate('c', (int) ($doc['modified'] ?? $e['modified'])),
                'canonical_url' => $this->canonicalUrl($e['locale'], $e['category'], $e['slug']),
                'excerpt' => $this->plaintextExcerptFromHtml((string) $html),
                'headings' => $doc ? $this->extractHeadings((string) $doc['markdown']) : [],
            ];
        }

        $nextCursor = null;
        if ($offset + $limit < count($filtered)) {
            $nextCursor = base64_encode(json_encode(['offset' => $offset + $limit], JSON_THROW_ON_ERROR));
        }

        return [
            'data' => $out,
            'pagination' => [
                'next_cursor' => $nextCursor,
                'limit' => $limit,
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getArticle(string $id, MarkdownContentService $markdownService): ?array
    {
        $parsedId = $this->parseId($id);
        if (! $parsedId) {
            return null;
        }

        $doc = $this->getMarkdownDocument($parsedId['locale'], $parsedId['category'], $parsedId['slug']);
        if (! $doc) {
            return null;
        }

        $meta = $doc['meta'];
        $parsed = $markdownService->getParsedContent($parsedId['locale'], $parsedId['category'], $parsedId['slug']);
        $html = is_array($parsed) ? ($parsed['html'] ?? '') : '';

        $title = is_string($meta['title'] ?? null) ? (string) $meta['title'] : Str::headline(str_replace('-', ' ', $parsedId['slug']));
        $description = is_string($meta['description'] ?? null) ? (string) $meta['description'] : '';

        $alternates = [];
        foreach (self::SUPPORTED_LOCALES as $altLocale) {
            $altPath = resource_path("content/{$altLocale}/{$parsedId['category']}/{$parsedId['slug']}.md");
            if (File::exists($altPath)) {
                $alternates[] = [
                    'locale' => $altLocale,
                    'url' => $this->canonicalUrl($altLocale, $parsedId['category'], $parsedId['slug']),
                ];
            }
        }

        return [
            'id' => $id,
            'locale' => $parsedId['locale'],
            'category' => $parsedId['category'],
            'slug' => $parsedId['slug'],
            'title' => $title,
            'description' => $description,
            'published_at' => is_string($meta['published'] ?? null) ? (string) $meta['published'] : null,
            'updated_at' => gmdate('c', (int) $doc['modified']),
            'canonical_url' => $this->canonicalUrl($parsedId['locale'], $parsedId['category'], $parsedId['slug']),
            'excerpt' => $this->plaintextExcerptFromHtml((string) $html),
            'headings' => $this->extractHeadings((string) $doc['markdown']),
            'alternates' => $alternates,
        ];
    }

    /**
     * @return array{format:string,content:string,canonical_url:string,updated_at:string,id:string}|null
     */
    public function getArticleContent(string $id, string $format): ?array
    {
        $parsedId = $this->parseId($id);
        if (! $parsedId) {
            return null;
        }

        $doc = $this->getMarkdownDocument($parsedId['locale'], $parsedId['category'], $parsedId['slug']);
        if (! $doc) {
            return null;
        }

        if ($format !== 'markdown') {
            return null;
        }

        // Keep payload bounded for public API usage.
        $maxChars = 200_000;
        $content = (string) $doc['markdown'];
        if (mb_strlen($content) > $maxChars) {
            $content = mb_substr($content, 0, $maxChars)."\n\n<!-- truncated -->\n";
        }

        return [
            'id' => $id,
            'format' => 'markdown',
            'content' => $content,
            'canonical_url' => $this->canonicalUrl($parsedId['locale'], $parsedId['category'], $parsedId['slug']),
            'updated_at' => gmdate('c', (int) $doc['modified']),
        ];
    }

    /**
     * @return list<string>
     */
    private function highlights(string $haystack, string $needle, int $max = 3): array
    {
        $needle = trim($needle);
        if ($needle === '') {
            return [];
        }

        $h = mb_strtolower($haystack);
        $n = mb_strtolower($needle);

        $pos = 0;
        $snippets = [];
        while (count($snippets) < $max) {
            $found = mb_strpos($h, $n, $pos);
            if ($found === false) {
                break;
            }

            $start = max(0, $found - 80);
            $end = min(mb_strlen($haystack), $found + mb_strlen($needle) + 120);
            $snippet = trim(mb_substr($haystack, $start, $end - $start));
            if ($start > 0) {
                $snippet = '…'.$snippet;
            }
            if ($end < mb_strlen($haystack)) {
                $snippet .= '…';
            }

            $snippets[] = $snippet;
            $pos = $found + mb_strlen($needle);
        }

        return array_values(array_unique($snippets));
    }

    /**
     * @return array{query:string,data:list<array<string,mixed>>}
     */
    public function search(string $query, ?string $locale, ?string $category, int $limit, MarkdownContentService $markdownService): array
    {
        $q = trim($query);
        if ($q === '') {
            return ['query' => $query, 'data' => []];
        }

        $results = [];
        foreach ($this->index() as $e) {
            if ($locale !== null && $e['locale'] !== $locale) {
                continue;
            }
            if ($category !== null && $e['category'] !== $category) {
                continue;
            }

            $searchable = $this->searchableText($e['locale'], $e['category'], $e['slug'], $markdownService);
            $title = $searchable['title'];
            $description = $searchable['description'];
            $text = $searchable['text'];
            $excerpt = $searchable['excerpt'];

            $hay = mb_strtolower($title."\n".$description."\n".$text);
            $needle = mb_strtolower($q);

            $count = substr_count($hay, $needle);
            if ($count === 0) {
                continue;
            }

            $score = min(1.0, 0.2 + $count / 10);

            $id = $this->makeId($e['locale'], $e['category'], $e['slug']);
            $results[] = [
                'id' => $id,
                'title' => $title !== '' ? $title : Str::headline(str_replace('-', ' ', $e['slug'])),
                'canonical_url' => $this->canonicalUrl($e['locale'], $e['category'], $e['slug']),
                'excerpt' => $excerpt,
                'highlights' => $this->highlights($text, $q, 3),
                'score' => $score,
            ];
        }

        usort($results, fn (array $a, array $b): int => ($b['score'] <=> $a['score']));

        return [
            'query' => $query,
            'data' => array_slice($results, 0, $limit),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function manifest(): array
    {
        return [
            'name' => 'DevSense Content API',
            'version' => 'v1',
            'website' => (string) config('app.url', 'https://devsense.work'),
            'canonical_host' => parse_url((string) config('app.url', 'https://devsense.work'), PHP_URL_HOST) ?: 'devsense.work',
            'locales' => self::SUPPORTED_LOCALES,
            'categories' => self::SUPPORTED_CATEGORIES,
            'citation_policy' => [
                'required' => true,
                'preferred' => 'link',
                'text' => 'When using DevSense content, include the canonical_url in citations.',
            ],
            'default_behavior' => [
                'list_and_search_return' => 'meta+excerpt',
                'full_content_endpoint' => '/api/v1/articles/{id}/content?format=markdown',
            ],
        ];
    }

    /**
     * @return array{required:bool,preferred:string,text:string,canonical_url:string}
     */
    public function citation(string $canonicalUrl): array
    {
        return [
            'required' => true,
            'preferred' => 'link',
            'text' => 'When using DevSense content, include the canonical_url in citations.',
            'canonical_url' => $canonicalUrl,
        ];
    }
}

