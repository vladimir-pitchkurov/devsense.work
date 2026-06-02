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
        $user->load('pendingProfile');
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
            'is_public' => ['nullable', 'boolean'],
        ], [
            'slug.regex' => 'The slug must be a valid URL-friendly string (e.g. jane-doe).',
        ]);

        $validated['is_public'] = (bool) $request->input('is_public', false);

        if ($request->hasFile('avatar')) {
            try {
                [$avatarUrl, $avatarPath] = $uploadService->uploadAndStrip($request->file('avatar'));
                $validated['avatar_path'] = $avatarPath;
            } catch (\Exception $e) {
                return back()
                    ->withInput()
                    ->withErrors(['avatar' => 'Failed to process avatar: ' . $e->getMessage()]);
            }
        }

        // Remove avatar key if present so it doesn't try to save it to users table directly
        unset($validated['avatar']);

        if ($user->is_approved && !$user->isAdmin()) {
            // Write to pending_user_profiles
            \App\Models\PendingUserProfile::updateOrCreate([
                'user_id' => $user->id,
            ], [
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'job_title' => $validated['job_title'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'avatar_path' => $validated['avatar_path'] ?? $user->avatar_path,
                'github_url' => $validated['github_url'] ?? null,
                'linkedin_url' => $validated['linkedin_url'] ?? null,
                'twitter_url' => $validated['twitter_url'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
            ]);

            // Allow toggling public visibility directly on the live profile
            $user->update(['is_public' => $validated['is_public']]);

            $message = 'Profile updates submitted for moderation. Your live profile remains unchanged until approved.';
        } else {
            // Not approved yet (e.g. newly registered), update users table directly
            $user->update($validated);
            $message = 'Profile updated. Changes will become public once your account is approved.';
        }

        return redirect()
            ->route('admin.profile.edit', ['locale' => app()->getLocale()])
            ->with('success', $message);
    }
}
