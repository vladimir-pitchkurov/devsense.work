<x-layout :title="__('ui.seo.page_not_found') . ' | DevSense'" :description="__('ui.seo.page_not_found')">
    <div style="max-width: 600px; margin: 6rem auto; text-align: center; padding: 0 1.5rem;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 8rem; font-weight: 800; margin: 0; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">404</h1>
        <h2 style="font-family: 'Outfit', sans-serif; font-size: 2rem; color: var(--text-color); margin-top: 1rem; margin-bottom: 1.5rem;">{{ __('ui.seo.page_not_found') }}</h2>
        <p style="color: var(--text-muted); font-size: 1.1rem; line-height: 1.6; margin-bottom: 2.5rem;">
            The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.
        </p>
        <a href="/{{ app()->getLocale() }}" class="btn-primary" style="display: inline-flex; align-items: center; justify-content: center; min-width: 160px; font-family: 'Outfit', sans-serif; text-decoration: none;">
            {{ __('ui.seo.back_to_home') }}
        </a>
    </div>
</x-layout>
