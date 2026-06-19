---
title: "Налаштування безпеки серверів та інфраструктури: Заголовки безпеки, TLS, обмеження швидкості запитів (Rate Limiting) та управління секретами | DevSense"
description: "Убезпечення вашої вебсерверної та прикладної інфраструктури. Дізнайтеся, як налаштувати заголовки безпеки HTTP, шифри TLS, обмеження швидкості запитів, безпечне зберігання секретів та ізоляцію бази даних."
published: 2026-06-19
faq:
  - question: "Чому слід уникати використання env() поза конфігураційними файлами в Laravel?"
    answer: "У продакшені Laravel кешує файли конфігурації в один оптимізований файл. Після кешування Laravel повністю припиняє зчитування файлу .env, і будь-який прямий виклик env() в коді додатка повертатиме null, що призведе до критичних збоїв конфігурації під час виконання."
  - question: "Яка основна роль заголовка Content-Security-Policy (CSP)?"
    answer: "CSP діє як потужний рівень захисту від міжсайтового скриптингу (XSS) та атак впровадження даних, дозволяючи адміністраторам обмежувати ресурси (такі як JavaScript, CSS, зображення), які браузеру дозволено завантажувати та виконувати для певної сторінки."
  - question: "Як Nginx захищає від брутфорс-атак та атак типу «відмова в обслуговуванні» (DoS)?"
    answer: "Обмеження швидкості Nginx використовує алгоритм Leaky Bucket (діряве відро) для обмеження частоти вхідних запитів з однієї IP-адреси, затримуючи або блокуючи запити, які перевищують визначені зони."
---

# Налаштування безпеки серверів та інфраструктури: Заголовки безпеки, TLS, обмеження швидкості запитів та управління секретами

Хоча безпека коду додатка є критично важливою, інфраструктура, на якій він розміщений, також має бути захищеною. Безпечне бекенд-середовище вимагає надійного захисту транспортування даних, обмеження швидкості запитів, мережевої ізоляції та захищених секретів оточення.

У цьому посібнику ми впровадимо заголовки безпеки за допомогою посередника (middleware) Laravel, налаштуємо обмеження швидкості в Nginx та Laravel, захистимо змінні оточення та обговоримо підвищення безпеки мережі.

**Супутні посібники:** [Вразливості вебдодатків та їх усунення](web-app-security) · [SSRF та безпека завантаження файлів](ssrf-and-file-upload-security)

## Зміст

