---
title: "Laravel Sail: Докер стек для локальной разработки, версии PHP, Redis, Postgres, очереди и деплой | DevSense"
description: "Практическое руководство по Laravel Sail: смена версии PHP, добавление Redis или RabbitMQ, замена MySQL на PostgreSQL, интеграция MongoDB, запуск очередей, настройка окружения и отличия Sail от продакшн серверов."
faq:
  - question: "Что такое Laravel Sail?"
    answer: "Laravel Sail — это легковесный CLI-интерфейс для взаимодействия с локальным Docker-окружением Laravel, построенным на базе Docker Compose."
  - question: "Как изменить версию PHP в Laravel Sail?"
    answer: "Измените сборочный аргумент PHP_VERSION в docker-compose.yml в секции laravel.test, а затем пересоберите контейнер с помощью команды sail build --no-cache."
  - question: "Почему возникает ошибка Connection Refused при подключении к Redis или MySQL?"
    answer: "Скорее всего, вы указали хост 127.0.0.1. Внутри сети контейнеров Sail хостами выступают имена соответствующих сервисов (например, mysql или redis)."
  - question: "Можно ли использовать Laravel Sail на продакшене?"
    answer: "Нет, Laravel Sail предназначен исключительно для локальной разработки. Для продакшена необходимо собирать оптимизированные образы и использовать системы оркестрации (Kubernetes, ECS, Docker Swarm)."
---

# Laravel Sail: Полное руководство по локальному окружению

Laravel Sail — это удобная обертка над **Docker Compose**, которая позволяет развернуть полноценное рабочее окружение (PHP-FPM, MySQL, Redis, Meilisearch, Mailpit и др.) без необходимости устанавливать и настраивать серверный софт на хост-машине.

Тем не менее, Sail — это **не** продакшн-среда. Четкое понимание границ между локальным Sail и реальным деплоем убережет вас от классической проблемы «на моем компьютере все работает». Давайте разберемся со всеми тонкостями Sail!

---

