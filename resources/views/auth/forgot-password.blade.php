<x-layout :title="__('ui.auth.password_reset.forgot_title') . ' | DevSense'" :description="__('ui.auth.password_reset.forgot_sub')">

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">

            <div class="auth-card__header">
                <h1 class="auth-card__title">{{ __('ui.auth.password_reset.forgot_heading') }}</h1>
                <p class="auth-card__subtitle">{{ __('ui.auth.password_reset.forgot_sub') }}</p>
            </div>

            @if ($errors->any())
                <div class="auth-error" role="alert">{{ $errors->first() }}</div>
            @endif

            @if (session('status'))
                <div class="auth-error" role="alert" style="background-color: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.4); color: #34d399;">
                    {{ session('status') }}
                </div>
            @endif

            <form action="{{ route('password.email', ['locale' => app()->getLocale()]) }}" method="POST" class="auth-form" id="forgotPasswordForm">
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

                <button type="submit" class="auth-submit">{{ __('ui.auth.password_reset.forgot_btn') }}</button>
            </form>

            <p class="auth-switch">
                <a href="{{ route('login.locale', ['locale' => app()->getLocale()]) }}" class="auth-link">
                    &larr; {{ __('ui.auth.password_reset.back_to_login') }}
                </a>
            </p>

        </div>
    </div>
</div>

</x-layout>
