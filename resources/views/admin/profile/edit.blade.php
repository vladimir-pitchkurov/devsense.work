<x-layout title="Admin - Edit Profile | DevSense" description="Edit your author profile details">
@php
    $profile = $user->pendingProfile ?: $user;
@endphp
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Edit Profile</h1>
        <div class="header-actions" style="display: flex; gap: 0.5rem;">
            <a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Dashboard
            </a>
            <a href="{{ route('admin.articles.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Back to Articles
            </a>
        </div>
    </div>

    <form action="{{ route('admin.profile.update', ['locale' => app()->getLocale()]) }}" method="POST" enctype="multipart/form-data" class="admin-form">
        @csrf
        @method('PUT')

        @if ($user->pendingProfile)
            <div class="admin-alert admin-alert--info">
                <strong>Notice:</strong> You have a profile update pending administrator approval. The form below shows your drafted changes.
            </div>
        @endif

        @if (session('success'))
            <div class="admin-alert admin-alert--success">
                {{ session('success') }}
            </div>
        @endif

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
            <!-- Left Side: Avatar Upload & Account Details -->
            <div class="form-sidebar">
                <div class="admin-card text-center">
                    <h2 class="card-title">Avatar</h2>
                    
                    <div class="avatar-preview-container">
                        <img src="{{ $profile->avatarUrl() }}" alt="{{ $profile->name }}" class="profile-avatar-img" id="avatarPreview">
                    </div>

                    <div class="form-group">
                        <label for="avatar" class="form-label">Upload New Avatar</label>
                        <input type="file" name="avatar" id="avatar" accept="image/*" class="form-input" onchange="previewImage(event)">
                        <p class="form-help">JPEG, PNG, WebP or GIF. Max 5MB. AI metadata will be automatically stripped.</p>
                    </div>
                </div>

                <div class="admin-card" style="margin-top: 1.5rem;">
                    <h2 class="card-title">Role Info</h2>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="text" value="{{ $user->email }}" disabled class="form-input" style="opacity: 0.6; cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <span class="admin-badge admin-badge--category">{{ strtoupper($user->role) }}</span>
                    </div>
                </div>
            </div>

            <!-- Right Side: Profile Details & Social Links -->
            <div class="form-content">
                <div class="admin-card">
                    <h2 class="card-title">Author Information (E-E-A-T)</h2>
                    
                    <div class="form-group">
                        <label for="name" class="form-label">Display Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $profile->name) }}" required placeholder="e.g. Jane Doe" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="slug" class="form-label">Author URL Slug</label>
                        <input type="text" name="slug" id="slug" value="{{ old('slug', $profile->slug) }}" required placeholder="e.g. jane-doe" class="form-input">
                        <p class="form-help">Used for your public page: <code>/{{ app()->getLocale() }}/authors/{slug}</code></p>
                    </div>

                    <div class="form-group">
                        <label for="job_title" class="form-label">Job Title / Title</label>
                        <input type="text" name="job_title" id="job_title" value="{{ old('job_title', $profile->job_title) }}" placeholder="e.g. Senior PHP Architect" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="bio" class="form-label">Biography / About Yourself</label>
                        <textarea name="bio" id="bio" rows="6" placeholder="Write a short professional bio..." class="form-input">{{ old('bio', $profile->bio) }}</textarea>
                        <p class="form-help">Markdown is supported. Describe your experience to boost E-E-A-T credibility.</p>
                    </div>
                </div>

                <div class="admin-card" style="margin-top: 1.5rem;">
                    <h2 class="card-title">Privacy Settings</h2>
                    <div class="form-group" style="flex-direction: row; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                        <input type="checkbox" name="is_public" id="is_public" value="1" {{ old('is_public', $user->is_public) ? 'checked' : '' }} style="width: auto; margin: 0; transform: scale(1.2);">
                        <label for="is_public" class="form-label" style="margin: 0; text-transform: none; font-size: 0.95rem; cursor: pointer; letter-spacing: normal;">
                            Make my profile page public and crawlable by search engines
                        </label>
                    </div>
                    <p class="form-help" style="margin-left: 1.75rem;">
                        If unchecked, your public author page will return a 404 error to visitors and search engines, and you will be hidden from the authors directory.
                    </p>
                </div>

                <div class="admin-card" style="margin-top: 1.5rem;">
                    <h2 class="card-title">Social Links & Websites</h2>
                    
                    <div class="form-group">
                        <label for="github_url" class="form-label">GitHub URL</label>
                        <input type="url" name="github_url" id="github_url" value="{{ old('github_url', $profile->github_url) }}" placeholder="https://github.com/username" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="linkedin_url" class="form-label">LinkedIn URL</label>
                        <input type="url" name="linkedin_url" id="linkedin_url" value="{{ old('linkedin_url', $profile->linkedin_url) }}" placeholder="https://linkedin.com/in/username" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="twitter_url" class="form-label">Twitter / X URL</label>
                        <input type="url" name="twitter_url" id="twitter_url" value="{{ old('twitter_url', $profile->twitter_url) }}" placeholder="https://twitter.com/username" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="website_url" class="form-label">Personal Website URL</label>
                        <input type="url" name="website_url" id="website_url" value="{{ old('website_url', $profile->website_url) }}" placeholder="https://yourwebsite.com" class="form-input">
                    </div>

                    <div class="form-actions" style="margin-top: 2rem;">
                        <button type="submit" class="admin-btn admin-btn--primary">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="form-grid" style="margin-top: 1.5rem;">
        <div class="form-sidebar" style="visibility: hidden; height: 0; overflow: hidden; margin: 0; padding: 0;"></div>
        <div class="form-content">
            <div class="admin-card">
                <h2 class="card-title">{{ __('ui.auth.password_reset.change_title') }}</h2>
                <form action="{{ route('admin.profile.password', ['locale' => app()->getLocale()]) }}" method="POST" class="admin-form" style="margin-top: 1rem;">
                    @csrf
                    @method('PUT')
                    
                    <div class="form-group">
                        <label for="current_password" class="form-label">{{ __('ui.auth.password_reset.current_password') }}</label>
                        <input type="password" name="current_password" id="current_password" required placeholder="••••••••" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="change_password" class="form-label">{{ __('ui.auth.password_reset.new_password') }}</label>
                        <input type="password" name="password" id="change_password" required placeholder="••••••••" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation" class="form-label">{{ __('ui.auth.password_reset.confirm_new_password') }}</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" required placeholder="••••••••" class="form-input">
                    </div>

                    <div class="form-actions" style="margin-top: 1.5rem;">
                        <button type="submit" class="admin-btn admin-btn--primary">
                            {{ __('ui.auth.password_reset.change_btn') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(event) {
    var reader = new FileReader();
    reader.onload = function() {
        var output = document.getElementById('avatarPreview');
        output.src = reader.result;
    }
    reader.readAsDataURL(event.target.files[0]);
}
</script>
</x-layout>
