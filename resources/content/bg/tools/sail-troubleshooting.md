---
title: "Laravel Sail: WSL2, права, портове, пресборка, OPcache и Vite — диагностика | DevSense"
description: "Често срещани проблеми с Laravel Sail: синхронизация на файлове в WSL2, UID/GID и storage, конфликти на FORWARD_* портове, остарели Docker слоеве, OPcache и Xdebug при разработка, npm/Vite на хоста срещу контейнер, безопасно нулиране на томове."
---

# Sail: диагностика и производителност

Тук са събрани **чести триения с Sail**, които не са „бъгове на Laravel“: файлова система, мрежата на Compose, кеш на образи и PHP разширения в контейнери. Четете заедно с [Бази данни и услуги](sail-databases), [Опашки](sail-queues) и [Среди и деплой](sail-env-deploy).

**Навигация:** [Всички инструменти](../) · [Sail](sail) · [БД](sail-databases) · [Опашки](sail-queues) · [Env](sail-env-deploy)

## Съдържание

* [WSL2, Docker Desktop и синхронизация на файлове](#wsl-filesync)
* [Права, `storage` и `vendor`](#permissions)
* [Портът е зает / `FORWARD_*`](#ports)
* [„На хоста работи, в Sail — не“](#host-vs-container)
* [Остарял код: пресборка и слоеве](#rebuild)
* [OPcache и автозареждане в dev](#opcache)
* [Xdebug забавя](#xdebug)
* [Vite, npm, Node извън и в Sail](#frontend)
* [Логове и бърза диагностика](#logs)
* [Кога да нулирате томове (загуба на данни)](#volumes)

---

<a id="wsl-filesync"></a>
## WSL2, Docker Desktop и синхронизация на файлове

На **Windows + WSL2** монтирането от `/mnt/c/...` в Linux контейнери често е **бавно** и чупи watcher-и (Vite). Дръжте проекта **във ФС на WSL** (напр. `~/projects/...`).

---

<a id="permissions"></a>
## Права, `storage` и `vendor`

Laravel изисква запис в **`storage/`** и **`bootstrap/cache/`** за PHP потребителя в **`laravel.test`**. На Linux хост **`WWWUSER`** / **`WWWGROUP`** в `.env` намаляват файлове „от root“.

```bash
sail exec laravel.test ls -la storage bootstrap/cache
```

---

<a id="ports"></a>
## Портът е зает / `FORWARD_*`

Задайте различни **`FORWARD_DB_PORT`**, **`APP_PORT`**, **`FORWARD_REDIS_PORT`** за всеки проект. Вътре в контейнерите вътрешните портове остават стандартни. Вижте [Среди и деплой](sail-env-deploy).

---

<a id="host-vs-container"></a>
## „На хоста работи, в Sail — не“

**`php` на хоста** ≠ **`sail artisan`**. Липсващи разширения в образа — редактирайте **Dockerfile** и **`sail build --no-cache`**. Хостове в `.env` — **имена на услуги** (`pgsql`, `redis`), не `127.0.0.1`. [БД](sail-databases#networking).

---

<a id="rebuild"></a>
## Остарял код: пресборка и слоеве

```bash
sail build --no-cache
sail up -d
```

След промени в кода на воркери: `sail artisan queue:restart` ([Опашки](sail-queues#restart)).

---

<a id="opcache"></a>
## OPcache и автозареждане в dev

Проверете **`validate_timestamps`** в **`php.ini`**. `sail composer dump-autoload` след PSR-4 промени.

---

<a id="xdebug"></a>
## Xdebug забавя

Изключвайте, когато не дебъгвате активно.

---

<a id="frontend"></a>
## Vite, npm, Node извън и в Sail

Подравнете **`APP_URL`**, HMR и прокси портове. [Env](sail-env-deploy#app-url).

---

<a id="logs"></a>
## Логове и бърза диагностика

```bash
sail logs -f laravel.test
docker compose ps
sail exec laravel.test php -m
```

---

<a id="volumes"></a>
## Кога да нулирате томове (загуба на данни)

**`sail down -v`** изтрива именованите томове. За един том: `docker volume rm`. [Томове](sail-databases#volumes).

---

## Вижте също

* [Sail — пълен гайд](sail)  
* [Бази данни и услуги](sail-databases)  
* [Опашки](sail-queues)  
* [Среди и деплой](sail-env-deploy)  

[← Всички инструменти](../)
