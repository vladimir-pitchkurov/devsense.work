<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\ActiveMxRecord;
use App\Rules\DisposableEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /**
     * Show the registration form.
     */
    public function showRegistrationForm()
    {
        session(['register_form_loaded_at' => microtime(true)]);

        $num1 = rand(1, 9);
        $num2 = rand(1, 9);
        session(['register_captcha_answer' => $num1 + $num2]);

        return view('auth.register', [
            'num1' => $num1,
            'num2' => $num2,
        ]);
    }

    /**
     * Handle a registration request.
     */
    public function register(Request $request)
    {
        $checkBot = !app()->runningUnitTests() || $request->has('test_bot_protection');

        // 1. Honeypot check
        if ($checkBot && $request->filled('middle_name')) {
            abort(422, 'Bot detected.');
        }

        // 2. Time-lock check
        if ($checkBot) {
            $loadedAt = session('register_form_loaded_at');
            if (!$loadedAt || (microtime(true) - $loadedAt < 3.0)) {
                abort(422, 'Form submitted too quickly.');
            }
        }

        // 3. Validation (including Math CAPTCHA)
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
                new DisposableEmail(),
                new ActiveMxRecord(),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms'    => ['required', 'accepted'],
            'captcha'  => [
                $checkBot ? 'required' : 'nullable',
                $checkBot ? 'integer' : 'nullable',
                function ($attribute, $value, $fail) use ($checkBot) {
                    if ($checkBot) {
                        if ((int)$value !== (int)session('register_captcha_answer')) {
                            $fail(__('ui.auth.math_captcha_error'));
                        }
                    }
                }
            ],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => User::ROLE_READER,
            'slug' => User::generateUniqueSlug($request->name),
            'is_approved' => false,
            'locale' => app()->getLocale(),
        ]);

        event(new \Illuminate\Auth\Events\Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

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

                $user->quizzes()->attach($quiz->id, [
                    'score' => $pointsScored,
                    'completed_at' => now(),
                ]);

                $user->points += $pointsScored;
                $user->save();

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

        session(['needs_onboarding' => true]);

        return redirect('/' . app()->getLocale() . '/admin');
    }
}
