---
title: "Laravel Sail: опашки, Horizon, Redis, RabbitMQ | DevSense"
description: "Опашки Laravel в Sail: sync/redis/database, queue:work, Horizon, RabbitMQ с пакети, failed jobs, queue:restart, разлики с production."
---

# Sail: опашки и workers

Услуги — [Бази данни](sail-databases), env — [Среди и деплой](sail-env-deploy).

**Навигация:** [Всички инструменти](../) · [Sail](sail) · [БД](sail-databases) · [Env](sail-env-deploy)

## Съдържание

* [Връзки](#connections)
* [queue:work](#queue-work)
* [Horizon](#horizon)
* [RabbitMQ](#rabbitmq)
* [Failed jobs](#failed-jobs)
* [queue:restart](#restart)
* [График](#scheduler)
* [Production](#production)

---

<a id="connections"></a>
## Връзки

`sync` за отладка; `database`/`redis` с workers; `rabbitmq` с пакет. `QUEUE_CONNECTION` в `.env`, `config:clear`.

---

<a id="queue-work"></a>
## queue:work

```bash
sail artisan queue:work redis --queue=high,default --tries=3
```

---

<a id="horizon"></a>
## Horizon

`sail artisan horizon` при Redis. На прод — supervisor.

---

<a id="rabbitmq"></a>
## RabbitMQ

[Контейнер](sail-databases#rabbitmq-sidecar), AMQP пакет, `queue:work rabbitmq`.

---

<a id="failed-jobs"></a>
## Failed jobs

`queue:failed`, `retry`, `flush`.

---

<a id="restart"></a>
## queue:restart

След промени в кода.

---

<a id="scheduler"></a>
## График

`schedule:run` или `schedule:work` локално; cron на сървър.

---

<a id="production"></a>
## Production

Supervisor, мащаб, управляван Redis/RabbitMQ.

---

[Sail](sail) · [БД](sail-databases) · [Env](sail-env-deploy) · [← Всички инструменти](../)
