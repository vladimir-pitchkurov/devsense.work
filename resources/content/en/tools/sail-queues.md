---
title: "Laravel Sail: queue workers, Horizon, Redis, RabbitMQ & failed jobs | DevSense"
description: "Run Laravel queues inside Sail: sync vs redis vs database, queue:work and Horizon locally, RabbitMQ with community drivers, failed_jobs, queue:restart, and how production differs."
faq:
  - question: "Why are my background jobs not executing in Sail?"
    answer: "Unlike the 'sync' driver, drivers like 'database' or 'redis' require a running worker process. You must start a worker container or run 'sail artisan queue:work' in your terminal."
  - question: "Why do my changes in Job classes not take effect in the queue?"
    answer: "Laravel queue workers load the application code into memory once. When you make code modifications, you must run 'sail artisan queue:restart' to force workers to reload."
  - question: "How do I run the scheduler in Laravel Sail without a host cron job?"
    answer: "You can run 'sail run --rm laravel.test php artisan schedule:work' in a separate terminal window, which polls and runs tasks every minute locally."
  - question: "How does queue management in Sail differ from production?"
    answer: "In Sail, workers run in the foreground of your terminal and stop when you close it. In production, process managers like Supervisor or Kubernetes keep workers running persistently."
---

# Sail: queues & workers

You trigger a background task in your Laravel app, but nothing happens. The job page says "Pending," and your database records remain unchanged. Then it hits you: in a containerized environment, background jobs do not run themselves. Without an active worker process listening inside the Sail container network, your queues are just dead files or silent Redis lists.

Running queue workers and task schedulers on your host OS will fail because they lack access to Sail's containerized databases, environment variables, and PHP extensions. To mirror production execution, you must run and manage your worker runtimes inside the Compose network.

In this guide, we show you how to configure queue connections, run workers and Horizon, handle job failures, and manage code updates without stale worker caches.

