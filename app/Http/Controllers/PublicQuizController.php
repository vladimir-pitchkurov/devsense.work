<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Quiz;
use App\Models\UserQuiz;
use App\Models\QuizInProgress;
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

        if ($user && $user->hasVerifiedEmail()) {
            $user->load(['badges.translations', 'quizzes']);
            $completedQuizzes = $user->quizzes->pluck('pivot.score', 'id');
            $unlockedBadges = $user->badges;
        } elseif ($user) {
            $user->load(['quizzes']);
            $completedQuizzes = $user->quizzes->pluck('pivot.score', 'id');
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

        $inProgressData = null;
        if ($user && !$lastCompletedTimestamp) {
            $inProgress = QuizInProgress::where('user_id', $user->id)
                ->where('quiz_id', $quiz->id)
                ->first();
            if ($inProgress) {
                $inProgressData = [
                    'question_index' => $inProgress->question_index,
                    'answers' => $inProgress->answers ?? (object)[],
                ];
            }
        }

        $showResultsData = null;
        if ($user && session('quiz_completed_from_guest') === $quiz->slug) {
            $answers = session('quiz_user_answers', []);
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

            $showResultsData = [
                'points_scored' => $pointsScored,
                'total_points' => $user->points,
                'correct_answers' => $correctAnswers,
                'correct_indexes' => $correctIndexes,
                'explanations' => $explanations,
                'new_badges' => ($user && $user->hasVerifiedEmail()) ? session('quiz_new_badges', []) : [],
            ];
        }

        return view('quizzes.show', compact('quiz', 'lastCompletedTimestamp', 'showResultsData', 'inProgressData'));
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

        if (!$user) {
            session([
                'pending_quiz' => [
                    'slug' => $quiz->slug,
                    'answers' => $answers,
                ]
            ]);

            return response()->json([
                'success' => true,
                'is_guest' => true,
                'points_scored' => $pointsScored,
            ]);
        }

        // Delete in-progress state since quiz is completed
        QuizInProgress::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->delete();

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
        $newBadges = $user->checkAndAwardBadges();
        $unlockedBadges = [];

        if ($user->hasVerifiedEmail()) {
            foreach ($newBadges as $badge) {
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

    /**
     * Save quiz progress in DB for authenticated users.
     */
    public function saveProgress(Request $request, string $slug): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $quiz = Quiz::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'question_index' => 'required|integer|min:0',
            'answers' => 'present|array',
        ]);

        QuizInProgress::updateOrCreate(
            ['user_id' => $user->id, 'quiz_id' => $quiz->id],
            [
                'question_index' => $validated['question_index'],
                'answers' => $validated['answers'],
                'saved_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }
}
