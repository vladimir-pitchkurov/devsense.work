---
title: "Laravel Sail: stack locale Docker, versioni PHP, Redis, Postgres, code e note di deploy | DevSense"
description: "Guida pratica a Laravel Sail: cambiare la versione di PHP, aggiungere Redis o RabbitMQ, passare da MySQL a PostgreSQL, note su MongoDB, queue worker nei container, separazione degli ambienti e differenze tra Sail e i server di dev/staging/produzione."
faq:
  - question: "Cos'è Laravel Sail?"
    answer: "Laravel Sail è un'interfaccia a riga di comando leggera per interagire con l'ambiente di sviluppo Docker predefinito di Laravel, basato su Docker Compose."
  - question: "Come posso cambiare la versione di PHP in Laravel Sail?"
    answer: "Modifica l'argomento di build PHP_VERSION nel file docker-compose.yml sotto il servizio laravel.test, quindi esegui la ricompilazione usando sail build --no-cache."
  - question: "Perché ricevo errori di connessione rifiutata (connection refused) verso Redis o MySQL?"
    answer: "Perché stai cercando di connetterti tramite 127.0.0.1. All'interno della rete di container di Sail, devi utilizzare il nome del servizio (ad es. mysql o redis) come host."
  - question: "Posso eseguire Laravel Sail in produzione?"
    answer: "No, Laravel Sail è progettato esclusivamente per lo sviluppo locale. Per la produzione, dovresti creare immagini Docker ottimizzate e distribuirle utilizzando un'adeguata orchestrazione come Kubernetes, ECS o Docker Swarm."
---

# Laravel Sail: La guida definitiva allo stack locale

Laravel Sail è un eccellente wrapper per **Docker Compose** progettato per configurare un ambiente di sviluppo completo (PHP-FPM, MySQL, Redis, Meilisearch, Mailpit, ecc.) senza dover gestire le configurazioni dei server sulla tua macchina host.

Tuttavia, Sail **non** è un ambiente di produzione. Comprendere il confine tra il Sail locale e un vero e proprio deploy di produzione è fondamentale per evitare la temuta sindrome del \"funziona sulla mia macchina\". Impariamo a padroneggiare Sail insieme!

---

