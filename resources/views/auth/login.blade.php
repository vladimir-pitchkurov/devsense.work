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

            <div class="auth-divider">
                <span>{{ __('ui.auth.or') }}</span>
            </div>

            <a href="{{ route('auth.google', ['locale' => app()->getLocale()]) }}" class="auth-google-btn">
                <svg viewBox="0 0 24 24" width="20" height="20" class="google-icon" aria-hidden="true">
                    <path fill="#EA4335" d="M12 5.04c1.78 0 3.37.61 4.63 1.81l3.46-3.46C17.99 1.19 15.22.4 12 .4 7.37.4 3.4 3.06 1.45 6.94l4.08 3.16c.96-2.87 3.66-5.06 6.47-5.06z"/>
                    <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.44c-.28 1.48-1.11 2.73-2.37 3.58l3.68 2.85c2.15-1.98 3.74-4.89 3.74-8.67z"/>
                    <path fill="#FBBC05" d="M5.53 14.9c-.24-.72-.38-1.49-.38-2.28 0-.79.14-1.56.38-2.28L1.45 7.18C.53 9.02 0 11.08 0 13.22c0 2.14.53 4.2 1.45 6.04l4.08-3.36z"/>
                    <path fill="#34A853" d="M12 23.6c3.24 0 5.97-1.07 7.96-2.92l-3.68-2.85c-1.02.68-2.33 1.09-3.96 1.09-3.12 0-5.77-2.11-6.72-4.96L1.53 17.3c2.01 3.98 6.13 6.3 10.47 6.3z"/>
                </svg>
                <span>{{ __('ui.auth.google_btn') }}</span>
            </a>

            <p class="auth-switch">
                {{ __('ui.auth.no_account') }}
                <a href="{{ route('register.locale', ['locale' => app()->getLocale()]) }}" class="auth-link">{{ __('ui.auth.register_link') }}</a>
            </p>

        </div>
    </div>
</div>



</x-layout>
