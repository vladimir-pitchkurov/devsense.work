@props(['title' => 'DevSense', 'description' => ''])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>

    @if($description)
        <meta name="description" content="{{ $description }}">
    @endif

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
<header class="header sticky">
    <div class="header__container">
        <a href="{{ route('home') }}" class="header__logo">DevSense</a>

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
        {{ $slot }}
    </div>
</main>

<footer class="footer">
    <div class="footer__container">
        <p class="footer__copyright">&copy; {{ date('Y') }} DevSense.work. {{ __('ui.footer.branch') }}: feature/php-guides-and-tools</p>
    </div>
</footer>
</body>
</html>
