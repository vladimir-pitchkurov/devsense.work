---
title: "Laravel Sail: БД, Redis, Postgres, MongoDB, RabbitMQ в Docker Compose | DevSense"
description: "Compose рецепти за Sail: Redis, смяна към PostgreSQL, MongoDB и PHP разширение, RabbitMQ, Mailpit/Meilisearch, healthcheck и именовани томове."
---

# Sail: бази данни и Docker услуги

Този гайд е за **услуги до `laravel.test`**: SQL, Redis, по избор RabbitMQ, MongoDB и удобни за dev екстри. Допълва [пълния Sail гайд](sail). Команди за workers — в [Опашки](sail-queues); `.env` и сървъри — в [Среди и деплой](sail-env-deploy).

**Навигация:** [Всички инструменти](../) · [Sail](sail) · [Опашки](sail-queues) · [Env](sail-env-deploy) · [Диагностика](sail-troubleshooting)

## Съдържание

* [Мрежа и правила за имена на хостове](#networking)
* [Redis услуга (пълен пример)](#redis-service)
* [Замяна на MySQL с PostgreSQL](#postgres-swap)
* [MongoDB и PHP разширение в Sail](#mongodb)
* [RabbitMQ като контейнер](#rabbitmq-sidecar)
* [Mailpit (SMTP за dev)](#mailpit)
* [Meilisearch / Typesense (по избор търсене)](#search-engines)
* [Healthcheck и ред на стартиране](#healthchecks)
* [Именовани томове и нулиране на данни](#volumes)

---

<a id="networking"></a>
## Мрежа и правила за имена на хостове

Всички хостове от страна на приложението в **`.env`** трябва да са **имена на Compose услуги** (`pgsql`, `redis`, `mongo`), не `127.0.0.1`, защото PHP работи **вътре** в `laravel.test`. От **хост машината** достъпът до БД е **`127.0.0.1:${FORWARD_DB_PORT}`**, когато портовете са пробросени.

---

<a id="redis-service"></a>
## Redis услуга (пълен пример)

Добавете в **`docker-compose.yml`** (нагласете имената към вашия Sail файл):

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

Регистрирайте **`sail-redis`** под **`volumes:`**. В **`laravel.test`**:

```yaml
depends_on:
    redis:
        condition: service_healthy
```

(Ако Compose няма `condition`, ползвайте `depends_on: [redis]`.)

**.env:**

```dotenv
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Проверка: `sail exec redis redis-cli ping`.

---

<a id="postgres-swap"></a>
## Замяна на MySQL с PostgreSQL

1. Премахнете (или коментирайте) услугата **`mysql`** и нейния том.
2. Добавете **`pgsql`** от свеж Sail stub (`php artisan sail:install --with=pgsql`) или от [laravel/sail](https://github.com/laravel/sail) `stubs/pgsql.stub`.
3. Насочете **`depends_on`** на **`laravel.test`** към **`pgsql`**.
4. **`.env`:**

```dotenv
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

5. Прекомпилирайте при смяна на PHP образ: `sail build --no-cache && sail up -d`.
6. Чиста БД: `sail artisan migrate:fresh`.

---

<a id="mongodb"></a>
## MongoDB и PHP разширение в Sail

**1. Услуга в Compose:**

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

2. Добавете **`sail-mongo`** към **`volumes:`**.

3. Обикновено **`mongodb/laravel-mongodb`**. Разширението **mongodb** трябва в **`laravel.test`**. След `sail:publish` редактирайте **Dockerfile** (напр. `docker/8.3/Dockerfile`):

```dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

4. `sail build --no-cache`.

5. Хост **`mongo`**, порт **27017** от контейнера на приложението.

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ като контейнер

При пакет с **AMQP** драйвер:

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

Том **`sail-rabbitmq`**. UI на **15672**.

---

<a id="mailpit"></a>
## Mailpit (SMTP за dev)

Насочете Laravel към **`mailpit`** и SMTP порта от compose; писмата в браузър UI.

---

<a id="search-engines"></a>
## Meilisearch / Typesense

`sail:install --with=meilisearch` или блок от upstream. Scout променливи към **име на услуга**.

---

<a id="healthchecks"></a>
## Healthcheck и ред на стартиране

**`depends_on`** само не чака готовност на БД. Предпочитайте **`healthcheck`** и `service_healthy`, където Compose го поддържа.

---

<a id="volumes"></a>
## Именовани томове и нулиране на данни

- Томовете остават след `sail down`.
- **`sail down -v`** ги изтрива — данните изчезват.
- За един том: `docker volume rm` по име от `docker volume ls`.

---

## Вижте също

* [Sail — пълен гайд](sail)  
* [Опашки](sail-queues)  
* [Среди и деплой](sail-env-deploy)  
* [Диагностика](sail-troubleshooting)  

[← Всички инструменти](../)