**Navigation:** [All tools](../) · [Sail overview](sail#what-sail-is) · [Databases](sail-databases#networking) · [Env & deploy](sail-env-deploy#forward-ports) · [Troubleshooting](sail-troubleshooting#wsl-filesync)

## Table of contents

* [Connections at a glance](#connections)
* [Running `queue:work` in Sail](#queue-work)
* [Horizon (Redis)](#horizon)
* [RabbitMQ and AMQP packages](#rabbitmq)
* [Failed jobs and retries](#failed-jobs)
* [Code changes and `queue:restart`](#restart)
* [Scheduler (`schedule:run`)](#scheduler)
* [Contrast with production](#production)
* [Common Mistakes](#common-mistakes)
* [Self-Check Questions](#self-check)

---

<a id="connections"></a>
## Connections at a glance

Select your queue driver in `.env`:

```dotenv
# .env
QUEUE_CONNECTION=redis
```

| Driver | Use case in Sail |
|--------|-------------------|
| **`sync`** | Debug job logic synchronously without starting a worker. |
| **`database`** | Simple async queue; requires running `php artisan queue:table` and a worker. |
| **`redis`** | Production-standard driver; integrates with Horizon; requires a Redis service. |
| **`rabbitmq`** | AMQP exchanges; requires a RabbitMQ sidecar container and community package. |

> [!NOTE]
> **Configuration caching**
> If you modify `QUEUE_CONNECTION` or any queue environment variables, run `sail artisan config:clear` or `sail artisan config:cache` to apply the changes to your running containers.

---

<a id="queue-work"></a>
## Running `queue:work` in Sail

To process jobs, open a dedicated terminal window and run:

```bash
# Terminal
sail artisan queue:work
```

For advanced controls, target specific connections and options:

```bash
# Terminal
sail artisan queue:work redis --queue=high,default --tries=3 --timeout=90
```

To run a single job and exit (useful for debugging):

```bash
# Terminal
sail artisan queue:work --once
```

---

<a id="horizon"></a>
## Horizon (Redis)

Laravel Horizon provides a beautiful dashboard and code-driven configuration for Redis queues.

1. Install Horizon via Composer:
   ```bash
   sail composer require laravel/horizon
   sail artisan horizon:install
   ```
2. Start Horizon inside your container:

```bash
# Terminal
sail artisan horizon
```

Access the dashboard at `http://localhost/horizon`. In development, Horizon stops when you press `Ctrl+C`.

---

<a id="rabbitmq"></a>
## RabbitMQ and AMQP packages

Laravel does not include a first-party RabbitMQ driver. To use AMQP:

1. Add the RabbitMQ service to `docker-compose.yml` ([see Database recipes](sail-databases#rabbitmq-sidecar)).
2. Install a community-maintained package (e.g., `vyuldashev/laravel-queue-rabbitmq`).
3. Set your connection details:

```dotenv
# .env
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
```

4. Start processing:
   ```bash
   sail artisan queue:work rabbitmq
   ```

---

<a id="failed-jobs"></a>
## Failed jobs and retries

When a background job throws an exception, Laravel logs it to the `failed_jobs` table.

- List failed jobs:
  ```bash
  sail artisan queue:failed
  ```
- Retry a specific job:
  ```bash
  sail artisan queue:retry 5
  ```
- Clear all failed jobs:
  ```bash
  sail artisan queue:flush
  ```

---

<a id="restart"></a>
## Code changes and `queue:restart`

Laravel workers boot the application once and process jobs in a continuous loop. If you edit a Job file, the running worker will continue executing the old code cached in memory.

Whenever you save changes to your codebase, restart the workers:

```bash
# Terminal
sail artisan queue:restart
```

> [!NOTE]
> **Autoreloading in Dev**
> To avoid typing `queue:restart` constantly during rapid local development, you can run the worker with the `--max-jobs=1` or `--once` flags.

---

<a id="scheduler"></a>
## Scheduler (`schedule:run`)

Sail does not run a system cron daemon automatically. To run scheduled tasks in development, you have two options:

1. Run the scheduler manually:
   ```bash
   sail artisan schedule:run
   ```
2. Run a continuous polling loop in the background:

```bash
# Terminal
sail run --rm laravel.test php artisan schedule:work
```

---

<a id="production"></a>
## Contrast with production

| Topic | Sail (Local Dev) | Production Environment |
|-------|------------------|------------------------|
| **Worker Manager** | Manual terminal foreground | **Supervisor** or systemd daemon |
| **Scaling** | Single Docker container | Multiple containers / Kubernetes autoscaling |
| **Failover** | Manual container restart | Redundant nodes and clustering |

---

## ⚠️ Common Mistakes

**1. Modifying Job code without restarting the worker**
You fix a bug in your Job class, but the queue runner continues throwing the same error.
*Reality:* The worker has cached the old PHP files. Run `sail artisan queue:restart`.

**2. Running `php artisan queue:work` on your host terminal**
Running the command directly on your host machine will crash due to missing database drivers or an inability to resolve internal hostnames like `redis` or `mysql`.
*Reality:* Always prefix with `sail` to run inside the container environment: `sail artisan queue:work`.

**3. Misunderstanding the `sync` queue driver**
Using `QUEUE_CONNECTION=sync` runs jobs immediately inside the HTTP request lifecycle. This hides concurrency issues, timeouts, and serialization errors that will only appear in production.
*Reality:* Use `database` or `redis` locally to match production behavior.

---

## 🧠 Self-Check Questions

1. **Why does a running queue worker fail to execute new code changes you just saved to disk?**
2. **What hostname should you write in your `.env` for RabbitMQ when running inside Sail?**
3. **How do you keep the Laravel task scheduler running continuously without setting up host-side cron jobs?**
4. **Which artisan command do you run to re-queue a job that failed during execution?**

<details>
<summary><b>Reveal Answers</b></summary>

1. PHP CLI workers boot the entire application framework once and keep it in memory. They do not re-read files from disk for subsequent jobs. You must run `sail artisan queue:restart`.
2. You must use the Compose service name, which is `rabbitmq`.
3. Run `sail run --rm laravel.test php artisan schedule:work`, which starts a long-running process that runs the scheduler every minute.
4. Run `sail artisan queue:retry {id}` where `{id}` is the identifier from the `failed_jobs` table or `all` to retry all failed jobs.
</details>
