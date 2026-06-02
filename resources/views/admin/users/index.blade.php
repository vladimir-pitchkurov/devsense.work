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
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
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
                                <strong>{{ $user->name }}</strong>
                                @if($user->isAuthor() && $user->is_approved && !$user->is_blocked)
                                    <br>
                                    <small>
                                        <a href="{{ route('authors.show', ['locale' => app()->getLocale(), 'slug' => $user->slug]) }}" target="_blank" style="color: var(--primary-color); text-decoration: underline;">
                                            View Profile
                                        </a>
                                    </small>
                                @endif
                            </td>
                            <td><code>{{ $user->email }}</code></td>
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
                                <div class="action-buttons">
                                    @if($user->id !== auth()->id())
                                        <a href="{{ route('admin.users.edit', ['locale' => app()->getLocale(), 'user' => $user->id]) }}" 
                                           class="admin-btn admin-btn--secondary" 
                                           title="Edit User">
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
