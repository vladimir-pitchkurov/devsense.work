<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Show the profile edit form.
     */
    public function edit()
    {
        $user = Auth::user();
        return view('admin.profile.edit', compact('user'));
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request, MediaUploadService $uploadService)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'slug')->ignore($user->id),
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/i', // URL-friendly slug pattern
            ],
            'job_title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:5120'], // Max 5MB
            'github_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
        ], [
            'slug.regex' => 'The slug must be a valid URL-friendly string (e.g. jane-doe).',
        ]);

        if ($request->hasFile('avatar')) {
            try {
                $avatarUrl = $uploadService->uploadAndStrip($request->file('avatar'));
                // Strip the app.url prefix to save a clean relative path in database
                $parsedPath = parse_url($avatarUrl, PHP_URL_PATH);
                $validated['avatar_path'] = ltrim($parsedPath, '/');
            } catch (\Exception $e) {
                return back()
                    ->withInput()
                    ->withErrors(['avatar' => 'Failed to process avatar: ' . $e->getMessage()]);
            }
        }

        // Remove avatar key if present so it doesn't try to save it to users table directly
        unset($validated['avatar']);

        $user->update($validated);

        return redirect()
            ->route('admin.profile.edit', ['locale' => app()->getLocale()])
            ->with('success', 'Profile updated successfully.');
    }
}
