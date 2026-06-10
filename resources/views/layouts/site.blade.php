<!DOCTYPE html>
<html lang="{{ $htmlLang }}">
<head>
    {{-- GTM is loaded automatically by Cloudflare Google Tag Gateway --}}
    {{-- Consent Mode: restore user preference from localStorage BEFORE GTM fires tags --}}
    <script>
        (function(){
            var KEY='ds_cookie_consent';
            try{
                var raw=localStorage.getItem(KEY);
                if(raw){
                    var d=JSON.parse(raw);
                    if(!d.expires||Date.now()<=d.expires){
                        window.dataLayer=window.dataLayer||[];
                        function gtag(){dataLayer.push(arguments);}
                        gtag('consent','update',{
                            analytics_storage: d.analytics?'granted':'denied',
                            ad_storage: d.marketing?'granted':'denied',
                            ad_user_data: d.marketing?'granted':'denied',
                            ad_personalization: d.marketing?'granted':'denied'
                        });
                    }
                }
            }catch(e){}
        })();
    </script>

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
    {{-- GTM noscript handled by Cloudflare Tag Gateway --}}
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
                <a href="{{ route('search') }}" class="nav__link">{{ app()->getLocale() === 'ru' ? 'Каталог' : 'Catalog' }}</a>
                <a href="{{ route('quizzes.index') }}" class="nav__link">{{ app()->getLocale() === 'ru' ? 'Квизы' : 'Quizzes' }}</a>
                <a href="{{ route('suggestions.index') }}" class="nav__link">{{ app()->getLocale() === 'ru' ? 'Предложения' : 'Suggestions' }}</a>
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

    {{-- Catalog --}}
    <a href="{{ route('search') }}" class="mobile-nav__item {{ Route::is('search') ? 'active' : '' }}" aria-label="Catalog">
        <span class="mobile-nav__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
        </span>
        <span class="mobile-nav__label">{{ app()->getLocale() === 'ru' ? 'Каталог' : 'Catalog' }}</span>
    </a>

    {{-- Quizzes --}}
    <a href="{{ route('quizzes.index') }}" class="mobile-nav__item {{ Route::is('quizzes.*') ? 'active' : '' }}" aria-label="Quizzes">
        <span class="mobile-nav__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
                <circle cx="12" cy="12" r="10"/>
            </svg>
        </span>
        <span class="mobile-nav__label">{{ app()->getLocale() === 'ru' ? 'Квизы' : 'Quizzes' }}</span>
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
                    <a href="{{ route('search') }}" class="footer__nav-link">{{ app()->getLocale() === 'ru' ? 'Каталог' : 'Catalog' }}</a>
                </li>
                <li class="footer__nav-item">
                    <a href="{{ route('quizzes.index') }}" class="footer__nav-link">{{ app()->getLocale() === 'ru' ? 'Квизы' : 'Quizzes' }}</a>
                </li>
                <li class="footer__nav-item">
                    <a href="{{ route('suggestions.index') }}" class="footer__nav-link">{{ app()->getLocale() === 'ru' ? 'Предложения' : 'Suggestions' }}</a>
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
            <h3 class="report-modal__title" id="report-modal-title">{{ __('ui.reports.modal_title') }}</h3>
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
                <label class="form-label" style="text-transform: none; font-size: 0.9rem; font-weight: 600; color: var(--text-color);">{{ __('ui.reports.type_label') }}</label>
                <div class="report-reasons" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="report_type" value="spam" checked>
                        <span>{{ __('ui.reports.categories.spam') }}</span>
                    </label>
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="report_type" value="insult">
                        <span>{{ __('ui.reports.categories.insult') }}</span>
                    </label>
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="report_type" value="promo">
                        <span>{{ __('ui.reports.categories.promo') }}</span>
                    </label>
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="report_type" value="plagiarism">
                        <span>{{ __('ui.reports.categories.plagiarism') }}</span>
                    </label>
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="report_type" value="other">
                        <span>{{ __('ui.reports.categories.other') }}</span>
                    </label>
                </div>
            </div>

            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                <label for="report-reason-details" class="form-label" style="text-transform: none; font-size: 0.9rem; font-weight: 600; color: var(--text-color);">{{ __('ui.reports.reason_label') }}</label>
                <textarea name="reason" id="report-reason-details" rows="4" class="form-input" style="background: var(--page-bg); border: 1px solid var(--border-color); color: var(--text-color); width: 100%; border-radius: 0.375rem; padding: 0.5rem; box-sizing: border-box; font-family: inherit; font-size: 0.95rem;" placeholder="{{ __('ui.reports.reason_placeholder') }}" required></textarea>
                <span id="report-error" style="color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; display: none;"></span>
            </div>

            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                <label for="report-screenshot" class="form-label" style="text-transform: none; font-size: 0.9rem; font-weight: 600; color: var(--text-color);">{{ __('ui.reports.screenshot_label') }}</label>
                <input type="file" name="screenshot" id="report-screenshot" accept="image/*" class="form-input" style="color: var(--text-color); font-size: 0.9rem; background: transparent; border: none; padding: 0;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" onclick="closeReportModal()" class="admin-btn admin-btn--secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem; background: transparent; border: 1px solid var(--border-color); color: var(--text-color); border-radius: 0.375rem; cursor: pointer;">
                    {{ __('ui.reports.cancel_btn') }}
                </button>
                <button type="submit" id="reportSubmitBtn" class="admin-btn admin-btn--primary" style="padding: 0.5rem 1rem; font-size: 0.9rem; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); color: white; border: none; border-radius: 0.375rem; cursor: pointer; font-weight: 600;">
                    {{ __('ui.reports.submit_btn') }}
                </button>
            </div>
        </form>
        <div id="report-success-msg" style="display: none; text-align: center; padding: 2rem 0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="3" width="48" height="48" style="margin: 0 auto 1rem auto; display: block;">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <h4 style="color: #10b981; font-weight: 700; margin-bottom: 0.5rem; font-size: 1.1rem;">{{ __('ui.reports.modal_title') }}</h4>
            <p id="report-success-text" style="color: var(--text-muted); font-size: 0.9rem; margin: 0;"></p>
            <button onclick="closeReportModal()" class="admin-btn admin-btn--secondary" style="margin-top: 1.5rem; padding: 0.5rem 1rem; background: transparent; border: 1px solid var(--border-color); color: var(--text-color); border-radius: 0.375rem; cursor: pointer;">
                {{ __('ui.reports.cancel_btn') }}
            </button>
        </div>
    </div>
