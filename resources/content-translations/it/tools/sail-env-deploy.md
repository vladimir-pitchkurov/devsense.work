---
title: "Laravel Sail: struttura .env, port forwarding, CI e local vs produzione | DevSense"
description: "Separa la configurazione di Laravel Sail e dell'host: .env.example, porte FORWARD_*, APP_URL in Docker, env_file opzionale, GitHub Actions con docker compose e liste di controllo quando Sail non è il tuo server."
faq:
  - question: "Perché ricevo errori del tipo 'porta già allocata' quando eseguo sail up?"
    answer: "Questo accade quando un altro servizio sulla tua macchina host (come un'istanza locale di MySQL o un'altra istanza di Sail) sta già utilizzando quella porta. Puoi risolvere il problema modificando le variabili FORWARD_DB_PORT o APP_PORT nel tuo file .env."
  - question: "Dovrei committare il mio file .env su Git?"
    answer: "No, non devi mai committare il file .env. Contiene segreti e impostazioni specifiche per lo sviluppatore. Mantieni e committa invece valori predefiniti sicuri all'interno del file .env.example."
  - question: "Come posso caricare impostazioni del container specifiche per lo sviluppatore senza modificare la configurazione condivisa?"
    answer: "Puoi specificare un elenco 'env_file' sotto il tuo servizio all'interno di docker-compose.yml per caricare un file locale secondario (ad es., .env.docker.local) che contiene le sostituzioni locali."
  - question: "Posso eseguire Laravel Sail su un server di produzione?"
    answer: "No. Sail è progettato per l'ergonomia dello sviluppatore e manca di sicurezza di livello di produzione, terminazione TLS, sistemi di backup e ottimizzazioni di scalabilità. Esegui il deploy utilizzando configurazioni dedicate (ad es. Kubernetes, Ansible o Laravel Forge)."
---

# Sail: ambienti e deploy

Committi un file `.env` locale contenente le password del database su GitHub e i tuoi scanner di sicurezza attivano immediatamente un avviso. Oppure un collega scarica la tua repository, esegue `sail up` e vede il processo bloccarsi perché la sua porta MySQL locale `3306` è già occupata da un altro progetto. La gestione dell'ambiente non consiste solo nel definire coppie chiave-valore; si tratta di tracciare un confine netto tra ergonomia locale, test CI/CD e distribuzioni di produzione protette.

Lo sviluppo containerizzato modifica il comportamento delle variabili d'ambiente. Poiché PHP viene eseguito all'interno di `laravel.test` mentre i database si collegano alle porte dell'host, i percorsi, gli URL e le mappature delle porte devono essere adattati a seconda che l'accesso avvenga dalla macchina host o dall'interno della rete Docker Compose.

In questa guida ti mostreremo come strutturare i file `.env`, evitare collisioni di porte sull'host, configurare pipeline di CI basate su Docker e controllare la configurazione prima di andare online in produzione.

