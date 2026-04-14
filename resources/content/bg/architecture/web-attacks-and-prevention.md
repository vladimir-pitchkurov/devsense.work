---
title: "Уеб атаки и защита: XSS, CSRF, SQLi, SSRF, IDOR и качване на файлове | DevSense"
description: "Атаките, които най-често се срещат в уеб приложения: инжекции (SQL/command), XSS, CSRF, IDOR и грешки в контрола на достъпа, SSRF, небезопасни качвания на файлове, clickjacking и конфигурационни капани. Практични мерки, заглавки и чеклисти."
published: 2026-04-14
---

# Атаки и превенция, които всеки уеб разработчик трябва да знае

Сигурността почти винаги се чупи **на границите**: има валидация, но не на входа; има авторизация, но един endpoint я пропуска; има escaping, но в един шаблон се показва raw HTML. Добрата новина е, че повечето реални инциденти са повторяеми класове грешки — и могат да се затварят системно.

**Свързани материали:** [Наблюдаемост и мониторинг](observability-monitoring-laravel) · [Бази данни под натоварване](database-performance-and-scaling)

## Съдържание

* [Модел на заплахите: какво пазим и от кого](#threat-model)
* [Инжекции: SQLi, command injection, template injection](#injection)
* [XSS: отразен, съхранен, DOM-based](#xss)
* [CSRF: защо „ама това е GET“ не е защита](#csrf)
* [Автентикация и сесии: кражба на токени, фиксация, cookies](#auth-sessions)
* [Контрол на достъпа и IDOR: „то е само id“](#idor)
* [Качване на файлове и пътища: upload, path traversal, RCE наблизо](#uploads)
* [SSRF: когато сървърът ходи „където не трябва“](#ssrf)
* [Небезопасна десериализация и подмяна на обекти](#deserialization)
* [Браузърни защити: clickjacking, CORS, заглавки](#browser)
* [DoS и abuse: rate limit, brute force, скъпи операции](#dos)
* [Чеклист за ревю и релийз](#checklist)

---

<a id="threat-model"></a>
## Модел на заплахите: какво пазим и от кого

Преди техниките — рамка:

- **Атакуващ**: анонимен потребител, логнат потребител, партньор с API ключ, „вътрешен“ във VPN, актьор с достъп до CI/CD.
- **Активи**: пари, лични данни, акаунти, админ панели, интеграции, тайни, достъп до вътрешна мрежа.
- **Повърхност**: форми и API, редиректи, webhooks, импорт/експорт, качване на файлове, интеграции (S3, SMTP, плащания).

Практично правило: **всеки вход е недоверен** (вкл. заглавки, cookies, webhook payload, URL параметри, съобщения от опашки).

---

<a id="injection"></a>
## Инжекции: SQLi, command injection, template injection

### SQL Injection (SQLi)

SQLi почти винаги започва с „само едно сурово SQL“. Защитата не е „escape“, а **параметризация**.

**Лошо** (конкатенация):

```php
$rows = DB::select("SELECT * FROM users WHERE email = '{$email}'");
```

**По-добре** (плейсхолдъри):

```php
$rows = DB::select('SELECT * FROM users WHERE email = ?', [$email]);
```

Често срещан капан са **динамичните имена на колони / сортиране**. Параметрите не важат за идентификатори, затова ползвайте allowlist:

```php
$allowed = ['created_at', 'email', 'id'];
$sort = in_array($request->get('sort'), $allowed, true) ? $request->get('sort') : 'created_at';

$users = User::query()->orderBy($sort, 'desc')->paginate();
```

### Command injection

Ако викате shell, правилото е: **никога не пускайте потребителски вход в команден ред**. Дори `escapeshellarg()` е последна бариера, не дизайн.

### Template injection

Опасно е да позволите потребителски „шаблон“, който се изпълнява като Blade/Twig/Smarty. Третирайте го като данни и използвайте ограничен формат (напр. Markdown с allowlist и безопасен рендер).

---

<a id="xss"></a>
## XSS: отразен, съхранен, DOM-based

XSS е изпълнение на JS във вашия origin. Типични източници: raw output, HTML без санитайз, `innerHTML`, инжектиране в `<script>`/атрибути без правилно кодиране.

Базови мерки:

- escaping по подразбиране (`{{ }}` в Blade);
- санитайз на HTML вход (allowlist);
- CSP като ограничител на щетите.

```http
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'
```

---

<a id="csrf"></a>
## CSRF: защо „ама това е GET“ не е защита

CSRF е когато браузърът на потребителя изпраща заявка към вашия сайт с неговите cookies/сесия, защото друг сайт го тригърва.

Мерки:

- CSRF токени за state-changing заявки (Laravel го има по подразбиране);
- `SameSite` cookies (`Lax` минимум; `None; Secure` за cross-site);
- проверка Origin/Referer за критични действия;
- никакви промени на състояние през GET.

---

<a id="auth-sessions"></a>
## Автентикация и сесии: кражба на токени, фиксация, cookies

Минимум:

- rate limit за login/OTP/reset;
- пароли само с `password_hash()` (argon2/bcrypt);
- `HttpOnly`, `Secure`, `SameSite` за сесийни cookies;
- ротация на session ID след login и повишаване на права.

---

<a id="idor"></a>
## Контрол на достъпа и IDOR: „то е само id“

IDOR е когато потребителят може да познае/преброи ID и да достъпи чужди ресурси.

Защита: policies/gates, scoped заявки по owner/tenant, аудит логове за чувствителни операции.

---

<a id="uploads"></a>
## Качване на файлове и пътища: upload, path traversal, RCE наблизо

- проверявайте съдържанието, не само разширението (MIME от браузъра е недоверен);
- лимити размер/брой;
- съхранение извън web-root, случайни имена;
- забрана за изпълнение в upload директория на ниво уеб сървър;
- не приемайте пътища от потребителски вход (защитете от `../`).

---

<a id="ssrf"></a>
## SSRF: когато сървърът ходи „където не трябва“

SSRF е когато приемете URL от потребителя и го fetch-нете от сървъра (preview, import, webhook tester).

Мерки: allowlist хостове, блокирайте private IP ranges, ограничете редиректи, сложете таймаути и лимити, разрешете само https.

---

<a id="deserialization"></a>
## Небезопасна десериализация и подмяна на обекти

Не десериализирайте attacker-controlled данни (cookies/hidden fields/външни payload-и). Ползвайте подпис (HMAC) за токени, JSON + схема + валидация за данни.

---

<a id="browser"></a>
## Браузърни защити: clickjacking, CORS, заглавки

Clickjacking:

```http
X-Frame-Options: DENY
Content-Security-Policy: frame-ancestors 'none'
```

Базови заглавки:

```http
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

---

<a id="dos"></a>
## DoS и abuse: rate limit, brute force, скъпи операции

Най-често атаката е „евтино за тях, скъпо за вас“: тежки търсения, експорти, PDF, login/OTP без rate limit.

Мерки: rate limiting по IP/акаунт/ключ, опашки за тежки задачи, таймаути и лимити на размер/дълбочина, кеширане там където е безопасно.

---

<a id="checklist"></a>
## Чеклист за ревю и релийз

- [ ] Валидация на входа на границата (форми/API/webhooks).
- [ ] Allowlist за сортиране/филтри/идентификатори.
- [ ] Escaping по подразбиране, без необоснован raw HTML.
- [ ] Server-side авторизация за всяко действие; няма IDOR.
- [ ] SSRF контроли (allowlist, private IP, таймаути).
- [ ] Uploads: проверки, извън web-root, забрана за изпълнение.

---

## Извод

Един принцип: **сигурността са инварианти на границата**. Валидация на входа, escaping на изхода, авторизация на сървъра, подписани интеграции и rate limit за всичко скъпо.
