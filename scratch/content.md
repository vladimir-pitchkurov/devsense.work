Title: Live Content

Description: Fetched live

Source: https://devsense.work/ru/security/web-app-security

---

<!DOCTYPE html>
<html lang="ru">
<head>
    
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){ dataLayer.push(arguments); }

        // Consent — always granted for analytics
        gtag('consent', 'default', {
            analytics_storage:  'granted',
            ad_storage:         'denied',
            ad_user_data:       'denied',
            ad_personalization: 'denied'
        });

        // Initialize GA4 directly (gtag/js is loaded by Cloudflare via /j389/)
        gtag('js', new Date());
        gtag('config', 'G-VM2L2L4KGG', { send_page_view: true });
    </script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#312e81">
    <meta name="robots" content="index, follow">

    <!-- Favicons -->
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">

    <title>Уязвимости веб-приложений и методы их устранения: SQLi, внедрение команд, XSS, CSRF и IDOR | DevSense</title>

            <meta name="description" content="Защитите свои PHP-приложения от распространенных уязвимостей. Узнайте, как предотвратить SQL-инъекции, внедрение команд, XSS, CSRF и IDOR с помощью примеров безопасного кода.">
    
    <link rel="canonical" href="https://devsense.work/ru/security/web-app-security">

    
    <meta property="og:type" content="article">
    <meta property="og:title" content="Уязвимости веб-приложений и методы их устранения: SQLi, внедрение команд, XSS, CSRF и IDOR | DevSense">
            <meta property="og:description" content="Защитите свои PHP-приложения от распространенных уязвимостей. Узнайте, как предотвратить SQL-инъекции, внедрение команд, XSS, CSRF и IDOR с помощью примеров безопасного кода.">
        <meta property="og:url" content="https://devsense.work/ru/security/web-app-security">
    <meta property="og:site_name" content="DevSense">
    <meta property="og:locale" content="ru_RU">
            <meta property="og:locale:alternate" content="en_US">
            <meta property="og:locale:alternate" content="uk_UA">
            <meta property="og:locale:alternate" content="bg_BG">
            <meta property="og:locale:alternate" content="de_DE">
            <meta property="og:locale:alternate" content="fr_FR">
            <meta property="og:locale:alternate" content="es_ES">
            <meta property="og:locale:alternate" content="it_IT">
                <meta property="og:image" content="https://devsense.work/images/devsense-og-default.png">
        <meta property="og:image:secure_url" content="https://devsense.work/images/devsense-og-default.png">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:width" content="1376">
        <meta property="og:image:height" content="768">
        <meta property="og:image:alt" content="Уязвимости веб-приложений и методы их устранения: SQLi, внедрение команд, XSS, CSRF и IDOR | DevSense">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Уязвимости веб-приложений и методы их устранения: SQLi, внедрение команд, XSS, CSRF и IDOR | DevSense">
            <meta name="twitter:description" content="Защитите свои PHP-приложения от распространенных уязвимостей. Узнайте, как предотвратить SQL-инъекции, внедрение команд, XSS, CSRF и IDOR с помощью примеров безопасного кода.">
                <meta name="twitter:image" content="https://devsense.work/images/devsense-og-default.png">
        <meta name="twitter:image:alt" content="Уязвимости веб-приложений и методы их устранения: SQLi, внедрение команд, XSS, CSRF и IDOR | DevSense">
        
    <script type="application/ld+json">
        {"@context":"https://schema.org","@graph":[{"@type":"Organization","@id":"https://devsense.work#organization","name":"DevSense","url":"https://devsense.work"},{"@type":"WebSite","@id":"https://devsense.work#website","name":"DevSense","url":"https://devsense.work","publisher":{"@id":"https://devsense.work#organization"}},{"@type":"Person","@id":"https://devsense.work#author","name":"Vladimir Pichkurov","jobTitle":"Senior PHP Developer & Backend Architect","sameAs":["https://github.com/vladimir-pitchkurov","https://www.linkedin.com/in/volodimir-pichkurov-626a46150","https://devsense.work/en/authors/vladimir-pichkurov"]},{"@type":"TechArticle","headline":"Уязвимости веб-приложений и методы их устранения: SQLi, внедрение команд, XSS, CSRF и IDOR | DevSense","description":"Защитите свои PHP-приложения от распространенных уязвимостей. Узнайте, как предотвратить SQL-инъекции, внедрение команд, XSS, CSRF и IDOR с помощью примеров безопасного кода.","inLanguage":"ru","datePublished":"2026-06-19T10:38:55+00:00","dateModified":"2026-06-19T10:38:55+00:00","mainEntityOfPage":{"@type":"WebPage","@id":"https://devsense.work/ru/security/web-app-security"},"@id":"https://devsense.work/ru/security/web-app-security#article","publisher":{"@id":"https://devsense.work#organization"},"isPartOf":{"@id":"https://devsense.work#website"},"image":{"@type":"ImageObject","url":"https://devsense.work/images/devsense-og-default.png","width":1376,"height":768},"author":{"@id":"https://devsense.work#author"}},{"@type":"FAQPage","mainEntity":[{"@type":"Question","name":"Почему подготовленные выражения безопасны против SQL-инъекций?","acceptedAnswer":{"@type":"Answer","text":"Подготовленные выражения отправляют шаблон SQL-запроса и данные параметров в СУБД отдельно друг от друга. СУБД сначала компилирует SQL-запрос, гарантируя, что данные параметров никогда не будут синтаксически разобраны или выполнены как SQL-команд, независимо от того, какие символы они содержат."}},{"@type":"Question","name":"Когда безопасно использовать неэкранированную директиву Blade {!! $var !!} в Laravel?","acceptedAnswer":{"@type":"Answer","text":"Использовать `{!! $var !!}` безопасно только тогда, когда переменная содержит необработанный HTML-код, который вы сгенерировали сами или очистили с помощью надежной библиотеки очистки HTML (например, HTMLPurifier). Вы никогда не должны выводить необработанный пользовательский ввод с помощью этой директивы."}},{"@type":"Question","name":"Как выборка через связь предотвращает IDOR?","acceptedAnswer":{"@type":"Answer","text":"Выборка через связь (например, `$user-\u003Eorders()-\u003EfindOrFail($id)`) гарантирует, что запрос к базе данных естественным образом фильтрует результаты по идентификатору аутентифицированного пользователя. Злоумышленник, пытающийся получить доступ к чужому ID, получит ошибку 404 Not Found, так как запись не существует в рамках связанной модели."}}]}]}
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
    <link rel="preload" as="style" href="https://devsense.work/build/assets/app-EkjBZtgn.css" /><link rel="modulepreload" as="script" href="https://devsense.work/build/assets/app-CvoaLcIJ.js" /><link rel="stylesheet" href="https://devsense.work/build/assets/app-EkjBZtgn.css" /><script type="module" src="https://devsense.work/build/assets/app-CvoaLcIJ.js"></script>    </head>
