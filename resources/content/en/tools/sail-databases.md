---
title: "Laravel Sail: databases, Redis, Postgres, MongoDB, RabbitMQ in Docker Compose | DevSense"
description: "Compose-first recipes for Laravel Sail: add Redis, switch to PostgreSQL, run MongoDB with the PHP extension, RabbitMQ sidecar, Mailpit and Meilisearch wiring, healthchecks and named volumes."
faq:
  - question: "Why does my Laravel app fail to connect to MySQL/PostgreSQL in Sail?"
    answer: "This usually happens because DB_HOST is set to 127.0.0.1 or localhost in .env. Inside the Docker network, laravel.test must connect using the Compose service name (e.g., mysql or pgsql) as the hostname."
  - question: "How do I add a new PHP extension (like MongoDB) to Sail?"
    answer: "You must publish the Sail Dockerfiles using 'php artisan sail:publish', edit the Dockerfile for your PHP version (e.g. pecl install mongodb), and rebuild the image with 'sail build --no-cache'."
  - question: "How do I clear the database without losing other project volumes?"
    answer: "Instead of running 'sail down -v' which destroys all volumes, identify the specific volume name using 'docker volume ls' and remove it individually using 'docker volume rm <volume_name>'."
  - question: "How can I prevent migration failures when starting Sail?"
    answer: "Configure Docker healthchecks for your database in docker-compose.yml and set the 'laravel.test' service to depend on it with 'condition: service_healthy' so migrations wait for database readiness."
---

# Sail: databases & Docker services

You run `sail up -d`, type `php artisan migrate`, and get a red `Connection refused` wall of error. Even though Docker Desktop shows your database container is running and healthy, the application inside refuses to talk to it. This connection breakdown highlights a fundamental rule of containerized development: in Docker Compose, `127.0.0.1` refers to the container itself, not the network.

Configuring external services (databases, caches, message brokers) for a Laravel project can quickly lead to environment mismatch issues across teams. Sail solves this by treating your services as independent, reproducible containers, but you must configure their network connectivity, build requirements, and startup ordering correctly.

In this guide, we show you how to swap and configure databases (Postgres, MongoDB), wire up caching and brokers (Redis, RabbitMQ), and handle healthchecks and volume resets safely.

