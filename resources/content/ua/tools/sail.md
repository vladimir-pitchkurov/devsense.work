---
title: "Laravel Sail: Docker-стек, PHP, Redis, Postgres, черги та деплой | DevSense"
description: "Практичний гайд з Laravel Sail: зміна версії PHP, Redis і RabbitMQ, перехід з MySQL на PostgreSQL, MongoDB, воркери черг у контейнерах, розділення .env і відмінності локальної розробки від dev/prod."
faq:
  - question: "Що таке Laravel Sail?"
    answer: "Laravel Sail — это легковесный CLI-интерфейс для взаимодействия с локальным Docker-окружением Laravel, построенным на базе Docker Compose."
  - question: "Як змінити версію PHP в Laravel Sail?"
    answer: "Змініть складальний аргумент PHP_VERSION в docker-compose.yml у секції laravel.test, а потім перезберіть контейнер за допомогою команди sail build --no-cache."
  - question: "Чому виникає помилка Connection Refused при підключенні до Redis або MySQL?"
    answer: "Швидше за все, ви вказали хост 127.0.0.1. Всередині мережі контейнерів Sail хостами виступають імена відповідних сервісів (наприклад, mysql або redis)."
  - question: "Чи можна використовувати Laravel Sail на продакшені?"
    answer: "Ні, Laravel Sail призначений виключно для локальної розробки. Для продакшену необхідно збирати оптимізовані образи та використовувати системи оркестрації (Kubernetes, ECS, Docker Swarm)."
---

# Laravel Sail: Повний гайд з локального стеку

Laravel Sail — це зручна обгортка над **Docker Compose**, яка дозволяє розгорнути повноцінне робоче оточення (PHP-FPM, MySQL, Redis, Meilisearch, Mailpit та ін.) без необхідності встановлювати та налаштовувати серверний софт на хост-машині.

Проте Sail — це **не** продакшн-середовище. Чітке розуміння меж між локальним Sail та реальним деплоєм вбереже вас від класичної проблеми «на моєму комп'ютері все працює». Давайте розберемося з усіма тонкощами Sail!

---

