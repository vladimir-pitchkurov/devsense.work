---
title: "Laravel Sail: Docker local stack, PHP versions, Redis, Postgres, queues & deploy notes | DevSense"
description: "Practical Laravel Sail guide: change PHP version, add Redis or RabbitMQ, switch MySQL to PostgreSQL, MongoDB notes, queue workers in containers, env separation, and how Sail differs from dev/staging/production servers."
faq:
  - question: "What is Laravel Sail?"
    answer: "Laravel Sail is a lightweight command-line interface for interacting with Laravel's default Docker development environment, built on Docker Compose."
  - question: "How do I change the PHP version in Laravel Sail?"
    answer: "Change the PHP_VERSION build argument in your docker-compose.yml file under the laravel.test service, then rebuild using sail build --no-cache."
  - question: "Why do I get connection refused errors to Redis or MySQL?"
    answer: "Because you are trying to connect via 127.0.0.1. Inside the Sail container network, you must use the service name (e.g., mysql or redis) as the host."
  - question: "Can I run Laravel Sail in production?"
    answer: "No, Laravel Sail is strictly designed for local development. For production, you should build optimized Docker images and deploy them using proper orchestration like Kubernetes, ECS, or Docker Swarm."
---

# Laravel Sail: The Ultimate Local-Stack Guide

Laravel Sail is a beautiful **Docker Compose** wrapper designed to stand up a complete development environment (PHP-FPM, MySQL, Redis, Meilisearch, Mailpit, etc.) without managing server configurations on your host machine.

However, Sail is **not** a production environment. Understanding the boundary between local Sail and real-world production deployment is key to avoiding the dreaded "works on my machine" syndrome. Let's master Sail together!

---

