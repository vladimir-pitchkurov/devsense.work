---
title: "Laravel Sail: troubleshooting WSL2, permissions, ports, rebuilds, OPcache & Vite | DevSense"
description: "Fix common Laravel Sail pain points: WSL2 file sync, UID/GID and storage permissions, FORWARD_* port clashes, stale Docker layers, OPcache and Xdebug in dev, npm/Vite split host vs container, and safe volume resets."
---

# Sail: troubleshooting & performance

This guide collects **frequent Sail friction** that is not really “Laravel bugs”: filesystem quirks, Compose networking, image caches, and PHP extensions behaving differently in containers. Use it together with [Databases & services](sail-databases#networking), [Queues & workers](sail-queues#queue-work), and [Environments & deployment](sail-env-deploy#env-files).

**What this page is (and is not):** a **runbook-style** checklist for when Sail “worked yesterday” — ports, bind mounts, Opcache, and dev extensions. It does **not** replace the narrative [Sail overview](sail#what-sail-is); start there for concepts, then return here when you need **symptom → command → next step** flow.

**Navigation:** [All tools](../) · [Sail overview](sail#what-sail-is) · [Databases](sail-databases#networking) · [Queues](sail-queues#connections) · [Env & deploy](sail-env-deploy#forward-ports)

## Table of contents

* [WSL2, Docker Desktop, and file sync](#wsl-filesync)
* [Permissions, `storage`, and `vendor`](#permissions)
* [Port already allocated / `FORWARD_*`](#ports)
* [“It works on the host but not in Sail”](#host-vs-container)
* [Stale code: rebuilds and layers](#rebuild)
* [OPcache and autoload in local dev](#opcache)
* [Xdebug feels slow](#xdebug)
* [Vite, npm, and Node inside vs outside Sail](#frontend)
* [Logs and quick diagnostics](#logs)
* [When to reset volumes (data loss)](#volumes)

---

<a id="wsl-filesync"></a>
## WSL2, Docker Desktop, and file sync

On **Windows + WSL2**, bind-mounting from `/mnt/c/...` into Linux containers is often **slow** and can confuse watchers (Vite, `php artisan serve` file polling). Prefer keeping the project **inside the WSL filesystem** (e.g. `~/projects/...`) and running Sail from there.

If editors on Windows still touch files across the boundary, expect occasional **permission or timestamp** oddities—normalize on one side (all edits in WSL, or all in Windows with a clear rule).

---

<a id="permissions"></a>
## Permissions, `storage`, and `vendor`

Laravel needs **`storage/`** and **`bootstrap/cache/`** writable by the PHP user in **`laravel.test`**. On Linux hosts, UID/GID mapping via **`WWWUSER`** / **`WWWGROUP`** in `.env` (see Sail docs) reduces “root-owned files” after `sail artisan` runs.

Quick checks inside the app container:

```bash
sail exec laravel.test ls -la storage bootstrap/cache
```

If logs fail to write, fix ownership or permissions **inside** the container, not only on the host copy—bind mounts reflect both directions.

---

<a id="ports"></a>
## Port already allocated / `FORWARD_*`

Sail publishes MySQL, Redis, app HTTP, etc. to **localhost**. Another local MySQL or a second Sail project often causes **`address already in use`**.

Set distinct values per project in `.env`:

```dotenv
FORWARD_DB_PORT=3307
APP_PORT=8081
FORWARD_REDIS_PORT=6380
```

Then `sail down && sail up -d`. Inside containers, **internal** ports (3306, 6379) stay the same; only the **host** mapping changes. See [Environments & deployment](sail-env-deploy#forward-ports).

---

<a id="host-vs-container"></a>
## “It works on the host but not in Sail”

Remember: **`php` / `composer` on your Mac** use **host** PHP; **`sail artisan`** uses the **container** PHP and extensions. Missing **`pdo_pgsql`**, **`redis`**, or **`mongodb`** in the image is a classic mismatch—fix by editing the published **Dockerfile** and **`sail build --no-cache`**.

Database hosts in `.env` must be **Compose service names** (`pgsql`, `redis`), not `127.0.0.1`, because the app runs **inside** `laravel.test`. See [Databases & services](sail-databases#networking).

---

<a id="rebuild"></a>
## Stale code: rebuilds and layers

After changing **Dockerfile** steps (`pecl install`, `apt` packages, PHP version base image), a normal `sail up` may still use **cached layers**. Use:

```bash
sail build --no-cache
sail up -d
```

After pulling a new **`composer.lock`**, run `sail composer install` so **`vendor/`** matches the container’s PHP version.

Long-running **queue workers** cache the app bootstrap; use `sail artisan queue:restart` after code changes ([Queues](sail-queues#restart)).

---

<a id="opcache"></a>
## OPcache and autoload in local dev

Production PHP images often enable **OPcache** with aggressive settings. If file changes do not seem to apply, check **`opcache.revalidate_freq`** / **`validate_timestamps`** in **`php.ini`** inside the container (Sail publishes overrides in some setups). For local iteration, ensuring **timestamps are validated** avoids “phantom old code”.

Composer **optimized autoload** (`--optimize-autoloader`) in dev is optional; if class not found errors persist after PSR-4 moves, run `sail composer dump-autoload`.

---

<a id="xdebug"></a>
## Xdebug feels slow

Xdebug **always** adds overhead. Disable it when you do not need breakpoints (Sail supports toggling per their docs / env flags for your version). Prefer targeted debugging sessions instead of leaving Xdebug on all day.

---

<a id="frontend"></a>
## Vite, npm, and Node inside vs outside Sail

Teams split responsibilities:

* Run **`npm run dev`** on the **host** when the Vite server binds to a port you open in the browser.
* Or use Sail’s **Node** service / `sail npm` when everything should stay in Compose.

Mixing them is fine if **`APP_URL`**, **HMR host**, and **proxied ports** line up. Misaligned URLs usually show as **websocket / HMR failed** or **404 on `@vite`**—align ports with [Env & deploy](sail-env-deploy#app-url).

---

<a id="logs"></a>
## Logs and quick diagnostics

```bash
sail logs -f laravel.test
docker compose ps
sail exec laravel.test php -v
sail exec laravel.test php -m
```

For database connectivity from the app container: `sail exec laravel.test php artisan migrate:status` (or a trivial `tinker` connection test). For Redis: `sail exec redis redis-cli ping`.

---

<a id="volumes"></a>
## When to reset volumes (data loss)

**`sail down -v`** removes **named volumes**—**all local DB/Redis data** for that project is gone. Use it when you suspect **corrupted volume state** or after major image upgrades—not as a daily habit.

To remove a **single** volume, use `docker volume ls` and `docker volume rm <name>` so other projects are unaffected. See [Databases & services](sail-databases#volumes).

---

## See also

* [Sail — full guide](sail#what-sail-is)  
* [Databases & services](sail-databases#networking)  
* [Queues & workers](sail-queues#queue-work)  
* [Environments & deployment](sail-env-deploy#env-files)  

[← All tools](../)
