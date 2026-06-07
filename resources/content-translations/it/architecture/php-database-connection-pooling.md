---
title: "Applicazioni PHP e il collo di bottiglia del pool di connessioni al database | DevSense"
description: "Perché PHP-FPM e i worker moltiplicano le sessioni del database, in che modo i pooler e i proxy di livello intermedio condividono le connessioni reali del server e cosa i team di Laravel dovrebbero sapere sulle modalità di PgBouncer, ProxySQL e prepared statement."
published: 2026-04-14
faq:
  - question: "Qual è la differenza principale tra Session pooling e Transaction pooling in PgBouncer?"
    answer: "Il Session pooling assegna una connessione server a un client per l'intera durata della sua connessione, rilasciandola solo al momento della disconnessione del client. Il Transaction pooling rilascia la connessione del server al pool immediatamente dopo ogni transazione (`COMMIT` o `ROLLBACK`). Il Transaction pooling consente rapporti client-server molto più elevati ma compromette lo stato a livello di sessione, come tabelle temporanee, lock consultivi (advisory lock) e impostazioni persistenti."
  - question: "Perché a volte i prepared statement falliscono quando si utilizza il transaction pooling?"
    answer: "Nel transaction pooling, le query sequenziali nella stessa sessione client possono essere instradate a diversi backend del server di database. Se la query client A prepara un'istruzione sul backend 1 e la query B tenta di eseguire tale istruzione sul backend 2, l'esecuzione fallirà poiché il backend 2 non ne ha conoscenza."
  - question: "In che modo il modello a processi di PHP-FPM influisce sulle connessioni al database rispetto a Node.js o Go?"
    answer: "PHP-FPM esegue un modello basato su un processo per richiesta, in cui ciascun processo figlio gestisce una richiesta alla volta e generalmente chiude le risorse al termine della richiesta. Nei sistemi ad alto traffico, ciò comporta una 'tempesta di connessioni' (handshake TCP e autenticazioni ripetute). Al contrario, Node.js e Go utilizzano runtime asincroni a processo singolo che mantengono un unico pool di connessioni al database a lungo termine condiviso tra migliaia di richieste simultanee."
  - question: "Il connection pooling risolve le query lente del database?"
    answer: "No. Il connection pooling risolve solo l'overhead relativo alla creazione delle connessioni ed evita di superare i limiti di connessione sul server di database. Non accelera l'esecuzione di SQL lenti, non risolve la mancanza di indici e non riduce il carico di CPU/disco causato da query non ottimizzate."
---

# Applicazioni PHP e il collo di bottiglia del database: pooler, proxy e realtà

In molti stack tecnologici il database è abbastanza veloce e le query sono ragionevoli, eppure in produzione si verificano comunque errori di tipo **`too many connections`**, **`remaining connection slots are reserved`** o **blocchi misteriosi** subito dopo un deploy. Spesso la colpa non è di SQL lenti, bensì dell'**aritmetica delle connessioni**: il modello di richiesta di PHP crea **picchi di connessione + autenticazione + TLS**, mentre il database ha un **limite rigido** di backend simultanei. I **pooler** di livello intermedio e i **proxy gestiti** esistono proprio per porre un **piccolo e stabile set di sessioni lato server** dietro a una **vasta schiera di client PHP a breve durata**.

**Guide correlate:** [Databases under load: queries & scaling](database-performance-and-scaling) · [Observability and monitoring](observability-monitoring-laravel)

## Indice

