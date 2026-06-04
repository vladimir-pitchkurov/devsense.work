<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LikeController extends Controller
{
    /**
     * Toggle a like or dislike reaction on content.
     */
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'likeable_id' => ['required', 'integer'],
            'likeable_type' => ['required', 'string', 'in:App\Models\Article,article'],
            'is_dislike' => ['required', 'boolean'],
        ]);

        $type = $validated['likeable_type'];
        if ($type === 'article') {
            $type = Article::class;
        }

        // Verify target exists
        $target = $type::find($validated['likeable_id']);
        if (!$target) {
            return response()->json(['error' => 'Content not found.'], 404);
        }

        $userId = Auth::id();
        $isDislike = (bool) $validated['is_dislike'];

        $existingLike = Like::where('user_id', $userId)
            ->where('likeable_type', $type)
            ->where('likeable_id', $validated['likeable_id'])
            ->first();

        if ($existingLike) {
            if ($existingLike->is_dislike === $isDislike) {
                // Clicking same button again: remove reaction
                $existingLike->delete();
                $status = 'removed';
            } else {
                // Switching reaction (e.g. from like to dislike)
                $existingLike->update(['is_dislike' => $isDislike]);
                $status = 'switched';
            }
        } else {
            // Add new reaction
            Like::create([
                'user_id' => $userId,
                'likeable_type' => $type,
                'likeable_id' => $validated['likeable_id'],
                'is_dislike' => $isDislike,
            ]);
            $status = 'added';
        }

        $isAdmin = Auth::check() && Auth::user()->isAdmin();

        return response()->json([
            'success' => true,
            'status' => $status,
            'likes_count' => $target->likesCount(),
            'dislikes_count' => $isAdmin ? $target->dislikesCount() : null,
        ]);
    }
}
