---
title: "Laravel Sail: бази данни, Redis, Postgres, MongoDB, RabbitMQ в Docker Compose | DevSense"
description: "Compose-рецепти за Laravel Sail: добавяне на Redis, преминаване към PostgreSQL, стартиране на MongoDB с PHP разширение, RabbitMQ, Mailpit/Meilisearch, проверка на състоянието (healthcheck) и именовани томове."
faq:
  - question: "Защо моето Laravel приложение не може да се свърже с MySQL/PostgreSQL в Sail?"
    answer: "Това обикновено се случва, защото DB_HOST е настроен на 127.0.0.1 или localhost в .env. Вътре в Docker мрежата контейнерът laravel.test трябва да се свърже с името на Compose услугата (напр. mysql или pgsql)."
  - question: "Как да добавя ново PHP разширение (като MongoDB) в Sail?"
    answer: "Трябва да публикувате Dockerfiles на Sail с помощта на 'php artisan sail:publish', да редактирате Dockerfile за вашата PHP версия (като добавите pecl install mongodb) и да прекомпилирате с 'sail build --no-cache'."
  - question: "Как да изчистя базата данни, без да губя томове от други проекти?"
    answer: "Вместо да изпълнявате 'sail down -v', което изтрива всички томове, намерете името на конкретния том с 'docker volume ls' и го премахнете индивидуално с 'docker volume rm <име_на_том>'."
  - question: "Как да избегна грешки при миграции по време на стартиране на Sail?"
    answer: "Конфигурирайте Docker healthcheck за вашата база данни в docker-compose.yml и укажете зависимостта на 'laravel.test' с 'condition: service_healthy', за да чакат миграциите готовността на базата данни."
---

# Sail: бази данни и Docker услуги

Вие стартирате `sail up -d`, въвеждате `php artisan migrate` и получавате червена стена с грешка `Connection refused`. Въпреки че Docker Desktop показва, че контейнерът на вашата база данни работи и е в изправност, приложението вътре отказва да комуникира с него. Този срив подчертава фундаментално правило при контейнеризираната разработка: в Docker Compose мрежата, `127.0.0.1` се отнася за самия контейнер на приложението, а не за мрежата на базата данни.

Настройването на външни услуги (бази данни, кешове, брокери на съобщения) за проект на Laravel може бързо да доведе до несъответствия в средите на различните разработчици. Sail решава това, като третира вашите услуги като независими, възпроизводими контейнери, но вие трябва правилно да конфигурирате тяхната мрежова свързаност, изисквания за изграждане и ред на стартиране.

В това ръководство ще ви покажем как да подменяте и конфигурирате бази данни (Postgres, MongoDB), да свързвате кеширане и брокери (Redis, RabbitMQ), както и да управлявате безопасно проверките на състоянието (healthchecks) и нулирането на томовете (volumes).

