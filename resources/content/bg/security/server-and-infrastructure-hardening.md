---
title: "Закаляване на сървъри и инфраструктура: Хедъри за сигурност, TLS, ограничаване на честотата на заявките и управление на тайни | DevSense"
description: "Подсилване на защитата на вашия уеб сървър и инфраструктура на приложението. Научете как да конфигурирате HTTP хедъри за сигурност, TLS шифри, ограничители на честотата, сигурно съхранение на тайни и изолация на бази данни."
published: 2026-06-19
faq:
  - question: "Защо трябва да избягвате използването на env() извън конфигурационните файлове в Laravel?"
    answer: "В производствена среда (production) Laravel кешира конфигурационния файл в един-единствен оптимизиран файл. Веднъж кеширан, Laravel спира да чете изцяло файла .env, и всяко директно извикване на env() в кода на приложението ще върне null, което ще доведе до критични сривове в конфигурацията по време на изпълнение."
  - question: "Каква е основната роля на хедъра Content-Security-Policy (CSP)?"
    answer: "CSP действа като мощен слой на защита срещу междусайтов скриптинг (XSS) и атаки за инжектиране на данни, като позволява на администраторите да ограничат ресурсите (като JavaScript, CSS, изображения), които браузърът има право да зарежда и изпълнява за дадена страница."
  - question: "Как Nginx предпазва от brute-force атаки и атаки за отказ на услуга (Denial of Service - DoS)?"
    answer: "Ограничаването на честотата на заявките (rate limiting) в Nginx използва алгоритъма Leaky Bucket за ограничаване на входящите заявки от един IP адрес, като забавя или блокира заявките, които надвишават дефинираните граници."
---

# Закаляване на сървъри и инфраструктура: Хедъри за сигурност, TLS, ограничаване на честотата на заявките и управление на тайни

Макар че защитата на кода на приложението е от критично значение, инфраструктурата, която го хоства, също трябва да бъде подсилена. Сигурната бекенд среда изисква надеждна защита при пренос на данни, ограничаване на заявките, мрежова изолация и защитени тайни на средата (environment secrets).

В това ръководство ще имплементираме хедъри за сигурност чрез междинен софтуер (middleware) в Laravel, ще конфигурираме ограничения на честотата в Nginx и Laravel, ще защитим променливите на средата и ще обсъдим подсилването на мрежовата сигурност.

**Свързани ръководства:** [Уязвимости на уеб приложения и предотвратяване](web-app-security) · [SSRF и сигурни качвания на файлове](ssrf-and-file-upload-security)

## Съдържание

