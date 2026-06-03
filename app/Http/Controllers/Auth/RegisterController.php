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
        return view('auth.register');
    }

    /**
     * Handle a registration request.
     */
    public function register(Request $request)
    {
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
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => User::ROLE_READER,
            'slug' => Str::slug($request->name),
            'is_approved' => false,
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

        return redirect('/' . app()->getLocale() . '/admin');
    }
}
