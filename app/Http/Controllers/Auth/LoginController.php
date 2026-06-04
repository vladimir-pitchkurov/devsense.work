<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle authentication attempt.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            if ($request->session()->has('pending_quiz')) {
                $pending = $request->session()->get('pending_quiz');
                $slug = $pending['slug'];
                $answers = $pending['answers'];

                $user = Auth::user();
                $quiz = \App\Models\Quiz::where('slug', $slug)->first();
                if ($quiz) {
                    $pointsScored = 0;
                    foreach ($quiz->questions as $question) {
                        $submittedAnswer = isset($answers[$question->id]) ? (int) $answers[$question->id] : -1;
                        if ($submittedAnswer === $question->correct_answer_index) {
                            $pointsScored += $question->points;
                        }
                    }

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

                    $newBadges = $user->checkAndAwardBadges();
                    $unlockedBadges = [];
                    foreach ($newBadges as $badge) {
                        $badgeTrans = $badge->translate(app()->getLocale());
                        $unlockedBadges[] = [
                            'title' => $badgeTrans?->title ?? $badge->slug,
                            'description' => $badgeTrans?->description ?? '',
                            'image_path' => $badge->image_path,
                        ];
                    }

                    $request->session()->flash('quiz_completed_from_guest', $slug);
                    $request->session()->flash('quiz_user_answers', $answers);
                    $request->session()->flash('quiz_new_badges', $unlockedBadges);
                }

                $request->session()->forget('pending_quiz');
                return redirect('/' . app()->getLocale() . '/quizzes/' . $slug);
            }

            return redirect()->intended('/' . app()->getLocale() . '/admin');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/' . app()->getLocale());
    }
}
