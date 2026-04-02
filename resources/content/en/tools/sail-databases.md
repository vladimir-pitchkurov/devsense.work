---
title: "Laravel Sail: databases, Redis, Postgres, MongoDB, RabbitMQ in Docker Compose | DevSense"
description: "Compose-first recipes for Laravel Sail: add Redis, switch to PostgreSQL, run MongoDB with the PHP extension, RabbitMQ sidecar, Mailpit and Meilisearch wiring, healthchecks and named volumes."
---

# Sail: databases & Docker services

This guide focuses on **services next to `laravel.test`**: SQL engines, Redis, optional RabbitMQ, MongoDB, and dev-friendly extras. It complements the [full Sail guide](sail). For queue worker commands see [Queues & workers](sail-queues); for `.env` layout and servers see [Environments & deployment](sail-env-deploy).

**Navigation:** [All tools](../) · [Sail overview](sail) · [Queues](sail-queues) · [Env & deploy](sail-env-deploy) · [Troubleshooting](sail-troubleshooting)

## Table of contents

* [Network and hostname rules](#networking)
* [Redis service (full example)](#redis-service)
* [Replace MySQL with PostgreSQL](#postgres-swap)
* [MongoDB + PHP extension in Sail](#mongodb)
* [RabbitMQ as a container](#rabbitmq-sidecar)
* [Mailpit (SMTP dev)](#mailpit)
* [Meilisearch / Typesense (optional search)](#search-engines)
* [Healthchecks and startup order](#healthchecks)
* [Named volumes and data reset](#volumes)

---

<a id="networking"></a>
## Network and hostname rules

All app-side hosts in **`.env`** must be **Compose service names** (`pgsql`, `redis`, `mongo`), not `127.0.0.1`, because PHP runs **inside** `laravel.test`. From your **host machine** you reach DB via **`127.0.0.1:${FORWARD_DB_PORT}`** when ports are forwarded.

---

<a id="redis-service"></a>
## Redis service (full example)

Add to **`docker-compose.yml`** (adjust names to match your published Sail file):

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

Register **`sail-redis`** under top-level **`volumes:`**. On **`laravel.test`** add:

```yaml
depends_on:
    redis:
        condition: service_healthy
```

(If your Compose version does not support `condition`, use plain `depends_on: [redis]`.)

**.env:**

```dotenv
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Verify: `sail exec redis redis-cli ping`.

---

<a id="postgres-swap"></a>
## Replace MySQL with PostgreSQL

1. Remove (or comment) the **`mysql`** service and its volume.
2. Add **`pgsql`** from a fresh Sail stub (`php artisan sail:install --with=pgsql` in a throwaway project) or from [laravel/sail](https://github.com/laravel/sail) `stubs/pgsql.stub` for your version.
3. Point **`laravel.test`** `depends_on` at **`pgsql`**.
4. **`.env`:**

```dotenv
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

5. Rebuild if the PHP image changes: `sail build --no-cache && sail up -d`.
6. Fresh DB: `sail artisan migrate:fresh` (destroys local data unless you dump/restore).

---

<a id="mongodb"></a>
## MongoDB + PHP extension in Sail

**1. Compose service** (minimal):

```yaml
mongo:
    image: 'mongo:7'
    ports:
        - '${FORWARD_MONGO_PORT:-27017}:27017'
    volumes:
        - 'sail-mongo:/data/db'
    networks:
        - sail
```

2. Add **`sail-mongo`** to **`volumes:`**.

3. **Laravel** typically uses **`mongodb/laravel-mongodb`**. The PHP **mongodb** extension must exist in **`laravel.test`**. After `sail:publish`, edit the **runtime Dockerfile** (e.g. `docker/8.3/Dockerfile`):

```dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

4. Rebuild: `sail build --no-cache`.

5. Configure the package with host **`mongo`** and port **27017** from inside the app container.

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ as a container

Use when you adopt an **AMQP** queue driver package (not core Laravel):

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

Declare volume **`sail-rabbitmq`**. Set **`.env`** keys expected by your queue package (`RABBITMQ_HOST=rabbitmq`, etc.). Management UI: `http://localhost:15672` (default guest/guest **disabled** when you set default user above—use **sail/password** from the example).

---

<a id="mailpit"></a>
## Mailpit (SMTP dev)

Sail often includes **Mailpit**. Point Laravel mail to the **`mailpit`** host and the **SMTP** port from `docker-compose.yml` (commonly `1025` inside the network, forwarded on host). Read messages in the **HTTP UI** on the forwarded web port—no real mail leaves the machine.

---

<a id="search-engines"></a>
## Meilisearch / Typesense (optional search)

Install-time flag example:

```bash
php artisan sail:install --with=meilisearch
```

Or paste the upstream **Meilisearch** / **Typesense** service block from Sail stubs. Set **`SCOUT_DRIVER`** / **`MEILISEARCH_HOST`** (or equivalent) to the **service name** and internal port.

---

<a id="healthchecks"></a>
## Healthchecks and startup order

**`depends_on`** alone does not wait for DB readiness. Prefer **`healthcheck`** on `mysql`/`pgsql`/`redis` and `condition: service_healthy` where Compose supports it so `php artisan migrate` in entry scripts fails less often.

---

<a id="volumes"></a>
## Named volumes and data reset

- **Named volumes** persist across `sail down`.
- **`sail down -v`** deletes those volumes—**all local DB data gone**.
- For a clean slate without nuking other projects, use **`docker volume rm`** on the specific volume name printed by `docker volume ls`.

---

## See also

* [Sail — full guide](sail) for the big picture  
* [Queues & workers](sail-queues) for `queue:work`, Horizon, RabbitMQ drivers  
* [Environments & deployment](sail-env-deploy) for `.env` split and production contrast  
* [Troubleshooting](sail-troubleshooting) for WSL2, permissions, ports, rebuilds  

[← All tools](../)
