<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    /**
     * Show the onboarding screen.
     */
    public function show(Request $request)
    {
        $categories = Category::with('translations')->get();
        $user = Auth::user();

        return view('auth.onboarding', compact('categories', 'user'));
    }

    /**
     * Handle the onboarding screen submission.
     */
    public function submit(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'locale' => ['required', 'string', 'in:en,ru,ua,bg,de,fr,es,it'],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['exists:categories,id'],
        ]);

        $user->update([
            'locale' => $request->input('locale'),
            'notify_articles_quizzes' => $request->boolean('notify_articles_quizzes'),
            'notify_comments' => $request->boolean('notify_comments'),
        ]);

        $user->interests()->sync($request->input('interests', []));

        return redirect('/' . $request->input('locale') . '/admin')
            ->with('success', 'Your onboarding preferences have been saved successfully.');
    }
}