## Содержание (Оглавление)
* [Ментальная модель и системные требования](#mental-model)
* [Полезные алиасы CLI](#shortcuts)
* [Смена версии PHP](#php-version)
* [Кастомизация сервисов (Redis, RabbitMQ, PostgreSQL)](#customizing-services)
* [Отладка с Mailpit и Xdebug](#debugging)
* [Оптимизация производительности в WSL2](#performance)
* [Сравнение: Sail (локально) vs. Продакшн](#local-vs-deploy)
* [🧠 Вопросы для самопроверки](#self-check)

---

<a id="mental-model"></a>
## Ментальная модель и системные требования

Sail не устанавливает PHP или Node.js на ваш физический компьютер. Вместо этого все команды выполняются **внутри изолированных контейнеров**.

Если вы работаете на Windows, вам **необходимо** запустить Docker в режиме **WSL2** (Windows Subsystem for Linux) и хранить проект внутри файловой системы Linux (например, `/home/user/projects/...`), а не на диске `C:\`. Попытки работы через смонтированные Windows-диски приводят к критическому падению производительности дискового ввода-вывода (I/O).

---

<a id="shortcuts"></a>
## Полезные алиасы CLI

Надоело каждый раз писать `./vendor/bin/sail`? Настройте удобный алиас в командной строке!

```bash
# ~/.zshrc или ~/.bashrc
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Теперь повседневные команды станут намного короче:
- `sail up -d` — запуск всех сервисов в фоновом режиме.
- `sail down` — остановка и удаление контейнеров.
- `sail artisan migrate` — запуск миграций БД.
- `sail npm run dev` — запуск Vite для сборки фронтенда.

---

<a id="php-version"></a>
## Смена версии PHP

Чтобы переключить версию PHP в Sail, необходимо отредактировать конфигурацию Compose:

```yaml
# docker-compose.yml
services:
    laravel.test:
        build:
            context: ./vendor/laravel/sail/runtimes/8.4
            dockerfile: Dockerfile
            args:
                WWWGROUP: '${WWWGROUP}'
                # Укажите нужную минорную версию PHP:
                PHP_VERSION: '8.4'
```

После изменения версии пересоберите слои образов без кэша:

```bash
# Терминал
sail build --no-cache
sail up -d
```

---

<a id="customizing-services"></a>
## Кастомизация сервисов

### 1. Подключение Redis
Если при создании проекта вы пропустили установку Redis, добавьте его секцию в конфигурацию:

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
```

Настройки в файле окружения должны выглядеть так:

```dotenv
# .env
REDIS_HOST=redis
REDIS_PORT=6379
```

> [!NOTE]
> **А вы знали?**
> Внутри Docker-сети Sail контейнеры общаются между собой по именам сервисов (`redis`, `mysql`). Попытка указать хост `127.0.0.1` приведет к ошибке, так как этот адрес внутри контейнера указывает на сам контейнер с приложением!

### 2. Замена MySQL на PostgreSQL
Чтобы сменить СУБД, замените секцию `mysql` на `pgsql` в файле `docker-compose.yml`:

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
```

И обновите параметры в вашем `.env`:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
```

---

<a id="debugging"></a>
## Отладка с Mailpit и Xdebug

### Mailpit (Тестирование почты)
Sail автоматически направляет все исходящие письма на заглушку Mailpit. Это защищает вас от случайной отправки тестовых писем реальным клиентам. Укажите параметры в `.env`:

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```
Просмотреть перехваченные письма можно в веб-интерфейсе по адресу `http://localhost:8025`.

### Xdebug
Для активации пошагового дебаггинга достаточно прописать соответствующий режим в `.env`:

```dotenv
# .env
SAIL_XDEBUG_MODE=develop,debug
```
Перезапустите контейнеры (`sail down && sail up -d`), чтобы применить изменения.

---

<a id="local-vs-deploy"></a>
## Сравнение: Sail (локально) vs. Продакшн

| Функция | Laravel Sail (Локально) | Продакшн окружение |
|---------|--------------------------|--------------------|
| **Веб-сервер** | Встроенный сервер PHP | Nginx / Apache + PHP-FPM, или Octane |
| **Очереди** | Ручной запуск `sail artisan queue:work` | Постоянный контроль через Supervisor / Systemd |
| **SSL / HTTPS** | Обычный HTTP на localhost | Терминация TLS на Nginx, Cloudflare или Load Balancer |
| **Кэш и сессии** | Файлы или локальный Redis | Кластер Redis или Managed Memcached |
| **База данных** | Контейнер на локальном диске | Облачные решения (RDS, Cloud SQL) с бэкапами |

---

## ⚠️ Частые ошибки разработчиков

**1. Использование `127.0.0.1` для подключения к БД/Redis**
Если в вашем `.env` в качестве `DB_HOST` указан `127.0.0.1`, приложение внутри контейнера не сможет достучаться до базы. Всегда используйте имя сервиса (`mysql` или `pgsql`).

**2. Запуск команд на хост-машине вместо контейнера**
Запуск `composer install` или `php artisan migrate` прямо на хосте может привести к конфликту версий PHP или ошибкам отсутствия расширений. Используйте обертку Sail:
```bash
# Терминал
sail composer install
sail artisan migrate
```

---

<a id="self-check"></a>
## 🧠 Вопросы для самопроверки

1. **Почему критически важно размещать проект внутри файловой системы WSL2 (например, `~/projects`), а не монтировать его из разделов Windows (`/mnt/c/...`)?**
2. **Правда или ложь?** Чтобы использовать PostgreSQL в Sail, необходимо вручную скачивать и компилировать расширение `pdo_pgsql` внутри контейнера.
3. **Какой хост нужно прописать в `.env` для отправки писем через Mailpit?**
4. **Как обновить кэш воркера очередей после изменения кода в проекте?**

<details>
<summary><b>Показать ответы</b></summary>

1. Прямое монтирование из NTFS в Linux Docker-контейнер создает сильную виртуализационную нагрузку, из-за чего файловые операции (и сборка Vite) могут работать медленнее в 10 раз.
2. **Ложь.** Базовые образы Sail уже поставляются со стандартными расширениями, включая `pdo_pgsql`. Вам нужно лишь обновить конфигурацию в `docker-compose.yml` и `.env`.
3. В качестве хоста необходимо указать `mailpit`.
4. Вызовите команду `sail artisan queue:restart` или запускайте воркер с флагом `--max-jobs=1` на время разработки.
</details>
