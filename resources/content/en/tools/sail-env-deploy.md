---
title: "Laravel Sail: .env layout, port forwards, CI, and local vs production | DevSense"
description: "Split Laravel Sail and host configuration: .env.example, FORWARD_* ports, APP_URL in Docker, optional env_file, GitHub Actions with docker compose, and checklists when Sail is not your server."
faq:
  - question: "Why do I get 'port already allocated' errors when running sail up?"
    answer: "This occurs when another service on your host machine (like local MySQL or another Sail instance) is already using the port. You can resolve this by changing the FORWARD_DB_PORT or APP_PORT in your .env file."
  - question: "Should I commit my .env file to Git?"
    answer: "No, you must never commit .env. It contains secrets and developer-specific settings. Instead, maintain and commit safe default values inside .env.example."
  - question: "How do I load developer-specific container settings without modifying shared configuration?"
    answer: "You can specify an 'env_file' list under your service inside docker-compose.yml to load a secondary local file (e.g., .env.docker.local) that contains local overrides."
  - question: "Can I run Laravel Sail on a production server?"
    answer: "No. Sail is designed for developer ergonomics and lacks production-grade security, TLS termination, backup systems, and scaling optimizations. Deploy using dedicated configurations (e.g. Kubernetes, Ansible, or Laravel Forge)."
---

# Sail: environments & deployment

You commit a local `.env` file containing database passwords to GitHub, and your security scanners immediately trigger an alert. Or a teammate pulls your repository, runs `sail up`, and watches the process crash because their local MySQL port `3306` is already occupied by another project. Environment management isn't just about defining key-value pairs; it is about drawing a strict boundary between local ergonomics, CI/CD testing, and hardened production deployments.

Containerized development changes how environment variables behave. Since PHP runs inside `laravel.test` while databases bind to host ports, paths, URLs, and port mappings must adjust depending on whether they are accessed from the host machine or from within the Docker Compose network.

In this guide, we show you how to structure `.env` files, avoid host port collisions, set up Docker-based CI pipelines, and audit your configuration before going live on production.