</div>


<script>
function openReportModal(type, id) {
    console.log('openReportModal triggered with type:', type, 'id:', id);
    
    const reportType = document.getElementById('report-type');
    if (reportType) reportType.value = type;

    const reportId = document.getElementById('report-id');
    if (reportId) reportId.value = id;

    const reasonDetails = document.getElementById('report-reason-details');
    if (reasonDetails) reasonDetails.value = '';

    const screenshot = document.getElementById('report-screenshot');
    if (screenshot) screenshot.value = '';

    const errorMsg = document.getElementById('report-error');
    if (errorMsg) errorMsg.style.display = 'none';

    const reportForm = document.getElementById('reportForm');
    if (reportForm) reportForm.style.display = 'block';

    const successMsg = document.getElementById('report-success-msg');
    if (successMsg) successMsg.style.display = 'none';

    const modal = document.getElementById('reportModal');
    if (modal) {
        modal.style.display = 'flex';
    } else {
        console.warn('Report modal container element (#reportModal) not found in DOM.');
    }

    // Map target labels for title
    const targetLabels = {
        'article': "{{ __('ui.reports.targets.article') }}",
        'user': "{{ __('ui.reports.targets.user') }}",
        'comment': "{{ __('ui.reports.targets.comment') }}",
        'App\\Models\\Article': "{{ __('ui.reports.targets.article') }}",
        'App\\Models\\User': "{{ __('ui.reports.targets.user') }}",
        'App\\Models\\ArticleSuggestionComment': "{{ __('ui.reports.targets.comment') }}"
    };
    const targetName = targetLabels[type] || "{{ __('ui.reports.targets.article') }}";
    
    const modalTitle = document.getElementById('report-modal-title');
    if (modalTitle) {
        modalTitle.innerText = "{{ __('ui.reports.modal_title') }}: " + targetName;
    }
}

