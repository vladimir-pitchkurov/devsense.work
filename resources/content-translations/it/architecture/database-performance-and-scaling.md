---
title: "Database sotto carico: query, indici, MySQL vs Postgres, scalabilità | DevSense"
description: "Come ottimizzare SQL e schema, scegliere i tipi di indice, quando la logica sul lato database diventa un ostacolo, come differiscono MySQL e PostgreSQL in produzione e quanto costano realmente lo scaling verticale, le repliche, la decomposizione e lo sharding."
published: 2026-04-13
faq:
  - question: "Perché la paginazione seek (keyset) è più veloce della paginazione OFFSET su dataset di grandi dimensioni?"
    answer: "La paginazione OFFSET richiede al motore del database di scansionare e scartare tutte le righe saltate (ad esempio, scansionando 100.000 righe per restituirne 20). La paginazione seek keyset utilizza una condizione di filtro (ad esempio, `WHERE (created_at, id) < (?, ?)`) basata sull'ultima riga visualizzata, consentendo al motore di passare direttamente alle righe di destinazione utilizzando un indice B-tree senza scansionare le righe saltate."
  - question: "Quando si dovrebbe usare un covering index anziché un indice normale?"
    answer: "Si dovrebbe usare un covering index (spesso utilizzando la clausola `INCLUDE` o un indice composto contenente tutti i campi selezionati) quando una query ad alta frequenza legge solo poche colonne. Ciò consente al motore del database di restituire il risultato direttamente dalla struttura dell'indice (Index Only Scan) senza eseguire una ricerca secondaria su disco o heap per la riga completa."
  - question: "Quali sono i principali compromessi nell'uso delle repliche di lettura?"
    answer: "Le repliche di lettura alleggeriscono il database primario dalle operazioni di lettura, aumentando il throughput delle letture. Tuttavia, il ritardo di replica (replication lag) comporta il rischio che le repliche servano dati obsoleti (consistenza eventuale). Gli sviluppatori devono progettare il routing dell'applicazione in modo da indirizzare le letture dipendenti dalle scritture al database primario per evitare bug di consistenza del tipo 'read-your-writes'."
  - question: "Perché lo sharding è considerato l'ultima risorsa per lo scaling del database?"
    answer: "Lo sharding suddivide i dati su istanze di database fisicamente separate, aumentando la complessità operativa. Impedisce l'uso di join tra shard, l'integrità delle transazioni globali (senza lenti commit a due fasi) e i vincoli semplici di unicità globale, introducendo al contempo la difficoltà di bilanciare nuovamente gli shard quando cambia la distribuzione dei dati."
---

# Database sotto carico: ottimizzazione delle query, indici, motori e compromessi di scalabilità

La maggior parte delle storie sulle prestazioni sono noiose finché non smettono di esserlo. La dashboard appare corretta a novantacinque millisecondi, poi viene lanciata una campagna, un report unisce sei tabelle e improvvisamente **il checkout** e **la reimpostazione della password** condividono la stessa coda dietro lo stesso motore di archiviazione. Le soluzioni raramente sono una singola manopola: consistono in **meno round-trip**, **indici corrispondenti ai predicati reali**, **calcoli onesti della capacità** e, a volte, **nell'ammettere che un database logico non può essere infinito**.

**Guide correlate:** [High-load event ingestion](high-load-event-ingestion) · [Message queues compared](message-queues-compared) · [Observability and monitoring](observability-monitoring-laravel)

## Indice

