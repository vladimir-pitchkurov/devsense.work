---
title: "Laravel Sail: .env, порти, CI, локально vs production | DevSense"
description: ".env і .env.example, FORWARD_* порти, APP_URL, env_file у compose, приклад GitHub Actions, чеклист: Sail не є production."
---

# Sail: оточення та деплой

**Навігація:** [Усі інструменти](../) · [Sail](sail) · [БД](sail-databases) · [Черги](sail-queues)

## Зміст

* [Файли оточення](#env-files)
* [FORWARD_*](#forward-ports)
* [APP_URL](#app-url)
* [env_file](#compose-env-file)
* [CI](#ci-example)
* [Не production](#not-production)
* [Чеклист](#checklist)

---

<a id="env-files"></a>
## Файли оточення

`.env` — локально, не в git. `.env.example` — усі ключі без секретів. Прод — Vault / CI secrets.

---

<a id="forward-ports"></a>
## FORWARD_*

`FORWARD_DB_PORT`, `FORWARD_REDIS_PORT` — щоб проєкти не конфліктували на localhost.

---

<a id="app-url"></a>
## APP_URL

`APP_URL=http://localhost` з реальним портом. За проксі — TrustProxies і https.

---

<a id="compose-env-file"></a>
## env_file

```yaml
env_file:
    - .env
    - .env.docker.local
```

---

<a id="ci-example"></a>
## CI

```yaml
- run: |
    cp .env.ci .env
    docker compose up -d
    docker compose exec -T laravel.test php artisan test
```

---

<a id="not-production"></a>
## Не production

Sail — для розробки; прод потребує TLS, бекапи, моніторинг, supervisor черг.

---

<a id="checklist"></a>
## Чеклист

`APP_DEBUG=false`, боєві `DB_*`/`REDIS_*`, черги під supervisor, cron для `schedule:run`, секрети не в образі.

---

[Sail](sail) · [БД](sail-databases) · [Черги](sail-queues) · [← Усі інструменти](../)