* [Perché PHP amplifica il problema](#why-php)
* [Da cosa si è realmente limitati](#limits)
* [Pooler e proxy di livello intermedio](#middle-tier)
* [PostgreSQL: PgBouncer nella pratica](#pgbouncer)
* [MySQL e MariaDB: ProxySQL e alternative](#proxysql)
* [Proxy gestiti (RDS Proxy, altri)](#managed)
* [Altri pooler: PgCat, Odyssey, pgpool-II](#other-tools)
* [Note specifiche per Laravel](#laravel)
* [Cosa i pooler *non* risolvono](#not-a-cure)
* [Errori comuni](#common-mistakes)
* [Checklist](#checklist)
* [Quiz di autovalutazione](#self-test-quiz)

---

<a id="why-php"></a>
## Perché PHP amplifica il problema

Il classico **PHP-FPM** esegue una richiesta, comunica con i servizi, restituisce una risposta e smantella le risorse allocate a livello di richiesta. A meno che non si utilizzino **connessioni persistenti** (accettandone i compromessi), ogni richiesta che tocca il database solitamente **apre o verifica** una sessione TCP, si **autentica**, eventualmente negozia il protocollo **TLS**, quindi esegue le query.

Sotto carico:

* **`pm.max_children`** su FPM definisce quanti processi PHP possono essere eseguiti **contemporaneamente** su quella macchina. Se la maggior parte delle richieste tocca il database, potresti necessitare di **fino a quel numero** di sessioni DB simultanee **per ogni macchina worker**.
* **I worker delle code** (`queue:work`, Horizon) sono **processi a lungo termine** — ciascun worker simultaneo spesso detiene **una o più** connessioni aperte durante l'esecuzione dei job.
* **La scalabilità orizzontale** moltiplica tutto: tre nodi applicativi con ottanta processi figli ciascuno equivalgono a **duecentoquaranta** potenziali sessioni prima ancora di contare worker, scheduler e task CLI occasionali.

Il database rileva **tempeste di connessioni (connection storm)** durante i deploy e i picchi di traffico: centinaia di handshake in pochi secondi. Anche quando `max_connections` è sufficientemente alto, la **memoria per backend** (soprattutto in Postgres) e la **CPU per l'autenticazione** diventano il vero limite.

---

<a id="limits"></a>
## Da cosa si è realmente limitati

* **`max_connections` (Postgres)** / **`max_connections` (MySQL)** — un limite globale. Gli slot riservati per superuser e replica possono ridurre quelli a disposizione delle applicazioni.
* **Memoria** — ciascun backend del server trasporta buffer e stato; “aumentare semplicemente il limite” può mandare l'istanza in crash per esaurimento della memoria (**OOM**).
* **Latenza di connessione** — TLS + verifica della password + LDAP opzionale aggiungono da **millisecondi a decine di millisecondi** a richiesta se ci si connette ogni volta.
* **Thundering herd (Effetto mandria)** — dopo un riavvio, ogni processo PHP potrebbe tentare di connettersi **contemporaneamente**, saturando la coda di accettazione o il percorso di autenticazione.

> [!NOTE]
> **Aritmetica del carico totale**
> Regola generale: conta **tutti** i programmi che comunicano con SQL (web, worker, cron, strumenti di amministrazione, BI), non solo HTTP. Ogni ambiente contribuisce all'impronta complessiva del database.

---

<a id="middle-tier"></a>
## Pooler e proxy di livello intermedio

Un **pooler** si colloca **tra** PHP e il database. PHP apre una connessione leggera **verso il pooler**; il pooler mantiene un **pool più piccolo** di connessioni reali a Postgres/MySQL e le **riutilizza** tra i vari client.

### Vantaggi
* Meno **backend del server** e meno **RAM** sull'host del database.
* **Multiplexing**: molti client PHP inattivi non tengono occupata ciascuno una sessione server inattiva.
* Comportamento più fluido in presenza di traffico **soggetto a picchi**.

### Costi e avvertenze
* Un **passaggio (hop)** in più (latenza, ulteriore dominio di guasto, configurazione da proteggere e monitorare).
* **La semantica della sessione** cambia a seconda della **modalità** di pooling — vedi PgBouncer di seguito.
* È comunque necessario dimensionare il pooler in modo che non diventi il **nuovo** collo di bottiglia (CPU, descrittori di file, esaurimento del pool).

---

<a id="pgbouncer"></a>
## PostgreSQL: PgBouncer nella pratica

**PgBouncer** è lo standard de facto per il pooling di Postgres negli stack PHP.

**Modalità di pool** comuni:

| Modalità | Comportamento | Integrazione PHP / Laravel |
|----------|---------------|----------------------------|
| **Session** | Una connessione server per l'intera sessione client fino alla disconnessione. | Compatibilità più sicura: `SET`, `LISTEN`, advisory lock, tabelle temporanee, prepared statement funzionano. **Minimo guadagno in termini di multiplexing** se i client rimangono connessi a lungo (worker) o se ci si connette comunque per ciascuna richiesta. |
| **Transaction** | La connessione al server viene restituita al pool **dopo ogni transazione** (COMMIT/ROLLBACK). | **Forte multiplexing** per richieste web brevi. Interrompe le funzionalità **legate alla sessione**: `SET LOCAL` su più round-trip senza transazione, `LISTEN`, tabelle temporanee a lungo termine, alcuni pattern di **prepared statement** a meno che non siano configurati con cura. |
| **Statement** | La connessione al server viene rilasciata dopo **ogni istruzione**. | Rara per gli ORM; interrompe le transazioni a istruzioni multiple. Non è un target tipico di Laravel. |

### Prepared statement e transaction pooling

Molti driver preparano le istruzioni **per nome** sulla sessione. Quando la connessione fisica al server cambia sotto il client, le **istruzioni preparate con nome** possono fallire. Mitigazioni utilizzate in produzione:

* Prediligi prepared statement **senza nome** / il protocollo **simple query** per quel passaggio, oppure
* **Disabilita** i prepared statement lato server per la connessione al pooler (specifico del driver; spesso `PDO::ATTR_EMULATE_PREPARES` o opzioni del framework).

```php
// config/database.php
'connections' => [
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
        // ...
        'options' => [
            PDO::ATTR_EMULATE_PREPARES => true, // Emula i prepared statement localmente in PHP
        ],
    ],
],
```

---

<a id="proxysql"></a>
## MySQL e MariaDB: ProxySQL e alternative

**ProxySQL** è un diffuso livello intermedio per il **protocollo MySQL**: routing, regole per le query, separazione delle letture/scritture e **connection pooling** con regole di multiplexing ottimizzate per utente/schema.

I team lo utilizzano per:
* Limitare le **connessioni al backend** mentre molti processi figli PHP-FPM si connettono a ProxySQL.
* Instradare le **letture** sulle repliche con regole esplicite (monitorando comunque il **ritardo di replica**).
* Eliminare o riscrivere determinati pattern di query (con attenzione — la logica nel proxy rappresenta comunque una **complessità operativa**).

**MySQL Router** (InnoDB Cluster) e alcuni **bilanciatori di carico cloud** presentano comportamenti simili al pooling, ma **verifica la documentazione**: non tutti i livelli effettuano il multiplexing nello stesso modo di ProxySQL.

**MariaDB MaxScale** può fungere da router con funzionalità di gestione delle connessioni a seconda dell'edizione e dei moduli — verifica **licenze** e funzionalità per il tuo deploy.

---

<a id="managed"></a>
## Proxy gestiti (RDS Proxy, altri)

I fornitori cloud offrono **proxy di connessione gestiti** a monte di RDS, Aurora, Cloud SQL, ecc. Di solito gestiscono:
* Il **pooling** e l'integrazione con l'**autenticazione tramite IAM o token**.
* La facilità in caso di **failover** (riconnettendo i backend senza riconnettere contemporaneamente tutti i processi PHP).

Obbediscono comunque alla **semantica del database**: se il prodotto esegue un multiplexing aggressivo, si presentano gli stessi vincoli sui **prepared statement** e sullo **stato della sessione** riscontrati con PgBouncer autogestito — leggi la **matrice dei servizi** per il tuo motore e driver.

---

<a id="other-tools"></a>
## Altri pooler: PgCat, Odyssey, pgpool-II

* **PgCat** e **Odyssey** — pooler Postgres con un crescente seguito; confronta **modalità di pool**, metriche e particolarità dei driver rispetto a PgBouncer prima di effettuare il passaggio.
* **pgpool-II** — spesso distribuito per la **replica** e il routing oltre che per il pooling; **operativamente più pesante** rispetto a PgBouncer se si necessita solo del multiplexing.

---

<a id="laravel"></a>
## Note specifiche per Laravel

* **`config/database.php`** — `connections.*.options` e i flag del driver consentono di allineare il comportamento di **PDO** al pooler (es. emulazione dei prepared statement se richiesto).
* **Separazione lettura/scrittura** — Laravel può inviare i select agli host di `lettura`; in combinazione con un pooler, assicurati che la semantica **sticky** soddisfi le tue aspettative (ritardo della replica vs configurazione `sticky`).
* **Octane / Swoole / FrankenPHP** — i worker **a lungo termine** modificano i calcoli: le connessioni persistenti possono **funzionare bene**, ma è necessario evitare il **filtraggio** dello stato della connessione tra le richieste e monitorare l'**idle timeout** sul server e sul pooler.
* **Horizon / `queue:work`** — la concorrenza × i worker aumenta le connessioni **sostenute**; crea un pool **per worker** oppure utilizza la **modalità transazione** con impostazioni compatibili.
* **Telescope, Nightwatch, debug bar** in produzione possono mantenere le transazioni aperte più a lungo di quanto si pensi — limitane l'uso **solo agli ambienti non di produzione**.

Esempio di configurazione dell'ambiente utilizzando un pooler:
```env
# .env
# PHP si connette a PgBouncer sulla porta 6432; PgBouncer si connette a Postgres sulla porta 5432
DB_HOST=pgbouncer.internal
DB_PORT=6432
DB_DATABASE=app
DB_USERNAME=app_rw
```

---

<a id="not-a-cure"></a>
## Cosa i pooler *non* risolvono

* **Le query N+1** e la mancanza di indici continuano a consumare **CPU e I/O** sul server — il pooling limita unicamente **quante sessioni** manifestano tale carico.
* **Le transazioni lunghe** occupano i backend del server sottraendoli al pool — i benefici della **modalità transazione** svaniscono se il codice mantiene aperte le transazioni durante le chiamate HTTP esterne.
* **Lock globali** e **migrazioni** — l'esecuzione di `migrate` attraverso un pooler saturo può interferire negativamente con i **lock**; alcuni team utilizzano un percorso di amministrazione **diretto** per le operazioni DDL.

---

<a id="common-mistakes"></a>
## Errori comuni

1. **Transaction Pooling con variabili di sessione**: impostare configurazioni specifiche per la sessione (come `SET TIMEZONE` o l'uso di tabelle temporanee) in un ambiente PgBouncer basato su transaction pooling, con la conseguenza che tali impostazioni si trasmettono ad altre sessioni client.
2. **Dimenticare l'emulazione dei prepared statement**: non impostare `PDO::ATTR_EMULATE_PREPARES => true` quando si utilizza il transaction pooling, sollevando eccezioni del tipo \"prepared statement already exists\" o \"prepared statement not found\".
3. **Estendere i limiti del pooler oltre i confini del database**: impostare la proprietà `max_client_conn` e la dimensione del pool backend di PgBouncer su valori superiori rispetto a quello fisico di `max_connections` di PostgreSQL.
4. **Connessioni persistenti errate con FPM**: abilitare `PDO::ATTR_PERSISTENT` sui server web senza gestire la durata del ciclo di vita dei processi figli di FPM, lasciando le connessioni inattive aperte per sempre.

---

<a id="checklist"></a>
## Checklist

1. **Censisci** ogni tipo di processo che avvia connessioni SQL (processi figli max di FPM × nodi, worker di Horizon, cron, CLI).
2. Confronta i totali con **`max_connections`** e la **RAM per connessione** sul DB — decidi in primo luogo tra **adottare un pooler rispetto a una maggiore disciplina dell'app**.
3. Scegli la **modalità di pool** (Postgres) o le **regole di multiplexing** (MySQL) adatte alle funzionalità dell'accoppiata **ORM + driver**.
4. Valuta **prepared statement** e **funzionalità di sessione** (`SET`, tabelle temporanee, advisory lock) sotto stress test.
5. Monitora il **tempo di attesa del pooler** e le **connessioni attive del server** — se la coda del pooler cresce, il limite è ancora rappresentato dal database o dal mix di query.

---

## Riepilogo

I pooler di livello intermedio sono **infrastrutture che si gestiscono** (o si acquistano). Se utilizzati correttamente, trasformano “PHP ha aperto ottocento connessioni” in “Postgres vede sessanta backend occupati” — che è esattamente il tipo di carico per cui la maggior parte dei database OLTP è stata progettata.

---

<a id="self-test-quiz"></a>
## Quiz di autovalutazione

### Domanda 1: Cosa succede se si tenta di utilizzare gli advisory lock di PostgreSQL tramite PgBouncer in esecuzione in modalità transaction pooling?
- A) I lock funzionano correttamente perché PgBouncer li intercetta.
- B) I lock potrebbero bloccare la sessione errata o andare persi silenziosamente quando la connessione cambia backend.
- C) Il parser di PgBouncer genera un'eccezione SQL immediata.

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta: B**
Gli advisory lock sono associati alla sessione fisica del backend. Nella modalità transazione, la query successiva potrebbe essere instradata a una connessione fisica diversa, il che significa che il lock andrebbe perso sul proprio lato pur rimanendo bloccato sul backend originale.
</details>

---

### Domanda 2: Perché PHP-FPM crea tempeste di connessioni rispetto a runtime con worker persistenti come Go o Node.js?
- A) I processi PHP-FPM non supportano il protocollo TCP.
- B) PHP-FPM termina lo stato della richiesta al termine dell'esecuzione, il che chiude e riapre ripetutamente gli handle del database.
- C) Node.js e Go utilizzano motori di database personalizzati.

<details>
<summary><b>Mostra risposte</b></summary>

**Answer: B**
Poiché PHP-FPM ha un ciclo di vita limitato alla richiesta, le connessioni vengono negoziate e chiuse a ogni richiesta, a meno che non vengano configurati attentamente handle persistenti.
</details>
