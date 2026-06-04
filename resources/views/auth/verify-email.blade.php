<x-layout :title="__('ui.auth.email_verification.title') . ' | DevSense'" :description="__('ui.auth.email_verification.sub')">

<div class="auth-page">
    <div class="auth-container">
        <div class="auth-card">

            <div class="auth-card__header">
                <h1 class="auth-card__title">{{ __('ui.auth.email_verification.heading') }}</h1>
                <p class="auth-card__subtitle">{{ __('ui.auth.email_verification.sub') }}</p>
            </div>

            @if (session('resent'))
                <div class="auth-status" style="background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.25); color: #22c55e; border-radius: 0.5rem; padding: 0.75rem 1rem; font-size: 0.875rem; margin-bottom: 1.5rem; line-height: 1.5;" role="alert">
                    {{ __('ui.auth.email_verification.resent') }}
                </div>
            @endif

            <div class="auth-form-wrapper" style="display: flex; flex-direction: column; gap: 1rem;">
                <form action="{{ route('verification.send', ['locale' => app()->getLocale()]) }}" method="POST">
                    @csrf
                    <button type="submit" class="auth-submit">{{ __('ui.auth.email_verification.resend_btn') }}</button>
                </form>

                <form action="{{ route('logout', ['locale' => app()->getLocale()]) }}" method="POST" style="text-align: center;">
                    @csrf
                    <button type="submit" class="auth-link" style="background: none; border: none; font-family: inherit; font-size: 0.875rem; cursor: pointer; color: var(--text-muted); font-weight: 500;">
                        {{ __('ui.auth.email_verification.logout_btn') }}
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

</x-layout>
