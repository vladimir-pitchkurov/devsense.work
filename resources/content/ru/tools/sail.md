---
title: "Laravel Sail: Docker-стек, версии PHP, Redis, Postgres, очереди и деплой | DevSense"
description: "Практичный гайд по Laravel Sail: смена версии PHP, Redis и RabbitMQ, переход с MySQL на PostgreSQL, MongoDB, воркеры очередей в контейнерах, разделение .env и чем локальная среда отличается от dev/prod."
---

# Laravel Sail: полный гайд по локальному стеку

**Laravel Sail** — это обёртка над **Docker Compose** для типичного приложения Laravel: PHP-FPM (сервис `laravel.test`), СУБД, Redis, Meilisearch, Selenium и опции по желанию. Цель — **локальная разработка** (и CI с Compose), а не готовая продакшен-платформа. Ниже — типовые доработки и граница, где Sail заканчивается и начинается настоящий деплой.

**Серия:** [Базы данных и Docker-сервисы](sail-databases) · [Очереди и воркеры](sail-queues) · [Окружения и деплой](sail-env-deploy) · [Диагностика](sail-troubleshooting) · [Все инструменты](../)

## Содержание

* [Что такое Sail (и чем не является)](#what-sail-is)
* [Требования и модель](#prerequisites)
* [Алиас и ежедневные команды](#daily-commands)
* [Сменить версию PHP](#php-version)
* [Сервисы по умолчанию и `sail:install`](#default-services)
* [Добавить Redis, если его нет](#add-redis)
* [RabbitMQ и очереди](#add-rabbitmq)
* [С MySQL на PostgreSQL](#mysql-to-postgres)
* [MongoDB (контейнер + Laravel)](#mongodb)
* [Очереди: драйверы, воркеры, Sail](#queues)
* [Почта и Mailpit](#mail)
* [Тома и производительность (WSL2 / macOS)](#volumes-performance)
* [Xdebug](#xdebug)
* [Кастомизация `docker-compose.yml`](#customizing-compose)
* [Файлы окружения](#environment-files)
* [Локально vs dev/staging/production](#local-vs-deploy)
* [Чеклист неполадок](#troubleshooting)

---

<a id="what-sail-is"></a>
## Что такое Sail (и чем не является)

- **Это:** опубликованный **`docker-compose.yml`** + Dockerfile’ы из `vendor/laravel/sail/runtimes/…` и скрипт `./vendor/bin/sail`. В репозиторий попадает после `php artisan sail:install` (или при создании проекта с Sail).
- **Не это:** хостинг. На сервере часто тоже Docker/K8s, но **скрипт Sail в production обычно не крутят** — там образы, оркестрация, health check’и, секреты и **supervisor** для очередей.

Sail — **воспроизводимая dev-среда**, частично похожая на прод (те же расширения PHP, тот же движок БД), но не копия сети и масштаба.

<a id="prerequisites"></a>
## Требования и модель

- Установлены **Docker** и **Compose v2**.
- **WSL2:** проект лучше держать в **файловой системе Linux** (`~/projects/...`), не на `C:\` — иначе bind mount тормозит.
- Команды выполняются **внутри** контейнеров: `./vendor/bin/sail artisan …`, `sail composer …`.

---

<a id="daily-commands"></a>
## Алиас и ежедневные команды

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Дальше: `sail up -d`, `sail artisan migrate`, `sail npm run dev`, `sail shell`, `sail down`.

---

<a id="php-version"></a>
## Сменить версию PHP

1. При необходимости опубликуйте файлы Sail: `sail artisan sail:publish`.
2. В **`docker-compose.yml`** у сервиса `laravel.test` в **`build.args`** задайте `PHP_VERSION` (например `8.4`) — смотрите, какие версии поддерживает ваш релиз Sail.
3. Пересоберите: `sail build --no-cache`, затем `sail up -d`.
4. Проверка: `sail php -v`.

Если свой **Dockerfile**, поменяйте `FROM` на актуальный образ `laravel/sail-php/...` из [репозитория Sail](https://github.com/laravel/sail).

---

<a id="default-services"></a>
## Сервисы по умолчанию и `sail:install`

Пример:

```bash
php artisan sail:install --with=mysql,redis,meilisearch,mailpit,selenium
```

Если Redis не ставили, а в `.env` всё ещё `REDIS_HOST=redis`, либо **добавьте** сервис (следующий раздел), либо укажите хост Redis на машине (для паритета лучше контейнер).

---

<a id="add-redis"></a>
## Добавить Redis, если его нет

1. В **`docker-compose.yml`** добавьте сервис `redis` (образ `redis:alpine`, порт, том `sail-redis`, сеть `sail`, при желании `healthcheck`).
2. Пропишите том в секции **`volumes:`**.
3. У `laravel.test` при необходимости добавьте **`depends_on: redis`**.
4. В **`.env`:** `REDIS_HOST=redis`, `REDIS_PORT=6379` и т.д.
5. `sail up -d`, проверка: `sail exec redis redis-cli ping`.

---

<a id="add-rabbitmq"></a>
## RabbitMQ и очереди

В ядре Laravel **нет** драйвера «rabbitmq из коробки» — нужен **пакет** с AMQP и контейнер **RabbitMQ**.

1. Сервис в compose, например `rabbitmq:3-management-alpine`, порты **5672** и **15672** (UI), переменные `RABBITMQ_DEFAULT_USER` / `PASS`, том данных.
2. В **`.env`** хост **`rabbitmq`**, учётные данные, `QUEUE_CONNECTION=rabbitmq` (или как требует пакет).
3. Установите пакет, опубликуйте конфиг, `sail artisan config:clear`.
4. Воркер: `sail artisan queue:work rabbitmq`.

Через **15672** удобно смотреть очереди и dead letter в разработке.

---

<a id="mysql-to-postgres"></a>
## С MySQL на PostgreSQL

1. Замените сервис БД в **`docker-compose.yml`** на шаблон **pgsql** из документации Sail / свежего `sail:install --with=pgsql`.
2. `depends_on` у `laravel.test` — на `pgsql`.
3. **`.env`:** `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, порт **5432**, логин/пароль как в compose.
4. `sail down -v` (удалит данные) или миграция данных вручную; затем `sail up -d`, `sail artisan migrate:fresh` для чистой dev-БД.
5. Расширение **`pdo_pgsql`** уже в образах Sail с PostgreSQL; в кастомных Dockerfile проверьте вручную.

---

<a id="mongodb"></a>
## MongoDB (контейнер + Laravel)

1. Добавьте сервис **mongo** в compose, том, при необходимости проброс порта для Compass.
2. Обычно используют **`mongodb/laravel-mongodb`** (или актуальный аналог) — следуйте их инструкции: часто нужен **`pecl install mongodb`** в Dockerfile Sail и пересборка.
3. В **`.env`** строка подключения или переменные пакета; хост из контейнера приложения — имя сервиса (**`mongo`**).
4. Учитывайте отличия от SQL: миграции, транзакции, тесты — по документации пакета.

---

<a id="queues"></a>
## Очереди: драйверы, воркеры, Sail

- **`sync`** — всё синхронно, для отладки сценариев «без фона».
- **`database` / `redis`:** `sail artisan queue:work`, для нескольких очередей: `--queue=high,default`.
- **Horizon:** `sail artisan horizon` локально; на проде — supervisor или облачный воркер.
- После правок кода воркеры держат старый код — `queue:restart` или ограничение **`--max-jobs`**.
- **RabbitMQ:** другая семантика повторов и DLX — тестируйте явно.

---

<a id="mail"></a>
## Почта и Mailpit

Настройте **`MAIL_HOST` / `MAIL_PORT`** на сервис **mailpit** (или аналог из compose). Веб-интерфейс на проброшенном порту — просмотр писем без реальной отправки.

---

<a id="volumes-performance"></a>
## Тома и производительность (WSL2 / macOS)

Bind mount всего репозитория на macOS и с диска Windows в WSL2 часто **медленный**; держите код в Linux FS. Именованные тома для MySQL/Redis сохраняют данные при `sail down`; **`sail down -v`** их сотрёт.

---

<a id="xdebug"></a>
## Xdebug

Включение через переменные окружения Sail (см. README вашей версии), например `SAIL_XDEBUG_MODE=debug,develop`. В IDE — маппинг путей контейнера ↔ хоста. Для очередей подключайтесь к контейнеру, где крутится `queue:work`.

---

<a id="customizing-compose"></a>
## Кастомизация `docker-compose.yml`

Стабильные **имена сервисов** = хосты в `.env`. Секреты — через `${VAR}` из `.env`. Дополнительные сервисы (Adminer и т.д.) — в той же сети **`sail`**. После правок: `sail build && sail up -d`.

---

<a id="environment-files"></a>
## Файлы окружения

- **`.env`** — локально, не в git; **`.env.example`** — шаблон без секретов.
- Отдельный **`env_file`** в compose для docker-специфичных значений — по желанию команды.
- **CI:** переменные в pipeline; тесты с `APP_ENV=testing` и `.env.testing`.
- **Порты:** `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT` и т.д., чтобы несколько проектов не конфликтовали.

---

<a id="local-vs-deploy"></a>
## Локально vs dev/staging/production

| Тема | Sail (локально) | Типичный сервер |
|------|-----------------|-----------------|
| Процессы | `sail up`, ручной artisan | php-fpm + nginx / Octane |
| Очереди | Терминал / Horizon локально | Supervisor, systemd, cloud worker |
| TLS | HTTP на localhost | Реальные сертификаты |
| Секреты | Файл .env | Vault, облачные параметры |
| Масштаб | Один контейнер на сервис | Реплики, LB, managed DB/Redis |

«Заработало в Sail» ≠ прод настроен: отдельно проверяйте **воркеры**, **cron scheduler**, **OPcache**, **S3** и т.д.

---

<a id="troubleshooting"></a>
## Чеклист неполадок

- Права на **`storage/`**, **`bootstrap/cache/`** — `sail root-shell`, `chown` пользователю `sail`.
- **Connection refused** к БД/Redis: с контейнера приложения хост — **имя сервиса**, не `127.0.0.1`.
- После Dockerfile: **`sail build --no-cache`**.
- Занят порт — поменять **`FORWARD_*`** в `.env`.
- Зависимости ставить **`sail composer install`**, чтобы совпала платформа с контейнером.

---

## Итог

Sail выгоден, когда у команды **один compose-файл** и понятный **`.env.example`**. Продакшен (мониторинг, бэкапы, супервизор очередей, секреты) проектируйте отдельно и переносите в Sail только то, что нужно для реалистичной локальной работы.
