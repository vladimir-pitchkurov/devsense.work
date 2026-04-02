---
title: "Laravel Sail: .env, портове, CI, локално vs production | DevSense"
description: ".env и .env.example, FORWARD_* портове, APP_URL, env_file в compose, пример GitHub Actions, чеклист: Sail не е production."
---

# Sail: среди и деплой

**Навигация:** [Всички инструменти](../) · [Sail](sail) · [БД](sail-databases) · [Опашки](sail-queues)

## Съдържание

* [Файлове](#env-files)
* [FORWARD_*](#forward-ports)
* [APP_URL](#app-url)
* [env_file](#compose-env-file)
* [CI](#ci-example)
* [Не production](#not-production)
* [Чеклист](#checklist)

---

<a id="env-files"></a>
## Файлове

`.env` локално, не в git. `.env.example` — шаблон. Прод — Vault / CI тайни.

---

<a id="forward-ports"></a>
## FORWARD_*

Различни портове за няколко проекта на една машина.

---

<a id="app-url"></a>
## APP_URL

Реален URL и порт за браузър; зад прокси — TrustProxies.

---

<a id="compose-env-file"></a>
## env_file

Допълнителен `.env.docker.local` в `docker-compose.yml`.

---

<a id="ci-example"></a>
## CI

`docker compose up -d` и `exec laravel.test php artisan test` с `.env.ci`.

---

<a id="not-production"></a>
## Не production

Sail е за разработка; продъкшън изисква TLS, backup, мониторинг, supervisor.

---

<a id="checklist"></a>
## Чеклист

`APP_DEBUG=false`, реални DB/Redis, опашки, cron, секрети.

---

[Sail](sail) · [БД](sail-databases) · [Опашки](sail-queues) · [← Всички инструменти](../)
