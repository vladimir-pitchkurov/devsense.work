<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MarkdownContentService;
use App\Services\PublicContentApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticlesController extends Controller
{
    public function __construct(
        private readonly PublicContentApiService $content,
        private readonly MarkdownContentService $markdown,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $locale = $request->query('locale');
        $category = $request->query('category');

        if (! is_string($locale) && $locale !== null) {
            return response()->json(['error' => ['code' => 'bad_request', 'message' => 'Invalid locale']], 400);
        }
        if (! is_string($category) && $category !== null) {
            return response()->json(['error' => ['code' => 'bad_request', 'message' => 'Invalid category']], 400);
        }
        if (! $this->content->isSupportedLocale($locale)) {
            return response()->json(['error' => ['code' => 'bad_request', 'message' => 'Unsupported locale']], 400);
        }
        if (! $this->content->isSupportedCategory($category)) {
            return response()->json(['error' => ['code' => 'bad_request', 'message' => 'Unsupported category']], 400);
        }

        $limit = (int) $request->integer('limit', 20);
        $limit = max(1, min(50, $limit));

        $cursor = $request->query('cursor');
        if (! is_string($cursor) && $cursor !== null) {
            return response()->json(['error' => ['code' => 'bad_request', 'message' => 'Invalid cursor']], 400);
        }

        $etag = sha1(implode('|', [
            $this->content->indexVersion(),
            'articles.index',
            (string) $locale,
            (string) $category,
            (string) $limit,
            (string) $cursor,
        ]));
        $probe = response()
            ->json([])
            ->setEtag($etag)
            ->header('Cache-Control', 'public, max-age=60');
        if ($probe->isNotModified($request)) {
            return $probe;
        }

        $payload = $this->content->listArticles($locale, $category, $limit, $cursor, $this->markdown);

        foreach ($payload['data'] as $i => $row) {
            if (is_array($row) && isset($row['canonical_url']) && is_string($row['canonical_url'])) {
                $payload['data'][$i]['citation'] = $this->content->citation($row['canonical_url']);
            }
        }

        return response()
            ->json($payload)
            ->setEtag($etag)
            ->header('Cache-Control', 'public, max-age=60');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $article = $this->content->getArticle($id, $this->markdown);
        if (! $article) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'Article not found', 'details' => ['id' => $id]]], 404);
        }

        $article['citation'] = $this->content->citation((string) $article['canonical_url']);

        $etag = sha1($article['id'].'|'.$article['updated_at']);
        $response = response()
            ->json(['data' => $article])
            ->setEtag($etag)
            ->header('Cache-Control', 'public, max-age=300');
        $response->isNotModified($request);

        return $response;
    }

    public function content(Request $request, string $id): JsonResponse
    {
        $format = $request->query('format', 'markdown');
        if (! is_string($format)) {
            return response()->json(['error' => ['code' => 'bad_request', 'message' => 'Invalid format']], 400);
        }

        $content = $this->content->getArticleContent($id, $format);
        if (! $content) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'Article content not found', 'details' => ['id' => $id]]], 404);
        }

        $content['citation'] = $this->content->citation((string) $content['canonical_url']);

        $etag = sha1($content['id'].'|'.$content['updated_at'].'|'.$content['format']);
        $response = response()
            ->json(['data' => $content])
            ->setEtag($etag)
            ->header('Cache-Control', 'public, max-age=300');
        $response->isNotModified($request);

        return $response;
    }
}

