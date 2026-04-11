---
title: "Laravel Sail: очереди, Horizon, Redis, RabbitMQ и failed jobs | DevSense"
description: "Запуск Laravel-очередей в Sail: sync/redis/database, queue:work, Horizon, RabbitMQ через пакеты, failed_jobs, queue:restart и отличия от production."
---

# Sail: очереди и воркеры

Работа с **очередями** внутри Sail. Контейнеры — в [Базы данных и сервисы](sail-databases#networking), переменные окружения — в [Окружения и деплой](sail-env-deploy#env-files).

**Навигация:** [Все инструменты](../) · [Sail](sail#what-sail-is) · [БД](sail-databases#networking) · [Env](sail-env-deploy#forward-ports) · [Диагностика](sail-troubleshooting#wsl-filesync)

## Содержание

* [Драйверы](#connections)
* [`queue:work`](#queue-work)
* [Horizon](#horizon)
* [RabbitMQ](#rabbitmq)
* [Failed jobs](#failed-jobs)
* [`queue:restart`](#restart)
* [Планировщик](#scheduler)
* [Продакшен](#production)

---

<a id="connections"></a>
## Драйверы

| Драйвер | Заметки |
|---------|---------|
| `sync` | Отладка без воркера |
| `database` | Таблица `jobs`, нужен воркер |
| `redis` | Типичный выбор, Horizon |
| `rabbitmq` | Через пакет + контейнер |

`QUEUE_CONNECTION` в `.env`, затем `sail artisan config:clear`.

---

<a id="queue-work"></a>
## `queue:work`

```bash
sail artisan queue:work redis --queue=high,default --tries=3
```

`--once` для одной задачи. Несколько очередей — несколько процессов или флаги `--queue`.

---

<a id="horizon"></a>
## Horizon

`QUEUE_CONNECTION=redis`, затем `sail artisan horizon`. На проде — supervisor. Локально останов — Ctrl+C.

---

<a id="rabbitmq"></a>
## RabbitMQ

Поставьте [сервис в compose](sail-databases#rabbitmq-sidecar), пакет AMQP, `sail artisan queue:work rabbitmq`. Семантика отличается от Redis — тестируйте задержки и повторы.

---

<a id="failed-jobs"></a>
## Failed jobs

`sail artisan queue:failed`, `queue:retry {id}`, `queue:flush`. Настройте `$tries`, `$timeout`, `backoff` на джобах.

---

<a id="restart"></a>
## `queue:restart`

После смены кода: `sail artisan queue:restart`. Для частых итераций — `--max-jobs=1` или `--once`.

---

<a id="scheduler"></a>
## Планировщик

Cron в Sail нет: `sail artisan schedule:run` вручную или `schedule:work` в отдельном терминале. На сервере — cron каждую минуту.

---

<a id="production"></a>
## Продакшен

На сервере воркеры под **supervisor**/K8s, масштабирование, кластер Redis/RabbitMQ. Sail — для разработки и интеграционных проверок.

---

[Sail](sail#what-sail-is) · [БД](sail-databases#networking) · [Env](sail-env-deploy#env-files) · [← Все инструменты](../)
