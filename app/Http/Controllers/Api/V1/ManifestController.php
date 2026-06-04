<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PublicContentApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManifestController extends Controller
{
    public function __construct(private readonly PublicContentApiService $content)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $data = $this->content->manifest();
        $etag = sha1(json_encode($data, JSON_THROW_ON_ERROR));

        $response = response()
            ->json($data)
            ->setEtag($etag)
            ->header('Cache-Control', 'public, max-age=600');

        $response->isNotModified($request);

        return $response;
    }
}

