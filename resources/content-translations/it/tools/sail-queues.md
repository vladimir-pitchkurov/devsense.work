---
title: "Laravel Sail: queue worker, Horizon, Redis, RabbitMQ e job falliti | DevSense"
description: "Esegui le code di Laravel all'interno di Sail: sync vs redis vs database, queue:work e Horizon in locale, RabbitMQ con driver della community, failed_jobs, queue:restart e come differisce la produzione."
faq:
  - question: "Perché i miei job in background non vengono eseguiti in Sail?"
    answer: "A differenza del driver 'sync', i driver come 'database' o 'redis' richiedono un processo worker attivo. Devi avviare un container worker o eseguire 'sail artisan queue:work' nel tuo terminale."
  - question: "Perché le modifiche apportate alle classi Job non hanno effetto sulla coda?"
    answer: "I queue worker di Laravel caricano il codice dell'applicazione in memoria una sola volta. Quando modifichi il codice, devi eseguire 'sail artisan queue:restart' per forzare il ricaricamento dei worker."
  - question: "Come posso eseguire lo scheduler in Laravel Sail senza configurare un cron job sull'host?"
    answer: "Puoi eseguire 'sail run --rm laravel.test php artisan schedule:work' in una finestra separata del terminale, che interroga ed esegue le attività ogni minuto localmente."
  - question: "In che modo la gestione delle code in Sail differisce dalla produzione?"
    answer: "In Sail, i worker vengono eseguiti in primo piano nel terminale e si arrestano quando lo chiudi. In produzione, i gestori di processi come Supervisor o Kubernetes mantengono i worker in esecuzione in modo persistente."
---

# Sail: code e worker

Inneschi un'attività in background nella tua app Laravel, ma non succede nulla. La pagina del job indica \"In attesa\" (Pending) e i record del database rimangono invariati. A questo punto realizzi: in un ambiente containerizzato, i job in background non si eseguono da soli. Senza un processo worker attivo in ascolto all'interno della rete di container Sail, le tue code sono solo file inattivi o silenti elenchi Redis.

L'esecuzione di queue worker e task scheduler sul sistema operativo host fallirà perché non hanno accesso ai database containerizzati di Sail, alle variabili d'ambiente e alle estensioni PHP. Per rispecchiare l'esecuzione in produzione, devi avviare e gestire i runtime dei worker all'interno della rete Compose.

In questa guida ti mostreremo come configurare le connessioni alle code, avviare i worker e Horizon, gestire i job falliti e applicare gli aggiornamenti del codice senza mantenere cache dei worker obsolete.

