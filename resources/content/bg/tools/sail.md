---
title: "Laravel Sail: Docker стек, PHP, Redis, Postgres, опашки и деплой | DevSense"
description: "Практично ръководство за Laravel Sail: смяна на версия PHP, Redis и RabbitMQ, преминаване от MySQL към PostgreSQL, MongoDB, queue workers в контейнери, разделяне на .env и разлики между локална разработка и dev/prod."
faq:
  - question: "Какво е Laravel Sail?"
    answer: "Laravel Sail е лек интерфейс с команден ред (CLI) за работа с локалната Docker среда за разработка на Laravel, изградена върху Docker Compose."
  - question: "Как да променя версията на PHP в Laravel Sail?"
    answer: "Променете аргумента за компилация PHP_VERSION в docker-compose.yml под услугата laravel.test, след което прекомпилирайте контейнера със sail build --no-cache."
  - question: "Защо получавам грешка Connection Refused при връзка с Redis или MySQL?"
    answer: "Най-вероятно сте задали хост 127.0.0.1. В мрежата на Sail контейнерите комуникират помежду си чрез имената на услугите (например mysql или redis)."
  - question: "Може ли да се използва Laravel Sail в production?"
    answer: "Не, Laravel Sail е предназначен изключително за локално тестване и разработка. За производствена среда трябва да компилирате оптимизирани Docker изображения и да използвате оркестрация (като Kubernetes или Docker Swarm)."
---

# Laravel Sail: Пълно ръководство за локалния стек

Laravel Sail е удобна обвивка на **Docker Compose**, която позволява бързо стартиране на пълноценна среда за разработка (PHP-FPM, MySQL, Redis, Meilisearch, Mailpit и др.), без да се налага да инсталирате и настройвате сървърен софтуер на собствената си машина.

Въпреки това, Sail **не е** среда за продакшън. Ясното разбиране на границата между локалния Sail и реалния деплой ще ви предпази от класическия проблем „на моя компютър всичко работи“. Нека разгледаме всички тънкости на Sail!

---

