---
title: "Laravel Sail: БД, Redis, Postgres, MongoDB, RabbitMQ у Docker Compose | DevSense"
description: "Compose-рецепти для Sail: Redis, перехід на PostgreSQL, MongoDB і розширення PHP, RabbitMQ, Mailpit/Meilisearch, healthcheck і томи."
---

# Sail: бази даних і Docker-сервіси

Сервіси поруч із **`laravel.test`**. Доповнює [повний гайд Sail](sail). Воркери — [Черги](sail-queues), `.env` — [Оточення та деплой](sail-env-deploy).

**Навігація:** [Усі інструменти](../) · [Sail](sail) · [Черги](sail-queues) · [Env](sail-env-deploy)

## Зміст

* [Мережа та хости](#networking)
* [Redis](#redis-service)
* [MySQL → PostgreSQL](#postgres-swap)
* [MongoDB](#mongodb)
* [RabbitMQ](#rabbitmq-sidecar)
* [Mailpit](#mailpit)
* [Meilisearch / Typesense](#search-engines)
* [Healthcheck](#healthchecks)
* [Томи](#volumes)

---

<a id="networking"></a>
## Мережа та хости

У **`.env`** з контейнера додатку використовуйте **імена сервісів** (`pgsql`, `redis`), не `127.0.0.1`. З хоста — `127.0.0.1:${FORWARD_DB_PORT}`.

---

<a id="redis-service"></a>
## Redis

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
```

Том **`sail-redis`** у **`volumes:`**. **`.env`:** `REDIS_HOST=redis`. Перевірка: `sail exec redis redis-cli ping`.

---

<a id="postgres-swap"></a>
## MySQL → PostgreSQL

Замініть **`mysql`** на **`pgsql`** (stub із `sail:install --with=pgsql` або [laravel/sail](https://github.com/laravel/sail)). **`.env`:** `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, порт **5432**. `sail build --no-cache` за потреби.

---

<a id="mongodb"></a>
## MongoDB

Сервіс **`mongo`**, том. Пакет **`mongodb/laravel-mongodb`**; у Dockerfile: `pecl install mongodb`, `docker-php-ext-enable mongodb`, пересборка. Хост **`mongo`**.

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ

```yaml
rabbitmq:
    image: 'rabbitmq:3-management-alpine'
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

Том **`sail-rabbitmq`**. UI на **15672**.

---

<a id="mailpit"></a>
## Mailpit

`MAIL_HOST=mailpit`, порти з compose; листи в браузері.

---

<a id="search-engines"></a>
## Meilisearch / Typesense

`sail:install --with=meilisearch` або фрагмент з upstream. Змінні Scout на **ім’я сервісу**.

---

<a id="healthchecks"></a>
## Healthcheck

`healthcheck` + `depends_on` з `service_healthy`, якщо підтримує Compose.

---

<a id="volumes"></a>
## Томи

`sail down` зберігає томи; **`sail down -v`** стирає дані.

---

[Sail](sail) · [Черги](sail-queues) · [Env](sail-env-deploy) · [← Усі інструменти](../)
