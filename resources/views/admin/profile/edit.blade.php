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
                    <h2 class="card-title">Candidate Details (Job Board)</h2>
                    
                    <div class="form-group">
                        <label for="job_status" class="form-label">Job Status</label>
                        <select name="job_status" id="job_status" class="form-input">
                            <option value="">Select Status...</option>
                            <option value="seeking" {{ old('job_status', $profile->job_status) === 'seeking' ? 'selected' : '' }}>Seeking Work (Активно ищу работу)</option>
                            <option value="passively_seeking" {{ old('job_status', $profile->job_status) === 'passively_seeking' ? 'selected' : '' }}>Passively Seeking (Рассматриваю предложения)</option>
                            <option value="not_looking" {{ old('job_status', $profile->job_status) === 'not_looking' ? 'selected' : '' }}>Not Looking (Не ищу работу)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="intro" class="form-label">Professional Intro (Краткое представление)</label>
                        <input type="text" name="intro" id="intro" value="{{ old('intro', $profile->intro) }}" placeholder="e.g. Passionate PHP Developer with 5 years of experience" class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="experience" class="form-label">Work Experience (Опыт работы)</label>
                        <textarea name="experience" id="experience" rows="6" placeholder="Describe your professional work history..." class="form-input">{{ old('experience', $profile->experience) }}</textarea>
                    </div>
                </div>

                <div class="admin-card" style="margin-top: 1.5rem;">
                    <h2 class="card-title">Privacy & Anonymity Settings</h2>
                    
                    <div class="form-group" style="flex-direction: row; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                        <input type="checkbox" name="is_public" id="is_public" value="1" {{ old('is_public', $user->is_public) ? 'checked' : '' }} style="width: auto; margin: 0; transform: scale(1.2);">
                        <label for="is_public" class="form-label" style="margin: 0; text-transform: none; font-size: 0.95rem; cursor: pointer; letter-spacing: normal;">
                            Make my profile page public and crawlable by search engines
                        </label>
                    </div>
                    <p class="form-help" style="margin-left: 1.75rem; margin-bottom: 1.5rem;">
                        If unchecked, your public profile page will return a 404 error and be hidden from the directory.
                    </p>

                    <div class="form-group" style="flex-direction: row; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                        <input type="checkbox" name="is_anonymous" id="is_anonymous" value="1" {{ old('is_anonymous', $profile->is_anonymous) ? 'checked' : '' }} style="width: auto; margin: 0; transform: scale(1.2);">
                        <label for="is_anonymous" class="form-label" style="margin: 0; text-transform: none; font-size: 0.95rem; cursor: pointer; letter-spacing: normal;">
                            Hide my real identity (Make me an Anonymous Candidate)
                        </label>
                    </div>
                    <p class="form-help" style="margin-left: 1.75rem;">
                        If checked, guests will see your name as "Anonymous Candidate", your avatar will be hidden, and contact details will be replaced with a request message. Administrators will still see your real details.
                    </p>
                </div>

                <div class="admin-card" style="margin-top: 1.5rem;">
                    <h2 class="card-title">Portfolio Projects (Max 3 images per project)</h2>
                    <div id="portfolio-container" style="display: flex; flex-direction: column; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <!-- Populate dynamic projects -->
                    </div>
                    <button type="button" class="admin-btn admin-btn--secondary" onclick="addNewProject()">+ Add Project</button>
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
                    
                    @if ($user->password !== null)
                    <div class="form-group">
                        <label for="current_password" class="form-label">{{ __('ui.auth.password_reset.current_password') }}</label>
                        <div class="auth-input-row">
                            <input type="password" name="current_password" id="current_password" required placeholder="••••••••" class="form-input">
                            <button type="button" class="auth-toggle" id="toggleCurrentPassword" aria-label="Toggle password visibility">
                                <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="eye-off-icon" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true">
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    @endif

                    <div class="form-group">
                        <label for="change_password" class="form-label">{{ __('ui.auth.password_reset.new_password') }}</label>
                        <div class="auth-input-row">
                            <input type="password" name="password" id="change_password" required placeholder="••••••••" class="form-input">
                            <button type="button" class="auth-toggle" id="toggleChangePassword" aria-label="Toggle password visibility">
                                <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="eye-off-icon" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true">
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation" class="form-label">{{ __('ui.auth.password_reset.confirm_new_password') }}</label>
                        <div class="auth-input-row">
                            <input type="password" name="password_confirmation" id="password_confirmation" required placeholder="••••••••" class="form-input">
                            <button type="button" class="auth-toggle" id="togglePasswordConfirm" aria-label="Toggle password visibility">
                                <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="eye-off-icon" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true">
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                </svg>
                            </button>
                        </div>
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
    var file = event.target.files[0];
    if (!file) return;

    var maxSize = 5 * 1024 * 1024; // 5MB
    if (file.size > maxSize) {
        alert("The selected file is too large (max 5MB). Please choose a smaller image.");
        event.target.value = ""; // Clear file input
        return;
    }

    var reader = new FileReader();
    reader.onload = function() {
        var output = document.getElementById('avatarPreview');
        if (output) output.src = reader.result;
    }
    reader.readAsDataURL(file);
}

