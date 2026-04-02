---
title: "Laravel Sail: БД, Redis, Postgres, MongoDB, RabbitMQ в Docker Compose | DevSense"
description: "Compose-рецепты для Sail: Redis, замена на PostgreSQL, MongoDB и расширение PHP, RabbitMQ, Mailpit/Meilisearch, healthcheck и именованные тома."
---

# Sail: базы данных и Docker-сервисы

Сервисы рядом с **`laravel.test`**: SQL, Redis, RabbitMQ, MongoDB, почта и поиск. Дополняет [полный гайд по Sail](sail). Команды воркеров — в [Очереди и воркеры](sail-queues), `.env` и серверы — в [Окружения и деплой](sail-env-deploy).

**Навигация:** [Все инструменты](../) · [Sail обзор](sail) · [Очереди](sail-queues) · [Env и деплой](sail-env-deploy) · [Диагностика](sail-troubleshooting)

## Содержание

* [Сеть и имена хостов](#networking)
* [Redis (полный пример)](#redis-service)
* [MySQL → PostgreSQL](#postgres-swap)
* [MongoDB и расширение PHP](#mongodb)
* [RabbitMQ как sidecar](#rabbitmq-sidecar)
* [Mailpit](#mailpit)
* [Meilisearch / Typesense](#search-engines)
* [Healthcheck и порядок старта](#healthchecks)
* [Тома и сброс данных](#volumes)

---

<a id="networking"></a>
## Сеть и имена хостов

В **`.env`** для PHP из контейнера указывайте **имена сервисов** Compose (`pgsql`, `redis`), не `127.0.0.1`. С **хоста** машины БД доступна как `127.0.0.1:${FORWARD_DB_PORT}`.

---

<a id="redis-service"></a>
## Redis (полный пример)

```yaml
redis:
    image: 'redis:alpine'
    ports:
        - '${FORWARD_REDIS_PORT:-6379}:6379'
    volumes:
        - 'sail-redis:/data'
    networks:
        - sail
    healthcheck:
        test: ["CMD", "redis-cli", "ping"]
        retries: 3
        timeout: 5s
```

Том **`sail-redis`** в секции **`volumes:`**. У **`laravel.test`**: `depends_on` (при поддержке — `condition: service_healthy`). **`.env`:** `REDIS_HOST=redis`, `REDIS_PORT=6379`. Проверка: `sail exec redis redis-cli ping`.

---

<a id="postgres-swap"></a>
## MySQL → PostgreSQL

Удалите **`mysql`**, добавьте **`pgsql`** из свежего `sail:install --with=pgsql` или stub’ов [laravel/sail](https://github.com/laravel/sail). **`depends_on`** → `pgsql`. **`.env`:** `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, порт **5432**. `sail build --no-cache` при смене образа. `migrate:fresh` для чистой dev-БД.

---

<a id="mongodb"></a>
## MongoDB и расширение PHP

Сервис **`mongo`**, том **`sail-mongo`**. Пакет вроде **`mongodb/laravel-mongodb`**. В опубликованном **Dockerfile** runtime: `pecl install mongodb` + `docker-php-ext-enable mongodb`, затем **`sail build --no-cache`**. Хост из приложения — **`mongo`**, порт **27017**.

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ как sidecar

```yaml
rabbitmq:
    image: 'rabbitmq:3-management-alpine'
    hostname: rabbitmq
    ports:
        - '${FORWARD_RABBITMQ_PORT:-5672}:5672'
        - '${FORWARD_RABBITMQ_MANAGEMENT:-15672}:15672'
    volumes:
        - 'sail-rabbitmq:/var/lib/rabbitmq'
    networks:
        - sail
    environment:
        RABBITMQ_DEFAULT_USER: '${RABBITMQ_USER:-sail}'
        RABBITMQ_DEFAULT_PASS: '${RABBITMQ_PASSWORD:-password}'
```

Том **`sail-rabbitmq`**. UI на **15672**. Переменные для вашего AMQP-пакета очередей.

---

<a id="mailpit"></a>
## Mailpit

Настройте SMTP на хост **`mailpit`** и порты из compose; письма смотрите в веб-UI проброшенного порта.

---

<a id="search-engines"></a>
## Meilisearch / Typesense

`php artisan sail:install --with=meilisearch` или вставьте блок из stub’ов Sail. **`SCOUT_DRIVER`**, **`MEILISEARCH_HOST`** — на **имя сервиса** и внутренний порт.

---

<a id="healthchecks"></a>
## Healthcheck и порядок старта

`depends_on` не ждёт готовности БД. Добавьте **`healthcheck`** и `service_healthy`, где поддерживается Compose.

---

<a id="volumes"></a>
## Тома и сброс данных

Именованные тома переживают `sail down`. **`sail down -v`** удаляет данные. Точечно — `docker volume rm` по имени.

---

## См. также

[Sail — полный гайд](sail) · [Очереди](sail-queues) · [Окружения и деплой](sail-env-deploy) · [Диагностика](sail-troubleshooting) · [← Все инструменты](../)
