{{-- Cookie Consent Banner --}}
@php
    $locale = app()->getLocale();
    $privacyUrl = route('privacy');
@endphp

<div
    id="cookie-banner"
    class="cookie-banner"
    role="dialog"
    aria-modal="false"
    aria-label="{{ __('cookie.banner_aria') }}"
    style="display:none"
>
    <div class="cookie-banner__inner">
        <div class="cookie-banner__text">
            <span class="cookie-banner__icon">🍪</span>
            <p class="cookie-banner__message">
                {!! __('cookie.message', ['link' => '<a href="'.$privacyUrl.'" class="cookie-banner__privacy-link">'.__('cookie.privacy_policy').'</a>']) !!}
            </p>
        </div>
        <div class="cookie-banner__actions">
            <button id="cookie-settings-btn" class="cookie-banner__btn cookie-banner__btn--settings" type="button">
                {{ __('cookie.btn_settings') }}
            </button>
            <button id="cookie-reject-btn" class="cookie-banner__btn cookie-banner__btn--reject" type="button">
                {{ __('cookie.btn_reject') }}
            </button>
            <button id="cookie-accept-btn" class="cookie-banner__btn cookie-banner__btn--accept" type="button">
                {{ __('cookie.btn_accept') }}
            </button>
        </div>
    </div>
</div>

{{-- Cookie Settings Modal --}}
<div id="cookie-modal-overlay" class="cookie-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="cookie-modal-title">
    <div class="cookie-modal">
        <header class="cookie-modal__header">
            <h2 class="cookie-modal__title" id="cookie-modal-title">{{ __('cookie.settings_title') }}</h2>
            <button class="cookie-modal__close" id="cookie-modal-close" aria-label="{{ __('cookie.btn_close') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </header>

        <div class="cookie-modal__body">
            <p class="cookie-modal__desc">{{ __('cookie.settings_desc') }}</p>

            {{-- Necessary --}}
            <div class="cookie-category">
                <div class="cookie-category__header">
                    <div class="cookie-category__info">
                        <span class="cookie-category__name">{{ __('cookie.necessary_title') }}</span>
                        <span class="cookie-category__desc">{{ __('cookie.necessary_desc') }}</span>
                    </div>
                    <div class="cookie-toggle cookie-toggle--locked" title="{{ __('cookie.always_on') }}">
                        <span class="cookie-toggle__label">{{ __('cookie.always_on') }}</span>
                    </div>
                </div>
            </div>

            {{-- Analytics --}}
            <div class="cookie-category">
                <div class="cookie-category__header">
                    <div class="cookie-category__info">
                        <span class="cookie-category__name">{{ __('cookie.analytics_title') }}</span>
                        <span class="cookie-category__desc">{{ __('cookie.analytics_desc') }}</span>
                    </div>
                    <label class="cookie-toggle" for="toggle-analytics">
                        <input type="checkbox" id="toggle-analytics" class="cookie-toggle__input" data-category="analytics_storage">
                        <span class="cookie-toggle__slider"></span>
                    </label>
                </div>
            </div>

            {{-- Marketing --}}
            <div class="cookie-category">
                <div class="cookie-category__header">
                    <div class="cookie-category__info">
                        <span class="cookie-category__name">{{ __('cookie.marketing_title') }}</span>
                        <span class="cookie-category__desc">{{ __('cookie.marketing_desc') }}</span>
                    </div>
                    <label class="cookie-toggle" for="toggle-marketing">
                        <input type="checkbox" id="toggle-marketing" class="cookie-toggle__input" data-category="ad_storage">
                        <span class="cookie-toggle__slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <footer class="cookie-modal__footer">
            <button id="cookie-save-btn" class="cookie-banner__btn cookie-banner__btn--accept" type="button">
                {{ __('cookie.btn_save') }}
            </button>
        </footer>
    </div>
</div>

<script>
(function () {
    const STORAGE_KEY = 'ds_cookie_consent';
    const EXPIRY_DAYS = 365;

    function getConsent() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            const data = JSON.parse(raw);
            if (data.expires && Date.now() > data.expires) {
                localStorage.removeItem(STORAGE_KEY);
                return null;
            }
            return data;
        } catch (e) { return null; }
    }

    function saveConsent(prefs) {
        const expires = Date.now() + EXPIRY_DAYS * 86400 * 1000;
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...prefs, expires }));
    }

    function pushConsent(prefs) {
        window.dataLayer = window.dataLayer || [];
            // Required by Google Consent Mode v2 — must use gtag('consent', 'update')
        function gtag(){ window.dataLayer.push(arguments); }
        gtag('consent', 'update', {
            'analytics_storage': prefs.analytics ? 'granted' : 'denied',
            'ad_storage':        prefs.marketing ? 'granted' : 'denied',
            'ad_user_data':      prefs.marketing ? 'granted' : 'denied',
            'ad_personalization': prefs.marketing ? 'granted' : 'denied',
        });
        // Also fire a custom event so GTM triggers can react if needed
        window.dataLayer.push({ event: 'consent_update' });
    }


    function hideBanner() {
        const banner = document.getElementById('cookie-banner');
        if (banner) {
            banner.classList.add('cookie-banner--hidden');
            setTimeout(() => { banner.style.display = 'none'; }, 350);
        }
    }

    function showBanner() {
        const banner = document.getElementById('cookie-banner');
        if (banner) {
            banner.style.display = 'block';
            requestAnimationFrame(() => banner.classList.add('cookie-banner--visible'));
        }
    }

    function openModal() {
        const overlay = document.getElementById('cookie-modal-overlay');
        if (!overlay) return;
        const consent = getConsent();
        document.getElementById('toggle-analytics').checked = consent ? !!consent.analytics : false;
        document.getElementById('toggle-marketing').checked = consent ? !!consent.marketing : false;
        overlay.style.display = 'flex';
        requestAnimationFrame(() => overlay.classList.add('cookie-modal-overlay--visible'));
    }

    function closeModal() {
        const overlay = document.getElementById('cookie-modal-overlay');
        if (!overlay) return;
        overlay.classList.remove('cookie-modal-overlay--visible');
        setTimeout(() => { overlay.style.display = 'none'; }, 300);
    }

    function init() {
        const existing = getConsent();
        if (existing) {
            pushConsent(existing);
            return;
        }
        showBanner();

        document.getElementById('cookie-accept-btn').addEventListener('click', function () {
            const prefs = { analytics: true, marketing: true };
            saveConsent(prefs);
            pushConsent(prefs);
            hideBanner();
        });

        document.getElementById('cookie-reject-btn').addEventListener('click', function () {
            const prefs = { analytics: false, marketing: false };
            saveConsent(prefs);
            pushConsent(prefs);
            hideBanner();
        });

        document.getElementById('cookie-settings-btn').addEventListener('click', openModal);
        document.getElementById('cookie-modal-close').addEventListener('click', closeModal);

        document.getElementById('cookie-save-btn').addEventListener('click', function () {
            const prefs = {
                analytics: document.getElementById('toggle-analytics').checked,
                marketing: document.getElementById('toggle-marketing').checked,
            };
            saveConsent(prefs);
            pushConsent(prefs);
            closeModal();
            hideBanner();
        });

        document.getElementById('cookie-modal-overlay').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