## Table of Contents
* [Mental Model & Prerequisites](#mental-model)
* [Essential CLI Shortcuts](#shortcuts)
* [Changing PHP Versions](#php-version)
* [Customizing Services (Redis, RabbitMQ, PostgreSQL)](#customizing-services)
* [Debugging with Mailpit & Xdebug](#debugging)
* [WSL2 Performance Optimization](#performance)
* [Sail (Local) vs. Production Comparison](#local-vs-deploy)
* [🧠 Self-Check Questions](#self-check)

---

<a id="mental-model"></a>
## Mental Model & Prerequisites

Sail does not install PHP or Node on your host machine. Instead, it runs commands **inside containerized runtimes**.

If you are on Windows, you **MUST** run Docker inside **WSL2** (Windows Subsystem for Linux) and keep your project folder inside the Linux filesystem (e.g., `~/Development/my-app`), not the Windows `C:\` partition. Working across the Windows-Linux mount boundary causes extreme disk I/O bottlenecks.

---

<a id="shortcuts"></a>
## Essential CLI Shortcuts

Tired of typing `./vendor/bin/sail` every time? Add a shell alias!

```bash
# ~/.zshrc or ~/.bashrc
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Now you can run daily commands efficiently:
- `sail up -d` — Start all services in the background.
- `sail down` — Stop services.
- `sail artisan migrate` — Run database migrations.
- `sail npm run dev` — Run Vite assets locally.

---

<a id="php-version"></a>
## Changing PHP Versions

To upgrade or downgrade your PHP version in Sail, you must modify your Compose config.

```yaml
# docker-compose.yml
services:
    laravel.test:
        build:
            context: ./vendor/laravel/sail/runtimes/8.4
            dockerfile: Dockerfile
            args:
                WWWGROUP: '${WWWGROUP}'
                # Change this build argument to the desired minor version:
                PHP_VERSION: '8.4'
```

After modifying the file, rebuild the container layers without cache:

```bash
# Terminal
sail build --no-cache
sail up -d
```

---

<a id="customizing-services"></a>
## Customizing Services

### 1. Connecting to Redis
If you skipped Redis during installation, you can add it by editing your Compose configuration:

```yaml
# docker-compose.yml
services:
    redis:
        image: 'redis:alpine'
        ports:
            - '${FORWARD_REDIS_PORT:-6379}:6379'
        volumes:
            - 'sail-redis:/data'
        networks:
            - sail
```

Make sure your environment file matches this:

```dotenv
# .env
REDIS_HOST=redis
REDIS_PORT=6379
```

> [!NOTE]
> **Did you know?**
> Inside the Sail network, containers communicate with each other using their service names (like `redis` or `mysql`) as hostnames. Using `127.0.0.1` will fail because it points to the application container itself!

### 2. Switching from MySQL to PostgreSQL
To swap database engines, replace the `mysql` block with `pgsql` in your `docker-compose.yml`:

```yaml
# docker-compose.yml
services:
    pgsql:
        image: 'postgres:15-alpine'
        ports:
            - '${FORWARD_DB_PORT:-5432}:5432'
        environment:
            POSTGRES_DB: '${DB_DATABASE}'
            POSTGRES_USER: '${DB_USERNAME}'
            POSTGRES_PASSWORD: '${DB_PASSWORD}'
        volumes:
            - 'sail-pgsql:/var/lib/postgresql/data'
        networks:
            - sail
```

And update your `.env`:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
```

---

<a id="debugging"></a>
## Debugging with Mailpit & Xdebug

### Mailpit (Fake Mail Server)
Sail automatically routes outgoing mail to Mailpit. It intercepts emails so you don't accidentally send test messages to real customers. Configure SMTP in `.env`:

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```
You can view the intercepted emails by opening the web UI at `http://localhost:8025`.

### Xdebug
To enable step-by-step debugging, simply set the Xdebug mode in your environment:

```dotenv
# .env
SAIL_XDEBUG_MODE=develop,debug
```
Restart Sail (`sail down && sail up -d`) to apply the configuration.

---

<a id="local-vs-deploy"></a>
## Sail (Local) vs. Production Comparison

| Feature | Laravel Sail (Local Dev) | Production Environment |
|---------|--------------------------|------------------------|
| **HTTP Server** | PHP built-in server | Nginx / Apache + PHP-FPM, or Octane |
| **Queues** | Run via terminal `sail artisan queue:work` | Managed via Supervisor or Systemd |
| **SSL / HTTPS** | Plain HTTP on localhost | TLS terminated on Nginx, Cloudflare or Load Balancer |
| **Cache & Sessions** | File storage or local Redis container | Clustered Redis or Managed Memcached |
| **Database** | Ephemeral container | Managed DB (RDS, Cloud SQL) with backups |

---

## ⚠️ Common Mistakes

**1. Connecting to `127.0.0.1` for Redis/Database inside Sail**
If your `DB_HOST` is set to `127.0.0.1` in `.env`, your app will not be able to find the database container. Use the service name (`mysql` or `pgsql`) instead.

**2. Running commands on the host instead of the container**
Running `composer install` or `php artisan migrate` on your local terminal can lead to version mismatches or missing PHP extension errors. Always run them through Sail:
```bash
# Terminal
sail composer install
sail artisan migrate
```

---

<a id="self-check"></a>
## 🧠 Self-Check Questions

1. **Why is it important to use WSL2 filesystem paths (like `~/Development`) instead of mounting directly from Windows partitions (`/mnt/c/...`)?**
2. **True or False?** To enable PostgreSQL, you must manually download and compile the `pdo_pgsql` driver inside the Sail container.
3. **What hostname should you use in `.env` to send emails via Mailpit inside Sail?**
4. **How do you restart a queue worker in Sail so that it loads updated code changes?**

<details>
<summary><b>Reveal Answers</b></summary>

1. Mounting from Windows drives (`C:\`) onto a Linux Docker container introduces severe translation and virtualization layers, slowing down file operations and asset compilation (Vite/Mix) up to 10x.
2. **False.** Sail's pre-built PHP runtimes already bundle standard extensions, including `pdo_pgsql`. You only need to switch the service in `docker-compose.yml` and `.env`.
3. You must use `mailpit` as the host.
4. Run `sail artisan queue:restart`, or run the worker with `--max-jobs=1` during development.
</details>
