<x-layout title="Admin - Edit Profile | DevSense" description="Edit your author profile details">
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
                        <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="profile-avatar-img" id="avatarPreview">
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
                        <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required placeholder="e.g. Jane Doe" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="slug" class="form-label">Author URL Slug</label>
                        <input type="text" name="slug" id="slug" value="{{ old('slug', $user->slug) }}" required placeholder="e.g. jane-doe" class="form-input">
                        <p class="form-help">Used for your public page: <code>/{{ app()->getLocale() }}/authors/{slug}</code></p>
                    </div>

                    <div class="form-group">
                        <label for="job_title" class="form-label">Job Title / Title</label>
                        <input type="text" name="job_title" id="job_title" value="{{ old('job_title', $user->job_title) }}" placeholder="e.g. Senior PHP Architect" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="bio" class="form-label">Biography / About Yourself</label>
                        <textarea name="bio" id="bio" rows="6" placeholder="Write a short professional bio..." class="form-input">{{ old('bio', $user->bio) }}</textarea>
                        <p class="form-help">Markdown is supported. Describe your experience to boost E-E-A-T credibility.</p>
                    </div>
                </div>

                <div class="admin-card" style="margin-top: 1.5rem;">
                    <h2 class="card-title">Social Links & Websites</h2>
                    
                    <div class="form-group">
                        <label for="github_url" class="form-label">GitHub URL</label>
                        <input type="url" name="github_url" id="github_url" value="{{ old('github_url', $user->github_url) }}" placeholder="https://github.com/username" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="linkedin_url" class="form-label">LinkedIn URL</label>
                        <input type="url" name="linkedin_url" id="linkedin_url" value="{{ old('linkedin_url', $user->linkedin_url) }}" placeholder="https://linkedin.com/in/username" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="twitter_url" class="form-label">Twitter / X URL</label>
                        <input type="url" name="twitter_url" id="twitter_url" value="{{ old('twitter_url', $user->twitter_url) }}" placeholder="https://twitter.com/username" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="website_url" class="form-label">Personal Website URL</label>
                        <input type="url" name="website_url" id="website_url" value="{{ old('website_url', $user->website_url) }}" placeholder="https://yourwebsite.com" class="form-input">
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

<style>
.admin-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.admin-title {
    font-family: 'Outfit', sans-serif;
    font-size: 2.25rem;
    font-weight: 800;
    color: var(--text-color);
}

.admin-alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 2rem;
    font-weight: 500;
}

.admin-alert--success {
    background-color: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.admin-alert--danger {
    background-color: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.admin-alert--danger ul {
    margin: 0.5rem 0 0 0;
    padding-left: 1.25rem;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

@media (min-width: 992px) {
    .form-grid {
        grid-template-columns: 320px 1fr;
    }
}

.admin-card {
    background-color: var(--card-bg, rgba(255, 255, 255, 0.02));
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.text-center {
    text-align: center;
}

.avatar-preview-container {
    margin: 1.5rem auto;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--primary-color);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    background-color: rgba(var(--bg-color-rgb), 0.5);
}

.profile-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: var(--text-color);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    text-align: left;
}

.form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-color);
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.form-input {
    background-color: rgba(var(--bg-color-rgb), 0.5);
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    padding: 0.75rem;
    color: var(--text-color);
    font-family: inherit;
    font-size: 0.95rem;
    width: 100%;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.15);
}

.form-help {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0;
}

.admin-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}

.admin-badge--category {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--primary-color);
    border: 1px solid rgba(var(--primary-color-rgb), 0.2);
}

.admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-family: inherit;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    cursor: pointer;
    transition: transform 0.2s, background-color 0.2s, opacity 0.2s;
    border: 1px solid transparent;
}

.admin-btn--primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    color: #fff;
}

.admin-btn--secondary {
    background-color: transparent;
    border-color: var(--border-color);
    color: var(--text-color);
}

.admin-btn--secondary:hover {
    background-color: rgba(var(--border-color-rgb), 0.1);
}

.admin-btn:hover {
    transform: translateY(-1px);
}
</style>
</x-layout>
