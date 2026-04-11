---
title: "Laravel Sail: queue workers, Horizon, Redis, RabbitMQ & failed jobs | DevSense"
description: "Run Laravel queues inside Sail: sync vs redis vs database, queue:work and Horizon locally, RabbitMQ with community drivers, failed_jobs, queue:restart, and how production differs."
---

# Sail: queues & workers

How to run **Laravel queues** when the app lives in **Sail**. Pair this with [Databases & services](sail-databases#networking) for Redis/RabbitMQ containers and [Environments & deployment](sail-env-deploy#env-files) for `.env` across machines.

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

---

<a id="connections"></a>
## Connections at a glance

| Driver | Use case in Sail |
|--------|-------------------|
| **`sync`** | Debug job logic without a worker; no concurrency. |
| **`database`** | Simple async; needs `jobs` table and **one or more workers**. |
| **`redis`** | Typical choice; pairs with Horizon; needs **Redis service**. |
| **`sqs`** | Cloud queue; configure AWS keys; less common purely local. |
| **`rabbitmq`** (via package) | AMQP exchanges/queues; needs **container + package**. |

Set **`QUEUE_CONNECTION`** in `.env`. Run `sail artisan config:clear` after changes.

---

<a id="queue-work"></a>
## Running `queue:work` in Sail

```bash
sail artisan queue:work
```

Useful flags:

```bash
sail artisan queue:work redis --queue=high,default --tries=3 --timeout=90
```

Run **multiple terminals** if you want separate workers for different queues. For one-off jobs:

```bash
sail artisan queue:work --once
```

---

<a id="horizon"></a>
## Horizon (Redis)

Requires **`QUEUE_CONNECTION=redis`** and Horizon installed/configured.

```bash
sail artisan horizon
```

Open Horizon’s dashboard on the path you configured (often `/horizon` behind auth). On production, **Supervisor** or systemd keeps Horizon alive; in Sail you stop it with Ctrl+C when you close the terminal.

---

<a id="rabbitmq"></a>
## RabbitMQ and AMQP packages

Laravel does not ship a first-party **RabbitMQ** driver. Steps:

1. Add a **RabbitMQ** service to Compose ([example](sail-databases#rabbitmq-sidecar)).
2. Install a maintained **AMQP** queue package; publish config.
3. Set **`QUEUE_CONNECTION`** (or connection name) per package docs.
4. Run:

```bash
sail artisan queue:work rabbitmq
```

Test **delayed** jobs, **failed** routing, and **TTL**—AMQP semantics differ from Redis lists.

---

<a id="failed-jobs"></a>
## Failed jobs and retries

- Ensure **`failed_jobs`** migration ran when using the `database` failed driver.
- Inspect: `sail artisan queue:failed`
- Retry one: `sail artisan queue:retry {id}`
- Flush: `sail artisan queue:flush`

Tune **`$tries`**, **`$timeout`**, and **`backoff`** on jobs to avoid infinite poison messages.

---

<a id="restart"></a>
## Code changes and `queue:restart`

Long-running workers **cache** the application bootstrap. After deploying code (or switching branches):

```bash
sail artisan queue:restart
```

Horizon honors this as well. During aggressive local iteration, **`--max-jobs=1`** or **`--once`** reduces stale-code surprises.

---

<a id="scheduler"></a>
## Scheduler (`schedule:run`)

Sail does not add a **cron** daemon. Options:

- Run manually: `sail artisan schedule:run`
- Use **`sail run --rm laravel.test php artisan schedule:work`** (Laravel 8+) for a long-lived scheduler process in dev.
- On production, a **single** server cron entry calls `schedule:run` every minute.

---

<a id="production"></a>
## Contrast with production

| Topic | Sail (local) | Production |
|-------|--------------|------------|
| Worker lifecycle | Manual terminal / Horizon foreground | **Supervisor**, Kubernetes Job, or managed worker |
| Scaling | One container | Multiple workers, autoscale |
| Redis | Single container | Cluster / managed ElastiCache, etc. |
| RabbitMQ | Dev container | Cluster, policies, monitoring |

Treat Sail as **correct enough** to write jobs and run integration tests—not as a load or HA model.

---

## See also

* [Sail — full guide](sail#what-sail-is)  
* [Databases & services](sail-databases#networking)  
* [Environments & deployment](sail-env-deploy#env-files)  

[← All tools](../)