<body
    class="page"
    id="app"
    data-a11y-theme-switcher="Текущая тема"
    data-a11y-language-select="Выберите язык"
>
    
<header class="header sticky">
    <div class="header__container">
        <a href="https://devsense.work/ru" class="header__logo">
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

                                            <a href="https://devsense.work/ru/login" class="header__cabinet-btn" title="Войти">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="header__cabinet-icon">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                    </svg>
                    <span class="header__cabinet-text">Войти</span>
                </a>
            
            <nav class="header__nav">
                <a href="https://devsense.work/ru/search" class="nav__link ">Каталог</a>
                <a href="https://devsense.work/ru/courses" class="nav__link ">Курсы</a>
                <a href="https://devsense.work/ru/quizzes" class="nav__link ">Квизы</a>
                <a href="https://devsense.work/ru/jobs" class="nav__link ">Вакансии</a>
                <a href="https://devsense.work/ru/suggestions" class="nav__link ">Suggestions</a>
            </nav>
        </div>
    </div>
</header>

<nav class="mobile-nav" aria-label="Основная навигация">
    
    <a href="https://devsense.work/ru" class="mobile-nav__item " aria-label="Главная">
        <span class="mobile-nav__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 12L12 3l9 9"/>
                <path d="M9 21V12h6v9"/>
                <path d="M3 12v9h18V12"/>
            </svg>
        </span>
        <span class="mobile-nav__label">Главная</span>
    </a>

    
    <a href="https://devsense.work/ru/search" class="mobile-nav__item " aria-label="Каталог">
        <span class="mobile-nav__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
        </span>
        <span class="mobile-nav__label">Каталог</span>
    </a>

    
    <a href="https://devsense.work/ru/courses" class="mobile-nav__item " aria-label="Курсы">
        <span class="mobile-nav__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
            </svg>
        </span>
        <span class="mobile-nav__label">Курсы</span>
    </a>

    
    <a href="https://devsense.work/ru/quizzes" class="mobile-nav__item " aria-label="Квизы">
        <span class="mobile-nav__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
                <circle cx="12" cy="12" r="10"/>
            </svg>
        </span>
        <span class="mobile-nav__label">Квизы</span>
    </a>

    
    <a href="https://devsense.work/ru/jobs" class="mobile-nav__item " aria-label="Вакансии">
        <span class="mobile-nav__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
            </svg>
        </span>
        <span class="mobile-nav__label">Вакансии</span>
    </a>

    
                    <a href="https://devsense.work/ru/login" class="mobile-nav__item " aria-label="Кабинет">
    
        <span class="mobile-nav__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
            </svg>
        </span>
        <span class="mobile-nav__label">Кабинет</span>
    </a>
</nav>

<main class="main">
    <div class="main__container">
                <div class="article__nav-wrapper no-print">
        <nav class="article__back" aria-label="Навигация по гайдам инструментов">
            <a href="https://devsense.work/ru/tools" class="article__back-link">Все инструменты</a>
        </nav>
        <button onclick="window.print()" class="btn-print" aria-label="PDF / Печать">
            <svg class="icon-print-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            <span>PDF / Печать</span>
        </button>
                    <button onclick="openReportModal('article', 47)" class="btn-report" aria-label="Report content" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-muted); border-radius: 0.375rem; padding: 0.5rem 0.75rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; transition: border-color 0.2s, color 0.2s; font-family: inherit;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                    <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                    <line x1="4" y1="22" x2="4" y2="15"></line>
                </svg>
                <span>Report</span>
            </button>
            </div>
    <div class="article-layout">
        <article class="article article-layout__main">
                            <div class="article__tags">
                                                                    <a href="https://devsense.work/ru/tags/security" class="article__tag-badge">
                            #Security
                        </a>
                                    </div>
            
            <!-- Mobile Table of Contents Accordion -->
            <details class="mobile-toc">
                <summary class="mobile-toc__summary">
                    <span>Содержание статьи</span>
                    <span class="mobile-toc__icon">▼</span>
                </summary>
                <div class="mobile-toc__content" id="article-toc-mobile"></div>
            </details>

            <div class="article__content markdown-body">
                <h1>Уязвимости веб-приложений и методы их устранения: SQLi, внедрение команд, XSS, CSRF и IDOR</h1>
