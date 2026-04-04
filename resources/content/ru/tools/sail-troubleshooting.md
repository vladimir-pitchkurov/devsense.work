---
title: "Laravel Sail: WSL2, права, порты, пересборка, OPcache и Vite — диагностика | DevSense"
description: "Типичные проблемы Laravel Sail: синхронизация файлов в WSL2, UID/GID и права на storage, конфликты FORWARD_* портов, устаревшие слои Docker, OPcache и Xdebug в разработке, npm/Vite на хосте и в контейнере, безопасный сброс томов."
---

# Sail: диагностика и производительность

Здесь собраны **частые трения Sail**, которые не являются «багами Laravel»: особенности ФС, сеть Compose, кэш образов и расширения PHP в контейнерах. Читайте вместе с [Базами данных и сервисами](sail-databases), [Очередями](sail-queues) и [Окружениями и деплоем](sail-env-deploy).

**Навигация:** [Все инструменты](../) · [Sail обзор](sail) · [БД](sail-databases) · [Очереди](sail-queues) · [Env и деплой](sail-env-deploy)

## Содержание

* [WSL2, Docker Desktop и синхронизация файлов](#wsl-filesync)
* [Права, `storage` и `vendor`](#permissions)
* [Порт занят / `FORWARD_*`](#ports)
* [«На хосте работает, в Sail — нет»](#host-vs-container)
* [Устаревший код: пересборка и слои](#rebuild)
* [OPcache и автозагрузка в dev](#opcache)
* [Xdebug тормозит](#xdebug)
* [Vite, npm, Node снаружи и внутри Sail](#frontend)
* [Логи и быстрая диагностика](#logs)
* [Когда сбрасывать тома (потеря данных)](#volumes)

---

<a id="wsl-filesync"></a>
## WSL2, Docker Desktop и синхронизация файлов

На **Windows + WSL2** монтирование из `/mnt/c/...` в Linux-контейнеры часто **медленное** и ломает вотчеры (Vite, опрос файлов). Держите проект **в ФС WSL** (например `~/projects/...`) и запускайте Sail оттуда.

Если редактор на Windows трогает файлы через границу WSL, возможны **права или метки времени** — договоритесь: правки только в WSL или только в Windows.

---

<a id="permissions"></a>
## Права, `storage` и `vendor`

Laravel требует права на запись в **`storage/`** и **`bootstrap/cache/`** для пользователя PHP в **`laravel.test`**. На Linux хосте маппинг UID/GID через **`WWWUSER`** / **`WWWGROUP`** в `.env` (см. документацию Sail) снижает число файлов «от root» после `sail artisan`.

Проверка в контейнере приложения:

```bash
sail exec laravel.test ls -la storage bootstrap/cache
```

Если логи не пишутся, чините владельца **внутри** контейнера — bind-mount отражает изменения в обе стороны.

---

<a id="ports"></a>
## Порт занят / `FORWARD_*`

Sail пробрасывает MySQL, Redis, HTTP приложения на **localhost**. Второй локальный MySQL или второй проект Sail даёт **`address already in use`**.

Задайте разные значения в `.env` на проект:

```dotenv
FORWARD_DB_PORT=3307
APP_PORT=8081
FORWARD_REDIS_PORT=6380
```

Затем `sail down && sail up -d`. **Внутри** контейнеров порты 3306/6379 не меняются; меняется только **хост**. Подробнее: [Окружения и деплой](sail-env-deploy).

---

<a id="host-vs-container"></a>
## «На хосте работает, в Sail — нет»

**`php` / `composer` на Mac** — это PHP **хоста**; **`sail artisan`** — PHP **контейнера** и его расширения. Отсутствие **`pdo_pgsql`**, **`redis`**, **`mongodb`** в образе — частая причина. Решение: правка опубликованного **Dockerfile** и **`sail build --no-cache`**.

Хосты БД в `.env` — **имена сервисов Compose** (`pgsql`, `redis`), не `127.0.0.1`, потому что приложение в **`laravel.test`**. См. [Базы данных](sail-databases#networking).

---

<a id="rebuild"></a>
## Устаревший код: пересборка и слои

После изменений в **Dockerfile** (`pecl install`, `apt`, базовый образ PHP) обычный `sail up` может брать **старые слои**. Используйте:

```bash
sail build --no-cache
sail up -d
```

После обновления **`composer.lock`**: `sail composer install`, чтобы **`vendor/`** соответствовал версии PHP в контейнере.

Долгоживущие **воркеры очередей** кэшируют bootstrap приложения; после смены кода — `sail artisan queue:restart` ([Очереди](sail-queues#restart)).

---

<a id="opcache"></a>
## OPcache и автозагрузка в dev

В прод-образах часто включён **OPcache** с жёсткими настройками. Если изменения кода «не видны», проверьте **`opcache.revalidate_freq`** / **`validate_timestamps`** в **`php.ini`** контейнера. В локальной разработке важно, чтобы **по меткам времени** файлы перечитывались.

После переезда классов по PSR-4 выполните `sail composer dump-autoload`, если ловите «class not found».

---

<a id="xdebug"></a>
## Xdebug тормозит

Xdebug **всегда** добавляет накладные расходы. Отключайте, когда не нужны breakpoints (в Sail обычно есть переключатель через env/доки вашей версии). Оставляйте отладку точечной, а не на весь день.

---

<a id="frontend"></a>
## Vite, npm, Node снаружи и внутри Sail

Варианты:

* **`npm run dev` на хосте**, если Vite слушает порт, открытый в браузере.
* Или **`sail npm`** / сервис Node в Compose, если всё в Docker.

Смешивание нормально, если согласованы **`APP_URL`**, **HMR host** и **прокси портов**. Расхождения дают **websocket / HMR failed** или **404 на `@vite`** — см. [Env и деплой](sail-env-deploy#app-url).

---

<a id="logs"></a>
## Логи и быстрая диагностика

```bash
sail logs -f laravel.test
docker compose ps
sail exec laravel.test php -v
sail exec laravel.test php -m
```

Проверка БД из приложения: `sail exec laravel.test php artisan migrate:status` или короткий тест в `tinker`. Redis: `sail exec redis redis-cli ping`.

---

<a id="volumes"></a>
## Когда сбрасывать тома (потеря данных)

**`sail down -v`** удаляет **именованные тома** — **все локальные данные БД/Redis** проекта. Используйте при подозрении на **битое состояние тома** или крупном обновлении образа — не как ежедневную привычку.

Один том: `docker volume ls` и `docker volume rm <имя>`, чтобы не задеть другие проекты. См. [Базы данных — тома](sail-databases#volumes).

---

## См. также

* [Sail — полный гайд](sail)  
* [Базы данных и сервисы](sail-databases)  
* [Очереди и воркеры](sail-queues)  
* [Окружения и деплой](sail-env-deploy)  

[← Все инструменты](../)
