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
                <a href="{{ route('login') }}" class="header__cabinet-btn" title="{{ __('ui.nav.login') }}">
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
        <a href="{{ route('login') }}" class="mobile-nav__item {{ Route::is('login') ? 'active' : '' }}" aria-label="{{ __('ui.nav.cabinet') ?? 'Cabinet' }}">
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
        <p class="footer__copyright">&copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.</p>
    </div>
</footer>
    @stack('scripts')
</body>
</html>