<p>Разработка безопасных веб-приложений заключается не в добавлении механизмов безопасности в самом конце разработки. Она требует понимания того, как уязвимости возникают на уровне кода, и проектирования защитных барьеров для их предотвращения.</p>
<p>В этом руководстве мы разберем пять критических уязвимостей веб-приложений (SQL-инъекции, внедрение команд, межсайтовый скриптинг, CSRF и IDOR) на примере PHP и Laravel, изучим способы их эксплуатации и реализуем безопасные методы защиты.</p>
<p><strong>Сопутствующие руководства:</strong> <a href="monolith-to-microservices-architecture">Переход от монолита к микросервисной архитектуре</a> · <a href="observability-monitoring-laravel">Наблюдаемость и мониторинг в Laravel</a></p>
<h2>Содержание</h2>
<ul>
<li><a href="#sql-injection">SQL-инъекции (SQLi)</a></li>
<li><a href="#command-injection">Внедрение команд (Command Injection)</a></li>
<li><a href="#xss">Межсайтовый скриптинг (XSS)</a></li>
<li><a href="#csrf">Межсайтовая подделка запроса (CSRF)</a></li>
<li><a href="#idor">Небезопасные прямые ссылки на объекты (IDOR)</a></li>
<li><a href="#common-mistakes">Распространенные ошибки</a></li>
<li><a href="#checklist">Контрольный список</a></li>
<li><a href="#summary">Резюме</a></li>
<li><a href="#self-test-quiz">Тест для самопроверки</a></li>
</ul>
<hr />
<p><a id="sql-injection"></a></p>
<h2>SQL-инъекции (SQLi)</h2>
<p><strong>SQL-инъекции (SQL Injection)</strong> возникают, когда ненадежные пользовательские данные конкатенируются напрямую со строкой SQL-запроса. Это позволяет злоумышленникам изменять структуру запроса, обходить аутентификацию, считывать конфиденциальные данные из базы данных или удалять записи.</p>
<h3>Плохой способ: конкатенация строк и unsafe-сортировка</h3>
<p>В данном примере разработчик конкатенирует переменные ввода напрямую в запрос, а также использует неотфильтрованные данные для определения столбца сортировки.</p>
<div class="code-block" data-filepath="app/Http/Controllers/ProductController.php"><div class="code-block__header"><span class="code-block__filename">app/Http/Controllers/ProductController.php</span></div><pre class="code-block__code"><code class="language-php">declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController
{
    public function search(Request $request): array
    {
        $search = $request-&gt;input('q');
        $sortBy = $request-&gt;input('sort', 'id');

        // VULNERABLE: SQL Injection via both search input and sorting column
        $sql = &quot;SELECT * FROM products WHERE name LIKE '%{$search}%' ORDER BY {$sortBy} ASC&quot;;
        return DB::select($sql);
    }
}
</code></pre></div>
<h3>Хороший способ: подготовленные выражения и белый список столбцов</h3>
<p>Чтобы исправить это, мы должны использовать <strong>подготовленные выражения (привязку параметров)</strong> для передаваемых в запрос данных. Поскольку столбцы сортировки не могут быть параметризованы, мы должны валидировать их по строгому <strong>белому списку (allowlist)</strong>.</p>
<div class="code-block" data-filepath="app/Http/Controllers/ProductController.php"><div class="code-block__header"><span class="code-block__filename">app/Http/Controllers/ProductController.php</span></div><pre class="code-block__code"><code class="language-php">declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController
{
    private const ALLOWED_SORT_COLUMNS = ['id', 'name', 'price', 'created_at'];

    public function search(Request $request): array
    {
        $search = $request-&gt;input('q', '');
        
        // Strict sorting column allowlist fallback
        $sortBy = in_array($request-&gt;input('sort'), self::ALLOWED_SORT_COLUMNS, true) 
            ? $request-&gt;input('sort') 
            : 'id';

        // SECURE: Parameter binding for data, strict validation for identifiers
        return DB::select(
            &quot;SELECT * FROM products WHERE name LIKE :search ORDER BY {$sortBy} ASC&quot;,
            ['search' =&gt; &quot;%{$search}%&quot;]
        );
    }
}
</code></pre></div>
<hr />
<p><a id="command-injection"></a></p>
<h2>Внедрение команд</h2>
<p><strong>Внедрение команд (Command Injection)</strong> происходит, когда пользовательский ввод передается напрямую в функции системной оболочки, такие как <code>exec()</code>, <code>shell_exec()</code> или <code>system()</code>. Это позволяет злоумышленникам выполнять произвольные shell-команды на сервере с правами пользователя, от имени которого запущен веб-сервер.</p>
<h3>Плохой способ: выполнение shell-команд через конкатенацию строк</h3>
<p>В этом примере мы пытаемся преобразовать PDF-файл в миниатюру формата PNG с помощью утилиты командной строки, но передаем имя файла напрямую в shell.</p>
<div class="code-block" data-filepath="app/Services/ThumbnailGenerator.php"><div class="code-block__header"><span class="code-block__filename">app/Services/ThumbnailGenerator.php</span></div><pre class="code-block__code"><code class="language-php">declare(strict_types=1);

namespace App\Services;

