---
title: "Laravel Sail: database, Redis, Postgres, MongoDB, RabbitMQ in Docker Compose | DevSense"
description: "Ricette pronte per Laravel Sail: aggiungere Redis, passare a PostgreSQL, eseguire MongoDB con l'estensione PHP, sidecar RabbitMQ, collegamento di Mailpit e Meilisearch, healthcheck e volumi nominativi."
faq:
  - question: "Perché la mia app Laravel non riesce a connettersi a MySQL/PostgreSQL in Sail?"
    answer: "Questo succede solitamente perché la variabile DB_HOST è impostata su 127.0.0.1 o localhost in .env. All'interno della rete Docker, laravel.test deve connettersi utilizzando il nome del servizio Compose (ad es. mysql o pgsql) come hostname."
  - question: "Come posso aggiungere una nuova estensione PHP (come MongoDB) a Sail?"
    answer: "Devi pubblicare i Dockerfile di Sail usando 'php artisan sail:publish', modificare il Dockerfile per la tua versione di PHP (ad es. pecl install mongodb) e ricompilare l'immagine con 'sail build --no-cache'."
  - question: "Come si svuota il database senza perdere gli altri volumi di progetto?"
    answer: "Invece di eseguire 'sail down -v' che distrugge tutti i volumi, individua il nome del volume specifico usando 'docker volume ls' e rimuovilo singolarmente usando 'docker volume rm <volume_name>'."
  - question: "Come posso prevenire errori di migrazione all'avvio di Sail?"
    answer: "Configura gli healthcheck di Docker per il tuo database in docker-compose.yml e imposta il servizio 'laravel.test' in modo che dipenda da esso con 'condition: service_healthy', così che le migrazioni attendano che il database sia pronto."
---

# Sail: database e servizi Docker

Esegui `sail up -d`, digiti `php artisan migrate` e ottieni un muro rosso di errore `Connection refused`. Sebbene Docker Desktop mostri che il container del tuo database è in esecuzione e sano, l'applicazione al suo interno rifiuta di comunicare con esso. Questa interruzione della connessione evidenzia una regola fondamentale dello sviluppo containerizzato: in Docker Compose, `127.0.0.1` si riferisce al container stesso, non alla rete.

La configurazione di servizi esterni (database, cache, broker di messaggi) per un progetto Laravel può portare rapidamente a problemi di disallineamento dell'ambiente tra i vari membri del team. Sail risolve questo problema trattando i servizi come container indipendenti e riproducibili, ma è necessario configurare correttamente la connettività di rete, i requisiti di build e l'ordine di avvio.

In questa guida mostriamo come sostituire e configurare i database (Postgres, MongoDB), collegare cache e broker (Redis, RabbitMQ) e gestire in sicurezza gli healthcheck e i reset dei volumi.

