---
title: "Laravel Sail: опашки, Horizon, Redis, RabbitMQ и failed jobs | DevSense"
description: "Laravel опашки в Sail: sync/redis/database, queue:work, Horizon локално, RabbitMQ чрез пакети, failed_jobs, queue:restart и разлики с production."
---

# Sail: опашки и workers

Как да пускате **Laravel опашки**, когато приложението е в **Sail**. За Redis/RabbitMQ контейнери вижте [Бази данни и услуги](sail-databases); за `.env` — [Среди и деплой](sail-env-deploy).

**Навигация:** [Всички инструменти](../) · [Sail](sail) · [БД](sail-databases) · [Env](sail-env-deploy) · [Диагностика](sail-troubleshooting)

## Съдържание

* [Връзки накратко](#connections)
* [`queue:work` в Sail](#queue-work)
* [Horizon (Redis)](#horizon)
* [RabbitMQ и AMQP пакети](#rabbitmq)
* [Неуспешни jobs и повтори](#failed-jobs)
* [Промени в кода и `queue:restart`](#restart)
* [Планировчик (`schedule:run`)](#scheduler)
* [Контраст с production](#production)

---

<a id="connections"></a>
## Връзки накратко

| Драйвер | В Sail |
|--------|--------|
| **`sync`** | Debug без worker; без паралелизъм. |
| **`database`** | Таблица `jobs` + един или повече workers. |
| **`redis`** | Типично с Horizon; нужен Redis услуга. |
| **`sqs`** | Облачна опашка; по-рядко само локално. |
| **`rabbitmq`** (пакет) | AMQP; контейнер + пакет. |

**`QUEUE_CONNECTION`** в `.env`. След промяна: `sail artisan config:clear`.

---

<a id="queue-work"></a>
## `queue:work` в Sail

```bash
sail artisan queue:work
```

```bash
sail artisan queue:work redis --queue=high,default --tries=3 --timeout=90
```

```bash
sail artisan queue:work --once
```

---

<a id="horizon"></a>
## Horizon (Redis)

**`QUEUE_CONNECTION=redis`** и инсталиран Horizon.

```bash
sail artisan horizon
```

В production Supervisor/systemd; в Sail — Ctrl+C.

---

<a id="rabbitmq"></a>
## RabbitMQ и AMQP пакети

1. Услуга в Compose ([пример](sail-databases#rabbitmq-sidecar)).
2. AMQP пакет; publish config.
3. **`QUEUE_CONNECTION`** по документация.
4. `sail artisan queue:work rabbitmq`

---

<a id="failed-jobs"></a>
## Неуспешни jobs и повтори

- Миграция **`failed_jobs`** при `database` failed driver.
- `sail artisan queue:failed`
- `sail artisan queue:retry {id}`
- `sail artisan queue:flush`

---

<a id="restart"></a>
## Промени в кода и `queue:restart`

```bash
sail artisan queue:restart
```

Локално: **`--once`** или **`--max-jobs=1`**.

---

<a id="scheduler"></a>
## Планировчик (`schedule:run`)

- Ръчно: `sail artisan schedule:run`
- **`sail run --rm laravel.test php artisan schedule:work`**
- Production: cron всяка минута.

---

<a id="production"></a>
## Контраст с production

| Тема | Sail | Production |
|------|------|------------|
| Worker lifecycle | Терминал / Horizon foreground | Supervisor, K8s, managed |
| Scaling | Един контейнер | Много workers |
| Redis | Един контейнер | Кластер / managed |

---

## Вижте също

* [Sail — пълен гайд](sail)  
* [Бази данни и услуги](sail-databases)  
* [Среди и деплой](sail-env-deploy)  

[← Всички инструменти](../)
