---
title: "Laravel Sail: бази даних, Redis, Postgres, MongoDB, RabbitMQ у Docker Compose | DevSense"
description: "Compose-рецепти для Laravel Sail: додавання Redis, перехід на PostgreSQL, запуск MongoDB із розширенням PHP, RabbitMQ, Mailpit/Meilisearch, перевірка працездатності (healthcheck) та іменовані томи."
faq:
  - question: "Чому мій застосунок Laravel не може підключитися до MySQL/PostgreSQL у Sail?"
    answer: "Зазвичай це трапляється через те, що у файлі .env параметр DB_HOST налаштовано як 127.0.0.1 або localhost. Усередині мережі Docker контейнер laravel.test повинен підключатися за ім'ям служби Compose (наприклад, mysql або pgsql)."
  - question: "Як додати нове розширення PHP (наприклад, MongoDB) у Sail?"
    answer: "Вам потрібно опублікувати Docker-файли Sail за допомогою команди 'php artisan sail:publish', відредагувати Dockerfile для вашої версії PHP (додавши pecl install mongodb) та перезбирати образ із прапорцем 'sail build --no-cache'."
  - question: "Як очистити базу даних, не втрачаючи томи інших проєктів?"
    answer: "Замість виконання команди 'sail down -v', яка видаляє всі томи, знайдіть ім'я конкретного тома за допомогою 'docker volume ls' та видаліть його індивідуально через 'docker volume rm <ім'я_тома>'."
  - question: "Як уникнути збоїв міграцій під час запуску Sail?"
    answer: "Налаштуйте перевірку працездатності (healthcheck) у файлі docker-compose.yml для служби бази даних та вкажіть для служби 'laravel.test' залежність 'condition: service_healthy', щоб міграції очікували на готовність БД."
---

# Sail: бази даних і Docker-сервіси

Ви запускаєте `sail up -d`, вводите `php artisan migrate` і отримуєте червону стіну помилки `Connection refused`. Незважаючи на те, що в Docker Desktop контейнер бази даних світиться зеленим як запущений та справний, застосунок відмовляється з ним працювати. Цей збій вказує на фундаментальне правило контейнеризації: у мережі Docker Compose хост `127.0.0.1` вказує на сам контейнер застосунку, а не на мережу бази даних.

Налаштування зовнішніх сервісів (баз даних, кешів, брокерів повідомлень) для проєкту Laravel може швидко призвести до розбіжностей в оточенні у членів команди. Sail вирішує цю проблему, представляючи ваші сервіси як незалежні, відтворювані контейнери, але вам потрібно правильно налаштувати їхнє мережеве взаємодію, вимоги до збірки та порядок запуску.

У цьому посібнику ми покажемо, як замінювати та налаштовувати бази даних (Postgres, MongoDB), підключати кешування та брокери (Redis, RabbitMQ), а також безпечно обробляти перевірки працездатності (healthchecks) та скидання томів (volumes).

