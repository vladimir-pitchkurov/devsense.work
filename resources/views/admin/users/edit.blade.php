<x-layout title="Admin - Edit User | DevSense" description="Edit user roles, approvals, and suspension status">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Edit User: {{ $user->name }}</h1>
        <a href="{{ route('admin.users.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
            Back to List
        </a>
    </div>

    <form action="{{ route('admin.users.update', ['locale' => app()->getLocale(), 'user' => $user->id]) }}" method="POST" class="admin-form">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="admin-alert admin-alert--danger">
                <strong>Please fix the errors below:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="form-grid">
            <!-- Left Side: Basic settings -->
            <div class="form-sidebar">
                <div class="admin-card">
                    <h2 class="card-title">Account Actions</h2>
                    
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="is_blocked" value="1" {{ old('is_blocked', $user->is_blocked) ? 'checked' : '' }}>
                            Suspend User Account
                        </label>
                        <p class="form-help">Suspended users will be logged out immediately and cannot log in or view the platform as logged-in users. Their articles and profile will also be hidden from standard public view.</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="is_approved" value="1" {{ old('is_approved', $user->is_approved) ? 'checked' : '' }}>
                            Approve Author Profile
                        </label>
                        <p class="form-help">Only approved authors can publish articles and have public profile pages.</p>
                    </div>

                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">
                        Update User Settings
                    </button>
                </div>
            </div>

            <!-- Right Side: User Profile & Roles -->
            <div class="form-content">
                <div class="admin-card">
                    <h2 class="card-title">User Details & Roles</h2>

                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" value="{{ $user->name }}" class="form-input" readonly disabled>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="text" value="{{ $user->email }}" class="form-input" readonly disabled>
                    </div>

                    <div class="form-group">
                        <label for="role" class="form-label">System Role</label>
                        <select name="role" id="role" class="form-input" required>
                            <option value="{{ \App\Models\User::ROLE_READER }}" {{ old('role', $user->role) === \App\Models\User::ROLE_READER ? 'selected' : '' }}>
                                Reader (standard user)
                            </option>
                            <option value="{{ \App\Models\User::ROLE_AUTHOR }}" {{ old('role', $user->role) === \App\Models\User::ROLE_AUTHOR ? 'selected' : '' }}>
                                Author (can publish, pending moderation)
                            </option>
                            <option value="{{ \App\Models\User::ROLE_SUPER_ADMIN }}" {{ old('role', $user->role) === \App\Models\User::ROLE_SUPER_ADMIN ? 'selected' : '' }}>
                                Super Admin (full system controls)
                            </option>
                        </select>
                        <p class="form-help">Changing the role will grant or revoke system permissions immediately.</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
</x-layout>
