<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle(Request $request)
    {
        // Store current locale in session to redirect back to it after auth
        $locale = $request->query('locale', app()->getLocale());
        session(['auth_locale' => $locale]);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            $locale = session('auth_locale', 'en');
            return redirect("/{$locale}/login")->withErrors([
                'email' => 'Google authentication failed. Please try again.',
            ]);
        }

        // Check if the user already exists by google_id
        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user) {
            // User exists, log them in
            Auth::login($user);
        } else {
            // Check if user exists by email (link Google ID if they registered normally first)
            $existingUser = User::where('email', $googleUser->getEmail())->first();

            if ($existingUser) {
                // Link Google account and mark email verified since it is verified via Google
                $existingUser->google_id = $googleUser->getId();
                if (!$existingUser->email_verified_at) {
                    $existingUser->email_verified_at = now();
                }
                $existingUser->save();
                
                Auth::login($existingUser);
                $user = $existingUser;
            } else {
                // Register a new user
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'role' => User::ROLE_READER,
                    'slug' => User::generateUniqueSlug($googleUser->getName()),
                    'is_approved' => false,
                ]);

                $user->email_verified_at = now();
                $user->save();

                // Trigger Registered event
                event(new \Illuminate\Auth\Events\Registered($user));

                Auth::login($user);
            }
        }

        $request->session()->regenerate();
        $locale = session('auth_locale', 'en');

        // Handle guest quiz progress sync!
        if ($request->session()->has('pending_quiz')) {
            $pending = $request->session()->get('pending_quiz');
            $slug = $pending['slug'];
            $answers = $pending['answers'];

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
                    $badgeTrans = $badge->translate($locale);
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
            return redirect("/{$locale}/quizzes/{$slug}");
        }

        return redirect("/{$locale}/admin");
    }
}
