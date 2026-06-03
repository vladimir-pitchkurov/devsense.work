<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use Illuminate\Http\JsonResponse;

class FeatureController extends Controller
{
    /**
     * Display features list for roadmap voting.
     */
    public function index()
    {
        $user = auth()->user();
        
        $features = Feature::with('votes')
            ->get()
            ->sortByDesc(fn ($f) => $f->totalVotesWeight());

        // Calculate current user's voting weight based on XP: 1 + floor(points / 100)
        $userWeight = $user ? (1 + floor($user->points / 100)) : 1;

        return view('features.index', compact('features', 'user', 'userWeight'));
    }

    /**
     * Vote (or remove vote) for a feature.
     */
    public function vote(Feature $feature): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $vote = $feature->votes()->where('user_id', $user->id)->first();

        if ($vote) {
            $vote->delete();
            $voted = false;
        } else {
            $feature->votes()->create([
                'user_id' => $user->id,
            ]);
            $voted = true;
        }

        return response()->json([
            'success' => true,
            'voted' => $voted,
            'total_count' => $feature->totalVotesCount(),
            'total_weight' => $feature->totalVotesWeight(),
        ]);
    }
}
