<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Article;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'reportable_type' => ['required', 'string', 'in:App\Models\Article,App\Models\User,App\Models\ArticleSuggestionComment,article,user,comment'],
            'reportable_id' => ['required', 'integer'],
            'type' => ['nullable', 'string', 'in:spam,insult,promo,plagiarism,other'],
            'screenshot' => ['nullable', 'image', 'max:5120'], // Max 5MB
        ]);

        // Map short names if passed
        $type = $validated['reportable_type'];
        if ($type === 'article') {
            $type = Article::class;
        } elseif ($type === 'user') {
            $type = User::class;
        } elseif ($type === 'comment' || $type === 'App\Models\ArticleSuggestionComment') {
            $type = \App\Models\ArticleSuggestionComment::class;
        }

        // Verify target exists
        $target = $type::find($validated['reportable_id']);
        if (!$target) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Reported content not found.'], 404);
            }
            return back()->with('error', 'Reported content not found.');
        }

        $screenshotPath = null;
        if ($request->hasFile('screenshot')) {
            $file = $request->file('screenshot');
            $originalName = $file->getClientOriginalName();
            if (substr_count($originalName, '.') > 1) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Double extensions or multiple dots are not allowed in filenames.'], 422);
                }
                return back()->withErrors(['screenshot' => 'Double extensions or multiple dots are not allowed in filenames.']);
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $filename = Str::uuid() . '.' . $extension;
            
            $environment = app()->environment();
            $envPrefix = in_array($environment, ['local', 'testing', 'dev', 'development'], true) ? 'dev' : 'prod';
            $path = "reports_screenshots/{$envPrefix}/{$filename}";

            if (config('filesystems.default') === 's3' || env('FILESYSTEM_DISK') === 's3') {
                \Illuminate\Support\Facades\Storage::disk('s3')->put(
                    $path,
                    file_get_contents($file->getRealPath()),
                    'public'
                );
                $screenshotPath = $path;
            } else {
                $screenshotPath = $file->storeAs("reports_screenshots/{$envPrefix}", $filename, 'public');
            }
        }

        // Create report
        Report::create([
            'user_id' => Auth::id(), // null if guest
            'reportable_type' => $type,
            'reportable_id' => $validated['reportable_id'],
            'type' => $validated['type'] ?? 'other',
            'reason' => $validated['reason'],
            'screenshot_path' => $screenshotPath,
            'status' => 'pending',
        ]);

        $message = __('ui.reports.success_message') ?: 'Thank you for your report. We take policy violations seriously and will review your submission shortly.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }
}