## Зміст
* [Ментальна модель та вимоги](#mental-model)
* [Корисні аліаси CLI](#shortcuts)
* [Зміна версії PHP](#php-version)
* [Кастомізація сервісів (Redis, RabbitMQ, PostgreSQL)](#customizing-services)
* [Налагодження з Mailpit та Xdebug](#debugging)
* [Оптимізація продуктивності в WSL2](#performance)
* [Порівняння: Sail (локально) vs. Продакшн](#local-vs-deploy)
* [🧠 Питання для самоперевірки](#self-check)

---

<a id="mental-model"></a>
## Ментальна модель та вимоги

Sail не встановлює PHP чи Node.js на ваш фізичний комп'ютер. Замість цього всі команди виконуються **всередині ізольованих контейнерів**.

Якщо ви працюєте на Windows, вам **необхідно** запустити Docker в режимі **WSL2** (Windows Subsystem for Linux) і зберігати проєкт всередині файлової системи Linux (наприклад, `/home/user/projects/...`), а не на диску `C:\`. Спроби роботи через змонтовані Windows-диски призводять до критичного падіння продуктивності дискового вводу-виводу (I/O).

---

<a id="shortcuts"></a>
## Корисні аліаси CLI

Набридло кожен раз писати `./vendor/bin/sail`? Налаштуйте зручний аліас у командному рядку!

```bash
# ~/.zshrc або ~/.bashrc
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Тепер повсякденні команди стануть набагато коротшими:
- `sail up -d` — запуск всіх сервісів у фоновому режимі.
- `sail down` — зупинка та видалення контейнерів.
- `sail artisan migrate` — запуск миграцій БД.
- `sail npm run dev` — запуск Vite для збирання фронтенду.

---

<a id="php-version"></a>
## Зміна версії PHP

Щоб переключити версію PHP в Sail, необхідно відредагувати конфігурацію Compose:

```yaml
# docker-compose.yml
services:
    laravel.test:
        build:
            context: ./vendor/laravel/sail/runtimes/8.4
            dockerfile: Dockerfile
            args:
                WWWGROUP: '${WWWGROUP}'
                # Вкажіть потрібну мінорну версію PHP:
                PHP_VERSION: '8.4'
```

Після зміни версії перезберіть шари образів без кешу:

```bash
# Термінал
sail build --no-cache
sail up -d
```

---

<a id="customizing-services"></a>
## Кастомізація сервісів

### 1. Підключення Redis
Якщо при створенні проєкту ви пропустили встановлення Redis, додайте його секцію в конфігурацію:

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

Налаштування у файлі оточення повинні виглядати так:

```dotenv
# .env
REDIS_HOST=redis
REDIS_PORT=6379
```

> [!NOTE]
> **А ви знали?**
> Всередині Docker-мережі Sail контейнери спілкуються між собою за іменами сервісів (`redis`, `mysql`). Спроба вказати хост `127.0.0.1` призведе до помилки, оскільки ця адреса всередині контейнера вказує на сам контейнер з додатком!

### 2. Заміна MySQL на PostgreSQL
Щоб змінити СУБД, замініть секцію `mysql` на `pgsql` у файлі `docker-compose.yml`:

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

І оновіть параметри у вашому `.env`:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
```

---

<a id="debugging"></a>
## Налагодження з Mailpit та Xdebug

### Mailpit (Тестування пошти)
Sail автоматично направляє всі вихідні листи на заглушку Mailpit. Це захищає вас від випадкового надсилання тестових листів реальним клієнтам. Вкажіть параметри в `.env`:

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```
Переглянути перехоплені листи можна у веб-інтерфейсі за адресою `http://localhost:8025`.

### Xdebug
Для активації покрокового дебаггінгу достатньо прописати відповідний режим у `.env`:

```dotenv
# .env
SAIL_XDEBUG_MODE=develop,debug
```
Перезапустіть контейнери (`sail down && sail up -d`), щоб застосувати зміни.

---

<a id="local-vs-deploy"></a>
## Порівняння: Sail (локально) vs. Продакшн

| Функція | Laravel Sail (Локально) | Продакшн оточення |
|---------|--------------------------|-------------------|
| **Веб-сервер** | Вбудований сервер PHP | Nginx / Apache + PHP-FPM, або Octane |
| **Черги** | Ручний запуск `sail artisan queue:work` | Постійний контроль через Supervisor / Systemd |
| **SSL / HTTPS** | Звичайний HTTP на localhost | Термінація TLS на Nginx, Cloudflare або Load Balancer |
| **Кеш та сесії** | Файли або локальний Redis | Кластер Redis або Managed Memcached |
| **База даних** | Контейнер на локальному диску | Хмарні рішення (RDS, Cloud SQL) з бекапами |

---

## ⚠️ Часті помилки розробників

**1. Використання `127.0.0.1` для підключення до БД/Redis**
Якщо у вашому `.env` як `DB_HOST` вказано `127.0.0.1`, додаток всередині контейнера не зможе достукатися до бази. Завжди використовуйте ім'я сервісу (`mysql` або `pgsql`).

**2. Запуск команд на хост-машині замість контейнера**
Запуск `composer install` або `php artisan migrate` прямо на хості може призвести до конфлікту версій PHP або помилок відсутності розширень. Використовуйте обгортку Sail:
```bash
# Термінал
sail composer install
sail artisan migrate
```

---

<a id="self-check"></a>
## 🧠 Питання для самоперевірки

1. **Чому критично важливо розміщувати проєкт всередині файлової системи WSL2 (наприклад, `~/projects`), а не монтувати його з розділів Windows (`/mnt/c/...`)?**
2. **Правда чи брехня?** Щоб використовувати PostgreSQL у Sail, необхідно вручну скачувати та компилювати розширення `pdo_pgsql` всередині контейнера.
3. **Який хост потрібно прописати в `.env` для відправки листів через Mailpit?**
4. **Як оновити кеш воркера черг після зміни коду в проєкті?**

<details>
<summary><b>Показати відповіді</b></summary>

1. Пряме монтування з NTFS у Linux Docker-контейнер створює сильне віртуалізаційне навантаження, через що файлові операції (і збирання Vite) можуть працювати повільніше в 10 разів.
2. **Ложь.** Базові образи Sail вже постачаються зі стандартними розширеннями, включаючи `pdo_pgsql`. Вам потрібно лише оновити конфігурацію в `docker-compose.yml` та `.env`.
3. В якості хоста необхідно вказати `mailpit`.
4. Викличте команду `sail artisan queue:restart` або запускайте воркер з прапорцем `--max-jobs=1` на час розробки.
</details>