**Navigazione:** [Tutti gli strumenti](../) · [Panoramica di Sail](sail#what-sail-is) · [Code](sail-queues#connections) · [Env e deploy](sail-env-deploy#forward-ports) · [Risoluzione dei problemi](sail-troubleshooting#wsl-filesync)

## Indice

* [Regole di rete e hostname](#networking)
* [Servizio Redis (esempio completo)](#redis-service)
* [Sostituire MySQL con PostgreSQL](#postgres-swap)
* [MongoDB + estensione PHP in Sail](#mongodb)
* [RabbitMQ come container](#rabbitmq-sidecar)
* [Mailpit (sviluppo SMTP)](#mailpit)
* [Meilisearch / Typesense (ricerca opzionale)](#search-engines)
* [Healthcheck e ordine di avvio](#healthchecks)
* [Volumi nominativi e reset dei dati](#volumes)
* [Errori comuni](#common-mistakes)
* [Domande di autovalutazione](#self-check)

---

<a id="networking"></a>
## Regole di rete e hostname

Tutti gli host lato applicazione in **`.env`** devono essere **nomi dei servizi Compose** (`mysql`, `pgsql`, `redis`, `mongo`), non `127.0.0.1`. Questo perché PHP viene eseguito **all'interno** del container `laravel.test` e comunica tramite una rete Docker isolata.

> [!NOTE]
> **Macchina Host vs Rete dei Container**
> Dalla tua macchina host (IDE, terminale, DBeaver), ti connetti ai database tramite `127.0.0.1` utilizzando la porta inoltrata (ad es., `FORWARD_DB_PORT`). All'interno dei container, invece, i servizi devono comunicare tra loro utilizzando i rispettivi nomi di servizio Compose.

---

<a id="redis-service"></a>
## Servizio Redis (esempio completo)

Per aggiungere Redis al tuo ambiente Sail, definisci il servizio e monta un volume nominativo.

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

Registra **`sail-redis`** sotto la sezione principale **`volumes:`** e dichiara la dipendenza all'interno di **`laravel.test`**:

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

Esegui `sail exec redis redis-cli ping` per verificare la connessione.

---

<a id="postgres-swap"></a>
## Sostituire MySQL con PostgreSQL

Se il tuo server di produzione utilizza PostgreSQL, eseguire MySQL in locale è un rischio inutile.

1. Commenta o elimina il servizio `mysql` e il volume associato in `docker-compose.yml`.
2. Aggiungi il blocco di servizio `pgsql` utilizzando lo stub standard di Sail:

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

3. Aggiorna l'array `depends_on` di `laravel.test` affinché punti a `pgsql`.
4. Aggiorna il file **`.env`**:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

Ricompila e avvia i container utilizzando `sail build --no-cache && sail up -d`.

---

<a id="mongodb"></a>
## MongoDB + estensione PHP in Sail

MongoDB non è supportato in modo nativo dai runtime predefiniti di Sail e richiede l'installazione dell'estensione PHP MongoDB all'interno del container dell'applicazione.

1. Aggiungi il servizio MongoDB al tuo file Compose:

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

2. Registra il volume `sail-mongo` sotto la voce `volumes:`.
3. Pubblica i Dockerfile di Sail se non lo hai già fatto:
   ```bash
   php artisan sail:publish
   ```
4. Modifica il Dockerfile del runtime (ad es., `docker/8.3/Dockerfile`) per installare l'estensione PECL:

```dockerfile
# docker/8.3/Dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

5. Ricompila le tue immagini Sail e avviale:
   ```bash
   sail build --no-cache
   sail up -d
   ```

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ como container

Per i progetti che richiedono funzionalità AMQP avanzate anziché code basate su Redis o database, esegui RabbitMQ come container sidecar.

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
> Assicurati di dichiarare `sail-rabbitmq` all'interno dell'elenco globale `volumes:`. Accedi alla dashboard di gestione in locale all'indirizzo `http://localhost:15672` utilizzando le tue credenziali personalizzate (ad es., `sail` / `password`).

---

<a id="mailpit"></a>
## Mailpit (sviluppo SMTP)

Sail include Mailpit per intercettare le e-mail in uscita. Ciò impedisce che messaggi reali vengano accidentalmente inviati agli utenti durante i test locali.

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

Tutte le e-mail inviate dall'applicazione possono essere lette tramite l'interfaccia web all'indirizzo `http://localhost:8025`.

---

<a id="search-engines"></a>
## Meilisearch / Typesense (ricerca opzionale)

Per abilitare una ricerca locale velocissima con Laravel Scout:

```bash
php artisan sail:install --with=meilisearch
```

Oppure copia lo stub del servizio Meilisearch nella tua configurazione di Compose. Ricorda di puntare le variabili d'ambiente al nome del servizio:

```dotenv
# .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
```

---

<a id="healthchecks"></a>
## Healthcheck e ordine di avvio

Per impostazione predefinita, Docker Compose avvia i container in parallelo. Un `depends_on: [mysql]` standard garantisce solo che il container MySQL *si avvii*, ma non che il motore del database sia *completamente pronto* a ricevere connessioni.

L'implementazione di un `healthcheck` sul servizio database e l'impostazione di `condition: service_healthy` sul servizio dell'applicazione garantiscono che le migrazioni vengano eseguite in modo pulito durante l'inizializzazione.

---

<a id="volumes"></a>
## Volumi nominativi e reset dei dati

I volumi nominativi fungono da unità virtuali persistenti.
- L'esecuzione di `sail down` arresta i container ma lascia inalterati i volumi del database.
- L'esecuzione di `sail down -v` distrugge **tutti** i volumi, resettando completamente l'ambiente locale.
- Per resettare solo un database: individua il volume di destinazione con `docker volume ls` e rimuovilo singolarmente usando `docker volume rm <volume_name>`.

---

## ⚠️ Errori comuni

**1. Utilizzo di `127.0.0.1` o `localhost` all'interno di `.env` per le connessioni ai container**
Impostare `DB_HOST=127.0.0.1` costringe Laravel a cercare il database all'interno del container PHP stesso, causando un errore di connessione.
*Soluzione:* Usa `DB_HOST=mysql` o `DB_HOST=pgsql`.

**2. Reset distruttivi dei volumi con `sail down -v`**
Eseguire `sail down -v` quando si desidera solo arrestare i container cancellerà i database, i record di seeding e le chiavi di Redis.
*Soluzione:* Usa `sail down` per arrestare i container in sicurezza. Usa `-v` solo se intendi cancellare completamente lo stato.

**3. Modifica dei Dockerfile personalizzati senza ricompilare l'immagine**
Modificare i Dockerfile del runtime pubblicati per installare le estensioni PHP (come `mongodb` o `gd`) non ha alcun effetto finché non si forza una ricostruzione.
*Soluzione:* Esegui sempre `sail build --no-cache` dopo aver modificato i Dockerfile.

---

## 🧠 Domande di autovalutazione

1. **Perché l'impostazione `DB_HOST=127.0.0.1` fallisce quando si eseguono le migrazioni Laravel all'interno di Sail?**
2. **In che modo `depends_on` con `condition: service_healthy` differisce da un semplice array `depends_on`?**
3. **Cosa succede ai dati locali di PostgreSQL se si esegue `sail down -v`?**
4. **Perché è necessario pubblicare i Dockerfile di Sail (tramite `sail:publish`) prima di poter utilizzare l'estensione MongoDB?**

<details>
<summary><b>Mostra risposte</b></summary>

1. All'interno di Docker Compose, ogni container ha la propria interfaccia di rete loopback. `127.0.0.1` fa riferimento al container `laravel.test` stesso, che non esegue il database. È necessario utilizzare il nome del servizio del container database come hostname.
2. Un semplice `depends_on` controlla solo se il container è avviato (nello stato `running`). Il modificatore `condition: service_healthy` impedisce l'avvio del container dipendente fino a quando il database non supera il proprio controllo di integrità interno (ad es. pronto ad accettare connessioni socket).
3. Tutti i dati locali di PostgreSQL verranno distrutti in modo permanente poiché il volume che memorizza `/var/lib/postgresql/data` viene eliminato.
4. Sail utilizza immagini Docker generiche precompilate. Per installare estensioni PECL personalizzate, devi accedere e modificare la configurazione del Dockerfile sottostante e compilare un'immagine locale.
</details>