## Съдържание
* [Ментален модел и изисквания](#mental-model)
* [Полезни псевдоними (алиаси) на CLI](#shortcuts)
* [Смяна на версията на PHP](#php-version)
* [Персонализиране на услуги (Redis, RabbitMQ, PostgreSQL)](#customizing-services)
* [Дебъгване с Mailpit и Xdebug](#debugging)
* [Оптимизация на производителността в WSL2](#performance)
* [Сравнение: Sail (локално) vs. Продакшн](#local-vs-deploy)
* [🧠 Въпроси за самопроверка](#self-check)

---

<a id="mental-model"></a>
## Ментален модел и изисквания

Sail не инсталира PHP или Node.js на вашия физически компютър. Вместо това всички команди се изпълняват **вътре в изолирани контейнери**.

Ако работите под Windows, е **задължително** да използвате Docker в режим **WSL2** (Windows Subsystem for Linux) и да съхранявате проекта си вътре в Linux файловата система (например `/home/user/projects/...`), а не на диск `C:\`. Опитите за работа през монтирани Windows дискове водят до критично забавяне на дисковия вход-изход (I/O).

---

<a id="shortcuts"></a>
## Полезни псевдоними (алиаси) на CLI

Омръзна ли ви да пишете `./vendor/bin/sail` всеки път? Настройте си удобен алиас в терминала!

```bash
# ~/.zshrc или ~/.bashrc
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Сега ежедневните команди ще бъдат много по-кратки:
- `sail up -d` — стартиране на всички услуги във фонов режим.
- `sail down` — спиране и изтриване на контейнерите.
- `sail artisan migrate` — стартиране на миграциите на БД.
- `sail npm run dev` — стартиране на Vite за компилиране на фронтенда.

---

<a id="php-version"></a>
## Смяна на версията на PHP

За да смените версията на PHP в Sail, трябва да редактирате конфигурацията в Compose:

```yaml
# docker-compose.yml
services:
    laravel.test:
        build:
            context: ./vendor/laravel/sail/runtimes/8.4
            dockerfile: Dockerfile
            args:
                WWWGROUP: '${WWWGROUP}'
                # Посочете желаната минорна версия на PHP:
                PHP_VERSION: '8.4'
```

След промяна на версията, прекомпилирайте слоевете на изображенията без кеш:

```bash
# Терминал
sail build --no-cache
sail up -d
```

---

<a id="customizing-services"></a>
## Персонализиране на услуги

### 1. Свързване с Redis
Ако сте пропуснали инсталирането на Redis при създаването на проекта, добавете следната секция в конфигурацията:

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

Настройките във вашия файл с променливи на средата трябва да изглеждат така:

```dotenv
# .env
REDIS_HOST=redis
REDIS_PORT=6379
```

> [!NOTE]
> **Знаехте ли, че?**
> В мрежата на Sail контейнерите комуникират помежду си чрез имената на услугите си (`redis`, `mysql`). Използването на `127.0.0.1` ще доведе до грешка, тъй като този адрес вътре в контейнера сочи към самото приложение!

### 2. Замяна на MySQL с PostgreSQL
За да смените СУБД, заменете секцията `mysql` с `pgsql` в `docker-compose.yml`:

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

И актуализирайте параметрите във вашия `.env`:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
```

---

<a id="debugging"></a>
## Дебъгване с Mailpit и Xdebug

### Mailpit (Тестване на писма)
Sail автоматично пренасочва всички изходящи писма към Mailpit. Това ви предпазва от изпращането на тестови писма към реални клиенти. Задайте настройките в `.env`:

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```
Можете да прегледате прихванатите писма в уеб интерфейса на адрес `http://localhost:8025`.

### Xdebug
За активиране на пошагово дебъгване просто добавете съответния режим в `.env`:

```dotenv
# .env
SAIL_XDEBUG_MODE=develop,debug
```
Рестартирайте контейнерите (`sail down && sail up -d`), за да приложите промените.

---

<a id="local-vs-deploy"></a>
## Сравнение: Sail (локално) vs. Продакшн

| Функция | Laravel Sail (Локално) | Продакшн среда |
|---------|--------------------------|----------------|
| **Уеб сървър** | Вграден PHP сървър | Nginx / Apache + PHP-FPM, или Octane |
| **Опашки** | Ръчно пускане на `sail artisan queue:work` | Постоянен контрол чрез Supervisor / Systemd |
| **SSL / HTTPS** | Обикновен HTTP на localhost | Терминиране на TLS на Nginx, Cloudflare или Load Balancer |
| **Кеш и сесии** | Файлове или локален Redis | Кластер Redis или Managed Memcached |
| **База данни** | Контейнер на локалния диск | Облачни услуги (RDS, Cloud SQL) с резервни копия |

---

## ⚠️ Чести грешки при разработка

**1. Използване на `127.0.0.1` за връзка с БД/Redis**
Ако във вашия `.env` е зададен `127.0.0.1` за `DB_HOST`, приложението в контейнера няма да може да се свърже с базата данни. Винаги използвайте името на услугата (`mysql` или `pgsql`).

**2. Изпълнение на команди на хост машината вместо в контейнера**
Стартирането на `composer install` или `php artisan migrate` директно на хост машината може да доведе до конфликт на версиите на PHP или грешки поради липса на разширения. Винаги използвайте обвивката Sail:
```bash
# Терминал
sail composer install
sail artisan migrate
```

---

<a id="self-check"></a>
## 🧠 Въпроси за самопроверка

1. **Защо е изключително важно проектът да бъде разположен в WSL2 Linux файловата система (напр. `~/projects`), а не да се монтира от Windows партиции (`/mnt/c/...`)?**
2. **Истина или лъжа?** За да използвате PostgreSQL в Sail, трябва ръчно да изтеглите и компилирате разширението `pdo_pgsql` в контейнера.
3. **Какъв хост трябва да въведете в `.env` за изпращане на писма през Mailpit?**
4. **Как се рестартира кешът на queue worker-а след промяна на кода в проекта?**

<details>
<summary><b>Покажи отговорите</b></summary>

1. Директното монтиране от NTFS към Linux Docker контейнер създава огромно забавяне, поради което файловите операции (и Vite компилацията) могат да работят до 10 пъти по-бавно.
2. **Лъжа.** Базовите изображения на Sail се доставят с предварително инсталирани стандартни разширения, включително `pdo_pgsql`. Трябва само да обновите конфигурациите в `docker-compose.yml` и `.env`.
3. Като хост трябва да въведете `mailpit`.
4. Изпълнете командата `sail artisan queue:restart` или стартирайте воркера с флага `--max-jobs=1` по време на разработка.
</details>