**Navigation:** [All tools](../) · [Sail overview](sail#what-sail-is) · [Queues](sail-queues#connections) · [Env & deploy](sail-env-deploy#forward-ports) · [Troubleshooting](sail-troubleshooting#wsl-filesync)

## Table of contents

* [Network and hostname rules](#networking)
* [Redis service (full example)](#redis-service)
* [Replace MySQL with PostgreSQL](#postgres-swap)
* [MongoDB + PHP extension in Sail](#mongodb)
* [RabbitMQ as a container](#rabbitmq-sidecar)
* [Mailpit (SMTP dev)](#mailpit)
* [Meilisearch / Typesense (optional search)](#search-engines)
* [Healthchecks and startup order](#healthchecks)
* [Named volumes and data reset](#volumes)
* [Common Mistakes](#common-mistakes)
* [Self-Check Questions](#self-check)

---

<a id="networking"></a>
## Network and hostname rules

All application-side hosts in **`.env`** must be **Compose service names** (`mysql`, `pgsql`, `redis`, `mongo`), not `127.0.0.1`. This is because PHP runs **inside** the `laravel.test` container and communicates via an isolated Docker network.

> [!NOTE]
> **Host Machine vs. Container Networking**
> From your host machine (IDE, terminal, DBeaver), you connect to databases via `127.0.0.1` using the forwarded port (e.g., `FORWARD_DB_PORT`). But inside the containers, services must address each other using their Compose service names.

---

<a id="redis-service"></a>
## Redis service (full example)

To add Redis to your Sail environment, define the service and mount a named volume.

```yaml
# docker-compose.yml
services:
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

Register **`sail-redis`** under the top-level **`volumes:`** section and declare the dependency inside **`laravel.test`**:

```yaml
# docker-compose.yml
services:
    laravel.test:
        depends_on:
            redis:
                condition: service_healthy
```

```dotenv
# .env
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Run `sail exec redis redis-cli ping` to verify the connection.

---

<a id="postgres-swap"></a>
## Replace MySQL with PostgreSQL

If your production server uses PostgreSQL, running MySQL locally is an unnecessary risk.

1. Comment out or delete the `mysql` service and its associated volume in `docker-compose.yml`.
2. Add the `pgsql` service block using the standard Sail stub:

```yaml
# docker-compose.yml
services:
    pgsql:
        image: 'postgres:15-alpine'
        ports:
            - '${FORWARD_DB_PORT:-5432}:5432'
        environment:
            POSTGRES_DB: '${DB_DATABASE}'
            POSTGRES_USER: '${DB_USERNAME}'
            POSTGRES_PASSWORD: '${DB_PASSWORD}'
        volumes:
            - 'sail-pgsql:/var/lib/postgresql/data'
        networks:
            - sail
        healthcheck:
            test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME} -d ${DB_DATABASE}"]
            retries: 3
            timeout: 5s
```

3. Update the `depends_on` array of `laravel.test` to point to `pgsql`.
4. Update **`.env`**:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

Rebuild and start the containers using `sail build --no-cache && sail up -d`.

---

<a id="mongodb"></a>
## MongoDB + PHP extension in Sail

MongoDB is not supported out-of-the-box by Sail's default runtimes and requires installing the PHP MongoDB extension inside the application container.

1. Add the MongoDB service to your Compose file:

```yaml
# docker-compose.yml
services:
    mongo:
        image: 'mongo:7'
        ports:
            - '${FORWARD_MONGO_PORT:-27017}:27017'
        volumes:
            - 'sail-mongo:/data/db'
        networks:
            - sail
```

2. Register the volume `sail-mongo` under `volumes:`.
3. Publish the Sail Dockerfiles if you haven't already:
   ```bash
   php artisan sail:publish
   ```
4. Edit the runtime Dockerfile (e.g., `docker/8.3/Dockerfile`) to install the PECL extension:

```dockerfile
# docker/8.3/Dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

5. Rebuild your Sail images and start:
   ```bash
   sail build --no-cache
   sail up -d
   ```

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ as a container

For projects requiring advanced AMQP features instead of Redis or database-backed queues, run RabbitMQ as a sidecar container.

```yaml
# docker-compose.yml
services:
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

> [!NOTE]
> Make sure to declare `sail-rabbitmq` under your top-level `volumes:` list. Access the management dashboard locally at `http://localhost:15672` using your custom credentials (e.g., `sail` / `password`).

---

<a id="mailpit"></a>
## Mailpit (SMTP dev)

Sail includes Mailpit to trap outgoing emails. This prevents real messages from accidentally being sent to users during local testing.

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

All emails sent by the application can be read via the web interface at `http://localhost:8025`.

---

<a id="search-engines"></a>
## Meilisearch / Typesense (optional search)

To enable lightning-fast local search with Laravel Scout:

```bash
php artisan sail:install --with=meilisearch
```

Or copy the Meilisearch service stub into your Compose configuration. Remember to point your environment variables to the service name:

```dotenv
# .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
```

---

<a id="healthchecks"></a>
## Healthchecks and startup order

By default, Docker Compose starts containers in parallel. A standard `depends_on: [mysql]` only ensures that the MySQL container *starts*, not that the database engine is *fully ready* to receive connections.

Implementing `healthcheck` on the database service and setting `condition: service_healthy` on the application service ensures migrations run cleanly during initialization.

---

<a id="volumes"></a>
## Named volumes and data reset

Named volumes act as persistent virtual drives.
- Running `sail down` stops containers but leaves your database volumes untouched.
- Running `sail down -v` destroys **all** volumes, resetting your local environment completely.
- To reset only one database: locate the target volume with `docker volume ls` and delete it using `docker volume rm <volume_name>`.

---

## ⚠️ Common Mistakes

**1. Using `127.0.0.1` or `localhost` inside `.env` for container connections**
Setting `DB_HOST=127.0.0.1` causes Laravel to search for the database inside the PHP container itself, leading to a connection failure.
*Reality:* Use `DB_HOST=mysql` or `DB_HOST=pgsql`.

**2. Destructive volume resets with `sail down -v`**
Running `sail down -v` when you only want to stop your containers will wipe your databases, seeding records, and Redis keys.
*Reality:* Use `sail down` to safely stop containers. Only use `-v` if you intend to wipe the state completely.

**3. Modifying custom Dockerfiles without rebuilding the image**
Editing the published runtime Dockerfiles to install PHP extensions (like `mongodb` or `gd`) has no effect until you force a rebuild.
*Reality:* Always run `sail build --no-cache` after editing Dockerfiles.

---

## 🧠 Self-Check Questions

1. **Why does setting `DB_HOST=127.0.0.1` fail when running Laravel migrations inside Sail?**
2. **How does `depends_on` with `condition: service_healthy` differ from a plain `depends_on` array?**
3. **What happens to your local PostgreSQL data if you run `sail down -v`?**
4. **Why do you need to publish Sail's Dockerfiles (via `sail:publish`) before you can use the MongoDB extension?**

<details>
<summary><b>Reveal Answers</b></summary>

1. Inside Docker Compose, each container has its own loopback network interface. `127.0.0.1` refers to the `laravel.test` container itself, which does not run the database. You must use the database container's service name as the hostname.
2. A plain `depends_on` only checks if the container has started (in a `running` state). The `condition: service_healthy` modifier blocks the dependent container from starting until the database passes its internal readiness healthcheck (e.g. it is ready to accept socket connections).
3. All local PostgreSQL data will be permanently destroyed because the volume storing `/var/lib/postgresql/data` is deleted.
4. Sail uses pre-built generic Docker images. To install custom PECL extensions, you must access and modify the underlying Dockerfile configuration and build a local image.
</details>
