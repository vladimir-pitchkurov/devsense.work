<x-layout title="Admin - User Management | DevSense" description="Manage user accounts, roles, and suspensions">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">User Management</h1>
        <div class="header-actions" style="display: flex; gap: 0.5rem;">
            <a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Dashboard
            </a>
            <a href="{{ route('admin.articles.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Articles
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="admin-alert admin-alert--success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="admin-alert admin-alert--danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="admin-card">
        <!-- Filters panel -->
        <div class="admin-filters-card" style="margin-bottom: 1.5rem; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); padding: 1.25rem; border-radius: 0.75rem;">
            <form action="{{ route('admin.users.index', ['locale' => app()->getLocale()]) }}" method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                <div style="flex: 1 1 200px;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem;">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or Email..." class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                </div>
                <div style="flex: 1 1 150px;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem;">Role</label>
                    <select name="role" class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                        <option value="">All Roles</option>
                        <option value="{{ \App\Models\User::ROLE_SUPER_ADMIN }}" {{ request('role') === \App\Models\User::ROLE_SUPER_ADMIN ? 'selected' : '' }}>Admin</option>
                        <option value="{{ \App\Models\User::ROLE_AUTHOR }}" {{ request('role') === \App\Models\User::ROLE_AUTHOR ? 'selected' : '' }}>Author</option>
                        <option value="{{ \App\Models\User::ROLE_READER }}" {{ request('role') === \App\Models\User::ROLE_READER ? 'selected' : '' }}>Reader</option>
                    </select>
                </div>
                <div style="flex: 1 1 150px;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem;">Approved Status</label>
                    <select name="approved" class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                        <option value="">All Statuses</option>
                        <option value="approved" {{ request('approved') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="pending" {{ request('approved') === 'pending' ? 'selected' : '' }}>Pending</option>
                    </select>
                </div>
                <div style="flex: 1 1 150px;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem;">Suspension Status</label>
                    <select name="status" class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    </select>
                </div>
                <div style="flex: 1 1 150px;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem;">VIP Status</label>
                    <select name="vip" class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                        <option value="">All Users</option>
                        <option value="vip" {{ request('vip') === 'vip' ? 'selected' : '' }}>VIP Users</option>
                        <option value="requested" {{ request('vip') === 'requested' ? 'selected' : '' }}>Requested VIP</option>
                    </select>
                </div>
                <div style="flex: 1 1 150px;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem;">Sort By</label>
                    <select name="sort_by" class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                        <option value="name_asc" {{ request('sort_by') === 'name_asc' ? 'selected' : '' }}>Name (A-Z)</option>
                        <option value="name_desc" {{ request('sort_by') === 'name_desc' ? 'selected' : '' }}>Name (Z-A)</option>
                        <option value="created_at_desc" {{ request('sort_by') === 'created_at_desc' ? 'selected' : '' }}>Newest Registered</option>
                        <option value="created_at_asc" {{ request('sort_by') === 'created_at_asc' ? 'selected' : '' }}>Oldest Registered</option>
                        <option value="xp_desc" {{ request('sort_by') === 'xp_desc' ? 'selected' : '' }}>Highest XP Points</option>
                    </select>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="admin-btn admin-btn--primary" style="padding: 0.5rem 1rem;">Filter</button>
                    <a href="{{ route('admin.users.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary" style="padding: 0.5rem 1rem;">Reset</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Verification</th>
                        <th>Role</th>
                        <th>Approved Status</th>
                        <th>Suspension Status</th>
                        <th>Registered</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <strong>{{ $user->name }}</strong>
                                    @if(!$user->is_vip && $user->vip_requested_at)
                                        <span class="admin-badge" style="background: rgba(251, 191, 36, 0.15); border: 1px solid #fbbf24; color: #fbbf24; font-size: 0.75rem; padding: 0.1rem 0.4rem; border-radius: 0.25rem;">
                                            VIP Request
                                        </span>
                                    @endif
                                </div>
                                @if($user->isAuthor() && $user->is_approved && !$user->is_blocked)
                                    <small style="display: block; margin-top: 0.25rem;">
                                        <a href="{{ route('authors.show', ['locale' => app()->getLocale(), 'slug' => $user->slug]) }}" target="_blank" style="color: var(--primary-color); text-decoration: underline;">
                                            View Profile
                                        </a>
                                    </small>
                                @endif
                            </td>
                            <td><code>{{ $user->email }}</code></td>
                            <td>
                                @if($user->email_verified_at)
                                    <span class="admin-badge admin-badge--published">Verified</span>
                                @else
                                    <span class="admin-badge admin-badge--draft">Unverified</span>
                                @endif
                            </td>
                            <td>
                                @if($user->role === \App\Models\User::ROLE_SUPER_ADMIN)
                                    <span class="admin-badge admin-badge--ai">Admin</span>
                                @elseif($user->role === \App\Models\User::ROLE_AUTHOR)
                                    <span class="admin-badge admin-badge--category">Author</span>
                                @else
                                    <span class="admin-badge admin-badge--none">Reader</span>
                                @endif
                            </td>
                            <td>
                                @if($user->is_approved)
                                    <span class="admin-badge admin-badge--published">Approved</span>
                                @else
                                    <span class="admin-badge admin-badge--draft">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if($user->is_blocked)
                                    <span class="admin-badge admin-badge--danger">Suspended</span>
                                @else
                                    <span class="admin-badge admin-badge--published">Active</span>
                                @endif
                            </td>
                            <td>{{ $user->created_at->diffForHumans() }}</td>
                            <td class="text-right actions-cell">
                                <div class="action-buttons" style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                                    @if(!$user->is_approved && $user->id !== auth()->id())
                                        @if($user->email_verified_at)
                                            <form action="{{ route('admin.moderation.authors.approve', ['locale' => app()->getLocale(), 'user' => $user->id]) }}" method="POST" style="display: inline; margin: 0;">
                                                @csrf
                                                <button type="submit" class="admin-btn admin-btn--primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: #10b981; border: none; color: white;">
                                                    Approve
                                                </button>
                                            </form>
                                        @else
                                            <button class="admin-btn admin-btn--secondary" disabled style="padding: 0.4rem 0.8rem; font-size: 0.85rem; opacity: 0.5; cursor: not-allowed;" title="Email verification is required before approval.">
                                                Approve
                                            </button>
                                        @endif
                                    @endif

                                    @if($user->id !== auth()->id())
                                        <a href="{{ route('admin.users.edit', ['locale' => app()->getLocale(), 'user' => $user->id]) }}" 
                                           class="admin-btn admin-btn--secondary" 
                                           title="Edit User" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                            Edit
                                        </a>
                                    @else
                                        <span class="text-muted" style="font-size: 0.85rem;">Self</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">
            {{ $users->links() }}
        </div>
    </div>
</div>
</x-layout>
