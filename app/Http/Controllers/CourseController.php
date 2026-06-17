<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseChapter;
use App\Models\UserChapterProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * Display a listing of courses.
     */
    public function index()
    {
        $locale = app()->getLocale();
        $courses = Course::with(['translations' => function ($q) use ($locale) {
            $q->where('locale', $locale);
        }, 'chapters'])->get();

        $user = Auth::user();
        $progress = [];

        if ($user) {
            foreach ($courses as $course) {
                $chapterIds = $course->chapters->pluck('id');
                $completedCount = UserChapterProgress::where('user_id', $user->id)
                    ->whereIn('course_chapter_id', $chapterIds)
                    ->count();
                
                $totalChapters = $course->chapters->count();
                $progress[$course->id] = [
                    'completed' => $completedCount,
                    'total' => $totalChapters,
                    'percentage' => $totalChapters > 0 ? round(($completedCount / $totalChapters) * 100) : 0,
                ];
            }
        }

        return view('courses.index', compact('courses', 'user', 'progress'));
    }

    /**
     * Display the course syllabus.
     */
    public function show(string $course_slug)
    {
        $locale = app()->getLocale();
        $course = Course::where('slug', $course_slug)
            ->with([
                'translations' => function ($q) use ($locale) {
                    $q->where('locale', $locale);
                },
                'chapters' => function ($q) {
                    $q->orderBy('order');
                },
                'chapters.translations' => function ($q) use ($locale) {
                    $q->where('locale', $locale);
                }
            ])->firstOrFail();

        $user = Auth::user();
        $completedChapters = [];

        if ($user) {
            $completedChapters = UserChapterProgress::where('user_id', $user->id)
                ->whereIn('course_chapter_id', $course->chapters->pluck('id'))
                ->pluck('score', 'course_chapter_id')
                ->toArray();
        }

        return view('courses.show', compact('course', 'user', 'completedChapters'));
    }

    /**
     * Display a specific course chapter.
     */
    public function showChapter(string $course_slug, string $chapter_slug)
    {
        $locale = app()->getLocale();
        $course = Course::where('slug', $course_slug)
            ->with([
                'translations' => function ($q) use ($locale) {
                    $q->where('locale', $locale);
                }
            ])->firstOrFail();

        $chapter = CourseChapter::where('course_id', $course->id)
            ->where('slug', $chapter_slug)
            ->with([
                'translations' => function ($q) use ($locale) {
                    $q->where('locale', $locale);
                },
                'quiz.questions.translations' => function ($q) use ($locale) {
                    $q->where('locale', $locale);
                }
            ])->firstOrFail();

        $user = Auth::user();
        $progress = null;

        if ($user) {
            $progress = UserChapterProgress::where('user_id', $user->id)
                ->where('course_chapter_id', $chapter->id)
                ->first();
        }

        // Parse content markdown to HTML
        $markdownService = app(\App\Services\MarkdownContentService::class);
        $htmlContent = $markdownService->parseMarkdown($chapter->translate($locale)?->content_markdown ?? '');

        // Load next chapter if exists
        $nextChapter = CourseChapter::where('course_id', $course->id)
            ->where('order', '>', $chapter->order)
            ->orderBy('order')
            ->first();

        return view('courses.chapter', compact('course', 'chapter', 'htmlContent', 'user', 'progress', 'nextChapter'));
    }

    /**
     * Grade and complete a course chapter.
     */
    public function completeChapter(string $course_slug, string $chapter_slug): JsonResponse
    {
        $locale = app()->getLocale();
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $course = Course::where('slug', $course_slug)->firstOrFail();
        $chapter = CourseChapter::where('course_id', $course->id)
            ->where('slug', $chapter_slug)
            ->with('quiz.questions.translations')
            ->firstOrFail();

        $quiz = $chapter->quiz;
        $pointsScored = 0;
        $correctAnswers = [];
        $correctIndexes = [];
        $explanations = [];
        $answers = request()->input('answers', []);

        if ($quiz) {
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
        }

        // Save progress to user_chapter_progress
        $existingChapterProgress = UserChapterProgress::where('user_id', $user->id)
            ->where('course_chapter_id', $chapter->id)
            ->first();

        $oldChapterScore = $existingChapterProgress ? $existingChapterProgress->score : 0;
        $newChapterScore = max($oldChapterScore, $pointsScored);

        UserChapterProgress::updateOrCreate(
            ['user_id' => $user->id, 'course_chapter_id' => $chapter->id],
            [
                'score' => $newChapterScore,
                'answers' => $answers,
                'completed_at' => now(),
            ]
        );

        // Update user_quizzes pivot if quiz exists (for gamification / badges sync)
        if ($quiz) {
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
}
