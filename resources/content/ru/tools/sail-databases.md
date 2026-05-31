---
title: "Laravel Sail: базы данных, Redis, Postgres, MongoDB, RabbitMQ в Docker Compose | DevSense"
description: "Compose-рецепты для Laravel Sail: добавление Redis, переход на PostgreSQL, запуск MongoDB с расширением PHP, RabbitMQ, Mailpit/Meilisearch, проверка работоспособности (healthcheck) и именованные тома."
faq:
  - question: "Почему Laravel не может подключиться к MySQL/PostgreSQL в Sail?"
    answer: "Обычно это связано с тем, что в файле .env параметр DB_HOST настроен как 127.0.0.1 или localhost. Внутри сети Docker контейнер laravel.test должен подключаться по имени службы Compose (например, mysql или pgsql)."
  - question: "Как добавить новое расширение PHP (например, MongoDB) в Sail?"
    answer: "Вам нужно опубликовать Docker-файлы Sail с помощью команды 'php artisan sail:publish', отредактировать Dockerfile для нужной версии PHP (добавив pecl install mongodb) и пересобрать образ с флагом 'sail build --no-cache'."
  - question: "Как очистить базу данных, не теряя тома других проектов?"
    answer: "Вместо выполнения команды 'sail down -v', которая удаляет все тома, найдите имя конкретного тома с помощью 'docker volume ls' и удалите его индивидуально через 'docker volume rm <имя_тома>'."
  - question: "Как избежать сбоев миграций при запуске контейнеров Sail?"
    answer: "Настройте проверку работоспособности (healthcheck) в файле docker-compose.yml для службы базы данных и укажите для службы 'laravel.test' зависимость 'condition: service_healthy', чтобы миграции ожидали готовности БД."
---

# Sail: базы данных и Docker-сервисы

Вы запускаете `sail up -d`, вводите `php artisan migrate` и получаете красную стену ошибки `Connection refused`. Несмотря на то, что в Docker Desktop контейнер базы данных светится зеленым как запущенный и исправный, приложение отказывается с ним работать. Этот сбой указывает на фундаментальное правило контейнеризации: в сети Docker Compose хост `127.0.0.1` указывает на сам контейнер приложения, а не на сеть базы данных.

Настройка внешних сервисов (баз данных, кешей, брокеров сообщений) для проекта Laravel может быстро привести к расхождениям в окружении у членов команды. Sail решает эту проблему, представляя ваши сервисы как независимые, воспроизводимые контейнеры, но вам нужно правильно настроить их сетевое взаимодействие, требования к сборке и порядок запуска.

В этом руководстве мы покажем, как заменять и настраивать базы данных (Postgres, MongoDB), подключать кеширование и брокеры (Redis, RabbitMQ), а также безопасно обрабатывать проверки работоспособности (healthchecks) и сброс томов (volumes).

