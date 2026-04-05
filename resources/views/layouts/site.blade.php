<!DOCTYPE html>
<html lang="{{ $htmlLang }}">
<head>
    @production
        @if (filled(config('services.gtm.container_id')))
            <!-- Google Tag Manager -->
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer',@json(config('services.gtm.container_id')));</script>
            <!-- End Google Tag Manager -->
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
        <p class="footer__copyright">&copy; {{ date('Y') }} {{ $siteName }}. {{ __('ui.footer.branch') }}: feature/php-guides-and-tools</p>
    </div>
</footer>
</body>
</html>
