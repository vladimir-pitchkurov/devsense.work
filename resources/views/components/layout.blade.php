<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'DevSense' }}</title>
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
<body class="page" id="app">
<header class="header">
    <div class="header__container">
        <a href="{{ url('/') }}" class="header__logo">DevSense</a>
        <nav class="header__nav nav">
            <ul class="nav__list">
                <li class="nav__item">
                    <a href="{{ route('php.index') }}" class="nav__link">{{ __('ui.nav.php_guides') }}</a>
                </li>
                <li class="nav__item">
                    <a href="{{ route('tools.sail') }}" class="nav__link">{{ __('ui.nav.tools') }}</a>
                </li>
            </ul>
            <div class="nav__controls" style="display: flex; gap: 1rem; align-items: center;">
                <language-switcher></language-switcher>
                <theme-switcher></theme-switcher>
            </div>
        </nav>
    </div>
</header>

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
