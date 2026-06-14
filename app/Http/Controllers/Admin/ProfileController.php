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
        $categories = \App\Models\Category::with('translations')->get();
        return view('admin.profile.edit', compact('user', 'categories'));
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
            'intro' => ['nullable', 'string'],
            'experience' => ['nullable', 'string'],
            'job_status' => ['nullable', 'string', 'in:seeking,passively_seeking,not_looking'],
            'is_anonymous' => ['nullable', 'boolean'],
            'portfolio' => ['nullable', 'array'],
            'portfolio.*.title' => ['required', 'string', 'max:255'],
            'portfolio.*.description' => ['nullable', 'string'],
            'portfolio.*.existing_images' => ['nullable', 'array'],
            'portfolio.*.existing_images.*' => ['string'],
            'portfolio.*.new_images' => ['nullable', 'array', 'max:3'],
            'portfolio.*.new_images.*' => ['file', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:5120'],
            'locale' => ['sometimes', 'required', 'string', 'in:en,ru,ua,bg,de,fr,es,it'],
            'interests' => ['sometimes', 'nullable', 'array'],
            'interests.*' => ['exists:categories,id'],
        ], [
            'slug.regex' => 'The slug must be a valid URL-friendly string (e.g. jane-doe).',
        ]);

        // Save notification preferences directly on User if provided
        $userUpdate = [];
        if ($request->has('locale')) {
            $userUpdate['locale'] = $validated['locale'];
        }
        if ($request->has('notify_articles_quizzes')) {
            $userUpdate['notify_articles_quizzes'] = $request->boolean('notify_articles_quizzes');
        }
        if ($request->has('notify_comments')) {
            $userUpdate['notify_comments'] = $request->boolean('notify_comments');
        }
        if (count($userUpdate) > 0) {
            $user->update($userUpdate);
        }

        if ($request->has('interests')) {
            $user->interests()->sync($request->input('interests', []));
        }

        unset($validated['locale'], $validated['interests']);

        $validated['is_public'] = (bool) $request->input('is_public', false);
        $validated['is_anonymous'] = (bool) $request->input('is_anonymous', false);

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

        // Process portfolio projects and strip EXIF
        $portfolioData = [];
        if ($request->has('portfolio')) {
            foreach ($request->input('portfolio') as $index => $project) {
                $projectImages = [];

                if (isset($project['existing_images'])) {
                    foreach ($project['existing_images'] as $img) {
                        $projectImages[] = $img;
                    }
                }

                $newFiles = $request->file("portfolio.{$index}.new_images") ?: [];
                $remainingSlots = max(0, 3 - count($projectImages));
                $newFiles = array_slice($newFiles, 0, $remainingSlots);

                foreach ($newFiles as $file) {
                    try {
                        [$imgUrl, $imgPath] = $uploadService->uploadAndStrip($file);
                        $projectImages[] = $imgPath;
                    } catch (\Exception $e) {
                        return back()
                            ->withInput()
                            ->withErrors(["portfolio.{$index}.new_images" => 'Failed to process portfolio image: ' . $e->getMessage()]);
                    }
                }

                $portfolioData[] = [
                    'title' => $project['title'],
                    'description' => $project['description'] ?? '',
                    'images' => $projectImages,
                ];
            }
        }
        $validated['portfolio'] = $portfolioData;

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
                'intro' => $validated['intro'] ?? null,
                'experience' => $validated['experience'] ?? null,
                'job_status' => $validated['job_status'] ?? null,
                'is_anonymous' => $validated['is_anonymous'],
                'portfolio' => $validated['portfolio'],
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
            ->route('admin.profile.edit', ['locale' => $user->locale])
            ->with('success', $message);
    }

    /**
     * Update user password from profile settings.
     */
    public function changePassword(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if ($user->password !== null) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $request->validate($rules);

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        ]);

        return redirect()
            ->route('admin.profile.edit', ['locale' => app()->getLocale()])
            ->with('success', __('ui.auth.password_reset.change_success'));
    }
}