* [Misurare prima di “ottimizzare”](#measure)
* [Ottimizzazioni a livello di query con esempi](#queries)
* [Scelte di schema che invecchiano bene](#schema)
* [Tipi di indice e quando sono utili](#indexes)
* [Perché la logica complessa all'interno del database danneggia i team](#db-logic)
* [MySQL vs PostgreSQL nella pratica](#mysql-vs-postgres)
* [Percorsi di scaling e i problemi che introducono](#scaling)
* [Decomposizione senza favole](#decomposition)
* [Sharding: chiavi, problemi tra shard, ribilanciamento](#sharding)
* [Errori comuni](#common-mistakes)
* [Checklist prima di effettuare lo sharding o acquistare hardware](#checklist)
* [Quiz di autovalutazione](#self-test-quiz)

---

<a id="measure"></a>
## Misurare prima di “ottimizzare”

I **percentili di latenza** superano le medie. Una media di 40 ms può nascondere una coda in cui **l'1%** delle richieste supera i due secondi a causa di attese di lock o cache fredde.

Segnali pratici:
* **Slow query log** con una soglia da rivedere man mano che i dati crescono — non registrare \"tutto\", altrimenti si abitueranno le persone a ignorare il rumore.
* **`EXPLAIN` (Postgres)** / **`EXPLAIN` (MySQL 8+)** sulle forme di query effettivamente eseguite in produzione con i relativi binding dei parametri, non solo valori letterali scritti a mano.
* **Numero di connessioni** e **saturazione del pool**; molti incidenti legati al \"database lento\" sono dovuti all'**attesa di una connessione**, non al disco.

> [!NOTE]
> **Stato condiviso (Shared State)**
> Il database è uno stato condiviso. Qualsiasi elemento che aumenti **CPU, lock o I/O** sul primario finisce per competere con tutto il resto su quel medesimo percorso.

---

<a id="queries"></a>
## Ottimizzazioni a livello di query con esempi

### Recuperare solo ciò che serve

Un `SELECT *` ampio su righe corpose costringe il motore a spostare byte che verranno scartati in PHP o Node. Preferire colonne esplicite, specialmente su tabelle con payload di testo o JSON di grandi dimensioni.

```sql
-- database/queries/fetch_users.sql
-- Evitare (invia tutte le colonne, inclusi i blob che non vengono visualizzati)
SELECT * FROM users WHERE country = 'DE' LIMIT 50;

-- Preferire
SELECT id, email, display_name, created_at
FROM users
WHERE country = 'DE'
ORDER BY created_at DESC
LIMIT 50;
```

### Forma dei join e cardinalità

I cicli nidificati (nested loops) sono economici quando il lato interno è **supportato da un indice** ed è di piccole dimensioni; i join hash brillano su set più grandi — **la strategia ottenuta** dipende dal motore e dalle statistiche. Se un join fa esplodere il numero di righe perché una relazione è **molti-a-molti senza vincoli**, correggere il modello, non il timeout.

### Paginazione senza scansionare l'intera tabella

La paginazione con `OFFSET` è ingannevolmente semplice. Per offset elevati, il motore spesso **continua a scorrere** le righe saltate.

```sql
-- database/queries/offset_pagination.sql
-- Diventa più lento al crescere di :offset
SELECT id, title, created_at
FROM posts
ORDER BY created_at DESC, id DESC
LIMIT 20 OFFSET 100000;
```

La **paginazione Keyset (seek)** utilizza l'ultima chiave di ordinamento vista:

```sql
-- database/queries/seek_pagination.sql
SELECT id, title, created_at
FROM posts
WHERE (created_at, id) < (:last_created_at, :last_id)
ORDER BY created_at DESC, id DESC
LIMIT 20;
```

È necessario un **indice di supporto** corrispondente all'ordine di ordinamento, ad esempio `(created_at DESC, id DESC)`.

### `EXISTS` vs `IN` per le verifiche di esistenza

Per i pattern del tipo \"esiste una riga corrispondente?\", i piani in stile semi-join si comportano spesso molto bene:

```sql
-- database/queries/exists_lookup.sql
SELECT o.id, o.total_cents
FROM orders o
WHERE EXISTS (
  SELECT 1 FROM order_items i
  WHERE i.order_id = o.id AND i.sku = 'GOLD-001'
);
```

### Aggregazioni e reportistica

I `GROUP BY` pesanti su tabelle OLTP grezze rappresentano un modo classico per **compromettere la latenza di coda**. Pre-elaborare i dati in **tabelle di riepilogo**, **viste materializzate** (Postgres) o in un archivio **OLAP** quando l'azienda necessita realmente di analisi interattive.

---

<a id="schema"></a>
## Scelte di schema che invecchiano bene

* **Tipi corrispondenti alla realtà** — memorizzare il denaro come **unità minori intere** (ad esempio, centesimi) o come **decimal con precisione esplicita**, non come float binari.
* **Nullabilità** — le colonne nullable complicano l'indicizzazione e le statistiche; utilizzarle quando il valore \"sconosciuto\" ha un significato reale, non come valore predefinito dettato dalla pigrizia.
* **Chiavi esterne (Foreign Keys)** — costano leggermente in scrittura e garantiscono la **coerenza referenziale**. I team che le disabilitano per \"velocità\" pagano spesso il prezzo in **righe orfane** e **bug applicativi non deterministici**.
* **Eccesso di normalizzazione vs percorsi di lettura** — la teoria pura ignora la frequenza con cui si leggono forme unite. Un **campo denormalizzato** misurato e documentato può superare join interminabili — al costo di una **complessità di scrittura** e di una **disciplina degli invarianti**.

---

<a id="indexes"></a>
## Tipi di indice e quando sono utili

Non tutti gli indici sono B-tree e non tutti i B-tree hanno la forma corretta.

| Concetto | Uso tipico | Note |
|----------|------------|------|
| **B-tree (predefinito)** | Uguaglianza e intervallo su tipi ordinabili | La maggior parte degli indici \"normali\"; nei compositi **l'ordine conta** — le colonne principali devono corrispondere ai filtri comuni `WHERE`/`ORDER BY`. |
| **Hash** | Uguaglianza esatta (ove supportato) | Gli indici **hash** di Postgres storicamente presentavano avvertenze di replica. MySQL espone i concetti di hash principalmente tramite **MEMORY** e i meccanismi interni di **adaptive hash**. |
| **Full-text** | Ricerca di token nel testo | MySQL **InnoDB FTS** vs Postgres **`tsvector` + GIN** — parser e profili di manutenzione differenti. |
| **GIN / GiST (Postgres)** | Contenimento JSONB, array, ricerca full-text, alcuni tipi geometrici | Potente; la **creazione dell'indice e il bloat** necessitano di monitoraggio. |
| **Spatial** | Query geografiche | Specifico per motore (**PostGIS** su Postgres; tipi spaziali su MySQL). |

### Indici compositi: ordine delle colonne

Se le query filtrano quasi sempre su `tenant_id`, posizionarlo **per primo** è generalmente corretto:

```sql
-- database/migrations/create_composite_index.sql
-- Ottimale quando le query assomigliano a: WHERE tenant_id = ? AND status = 'open'
CREATE INDEX orders_tenant_status_idx ON orders (tenant_id, status);
```

### Indici di copertura (Covering Indexes)

Se l'indice **contiene tutte le colonne** lette dalla query, il motore può soddisfare la query unicamente dall'indice (**Index Only Scan** in Postgres). Esempio di pattern:

```sql
-- database/migrations/create_covering_index.sql
CREATE INDEX sessions_lookup_idx ON sessions (user_id) INCLUDE (last_seen_at);
```

### Indici parziali / filtrati

Quando un predicato è **stabile e selettivo**, indicizzare solo la parte attiva:

```sql
-- database/migrations/create_partial_index.sql
-- Indice parziale specifico per Postgres
CREATE INDEX invoices_open_due_idx
ON invoices (due_at)
WHERE status = 'open' AND due_at IS NOT NULL;
```

---

<a id="db-logic"></a>
## Perché la logica complessa all'interno del database danneggia i team

Stored procedure, trigger e viste complesse **non sono una fisica malvagia**. Sono scelte di **distribuzione e responsabilità**.

Cosa va spesso storto:
* **Controllo versione** — il codice dell'applicazione viene distribuito tramite Git, CI e pipeline di rilascio; le routine del database vivono **altrove**. Il disallineamento tra rami e ambienti diventa doloroso.
* **Testing** — le regole di business in PHP/Java vengono testate a livello unitario in ambienti familiari; i trigger che mutano le righe durante l'inserimento sono **più difficili da analizzare** in isolamento.
* **Portabilità** — la logica nell'app può spostarsi tra MySQL, Postgres o persino altri vendor; la logica in PL/pgSQL o nelle stored procedure di MySQL **ti vincola a un fornitore**.
* **Osservabilità** — gli stack trace e gli span APM si concentrano sul livello dell'applicazione; catene di trigger profonde si manifestano come **latenze misteriose** a meno che non si utilizzi una strumentazione accurata.

Quando la logica sul lato database ha **ancora** senso:
* **Vincoli (Constraints)** — `CHECK`, chiavi esterne e un'**unicità ben scelta** esprimono invarianti in modo più economico rispetto a sperare che ogni servizio se ne ricordi.
* **Protezioni di idempotenza** — un **indice unico (unique index)** su una chiave di business supera le race condition del tipo \"seleziona e poi inserisci\".

---

<a id="mysql-vs-postgres"></a>
## MySQL vs PostgreSQL nella pratica

Entrambi sono pronti per la produzione. Le differenze emergono quando si assume che siano intercambiabili.

| Argomento | MySQL (InnoDB tipico) | PostgreSQL |
|-----------|------------------------|------------|
| **Modello di memorizzazione** | La **chiave primaria** (clusterizzata) organizza le righe; gli indici secondari puntano alla PK | Tabelle **Heap**; gli indici sono separati; **CLUSTER** è un'operazione di manutenzione |
| **MVCC / rimozione tuple** | Storico di **Undo**; i ritardi di purga (**purge lag**) possono essere rilevanti per transazioni lunghe | **Tuple morte**; il **VACUUM** (autovacuum) è il pane quotidiano a livello operativo |
| **Funzionalità SQL** | SQL di base solido; storicamente più rigido su alcuni aspetti | **CTE** più ricche, **window function**, **LATERAL**, operatori **JSONB**, **tipi intervallo** |
| **Estensioni** | Poche nel core; ecosistema tramite plugin | **PostGIS**, **pgvector**, **Citext**, molti altri |
| **Replica** | Streaming di **binlog** maturo; molte topologie ospitate | Replica **fisica** e **logica**; modello **pubblicazione/sottoscrizione** |

Esempi di query che divergono:

Postgres — **Contenimento JSONB**:
```sql
-- database/queries/postgres_jsonb_containment.sql
SELECT id FROM events WHERE payload @> '{"kind":"purchase"}'::jsonb;
```

MySQL — Estrazione **JSON** (8.x):
```sql
-- database/queries/mysql_json_extract.sql
SELECT id FROM events WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.kind')) = 'purchase';
```

---

<a id="scaling"></a>
## Percorsi di scaling e i problemi che introducono

### Scaling verticale (“macchina più grande”)
Semplice fino a quando la fisica non è d'accordo. Emergono limiti sulla larghezza di banda del disco ed effetti CPU NUMA. Inoltre, si concentra il punto di guasto.

### Repliche di lettura
* **Pro:** alleggeriscono il traffico di **sola lettura**, backup, cloni per reportistica.
* **Contro:** il **ritardo di replica** (replication lag) comporta il rischio che le letture servano dati obsoleti.

### Pool di connessioni (Connection Pooling)
Aprire una sessione TCP+auth per ogni richiesta HTTP non scala. Strumenti come **PgBouncer** (Postgres) o ProxySQL (MySQL) si posizionano tra l'app e il DB.

### Caching (Redis e simili)
Le cache mascherano le chiavi calde (hot key); non risolvono l'**amplificazione di scrittura** sul database primario.

---

<a id="decomposition"></a>
## Decomposizione senza favole

Lo **schema-per-servizio** non è una formula magica — offre semplicemente **meno join accidentali** e un **raggio d'azione di un guasto più limitato**. Tuttavia, introduce **transazioni distribuite** o **saga** quando un'azione dell'utente coinvolge realmente più archivi di dati.

Anti-pattern: **due servizi che scrivono nella stessa tabella** tramite un “database condiviso” — è stato costruito un **monolito distribuito** con salti di rete aggiuntivi.

---

<a id="sharding"></a>
## Sharding: chiavi, problemi tra shard, ribilanciamento

Lo **sharding** suddivide le righe su **più nodi primari** utilizzando una **chiave di sharding (shard key)** (spesso l'ID utente o l'ID tenant).

Cosa migliora:
* I limiti massimi di **throughput delle scritture** per singola macchina diminuiscono quando ciascun shard possiede solo una parte dei dati.
* Il **raggio d'azione di un guasto** (blast radius) può ridursi se un guasto isola solo determinati shard.

Cosa rende le cose difficili:
* **Join tra shard** e **unicità globale** — è necessario coordinamento o regole a livello di applicazione.
* **Ribilanciamento** quando un tenant molto attivo domina uno shard — il resharding è operativamente molto complesso.

---

<a id="common-mistakes"></a>
## Errori comuni

1. **Affidarsi a OFFSET per dataset di grandi dimensioni**: utilizzare la paginazione standard `LIMIT ... OFFSET`, costringendo il motore a scansionare milioni di righe per restituirne solo alcune.
2. **Ignorare l'ordine delle colonne negli indici composti**: inserire una colonna con filtri a intervallo (ad esempio, `created_at`) prima delle colonne con filtri di uguaglianza nelle definizioni degli indici composti.
3. **Pattern di database condiviso nei microservizi**: consentire a più servizi di scrivere sulla stessa tabella, creando un accoppiamento stretto e colli di bottiglia nell'integrazione.
4. **Scrivere logica di business nei trigger**: inserire validazioni e mutazioni complesse nei trigger, aggirando test, CI e tracciamento.

---

<a id="checklist"></a>
## Checklist prima di effettuare lo sharding o acquistare hardware

1. **L'`EXPLAIN` mostra una scansione sequenziale** che un indice corretto potrebbe evitare?
2. **Si stanno generando query N+1** dal livello ORM, indipendentemente dalla velocità del disco?
3. **Il collo di bottiglia è rappresentato dalle connessioni** o dalla CPU sul database — o dalla **contesa sui lock** causata da transaktionen lunghe?
4. **È stato misurato il ritardo di replica** se si spostano le letture sulle repliche?
5. **La crescita del dataset è limitata dalla retention** (archiviazione delle partizioni fredde) in modo più economico rispetto a una nuova topologia?

---

## Riepilogo

I database premiano la **correttezza noiosa** e la **misurazione**. Gli indici e la scelta del motore fanno guadagnare tempo; **l'architettura** — code, modelli di lettura separati e talvolta sharding — fa guadagnare **margine di crescita**.

---

<a id="self-test-quiz"></a>
## Quiz di autovalutazione

### Domanda 1: Perché una query come `SELECT * FROM users ORDER BY created_at DESC LIMIT 1` viene eseguita lentamente su una tabella di 10 milioni di righe anche se `created_at` è indicizzato?
- A) Gli indici non possono essere scansionati in ordine decrescente.
- B) Se la colonna è nullable, il motore del database potrebbe scansionare l'intera tabella alla ricerca di valori NULL.
- C) È necessario utilizzare un covering index con `INCLUDE`.

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta: B**
Se la colonna ordinata è nullable e la query non filtra i valori NULL (ad esempio, `WHERE created_at IS NOT NULL`), il motore potrebbe ricorrere a una scansione completa della tabella per individuare i valori nulli, bypassando la scansione dell'indice.
</details>

---

### Domanda 2: Quale tipo di indice è più adatto per la ricerca di chiavi all'interno di documenti JSON arbitrari in PostgreSQL?
- A) Indice B-Tree.
- B) Indice GIN (Generalized Inverted Index).
- C) Indice Hash.

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta: B**
Gli indici GIN sono progettati specificamente per indicizzare elementi multi-valore come array e strutture JSONB, consentendo query di contenimento veloci.
</details>
