<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Quiz;
use App\Models\UserQuiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PublicQuizController extends Controller
{
    /**
     * Display a listing of quizzes.
     */
    public function index()
    {
        $locale = app()->getLocale();
        $quizzes = Quiz::with(['translations' => function ($q) use ($locale) {
            $q->where('locale', $locale);
        }])->get();

        $user = Auth::user();
        $completedQuizzes = collect();
        $unlockedBadges = collect();
        $allBadges = Badge::with(['translations' => function ($q) use ($locale) {
            $q->where('locale', $locale);
        }])->get();

        if ($user) {
            $user->load(['badges.translations', 'quizzes']);
            $completedQuizzes = $user->quizzes->pluck('pivot.score', 'id');
            $unlockedBadges = $user->badges;
        }

        return view('quizzes.index', compact('quizzes', 'user', 'completedQuizzes', 'unlockedBadges', 'allBadges'));
    }

    /**
     * Display a specific quiz.
     */
    public function show(string $slug)
    {
        $locale = app()->getLocale();
        $quiz = Quiz::where('slug', $slug)
            ->with([
                'translations' => function ($q) use ($locale) {
                    $q->where('locale', $locale);
                },
                'questions.translations' => function ($q) use ($locale) {
                    $q->where('locale', $locale);
                }
            ])
            ->firstOrFail();

        $lastCompletedTimestamp = null;
        $user = auth()->user();
        if ($user) {
            $userQuiz = \DB::table('user_quizzes')
                ->where('user_id', $user->id)
                ->where('quiz_id', $quiz->id)
                ->first();
            if ($userQuiz) {
                $lastCompletedTimestamp = \Carbon\Carbon::parse($userQuiz->completed_at)->timestamp * 1000;
            }
        }

        return view('quizzes.show', compact('quiz', 'lastCompletedTimestamp'));
    }

    /**
     * Complete a quiz and submit answers.
     */
    public function complete(string $slug): JsonResponse
    {
        $locale = app()->getLocale();
        $request = request();
        $quiz = Quiz::where('slug', $slug)->with('questions.translations')->firstOrFail();
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $answers = $request->input('answers', []);
        $pointsScored = 0;
        $correctAnswers = [];
        $correctIndexes = [];
        $explanations = [];

        foreach ($quiz->questions as $question) {
            $submittedAnswer = isset($answers[$question->id]) ? (int) $answers[$question->id] : -1;
            $isCorrect = ($submittedAnswer === $question->correct_answer_index);
            
            if ($isCorrect) {
                $pointsScored += $question->points;
            }

            $correctAnswers[$question->id] = $isCorrect;
            $correctIndexes[$question->id] = $question->correct_answer_index;
            $explanations[$question->id] = $question->explanation;
        }

        // Determine point diff for UserQuiz pivot and User points
        $existingRecord = $user->quizzes()->where('quiz_id', $quiz->id)->first();
        $oldScore = $existingRecord ? $existingRecord->pivot->score : 0;
        $newScore = max($oldScore, $pointsScored);
        
        $pointsDiff = $newScore - $oldScore;

        if ($pointsDiff > 0) {
            $user->points += $pointsDiff;
            $user->save();
        }

        if ($existingRecord) {
            if ($pointsScored > $oldScore) {
                $user->quizzes()->updateExistingPivot($quiz->id, [
                    'score' => $pointsScored,
                    'completed_at' => now(),
                ]);
            }
        } else {
            $user->quizzes()->attach($quiz->id, [
                'score' => $pointsScored,
                'completed_at' => now(),
            ]);
        }

        // Award Badges
        $unlockedBadges = [];
        $availableBadges = Badge::where('points_required', '<=', $user->points)->get();
        $currentBadgeIds = $user->badges()->pluck('badges.id')->toArray();

        foreach ($availableBadges as $badge) {
            if (!in_array($badge->id, $currentBadgeIds, true)) {
                $user->badges()->attach($badge->id, ['unlocked_at' => now()]);
                
                $badgeTrans = $badge->translate($locale);
                $unlockedBadges[] = [
                    'title' => $badgeTrans?->title ?? $badge->slug,
                    'description' => $badgeTrans?->description ?? '',
                    'image_path' => $badge->image_path,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'points_scored' => $pointsScored,
            'total_points' => $user->points,
            'correct_answers' => $correctAnswers,
            'correct_indexes' => $correctIndexes,
            'explanations' => $explanations,
            'new_badges' => $unlockedBadges,
        ]);
    }
}
