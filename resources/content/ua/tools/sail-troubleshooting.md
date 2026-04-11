---
title: "Laravel Sail: WSL2, права, порти, перезбірка, OPcache та Vite — діагностика | DevSense"
description: "Типові проблеми Laravel Sail: синхронізація файлів у WSL2, UID/GID і storage, конфлікти FORWARD_* портів, застарілі шари Docker, OPcache й Xdebug у розробці, npm/Vite на хості й у контейнері, безпечне скидання томів."
---

# Sail: діагностика та продуктивність

Тут зібрано **часті тертя Sail**, які не є «багами Laravel»: ФС, мережа Compose, кеш образів і розширення PHP в контейнерах. Читайте разом із [БД і сервісами](sail-databases#networking), [Чергами](sail-queues#queue-work) та [Оточенням і деплоєм](sail-env-deploy#env-files).

**Навігація:** [Усі інструменти](../) · [Sail](sail#what-sail-is) · [БД](sail-databases#networking) · [Черги](sail-queues#connections) · [Env](sail-env-deploy#forward-ports)

## Зміст

* [WSL2, Docker Desktop і синхронізація файлів](#wsl-filesync)
* [Права, `storage` і `vendor`](#permissions)
* [Порт зайнятий / `FORWARD_*`](#ports)
* [«На хості працює, у Sail — ні»](#host-vs-container)
* [Застарілий код: перезбірка й шари](#rebuild)
* [OPcache й автозавантаження в dev](#opcache)
* [Xdebug гальмує](#xdebug)
* [Vite, npm, Node зовні й усередині Sail](#frontend)
* [Логи й швидка діагностика](#logs)
* [Коли скидати томи (втрата даних)](#volumes)

---

<a id="wsl-filesync"></a>
## WSL2, Docker Desktop і синхронізація файлів

На **Windows + WSL2** монтування з `/mnt/c/...` у Linux-контейнери часто **повільне** і ламає вотчери (Vite). Тримайте проєкт **у ФС WSL** (наприклад `~/projects/...`).

Якщо редактор у Windows чіпає файли через межу WSL, можливі **права чи час модифікації** — домовтеся про одне місце правок.

---

<a id="permissions"></a>
## Права, `storage` і `vendor`

Laravel потребує запису в **`storage/`** і **`bootstrap/cache/`** для користувача PHP в **`laravel.test`**. На Linux-хості маппінг UID/GID через **`WWWUSER`** / **`WWWGROUP`** у `.env` зменшує файли «від root» після `sail artisan`.

```bash
sail exec laravel.test ls -la storage bootstrap/cache
```

Якщо логи не пишуться, виправляйте власника **всередині** контейнера.

---

<a id="ports"></a>
## Порт зайнятий / `FORWARD_*`

Sail пробросить MySQL, Redis, HTTP на **localhost**. Другий MySQL або другий проєкт — **`address already in use`**.

```dotenv
FORWARD_DB_PORT=3307
APP_PORT=8081
FORWARD_REDIS_PORT=6380
```

Потім `sail down && sail up -d`. **Усередині** контейнерів порти 3306/6379 не змінюються. Детальніше: [Оточення та деплой](sail-env-deploy#forward-ports).

---

<a id="host-vs-container"></a>
## «На хості працює, у Sail — ні»

**`php` / `composer` на хості** — одна версія та розширення; **`sail artisan`** — інша (контейнер). Відсутність **`pdo_pgsql`**, **`redis`**, **`mongodb`** у образі — типова причина. Рішення: **Dockerfile** + **`sail build --no-cache`**.

Хости БД в `.env` — **імена сервісів** (`pgsql`, `redis`), не `127.0.0.1`. Див. [БД](sail-databases#networking).

---

<a id="rebuild"></a>
## Застарілий код: перезбірка й шари

Після змін у **Dockerfile**:

```bash
sail build --no-cache
sail up -d
```

Після **`composer.lock`**: `sail composer install`. Воркери черг: `sail artisan queue:restart` ([Черги](sail-queues#restart)).

---

<a id="opcache"></a>
## OPcache й автозавантаження в dev

Якщо зміни коду «не видно», перевірте **`validate_timestamps`** / **`opcache.revalidate_freq`** у **`php.ini`** контейнера. Після PSR-4-переїздів: `sail composer dump-autoload`.

---

<a id="xdebug"></a>
## Xdebug гальмує

Xdebug **завжди** додає накладні витрати. Вимикайте без потреби в breakpoints.

---

<a id="frontend"></a>
## Vite, npm, Node зовні й усередині Sail

**`npm run dev` на хості** або **`sail npm`** у Compose — головне, щоб **`APP_URL`**, **HMR** і **порти** збігалися. Див. [Env](sail-env-deploy#app-url).

---

<a id="logs"></a>
## Логи й швидка діагностика

```bash
sail logs -f laravel.test
docker compose ps
sail exec laravel.test php -v
sail exec laravel.test php -m
```

Redis: `sail exec redis redis-cli ping`.

---

<a id="volumes"></a>
## Коли скидати томи (втрата даних)

**`sail down -v`** знімає **іменовані томи** — дані БД/Redis проєкту зникнуть. Один том: `docker volume rm`. Див. [Томи](sail-databases#volumes).

---

## Див. також

* [Sail — повний гайд](sail#what-sail-is)  
* [БД і сервіси](sail-databases#networking)  
* [Черги](sail-queues#queue-work)  
* [Оточення та деплой](sail-env-deploy#env-files)  

[← Усі інструменти](../)