## Indice
* [Modello mentale e prerequisiti](#mental-model)
* [Scorciatoie CLI essenziali](#shortcuts)
* [Cambiare la versione di PHP](#php-version)
* [Personalizzazione dei servizi (Redis, RabbitMQ, PostgreSQL)](#customizing-services)
* [Debugging con Mailpit e Xdebug](#debugging)
* [Ottimizzazione delle prestazioni di WSL2](#performance)
* [Confronto tra Sail (Locale) e Produzione](#local-vs-deploy)
* [🧠 Domande di autovalutazione](#self-check)

---

<a id="mental-model"></a>
## Modello mentale e prerequisiti

Sail non installa PHP o Node sulla tua macchina host. Al contrario, esegue i comandi **all'interno di runtime containerizzati**.

Se utilizzi Windows, **DEVI** eseguire Docker all'interno di **WSL2** (Windows Subsystem for Linux) e mantenere la cartella del tuo progetto all'interno del file system Linux (ad es., `~/Development/my-app`), non nella partizione `C:\` di Windows. Lavorare attraverso il confine di mount Windows-Linux causa colli di bottiglia estremi nelle operazioni di I/O del disco.

---

<a id="shortcuts"></a>
## Scorciatoie CLI essenziali

Stanco di digitare `./vendor/bin/sail` ogni volta? Aggiungi un alias di shell!

```bash
# ~/.zshrc o ~/.bashrc
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Ora puoi eseguire i comandi quotidiani in modo efficiente:
- `sail up -d` — Avvia tutti i servizi in background.
- `sail down` — Arresta i servizi.
- `sail artisan migrate` — Esegue le migrazioni del database.
- `sail npm run dev` — Esegue gli asset di Vite localmente.

---

<a id="php-version"></a>
## Cambiare la versione di PHP

Per aggiornare o retrocedere la versione di PHP in Sail, devi modificare la configurazione di Compose.

```yaml
# docker-compose.yml
services:
    laravel.test:
        build:
            context: ./vendor/laravel/sail/runtimes/8.4
            dockerfile: Dockerfile
            args:
                WWWGROUP: '${WWWGROUP}'
                # Cambia questo argomento di build con la versione secondaria desiderata:
                PHP_VERSION: '8.4'
```

Dopo aver modificato il file, ricompila i layer del container senza usare la cache:

```bash
# Terminal
sail build --no-cache
sail up -d
```

---

<a id="customizing-services"></a>
## Personalizzazione dei servizi

### 1. Connessione a Redis
Se hai saltato Redis durante l'installazione, puoi aggiungerlo modificando la configurazione di Compose:

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

Assicurati che il file delle variabili d'ambiente corrisponda a questo:

```dotenv
# .env
REDIS_HOST=redis
REDIS_PORT=6379
```

> [!NOTE]
> **Lo sapevi?**
> All'interno della rete Sail, i container comunicano tra loro utilizzando i rispettivi nomi di servizio (come `redis` o `mysql`) come host. Usare `127.0.0.1` fallirà perché fa riferimento al container dell'applicazione stessa!

### 2. Passare da MySQL a PostgreSQL
Per scambiare i motori di database, sostituisci il blocco `mysql` con `pgsql` in `docker-compose.yml`:

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

E aggiorna il file `.env`:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
```

---

<a id="debugging"></a>
## Debugging con Mailpit e Xdebug

### Mailpit (Server di posta falso)
Sail instrada automaticamente la posta in uscita a Mailpit. Intercetta le e-mail in modo da non inviare accidentalmente messaggi di test a clienti reali. Configura l'SMTP in `.env`:

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```
Puoi visualizzare le e-mail intercettate aprendo l'interfaccia utente web all'indirizzo `http://localhost:8025`.

### Xdebug
Per abilitare il debug passo-passo, imposta semplicemente la modalità Xdebug nel tuo ambiente:

```dotenv
# .env
SAIL_XDEBUG_MODE=develop,debug
```
Riavvia Sail (`sail down && sail up -d`) per applicare la configurazione.

---

<a id="local-vs-deploy"></a>
## Confronto tra Sail (Locale) e Produzione

| Funzionalità | Laravel Sail (Dev Locale) | Ambiente di Produzione |
|--------------|---------------------------|------------------------|
| **Server HTTP** | Server integrato di PHP | Nginx / Apache + PHP-FPM, o Octane |
| **Code (Queues)** | Eseguite tramite terminale `sail artisan queue:work` | Gestite tramite Supervisor o Systemd |
| **SSL / HTTPS** | HTTP semplice su localhost | TLS terminato su Nginx, Cloudflare o Load Balancer |
| **Cache & Sessioni** | File storage o container Redis locale | Redis clusterizzato o Memcached gestito |
| **Database** | Container effimero | DB gestito (RDS, Cloud SQL) con backup |

---

## ⚠️ Errori comuni

**1. Connessione a `127.0.0.1` per Redis/Database all'interno di Sail**
Se il tuo `DB_HOST` è impostato su `127.0.0.1` nel file `.env`, l'applicazione non sarà in grado di trovare il container del database. Usa invece il nome del servizio (`mysql` o `pgsql`).

**2. Esecuzione dei comandi sull'host invece che sul container**
L'esecuzione di `composer install` o `php artisan migrate` sul tuo terminale locale può causare discrepanze di versione o errori di estensione PHP mancanti. Esegui sempre questi comandi tramite Sail:
```bash
# Terminal
sail composer install
sail artisan migrate
```

---

<a id="self-check"></a>
## 🧠 Domande di autovalutazione

1. **Perché è importante utilizzare i percorsi del file system di WSL2 (come `~/Development`) invece di montare direttamente dalle partizioni Windows (`/mnt/c/...`)?**
2. **Vero o Falso?** Per abilitare PostgreSQL, devi scaricare e compilare manualmente il driver `pdo_pgsql` all'interno del container Sail.
3. **Quale nome host devi usare in `.env` per inviare e-mail tramite Mailpit all'interno di Sail?**
4. **Come si riavvia un queue worker in Sail in modo che carichi le modifiche al codice aggiornate?**

<details>
<summary><b>Mostra risposte</b></summary>

1. Il montaggio da unità Windows (`C:\`) a un container Docker Linux introduce pesanti livelli di traduzione e virtualizzazione, rallentando le operazioni sui file e la compilazione degli asset (Vite/Mix) fino a 10 volte.
2. **Falso.** I runtime PHP predefiniti di Sail includono già le estensioni standard, tra cui `pdo_pgsql`. Devi solo cambiare il servizio in `docker-compose.yml` e `.env`.
3. Devi usare `mailpit` come host.
4. Esegui `sail artisan queue:restart`, oppure avvia il worker con l'opzione `--max-jobs=1` durante lo sviluppo.
</details>