**Navigation:** [All tools](../) · [Sail overview](sail#what-sail-is) · [Databases](sail-databases#networking) · [Queues](sail-queues#connections) · [Troubleshooting](sail-troubleshooting#wsl-filesync)

## Table of contents

* [`.env`, `.env.example`, and secrets](#env-files)
* [`FORWARD_*` ports and collisions](#forward-ports)
* [`APP_URL` and trusted proxies](#app-url)
* [Optional `env_file` in Compose](#compose-env-file)
* [CI: GitHub Actions pattern](#ci-example)
* [Sail vs dev/staging/prod servers](#not-production)
* [Checklist before go-live](#checklist)
* [Common Mistakes](#common-mistakes)
* [Self-Check Questions](#self-check)

---

<a id="env-files"></a>
## `.env`, `.env.example`, and secrets

- **`.env`**: Stores local secrets and environmental details. **Never commit this file to source control.**
- **`.env.example`**: Committed to Git. Default keys should represent safe local defaults so a new developer can copy it to `.env` and run `sail up` immediately.

```dotenv
# .env.example
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306

# Sail specific overrides
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
```

---

<a id="forward-ports"></a>
## `FORWARD_*` ports and collisions

By default, Sail binds database and application ports to localhost. If you run multiple projects, ports like `3306` (MySQL) or `80` (HTTP) will collide.

To fix this, change the forwarded ports in `.env`:

```dotenv
# .env
APP_PORT=8080
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
```

> [!NOTE]
> **Internal vs. External Ports**
> Modifying `FORWARD_DB_PORT` changes the port exposed on your host machine. Inside the Docker network, MySQL still communicates on the default port `3306`. Your Laravel config does not need to change its `DB_PORT`.

---

<a id="app-url"></a>
## `APP_URL` and trusted proxies

Laravel uses `APP_URL` to generate signed URLs and route redirects.

```dotenv
# .env
APP_URL=http://localhost:8080
```

Ensure this matches the port mapped to your host. If you put a load balancer or reverse proxy (like Nginx or Traefik) in front of your production application, configure `TrustProxies` in Laravel to read the client's original IP and protocol (HTTPS) correctly.

---

<a id="compose-env-file"></a>
## Optional `env_file` in Compose

If you have environment variables that only apply to the Docker environment, load them in `docker-compose.yml`:

```yaml
# docker-compose.yml
services:
    laravel.test:
        env_file:
            - .env
            - .env.docker.local
```

This prevents polluting the shared `.env` file with environment variables that only matter inside containers.

---

<a id="ci-example"></a>
## CI: GitHub Actions pattern

You can use Sail's Docker Compose services inside your CI/CD pipeline to run automated tests in a clean environment.

```yaml
# .github/workflows/tests.yml
name: Run Tests
on: [push]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Copy CI Env
        run: cp .env.ci .env
      - name: Start Sail
        run: docker compose up -d
      - name: Run Pest/PHPUnit
        run: docker compose exec -T laravel.test php artisan test
```

---

<a id="not-production"></a>
## Sail vs dev/staging/prod servers

- **Sail** is optimized for local developer speed (includes tools like Mailpit, Meilisearch, and file-watching runtimes).
- **Staging/Production** environments require security hardening, log aggregation, automated backup routines, SSL/TLS certificates, and separate database servers (e.g., AWS RDS).

> [!NOTE]
> Do not attempt to use `sail up` on a public-facing cloud server. It exposes development services and uses configuration stubs that are highly insecure.

---

<a id="checklist"></a>
## Checklist before go-live

- [ ] `APP_ENV=production` and `APP_DEBUG=false` in the production environment.
- [ ] Generate a secure `APP_KEY` using `php artisan key:generate`.
- [ ] Connect to managed database instances, not containerized local databases.
- [ ] Set `QUEUE_CONNECTION=redis` or `sqs` with supervised worker systems.
- [ ] Enable TLS/HTTPS termination.
- [ ] Configure centralized log handlers (e.g., Bugsnag, Sentry, or AWS CloudWatch).

---

## ⚠️ Common Mistakes

**1. Committing the `.env` file to Git**
Exposing API keys, database credentials, or application passwords in repository histories.
*Reality:* Verify that `.env` is listed in your `.gitignore` file before committing.

**2. Changing `DB_PORT` instead of `FORWARD_DB_PORT` for local conflicts**
Changing `DB_PORT=3307` in your `.env` without modifying Sail's compose config will break the internal connection because the container database is still listening on `3306`.
*Reality:* Keep `DB_PORT=3306` (for internal container connection) and set `FORWARD_DB_PORT=3307` (to change the host machine port).

**3. Direct deployment of Sail's `docker-compose.yml` to production**
Spinning up databases alongside the app container without persistent replication, backups, or secure access policies.
*Reality:* Sail's Compose file is a development utility, not a deployment manifest.

---

## 🧠 Self-Check Questions

1. **Why does changing `DB_PORT=3307` inside `.env` break migrations in Sail, while changing `FORWARD_DB_PORT=3307` does not?**
2. **What is the security risk of deploying Sail's Compose file directly to a public production server?**
3. **How does the `env_file` block in `docker-compose.yml` help structure environment overrides?**
4. **Which file should you update to ensure teammates have a list of all required API keys for a new feature?**

<details>
<summary><b>Reveal Answers</b></summary>

1. Changing `DB_PORT` tells Laravel to look for the database on port `3307` inside the container network, where the database container is still listening on `3306`. Changing `FORWARD_DB_PORT` changes only the external port exposed to your host system, leaving internal container networking untouched.
2. It exposes development-only tooling (like Mailpit and Meilisearch) to the public internet, runs containers with developer privileges, and exposes databases without replication or production backups.
3. It allows you to split your variables across multiple files (like `.env` and `.env.docker.local`), loading docker-specific overrides without cluttering the main configuration file.
4. You should update `.env.example` with empty or safe placeholder keys and document their usage.
</details>