class ThumbnailGenerator
{
    public function generate(string $filename): string
    {
        $outputPath = &quot;/tmp/&quot; . uniqid('thumb_', true) . &quot;.png&quot;;
        
        // VULNERABLE: Command injection if filename contains shell operators (e.g. &quot;; rm -rf /;&quot;)
        $cmd = &quot;pdftoppm -png -r 150 {$filename} {$outputPath}&quot;;
        shell_exec($cmd);

        return $outputPath;
    }
}
</code></pre></div>
<h3>Хороший способ: компонент Process с передачей аргументов в виде массива</h3>
<p>Никогда не вызывайте командную оболочку напрямую и не передавайте конкатенированные аргументы. Используйте специализированные библиотеки-обертки для управления процессами (такие как Symfony Process), которые осуществляют экранирование аргументов и их запуск без использования среды командной оболочки.</p>
<div class="code-block" data-filepath="app/Services/ThumbnailGenerator.php"><div class="code-block__header"><span class="code-block__filename">app/Services/ThumbnailGenerator.php</span></div><pre class="code-block__code"><code class="language-php">declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ThumbnailGenerator
{
    public function generate(string $filePath): string
    {
        $outputPath = &quot;/tmp/&quot; . uniqid('thumb_', true) . &quot;.png&quot;;
        
        // SECURE: Arguments are passed as an array, bypassing the shell shell interpreter
        $process = new Process(['pdftoppm', '-png', '-r', '150', $filePath, $outputPath]);
        $process-&gt;run();

        if (!$process-&gt;isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $outputPath;
    }
}
</code></pre></div>
<hr />
<p><a id="xss"></a></p>
<h2>Межсайтовый скриптинг (XSS)</h2>
<p><strong>Межсайтовый скриптинг (Cross-Site Scripting, XSS)</strong> возникает, когда приложение выводит на веб-странице неотфильтрованные данные пользователя без надлежащего экранирования. Браузер жертвы выполняет вредоносный JavaScript-код, который может украсть сессионные файлы cookie, перехватить ввод (кейлоггинг) или перенаправить пользователя.</p>
<p>Существует три типа XSS:</p>
<ul>
<li><strong>Отраженная XSS (Reflected XSS):</strong> Скрипт является частью отправляемого запроса и сразу же возвращается в ответе сервера.</li>
<li><strong>Хранимая XSS (Stored XSS):</strong> Скрипт сохраняется в базе данных (например, текст комментария) и выполняется каждый раз, когда другие пользователи просматривают страницу.</li>
<li><strong>XSS на основе DOM (DOM-based XSS):</strong> Уязвимость существует исключительно в клиентском JavaScript-коде, который выполняет недоверенные DOM-переменные.</li>
</ul>
<h3>Плохой способ: вывод неэкранированного пользовательского контента</h3>
<p>Директива Blade <code>{!! $var !!}</code> в Laravel выводит необработанный HTML, обходя стандартное экранирование XSS в Blade.</p>
<div class="code-block"><pre class="code-block__code"><code class="language-blade">&lt;!-- resources/views/profile.blade.php --&gt;
&lt;div class=&quot;user-bio&quot;&gt;
    &lt;!-- VULNERABLE: Stored XSS if bio contains &lt;script&gt;alert('xss')&lt;/script&gt; --&gt;
    {!! $user-&gt;bio !!}
&lt;/div&gt;
</code></pre></div>
<h3>Хороший способ: экранирование по умолчанию и очистка форматированного текста</h3>
<p>Всегда используйте конструкцию <code>{{ $var }}</code>, которая автоматически вызывает функцию <code>e()</code> (функцию <code>htmlspecialchars</code> в PHP) для экранирования вывода. Если вам необходимо вывести форматированный текст, предоставленный пользователем, пропустите его через надежную библиотеку очистки HTML (например, HTMLPurifier).</p>
<div class="code-block"><pre class="code-block__code"><code class="language-blade">&lt;!-- resources/views/profile.blade.php --&gt;
&lt;div class=&quot;user-bio&quot;&gt;
    &lt;!-- SECURE: Automatically escaped by Blade --&gt;
    {{ $user-&gt;bio }}
&lt;/div&gt;

&lt;div class=&quot;user-rich-content&quot;&gt;
    &lt;!-- SECURE: Outputting raw html only AFTER strict HTML purification --&gt;
    {!! clean($user-&gt;rich_description) !!}
&lt;/div&gt;
</code></pre></div>
<hr />
<p><a id="csrf"></a></p>
<h2>Межсайтовая подделка запроса (CSRF)</h2>
<p><strong>Межсайтовая подделка запроса (Cross-Site Request Forgery, CSRF)</strong> заставляет браузер авторизованного пользователя выполнять действия, изменяющие состояние (например, изменение пароля или совершение платежей), на веб-ресурсе, в котором пользователь в данный момент аутентифицирован. Браузер автоматически прикрепляет сессионные файлы cookie к межсайтовым запросам, подтверждая их подлинность.</p>
<h3>Плохой способ: изменение состояния через GET-запросы</h3>
<p>GET-запросы всегда должны быть <strong>идемпотентными</strong> (безопасными для многократного выполнения без изменения состояния). Выполнение обновлений данных в GET-маршрутах делает CSRF-атаки тривиальными.</p>
<div class="code-block" data-filepath="routes/web.php"><div class="code-block__header"><span class="code-block__filename">routes/web.php</span></div><pre class="code-block__code"><code class="language-php">// VULNERABLE: Anyone can link to /profile/delete in an img tag to delete an account
Route::get('/profile/delete', [ProfileController::class, 'destroy']);
</code></pre></div>
<h3>Хороший способ: маршруты POST/DELETE с CSRF-токенами</h3>
<p>Всегда используйте HTTP-методы, изменяющие состояние (POST, PUT, DELETE), и требуйте секретный, криптографически стойкий токен, который не может быть угадан сторонними сайтами.</p>
<div class="code-block"><pre class="code-block__code"><code class="language-blade">&lt;!-- resources/views/profile.blade.php --&gt;
&lt;!-- SECURE: Form triggers a POST request and generates a hidden CSRF token --&gt;
&lt;form action=&quot;{{ route('profile.destroy') }}&quot; method=&quot;POST&quot;&gt;
    @csrf
    @method('DELETE')
    &lt;button type=&quot;submit&quot;&gt;Delete Account&lt;/button&gt;
&lt;/form&gt;
</code></pre></div>
<hr />
<p><a id="idor"></a></p>
<h2>Небезопасные прямые ссылки на объекты (IDOR)</h2>
<p><strong>IDOR (Insecure Direct Object Reference)</strong> возникает, когда приложение предоставляет доступ к ключу базы данных или идентификатору ресурса напрямую пользователям, не проверяя, имеет ли запрашивающий пользователь права на доступ к этому конкретному ресурсу.</p>
<h3>Плохой способ: глобальный запрос без проверки авторизации</h3>
<p>В этом примере любой авторизованный пользователь может получить доступ к данным чужого счета, просто изменив параметр <code>{id}</code> в URL.</p>
<div class="code-block" data-filepath="app/Http/Controllers/InvoiceController.php"><div class="code-block__header"><span class="code-block__filename">app/Http/Controllers/InvoiceController.php</span></div><pre class="code-block__code"><code class="language-php">declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController
{
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        // VULNERABLE: Retrieves invoice without checking user relationship or authorization
        $invoice = Invoice::findOrFail($id);
        
        return response()-&gt;json($invoice);
    }
}
</code></pre></div>
<h3>Хороший способ: запросы с ограничением области видимости (связей) и авторизация через Gate</h3>
<p>Разграничивайте получение ресурсов, загружая их через область видимости связей (relationship scope) модели пользователя, или проверяйте права доступа с помощью механизмов авторизации (Gates/Policies).</p>
<div class="code-block" data-filepath="app/Http/Controllers/InvoiceController.php"><div class="code-block__header"><span class="code-block__filename">app/Http/Controllers/InvoiceController.php</span></div><pre class="code-block__code"><code class="language-php">declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvoiceController
{
    public function show(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        // SECURE Method A: Query scoped directly to the authenticated user
        $invoice = $request-&gt;user()-&gt;invoices()-&gt;findOrFail($id);

        // SECURE Method B: Global lookup but validated with a Laravel Policy
        // $invoice = Invoice::findOrFail($id);
        // Gate::authorize('view', $invoice);
        
        return response()-&gt;json($invoice);
    }
}
</code></pre></div>
<hr />
<p><a id="common-mistakes"></a></p>
<h2>Распространенные ошибки</h2>
<ol>
<li><strong>Экранирование вместо параметризации:</strong> Заблуждение о том, что использование <code>addslashes()</code> или простых регулярных выражений для переменных защищает запросы. Всегда используйте параметризацию.</li>
<li><strong>Изменение состояния в GET-запросах:</strong> Создание API/веб-эндпоинтов, выполняющих действия по удалению или обновлению данных с использованием метода HTTP GET.</li>
<li><strong>Мега-шлюзы для валидации:</strong> Перекладывание валидации на предмет XSS/SQLi на плечи WAF или шлюзов безопасности вместо написания надежной валидации непосредственно в контроллере приложения.</li>
<li><strong>IDOR в AJAX-запросах:</strong> Обеспечение безопасности основных маршрутов HTML-страниц, но раскрытие неавторизованных идентификаторов ресурсов в конечных точках API / AJAX (JSON).</li>
</ol>
<hr />
<p><a id="checklist"></a></p>
<h2>Контрольный список</h2>
<ol>
<li><strong>Безопасность запросов:</strong> Используете ли вы конкатенацию переменных внутри сырых выражений <code>DB::select</code> or <code>whereRaw</code>?</li>
<li><strong>Выполнение команд оболочки:</strong> Можете ли вы заменить shell-команды стандартными библиотеками PHP? Если нет, передаются ли аргументы в виде массива?</li>
<li><strong>Вывод Blade:</strong> Используете ли вы <code>{!! !!}</code> в шаблонах Blade без предварительной очистки с помощью HTMLPurifier?</li>
<li><strong>Проверка авторизации:</strong> Проверяет ли каждый метод контроллера, загружающий ресурс, принадлежит ли этот ресурс текущему пользователю?</li>
</ol>
<hr />
<p><a id="summary"></a></p>
<h2>Резюме</h2>
<p>Безопасные веб-приложения строго валидируют входные данные и используют стратегию глубокой защиты (defense-in-depth). Предотвращайте <strong>SQL-инъекции</strong> с помощью подготовленных выражений и белых списков столбцов. Избегайте <strong>внедрения команд</strong>, используя массивы аргументов внутри процессов-оберток. Блокируйте <strong>XSS</strong>, экранируя выводимые переменные средствами шаблонизатора. Пресекайте <strong>CSRF</strong>, изолируя изменения состояния в запросах POST/DELETE, защищенных токенами. Устраняйте <strong>IDOR</strong>, ограничивая выборку рамками связей аутентифицированного пользователя или выполняя проверки политик авторизации.</p>
<hr />
<p><a id="self-test-quiz"></a></p>
<h2>Тест для самопроверки</h2>
<h3>Вопрос 1: В чем основное различие между экранированием и параметризацией для SQL-запросов?</h3>
<ul>
<li>A) Экранирование выполняется на сервере базы данных, а параметризация обрабатывается внутри движка PHP.</li>
<li>B) Экранирование изменяет специальные символы внутри строк запроса, чтобы сделать их безопасными, тогда как параметризация отправляет структуру запроса и параметры в СУБД отдельно друг от друга.</li>
<li>C) Параметризация совместима только с базами данных PostgreSQL.</li>
</ul>
<details>
<summary>Нажмите, чтобы увидеть ответ</summary>
<p><strong>Ответ: B</strong>
Экранирование представляет собой манипуляцию со строками, которая подвержена ошибкам парсера и методам обхода. Параметризация — это функция протокола, при которой структура SQL и значения данных отправляются независимо друг от друга, что гарантирует невозможность изменения шаблона запроса передаваемыми данными.</p>
</details>
<h3>Вопрос 2: Почему GET-запросы уязвимы для CSRF, даже если они защищены токенами?</h3>
<ul>
<li>A) Браузеры не поддерживают GET-формы.</li>
<li>B) GET-запросы раскрывают токены в истории URL, логах браузера и заголовках Referer, и в любом случае никогда не должны использоваться для операций, изменяющих состояние.</li>
<li>C) GET-запросы автоматически обходят посредник (middleware) проверки CSRF-токенов в Laravel.</li>
</ul>
<details>
<summary>Нажмите, чтобы увидеть ответ</summary>
<p><strong>Ответ: B</strong>
GET-запросы предназначены для идемпотентного использования (безопасного чтения данных). Если GET-запрос изменяет состояние, злоумышленники могут внедрить целевой URL-адрес в межсайтовые ссылки или теги изображений, автоматически инициируя изменение состояния без согласия пользователя.</p>
</details>
<h3>Вопрос 3: Как ограничение запроса к базе данных связью с пользователем предотвращает IDOR?</h3>
<ul>
<li>A) Это шифрует первичные ключи базы данных.</li>
<li>B) Это блокирует запрос на уровне брандмауэра.</li>
<li>C) Это гарантирует, что SQL-запрос естественным образом фильтрует записи, используя ID аутентифицированного пользователя в качестве поискового ограничения, делая записи других пользователей недосягаемыми.</li>
</ul>
<details>
<summary>Нажмите, чтобы увидеть ответ</summary>
<p><strong>Ответ: C</strong>
Ограничение запросов (например, <code>$user-&gt;invoices()-&gt;findOrFail($id)</code>) добавляет условие <code>WHERE user_id = ?</code> к SQL-запросу. Если злоумышленник попытается получить идентификатор ресурса, принадлежащий кому-то другому, запрос не вернет никаких записей, что приведет к безопасной ошибке 404.</p>
</details>

            </div>

            <!-- Like & Dislike Section -->
                            <div class="article-reactions" style="margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 1rem; padding-bottom: 1.5rem;">
                                        <button onclick="toggleReaction(47, false)" 
                            id="like-btn"
                            class="btn-reaction " 
                            style="display: inline-flex; align-items: center; gap: 0.5rem; background: transparent; border: 1px solid var(--border-color); color: var(--text-color); border-radius: 0.375rem; padding: 0.5rem 1rem; cursor: pointer; transition: background-color 0.2s, border-color 0.2s; font-family: inherit;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                        </svg>
                        <span id="likes-count">0</span>
                    </button>
                    
                    <button onclick="toggleReaction(47, true)" 
                            id="dislike-btn"
                            class="btn-reaction " 
                            style="display: inline-flex; align-items: center; gap: 0.5rem; background: transparent; border: 1px solid var(--border-color); color: var(--text-color); border-radius: 0.375rem; padding: 0.5rem 1rem; cursor: pointer; transition: background-color 0.2s, border-color 0.2s; font-family: inherit;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-3"></path>
                        </svg>
                                            </button>
                </div>

                <script>
                function toggleReaction(articleId, isDislike) {
                    if (!false) {
                        window.location.href = "https://devsense.work/login?locale=ru";
                        return;
                    }

                    fetch("https://devsense.work/ru/likes", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': 'DvCXrvrwtJRPgyyDvDIoeGLfUbJXJDrpV4TqXTvD',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            likeable_id: articleId,
                            likeable_type: 'article',
                            is_dislike: isDislike ? 1 : 0
                        })
                    })
                    .then(response => {
                        if (response.status === 401) {
                            window.location.href = "https://devsense.work/login?locale=ru";
                            return;
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.success) {
                            document.getElementById('likes-count').innerText = data.likes_count;
                            
                            const dislikesEl = document.getElementById('dislikes-count');
                            if (dislikesEl && data.dislikes_count !== null) {
                                dislikesEl.innerText = data.dislikes_count;
                            }

                            const likeBtn = document.getElementById('like-btn');
                            const dislikeBtn = document.getElementById('dislike-btn');
                            
                            if (data.status === 'removed') {
                                likeBtn.classList.remove('active');
                                likeBtn.querySelector('svg').setAttribute('fill', 'none');
                                dislikeBtn.classList.remove('active');
                                dislikeBtn.querySelector('svg').setAttribute('fill', 'none');
                            } else if (data.status === 'added' || data.status === 'switched') {
                                if (isDislike) {
                                    dislikeBtn.classList.add('active');
                                    dislikeBtn.querySelector('svg').setAttribute('fill', 'currentColor');
                                    likeBtn.classList.remove('active');
                                    likeBtn.querySelector('svg').setAttribute('fill', 'none');
                                } else {
                                    likeBtn.classList.add('active');
                                    likeBtn.querySelector('svg').setAttribute('fill', 'currentColor');
                                    dislikeBtn.classList.remove('active');
                                    dislikeBtn.querySelector('svg').setAttribute('fill', 'none');
                                }
                            }
                        }
                    })
                    .catch(err => console.error('Error toggling reaction:', err));
                }
                </script>

                <style>
                .btn-reaction.active {
                    background-color: var(--primary-color) !important;
                    border-color: var(--primary-color) !important;
                    color: #fff !important;
                }
                </style>

                <div class="suggestions-section" id="suggestions-block" style="margin-top: 3rem; padding: 2rem; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); border-radius: 1rem; backdrop-filter: blur(10px);">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; margin: 0; color: var(--text-color); font-family: 'Outfit', sans-serif;">Предложения сообщества | DevSense</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.25rem 0 0 0;">Help us improve this article. Suggest edits, additions, or corrections.</p>
        </div>
    </div>

    <!-- Suggestions List -->
    <div class="suggestions-list" style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.95rem;">
                No suggestions submitted yet. Be the first to share your feedback!
            </div>
            </div>

    <!-- Submit Suggestion Form / Cabinet CTA -->
    <div style="margin-top: 2.5rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                    <div style="text-align: center; padding: 1.5rem; background: rgba(255, 255, 255, 0.01); border: 1px dashed var(--border-color); border-radius: 0.5rem;">
                <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0 0 1rem 0;">Please sign in to submit suggestions or vote.</p>
                <a href="https://devsense.work/ru/login" class="admin-btn admin-btn--primary" style="display: inline-block; padding: 0.5rem 1.25rem; font-size: 0.9rem; text-decoration: none;">
                    Войти
                </a>
            </div>
            </div>
