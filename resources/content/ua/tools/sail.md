---
title: "Laravel Sail: Docker-стек, PHP, Redis, Postgres, черги та деплой | DevSense"
description: "Практичний гайд з Laravel Sail: зміна версії PHP, Redis і RabbitMQ, перехід з MySQL на PostgreSQL, MongoDB, воркери черг у контейнерах, розділення .env і відмінності локальної розробки від dev/prod."
---

# Laravel Sail: повний гайд з локального стеку

**Laravel Sail** — обгортка над **Docker Compose** для типового застосунку Laravel: PHP-FPM (`laravel.test`), СУБД, Redis, Meilisearch, Selenium тощо. Призначення — **локальна розробка** (і CI з Compose), а не готова продакшен-платформа. Нижче — типові налаштування та межа, де Sail закінчується, а починається реальний деплой.

**Серія:** [Бази даних і Docker-сервіси](sail-databases#networking) · [Черги та воркери](sail-queues#connections) · [Оточення та деплой](sail-env-deploy#env-files) · [Діагностика](sail-troubleshooting#wsl-filesync) · [Усі інструменти](../)

## Зміст

* [Що таке Sail (і чим не є)](#what-sail-is)
* [Вимоги та модель](#prerequisites)
* [Аліас і щоденні команди](#daily-commands)
* [Змінити версію PHP](#php-version)
* [Сервіси за замовчуванням і `sail:install`](#default-services)
* [Додати Redis, якщо немає](#add-redis)
* [RabbitMQ і черги](#add-rabbitmq)
* [З MySQL на PostgreSQL](#mysql-to-postgres)
* [MongoDB (контейнер + Laravel)](#mongodb)
* [Черги: драйвери, воркери, Sail](#queues)
* [Пошта та Mailpit](#mail)
* [Томи та продуктивність (WSL2 / macOS)](#volumes-performance)
* [Xdebug](#xdebug)
* [Кастомізація `docker-compose.yml`](#customizing-compose)
* [Файли оточення](#environment-files)
* [Локально проти dev/staging/production](#local-vs-deploy)
* [Чеклист проблем](#troubleshooting)

---

<a id="what-sail-is"></a>
## Що таке Sail (і чим не є)

- **Це:** опублікований **`docker-compose.yml`**, Dockerfile’и з `vendor/laravel/sail/runtimes/…` і скрипт `./vendor/bin/sail` у репозиторії після `php artisan sail:install`.
- **Не це:** хостинг. На сервері часто Docker/K8s, але **Sail у production зазвичай не запускають** — там образи, оркестрація, health checks, секрети та **supervisor** для черг.

Sail — **відтворюване dev-оточення**, частково схоже на прод (розширення PHP, той самий рушій БД), але не копія мережі та масштабу.

<a id="prerequisites"></a>
## Вимоги та модель

- **Docker** і **Compose v2**.
- **WSL2:** тримайте проєкт у **файловій системі Linux** (`~/projects/...`), не на `C:\`, інакше bind mount гальмує.
- Команди — **всередині** контейнерів: `sail artisan …`, `sail composer …`.

---

<a id="daily-commands"></a>
## Аліас і щоденні команди

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Далі: `sail up -d`, `sail artisan migrate`, `sail npm run dev`, `sail shell`, `sail down`.

---

<a id="php-version"></a>
## Змінити версію PHP

1. За потреби: `sail artisan sail:publish`.
2. У **`docker-compose.yml`** для `laravel.test` у **`build.args`** встановіть `PHP_VERSION` (наприклад `8.4`) згідно з підтримкою вашої версії Sail.
3. `sail build --no-cache`, потім `sail up -d`.
4. Перевірка: `sail php -v`.

У власному **Dockerfile** оновіть `FROM` на актуальний образ `laravel/sail-php/...` з [GitHub Sail](https://github.com/laravel/sail).

---

<a id="default-services"></a>
## Сервіси за замовчуванням і `sail:install`

```bash
php artisan sail:install --with=mysql,redis,meilisearch,mailpit,selenium
```

Якщо Redis не встановлювали, а в `.env` лишився `REDIS_HOST=redis`, або **додайте** сервіс, або змініть хост (краще для паритету — контейнер Redis).

---

<a id="add-redis"></a>
## Додати Redis, якщо немає

Додайте сервіс **`redis`** у **`docker-compose.yml`** (образ `redis:alpine`, порт, том `sail-redis`, мережа `sail`, за бажанням healthcheck), оголосіть том унизу файлу, за потреби **`depends_on`** у `laravel.test`. У **`.env`:** `REDIS_HOST=redis`, `REDIS_PORT=6379`. Потім `sail up -d` і `sail exec redis redis-cli ping`.

---

<a id="add-rabbitmq"></a>
## RabbitMQ і черги

У ядрі Laravel **немає** вбудованого драйвера RabbitMQ — потрібен **пакет** AMQP і контейнер **RabbitMQ** (наприклад `rabbitmq:3-management-alpine`, порти 5672 та 15672 для UI). У **`.env`** хост **`rabbitmq`**, облікові дані, `QUEUE_CONNECTION` за документацією пакета. Після встановлення пакета: `sail artisan queue:work rabbitmq`. UI на **15672** допомагає дивитися черги та dead letter.

---

<a id="mysql-to-postgres"></a>
## З MySQL на PostgreSQL

Замініть сервіс БД у compose на **pgsql** за шаблоном Sail, оновіть **`depends_on`**, **`.env`:** `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, порт **5432**. `sail down -v` або міграція даних; далі `sail up -d`, `migrate:fresh` для чистої dev-БД. **`pdo_pgsql`** у типових образах Sail уже є.

---

<a id="mongodb"></a>
## MongoDB (контейнер + Laravel)

Додайте сервіс **mongo**, том, за потреби проброс порту. Зазвичай використовують **`mongodb/laravel-mongodb`** — часто потрібен **`pecl install mongodb`** у Dockerfile Sail і пересборка. З контейнера додатку хост — **ім’я сервису** (`mongo`). Міграції та транзакції відрізняються від SQL — дивіться документацію пакета.

---

<a id="queues"></a>
## Черги: драйвери, воркери, Sail

- **`sync`** — синхронно, для простої відладки.
- **`database` / `redis`:** `sail artisan queue:work`, кілька черг: `--queue=high,default`.
- **Horizon:** локально `sail artisan horizon`; на проді — supervisor або хмарний воркер.
- Після змін коду: **`queue:restart`** або обмеження **`--max-jobs`**.
- **RabbitMQ:** інша семантика повторів і DLX — перевіряйте тестами.

---

<a id="mail"></a>
## Пошта та Mailpit

`MAIL_HOST` / `MAIL_PORT` на сервіс **mailpit** з compose; веб-UI на проброшеному порту — перегляд листів без реальної відправки.

---

<a id="volumes-performance"></a>
## Томи та продуктивність (WSL2 / macOS)

Повний bind mount репозиторію на macOS і з диска Windows у WSL2 часто **повільний**; код — у Linux FS. Іменовані томи зберігають дані БД/Redis після `sail down`; **`sail down -v`** їх видаляє.

---

<a id="xdebug"></a>
## Xdebug

Увімкнення через змінні Sail (див. README), наприклад `SAIL_XDEBUG_MODE=debug,develop`. Мапінг шляхів IDE ↔ контейнер. Для черг — підключення до контейнера з `queue:work`.

---

<a id="customizing-compose"></a>
## Кастомізація `docker-compose.yml`

Імена сервісів = хости в `.env`. Секрети через `${VAR}`. Додаткові сервіси — у мережі **`sail`**. Після змін: `sail build && sail up -d`.

---

<a id="environment-files"></a>
## Файли оточення

**`.env`** локально не в git; **`.env.example`** — шаблон. Опційно **`env_file`** у compose. У CI — змінні в pipeline; тести з `.env.testing`. **`FORWARD_*`** порти, щоб проєкти не конфліктували.

---

<a id="local-vs-deploy"></a>
## Локально проти dev/staging/production

| Тема | Sail | Типовий сервер |
|------|------|----------------|
| Процеси | `sail up` | php-fpm + nginx / Octane |
| Черги | Термінал | Supervisor / хмара |
| TLS | localhost | Реальні сертифікати |
| Секрети | Файл | Vault / параметри |
| Масштаб | 1 контейнер на сервіс | Репліки, LB, managed сервіси |

Окремо перевіряйте **воркери**, **cron `schedule:run`**, **OPcache**, **S3**.

---

<a id="troubleshooting"></a>
## Чеклист проблем

Права на **`storage/`** / **`bootstrap/cache/`**; хост БД/Redis — **ім’я сервису**, не `127.0.0.1`; після Dockerfile — **`build --no-cache`**; зайняті порти — **`FORWARD_*`**; залежності — **`sail composer install`**.

---

## Підсумок

Інвестиція в чистий **`docker-compose.yml`** і **`.env.example`** окупається синхроном команди. Продакшен (моніторинг, бекапи, supervisor, секрети) проектуйте окремо й переносьте в Sail лише те, що потрібно для реалістичної локальної роботи.
