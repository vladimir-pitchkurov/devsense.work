<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of all users.
     */
    public function index()
    {
        Gate::authorize('manage-users');

        $users = User::orderBy('name')->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for editing the user's role and suspension state.
     */
    public function edit(User $user)
    {
        Gate::authorize('manage-users');

        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update the user's role and suspension state.
     */
    public function update(Request $request, User $user)
    {
        Gate::authorize('manage-users');

        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot modify your own role or block status.']);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in([User::ROLE_SUPER_ADMIN, User::ROLE_AUTHOR, User::ROLE_READER])],
            'is_blocked' => ['nullable', 'boolean'],
            'is_approved' => ['nullable', 'boolean'],
        ]);

        $isBlocked = $request->has('is_blocked') ? (bool) $request->input('is_blocked') : false;
        $isApproved = $request->has('is_approved') ? (bool) $request->input('is_approved') : false;

        $user->update([
            'role' => $validated['role'],
            'is_blocked' => $isBlocked,
            'is_approved' => $isApproved,
        ]);

        return redirect()
            ->route('admin.users.index', ['locale' => app()->getLocale()])
            ->with('success', "User '{$user->name}' updated successfully.");
    }
}