* [Заголовки безпеки HTTP](#security-headers)
* [Посилення безпеки SSL/TLS](#ssl-tls-hardening)
* [Обмеження швидкості запитів (Nginx та Laravel)](#rate-limiting)
* [Безпечне управління секретами](#secrets-management)
* [Ізоляція бази даних](#database-isolation)
* [Типові помилки](#common-mistakes)
* [Контрольний список](#checklist)
* [Підсумок](#summary)
* [Тест для самоперевірки](#self-test-quiz)

---

<a id="security-headers"></a>
## Заголовки безпеки HTTP

Заголовки безпеки HTTP вказують браузеру, як поводитися під час взаємодії з вашим сайтом, нейтралізуючи поширені вектори атак, такі як клікджекінг (Clickjacking), міжсайтовий скриптинг (XSS) та підміна типу MIME (MIME-sniffing).

Ми можемо застосувати ці заголовки глобально в Laravel за допомогою власного посередника (middleware):

```php
// app/Http/Middleware/SecureHeadersMiddleware.php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 1. Prevent Clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // 2. Prevent MIME-type Sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 3. Control referrer information sent in HTTP headers
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 4. Force HTTPS (HTTP Strict Transport Security - HSTS)
        // 31536000 seconds = 1 year. Include subdomains and preloading.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // 5. Restrict permissions (Permissions-Policy)
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');

        // 6. Content Security Policy (CSP)
        // Allow scripts and styles only from self and specific secure CDNs
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' https://trusted-cdn.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; frame-ancestors 'none';"
        );

        return $response;
    }
}
```

---

<a id="ssl-tls-hardening"></a>
## Посилення безпеки SSL/TLS

Правильне налаштування SSL/TLS гарантує, що дані під час транспортування не можуть бути перехоплені або змінені. Ви повинні вимкнути застарілі версії TLS та обмежити використання серверних шифрів безпечними.

Ось блок конфігурації безпечного TLS для Nginx:

```nginx
# nginx.conf
server {
    listen 443 ssl http2;
    server_name example.com;

    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    # Restrict to secure TLS versions (TLS v1.2 and TLS v1.3 only)
    ssl_protocols TLSv1.2 TLSv1.3;

    # Enforce secure cipher suites
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers on;

    # Enable Session Tickets & Caching for performance
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    # Enable OCSP Stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    resolver 8.8.8.8 8.8.4.4 valid=300s;
    resolver_timeout 5s;
}
```

---

<a id="rate-limiting"></a>
## Обмеження швидкості запитів (Nginx та Laravel)

Обмеження швидкості запитів (Rate limiting) запобігає зловживанням унаслідок DDoS-атак, спроб брутфорсу входу та ботів-парсерів. Захист має бути налаштований як на рівні вебсервера (Nginx), так і на рівні додатка (Laravel).

### 1. Обмеження швидкості в Nginx (на основі IP)

Nginx обробляє обмеження швидкості до того, як запити потраплять до PHP-FPM, заощаджуючи ресурси сервера:

```nginx
# nginx.conf (global context)
limit_req_zone $binary_remote_addr zone=login_limit:10m rate=5r/m;

# server context
location /login {
    # Apply limit with a burst margin of 5 requests
    limit_req zone=login_limit burst=5 nodelay;
    
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 2. Обмеження швидкості в Laravel

Laravel дозволяє динамічне обмеження швидкості запитів на основі конкретного користувача безпосередньо в коді додатка. Налаштуйте лімітери в `app/Providers/AppServiceProvider.php` (або `RouteServiceProvider.php`):

```php
// app/Providers/AppServiceProvider.php
declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Global API limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Dedicated sensitive action limiter (e.g., login/register)
        RateLimiter::for('auth', function (Request $request) {
            $email = (string) $request->input('email');
            return Limit::perMinute(5)->by($email ?: $request->ip());
        });
    }
}
```

Застосуйте цей лімітер у вашому файлі маршрутизації:

```php
// routes/api.php
Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});
```

---

<a id="secrets-management"></a>
## Безпечне управління секретами

Розкриття секретних ключів API або паролів баз даних може призвести до катастрофічних витоків даних.

### 1. Уникайте використання `env()` у коді додатка

Ніколи не викликайте `env()` поза межами конфігураційних файлів (які знаходяться в директорії `config/`). Коли ви кешуєте конфігурацію в продакшені (`php artisan config:cache`), Laravel завантажує значення з `.env` один раз, кешує їх і вимикає читання файлу `.env` під час роботи додатка. Будь-який прямий виклик `env()` після кешування повертатиме `null`.

**Правильний шаблон:**
```php
// config/services.php
return [
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
    ],
];

// app/Services/PaymentService.php
// Access using config() helper, not env()
$secretKey = config('services.stripe.secret');
```

### 2. Безпечні права доступу до `.env`

Переконайтеся, що сторонні користувачі в хост-системі не можуть прочитати файл `.env`, який містить ключі конфігурації:

```bash
chmod 600 .env
```

---

<a id="database-isolation"></a>
## Ізоляція бази даних

Ваша база даних ніколи не повинна бути доступною з публічного інтернету.

1. **Прив'язка до мережі (Network Binding):** Змусьте сервер бази даних слухати лише внутрішні інтерфейси або локальну петлю (loopback). У конфігурації PostgreSQL (`postgresql.conf`) або MySQL (`my.cnf`) прив'яжіть параметри до локальних адрес:
   ```ini
   # mysql.cnf
   bind-address = 127.0.0.1
   ```
2. **Правила брандмауера:** Блокуйте вхідні зовнішні порти (такі як порт MySQL 3306 або порт PostgreSQL 5432) за допомогою правил брандмауера (UFW або хмарних груп безпеки). Дозволяйте доступ лише з приватної IP-адреси вебсервера.
3. **SSL/TLS бази даних:** Якщо база даних та вебдодаток розташовані на різних серверах у межах приватної підмережі, налаштуйте обов'язкове SSL-з'єднання між Laravel та базою даних:
   ```php
   // config/database.php
   'mysql' => [
       // ...
       'options' => [
           PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
           PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
       ],
   ],
   ```

---

## Типові помилки

1. **Виклик `env()` під час виконання:** Виклик `env('API_KEY')` всередині контролерів або завдань (jobs), що призводить до збоїв конфігурації, коли увімкнено кешування в продакшені.
2. **Відсутність `Strict-Transport-Security` (HSTS):** Забування про відправку заголовків HSTS, що робить користувачів вразливими до атак зі зниженням протоколу шифрування (SSL-stripping).
3. **Слабка конфігурація TLS:** Збереження підтримки TLS 1.0 або 1.1 на серверах для забезпечення зворотної сумісності, що компрометує загальне шифрування системи.
4. **Публічно відкритий порт 3306/5432:** Залишення порту бази даних відкритим для публічного інтернету, що піддає його скануванню, атакам методом підбору (dictionary attacks) та переповненню з'єднань.

---

## Контрольний список

1. **Відсутність викликів `env()`:** Чи провели ви аудит кодової бази, щоб переконатися, що всі пошуки змінних оточення виконуються виключно всередині конфігураційних файлів?
2. **Заголовки безпеки HTTP:** Чи надсилає ваш вебдодаток заголовки `X-Frame-Options`, `X-Content-Type-Options` та визначений `Content-Security-Policy`?
3. **Обмеження версії TLS:** Чи обмежує ваша конфігурація Nginx протоколи лише версіями TLS v1.2 та v1.3?
4. **Обмеження швидкості запитів:** Чи захищені всі публічні ендпоінти автентифікації та важливі API-ендпоінти обмеженням швидкості запитів?
5. **Мережевий брандмауер:** Чи вимкнено публічний мережевий доступ до баз даних, систем кешування (Redis/Memcached) та внутрішніх мікросервісів?

---

## Підсумок

Убезпечення серверів додатків вимагає захисту даних на всіх рівнях. Увімкніть механізми безпеки браузера, використовуючи такі заголовки безпеки, як HSTS та CSP. Посильте конфігурації Nginx та Laravel, застосувавши обмеження швидкості для блокування спроб брутфорсу. Завжди захищайте конфігураційні секрети, поміщаючи їх у конфігураційні файли та належним чином кешуючи їх, а також повністю ізолюйте рівень бази даних від публічної мережі.

---

<a id="self-test-quiz"></a>
## Тест для самоперевірки

### Запитання 1: Що станеться, якщо викликати `env('STRIPE_KEY')` всередині контролера після виконання команди `php artisan config:cache`?
- A) Laravel зчитує ключ безпосередньо з файлу `.env`.
- B) Виклик поверне `null`, оскільки зчитування `.env` під час виконання вимикається після кешування конфігурації.
- C) Буде викинуто виняток безпеки.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: B**
Кешування збирає всі конфігураційні файли в один кешований файл. Після цього завантаження файлу `.env` повністю пропускається, тому виклики `env()` повертатимуть `null`. Усі облікові дані мають зчитуватися з конфігураційної директорії за допомогою хелпера `config()`.
</details>

### Запитання 2: Чому заголовок `X-Content-Type-Options: nosniff` є важливим?
- A) Він запобігає виконанню браузером файлів, тип MIME яких не відповідає розширенню файлу або типу HTML, знижуючи ризик атак із виконанням файлів.
- B) Він запобігає завантаженню вебсайту всередині iframe (клікджекінг).
- C) Він стискає відповіді для швидшого завантаження.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: A**
Без заголовка `nosniff` браузери виконують «MIME-sniffing» та запускають завантажені файли як HTML/JavaScript, якщо вони містять відповідний вміст, незалежно від надісланого заголовка Content-Type. Вказівка `nosniff` запобігає такій поведінці.
</details>

### Запитання 3: Які версії протоколу повинні бути вимкнені у конфігурації TLS вашого вебсервера?
- A) TLS 1.2 та TLS 1.3
- B) TLS 1.0 та TLS 1.1
- C) Лише SSLv2 та SSLv3

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: B**
Протоколи TLS 1.0 та 1.1 містять криптографічні вразливості та не підтримують сучасні набори шифрів із прямою секретністю (forward-secrecy cipher suites). Для робочих сервісів (production) мають бути увімкнені лише TLS 1.2 та TLS 1.3.
</details>
