<x-layout :title="__('ui.auth.register_title') . ' | DevSense'" :description="__('ui.auth.register_sub')">

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">

            <div class="auth-card__header">
                <h1 class="auth-card__title">{{ __('ui.auth.register_heading') }}</h1>
                <p class="auth-card__subtitle">{{ __('ui.auth.register_sub') }}</p>
            </div>

            @if ($errors->any())
                <div class="auth-error" role="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('register.post') }}" method="POST" class="auth-form" id="registerForm">
                @csrf

                <div class="auth-field">
                    <label for="name" class="auth-label">{{ __('ui.auth.full_name') }}</label>
                    <input
                        type="text" name="name" id="name"
                        value="{{ old('name') }}"
                        required autofocus autocomplete="name"
                        placeholder="Jane Doe"
                        class="auth-input{{ $errors->has('name') ? ' auth-input--error' : '' }}"
                    >
                </div>

                <div class="auth-field">
                    <label for="email" class="auth-label">Email</label>
                    <input
                        type="email" name="email" id="email"
                        value="{{ old('email') }}"
                        required autocomplete="email"
                        placeholder="you@example.com"
                        class="auth-input{{ $errors->has('email') ? ' auth-input--error' : '' }}"
                    >
                </div>

                <div class="auth-field">
                    <label for="password" class="auth-label">{{ __('ui.auth.password') }}</label>
                    <div class="auth-input-row">
                        <input
                            type="password" name="password" id="password"
                            required autocomplete="new-password"
                            placeholder="Min 8 characters"
                            class="auth-input{{ $errors->has('password') ? ' auth-input--error' : '' }}"
                        >
                        <button type="button" class="auth-toggle" id="togglePassword" aria-label="Toggle password visibility">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <div class="auth-strength" aria-hidden="true">
                        <div class="auth-strength__bar">
                            <div class="auth-strength__fill" id="strengthFill"></div>
                        </div>
                        <span class="auth-strength__label" id="strengthLabel"></span>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password_confirmation" class="auth-label">{{ __('ui.auth.confirm_password') }}</label>
                    <div class="auth-input-row">
                        <input
                            type="password" name="password_confirmation" id="password_confirmation"
                            required autocomplete="new-password"
                            placeholder="••••••••"
                            class="auth-input"
                        >
                        <button type="button" class="auth-toggle" id="toggleConfirm" aria-label="Toggle confirm password visibility">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="auth-field">
                    <label class="auth-check-row">
                        <input type="checkbox" name="terms" id="terms" required class="auth-check">
                        <span class="auth-check-text">
                            {{ __('ui.auth.terms_prefix') }}
                            <a href="{{ route('terms', ['locale' => app()->getLocale()]) }}" target="_blank" class="auth-link">{{ __('ui.auth.terms') }}</a>
                            &amp;
                            <a href="{{ route('privacy', ['locale' => app()->getLocale()]) }}" target="_blank" class="auth-link">{{ __('ui.auth.privacy') }}</a>
                        </span>
                    </label>
                </div>

                <button type="submit" class="auth-submit">{{ __('ui.auth.register_btn') }}</button>
            </form>

            <p class="auth-switch">
                {{ __('ui.auth.has_account') }}
                <a href="{{ route('login.locale', ['locale' => app()->getLocale()]) }}" class="auth-link">{{ __('ui.auth.login_link') }}</a>
            </p>

        </div>
    </div>
</div>



<script>
(function () {
    function makeToggle(inputId, btnId) {
        var btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', function () {
            var pw = document.getElementById(inputId);
            if (!pw) return;
            var svg = btn.querySelector('svg');
            var showing = pw.type === 'password';
            pw.type = showing ? 'text' : 'password';
            btn.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
            svg.innerHTML = showing
                ? '<line x1="1" y1="1" x2="23" y2="23"/><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>'
                : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        });
    }
    makeToggle('password', 'togglePassword');
    makeToggle('password_confirmation', 'toggleConfirm');

    var pw   = document.getElementById('password');
    var fill  = document.getElementById('strengthFill');
    var label = document.getElementById('strengthLabel');
    if (pw && fill && label) {
        var levels = [
            { pct: 0,   color: '',        text: '' },
            { pct: 20,  color: '#ef4444', text: 'Weak' },
            { pct: 45,  color: '#f97316', text: 'Fair' },
            { pct: 65,  color: '#eab308', text: 'Good' },
            { pct: 85,  color: '#22c55e', text: 'Strong' },
            { pct: 100, color: '#10b981', text: 'Very Strong' },
        ];
        pw.addEventListener('input', function () {
            var v = pw.value, score = 0;
            if (v.length >= 8)  score++;
            if (v.length >= 12) score++;
            if (/[A-Z]/.test(v)) score++;
            if (/[0-9]/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            var idx = v.length === 0 ? 0 : Math.min(score + 1, levels.length - 1);
            var lv = levels[idx];
            fill.style.width = lv.pct + '%';
            fill.style.backgroundColor = lv.color;
            label.textContent = lv.text;
            label.style.color = lv.color || 'var(--text-muted)';
        });
    }
})();
</script>

</x-layout>
