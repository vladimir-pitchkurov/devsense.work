<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Article;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * Store a new content report/complaint.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'reportable_type' => ['required', 'string', 'in:App\Models\Article,App\Models\User,article,user'],
            'reportable_id' => ['required', 'integer'],
        ]);

        // Map short names if passed
        $type = $validated['reportable_type'];
        if ($type === 'article') {
            $type = Article::class;
        } elseif ($type === 'user') {
            $type = User::class;
        }

        // Verify target exists
        $target = $type::find($validated['reportable_id']);
        if (!$target) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Reported content not found.'], 404);
            }
            return back()->with('error', 'Reported content not found.');
        }

        // Create report
        Report::create([
            'user_id' => Auth::id(), // null if guest
            'reportable_type' => $type,
            'reportable_id' => $validated['reportable_id'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        $message = 'Thank you for your report. We take policy violations seriously and will review your submission shortly.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }
}
