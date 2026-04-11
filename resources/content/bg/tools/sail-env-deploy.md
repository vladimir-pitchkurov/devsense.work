---
title: "Laravel Sail: .env, проброс на портове, CI и локално срещу production | DevSense"
description: "Разделяне на Laravel Sail и хоста: .env.example, FORWARD_* портове, APP_URL в Docker, опционален env_file, GitHub Actions с docker compose и чеклисти, когато Sail не е вашият сървър."
---

# Sail: среди и деплой

Как да **подредите променливите на средата** за Sail, екипа и CI — и как се различава от **staging/production**. Вижте също [Sail](sail#what-sail-is), [БД](sail-databases#networking) и [Опашки](sail-queues#connections).

**Навигация:** [Всички инструменти](../) · [Sail](sail#what-sail-is) · [БД](sail-databases#networking) · [Опашки](sail-queues#connections) · [Диагностика](sail-troubleshooting#wsl-filesync)

## Съдържание

* [`.env`, `.env.example`, тайни](#env-files)
* [**`FORWARD_*` портове и сблъсъци**](#forward-ports)
* [**`APP_URL` и доверени проксита**](#app-url)
* [**Опционален `env_file` в Compose**](#compose-env-file)
* [**CI: GitHub Actions пример**](#ci-example)
* [**Sail срещу dev/staging/prod**](#not-production)
* [**Чеклист преди go-live**](#checklist)

---

<a id="env-files"></a>
## `.env`, `.env.example`, тайни

- **`.env`**: локални тайни; **никога commit**. Копирайте от **`.env.example`** при първи clone.
- **`.env.example`**: безопасни стойности, **документирайте всеки ключ** (`DB_*`, `REDIS_*`, `QUEUE_*`, `MAIL_*`, Scout и т.н.). Sail: **`WWWUSER`**, **`WWWGROUP`**, **`FORWARD_DB_PORT`**, …
- **Екип:** дали host команди (напр. `npm` на Mac) се нуждаят от Laravel env; често важат само контейнерни команди.
- **Production:** тайни през **хостинг**, **Vault** или **masked CI variables** — не копиран `.env` в образа.

---

<a id="forward-ports"></a>
## `FORWARD_*` портове и сблъсъци

Sail пробросва БД, Redis, Meilisearch към **localhost**. При няколко проекта в **`.env`**:

```dotenv
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
```

Рестартирайте Compose. **Вътре** в контейнерите портовете остават по подразбиране (`3306`, `6379`).

---

<a id="app-url"></a>
## `APP_URL` и доверени проксита

```dotenv
APP_URL=http://localhost
```

Използвайте реалния **хост порт**. Зад **Traefik/nginx** настройте **`TrustProxies`** и **`APP_URL`** с `https`.

---

<a id="compose-env-file"></a>
## Опционален `env_file` в Compose

```yaml
laravel.test:
    env_file:
        - .env
        - .env.docker.local
```

За **лични** overrides без промяна на споделен `.env`.

---

<a id="ci-example"></a>
## CI: GitHub Actions пример

```yaml
- name: Run tests in Sail
  run: |
    cp .env.ci .env
    docker compose up -d
    docker compose exec -T laravel.test php artisan test
```

Нагласете имената на услугите. Кеширайте Composer/npm отделно от Docker layer cache.

---

<a id="not-production"></a>
## Sail срещу dev/staging/prod

- **Sail** — ergonomics за разработка (Mailpit, пробросени портове, един възел БД).
- **Споделен dev/staging** — дългоживуща среда; пак не е production HA.
- **Production** — TLS, backups, monitoring, log aggregation, queue supervision, DB replicas, secret rotation — Sail не го замества.

Може да **реизползвате** образи от Sail Dockerfiles, но **оркестрацията** ще е различна.

---

<a id="checklist"></a>
## Чеклист преди go-live

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, силен `APP_KEY`
- [ ] Реални **`DB_*`** / **`REDIS_*`**
- [ ] **`QUEUE_CONNECTION`** съвпада с **supervised** workers
- [ ] **`schedule:run`** в cron
- [ ] Live ключове за поща/плащания в secret store
- [ ] **`LOG_CHANNEL`** и retention според изискванията

---

## Вижте също

* [Sail — пълен гайд](sail#what-sail-is)  
* [Бази данни и услуги](sail-databases#networking)  
* [Опашки](sail-queues#queue-work)  

[← Всички инструменти](../)