**Навигация:** [Всички инструменти](../) · [Sail обзор](sail#what-sail-is) · [Опашки](sail-queues#connections) · [Env и деплой](sail-env-deploy#forward-ports) · [Диагностика](sail-troubleshooting#wsl-filesync)

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
* [Чести грешки](#common-mistakes)
* [Въпроси за самопроверка](#self-check)

---

<a id="networking"></a>
## Мрежа и правила за имена на хостове

Всички хостове от страната на приложението в **`.env`** трябва да бъдат **имена на Compose услуги** (`mysql`, `pgsql`, `redis`, `mongo`), а не `127.0.0.1`. Това е така, защото PHP работи **вътре** в контейнера `laravel.test` и комуникира чрез изолирана Docker мрежа.

> [!NOTE]
> **Хост машина срещу мрежа на контейнери**
> От вашата хост машина (IDE, терминал, DBeaver) се свързвате с базите данни чрез `127.0.0.1`, като използвате пренасочения порт (например `FORWARD_DB_PORT`). Но вътре в контейнерите услугите трябва да се достъпват взаимно чрез своите имена на Compose услуги.

---

<a id="redis-service"></a>
## Redis услуга (пълен пример)

За да добавите Redis към вашата Sail среда, дефинирайте услугата и монтирайте именован том.

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

Регистрирайте **`sail-redis`** в секцията **`volumes:`** на най-високо ниво и декларирайте зависимостта в **`laravel.test`**:

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

Стартирайте `sail exec redis redis-cli ping`, за да тествате връзката.

---

<a id="postgres-swap"></a>
## Замяна на MySQL с PostgreSQL

Ако на продукционния сървър се използва PostgreSQL, стартирането на MySQL локално е излишен риск.

1. Коментирайте или изтрийте услугата `mysql` и свързания с нея том в `docker-compose.yml`.
2. Добавете блока за услугата `pgsql`, като използвате стандартния stub-шаблон на Sail:

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

3. Обновете масива `depends_on` на услугата `laravel.test`, като посочите `pgsql`.
4. Обновете **`.env`**:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

Прекомпилирайте и стартирайте контейнерите: `sail build --no-cache && sail up -d`.

---

<a id="mongodb"></a>
## MongoDB и PHP разширение в Sail

MongoDB не се поддържа „от кутията“ от стандартните Sail среди и изисква инсталиране на PHP mongodb разширението вътре в контейнера на приложението.

1. Добавете услугата MongoDB в Compose файла:

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

2. Регистрирайте тома `sail-mongo` в секцията `volumes:`.
3. Публикувайте Dockerfiles на Sail, ако все още не сте го направили:
   ```bash
   php artisan sail:publish
   ```
4. Редактирайте съответния Dockerfile (например `docker/8.3/Dockerfile`), за да инсталирате PECL разширението:

```dockerfile
# docker/8.3/Dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

5. Прекомпилирайте образите на Sail и ги стартирайте:
   ```bash
   sail build --no-cache
   sail up -d
   ```

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ като контейнер

За проекти, изискващи разширени AMQP функции вместо Redis или опашки на базата на БД, стартирайте RabbitMQ като допълнителен контейнер.

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
> Не забравяйте да декларирате тома `sail-rabbitmq` в най-високото ниво на `volumes:`. Панелът за управление (management UI) ще бъде достъпен на адрес `http://localhost:15672` (в примера данните са: `sail` / `password`).

---

<a id="mailpit"></a>
## Mailpit (SMTP за dev)

Sail включва Mailpit за прехващане на изходяща поща. Това предотвратява случайното изпращане на тестови съобщения до реални потребители по време на локална разработка.

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

Всички изпратени писма могат да бъдат прегледани в уеб интерфейса на адрес `http://localhost:8025`.

---

<a id="search-engines"></a>
## Meilisearch / Typesense

За бърза локална работа с пълнотекстово търсене чрез Laravel Scout:

```bash
php artisan sail:install --with=meilisearch
```

Или копирайте конфигурацията на Meilisearch в Compose файла. Посочете името на услугата като хост в `.env`:

```dotenv
# .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
```

---

<a id="healthchecks"></a>
## Healthcheck и ред на стартиране

По подразбиране Docker Compose стартира контейнерите паралелно. Простата директива `depends_on: [mysql]` гарантира само, че контейнерът с MySQL *ще стартира*, но не и че базата данни е *готова* да приема връзки.

Настройването на `healthcheck` за базата данни и използването на `condition: service_healthy` за зависимите услуги елиминира грешките при миграции при първоначално стартиране.

---

<a id="volumes"></a>
## Именовани томове и нулиране на данни

Именованите томове се държат като постоянни виртуални дискове.
- Изпълнението на командата `sail down` спира контейнерите, но запазва вашите томове с данни.
- Командата `sail down -v` унищожава **всички** томове, като напълно нулира състоянието на локалната среда.
- За да нулирате само една БД: намерете името на тома с `docker volume ls` и го изтрийте чрез `docker volume rm <име_на_том>`.

---

## ⚠️ Чести грешки

**1. Използване на `127.0.0.1` или `localhost` в `.env` за връзка с бази данни**
Настройката `DB_HOST=127.0.0.1` кара Laravel да търси базата данни вътре в самия PHP контейнер, което води до грешка при свързване.
*Решение:* Използвайте `DB_HOST=mysql` или `DB_HOST=pgsql`.

**2. Разрушително нулиране на томове чрез `sail down -v`**
Изпълнението на `sail down -v` за обикновено спиране на контейнерите ще изтрие базите данни, тестовите записи и ключовете за Redis.
*Решение:* Използвайте `sail down` за безопасно спиране. Добавяйте флага `-v` само ако съзнателно искате да изчистите всички данни.

**3. Промяна на Dockerfile без прекомпилиране на образа**
Промяната на публикуваните файлове в директорията `docker/` (например инсталиране на разширения `mongodb` или `gd`) няма да има ефект, докато не принудите образа да се прекомпилира.
*Решение:* Винаги стартирайте `sail build --no-cache` след редактиране на Dockerfile.

---

## 🧠 Въпроси за самопроверка

1. **Защо посочването на `DB_HOST=127.0.0.1` води до неуспех при миграциите вътре в Sail?**
2. **Как `depends_on` с модификатора `condition: service_healthy` се различава от обикновения списък `depends_on`?**
3. **Какво ще се случи с вашите локални данни за PostgreSQL, ако изпълните `sail down -v`?**
4. **Защо за работата на разширението MongoDB в контейнера на приложението се изисква първо да се изпълни командата `sail:publish`?**

<details>
<summary><b>Покажи отговорите</b></summary>

1. В Docker Compose всеки контейнер има свой loopback интерфейс. `127.0.0.1` се отнася за самия контейнер `laravel.test`, на който базата данни не е стартирана. Трябва да укажете името на контейнера на БД в Compose мрежата.
2. Обикновеният `depends_on` проверява само дали контейнерът е стартирал (статус `running`). Модификаторът `condition: service_healthy` блокира стартирането на зависимия контейнер, докато базата данни не премине вътрешен тест за готовност (например не започне да приема сокет връзки).
3. Всички локални данни за PostgreSQL ще бъдат изтрити безвъзвратно заедно с тома, съхраняващ директорията `/var/lib/postgresql/data`.
4. Командата Sail използва предварително конфигурирани универсални Docker образи. За да инсталирате разширения през PECL, трябва да получите достъп до Dockerfile, да го промените и да стартирате локално компилиране.
</details>
