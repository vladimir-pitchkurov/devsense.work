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
        <meta property="og:image:width" content="{{ (int) $ogImageWidth }}">
        <meta property="og:image:height" content="{{ (int) $ogImageHeight }}">
    @endif

    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title }}">
    @if ($description !== '')
        <meta name="twitter:description" content="{{ $description }}">
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
    @vite(['resources/sass/app.scss', 'resources/js/app.ts'])
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
        <a href="{{ route('home') }}" class="header__logo">{{ $siteName }}</a>

        <div class="header__controls">
            <language-switcher></language-switcher>
            <theme-switcher></theme-switcher>

            <nav class="header__nav">
                <a href="{{ route('php.index') }}" class="nav__link">{{ __('ui.nav.php_guides') }}</a>
                <a href="{{ route('tools.index') }}" class="nav__link">{{ __('ui.nav.tools') }}</a>
                <a href="{{ route('microservices.index') }}" class="nav__link">{{ __('ui.nav.microservices') }}</a>
            </nav>
        </div>
    </div>
</header>

<nav class="mobile-nav">
    <a href="{{ route('home') }}" class="mobile-nav__item {{ Route::is('home') ? 'active' : '' }}">
        <span class="icon">🏠</span>
        <span class="label">{{ __('ui.nav.home') }}</span>
    </a>
    <a href="{{ route('php.index') }}" class="mobile-nav__item {{ Route::is('php.*') ? 'active' : '' }}">
        <span class="icon">🐘</span>
        <span class="label">{{ __('ui.nav.php_short') }}</span>
    </a>
    <a href="{{ route('tools.index') }}" class="mobile-nav__item {{ Route::is('tools.*') ? 'active' : '' }}">
        <span class="icon">🛠️</span>
        <span class="label">{{ __('ui.nav.tools') }}</span>
    </a>
    <a href="{{ route('microservices.index') }}" class="mobile-nav__item {{ Route::is('microservices.*') ? 'active' : '' }}">
        <span class="icon">🔀</span>
        <span class="label">{{ __('ui.nav.microservices_short') }}</span>
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
        <p class="footer__copyright">&copy; {{ date('Y') }} {{ $siteName }}. {{ __('ui.footer.branch') }}: feature/php-guides-and-tools</p>
    </div>
</footer>
</body>
</html>
