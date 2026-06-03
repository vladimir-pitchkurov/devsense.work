<!DOCTYPE html>
<html lang="{{ $htmlLang }}">
<head>
    @production
        @if (filled(config('services.gtm.container_id')))
            @php
                $partytownPublic = public_path('~partytown/partytown.js');
                $partytownQuery = is_file($partytownPublic) ? '?v='.filemtime($partytownPublic) : '';
            @endphp
            <!-- Partytown + GTM: forward dataLayer.push + gtag so main-thread events reach the worker (GA4 / custom tags). -->
            <link rel="preload" href="/~partytown/partytown.js{{ $partytownQuery }}" as="script" fetchpriority="high">
            <script>
                window.dataLayer = window.dataLayer || [];
                window.gtag = function gtag() { window.dataLayer.push(arguments); };
                window.partytown = {
                    forward: ['dataLayer.push', 'gtag'],
                    resolveUrl: function (url, location, type) {
                        if (type === 'script') {
                            var hostname = url.hostname;
                            if (hostname === 'www.googletagmanager.com' ||
                                hostname === 'googletagmanager.com' ||
                                hostname === 'www.google-analytics.com' ||
                                hostname === 'google-analytics.com' ||
                                hostname === 'region1.google-analytics.com') {
                                var proxyUrl = new URL('/partytown-proxy', location.origin);
                                proxyUrl.searchParams.append('url', url.href);
                                return proxyUrl;
                            }
                        }
                        return url;
                    }
                };
            </script>
            <script src="/~partytown/partytown.js{{ $partytownQuery }}"></script>
            <script>
                window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
            </script>
            <script
                type="text/partytown"
                src="https://www.googletagmanager.com/gtm.js?id={{ rawurlencode(config('services.gtm.container_id')) }}"
            ></script>
        @endif
    @endproduction
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="{{ $themeColor }}">
    <meta name="robots" content="{{ $robotsContent }}">

    <!-- Favicons -->
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">

    <title>{{ $title }}</title>

    @if ($description !== '')
        <meta name="description" content="{{ $description }}">
    @endif

    <link rel="canonical" href="{{ $canonical }}">

    @foreach ($hreflangLinks as $link)
        <link rel="alternate" hreflang="{{ $link['code'] }}" href="{{ $link['url'] }}">
    @endforeach

    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $title }}">
    @if ($description !== '')
        <meta property="og:description" content="{{ $description }}">
    @endif
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:locale" content="{{ $ogLocale }}">
    @foreach ($ogAlternateLocales as $altLocale)
        <meta property="og:locale:alternate" content="{{ $altLocale }}">
    @endforeach
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:secure_url" content="{{ $ogImage }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:width" content="{{ (int) $ogImageWidth }}">
        <meta property="og:image:height" content="{{ (int) $ogImageHeight }}">
        <meta property="og:image:alt" content="{{ $title }}">
    @endif

    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title }}">
    @if ($description !== '')
        <meta name="twitter:description" content="{{ $description }}">
    @endif
    @if ($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
        <meta name="twitter:image:alt" content="{{ $title }}">
    @endif
    @if ($twitterSite)
        @php
            $tw = ltrim((string) $twitterSite, '@');
        @endphp
        <meta name="twitter:site" content="{{ '@'.$tw }}">
    @endif

    <script type="application/ld+json">
        {!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) !!}
    </script>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
            } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/sass/app.scss', 'resources/js/app.ts'])
    @stack('styles')
</head>
<body
    class="page"
    id="app"
    data-a11y-theme-switcher="{{ __('ui.a11y.theme_switcher') }}"
    data-a11y-language-select="{{ __('ui.a11y.language_select') }}"
>
    @production
        @if (filled(config('services.gtm.container_id')))
            <!-- Google Tag Manager (noscript) -->
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ rawurlencode(config('services.gtm.container_id')) }}"
                    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
            <!-- End Google Tag Manager (noscript) -->
        @endif
    @endproduction