function closeReportModal() {
    const modal = document.getElementById('reportModal');
    if (modal) modal.style.display = 'none';
}

function submitReportForm(event) {
    event.preventDefault();
    const submitBtn = document.getElementById('reportSubmitBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerText = 'Submitting...';
    }
    
    const reasonDetails = document.getElementById('report-reason-details');
    const details = reasonDetails ? reasonDetails.value : '';
    const errorMsg = document.getElementById('report-error');
    
    if (details.trim().length < 5) {
        if (errorMsg) {
            errorMsg.innerText = 'Please provide details (at least 5 characters).';
            errorMsg.style.display = 'block';
        }
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerText = "{{ __('ui.reports.submit_btn') }}";
        }
        return;
    }
    
    const checkedRadio = document.querySelector('input[name="report_type"]:checked');
    const category = checkedRadio ? checkedRadio.value : 'other';
    
    const reportType = document.getElementById('report-type');
    const type = reportType ? reportType.value : '';
    
    const reportId = document.getElementById('report-id');
    const id = reportId ? reportId.value : '';
    
    const tokenEl = document.querySelector('input[name="_token"]');
    const token = tokenEl ? tokenEl.value : '';
    
    const screenshotInput = document.getElementById('report-screenshot');
    if (screenshotInput && screenshotInput.files && screenshotInput.files[0]) {
        const file = screenshotInput.files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            if (errorMsg) {
                errorMsg.innerText = 'The selected file is too large (max 5MB). Please choose a smaller image.';
                errorMsg.style.display = 'block';
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = "{{ __('ui.reports.submit_btn') }}";
            }
            return;
        }
    }
    
    const formData = new FormData();
    formData.append('reportable_type', type);
    formData.append('reportable_id', id);
    formData.append('type', category);
    formData.append('reason', details);
    if (screenshotInput && screenshotInput.files && screenshotInput.files[0]) {
        formData.append('screenshot', screenshotInput.files[0]);
    }
    
    fetch('/' + document.documentElement.lang + '/reports', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token
        },
        body: formData
    })
    .then(response => response.json().then(data => ({ status: response.status, body: data })))
    .then(res => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerText = "{{ __('ui.reports.submit_btn') }}";
        }
        if (res.status === 200 && res.body.success) {
            const reportForm = document.getElementById('reportForm');
            if (reportForm) reportForm.style.display = 'none';
            
            const successText = document.getElementById('report-success-text');
            if (successText) successText.innerText = res.body.message;
            
            const successMsg = document.getElementById('report-success-msg');
            if (successMsg) successMsg.style.display = 'block';
        } else {
            if (errorMsg) {
                errorMsg.innerText = res.body.error || 'Failed to submit report. Please try again.';
                errorMsg.style.display = 'block';
            }
        }
    })
    .catch(err => {
        console.error('Error submitting report:', err);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerText = "{{ __('ui.reports.submit_btn') }}";
        }
        if (errorMsg) {
            errorMsg.innerText = 'A network error occurred. Please try again.';
            errorMsg.style.display = 'block';
        }
    });
}

// Global delegated listener for password visibility toggles (works inside Vue-managed DOM as well)
document.addEventListener('click', function (event) {
    const btn = event.target.closest('.auth-toggle');
    if (!btn) return;

    const container = btn.closest('.auth-input-row');
    if (!container) return;

    const pw = container.querySelector('input');
    if (!pw) return;

    event.preventDefault();

    const eyeIcon = btn.querySelector('.eye-icon');
    const eyeOffIcon = btn.querySelector('.eye-off-icon');
    const showing = pw.type === 'password';

    pw.type = showing ? 'text' : 'password';
    btn.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');

    if (eyeIcon) eyeIcon.style.display = showing ? 'none' : 'block';
    if (eyeOffIcon) eyeOffIcon.style.display = showing ? 'block' : 'none';
});
</script>

    <x-cookie-consent />
    @stack('scripts')
</body>
</html>
