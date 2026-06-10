{{-- Cookie Notice (informational only) --}}
@php
    $privacyUrl = route('privacy');
@endphp

<div
    id="cookie-banner"
    class="cookie-banner"
    role="status"
    aria-live="polite"
    style="display:none"
>
    <div class="cookie-banner__inner">
        <p class="cookie-banner__message">
            {!! __('cookie.message', ['link' => '<a href="'.$privacyUrl.'" class="cookie-banner__link">'.__('cookie.privacy_policy').'</a>']) !!}
        </p>
        <button id="cookie-accept-btn" class="cookie-banner__btn" type="button">
            {{ __('cookie.btn_accept') }}
        </button>
    </div>
</div>

<script>
(function () {
    var STORAGE_KEY = 'ds_cookie_consent';

    function dismiss() {
        try { localStorage.setItem(STORAGE_KEY, '1'); } catch(e) {}
        var banner = document.getElementById('cookie-banner');
        if (!banner) return;
        banner.classList.add('cookie-banner--hidden');
        setTimeout(function () { banner.style.display = 'none'; }, 400);
    }

    function init() {
        try { if (localStorage.getItem(STORAGE_KEY)) return; } catch(e) {}

        var banner = document.getElementById('cookie-banner');
        if (!banner) return;
        banner.style.display = 'flex';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                banner.classList.add('cookie-banner--visible');
            });
        });

        document.getElementById('cookie-accept-btn').addEventListener('click', dismiss);

        // Also dismiss on any scroll or click outside banner
        document.addEventListener('scroll', function onScroll() {
            dismiss();
            document.removeEventListener('scroll', onScroll);
        }, { passive: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
