---
title: "Laravel Sail: БД, Redis, Postgres, MongoDB, RabbitMQ у Docker Compose | DevSense"
description: "Compose-рецепти для Sail: Redis, перехід на PostgreSQL, MongoDB і розширення PHP, RabbitMQ, Mailpit/Meilisearch, healthcheck і іменовані томи."
---

# Sail: бази даних і Docker-сервіси

Цей гайд про **сервіси поруч із `laravel.test`**: SQL, Redis, опційний RabbitMQ, MongoDB і зручні для dev додатки. Доповнює [повний гайд Sail](sail). Команди воркерів — у [Черги та воркери](sail-queues); розклад `.env` і серверів — у [Оточення та деплой](sail-env-deploy).

**Навігація:** [Усі інструменти](../) · [Sail](sail) · [Черги](sail-queues) · [Env](sail-env-deploy) · [Діагностика](sail-troubleshooting)

## Зміст

* [Мережа та правила імен хостів](#networking)
* [Сервіс Redis (повний приклад)](#redis-service)
* [Замінити MySQL на PostgreSQL](#postgres-swap)
* [MongoDB і розширення PHP у Sail](#mongodb)
* [RabbitMQ як контейнер](#rabbitmq-sidecar)
* [Mailpit (SMTP для dev)](#mailpit)
* [Meilisearch / Typesense (опційний пошук)](#search-engines)
* [Healthcheck і порядок запуску](#healthchecks)
* [Іменовані томи й скидання даних](#volumes)

---

<a id="networking"></a>
## Мережа та правила імен хостів

Усі хости з боку застосунку в **`.env`** мають бути **іменами сервісів Compose** (`pgsql`, `redis`, `mongo`), а не `127.0.0.1`, бо PHP працює **всередині** `laravel.test`. З **хост-машини** до БД звертаються через **`127.0.0.1:${FORWARD_DB_PORT}`**, коли порти проброшені.

---

<a id="redis-service"></a>
## Сервіс Redis (повний приклад)

Додайте в **`docker-compose.yml`** (підлаштуйте імена під ваш опублікований файл Sail):

```yaml
redis:
    image: 'redis:alpine'
    ports:
        - '${FORWARD_REDIS_PORT:-6379}:6379'
    volumes:
        - 'sail-redis:/data'
    networks:
        - sail
    healthcheck:
        test: ["CMD", "redis-cli", "ping"]
        retries: 3
        timeout: 5s
```

Зареєструйте **`sail-redis`** у верхньому рівні **`volumes:`**. У **`laravel.test`** додайте:

```yaml
depends_on:
    redis:
        condition: service_healthy
```

(Якщо ваша версія Compose не підтримує `condition`, використовуйте простий `depends_on: [redis]`.)

**.env:**

```dotenv
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Перевірка: `sail exec redis redis-cli ping`.

---

<a id="postgres-swap"></a>
## Замінити MySQL на PostgreSQL

1. Приберіть (або закоментуйте) сервіс **`mysql`** і його том.
2. Додайте **`pgsql`** зі свіжого заготовки Sail (`php artisan sail:install --with=pgsql` у тимчасовому проєкті) або з [laravel/sail](https://github.com/laravel/sail) `stubs/pgsql.stub` для вашої версії.
3. Направте **`depends_on`** у **`laravel.test`** на **`pgsql`**.
4. **`.env`:**

```dotenv
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

5. Перезберіть, якщо змінився образ PHP: `sail build --no-cache && sail up -d`.
6. Чиста БД: `sail artisan migrate:fresh` (знищує локальні дані, якщо не зробити dump/restore).

---

<a id="mongodb"></a>
## MongoDB і розширення PHP у Sail

**1. Сервіс у Compose** (мінімально):

```yaml
mongo:
    image: 'mongo:7'
    ports:
        - '${FORWARD_MONGO_PORT:-27017}:27017'
    volumes:
        - 'sail-mongo:/data/db'
    networks:
        - sail
```

2. Додайте **`sail-mongo`** до **`volumes:`**.

3. У **Laravel** зазвичай **`mongodb/laravel-mongodb`**. Розширення PHP **mongodb** має бути в **`laravel.test`**. Після `sail:publish` відредагуйте **runtime Dockerfile** (наприклад `docker/8.3/Dockerfile`):

```dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

4. Перезбірка: `sail build --no-cache`.

5. У пакеті вкажіть хост **`mongo`** і порт **27017** зсередини контейнера застосунку.

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ як контейнер

Потрібно, якщо використовуєте пакет з драйвером **AMQP** (не ядро Laravel):

```yaml
rabbitmq:
    image: 'rabbitmq:3-management-alpine'
    hostname: rabbitmq
    ports:
        - '${FORWARD_RABBITMQ_PORT:-5672}:5672'
        - '${FORWARD_RABBITMQ_MANAGEMENT:-15672}:15672'
    volumes:
        - 'sail-rabbitmq:/var/lib/rabbitmq'
    networks:
        - sail
    environment:
        RABBITMQ_DEFAULT_USER: '${RABBITMQ_USER:-sail}'
        RABBITMQ_DEFAULT_PASS: '${RABBITMQ_PASSWORD:-password}'
```

Оголосіть том **`sail-rabbitmq`**. У **`.env`** задайте ключі, які очікує ваш пакет черг (`RABBITMQ_HOST=rabbitmq` тощо). Management UI: `http://localhost:15672` (guest/guest **вимкнено**, якщо задано користувача вище — у прикладі **sail/password**).

---

<a id="mailpit"></a>
## Mailpit (SMTP для dev)

У Sail часто є **Mailpit**. Налаштуйте пошту Laravel на хост **`mailpit`** і **SMTP**-порт з `docker-compose.yml` (зазвичай `1025` у мережі, на хості — проброшений). Листи дивіться в **HTTP UI** на проброшеному веб-порту — реальна пошта машину не залишає.

---

<a id="search-engines"></a>
## Meilisearch / Typesense (опційний пошук)

Приклад прапорця при встановленні:

```bash
php artisan sail:install --with=meilisearch
```

Або вставте блок **Meilisearch** / **Typesense** з upstream-заготовок Sail. **`SCOUT_DRIVER`** / **`MEILISEARCH_HOST`** (або еквівалент) — на **ім’я сервісу** і внутрішній порт.

---

<a id="healthchecks"></a>
## Healthcheck і порядок запуску

Сам **`depends_on`** не чекає готовності БД. Краще **`healthcheck`** на `mysql`/`pgsql`/`redis` і `condition: service_healthy`, де Compose це підтримує — тоді `php artisan migrate` у entrypoint-скриптах рідше падає на старті.

---

<a id="volumes"></a>
## Іменовані томи й скидання даних

- **Іменовані томи** зберігаються після `sail down`.
- **`sail down -v`** видаляє ці томи — **усі локальні дані БД зникнуть**.
- Для чистого стану без удару по інших проєктах: **`docker volume rm`** з конкретним ім’ям з `docker volume ls`.

---

## Див. також

* [Sail — повний гайд](sail)  
* [Черги та воркери](sail-queues) — `queue:work`, Horizon, драйвери RabbitMQ  
* [Оточення та деплой](sail-env-deploy) — розділення `.env` і контраст із production  
* [Діагностика](sail-troubleshooting)  

[← Усі інструменти](../)