**Navigazione:** [Tutti gli strumenti](../) · [Panoramica di Sail](sail#what-sail-is) · [Database](sail-databases#networking) · [Env e deploy](sail-env-deploy#forward-ports) · [Risoluzione dei problemi](sail-troubleshooting#wsl-filesync)

## Indice

* [Connessioni a colpo d'occhio](#connections)
* [Esecuzione di `queue:work` in Sail](#queue-work)
* [Horizon (Redis)](#horizon)
* [RabbitMQ e pacchetti AMQP](#rabbitmq)
* [Job falliti e tentativi](#failed-jobs)
* [Modifiche al codice e `queue:restart`](#restart)
* [Scheduler (`schedule:run`)](#scheduler)
* [Differenze con la produzione](#production)
* [Errori comuni](#common-mistakes)
* [Domande di autovalutazione](#self-check)

---

<a id="connections"></a>
## Connessioni a colpo d'occhio

Seleziona il driver della coda in `.env`:

```dotenv
# .env
QUEUE_CONNECTION=redis
```

| Driver | Caso d'uso in Sail |
|--------|-------------------|
| **`sync`** | Esegue il debug della logica dei job in modo sincrono senza avviare un worker. |
| **`database`** | Coda asincrona semplice; richiede l'esecuzione di `php artisan queue:table` e di un worker. |
| **`redis`** | Driver standard per la produzione; si integra con Horizon; richiede un servizio Redis. |
| **`rabbitmq`** | Exchange AMQP; richiede un container sidecar RabbitMQ e un pacchetto della community. |

> [!NOTE]
> **Cache della configurazione**
> Se modifichi `QUEUE_CONNECTION` o qualsiasi altra variabile d'ambiente relativa alle code, esegui `sail artisan config:clear` o `sail artisan config:cache` per applicare le modifiche ai container in esecuzione.

---

<a id="queue-work"></a>
## Esecuzione di `queue:work` in Sail

Per elaborare i job, apri una finestra del terminale dedicata ed esegui:

```bash
# Terminal
sail artisan queue:work
```

Per controlli avanzati, specifica connessioni e opzioni particolari:

```bash
# Terminal
sail artisan queue:work redis --queue=high,default --tries=3 --timeout=90
```

Per eseguire un singolo job e poi uscire (utile per il debug):

```bash
# Terminal
sail artisan queue:work --once
```

---

<a id="horizon"></a>
## Horizon (Redis)

Laravel Horizon offre una bellissima dashboard e una configurazione basata su codice per le code Redis.

1. Installa Horizon tramite Composer:
   ```bash
   sail composer require laravel/horizon
   sail artisan horizon:install
   ```
2. Avvia Horizon all'interno del container:

```bash
# Terminal
sail artisan horizon
```

Accedi alla dashboard all'indirizzo `http://localhost/horizon`. In sviluppo, Horizon si interrompe premendo `Ctrl+C`.

---

<a id="rabbitmq"></a>
## RabbitMQ e pacchetti AMQP

Laravel non include un driver RabbitMQ nativo. Per utilizzare AMQP:

1. Aggiungi il servizio RabbitMQ al tuo file `docker-compose.yml` ([vedi ricette Database](sail-databases#rabbitmq-sidecar)).
2. Installa un pacchetto gestito dalla community (ad es., `vyuldashev/laravel-queue-rabbitmq`).
3. Imposta i dettagli della connessione:

```dotenv
# .env
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
```

4. Avvia l'elaborazione:
   ```bash
   sail artisan queue:work rabbitmq
   ```

---

<a id="failed-jobs"></a>
## Job falliti e tentativi

Quando un'attività in background lancia un'eccezione, Laravel la registra nella tabella `failed_jobs`.

- Elenca i job falliti:
  ```bash
  sail artisan queue:failed
  ```
- Riprova un job specifico:
  ```bash
  sail artisan queue:retry 5
  ```
- Cancella tutti i job falliti:
  ```bash
  sail artisan queue:flush
  ```

---

<a id="restart"></a>
## Modifiche al codice e `queue:restart`

I worker di Laravel avviano l'applicazione una sola volta ed elaborano i job in un ciclo continuo. Se modifichi un file Job, il worker in esecuzione continuerà ad eseguire il vecchio codice memorizzato nella cache.

Ogni volta che salvi modifiche al codice, riavvia i worker:

```bash
# Terminal
sail artisan queue:restart
```

> [!NOTE]
> **Ricaricamento automatico in Sviluppo**
> Per evitare di digitare continuamente `queue:restart` durante lo sviluppo locale veloce, puoi eseguire il worker con le opzioni `--max-jobs=1` o `--once`.

---

<a id="scheduler"></a>
## Scheduler (`schedule:run`)

Sail non esegue automaticamente un demone cron di sistema. Per eseguire le attività programmate in sviluppo, hai due opzioni:

1. Esegui lo scheduler manualmente:
   ```bash
   sail artisan schedule:run
   ```
2. Esegui un ciclo di polling continuo in background:

```bash
# Terminal
sail run --rm laravel.test php artisan schedule:work
```

---

<a id="production"></a>
## Differenze con la produzione

| Argomento | Sail (Sviluppo Locale) | Ambiente di Produzione |
|-----------|-------------------------|------------------------|
| **Gestione dei Worker** | Manuale in primo piano nel terminale | Demone **Supervisor** o systemd |
| **Scalabilità** | Singolo container Docker | Container multipli / Autoscaling in Kubernetes |
| **Failover** | Riavvio manuale del container | Nodi ridondanti e clustering |

---

## ⚠️ Errori comuni

**1. Modifica del codice dei Job senza riavviare il worker**
Correggi un bug nella classe Job, ma il runner della coda continua a restituire lo stesso errore.
*Soluzione:* Il worker ha memorizzato nella cache i vecchi file PHP. Esegui `sail artisan queue:restart`.

**2. Esecuzione di `php artisan queue:work` sul terminale host**
Eseguire il comando direttamente sulla macchina host causerà un arresto anomalo dovuto alla mancanza dei driver di database o all'impossibilità di risolvere gli hostname interni come `redis` o `mysql`.
*Soluzione:* Prefissa sempre il comando con `sail` per eseguirlo all'interno dell'ambiente del container: `sail artisan queue:work`.

**3. Incomprensione del driver di coda `sync`**
L'uso di `QUEUE_CONNECTION=sync` esegue i job immediatamente all'interno del ciclo di vita della richiesta HTTP. Questo nasconde problemi di concorrenza, timeout ed errori di serializzazione che si presenterebbero solo in produzione.
*Soluzione:* Usa `database` o `redis` in locale per allinearti al comportamento di produzione.

---

## 🧠 Domande di autovalutazione

1. **Perché un queue worker in esecuzione non applica le nuove modifiche al codice appena salvate su disco?**
2. **Quale hostname devi scrivere in `.env` per RabbitMQ quando è in esecuzione all'interno di Sail?**
3. **Come si mantiene lo scheduler di Laravel in esecuzione continua senza configurare cron job lato host?**
4. **Quale comando artisan devi eseguire per rimettere in coda un job fallito durante l'esecuzione?**

<details>
<summary><b>Mostra risposte</b></summary>

1. I worker PHP CLI avviano l'intero framework dell'applicazione una sola volta e lo mantengono in memoria. Non rileggono i file dal disco per i job successivi. È necessario eseguire `sail artisan queue:restart`.
2. Devi utilizzare il nome del servizio Compose, ovvero `rabbitmq`.
3. Esegui `sail run --rm laravel.test php artisan schedule:work`, che avvia un processo a lungo termine che esegue lo scheduler ogni minuto.
4. Esegui `sail artisan queue:retry {id}` dove `{id}` è l'identificativo presente nella tabella `failed_jobs` o `all` per riprovare tutti i job falliti.
</details>
