---
title: "Laravel Sail: черги, Horizon, Redis, RabbitMQ і failed jobs | DevSense"
description: "Запуск черг Laravel у Sail: sync/redis/database, queue:work, Horizon локально, RabbitMQ через пакети, failed_jobs, queue:restart і відмінності від production."
---

# Sail: черги та воркери

Як крутити **черги Laravel**, коли застосунок у **Sail**. Разом із [БД і сервісами](sail-databases) для Redis/RabbitMQ і [Оточенням і деплоєм](sail-env-deploy) для `.env` на різних машинах.

**Навігація:** [Усі інструменти](../) · [Sail](sail) · [БД](sail-databases) · [Env](sail-env-deploy) · [Діагностика](sail-troubleshooting)

## Зміст

* [Підключення одним поглядом](#connections)
* [Запуск `queue:work` у Sail](#queue-work)
* [Horizon (Redis)](#horizon)
* [RabbitMQ і пакети AMQP](#rabbitmq)
* [Невдалі джоби й повтори](#failed-jobs)
* [Зміна коду й `queue:restart`](#restart)
* [Планувальник (`schedule:run`)](#scheduler)
* [Контраст із production](#production)

---

<a id="connections"></a>
## Підключення одним поглядом

| Драйвер | У Sail |
|--------|--------|
| **`sync`** | Дебаг логіки джоба без воркера; без паралелізму. |
| **`database`** | Простий async; потрібна таблиця `jobs` і **один або більше воркерів**. |
| **`redis`** | Типовий вибір; з Horizon; потрібен **сервіс Redis**. |
| **`sqs`** | Хмарна черга; ключі AWS; рідко лише локально. |
| **`rabbitmq`** (через пакет) | AMQP; потрібні **контейнер + пакет**. |

**`QUEUE_CONNECTION`** у `.env`. Після змін: `sail artisan config:clear`.

---

<a id="queue-work"></a>
## Запуск `queue:work` у Sail

```bash
sail artisan queue:work
```

Корисні прапорці:

```bash
sail artisan queue:work redis --queue=high,default --tries=3 --timeout=90
```

Окремі термінали — для різних черг. Разовий запуск:

```bash
sail artisan queue:work --once
```

---

<a id="horizon"></a>
## Horizon (Redis)

Потрібні **`QUEUE_CONNECTION=redis`** і встановлений Horizon.

```bash
sail artisan horizon
```

Дашборд Horizon — за налаштованим шляхом (часто `/horizon` з auth). У production **Supervisor** або systemd тримає Horizon; у Sail зупиняється Ctrl+C.

---

<a id="rabbitmq"></a>
## RabbitMQ і пакети AMQP

У Laravel **немає** офіційного драйвера RabbitMQ. Кроки:

1. Додати сервіс RabbitMQ у Compose ([приклад](sail-databases#rabbitmq-sidecar)).
2. Встановити підтримуваний **AMQP**-пакет; опублікувати конфіг.
3. **`QUEUE_CONNECTION`** (або ім’я з’єднання) за документацією пакета.
4. Запуск:

```bash
sail artisan queue:work rabbitmq
```

Перевірте **відкладені** джоби, **failed** маршрутизацію й **TTL** — семантика AMQP відрізняється від Redis-списків.

---

<a id="failed-jobs"></a>
## Невдалі джоби й повтори

- Міграція **`failed_jobs`**, якщо драйвер failed — `database`.
- Перегляд: `sail artisan queue:failed`
- Повтор одного: `sail artisan queue:retry {id}`
- Очистка: `sail artisan queue:flush`

Налаштуйте **`$tries`**, **`$timeout`**, **`backoff`**, щоб уникнути «отруєних» повідомлень.

---

<a id="restart"></a>
## Зміна коду й `queue:restart`

Довгоживучі воркери **кешують** bootstrap застосунку. Після деплою коду (або зміни гілки):

```bash
sail artisan queue:restart
```

Horizon теж реагує. Для швидкої ітерації локально — **`--max-jobs=1`** або **`--once`**.

---

<a id="scheduler"></a>
## Планувальник (`schedule:run`)

У Sail **немає** cron-демона за замовчуванням. Варіанти:

- Вручну: `sail artisan schedule:run`
- **`sail run --rm laravel.test php artisan schedule:work`** (Laravel 8+) — довгий процес планувальника в dev.
- У production — **один** cron на хвилину викликає `schedule:run`.

---

<a id="production"></a>
## Контраст із production

| Тема | Sail (локально) | Production |
|------|-----------------|------------|
| Життєвий цикл воркера | Термінал вручну / Horizon на передньому плані | **Supervisor**, Kubernetes Job, керований worker |
| Масштаб | Один контейнер | Кілька воркерів, autoscale |
| Redis | Один контейнер | Кластер / керований кеш |
| RabbitMQ | Dev-контейнер | Кластер, політики, моніторинг |

Sail — **достатньо**, щоб писати джоби й інтеграційні тести, не модель навантаження чи HA.

---

## Див. також

* [Sail — повний гайд](sail)  
* [БД і сервіси](sail-databases)  
* [Оточення та деплой](sail-env-deploy)  

[← Усі інструменти](../)