<header class="header sticky">
    <div class="header__container">
        <a href="{{ route('home') }}" class="header__logo">
            <svg class="header__logo-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="none">
                <defs>
                    <linearGradient id="logo-ds-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="var(--primary-color)" />
                        <stop offset="100%" stop-color="var(--primary-hover)" />
                    </linearGradient>
                </defs>
                <rect x="2" y="2" width="28" height="28" rx="8" stroke="var(--border-color)" stroke-width="1.5" />
                <rect x="2" y="2" width="28" height="28" rx="8" stroke="url(#logo-ds-gradient)" stroke-width="1.5" stroke-dasharray="24 64" stroke-linecap="round" />
                <!-- D character styled as cursor and bracket -->
                <path d="M8 8v16" stroke="url(#logo-ds-gradient)" stroke-width="2.5" stroke-linecap="round" />
                <path d="M8 8h4.5c4 0 6.5 3 6.5 8s-2.5 8-6.5 8H8" stroke="url(#logo-ds-gradient)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                <!-- S character styled as a curly tag -->
                <path d="M24 10c0-1.2-1-2-2.2-2H19.5c-1.2 0-2 .8-2 2v1.5c0 1.2.8 2 2 2h1c1.2 0 2 .8 2 2v1.5c0 1.2-.8 2-2 2H18c-1.2 0-2.2-.8-2.2-2" stroke="var(--text-color)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="15.5" cy="16" r="1.5" fill="var(--primary-color)" />
            </svg>
            <span class="header__logo-wordmark">
                <span class="header__logo-text">
                    <span class="logo-char-main">D</span><span class="logo-char-sub">ev</span><span class="logo-char-main">S</span><span class="logo-char-sub">ense</span><span class="logo-char-dot">.</span>
                </span>
                <span class="header__logo-tagline">PHP &amp; Backend Guides</span>
            </span>
        </a>

        <div class="header__controls">
            <language-switcher></language-switcher>
            <theme-switcher></theme-switcher>

            @auth
                <a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}" class="header__cabinet-btn" title="{{ __('ui.nav.cabinet') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="header__cabinet-icon">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                    </svg>
                    <span class="header__cabinet-text">{{ __('ui.nav.cabinet') }}</span>
                </a>
            @else
                @php
                    $locale = app()->getLocale();
                    $loginUrl  = Route::has('login.locale')  ? route('login.locale',  ['locale' => $locale]) : route('login');
                @endphp
                <a href="{{ $loginUrl }}" class="header__cabinet-btn" title="{{ __('ui.nav.login') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="header__cabinet-icon">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                    </svg>
                    <span class="header__cabinet-text">{{ __('ui.nav.login') }}</span>
                </a>
            @endauth

            <nav class="header__nav">
                <a href="{{ route('php.index') }}" class="nav__link">{{ __('ui.nav.php_guides') }}</a>
                <a href="{{ route('tools.index') }}" class="nav__link">{{ __('ui.nav.tools') }}</a>
                <a href="{{ route('microservices.index') }}" class="nav__link">{{ __('ui.nav.microservices') }}</a>
                <a href="{{ route('architecture.index') }}" class="nav__link">{{ __('ui.nav.architecture') }}</a>
                <a href="{{ route('quizzes.index') }}" class="nav__link">{{ app()->getLocale() === 'ru' ? 'Квизы' : 'Quizzes' }}</a>
            </nav>
        </div>
    </div>
</header>

<nav class="mobile-nav" aria-label="{{ __('ui.nav.mobile_aria', [], app()->getLocale()) ?? 'Navigation' }}">
    {{-- Home --}}
    <a href="{{ route('home') }}" class="mobile-nav__item {{ Route::is('home') ? 'active' : '' }}" aria-label="{{ __('ui.nav.home') }}">
        <span class="mobile-nav__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 12L12 3l9 9"/>
                <path d="M9 21V12h6v9"/>
                <path d="M3 12v9h18V12"/>
            </svg>
        </span>
        <span class="mobile-nav__label">{{ __('ui.nav.home') }}</span>
    </a>

    {{-- Browse (articles / categories) --}}
    <a href="{{ route('home') }}#categories" class="mobile-nav__item {{ Route::is('php.*') || Route::is('tools.*') || Route::is('microservices.*') || Route::is('architecture.*') ? 'active' : '' }}" aria-label="{{ __('ui.nav.browse') ?? 'Browse' }}">
        <span class="mobile-nav__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
        </span>
        <span class="mobile-nav__label">{{ __('ui.nav.browse') ?? 'Browse' }}</span>
    </a>

    {{-- Search --}}
    <a href="{{ route('home') }}?focus=search" class="mobile-nav__item" id="mobile-search-tab" aria-label="{{ __('ui.search.placeholder') ?? 'Search' }}">
        <span class="mobile-nav__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
            </svg>
        </span>
        <span class="mobile-nav__label">{{ __('ui.search.label') ?? 'Search' }}</span>
    </a>

    {{-- Cabinet / Admin --}}
    @auth
        <a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}" class="mobile-nav__item {{ Route::is('admin.*') ? 'active' : '' }}" aria-label="{{ __('ui.nav.cabinet') ?? 'Cabinet' }}">
    @else
        @php $mobileLoginUrl = Route::has('login.locale') ? route('login.locale', ['locale' => app()->getLocale()]) : route('login'); @endphp
        <a href="{{ $mobileLoginUrl }}" class="mobile-nav__item {{ Route::is('login.locale') ? 'active' : '' }}" aria-label="{{ __('ui.nav.cabinet') ?? 'Cabinet' }}">
    @endauth

        <span class="mobile-nav__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
            </svg>
        </span>
        <span class="mobile-nav__label">{{ __('ui.nav.cabinet') ?? 'Cabinet' }}</span>
    </a>
</nav>

<main class="main">
    <div class="main__container">
        @if ($breadcrumbItems !== [])
            <nav class="breadcrumb" aria-label="{{ __('ui.seo.breadcrumb_aria') }}">
                <ol class="breadcrumb__list">
                    @foreach ($breadcrumbItems as $crumb)
                        <li class="breadcrumb__item">
                            @if ($crumb['url'] !== null)
                                <a href="{{ $crumb['url'] }}" class="breadcrumb__link">{{ $crumb['label'] }}</a>
                            @else
                                <span class="breadcrumb__current" aria-current="page">{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        {{ $slot }}
    </div>
</main>

<footer class="footer">
    <div class="footer__container">
        <nav class="footer__nav" aria-label="{{ __('ui.footer.nav_aria') }}">
            <ul class="footer__nav-list">
                <li class="footer__nav-item">
                    <a href="{{ route('home') }}" class="footer__nav-link">{{ __('ui.nav.home') }}</a>
                </li>
                <li class="footer__nav-item">
                    <a href="{{ route('php.index') }}" class="footer__nav-link">{{ __('ui.nav.php_guides') }}</a>
                </li>
                <li class="footer__nav-item">
                    <a href="{{ route('tools.index') }}" class="footer__nav-link">{{ __('ui.nav.tools') }}</a>
                </li>
                <li class="footer__nav-item">
                    <a href="{{ route('microservices.index') }}" class="footer__nav-link">{{ __('ui.nav.microservices') }}</a>
                </li>
                <li class="footer__nav-item">
                    <a href="{{ route('architecture.index') }}" class="footer__nav-link">{{ __('ui.nav.architecture') }}</a>
                </li>
                <li class="footer__nav-item">
                    <a href="{{ route('terms') }}" class="footer__nav-link">{{ __('ui.footer.terms') }}</a>
                </li>
                <li class="footer__nav-item">
                    <a href="{{ route('privacy') }}" class="footer__nav-link">{{ __('ui.footer.privacy') }}</a>
                </li>
            </ul>
        </nav>
        @php
            $path = trim(request()->path(), '/');
            $segments = $path === '' ? [] : explode('/', $path);
            $supportedLocales = \App\Http\Middleware\SetLocale::SUPPORTED_LOCALES;
            $footerLocaleLinks = [];
            if ($segments !== [] && in_array($segments[0], $supportedLocales, true)) {
                $suffixParts = array_slice($segments, 1);
                $suffix = $suffixParts !== [] ? implode('/', $suffixParts) : '';
                $rootUrl = rtrim((string) config('app.url'), '/');
                foreach ($supportedLocales as $loc) {
                    $footerLocaleLinks[] = [
                        'code' => $loc,
                        'label' => strtoupper($loc),
                        'url' => $rootUrl.'/'.$loc.($suffix !== '' ? '/'.$suffix : ''),
                    ];
                }
            }
        @endphp
        @if ($footerLocaleLinks !== [])
            <nav class="footer__locales" aria-label="{{ __('ui.footer.locales_aria') }}">
                <p class="footer__locales-label">{{ __('ui.footer.locales_label') }}</p>
                <ul class="footer__locales-list">
                    @foreach ($footerLocaleLinks as $link)
                        <li class="footer__locales-item">
                            @if ($link['code'] === app()->getLocale())
                                <span class="footer__locales-current" aria-current="true">{{ $link['label'] }}</span>
                            @else
                                <a
                                    href="{{ $link['url'] }}"
                                    class="footer__locales-link"
                                    hreflang="{{ config('seo.hreflang.'.$link['code']) ?? $link['code'] }}"
                                    lang="{{ $link['code'] }}"
                                >{{ $link['label'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
        <div class="footer__contacts">
            <span class="footer__contact-item">
                {{ __('ui.footer.ceo_label') }}: <strong>Vladimir Pichkurov</strong> (<a href="mailto:vladimir@devsense.work" class="footer__contact-link">vladimir@devsense.work</a>)
            </span>
            <span class="footer__contact-item">
                {{ __('ui.footer.support_label') }}: <a href="mailto:support@mail.devsense.work" class="footer__contact-link">support@mail.devsense.work</a>
            </span>
        </div>
        <p class="footer__copyright">&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
    </div>
</footer>

<!-- Content Report Modal -->
<div id="reportModal" class="report-modal" style="display: none;">
    <div class="report-modal__overlay" onclick="closeReportModal()"></div>
    <div class="report-modal__container">
        <header class="report-modal__header">
            <h3 class="report-modal__title">Report Content</h3>
            <button onclick="closeReportModal()" class="report-modal__close" aria-label="Close modal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </header>
        <form id="reportForm" onsubmit="submitReportForm(event)">
            @csrf
            <input type="hidden" name="reportable_type" id="report-type">
            <input type="hidden" name="reportable_id" id="report-id">
            
            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                <label class="form-label" style="text-transform: none; font-size: 0.9rem; font-weight: 600; color: var(--text-color);">Reason for Report</label>
                <div class="report-reasons" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="reason_preset" value="Harassment or Hate Speech" checked>
                        <span>Harassment or Hate Speech</span>
                    </label>
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="reason_preset" value="Copyright Infringement">
                        <span>Copyright / Plagiarism</span>
                    </label>
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="reason_preset" value="Discrimination / Non-scientific Content">
                        <span>Discrimination or Unscientific content</span>
                    </label>
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="reason_preset" value="Other">
                        <span>Other (specify below)</span>
                    </label>
                </div>
            </div>

            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                <label for="report-reason-details" class="form-label" style="text-transform: none; font-size: 0.9rem; font-weight: 600; color: var(--text-color);">Details (Required)</label>
                <textarea name="reason" id="report-reason-details" rows="4" class="form-input" style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: var(--text-color); width: 100%; border-radius: 0.375rem; padding: 0.5rem; box-sizing: border-box; font-family: inherit; font-size: 0.95rem;" placeholder="Please describe the violation in detail..." required></textarea>
                <span id="report-error" style="color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; display: none;"></span>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" onclick="closeReportModal()" class="admin-btn admin-btn--secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem; background: transparent; border: 1px solid var(--border-color); color: var(--text-color); border-radius: 0.375rem; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" id="reportSubmitBtn" class="admin-btn admin-btn--primary" style="padding: 0.5rem 1rem; font-size: 0.9rem; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); color: white; border: none; border-radius: 0.375rem; cursor: pointer; font-weight: 600;">
                    Submit Report
                </button>
            </div>
        </form>
        <div id="report-success-msg" style="display: none; text-align: center; padding: 2rem 0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="3" width="48" height="48" style="margin: 0 auto 1rem auto; display: block;">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <h4 style="color: #10b981; font-weight: 700; margin-bottom: 0.5rem; font-size: 1.1rem;">Report Submitted</h4>
            <p id="report-success-text" style="color: var(--text-muted); font-size: 0.9rem; margin: 0;"></p>
            <button onclick="closeReportModal()" class="admin-btn admin-btn--secondary" style="margin-top: 1.5rem; padding: 0.5rem 1rem; background: transparent; border: 1px solid var(--border-color); color: var(--text-color); border-radius: 0.375rem; cursor: pointer;">
                Close
            </button>
        </div>
    </div>
</div>

<style>
.report-modal {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    animation: fadeIn 0.2s ease-out;
}
.report-modal__overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
}
.report-modal__container {
    position: relative;
    background: var(--card-bg, #1a202c);
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    width: 100%;
    max-width: 480px;
    padding: 1.5rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.3);
    z-index: 10001;
    color: var(--text-color);
    animation: scaleIn 0.2s ease-out;
}
.report-modal__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.75rem;
}
.report-modal__title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    font-family: 'Outfit', sans-serif;
    color: var(--text-color);
}
.report-modal__close {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.report-modal__close:hover {
    color: var(--text-color);
    background: rgba(255, 255, 255, 0.05);
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes scaleIn {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
</style>

<script>
function openReportModal(type, id) {
    document.getElementById('report-type').value = type;
    document.getElementById('report-id').value = id;
    document.getElementById('report-reason-details').value = '';
    document.getElementById('report-error').style.display = 'none';
    document.getElementById('reportForm').style.display = 'block';
    document.getElementById('report-success-msg').style.display = 'none';
    document.getElementById('reportModal').style.display = 'flex';
}

function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
}

function submitReportForm(event) {
    event.preventDefault();
    const submitBtn = document.getElementById('reportSubmitBtn');
    submitBtn.disabled = true;
    submitBtn.innerText = 'Submitting...';
    
    const details = document.getElementById('report-reason-details').value;
    if (details.trim().length < 5) {
        document.getElementById('report-error').innerText = 'Please provide details (at least 5 characters).';
        document.getElementById('report-error').style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.innerText = 'Submit Report';
        return;
    }
    
    const preset = document.querySelector('input[name="reason_preset"]:checked').value;
    const finalReason = preset === 'Other' ? details : preset + ': ' + details;
    
    const type = document.getElementById('report-type').value;
    const id = document.getElementById('report-id').value;
    const token = document.querySelector('input[name="_token"]').value;
    
    fetch('/' + document.documentElement.lang + '/reports', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token
        },
        body: JSON.stringify({
            reportable_type: type,
            reportable_id: id,
            reason: finalReason
        })
    })
    .then(response => response.json().then(data => ({ status: response.status, body: data })))
    .then(res => {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Submit Report';
        if (res.status === 200 && res.body.success) {
            document.getElementById('reportForm').style.display = 'none';
            document.getElementById('report-success-text').innerText = res.body.message;
            document.getElementById('report-success-msg').style.display = 'block';
        } else {
            document.getElementById('report-error').innerText = res.body.error || 'Failed to submit report. Please try again.';
            document.getElementById('report-error').style.display = 'block';
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Submit Report';
        document.getElementById('report-error').innerText = 'A network error occurred. Please try again.';
        document.getElementById('report-error').style.display = 'block';
    });
}
</script>

    @stack('scripts')
</body>
</html>
