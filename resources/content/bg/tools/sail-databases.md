---
title: "Laravel Sail: БД, Redis, Postgres, MongoDB, RabbitMQ в Docker Compose | DevSense"
description: "Compose рецепти за Sail: Redis, смяна към PostgreSQL, MongoDB и PHP разширение, RabbitMQ, Mailpit/Meilisearch, healthcheck и томове."
---

# Sail: бази данни и Docker услуги

Допълва [пълния Sail гайд](sail). Воркери — [Опашки](sail-queues), `.env` — [Среди и деплой](sail-env-deploy).

**Навигация:** [Всички инструменти](../) · [Sail](sail) · [Опашки](sail-queues) · [Env](sail-env-deploy)

## Съдържание

* [Мрежа и хостове](#networking)
* [Redis](#redis-service)
* [MySQL → PostgreSQL](#postgres-swap)
* [MongoDB](#mongodb)
* [RabbitMQ](#rabbitmq-sidecar)
* [Mailpit](#mailpit)
* [Meilisearch / Typesense](#search-engines)
* [Healthcheck](#healthchecks)
* [Томове](#volumes)

---

<a id="networking"></a>
## Мрежа и хостове

В **`.env`** от контейнера ползвайте **имена на услуги** (`pgsql`, `redis`), не `127.0.0.1`. От хоста — `127.0.0.1:${FORWARD_DB_PORT}`.

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

Том **`sail-redis`**. **`.env`:** `REDIS_HOST=redis`. Тест: `sail exec redis redis-cli ping`.

---

<a id="postgres-swap"></a>
## MySQL → PostgreSQL

Заменете **`mysql`** с **`pgsql`** от stub на Sail. **`.env`:** `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, порт **5432**. `sail build --no-cache` при нужда.

---

<a id="mongodb"></a>
## MongoDB

Услуга **`mongo`**, том. Пакет **`mongodb/laravel-mongodb`**; в Dockerfile: `pecl install mongodb`, enable, rebuild. Хост **`mongo`**.

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

Том **`sail-rabbitmq`**. UI **15672**.

---

<a id="mailpit"></a>
## Mailpit

SMTP към **`mailpit`**, портове от compose.

---

<a id="search-engines"></a>
## Meilisearch / Typesense

`sail:install --with=meilisearch` или блок от upstream stubs.

---

<a id="healthchecks"></a>
## Healthcheck

`healthcheck` + `depends_on` с `service_healthy` където Compose го поддържа.

---

<a id="volumes"></a>
## Томове

`sail down -v` изтрива данни.

---

[Sail](sail) · [Опашки](sail-queues) · [Env](sail-env-deploy) · [← Всички инструменти](../)