</div>

<script>
function toggleSuggestionVote(suggestionId, button) {
    const isVoted = button.getAttribute('data-voted') === 'true';
    const voteTextSpan = button.querySelector('.vote-text');
    const voteCountSpan = button.querySelector('.vote-count');
    const csrfToken = document.querySelector('input[name="_token"]').value;

    button.disabled = true;

    fetch('/' + document.documentElement.lang + '/suggestions/' + suggestionId + '/vote', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => {
        if (response.status === 401) {
            window.location.href = '/' + document.documentElement.lang + '/login';
            return;
        }
        return response.json();
    })
    .then(data => {
        button.disabled = false;
        if (data && data.success) {
            button.setAttribute('data-voted', data.voted ? 'true' : 'false');
            if (data.voted) {
                button.style.background = 'rgba(99, 102, 241, 0.2)';
                button.style.color = 'var(--primary-color)';
                button.querySelector('svg').setAttribute('fill', 'currentColor');
                voteTextSpan.innerText = "Upvoted";
            } else {
                button.style.background = 'transparent';
                button.style.color = 'var(--text-color)';
                button.querySelector('svg').setAttribute('fill', 'none');
                voteTextSpan.innerText = "Upvote";
            }
            voteCountSpan.innerText = '(' + data.count + ')';
        }
    })
    .catch(err => {
        button.disabled = false;
        console.error('Error voting:', err);
    });
}
</script>
                    </article>

        <!-- Desktop Sticky Table of Contents Sidebar -->
        <aside class="article-layout__sidebar" aria-label="Содержание статьи">
            <div class="sticky-toc" id="article-toc">
                <div class="sticky-toc__title">Содержание статьи</div>
                <!-- ToC content populated by JS -->
            </div>
        </aside>
    </div>
    </div>