const initialPortfolio = @json($profile->portfolio ?? []);
let projectIndex = 0;

function renderPortfolio() {
    const container = document.getElementById('portfolio-container');
    container.innerHTML = '';
    initialPortfolio.forEach((project) => {
        addNewProject(project);
    });
}

function addNewProject(data = null) {
    const container = document.getElementById('portfolio-container');
    const idx = projectIndex++;
    
    const projectHtml = `
        <div class="portfolio-project-card" id="project-${idx}" style="border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 1rem; background: rgba(0,0,0,0.15); position: relative;">
            <button type="button" onclick="removeProject(${idx})" style="position: absolute; top: 1rem; right: 1rem; color: #ef4444; background: none; border: none; font-size: 1.25rem; cursor: pointer;" title="Remove Project">&times;</button>
            <h3 style="margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem; color: var(--text-color);">Project</h3>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label">Project Title *</label>
                <input type="text" name="portfolio[${idx}][title]" value="${data ? escapeHtml(data.title) : ''}" required class="form-input" placeholder="Project Name">
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label">Project Description</label>
                <textarea name="portfolio[${idx}][description]" rows="3" class="form-input" placeholder="Briefly describe what this project is...">${data ? escapeHtml(data.description) : ''}</textarea>
            </div>
            
            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label">Existing Images</label>
                <div class="existing-images-grid" style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                    ${data && data.images && data.images.length ? data.images.map(img => `
                        <div class="portfolio-img-thumb" style="position: relative; width: 80px; height: 80px; border-radius: 0.5rem; overflow: hidden; border: 1px solid var(--border-color);">
                            <img src="${getPortfolioUrl(img)}" style="width: 100%; height: 100%; object-fit: cover;">
                            <input type="hidden" name="portfolio[${idx}][existing_images][]" value="${img}">
                            <button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 2px; right: 2px; width: 18px; height: 18px; border-radius: 50%; background: rgba(239, 68, 68, 0.9); color: white; border: none; font-size: 10px; line-height: 18px; text-align: center; cursor: pointer; padding: 0;">&times;</button>
                        </div>
                    `).join('') : '<p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">No existing images.</p>'}
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Upload New Images (Max 3 total images including existing)</label>
                <input type="file" name="portfolio[${idx}][new_images][]" accept="image/*" multiple class="form-input" onchange="validateProjectImages(this)">
                <p class="form-help">JPEG, PNG, WebP or GIF. Max 3 images per project. EXIF data stripped automatically.</p>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', projectHtml);
}

function removeProject(idx) {
    const el = document.getElementById(`project-${idx}`);
    if (el) el.remove();
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function getPortfolioUrl(path) {
    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }
    const s3Enabled = {{ (config('filesystems.default') === 's3' || env('FILESYSTEM_DISK') === 's3') ? 'true' : 'false' }};
    if (s3Enabled) {
        return `{{ \Storage::disk('s3')->url('') }}${path}`;
    }
    return `/${path.replace(/^\//, '')}`;
}

function validateProjectImages(input) {
    const files = input.files;
    const card = input.closest('.portfolio-project-card');
    const existingCount = card.querySelectorAll('.portfolio-img-thumb').length;
    const total = existingCount + files.length;
    if (total > 3) {
        alert("A project cannot have more than 3 images. Please select fewer files.");
        input.value = '';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    renderPortfolio();
});
</script>
</x-layout>
