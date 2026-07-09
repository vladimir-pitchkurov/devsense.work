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
    public function index(Request $request)
    {
        Gate::authorize('manage-users');

        $query = User::query();

        // Filter by Role
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        // Filter by Approval Status
        if ($request->filled('approved')) {
            $query->where('is_approved', $request->input('approved') === 'approved');
        }

        // Filter by Blocked Status
        if ($request->filled('status')) {
            $query->where('is_blocked', $request->input('status') === 'suspended');
        }

        // Filter by VIP Status
        if ($request->filled('vip')) {
            $vipFilter = $request->input('vip');
            if ($vipFilter === 'vip') {
                $query->where('is_vip', true);
            } elseif ($vipFilter === 'requested') {
                $query->where('is_vip', false)->whereNotNull('vip_requested_at');
            }
        }

        // Search by Name or Email
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'name_asc');
        switch ($sortBy) {
            case 'name_desc':
                $query->orderByDesc('name');
                break;
            case 'created_at_desc':
                $query->orderByDesc('created_at');
                break;
            case 'created_at_asc':
                $query->orderBy('created_at');
                break;
            case 'xp_desc':
                $query->orderByDesc('points');
                break;
            case 'name_asc':
            default:
                $query->orderBy('name');
                break;
        }

        $users = $query->paginate(15)->withQueryString();

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
            'is_vip' => ['nullable', 'boolean'],
        ]);

        $isBlocked = $request->has('is_blocked') ? (bool) $request->input('is_blocked') : false;
        $isApproved = $request->has('is_approved') ? (bool) $request->input('is_approved') : false;
        $isVip = $request->has('is_vip') ? (bool) $request->input('is_vip') : false;

        $user->update([
            'role' => $validated['role'],
            'is_blocked' => $isBlocked,
            'is_approved' => $isApproved,
            'is_vip' => $isVip,
            'vip_requested_at' => $isVip ? null : $user->vip_requested_at,
        ]);

        return redirect()
            ->route('admin.users.index', ['locale' => app()->getLocale()])
            ->with('success', "User '{$user->name}' updated successfully.");
    }
}
