---
title: "Laravel Sail: .env layout, port forwards, CI, and local vs production | DevSense"
description: "Split Laravel Sail and host configuration: .env.example, FORWARD_* ports, APP_URL in Docker, optional env_file, GitHub Actions with docker compose, and checklists when Sail is not your server."
---

# Sail: environments & deployment

How to **organize environment variables** for Sail, teammates, and CI—and how that differs from **staging/production**. See also [Sail overview](sail), [Databases](sail-databases), and [Queues](sail-queues).

**Navigation:** [All tools](../) · [Sail overview](sail) · [Databases](sail-databases) · [Queues](sail-queues)

## Table of contents

* [`.env`, `.env.example`, secrets](#env-files)
* **`FORWARD_*` ports and collisions](#forward-ports)
* **`APP_URL` and trusted proxies](#app-url)
* **Optional `env_file` in Compose](#compose-env-file)
* **CI: GitHub Actions pattern](#ci-example)
* **Sail vs dev/staging/prod servers](#not-production)
* **Checklist before go-live](#checklist)

---

<a id="env-files"></a>
## `.env`, `.env.example`, secrets

- **`.env`**: local secrets; **never commit**. Copy from **`.env.example`** on first clone.
- **`.env.example`**: safe defaults, **document every key** the app needs (`DB_*`, `REDIS_*`, `QUEUE_*`, `MAIL_*`, Scout, etc.). Sail-specific: **`WWWUSER`**, **`WWWGROUP`**, **`FORWARD_DB_PORT`**, …
- **Teams:** agree whether host-run commands (e.g. `npm` on Mac) need any Laravel env; often only containerized commands matter.
- **Production:** inject secrets via **hosting provider**, **Vault**, or CI **masked variables**—not a copied `.env` file on disk in the image.

---

<a id="forward-ports"></a>
## `FORWARD_*` ports and collisions

Sail forwards DB, Redis, Meilisearch, etc. to **localhost**. When several projects run at once, set in **`.env`**:

```dotenv
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
```

Restart Compose after changes. Inside containers, **ports stay default** (`3306`, `6379`); only **host** mapping changes.

---

<a id="app-url"></a>
## `APP_URL` and trusted proxies

For browser-facing features (signed URLs, some OAuth callbacks), set:

```dotenv
APP_URL=http://localhost
```

Use the **host port** you actually open (e.g. `http://localhost:80` if mapped). Behind **Traefik/nginx** on a real server, configure **`TrustProxies`** and real **`APP_URL`** with `https`.

---

<a id="compose-env-file"></a>
## Optional `env_file` in Compose

You can load an extra file in **`docker-compose.yml`**:

```yaml
laravel.test:
    env_file:
        - .env
        - .env.docker.local
```

Use this for **developer-specific** overrides without editing shared `.env`. Document required keys in README to avoid “works on my machine” gaps.

---

<a id="ci-example"></a>
## CI: GitHub Actions pattern

Minimal idea: checkout, copy **`.env.ci`** → `.env`, `docker compose run` tests.

```yaml
- name: Run tests in Sail
  run: |
    cp .env.ci .env
    docker compose up -d
    docker compose exec -T laravel.test php artisan test
```

Adjust service names to your **published** compose file. Cache **Composer**/**npm** in CI separately from Docker layer cache to save time.

---

<a id="not-production"></a>
## Sail vs dev/staging/prod servers

- **Sail** optimizes **developer ergonomics** (Mailpit, forwarded ports, single-node DB).
- **Shared dev/staging** often uses **one** long-lived environment; still not production HA.
- **Production** needs **TLS**, **backups**, **monitoring**, **log aggregation**, **queue supervision**, **database replicas**, and **secret rotation**—none of which Sail solves by itself.

You may **reuse** the same Docker images built from Sail Dockerfiles, but **orchestration** (Compose file, env, scaling) will differ.

---

<a id="checklist"></a>
## Checklist before go-live

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, strong `APP_KEY`
- [ ] Real **`DB_*`** / **`REDIS_*`** endpoints (managed services)
- [ ] **`QUEUE_CONNECTION`** matches a **supervised** worker setup
- [ ] **`schedule:run`** in cron (or managed scheduler)
- [ ] Mail/SMS/payment **live** keys in secret store
- [ ] **`LOG_CHANNEL`** and retention appropriate for compliance

---

## See also

* [Sail — full guide](sail)  
* [Databases & services](sail-databases)  
* [Queues & workers](sail-queues)  

[← All tools](../)
