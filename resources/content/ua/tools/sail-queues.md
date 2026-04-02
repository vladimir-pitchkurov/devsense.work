---
title: "Laravel Sail: черги, Horizon, Redis, RabbitMQ | DevSense"
description: "Черги Laravel у Sail: sync/redis/database, queue:work, Horizon, RabbitMQ через пакети, failed jobs, queue:restart, відмінності від production."
---

# Sail: черги та воркери

Контейнери — [Бази даних](sail-databases), env — [Оточення](sail-env-deploy).

**Навігація:** [Усі інструменти](../) · [Sail](sail) · [БД](sail-databases) · [Env](sail-env-deploy)

## Зміст

* [Драйвери](#connections)
* [queue:work](#queue-work)
* [Horizon](#horizon)
* [RabbitMQ](#rabbitmq)
* [Failed jobs](#failed-jobs)
* [queue:restart](#restart)
* [Розклад](#scheduler)
* [Production](#production)

---

<a id="connections"></a>
## Драйвери

`sync` — відладка; `database` / `redis` — воркери; `rabbitmq` — пакет + контейнер. `QUEUE_CONNECTION` у `.env`, `sail artisan config:clear`.

---

<a id="queue-work"></a>
## queue:work

```bash
sail artisan queue:work redis --queue=high,default --tries=3
```

`--once` для однієї задачі.

---

<a id="horizon"></a>
## Horizon

`sail artisan horizon` при `QUEUE_CONNECTION=redis`. На проді — supervisor.

---

<a id="rabbitmq"></a>
## RabbitMQ

[Сервіс](sail-databases#rabbitmq-sidecar), AMQP-пакет, `sail artisan queue:work rabbitmq`.

---

<a id="failed-jobs"></a>
## Failed jobs

`queue:failed`, `queue:retry`, `queue:flush`.

---

<a id="restart"></a>
## queue:restart

Після змін коду: `sail artisan queue:restart`.

---

<a id="scheduler"></a>
## Розклад

`sail artisan schedule:run` або `schedule:work`. На сервері — cron.

---

<a id="production"></a>
## Production

Supervisor, масштаб, керовані Redis/RabbitMQ — не Sail.

---

[Sail](sail) · [БД](sail-databases) · [Env](sail-env-deploy) · [← Усі інструменти](../)