**Навигация:** [Все инструменты](../) · [Sail обзор](sail#what-sail-is) · [Очереди](sail-queues#connections) · [Env и деплой](sail-env-deploy#forward-ports) · [Диагностика](sail-troubleshooting#wsl-filesync)

## Содержание

* [Сеть и имена хостов](#networking)
* [Redis (полный пример)](#redis-service)
* [MySQL → PostgreSQL](#postgres-swap)
* [MongoDB и расширение PHP](#mongodb)
* [RabbitMQ как sidecar](#rabbitmq-sidecar)
* [Mailpit](#mailpit)
* [Meilisearch / Typesense](#search-engines)
* [Healthcheck и порядок старта](#healthchecks)
* [Тома и сброс данных](#volumes)
* [Частые ошибки](#common-mistakes)
* [Вопросы для самопроверки](#self-check)

---

<a id="networking"></a>
## Сеть и имена хостов

Все хосты со стороны приложения в **`.env`** должны быть **именами служб Compose** (`mysql`, `pgsql`, `redis`, `mongo`), а не `127.0.0.1`. Это связано с тем, что PHP работает **внутри** контейнера `laravel.test` и общается через изолированную сеть Docker.

> [!NOTE]
> **Хост-машина против сети контейнеров**
> С хост-машины (из IDE, терминала, DBeaver) вы подключаетесь к базам данных через `127.0.0.1`, используя проброшенный порт (например, `FORWARD_DB_PORT`). Но внутри контейнеров службы должны обращаться друг к другу по именам служб Compose.

---

<a id="redis-service"></a>
## Redis (полный пример)

Чтобы добавить Redis в окружение Sail, определите службу и смонтируйте именованный том.

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

Зарегистрируйте **`sail-redis`** в секции **`volumes:`** на верхнем уровне и добавьте зависимость в **`laravel.test`**:

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

Запустите `sail exec redis redis-cli ping` для проверки подключения.

---

<a id="postgres-swap"></a>
## MySQL → PostgreSQL

Если на продакшене используется PostgreSQL, запуск MySQL локально — это лишний риск.

1. Закомментируйте или удалите службу `mysql` и связанный с ней том в `docker-compose.yml`.
2. Добавьте блок службы `pgsql`, используя стандартный stub-шаблон Sail:

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

3. Обновите массив `depends_on` службы `laravel.test`, указав `pgsql`.
4. Обновите **`.env`**:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

Пересоберите и запустите контейнеры: `sail build --no-cache && sail up -d`.

---

<a id="mongodb"></a>
## MongoDB и расширение PHP

MongoDB не поддерживается «из коробки» стандартными сборками Sail и требует установки расширения PHP mongodb внутри контейнера приложения.

1. Добавьте службу MongoDB в Compose файл:

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

2. Зарегистрируйте том `sail-mongo` в секции `volumes:`.
3. Опубликуйте Docker-файлы Sail, если вы еще этого не сделали:
   ```bash
   php artisan sail:publish
   ```
4. Отредактируйте Dockerfile используемой версии (например, `docker/8.3/Dockerfile`) для установки PECL-расширения:

```dockerfile
# docker/8.3/Dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

5. Пересоберите образы Sail и запустите их:
   ```bash
   sail build --no-cache
   sail up -d
   ```

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ как sidecar

Для проектов, требующих расширенных возможностей AMQP вместо Redis или очередей на базе БД, запустите RabbitMQ как вспомогательный контейнер.

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
> Не забудьте объявить том `sail-rabbitmq` в верхнем уровне `volumes:`. Панель управления (management UI) будет доступна по адресу `http://localhost:15672` (в примере учетные данные: `sail` / `password`).

---

<a id="mailpit"></a>
## Mailpit

Sail включает Mailpit для перехвата исходящей почты. Это предотвращает случайную отправку тестовых сообщений реальным пользователям во время локальной разработки.

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

Все отправленные письма можно просмотреть в веб-интерфейсе по адресу `http://localhost:8025`.

---

<a id="search-engines"></a>
## Meilisearch / Typesense

Для быстрой локальной работы с полнотекстовым поиском через Laravel Scout:

```bash
php artisan sail:install --with=meilisearch
```

Или скопируйте конфигурацию Meilisearch в файл Compose. Укажите имя службы в качестве хоста в `.env`:

```dotenv
# .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
```

---

<a id="healthchecks"></a>
## Healthcheck и порядок старта

По умолчанию Docker Compose запускает контейнеры параллельно. Простая директива `depends_on: [mysql]` гарантирует только то, что контейнер с MySQL *запустится*, но не то, что база данных *готова* принимать соединения.

Настройка `healthcheck` для базы данных и использование `condition: service_healthy` для зависимых служб исключает сбои миграций при первоначальном запуске.

---

<a id="volumes"></a>
## Тома и сброс данных

Именованные тома ведут себя как постоянные виртуальные диски.
- Выполнение команды `sail down` останавливает контейнеры, но сохраняет ваши тома с данными.
- Команда `sail down -v` уничтожает **все** тома, полностью сбрасывая состояние локальной среды.
- Чтобы сбросить только одну БД: найдите имя тома с помощью `docker volume ls` и удалите его через `docker volume rm <имя_тома>`.

---

## ⚠️ Частые ошибки

**1. Использование `127.0.0.1` или `localhost` в `.env` для подключения к базам данных**
Настройка `DB_HOST=127.0.0.1` заставляет Laravel искать базу данных внутри самого PHP-контейнера, что приводит к ошибке подключения.
*Решение:* Используйте `DB_HOST=mysql` или `DB_HOST=pgsql`.

**2. Разрушительный сброс томов с помощью `sail down -v`**
Выполнение `sail down -v` для простой остановки контейнеров сотрет базы данных, тестовые записи и ключи Redis.
*Решение:* Используйте `sail down` для безопасной остановки. Добавляйте флаг `-v` только если осознанно хотите очистить все данные.

**3. Изменение Dockerfile без пересборки образа**
Изменение опубликованных файлов в директории `docker/` (например, установка расширений `mongodb` или `gd`) не возымеет эффекта, пока вы не принудите образ пересобраться.
*Решение:* Всегда запускайте `sail build --no-cache` после редактирования Dockerfile.

---

## 🧠 Вопросы для самопроверки

1. **Почему указание `DB_HOST=127.0.0.1` приводит к сбою выполнения миграций внутри Sail?**
2. **Чем `depends_on` с модификатором `condition: service_healthy` отличается от обычного списка `depends_on`?**
3. **Что произойдет с вашими локальными данными PostgreSQL, если выполнить `sail down -v`?**
4. **Почему для работы расширения MongoDB в контейнере приложения требуется предварительно выполнить команду `sail:publish`?**

<details>
<summary><b>Показать ответы</b></summary>

1. В Docker Compose у каждого контейнера свой сетевой интерфейс loopback. `127.0.0.1` указывает на сам контейнер `laravel.test`, на котором база данных не запущена. Нужно указывать имя контейнера БД в сети Compose.
2. Простой `depends_on` проверяет только факт запуска контейнера (статус `running`). Модификатор `condition: service_healthy` блокирует запуск зависимого контейнера до тех пор, пока база данных не пройдет внутренний тест готовности (например, не начнет принимать сокет-соединения).
3. Все локальные данные PostgreSQL будут безвозвратно удалены вместе с томом, в котором хранилась директория `/var/lib/postgresql/data`.
4. Команда Sail использует преднастроенные универсальные образы Docker. Чтобы установить расширения через PECL, нужно получить доступ к Dockerfile, изменить его и запустить локальную сборку.
</details>