**Navigazione:** [Tutti gli strumenti](../) · [Panoramica di Sail](sail#what-sail-is) · [Database](sail-databases#networking) · [Code](sail-queues#connections) · [Risoluzione dei problemi](sail-troubleshooting#wsl-filesync)

## Indice

* [`.env`, `.env.example` e segreti](#env-files)
* [Porte `FORWARD_*` e collisioni](#forward-ports)
* [`APP_URL` e proxy attendibili](#app-url)
* [`env_file` opzionale in Compose](#compose-env-file)
* [CI: modello GitHub Actions](#ci-example)
* [Sail vs server dev/staging/prod](#not-production)
* [Lista di controllo prima di andare online](#checklist)
* [Errori comuni](#common-mistakes)
* [Domande di autovalutazione](#self-check)

---

<a id="env-files"></a>
## `.env`, `.env.example` e segreti

- **`.env`**: memorizza i segreti locali e i dettagli dell'ambiente. **Non committare mai questo file nel controllo di versione.**
- **`.env.example`**: committato su Git. Le chiavi predefinite dovrebbero rappresentare valori locali sicuri, in modo che un nuovo sviluppatore possa copiarlo in `.env` ed eseguire subito `sail up`.

```dotenv
# .env.example
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306

# Sostituzioni specifiche di Sail
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
```

---

<a id="forward-ports"></a>
## Porte `FORWARD_*` e collisioni

Per impostazione predefinita, Sail associa le porte del database e dell'applicazione a localhost. Se esegui più progetti, porte come `3306` (MySQL) o `80` (HTTP) entreranno in collisione.

Per risolvere il problema, modifica le porte inoltrate nel file `.env`:

```dotenv
# .env
APP_PORT=8080
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
```

> [!NOTE]
> **Porte Interne vs Esterne**
> La modifica di `FORWARD_DB_PORT` cambia la porta esposta sulla macchina host. All'interno della rete Docker, MySQL comunica ancora sulla porta predefinita `3306`. La configurazione di Laravel non ha bisogno di cambiare la sua variabile `DB_PORT`.

---

<a id="app-url"></a>
## `APP_URL` e proxy attendibili

Laravel utilizza `APP_URL` per generare URL firmati e reindirizzamenti di rotte.

```dotenv
# .env
APP_URL=http://localhost:8080
```

Assicurati che corrisponda alla porta mappata sul tuo host. Se inserisci un load balancer o un reverse proxy (come Nginx o Traefik) davanti alla tua applicazione di produzione, configura `TrustProxies` in Laravel per leggere correttamente l'IP originale del client e il protocollo (HTTPS).

---

<a id="compose-env-file"></a>
## `env_file` opzionale in Compose

Se hai variabili d'ambiente che si applicano solo all'ambiente Docker, caricale in `docker-compose.yml`:

```yaml
# docker-compose.yml
services:
    laravel.test:
        env_file:
            - .env
            - .env.docker.local
```

Ciò evita di inquinare il file `.env` condiviso con variabili d'ambiente che contano solo all'interno dei container.

---

<a id="ci-example"></a>
## CI: modello GitHub Actions

Puoi utilizzare i servizi Docker Compose di Sail all'interno della tua pipeline CI/CD per eseguire test automatizzati in un ambiente pulito.

```yaml
# .github/workflows/tests.yml
name: Run Tests
on: [push]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Copy CI Env
        run: cp .env.ci .env
      - name: Start Sail
        run: docker compose up -d
      - name: Run Pest/PHPUnit
        run: docker compose exec -T laravel.test php artisan test
```

---

<a id="not-production"></a>
## Sail vs server dev/staging/prod

- **Sail** è ottimizzato per la velocità di sviluppo locale (include strumenti come Mailpit, Meilisearch e ambienti di monitoraggio dei file).
- **Gli ambienti di Staging/Produzione** richiedono hardening della sicurezza, aggregazione dei log, routine di backup automatizzate, certificati SSL/TLS e server di database separati (ad es., AWS RDS).

> [!NOTE]
> Non tentare di utilizzare `sail up` su un server cloud pubblico. Espone i servizi di sviluppo e utilizza stub di configurazione che sono estremamente insicuri.

---

<a id="checklist"></a>
## Lista di controllo prima di andare online

- [ ] `APP_ENV=production` e `APP_DEBUG=false` nell'ambiente di produzione.
- [ ] Genera una chiave `APP_KEY` sicura usando `php artisan key:generate`.
- [ ] Connettiti a istanze di database gestite, non a database locali containerizzati.
- [ ] Imposta `QUEUE_CONNECTION=redis` o `sqs` con sistemi di worker supervisionati.
- [ ] Abilita la terminazione TLS/HTTPS.
- [ ] Configura gestori di log centralizzati (ad es., Bugsnag, Sentry o AWS CloudWatch).

---

## ⚠️ Errori comuni

**1. Committare il file `.env` su Git**
Esporre chiavi API, credenziali del database o password dell'applicazione nella cronologia del repository.
*Soluzione:* Verifica che il file `.env` sia elencato nel tuo file `.gitignore` prima di committare.

**2. Modificare `DB_PORT` invece di `FORWARD_DB_PORT` per i conflitti locali**
Modificare `DB_PORT=3307` nel tuo `.env` senza modificare la configurazione di Compose di Sail interromperà la connessione interna perché il database del container è ancora in ascolto su `3306`.
*Soluzione:* Mantieni `DB_PORT=3306` (per la connessione interna del container) e imposta `FORWARD_DB_PORT=3307` (per cambiare la porta della macchina host).

**3. Distribuzione diretta del file `docker-compose.yml` di Sail in produzione**
Avviare database insieme al container dell'app senza replica persistente, backup o criteri di accesso sicuro.
*Soluzione:* Il file Compose di Sail è un'utility di sviluppo, non un manifesto di deploy.

---

## 🧠 Domande di autovalutazione

1. **Perché la modifica di `DB_PORT=3307` all'interno di `.env` interrompe le migrazioni in Sail, mentre la modifica di `FORWARD_DB_PORT=3307` non lo fa?**
2. **Qual è il rischio per la sicurezza derivante dal deploy diretto del file Compose di Sail su un server di produzione pubblico?**
3. **In che modo il blocco `env_file` in `docker-compose.yml` aiuta a strutturare le sostituzioni dell'ambiente?**
4. **Quale file dovresti aggiornare per assicurarti che i colleghi abbiano un elenco di tutte le chiavi API richieste per una nuova funzionalità?**

<details>
<summary><b>Mostra risposte</b></summary>

1. La modifica di `DB_PORT` indica a Laravel di cercare il database sulla porta `3307` all'interno della rete del container, dove il container database è ancora in ascolto su `3306`. La modifica di `FORWARD_DB_PORT` cambia solo la porta esterna esposta al sistema host, lasciando intatta la rete interna dei container.
2. Espone strumenti di solo sviluppo (come Mailpit e Meilisearch) a Internet pubblico, esegue container con privilegi di sviluppo e espone database senza replica o backup di produzione.
3. Consente di dividere le variabili su più file (come `.env` e `.env.docker.local`), caricando le sostituzioni specifiche di Docker senza ingombrare il file di configurazione principale.
4. Dovresti aggiornare il file `.env.example` con chiavi vuote o segnaposto sicuri e documentare il loro utilizzo.
</details>
