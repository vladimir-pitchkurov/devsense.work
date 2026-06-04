<x-layout :title="__('ui.auth.login_title') . ' | DevSense'" :description="__('ui.auth.login_sub')">

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">

            <div class="auth-card__header">
                <h1 class="auth-card__title">{{ __('ui.auth.login_heading') }}</h1>
                <p class="auth-card__subtitle">{{ __('ui.auth.login_sub') }}</p>
            </div>

            @if ($errors->any())
                <div class="auth-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('login.post', ['locale' => app()->getLocale()]) }}" method="POST" class="auth-form" id="loginForm">
                @csrf

                <div class="auth-field">
                    <label for="email" class="auth-label">Email</label>
                    <input
                        type="email" name="email" id="email"
                        value="{{ old('email') }}"
                        required autofocus autocomplete="email"
                        placeholder="you@example.com"
                        class="auth-input{{ $errors->has('email') ? ' auth-input--error' : '' }}"
                    >
                </div>

                <div class="auth-field">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <label for="password" class="auth-label" style="margin-bottom: 0;">{{ __('ui.auth.password') }}</label>
                        <a href="{{ route('password.request.locale', ['locale' => app()->getLocale()]) }}" class="auth-link" style="font-size: 0.85rem;">{{ __('ui.auth.password_reset.forgot_link') }}</a>
                    </div>
                    <div class="auth-input-row">
                        <input
                            type="password" name="password" id="password"
                            required autocomplete="current-password"
                            placeholder="••••••••"
                            class="auth-input{{ $errors->has('password') ? ' auth-input--error' : '' }}"
                        >
                        <button type="button" class="auth-toggle" id="togglePassword" aria-label="Toggle password visibility">
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

                <button type="submit" class="auth-submit">{{ __('ui.auth.login_btn') }}</button>
            </form>

            <p class="auth-switch">
                {{ __('ui.auth.no_account') }}
                <a href="{{ route('register.locale', ['locale' => app()->getLocale()]) }}" class="auth-link">{{ __('ui.auth.register_link') }}</a>
            </p>

        </div>
    </div>
</div>



<script>
document.getElementById('togglePassword')?.addEventListener('click', function () {
    var pw = document.getElementById('password');
    if (!pw) return;
    var eyeIcon = this.querySelector('.eye-icon');
    var eyeOffIcon = this.querySelector('.eye-off-icon');
    var showing = pw.type === 'password';
    pw.type = showing ? 'text' : 'password';
    this.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
    if (eyeIcon) eyeIcon.style.display = showing ? 'none' : 'block';
    if (eyeOffIcon) eyeOffIcon.style.display = showing ? 'block' : 'none';
});
</script>

</x-layout>
