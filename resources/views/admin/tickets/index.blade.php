<x-layout title="Admin - Support Tickets | DevSense" description="View and manage user complaints and suggestions">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Support Tickets</h1>
        <div class="header-actions" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Dashboard
            </a>
            <a href="{{ route('admin.articles.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Articles
            </a>
            <a href="{{ route('admin.categories.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Categories
            </a>
            <a href="{{ route('admin.tags.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Tags
            </a>
            @can('manage-users')
                <a href="{{ route('admin.users.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                    Users
                </a>
            @endcan
            <a href="{{ route('admin.profile.edit', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Edit Profile
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="admin-alert admin-alert--success">
            {{ session('success') }}
        </div>
    @endif

    <!-- Filter Bar -->
    <div class="admin-card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
        <form method="GET" action="{{ route('admin.tickets.index', ['locale' => app()->getLocale()]) }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-color); text-transform: uppercase;">Status</label>
                <select name="status" style="padding: 0.5rem; border-radius: 0.375rem; border: 1.5px solid var(--border-color); background: var(--page-bg); color: var(--text-color);">
                    <option value="">All Statuses</option>
                    <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                    <option value="answered" {{ request('status') === 'answered' ? 'selected' : '' }}>Answered</option>
                </select>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-color); text-transform: uppercase;">Type</label>
                <select name="type" style="padding: 0.5rem; border-radius: 0.375rem; border: 1.5px solid var(--border-color); background: var(--page-bg); color: var(--text-color);">
                    <option value="">All Types</option>
                    <option value="complaint" {{ request('type') === 'complaint' ? 'selected' : '' }}>Complaint</option>
                    <option value="suggestion" {{ request('type') === 'suggestion' ? 'selected' : '' }}>Suggestion</option>
                    <option value="general" {{ request('type') === 'general' ? 'selected' : '' }}>General</option>
                </select>
            </div>

            <button type="submit" class="admin-btn admin-btn--primary" style="padding: 0.5rem 1rem;">Filter</button>
            <a href="{{ route('admin.tickets.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary" style="padding: 0.5rem 1rem;">Reset</a>
        </form>
    </div>

    <div class="admin-card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sender</th>
                        <th>Subject</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td>#{{ $ticket->id }}</td>
                            <td>
                                <strong>{{ $ticket->sender_name ?: 'Guest' }}</strong><br>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">{{ $ticket->sender_email }}</span>
                            </td>
                            <td>{{ $ticket->subject }}</td>
                            <td>
                                @if ($ticket->type === 'complaint')
                                    <span class="admin-badge admin-badge--danger" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 0.15rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">Complaint</span>
                                @elseif ($ticket->type === 'suggestion')
                                    <span class="admin-badge admin-badge--success" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); padding: 0.15rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">Suggestion</span>
                                @else
                                    <span class="admin-badge admin-badge--secondary" style="background: rgba(100, 116, 139, 0.1); color: var(--text-muted); border: 1px solid rgba(100, 116, 139, 0.2); padding: 0.15rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">General</span>
                                @endif
                            </td>
                            <td>
                                @if ($ticket->status === 'open')
                                    <span class="admin-badge admin-badge--warning" style="background: rgba(249, 115, 22, 0.1); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.2); padding: 0.15rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">Open</span>
                                @else
                                    <span class="admin-badge admin-badge--success" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); padding: 0.15rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">Answered</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size: 0.85rem;">{{ $ticket->created_at->format('Y-m-d H:i') }}</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.tickets.show', ['locale' => app()->getLocale(), 'ticket' => $ticket]) }}" class="admin-btn admin-btn--secondary" style="padding: 0.25rem 0.5rem; font-size: 0.85rem;">
                                    View / Reply
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                No support tickets found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tickets->hasPages())
            <div style="margin-top: 1.5rem;">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
</div>
</x-layout>