</main>

<footer class="footer">
    <div class="footer__container">
        <nav class="footer__nav" aria-label="Основные разделы">
            <ul class="footer__nav-list">
                <li class="footer__nav-item">
                    <a href="https://devsense.work/ru" class="footer__nav-link">Главная</a>
                </li>
                <li class="footer__nav-item">
                    <a href="https://devsense.work/ru/search" class="footer__nav-link">Каталог</a>
                </li>
                <li class="footer__nav-item">
                    <a href="https://devsense.work/ru/courses" class="footer__nav-link">Курсы</a>
                </li>
                <li class="footer__nav-item">
                    <a href="https://devsense.work/ru/quizzes" class="footer__nav-link">Квизы</a>
                </li>
                <li class="footer__nav-item">
                    <a href="https://devsense.work/ru/jobs" class="footer__nav-link">Вакансии</a>
                </li>
                <li class="footer__nav-item">
                    <a href="https://devsense.work/ru/suggestions" class="footer__nav-link">Suggestions</a>
                </li>
                <li class="footer__nav-item">
                    <a href="https://devsense.work/ru/terms" class="footer__nav-link">Условия использования</a>
                </li>
                <li class="footer__nav-item">
                    <a href="https://devsense.work/ru/privacy" class="footer__nav-link">Политика конфиденциальности</a>
                </li>
            </ul>
        </nav>
                            <nav class="footer__locales" aria-label="Та же страница на других языках">
                <p class="footer__locales-label">Язык:</p>
                <ul class="footer__locales-list">
                                            <li class="footer__locales-item">
                                                            <span class="footer__locales-current" aria-current="true">RU</span>
                                                    </li>
                                            <li class="footer__locales-item">
                                                            <a
                                    href="https://devsense.work/en/security/web-app-security"
                                    class="footer__locales-link"
                                    hreflang="en"
                                    lang="en"
                                >EN</a>
                                                    </li>
                                            <li class="footer__locales-item">
                                                            <a
                                    href="https://devsense.work/ua/security/web-app-security"
                                    class="footer__locales-link"
                                    hreflang="uk"
                                    lang="ua"
                                >UA</a>
                                                    </li>
                                            <li class="footer__locales-item">
                                                            <a
                                    href="https://devsense.work/bg/security/web-app-security"
                                    class="footer__locales-link"
                                    hreflang="bg"
                                    lang="bg"
                                >BG</a>
                                                    </li>
                                            <li class="footer__locales-item">
                                                            <a
                                    href="https://devsense.work/de/security/web-app-security"
                                    class="footer__locales-link"
                                    hreflang="de"
                                    lang="de"
                                >DE</a>
                                                    </li>
                                            <li class="footer__locales-item">
                                                            <a
                                    href="https://devsense.work/fr/security/web-app-security"
                                    class="footer__locales-link"
                                    hreflang="fr"
                                    lang="fr"
                                >FR</a>
                                                    </li>
                                            <li class="footer__locales-item">
                                                            <a
                                    href="https://devsense.work/es/security/web-app-security"
                                    class="footer__locales-link"
                                    hreflang="es"
                                    lang="es"
                                >ES</a>
                                                    </li>
                                            <li class="footer__locales-item">
                                                            <a
                                    href="https://devsense.work/it/security/web-app-security"
                                    class="footer__locales-link"
                                    hreflang="it"
                                    lang="it"
                                >IT</a>
                                                    </li>
                                    </ul>
            </nav>
                <div class="footer__contacts">
            <span class="footer__contact-item">
                Учредитель и гендиректор: <strong>Vladimir Pichkurov</strong> (<a href="/cdn-cgi/l/email-protection#42342e23262b2f2b300226273431272c31276c352d3029" class="footer__contact-link"><span class="__cf_email__" data-cfemail="13657f72777a7e7a615377766560767d60763d647c6178">[email&#160;protected]</span></a>)
            </span>
            <span class="footer__contact-item">
                Предложения и отзывы: <a href="/cdn-cgi/l/email-protection#d5a6a0a5a5baa7a195b8b4bcb9fbb1b0a3a6b0bba6b0fba2baa7be" class="footer__contact-link"><span class="__cf_email__" data-cfemail="becdcbceced1cccafed3dfd7d290dadbc8cddbd0cddb90c9d1ccd5">[email&#160;protected]</span></a>
            </span>
        </div>
        <p class="footer__copyright">&copy; 2026 DevSense. All rights reserved.</p>
    </div>
</footer>

<!-- Content Report Modal -->
<div id="reportModal" class="report-modal" style="display: none;">
    <div class="report-modal__overlay" onclick="closeReportModal()"></div>
    <div class="report-modal__container">
        <header class="report-modal__header">
            <h3 class="report-modal__title" id="report-modal-title">Пожаловаться</h3>
            <button onclick="closeReportModal()" class="report-modal__close" aria-label="Close modal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </header>
        <form id="reportForm" onsubmit="submitReportForm(event)">
            <input type="hidden" name="_token" value="DvCXrvrwtJRPgyyDvDIoeGLfUbJXJDrpV4TqXTvD" autocomplete="off">            <input type="hidden" name="reportable_type" id="report-type">
            <input type="hidden" name="reportable_id" id="report-id">
            
            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                <label class="form-label" style="text-transform: none; font-size: 0.9rem; font-weight: 600; color: var(--text-color);">Причина жалобы</label>
                <div class="report-reasons" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="report_type" value="spam" checked>
                        <span>Спам</span>
                    </label>
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="report_type" value="insult">
                        <span>Оскорбление или домогательство</span>
                    </label>
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="report_type" value="promo">
                        <span>Реклама или промо</span>
                    </label>
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="report_type" value="plagiarism">
                        <span>Плагиат / Скопированный контент</span>
                    </label>
                    <label class="reason-option" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; cursor: pointer; color: var(--text-color);">
                        <input type="radio" name="report_type" value="other">
                        <span>Другое (укажите ниже)</span>
                    </label>
                </div>
            </div>

            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                <label for="report-reason-details" class="form-label" style="text-transform: none; font-size: 0.9rem; font-weight: 600; color: var(--text-color);">Детальное описание</label>
                <textarea name="reason" id="report-reason-details" rows="4" class="form-input" style="background: var(--page-bg); border: 1px solid var(--border-color); color: var(--text-color); width: 100%; border-radius: 0.375rem; padding: 0.5rem; box-sizing: border-box; font-family: inherit; font-size: 0.95rem;" placeholder="Пожалуйста, укажите больше деталей (минимум 5 символов)..." required></textarea>
                <span id="report-error" style="color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem; display: none;"></span>
            </div>

            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                <label for="report-screenshot" class="form-label" style="text-transform: none; font-size: 0.9rem; font-weight: 600; color: var(--text-color);">Прикрепить скриншот (необязательно, макс. 5МБ)</label>
                <input type="file" name="screenshot" id="report-screenshot" accept="image/*" class="form-input" style="color: var(--text-color); font-size: 0.9rem; background: transparent; border: none; padding: 0;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem;">
                <button type="button" onclick="closeReportModal()" class="admin-btn admin-btn--secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem; background: transparent; border: 1px solid var(--border-color); color: var(--text-color); border-radius: 0.375rem; cursor: pointer;">
                    Отмена
                </button>
                <button type="submit" id="reportSubmitBtn" class="admin-btn admin-btn--primary" style="padding: 0.5rem 1rem; font-size: 0.9rem; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); color: white; border: none; border-radius: 0.375rem; cursor: pointer; font-weight: 600;">
                    Отправить жалобу
                </button>
            </div>
        </form>
        <div id="report-success-msg" style="display: none; text-align: center; padding: 2rem 0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="3" width="48" height="48" style="margin: 0 auto 1rem auto; display: block;">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            <h4 style="color: #10b981; font-weight: 700; margin-bottom: 0.5rem; font-size: 1.1rem;">Пожаловаться</h4>
            <p id="report-success-text" style="color: var(--text-muted); font-size: 0.9rem; margin: 0;"></p>
            <button onclick="closeReportModal()" class="admin-btn admin-btn--secondary" style="margin-top: 1.5rem; padding: 0.5rem 1rem; background: transparent; border: 1px solid var(--border-color); color: var(--text-color); border-radius: 0.375rem; cursor: pointer;">
                Отмена
            </button>
        </div>
    </div>
</div>


<script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script><script>
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
        'article': "Статья",
        'user': "Автор/Пользователь",
        'comment': "Комментарий к предложению",
        'App\\Models\\Article': "Статья",
        'App\\Models\\User': "Автор/Пользователь",
        'App\\Models\\ArticleSuggestionComment': "Комментарий к предложению"
    };
    const targetName = targetLabels[type] || "Статья";
    
    const modalTitle = document.getElementById('report-modal-title');
    if (modalTitle) {
        modalTitle.innerText = "Пожаловаться: " + targetName;
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
            submitBtn.innerText = "Отправить жалобу";
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
                submitBtn.innerText = "Отправить жалобу";
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
            submitBtn.innerText = "Отправить жалобу";
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
            submitBtn.innerText = "Отправить жалобу";
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

    <div
    id="cookie-banner"
    class="cookie-banner"
    role="status"
    aria-live="polite"
    style="display:none"
>
    <div class="cookie-banner__inner">
        <p class="cookie-banner__message">
            Мы используем файлы cookie для улучшения вашего опыта. Продолжая использовать сайт, вы соглашаетесь с нашей <a href="https://devsense.work/ru/privacy" class="cookie-banner__link">Политикой конфиденциальности</a>.
        </p>
        <button id="cookie-accept-btn" class="cookie-banner__btn" type="button">
            Понятно
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
    <script defer src="https://static.cloudflareinsights.com/beacon.min.js/v4513226cdae34746b4dedf0b4dfa099e1781791509496" integrity="sha512-ZE9pZaUXND66v380QUtch/5sE9tPFh2zg45pR2PB0CVkCtOREv2AJKkSidISWkysEuQ0EH8faUU5du78bx87UQ==" data-cf-beacon='{"version":"2024.11.0","token":"942bf2ad7fdb476aa918362f462836d2","r":1,"server_timing":{"name":{"cfCacheStatus":true,"cfEdge":true,"cfExtPri":true,"cfL4":true,"cfOrigin":true,"cfSpeedBrain":true},"location_startswith":null}}' crossorigin="anonymous"></script>
</body>
</html>


