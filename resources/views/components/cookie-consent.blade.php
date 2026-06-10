{{-- Cookie Consent Banner (implied consent) --}}
@php
    $privacyUrl = route('privacy');
@endphp

<div
    id="cookie-banner"
    class="cookie-banner"
    role="dialog"
    aria-live="polite"
    aria-label="{{ __('cookie.banner_aria') }}"
    style="display:none"
>
    <div class="cookie-banner__inner">
        <p class="cookie-banner__message">
            {!! __('cookie.message', ['link' => '<a href="'.$privacyUrl.'" class="cookie-banner__link">'.__('cookie.privacy_policy').'</a>']) !!}
        </p>
        <div class="cookie-banner__actions">
            <button id="cookie-accept-btn" class="cookie-banner__btn cookie-banner__btn--accept" type="button">
                {{ __('cookie.btn_accept') }}
            </button>
            <button id="cookie-reject-btn" class="cookie-banner__btn cookie-banner__btn--reject" type="button" aria-label="{{ __('cookie.btn_reject') }}">
                &times;
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var STORAGE_KEY = 'ds_cookie_consent';
    var EXPIRY_DAYS = 365;

    function getConsent() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            var data = JSON.parse(raw);
            if (data.expires && Date.now() > data.expires) {
                localStorage.removeItem(STORAGE_KEY);
                return null;
            }
            return data;
        } catch (e) { return null; }
    }

    function saveConsent(granted) {
        var expires = Date.now() + EXPIRY_DAYS * 86400 * 1000;
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ analytics: granted, marketing: false, expires: expires }));
    }

    function sendConsent(granted) {
        window.dataLayer = window.dataLayer || [];
        function gtag(){ dataLayer.push(arguments); }
        gtag('consent', 'update', {
            analytics_storage:  granted ? 'granted' : 'denied',
            ad_storage:         'denied',
            ad_user_data:       'denied',
            ad_personalization: 'denied'
        });
        dataLayer.push({ event: 'consent_update' });
    }

    function hideBanner() {
        var banner = document.getElementById('cookie-banner');
        if (!banner) return;
        banner.classList.add('cookie-banner--hidden');
        setTimeout(function () { banner.style.display = 'none'; }, 400);
    }

    function accept() {
        saveConsent(true);
        sendConsent(true);
        hideBanner();
        // Remove implied-consent listeners
        document.removeEventListener('scroll', onImpliedConsent, { passive: true });
        document.removeEventListener('click',  onImpliedConsent);
    }

    function reject() {
        saveConsent(false);
        sendConsent(false);
        hideBanner();
        document.removeEventListener('scroll', onImpliedConsent, { passive: true });
        document.removeEventListener('click',  onImpliedConsent);
    }

    function onImpliedConsent(e) {
        // Ignore clicks on the banner itself
        var banner = document.getElementById('cookie-banner');
        if (banner && banner.contains(e.target)) return;
        accept();
    }

    function init() {
        var existing = getConsent();
        if (existing !== null) {
            // Already decided — nothing to show, consent sent from <head> script
            return;
        }

        // First visit — show banner
        var banner = document.getElementById('cookie-banner');
        if (banner) {
            banner.style.display = 'flex';
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    banner.classList.add('cookie-banner--visible');
                });
            });
        }

        document.getElementById('cookie-accept-btn').addEventListener('click', accept);
        document.getElementById('cookie-reject-btn').addEventListener('click', reject);

        // Implied consent: grant on scroll or any click outside the banner
        document.addEventListener('scroll', onImpliedConsent, { passive: true, once: true });
        document.addEventListener('click',  onImpliedConsent, { once: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
