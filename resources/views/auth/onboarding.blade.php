<x-layout title="Onboarding - Customize Your Preferences | DevSense" description="Choose your interested categories and notification settings">

<div class="auth-page">
    <div class="auth-container" style="max-width: 600px;">
        <div class="auth-card" style="padding: 2.5rem; background: rgba(30, 27, 75, 0.4); backdrop-filter: blur(10px); border: 1px solid var(--border-color); border-radius: 1rem;">
            <div class="auth-card__header" style="margin-bottom: 2rem; text-align: center;">
                <h1 class="auth-card__title" style="font-size: 1.8rem; font-weight: 800; color: var(--text-color); margin-bottom: 0.5rem;">Welcome to DevSense!</h1>
                <p class="auth-card__subtitle" style="color: var(--text-muted); font-size: 0.95rem;">Let's customize your experience before you dive in.</p>
            </div>

            @if ($errors->any())
                <div class="auth-error" role="alert" style="margin-bottom: 1.5rem; background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 1rem; border-radius: 0.5rem; font-size: 0.9rem;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('onboarding.submit', ['locale' => app()->getLocale()]) }}" method="POST" class="auth-form">
                @csrf

                <!-- Language / Locale -->
                <div class="auth-field" style="margin-bottom: 1.5rem;">
                    <label for="locale" class="auth-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.85rem; font-weight: 700; color: var(--text-color); text-transform: uppercase;">Preferred Language for Emails</label>
                    <select name="locale" id="locale" class="auth-input" style="width: 100%; padding: 0.75rem; border-radius: 0.375rem; border: 1.5px solid var(--border-color); background: var(--page-bg); color: var(--text-color);">
                        <option value="en" {{ app()->getLocale() === 'en' ? 'selected' : '' }}>English</option>
                        <option value="ru" {{ app()->getLocale() === 'ru' ? 'selected' : '' }}>Русский</option>
                        <option value="ua" {{ app()->getLocale() === 'ua' ? 'selected' : '' }}>Українська</option>
                        <option value="bg" {{ app()->getLocale() === 'bg' ? 'selected' : '' }}>Български</option>
                        <option value="de" {{ app()->getLocale() === 'de' ? 'selected' : '' }}>Deutsch</option>
                        <option value="fr" {{ app()->getLocale() === 'fr' ? 'selected' : '' }}>Français</option>
                        <option value="es" {{ app()->getLocale() === 'es' ? 'selected' : '' }}>Español</option>
                        <option value="it" {{ app()->getLocale() === 'it' ? 'selected' : '' }}>Italiano</option>
                    </select>
                </div>

                <!-- Interests (Categories) -->
                <div class="auth-field" style="margin-bottom: 1.5rem;">
                    <label class="auth-label" style="display: block; margin-bottom: 0.75rem; font-size: 0.85rem; font-weight: 700; color: var(--text-color); text-transform: uppercase;">Topics of Interest</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem;">
                        @foreach ($categories as $cat)
                            <label style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid var(--border-color); background: rgba(0,0,0,0.1); cursor: pointer; user-select: none;">
                                <input type="checkbox" name="interests[]" value="{{ $cat->id }}" checked style="width: auto; transform: scale(1.15);">
                                <span style="font-size: 0.9rem; color: var(--text-color); font-weight: 500;">
                                    {{ $cat->translate()?->name ?? $cat->slug }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Notification Toggles -->
                <div class="auth-field" style="margin-bottom: 2rem;">
                    <label class="auth-label" style="display: block; margin-bottom: 0.75rem; font-size: 0.85rem; font-weight: 700; color: var(--text-color); text-transform: uppercase;">Notification Settings</label>
                    
                    <label style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem; cursor: pointer;">
                        <input type="checkbox" name="notify_articles_quizzes" value="1" checked style="width: auto; margin-top: 0.25rem; transform: scale(1.2);">
                        <span style="font-size: 0.9rem; color: var(--text-color); line-height: 1.4;">
                            <strong>New Articles & Quizzes</strong><br>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">Receive email notifications about new guides and quizzes in your interested topics.</span>
                        </span>
                    </label>

                    <label style="display: flex; align-items: flex-start; gap: 0.75rem; cursor: pointer;">
                        <input type="checkbox" name="notify_comments" value="1" checked style="width: auto; margin-top: 0.25rem; transform: scale(1.2);">
                        <span style="font-size: 0.9rem; color: var(--text-color); line-height: 1.4;">
                            <strong>Comments & Replies</strong><br>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">Receive notifications about comments on your articles or replies to your suggestions.</span>
                        </span>
                    </label>
                </div>

                <button type="submit" class="admin-btn admin-btn--primary" style="width: 100%; padding: 0.75rem; font-size: 1rem; font-weight: bold;">
                    Save & Continue
                </button>
            </form>
        </div>
    </div>
</div>

</x-layout>
