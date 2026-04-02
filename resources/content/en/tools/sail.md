---
title: "Laravel Sail: Docker local stack, PHP versions, Redis, Postgres, queues & deploy notes | DevSense"
description: "Practical Laravel Sail guide: change PHP version, add Redis or RabbitMQ, switch MySQL to PostgreSQL, MongoDB notes, queue workers in containers, env separation, and how Sail differs from dev/staging/production servers."
---

# Laravel Sail: full local-stack guide

**Laravel Sail** is a **Docker Compose** wrapper around a typical Laravel app: PHP-FPM (the `laravel.test` service), database, Redis, Meilisearch, Selenium, and optional extras. It is aimed at **local development** (and CI that can run Compose)—not a drop-in production topology. This guide walks through common customizations and clarifies where Sail ends and real deployment begins.

**In this series:** [Databases & Docker services](sail-databases) · [Queues & workers](sail-queues) · [Environments & deployment](sail-env-deploy) · [All tools](../)

## Table of contents

* [What Sail is (and is not)](#what-sail-is)
* [Prerequisites and mental model](#prerequisites)
* [Daily commands alias](#daily-commands)
* [Change the PHP version](#php-version)
* [Default services and `sail:install` options](#default-services)
* [Add Redis when it is missing](#add-redis)
* [Add RabbitMQ and wire queues](#add-rabbitmq)
* [Switch from MySQL to PostgreSQL](#mysql-to-postgres)
* [MongoDB (container + Laravel)](#mongodb)
* [Queues: connections, workers, and Sail](#queues)
* [Mail and debugging (Mailpit)](#mail)
* [Volumes, performance (WSL2 / macOS)](#volumes-performance)
* [Xdebug and debugging](#xdebug)
* [Customizing `docker-compose.yml`](#customizing-compose)
* [Environment files: local, Docker-only, teams](#environment-files)
* [Local vs dev/staging/production](#local-vs-deploy)
* [Troubleshooting checklist](#troubleshooting)

---

<a id="what-sail-is"></a>
## What Sail is (and is not)

- **Is:** A **published** `docker-compose.yml` + Dockerfiles (under `vendor/laravel/sail/runtimes/…`) plus the `./vendor/bin/sail` script. Your project **copies** this into the repo when you run `php artisan sail:install` (or install Sail with Laravel).
- **Is not:** A hosting product. On a server you might still use Docker or Kubernetes, but you typically **do not** run the Sail script in production—you run **images**, **orchestration**, health checks, secrets managers, and **supervised** queue workers.

Treat Sail as **one reproducible dev environment** that mirrors *some* production choices (same PHP extensions, same DB engine) without being identical to prod networking or scale.

<a id="prerequisites"></a>
## Prerequisites and mental model

- **Docker Engine** + **Docker Compose v2** installed and running.
- **WSL2** (Windows): store the project **inside the Linux filesystem** (`~/projects/...`), not `C:\...`, for acceptable I/O; bind mounts from NTFS are slow.
- Sail runs commands **inside** containers. Host PHP is optional; most teams use only `./vendor/bin/sail artisan …` and `sail composer …`.

---

<a id="daily-commands"></a>
## Daily commands alias

Add to your shell profile:

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Then: `sail up -d`, `sail artisan migrate`, `sail npm run dev`, `sail shell`, `sail down`.

---

<a id="php-version"></a>
## Change the PHP version

Sail images are built from published **runtime** Dockerfiles. Typical flow:

1. **Publish** Sail assets (if you have not customized yet):

   ```bash
   sail artisan sail:publish
   ```

   This adds `docker-compose.yml` (if not present) and a `Dockerfile` under `vendor/laravel/sail` is referenced from compose—after publish you often get a **`docker/`** (or runtime) path in your project; follow what your Laravel/Sail version generates.

2. In **`docker-compose.yml`**, find the `laravel.test` service **`build.args`** (e.g. `PHP_VERSION=8.3`). Set the desired **minor** (e.g. `8.4`) that Sail supports for your Laravel version.

3. **Rebuild** without cache so the base image layer updates:

   ```bash
   sail build --no-cache
   sail up -d
   ```

4. Confirm:

   ```bash
   sail php -v
   ```

If you maintain a **custom Dockerfile**, change the `FROM` line to the matching `laravel/sail-php/x.y` image (check [laravel/sail on GitHub](https://github.com/laravel/sail) for current tags). After edits, always **`build --no-cache`** to avoid stale layers.

---

<a id="default-services"></a>
## Default services and `sail:install` options

When installing Sail, you choose services, e.g.:

```bash
php artisan sail:install --with=mysql,redis,meilisearch,mailpit,selenium
```

- **`mysql`** / **`pgsql`** / **`mariadb`**: primary SQL database container.
- **`redis`**: cache, session, queue backend.
- **`memcached`**: alternative cache session store.
- **`meilisearch`**, **`typesense`**, **`soketi`**, etc.: optional stacks.

If you **skipped** Redis but your `.env` still has `REDIS_HOST=redis`, either **add** the service (next section) or point `REDIS_HOST` to `127.0.0.1` and run Redis on the host (not recommended for parity).

---

<a id="add-redis"></a>
## Add Redis when it is missing

1. Edit **`docker-compose.yml`**: add a service (names may vary; align with Laravel docs for your Sail version):

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

2. Add the **volume** `sail-redis` under `volumes:` at the bottom of the file.

3. On **`laravel.test`**, ensure `depends_on` includes `redis` if you want startup ordering.

4. In **`.env`** (inside the app container, use **service names** as hosts):

```dotenv
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

5. `sail up -d` and test: `sail exec redis redis-cli ping`.

---

<a id="add-rabbitmq"></a>
## Add RabbitMQ and wire queues

Laravel’s core queue drivers include **`database`**, **`redis`**, **`beanstalkd`**, **`sqs`**, etc. **AMQP/RabbitMQ** is not enabled out of the box; you add a **community package** (e.g. one that provides a `rabbitmq` queue driver) and a **RabbitMQ** container.

**1. Compose service** (example):

```yaml
rabbitmq:
    image: 'rabbitmq:3-management-alpine'
    hostname: rabbitmq
    ports:
        - '${FORWARD_RABBITMQ_PORT:-5672}:5672'
        - '${FORWARD_RABBITMQ_MANAGEMENT_PORT:-15672}:15672'
    volumes:
        - 'sail-rabbitmq:/var/lib/rabbitmq'
    networks:
        - sail
    environment:
        RABBITMQ_DEFAULT_USER: '${RABBITMQ_USER:-sail}'
        RABBITMQ_DEFAULT_PASS: '${RABBITMQ_PASSWORD:-password}'
```

2. Declare volume `sail-rabbitmq`.

3. **`.env`** (example keys—match your package’s docs):

```dotenv
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=sail
RABBITMQ_PASSWORD=password
RABBITMQ_VHOST=/
QUEUE_CONNECTION=rabbitmq
```

4. Install and configure the **queue driver package**; publish its config; run `sail artisan config:clear`.

5. Run a worker **inside** Sail:

```bash
sail artisan queue:work rabbitmq
```

Use the **management UI** on port `15672` to inspect queues and dead letters while developing.

---

<a id="mysql-to-postgres"></a>
## Switch from MySQL to PostgreSQL

**1. Replace the DB service** in `docker-compose.yml`: remove or disable `mysql`, add `pgsql` using the template from a fresh `sail:install --with=pgsql` project or [Sail’s default compose fragments](https://github.com/laravel/sail).

**2. `laravel.test` `depends_on`**: point to `pgsql` instead of `mysql`.

**3. `.env`:**

```dotenv
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

**4. Remove MySQL-specific session or test config** if any.

**5. Rebuild / up:** `sail down -v` (⚠️ destroys volumes) or migrate data properly; then `sail up -d`, `sail artisan migrate:fresh` for a clean dev DB.

**6. PHP extension:** Sail’s PostgreSQL runtime already includes **`pdo_pgsql`**. If you use custom Dockerfiles, ensure the extension is installed.

---

<a id="mongodb"></a>
## MongoDB (container + Laravel)

**1. Add MongoDB** to `docker-compose.yml` (official `mongo` image, persistent volume, port forward if you want Compass from host).

**2. Laravel** usually uses **`mongodb/laravel-mongodb`** (or similar maintained package)—not core Eloquent drivers. Follow that package’s **Sail/Docker** notes: you often must install the **MongoDB PHP extension** in the **Sail Dockerfile** (`pecl install mongodb` + `docker-php-ext-enable mongodb`) and rebuild.

**3. `.env`:** connection string or `DB_HOST`/`DB_PORT` style per package—use hostname **`mongo`** (service name) from `laravel.test`.

**4. Queues / transactions:** MongoDB behaves differently from SQL; migrations are not `Schema` in the same way—read the package docs for **indexes**, **replicas**, and **testing** (in-memory Mongo or Docker service in CI).

---

<a id="queues"></a>
## Queues: connections, workers, and Sail

- **`QUEUE_CONNECTION=sync`** runs jobs **inline**—good for debugging, bad for realistic async behavior.
- **`database`** / **`redis`**: start a worker in the app container:

  ```bash
  sail artisan queue:work --tries=3
  ```

  For **multiple** queues: `queue:work redis --queue=high,default`.

- **Horizon** (Redis): `sail artisan horizon` in dev; in production use **supervisor** or a managed worker.

- **Restart after code changes:** workers cache bootstrapped code; use `queue:restart` or run workers with **`--max-jobs=1`** during heavy TDD (or restart container).

- **RabbitMQ**: see [Add RabbitMQ](#add-rabbitmq); failure modes (NACK, TTL, DLX) differ from Redis lists—test **retry** and **timeout** explicitly.

---

<a id="mail"></a>
## Mail and debugging (Mailpit)

Sail often ships **Mailpit** (or Mailhog in older setups). Point SMTP to the **`mailpit`** host and appropriate ports from `docker-compose.yml` (`MAIL_HOST`, `MAIL_PORT`, etc.). Open the **web UI** on the forwarded port to read outbound mail without sending real email.

---

<a id="volumes-performance"></a>
## Volumes, performance (WSL2 / macOS)

- **Bind mounts** of the whole project can be **slow** on macOS and on WSL2 when files live on Windows drives. Prefer **Linux filesystem** for the repo on WSL2.
- **Named volumes** for `mysql`, `redis`, etc. preserve data across `sail down`; `sail down -v` **deletes** them—use consciously.
- Optional **delegated/cached** mount flags are platform-specific; search current Docker docs for your OS.

---

<a id="xdebug"></a>
## Xdebug and debugging

Sail’s PHP images support **Xdebug** toggled via env (see Sail README for your version), e.g. `SAIL_XDEBUG_MODE=debug,develop`. Configure your IDE to listen on the correct port and map **server path** to **host path**. For **step debugging** of queue workers, attach to the **same** container that runs `queue:work`.

---

<a id="customizing-compose"></a>
## Customizing `docker-compose.yml`

- Keep **service names** stable—your `.env` uses them as hostnames.
- Prefer **environment variables** in compose referencing `.env` (`${VAR}`) so teammates do not commit secrets.
- For **one-off** tools (Adminer, ngrok sidecar), add services on the same **`sail` network** so `laravel.test` can reach them by name.
- After structural changes: `sail build && sail up -d`.

---

<a id="environment-files"></a>
## Environment files: local, Docker-only, teams

- **`.env`**: local secrets; never commit. Often **gitignored** with `.env.example` committed.
- **`.env.example`**: safe defaults and **documentation** for required keys (DB, Redis, queue, mail).
- **Docker-only values:** some teams use `.env.docker` loaded via `docker-compose` `env_file:`—keep overlap clear to avoid “works in Sail, fails on host” confusion.
- **CI:** inject env in the pipeline; same `docker compose` file can run tests with a **test** `.env.testing` and `APP_ENV=testing`.
- **Per-developer overrides:** optional `.env.local` (if your bootstrap loads it) or shell exports for ports `FORWARD_*` to avoid clashes when multiple projects run Sail.

**Forward ports:** `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`, etc. in `.env` prevent collisions when many stacks run on one machine.

---

<a id="local-vs-deploy"></a>
## Local vs dev/staging/production

| Topic | Sail (local) | Typical server |
|--------|----------------|----------------|
| Process model | `sail up`, ad-hoc `artisan` | **php-fpm** + **nginx**, or Octane |
| Queues | Manual `queue:work` / Horizon in terminal | **Supervisor**, systemd, or cloud worker |
| SSL | HTTP on localhost | **TLS** termination, real certs |
| Secrets | `.env` file | Vault, parameter store, sealed secrets |
| Scale | Single container per service | Replicas, load balancers, managed Redis/DB |
| Mail | Mailpit | SMTP relay, SES, etc. |

**Do not** assume “it worked in Sail” means production is configured—especially **queue workers**, **scheduler** (`schedule:run` cron), **OPcache**, and **file storage** (local `storage/` vs S3).

---

<a id="troubleshooting"></a>
## Troubleshooting checklist

- **Permission errors** on `storage/` / `bootstrap/cache/`: `sail artisan cache:clear` and fix ownership (often `sail root-shell` + `chown -R sail:sail storage bootstrap/cache`).
- **“Connection refused” to DB/Redis:** wrong **`DB_HOST` / `REDIS_HOST`** (must be **service name**, not `127.0.0.1`, from inside `laravel.test`).
- **Stale containers** after Dockerfile edits: `sail build --no-cache`.
- **Port already allocated:** change `FORWARD_*` variables in `.env`.
- **Composer inside vs host:** run **`sail composer install`** so extensions and platform match the container.

---

## Closing thoughts

Sail shines when the whole team shares **one Compose file** and the same **PHP extensions** and **service topology**. Invest once in a clean **`docker-compose.yml`**, documented **`.env.example`**, and a short **README** for “first clone” steps (`cp .env.example .env`, `sail up -d`, `sail artisan migrate`). Keep production concerns—**monitoring**, **backups**, **queue supervision**, **secrets**—separate from Sail, and mirror only what you need for realistic local behavior.