**Навігація:** [Усі інструменти](../) · [Sail огляд](sail#what-sail-is) · [Черги](sail-queues#connections) · [Env та деплой](sail-env-deploy#forward-ports) · [Діагностика](sail-troubleshooting#wsl-filesync)

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
* [Часті помилки](#common-mistakes)
* [Тест для самоперевірки](#self-check)

---

<a id="networking"></a>
## Мережа та правила імен хостів

Усі хости з боку застосунку в **`.env`** мають бути **іменами служб Compose** (`mysql`, `pgsql`, `redis`, `mongo`), а не `127.0.0.1`. Це пов'язано з тим, що PHP працює **всередині** контейнера `laravel.test` і спілкується через ізольовану мережу Docker.

> [!NOTE]
> **Хост-машина проти мережі контейнерів**
> З хост-машини (з IDE, термінала, DBeaver) ви підключаєтеся до баз даних через `127.0.0.1`, використовуючи проброшений порт (наприклад, `FORWARD_DB_PORT`). Але всередині контейнерів служби мають звертатися одна до одної за іменами служб Compose.

---

<a id="redis-service"></a>
## Сервіс Redis (повний приклад)

Щоб додати Redis в оточення Sail, визначте службу та змонтуйте іменований том.

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

Зареєструйте **`sail-redis`** у секції **`volumes:`** на верхньому рівні та додайте залежність у **`laravel.test`**:

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

Запустіть `sail exec redis redis-cli ping` для перевірки підключення.

---

<a id="postgres-swap"></a>
## Замінити MySQL на PostgreSQL

Якщо на продакшені використовується PostgreSQL, запуск MySQL локально — це зайвий ризик.

1. Закоментуйте або видаліть службу `mysql` та пов'язаний з нею том у `docker-compose.yml`.
2. Додайте блок служби `pgsql`, використовуючи стандартний stub-шаблон Sail:

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

3. Оновіть масив `depends_on` служби `laravel.test`, вказавши `pgsql`.
4. Оновіть **`.env`**:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

Перезберіть та запустіть контейнери: `sail build --no-cache && sail up -d`.

---

<a id="mongodb"></a>
## MongoDB і розширення PHP у Sail

MongoDB не підтримується «із коробки» стандартними збірками Sail і вимагає встановлення розширення PHP mongodb всередині контейнера застосунку.

1. Додайте службу MongoDB у Compose файл:

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

2. Зареєструйте том `sail-mongo` у секції `volumes:`.
3. Опублікуйте Docker-файли Sail, якщо ви ще цього не зробили:
   ```bash
   php artisan sail:publish
   ```
4. Відредагуйте Dockerfile версії, що використовується (наприклад, `docker/8.3/Dockerfile`), для встановлення PECL-розширення:

```dockerfile
# docker/8.3/Dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

5. Перезберіть образи Sail та запустіть їх:
   ```bash
   sail build --no-cache
   sail up -d
   ```

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ як контейнер

Для проєктів, які вимагають розширених можливостей AMQP замість Redis або черг на базі БД, запустіть RabbitMQ як допоміжний контейнер.

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
> Не забудьте оголосити том `sail-rabbitmq` у верхньому рівні `volumes:`. Панель керування (management UI) буде доступна за адресою `http://localhost:15672` (в прикладі облікові дані: `sail` / `password`).

---

<a id="mailpit"></a>
## Mailpit (SMTP для dev)

Sail включає Mailpit для перехоплення вихідної пошти. Це запобігає випадковому надсиланню тестових повідомлень реальним користувачам під час локальної розробки.

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

Усі надіслані листи можна переглянути у веб-інтерфейсі за адресою `http://localhost:8025`.

---

<a id="search-engines"></a>
## Meilisearch / Typesense (опційний пошук)

Для швидкої локальної роботи з повнотекстовим пошуком через Laravel Scout:

```bash
php artisan sail:install --with=meilisearch
```

Або скопіюйте конфігурацію Meilisearch у файл Compose. Вкажіть ім'я служби як хост в `.env`:

```dotenv
# .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
```

---

<a id="healthchecks"></a>
## Healthcheck і порядок запуску

За замовчуванням Docker Compose запускає контейнери паралельно. Проста директива `depends_on: [mysql]` гарантує лише те, що контейнер із MySQL *запуститься*, але не те, що база даних *готова* приймати з'єднання.

Налаштування `healthcheck` для бази даних та використання `condition: service_healthy` для залежних служб виключає збої міграцій під час початкового запуску.

---

<a id="volumes"></a>
## Іменовані томи й скидання даних

Іменовані томи поводяться як постійні віртуальні диски.
- Виконання команди `sail down` зупиняє контейнери, але зберігає ваші томи з даними.
- Команда `sail down -v` знищує **всі** тома, повністю скидаючи стан локального середовища.
- Щоб скинути тільки одну БД: знайдіть ім'я тома за допомогою `docker volume ls` та видаліть його через `docker volume rm <ім'я_тома>`.

---

## ⚠️ Часті помилки

**1. Використання `127.0.0.1` або `localhost` в `.env` для підключення до баз даних**
Налаштування `DB_HOST=127.0.0.1` змушує Laravel шукати базу даних всередині самого PHP-контейнера, що призводить до помилки підключення.
*Рішення:* Використовуйте `DB_HOST=mysql` або `DB_HOST=pgsql`.

**2. Руйнівне скидання томів за допомогою `sail down -v`**
Виконання `sail down -v` для простої зупинки контейнерів зітре бази даних, тестові записи та ключі Redis.
*Рішення:* Використовуйте `sail down` для безпечної зупинки. Додавайте прапорець `-v` тільки якщо усвідомлено хочете очистити всі дані.

**3. Зміна Dockerfile без перезбирання образу**
Зміна опублікованих файлів у директорії `docker/` (наприклад, встановлення розширень `mongodb` або `gd`) не матиме ефекту, поки ви не примусите образ перезбиратися.
*Рішення:* Завжди запускайте `sail build --no-cache` після редагування Dockerfile.

---

## 🧠 Тест для самоперевірки

1. **Чому вказівка `DB_HOST=127.0.0.1` призводить до збою виконання міграцій всередині Sail?**
2. **Чим `depends_on` із модифікатором `condition: service_healthy` відрізняється від звичайного списку `depends_on`?**
3. **Що станеться з вашими локальними даними PostgreSQL, якщо виконати `sail down -v`?**
4. **Чому для роботи розширення MongoDB у контейнері застосунку потрібно спочатку виконати команду `sail:publish`?**

<details>
<summary><b>Показати відповіді</b></summary>

1. У Docker Compose кожен контейнер має свій loopback-інтерфейс. `127.0.0.1` вказує на сам контейнер `laravel.test`, на якому база даних не запущена. Потрібно вказувати ім'я контейнера БД у мережі Compose.
2. Простий `depends_on` перевіряє тільки факт запуску контейнера (статус `running`). Модифікатор `condition: service_healthy` блокує запуск залежного контейнера доти, доки база даних не пройде внутрішній тест готовності (наприклад, не почне приймати сокет-з'єднання).
3. Усі локальні дані PostgreSQL будуть безповоротно видалені разом із томом, у якому зберігалася директорія `/var/lib/postgresql/data`.
4. Команда Sail використовує налаштовані універсальні образи Docker. Щоб встановити розширення через PECL, потрібно отримати доступ до Dockerfile, змінити його та запустити локальну збірку.
</details>
