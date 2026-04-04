---
title: "Laravel Sail: .env, порты, CI и локально vs production | DevSense"
description: "Разделение конфигурации Sail: .env.example, FORWARD_* порты, APP_URL, env_file в compose, пример GitHub Actions и чеклист перед продом."
---

# Sail: окружения и деплой

Организация **переменных** и отличия **Sail** от реальных серверов. См. также [Sail](sail), [БД](sail-databases), [Очереди](sail-queues).

**Навигация:** [Все инструменты](../) · [Sail](sail) · [БД](sail-databases) · [Очереди](sail-queues) · [Диагностика](sail-troubleshooting)

## Содержание

* [Файлы окружения](#env-files)
* [FORWARD_* порты](#forward-ports)
* [APP_URL](#app-url)
* [env_file в compose](#compose-env-file)
* [CI](#ci-example)
* [Sail ≠ production](#not-production)
* [Чеклист](#checklist)

---

<a id="env-files"></a>
## Файлы окружения

**`.env`** — локально, не в git. **`.env.example`** — все ключи без секретов. В проде — Vault / переменные CI / панель хостинга.

---

<a id="forward-ports"></a>
## FORWARD_* порты

Несколько проектов: `FORWARD_DB_PORT=3307`, `FORWARD_REDIS_PORT=6380` и т.д. Внутри контейнеров порты стандартные.

---

<a id="app-url"></a>
## APP_URL

```dotenv
APP_URL=http://localhost
```

Учитывайте реальный порт в браузере. За прокси на сервере — `TrustProxies` и `https` URL.

---

<a id="compose-env-file"></a>
## env_file в compose

```yaml
env_file:
    - .env
    - .env.docker.local
```

Личные дополнения без правок общего `.env` — задокументируйте в README.

---

<a id="ci-example"></a>
## CI

```yaml
- run: |
    cp .env.ci .env
    docker compose up -d
    docker compose exec -T laravel.test php artisan test
```

Имена сервисов — как в вашем `docker-compose.yml`.

---

<a id="not-production"></a>
## Sail ≠ production

Sail упрощает **разработку** (Mailpit, один узел БД). Продакшен: TLS, бэкапы, мониторинг, супервизор очередей, секреты.

---

<a id="checklist"></a>
## Чеклист перед продом

- [ ] `APP_DEBUG=false`, надёжный `APP_KEY`
- [ ] Реальные `DB_*` / `REDIS_*`
- [ ] Очереди под supervisor
- [ ] `schedule:run` в cron
- [ ] Почта и платежи — боевые ключи из хранилища секретов

---

[Sail](sail) · [БД](sail-databases) · [Очереди](sail-queues) · [← Все инструменты](../)
