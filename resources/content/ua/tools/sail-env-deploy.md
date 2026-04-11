---
title: "Laravel Sail: .env, проброс портів, CI і локально vs production | DevSense"
description: "Розділення Laravel Sail і хоста: .env.example, FORWARD_* порти, APP_URL у Docker, опційний env_file, GitHub Actions з docker compose і чеклисти, коли Sail не ваш сервер."
---

# Sail: оточення та деплой

Як **організувати змінні оточення** для Sail, команди та CI — і чим це відрізняється від **staging/production**. Див. також [Sail](sail#what-sail-is), [БД](sail-databases#networking) і [Черги](sail-queues#connections).

**Навігація:** [Усі інструменти](../) · [Sail](sail#what-sail-is) · [БД](sail-databases#networking) · [Черги](sail-queues#connections) · [Діагностика](sail-troubleshooting#wsl-filesync)

## Зміст

* [`.env`, `.env.example`, секрети](#env-files)
* [**`FORWARD_*` порти та колізії**](#forward-ports)
* [**`APP_URL` і довірені проксі**](#app-url)
* [**Опційний `env_file` у Compose**](#compose-env-file)
* [**CI: приклад GitHub Actions**](#ci-example)
* [**Sail vs dev/staging/prod**](#not-production)
* [**Чеклист перед запуском**](#checklist)

---

<a id="env-files"></a>
## `.env`, `.env.example`, секрети

- **`.env`**: локальні секрети; **не комітити**. Копіювати з **`.env.example`** після клону.
- **`.env.example`**: безпечні дефолти, **документувати кожен ключ** (`DB_*`, `REDIS_*`, `QUEUE_*`, `MAIL_*`, Scout тощо). Специфіка Sail: **`WWWUSER`**, **`WWWGROUP`**, **`FORWARD_DB_PORT`**, …
- **Команда:** домовтеся, чи потрібен Laravel env для команд на хості (наприклад `npm` на Mac); часто важливі лише контейнерні команди.
- **Production:** секрети через **хостинг**, **Vault** або **masked variables** у CI — не копійований `.env` у образі.

---

<a id="forward-ports"></a>
## `FORWARD_*` порти та колізії

Sail пробросить БД, Redis, Meilisearch тощо на **localhost**. Кілька проєктів одночасно — задайте в **`.env`**:

```dotenv
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
```

Перезапустіть Compose. **Усередині** контейнерів порти лишаються стандартними (`3306`, `6379`); змінюється лише **хост**-маппінг.

---

<a id="app-url"></a>
## `APP_URL` і довірені проксі

Для підписаних URL, OAuth тощо:

```dotenv
APP_URL=http://localhost
```

Використовуйте **хост-порт**, який реально відкриваєте (напр. `http://localhost:80`). За **Traefik/nginx** на сервері налаштуйте **`TrustProxies`** і реальний **`APP_URL`** з `https`.

---

<a id="compose-env-file"></a>
## Опційний `env_file` у Compose

Можна підвантажити додатковий файл у **`docker-compose.yml`**:

```yaml
laravel.test:
    env_file:
        - .env
        - .env.docker.local
```

Зручно для **індивідуальних** перевизначень без зміни спільного `.env`. Документуйте ключі в README.

---

<a id="ci-example"></a>
## CI: приклад GitHub Actions

Мінімальна ідея: checkout, скопіювати **`.env.ci`** → `.env`, `docker compose run` тести.

```yaml
- name: Run tests in Sail
  run: |
    cp .env.ci .env
    docker compose up -d
    docker compose exec -T laravel.test php artisan test
```

Підлаштуйте імена сервісів під ваш **опублікований** compose. Окремо кешуйте **Composer**/**npm** у CI.

---

<a id="not-production"></a>
## Sail vs dev/staging/prod

- **Sail** оптимізує **зручність розробника** (Mailpit, проброс портів, одновузлова БД).
- **Спільний dev/staging** — довгоживуче оточення; все одно не production HA.
- **Production** потребує **TLS**, **бекапи**, **моніторинг**, **логи**, **нагляд за чергами**, **репліки БД**, **ротацію секретів** — Sail це сам по собі не закриває.

Можна **повторно використовувати** образи з Sail Dockerfile, але **оркестрація** (compose, env, масштаб) буде іншою.

---

<a id="checklist"></a>
## Чеклист перед запуском

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, надійний `APP_KEY`
- [ ] Реальні **`DB_*`** / **`REDIS_*`** (керовані сервіси)
- [ ] **`QUEUE_CONNECTION`** узгоджено з **керованими** воркерами
- [ ] **`schedule:run`** у cron (або керований планувальник)
- [ ] Живі ключі пошти/SMS/платежів у сховищі секретів
- [ ] **`LOG_CHANNEL`** і ретенція відповідають вимогам

---

## Див. також

* [Sail — повний гайд](sail#what-sail-is)  
* [БД і сервіси](sail-databases#networking)  
* [Черги](sail-queues#queue-work)  

[← Усі інструменти](../)