* [HTTP хедъри за сигурност](#security-headers)
* [Закаляване на SSL/TLS](#ssl-tls-hardening)
* [Ограничаване на честотата на заявките (Nginx и Laravel)](#rate-limiting)
* [Сигурно управление на тайни](#secrets-management)
* [Изолация на бази данни](#database-isolation)
* [Често срещани грешки](#common-mistakes)
* [Контролен списък](#checklist)
* [Резюме](#summary)
* [Тест за самопроверка](#self-test-quiz)

---

<a id="security-headers"></a>
## HTTP хедъри за сигурност

HTTP хедърите за сигурност указват на браузъра как да се държи при взаимодействие с вашия сайт, като неутрализират често срещани вектори на атака като Clickjacking, междусайтов скриптинг (XSS) и MIME-sniffing.

Можем да приложим тези хедъри глобално в Laravel, като използваме персонализиран междинен софтуер (middleware):

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
## Закаляване на SSL/TLS

Правилното конфигуриране на SSL/TLS гарантира, че данните в процес на пренос не могат да бъдат прихванати или променени. Трябва да деактивирате остарелите версии на TLS и да ограничите сървъра си до сигурни алгоритми за шифриране (ciphers).

Ето пример за защитен конфигурационен блок за TLS в Nginx:

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
## Ограничаване на честотата на заявките (Nginx и Laravel)

Ограничаването на честотата на заявките (rate limiting) предотвратява злоупотреби от DDoS атаки, опити за brute-force влизане и ботове за събиране на данни (scrapers). Подсилването на защитата трябва да се извършва както на ниво уеб сървър (Nginx), така и на ниво приложение (Laravel).

### 1. Ограничаване на честотата в Nginx (на база IP)

Nginx обработва ограничаването на честотата, преди заявките да достигнат до PHP-FPM, като по този начин спестява сървърни ресурси:

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

### 2. Ограничаване на честотата в Laravel

Laravel позволява динамично ограничаване на честотата за конкретни потребители в кода на приложението. Конфигурирайте ограничителите в `app/Providers/AppServiceProvider.php` (или `RouteServiceProvider.php`):

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

Приложете този ограничител във вашия файл с маршрути (routing file):

```php
// routes/api.php
Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});
```

---

<a id="secrets-management"></a>
## Сигурно управление на тайни

Разкриването на тайни API ключове или пароли за бази данни може да доведе до катастрофални пробиви в сигурността на данните.

### 1. Избягвайте `env()` в кода на приложението

Никога не извиквайте `env()` извън вашите конфигурационни файлове (намиращи се в директорията `config/`). Когато кеширате конфигурацията в производствена среда (`php artisan config:cache`), Laravel зарежда стойностите от `.env` веднъж, кешира ги и деактивира четенето на `.env` файла по време на работа. Всяко директно извикване на `env()` след кеширане ще върне `null`.

**Правилен модел:**
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

### 2. Защита на правата за достъп до `.env`

Уверете се, че неупълномощени потребители на хост системата нямат достъп за четене до файла `.env`, съдържащ конфигурационните ключове:

```bash
chmod 600 .env
```

---

<a id="database-isolation"></a>
## Изолация на бази данни

Вашата база данни никога не трябва да бъде изложена на публичния интернет.

1. **Мрежово обвързване (Network Binding):** Наложете на сървъра на базата данни да слуша само на вътрешни интерфейси или локален loopback. В PostgreSQL (`postgresql.conf`) или MySQL (`my.cnf`), обвържете конфигурацията с локални адреси:
   ```ini
   # mysql.cnf
   bind-address = 127.0.0.1
   ```
2. **Правила на защитната стена (Firewall Rules):** Блокирайте входящите външни портове (като MySQL порт 3306 или PostgreSQL порт 5432) чрез правила на защитната стена (UFW или Cloud Security Groups). Разрешете достъп единствено от частния IP адрес на уеб сървъра.
3. **SSL/TLS на базата данни:** Ако базата данни и уеб приложението се намират на различни сървъри в рамките на частна подмрежа, наложете използването на SSL връзки между Laravel и базата данни:
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

<a id="common-mistakes"></a>
## Често срещани грешки

1. **Извикване на `env()` по време на работа (Runtime):** Извикване на `env('API_KEY')` в контролери или опашки от задачи (jobs), което води до сривове в конфигурацията при активирано кеширане в производствена среда.
2. **Липсващ `Strict-Transport-Security` (HSTS) хедър:** Пропускане на изпращането на HSTS хедъри, което оставя потребителите уязвими към атаки за премахване на SSL защитата (SSL-stripping).
3. **Слаба конфигурация на TLS:** Запазване на поддръжката за TLS 1.0 или 1.1 на сървърите с цел обратна съвместимост, което компрометира криптирането на цялата система.
4. **Публично изложен порт 3306/5432:** Оставяне на порта на базата данни отворен към публичния интернет, което го излага на сканиране, речникови атаки и претоварване с връзки (connection flooding).

---

<a id="checklist"></a>
## Контролен списък

1. **Без извиквания на `env()`:** Направили ли сте одит на кодовата си база, за да сте сигурни, че всички търсения на променливи на средата се извършват вътре в конфигурационните файлове?
2. **HTTP хедъри за сигурност:** Дали вашето уеб приложение връща хедърите `X-Frame-Options`, `X-Content-Type-Options` и дефинирана политика `Content-Security-Policy`?
3. **Ограничение на версиите на TLS:** Дали конфигурацията на вашия Nginx ограничава протоколите само до TLS v1.2 и v1.3?
4. **Ограничаване на честотата:** Дали всички публични крайни точки за удостоверяване и чувствителни API са защитени с ограничения на честотата?
5. **Мрежова защитна стена:** Деактивиран ли е публичният мрежов достъп до базите данни, кеширащите системи (Redis/Memcached) и вътрешните микроуслуги?

---

<a id="summary"></a>
## Резюме

Защитата на сървърите за приложения изисква сигурност на данните на всички нива. Активирайте механизмите за безопасност на браузъра чрез използване на хедъри за сигурност като HSTS и CSP. Подсилете конфигурациите на Nginx и Laravel чрез прилагане на ограничаване на честотата на заявките с цел блокиране на brute-force атаки. Винаги защитавайте конфигурационните тайни, като ги дефинирате в конфигурационни файлове и ги кеширате правилно, и напълно изолирайте нивото на базата данни от публичната мрежа.

---

<a id="self-test-quiz"></a>
## Тест за самопроверка

### Въпрос 1: Какво се случва, ако `env('STRIPE_KEY')` бъде извикан в контролер след стартиране на `php artisan config:cache`?
- A) Laravel прочита ключа директно от файла `.env`.
- B) Извикването връща `null`, тъй като четенето на `.env` файла по време на работа е деактивирано след кеширане на конфигурацията.
- C) Това хвърля изключение за сигурност.

<details>
<summary>Кликнете, за да видите отговора</summary>

**Отговор: B**
Кеширането анализира всички конфигурационни файлове в един общ кеширан файл. Веднъж кеширан, зареждането на файла `.env` се пропуска изцяло, което означава, че извикванията на `env()` ще върнат `null`. Всички идентификационни данни трябва да се четат от директорията config чрез функцията `config()`.
</details>

### Въпрос 2: Защо е важен хедърът `X-Content-Type-Options: nosniff`?
- A) Той предпазва браузъра от изпълнение на файлове, чийто MIME тип не съвпада с разширението на файла или с HTML типа, като по този начин неутрализира атаки за изпълнение на файлове.
- B) Той не позволява уебсайтът да бъде зареден в iframe (Clickjacking).
- C) Той компресира отговорите, за да се зареждат по-бързо.

<details>
<summary>Кликнете, за да видите отговора</summary>

**Отговор: A**
Без `nosniff` браузърите извършват "MIME-sniffing" и изпълняват качените файлове като HTML/JavaScript, ако те съдържат съответстващи полезни товари, независимо от изпратения хедър Content-Type. Хедърът `nosniff` предотвратява това поведение.
</details>

### Въпрос 3: Кои версии на протоколи трябва да бъдат деактивирани в TLS конфигурацията на вашия уеб сървър?
- A) TLS 1.2 и TLS 1.3
- B) TLS 1.0 и TLS 1.1
- C) Само SSLv2 и SSLv3

<details>
<summary>Кликнете, за да видите отговора</summary>

**Отговор: B**
TLS 1.0 и 1.1 съдържат криптографски уязвимости и не поддържат съвременни набори от шифри с права защита на връзката (forward-secrecy). Само TLS 1.2 и TLS 1.3 трябва да бъдат разрешени за производствени услуги.
</details>
