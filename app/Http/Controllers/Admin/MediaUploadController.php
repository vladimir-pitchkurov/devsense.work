<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;

class MediaUploadController extends Controller
{
    /**
     * Handle image upload request.
     */
    public function upload(Request $request, MediaUploadService $uploadService)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:5120'], // max 5MB
        ]);

        try {
            $url = $uploadService->uploadAndStrip($request->file('image'));

            return response()->json([
                'success' => true,
                'url' => $url,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }
}
