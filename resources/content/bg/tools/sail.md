---
title: "Laravel Sail: Docker стек, PHP, Redis, Postgres, опашки и деплой | DevSense"
description: "Практично ръководство за Laravel Sail: смяна на версия PHP, Redis и RabbitMQ, преминаване от MySQL към PostgreSQL, MongoDB, queue workers в контейнери, разделяне на .env и разлики между локална разработка и dev/prod."
---

# Laravel Sail: пълен гайд за локалния стек

**Laravel Sail** е обвивка върху **Docker Compose** за типично Laravel приложение: PHP-FPM (`laravel.test`), СУБД, Redis, Meilisearch, Selenium и др. Целта е **локална разработка** (и CI с Compose), не готова продукционна платформа. По-долу са чести персонализации и границата, където Sail свършва и започва реалният деплой.

## Съдържание

* [Какво е Sail (и какво не е)](#what-sail-is)
* [Изисквания и модел](#prerequisites)
* [Псевдоним и ежедневни команди](#daily-commands)
* [Смяна на версия PHP](#php-version)
* [Услуги по подразбиране и `sail:install`](#default-services)
* [Добавяне на Redis](#add-redis)
* [RabbitMQ и опашки](#add-rabbitmq)
* [От MySQL към PostgreSQL](#mysql-to-postgres)
* [MongoDB (контейнер + Laravel)](#mongodb)
* [Опашки: драйвери, workers, Sail](#queues)
* [Поща и Mailpit](#mail)
* [Томове и производителност (WSL2 / macOS)](#volumes-performance)
* [Xdebug](#xdebug)
* [Персонализиране на `docker-compose.yml`](#customizing-compose)
* [Файлове на средата](#environment-files)
* [Локално срещу dev/staging/production](#local-vs-deploy)
* [Отстраняване на проблеми](#troubleshooting)

---

<a id="what-sail-is"></a>
## Какво е Sail (и какво не е)

- **Е:** публикуван **`docker-compose.yml`**, Dockerfile-и от `vendor/laravel/sail/runtimes/…` и скрипт `./vendor/bin/sail` след `php artisan sail:install`.
- **Не е:** хостинг. На сървър често Docker/K8s, но **Sail в production обикновено не се пуска** — там образи, оркестрация, health checks, тайни и **supervisor** за опашки.

Sail е **възпроизводима dev среда**, частично подобна на прод (PHP разширения, същият DB engine), но не копие на мрежата и мащаба.

<a id="prerequisites"></a>
## Изисквания и модел

- **Docker** и **Compose v2**.
- **WSL2:** държете проекта в **Linux файлова система** (`~/projects/...`), не на `C:\`, иначе bind mount е бавен.
- Команди **вътре** в контейнери: `sail artisan …`, `sail composer …`.

---

<a id="daily-commands"></a>
## Псевдоним и ежедневни команди

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

След това: `sail up -d`, `sail artisan migrate`, `sail npm run dev`, `sail shell`, `sail down`.

---

<a id="php-version"></a>
## Смяна на версия PHP

1. При нужда: `sail artisan sail:publish`.
2. В **`docker-compose.yml`** за `laravel.test` в **`build.args`** задайте `PHP_VERSION` (напр. `8.4`) според поддръжката на вашия Sail.
3. `sail build --no-cache`, после `sail up -d`.
4. Проверка: `sail php -v`.

При собствен **Dockerfile** обновете `FROM` към актуален `laravel/sail-php/…` от [GitHub Sail](https://github.com/laravel/sail).

---

<a id="default-services"></a>
## Услуги по подразбиране и `sail:install`

```bash
php artisan sail:install --with=mysql,redis,meilisearch,mailpit,selenium
```

Ако Redis не е инсталиран, а `.env` още сочи `redis`, или **добавете** услугата, или сменете хоста (за паритет предпочитайте контейнер).

---

<a id="add-redis"></a>
## Добавяне на Redis

Добавете услуга **`redis`** в **`docker-compose.yml`** (`redis:alpine`, порт, том `sail-redis`, мрежа `sail`, по желание healthcheck), декларирайте тома в **`volumes:`**, при нужда **`depends_on`** в `laravel.test`. В **`.env`:** `REDIS_HOST=redis`, `REDIS_PORT=6379`. `sail up -d` и `sail exec redis redis-cli ping`.

---

<a id="add-rabbitmq"></a>
## RabbitMQ и опашки

Laravel **няма** вграден RabbitMQ драйвер — нужен е **пакет** (AMQP) и контейнер **RabbitMQ** (напр. `rabbitmq:3-management-alpine`, портове 5672 и 15672 за UI). В **`.env`** хост **`rabbitmq`**, акаунт, `QUEUE_CONNECTION` според пакета. След инсталация: `sail artisan queue:work rabbitmq`. UI на **15672** за преглед на опашки и dead letter.

---

<a id="mysql-to-postgres"></a>
## От MySQL към PostgreSQL

Сменете DB услугата в compose с **pgsql** шаблон от Sail, **`depends_on`** към `pgsql`, **`.env`:** `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, порт **5432**. `sail down -v` или миграция на данни; после `sail up -d`, `migrate:fresh` за чиста dev БД. **`pdo_pgsql`** е в типичните Sail образи за PostgreSQL.

---

<a id="mongodb"></a>
## MongoDB (контейнер + Laravel)

Добавете услуга **mongo**, том, по желание порт за Compass. Обикновено **`mongodb/laravel-mongodb`** — често **`pecl install mongodb`** в Sail Dockerfile и rebuild. От контейнера на приложението хостът е **името на услугата** (`mongo`). Миграции и транзакции се различават от SQL — вижте документацията на пакета.

---

<a id="queues"></a>
## Опашки: драйвери, workers, Sail

- **`sync`** — синхронно за прости тестове.
- **`database` / `redis`:** `sail artisan queue:work`, няколко опашки: `--queue=high,default`.
- **Horizon:** локално `sail artisan horizon`; на прод — supervisor или облачен worker.
- След промени в код: **`queue:restart`** или **`--max-jobs`**.
- **RabbitMQ:** различна семантика на повторения и DLX — тествайте изрично.

---

<a id="mail"></a>
## Поща и Mailpit

`MAIL_HOST` / `MAIL_PORT` към **mailpit** от compose; уеб UI на пробросен порт — преглед на писма без реална изпращане.

---

<a id="volumes-performance"></a>
## Томове и производителност (WSL2 / macOS)

Пълен bind mount на repo на macOS и от Windows диск в WSL2 често е **бавен**; кодът — в Linux FS. Именуваните томове запазват данни след `sail down`; **`sail down -v`** ги изтрива.

---

<a id="xdebug"></a>
## Xdebug

Чрез променливи на Sail (виж README), напр. `SAIL_XDEBUG_MODE=debug,develop`. Мапване пътища IDE ↔ контейнер. За опашки — attach към контейнера с `queue:work`.

---

<a id="customizing-compose"></a>
## Персонализиране на `docker-compose.yml`

Имената на услугите = хостове в `.env`. Тайни чрез `${VAR}`. Допълнителни услуги в мрежата **`sail`**. След промени: `sail build && sail up -d`.

---

<a id="environment-files"></a>
## Файлове на средата

**`.env`** локално, не в git; **`.env.example`** — шаблон. По желание **`env_file`** в compose. В CI — променливи в pipeline; тестове с `.env.testing`. **`FORWARD_*`** за портове при няколко проекта.

---

<a id="local-vs-deploy"></a>
## Локално срещу dev/staging/production

| Тема | Sail | Типичен сървър |
|------|------|----------------|
| Процеси | `sail up` | php-fpm + nginx / Octane |
| Опашки | Терминал | Supervisor / облак |
| TLS | localhost | Реални сертификати |
| Тайни | Файл | Vault / параметри |
| Мащаб | 1 контейнер на услуга | Реплики, LB, managed DB/Redis |

Отделно проверявайте **workers**, **cron scheduler**, **OPcache**, **S3**.

---

<a id="troubleshooting"></a>
## Отстраняване на проблеми

Права **`storage/`** / **`bootstrap/cache/`**; хост БД/Redis — **име на услуга**, не `127.0.0.1`; след Dockerfile — **`build --no-cache`**; заети портове — **`FORWARD_*`**; зависимости — **`sail composer install`**.

---

## Обобщение

Един ясен **`docker-compose.yml`** и **`.env.example`** спестяват време на целия екип. Продукцията (мониторинг, backup, supervisor, тайни) проектирайте отделно и пренасяйте в Sail само нужното за реалистична локална работа.
