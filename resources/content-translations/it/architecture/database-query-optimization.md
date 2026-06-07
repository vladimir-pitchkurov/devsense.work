---
title: "Ottimizzazione delle query del database: Master Class | DevSense"
description: "Impara a leggere EXPLAIN ed EXPLAIN ANALYZE, ottimizzare JOIN e CTE, implementare la paginazione keyset e comprendere i comportamenti del carico MVCC dei motori di database."
published: 2026-05-31
faq:
  - question: "Qual è la differenza tra un Sequential Scan e un Index Scan?"
    answer: "Un Sequential Scan legge ogni pagina della tabella dall'inizio alla fine per trovare le righe corrispondenti. Un Index Scan attraversa l'albero dell'indice per individuare le voci corrispondenti e quindi recupera solo le pagine corrispondenti dall'heap. Per tabelle piccole o query che recuperano una grande percentuale di righe, il Seq Scan è spesso più veloce dell'I/O casuale di un Index Scan."
  - question: "Cosa sono Nested Loop, Hash Join e Merge Join?"
    answer: "I Nested Loop uniscono le tabelle scansionando la tabella esterna e cercando le righe corrispondenti nella tabella interna (ottimale per set di piccole dimensioni). Gli Hash Join creano una tabella hash in memoria dalla relazione più piccola e scansionano la relazione più grande per trovare corrispondenze (ottimale per set di grandi dimensioni non ordinati). I Merge Join ordinano entrambe le relazioni sulla chiave di join e le scansionano in parallelo (ottimale per set di grandi dimensioni pre-ordinati o quando le scansioni dell'indice possono evitare l'ordinamento)."
  - question: "Perché la paginazione keyset è più veloce di LIMIT/OFFSET?"
    answer: "Le query LIMIT/OFFSET devono leggere e scartare tutte le righe fino al valore di offset, causando un degrado delle prestazioni di tipo O(N) man mano che le pagine diventano più profonde. La paginazione keyset utilizza un filtro WHERE su una colonna univoca ordinata (come `WHERE id > ?`) per passare direttamente alla riga di destinazione utilizzando un indice, ottenendo una ricerca in tempo costante O(log N)."
  - question: "Cos'è una barriera di ottimizzazione (optimization fence) delle CTE?"
    answer: "Nelle versioni precedenti di Postgres (pre-12), e facoltativamente in quelle più recenti utilizzando `MATERIALIZED`, le Common Table Expressions (CTE) fungono da barriera di ottimizzazione: il database esegue prima la CTE, memorizza il risultato in uno spazio temporaneo e quindi esegue il join. Ciò impedisce all'ottimizzatore di spingere i predicati della query esterna (come le clausole WHERE) all'interno della CTE (push-down), il che può rallentare l'esecuzione."
---

# Ottimizzazione delle query del database: Master Class

Una singola query lenta può compromettere un'intera applicazione aziendale. In produzione, le prestazioni del database dipendono raramente dalla potenza della CPU; dipendono dall'efficienza con cui il pianificatore di query esplora le pagine del disco, crea tabelle di join in memoria e gestisce la concorrenza.

Quando scrivi una query SQL, descrivi *quali* dati desideri, non *come* ottenerli. L'ottimizzatore di query del motore di database è responsabile della pianificazione del piano di esecuzione. Per scrivere applicazioni ad alte prestazioni, devi imparare a pensare come l'ottimizzatore. In questa master class esamineremo i piani di esecuzione, analizzeremo gli algoritmi di join, esploreremo lo scaling della paginazione e analizzeremo i meccanismi MVCC di PostgreSQL e MySQL.

---

## 1. Decodificare EXPLAIN ed EXPLAIN ANALYZE

Per ottimizzare una query, devi prima ispezionare il suo piano di esecuzione utilizzando `EXPLAIN`. Tuttavia, un `EXPLAIN` standard mostra solo la *stima* del costo della query da parte dell'ottimizzatore. Per vedere cosa è realmente accaduto durante l'esecuzione, devi usare `EXPLAIN ANALYZE` (che esegue effettivamente la query).

### Comprendere gli indicatori di costo (formato Postgres)
Un piano di esecuzione mostra i costi nel formato: `cost=0.00..431.25 rows=10500 width=244`.
*   **Costo di avvio (`0.00`):** Il costo sostenuto prima che la prima riga possa essere restituita (ad esempio, la creazione di una tabella hash o l'ordinamento dei nodi dell'indice).
*   **Costo totale (`431.25`):** Il costo stimato per restituire tutte le righe. Questo viene misurato in unità arbitrarie di letture di pagine (in genere `1.0` per la lettura sequenziale delle pagine, `4.0` per la lettura casuale delle pagine).
*   **Righe (`10500`):** Il numero stimato di righe restituite (rows).
*   **Larghezza (`244`):** La dimensione media in byte delle righe restituite (width).

### Tipi di scansione dei nodi
1.  **Sequential Scan (Seq Scan):** Il motore legge l'intera tabella dall'inizio alla fine. È ottimale per tabelle di piccole dimensioni o quando si recupera $> 20\%$ delle righe della tabella.
2.  **Index Scan:** Il motore attraversa il B-Tree dell'indice, recupera i TID/PK corrispondenti e quindi recupera le pagine corrispondenti dall'heap/tabella. Ciò comporta un accesso casuale al disco.
3.  **Index Only Scan:** Se tutte le colonne selezionate sono incluse nell'indice stesso, il database bypassa completamente le letture della tabella.
4.  **Bitmap Index Scan & Bitmap Heap Scan (Postgres):** Quando si recuperano più righe corrispondenti tramite indice, Postgres crea prima una bitmap delle pagine corrispondenti in memoria (Bitmap Index Scan) e poi legge tali pagine in ordine fisico sequenziale (Bitmap Heap Scan). Ciò converte il lento I/O casuale in un I/O sequenziale più veloce.

### Algoritmi di join
*   **Nested Loop:** Il database scansiona la tabella esterna e, per ogni riga, cerca le righe corrispondenti nella tabella interna. Questo è estremamente veloce per set di dati di piccole dimensioni, soprattutto se la colonna di join della tabella interna è indicizzata.
*   **Hash Join:** Il database scansiona la tabella più piccola, crea una tabella hash in memoria dalle chiavi di join e quindi scansiona la tabella più grande, calcolando l'hash delle sue chiavi per trovare corrispondenze immediate. Questo è ottimale per set di dati grandi e non ordinati.
*   **Merge Join:** Entrambe le tabelle vengono ordinate in base alle chiavi di join e il database le scansiona in parallelo, unendo le righe corrispondenti. Questo è molto efficiente per set di dati molto grandi, in particolare se sono già pre-ordinati dagli indici.

---

## 2. Ottimizzazione avanzata dei join e CTE

Non tutti i join sono creati allo stesso modo. Il modo in cui scrivi le sottoquery e le CTE influisce pesantemente sulla capacità dell'ottimizzatore di ridurre i percorsi di esecuzione.

### Inner vs. Outer Join e Anti-Join
Un anti-join trova i record in una tabella che *non* esistono in un'altra. Gli sviluppatori spesso scrivono questa condizione usando `NOT IN`, che ha prestazioni pessime e fallisce se la sottoquery restituisce `NULL`. Un approccio altamente ottimizzato consiste nell'usare `NOT EXISTS`.

```sql
-- database/queries/anti_join_bad.sql
-- Errato: NOT IN scansiona la sottoquery e si comporta in modo imprevisto se sono presenti dei NULL
SELECT id, name FROM users 
WHERE id NOT IN (SELECT user_id FROM orders);

-- database/queries/anti_join_good.sql
-- Corretto: NOT EXISTS si traduce in un Hash Anti Join o Merge Anti Join altamente ottimizzato
SELECT u.id, u.name 
FROM users u
WHERE NOT EXISTS (
    SELECT 1 
    FROM orders o 
    WHERE o.user_id = u.id
);
```

### Join LATERAL (sottoquery correlate)
Un join `LATERAL` agisce come un ciclo `foreach` in SQL. Consente a una sottoquery di fare riferimento a colonne di tabelle precedenti nella clausola `FROM`. Questo è incredibilmente utile per recuperare i record \"Top N\" per ciascun gruppo.

```sql
-- database/queries/lateral_join.sql
-- Recupera le ultime 3 query di ordine per ciascun utente (Postgres & MySQL 8.0.14+)
SELECT u.id, u.name, recent_orders.id AS order_id, recent_orders.amount, recent_orders.created_at
FROM users u
CROSS JOIN LATERAL (
    SELECT o.id, o.amount, o.created_at
    FROM orders o
    WHERE o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 3
) AS recent_orders;
```

### Barriere di ottimizzazione delle CTE
Le Common Table Expressions (CTE) rendono pulito il codice delle query, ma storicamente fungevano da **barriere di ottimizzazione**.
*   **Postgres (Pre-12):** Le CTE venivano sempre materializzate (calcolate e scritte in uno spazio temporaneo) prima di eseguire la query esterna. Ciò impediva l'uso degli indici delle clausole WHERE esterne.
*   **Postgres (12+):** Le CTE vengono ora incorporate (inlined) per impostazione predefinita, a meno che non siano ricorsive o abbiano effetti collaterali. È possibile forzare o impedire esplicitamente la materializzazione utilizzando `MATERIALIZED` o `NOT MATERIALIZED`.

```sql
-- database/queries/cte_fence.sql
-- Impedisce la materializzazione per consentire al pianificatore di query di spingere il predicato user_id verso il basso
WITH user_stats AS NOT MATERIALIZED (
    SELECT user_id, COUNT(*) as total_orders, SUM(amount) as total_spent
    FROM orders
    GROUP BY user_id
)
SELECT u.name, s.total_orders, s.total_spent
FROM users u
JOIN user_stats s ON u.id = s.user_id
WHERE u.id = 42;
```

---

## 3. Scaling dei percorsi delle query: paginazione keyset e partizionamento

Man mano che le tabelle del database crescono fino a miliardi di righe, le query semplici rallentano, a meno che non si modifichi il modo in cui vengono scansionati i percorsi.

### Paginazione Keyset (basata su cursore) vs. LIMIT / OFFSET
La paginazione offset (`LIMIT 10 OFFSET 100000`) costringe il database a leggere e scartare 100.000 righe solo per restituirne 10. Le prestazioni scalano linearmente ($O(N)$), degradando pesantemente sulle pagine profonde.
La paginazione keyset filtra le righe già viste utilizzando una clausola WHERE su un indice ordinato, scalando con un tempo di ricerca costante $O(\log N)$.

```sql
-- database/queries/offset_pagination_bad.sql
-- Errato: Scansiona e scarta 100.000 record prima di restituirne 10
SELECT id, amount, created_at
FROM orders
ORDER BY created_at DESC, id DESC
LIMIT 10 OFFSET 100000;

-- database/queries/keyset_pagination_good.sql
-- Corretto: Salta direttamente all'ultimo keyset visto utilizzando una scansione dell'indice composto
SELECT id, amount, created_at
FROM orders
WHERE (created_at, id) < ('2026-05-30 15:30:00', 987654)
ORDER BY created_at DESC, id DESC
LIMIT 10;
```

> [!NOTE]
> La paginazione keyset richiede un ordinamento deterministico. Se ordini in base a una colonna non univoca come `created_at`, devi aggiungere una colonna univoca come spareggio (es. `id`) per evitare righe mancanti o duplicate.

### Partizionamento delle tabelle
Il partizionamento delle tabelle suddivide una tabella di grandi dimensioni in tabelle fisiche più piccole (partizioni) in base a una chiave (es. intervalli di date), mantenendo un'unica interfaccia logica.
*   **Query Pruning (potatura delle query):** Quando una query filtra in base alla chiave di partizione, il pianificatore ignora completamente le partizioni irrilevanti, scansionando solo quella corrispondente.

```sql
-- database/migrations/2026_05_31_partitioned_orders.sql
-- Partizionamento dichiarativo per intervalli (Range) in PostgreSQL
CREATE TABLE orders_partitioned (
    id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    PRIMARY KEY (created_at, id)
) PARTITION BY RANGE (created_at);

-- Creare le singole tabelle di partizione
CREATE TABLE orders_2026_q1 PARTITION OF orders_partitioned
    FOR VALUES FROM ('2026-01-01 00:00:00') TO ('2026-04-01 00:00:00');
```

---

## 4. MVCC, Vacuuming e Purge Lag

La concorrenza nei database moderni viene ottenuta tramite il **Multi-Version Concurrency Control (MVCC)**. Invece di bloccare le righe per la lettura, i database mantengono simultaneamente più versioni di una riga.

### PostgreSQL MVCC & Autovacuum
In Postgres, ogni riga (tupla) viene memorizzata su disco con intestazioni di metadati che includono `xmin` (l'ID della transazione che ha creato la riga) und `xmax` (l'ID della transazione che ha eliminato/scaduto la riga).
*   **Aggiornamenti (Updates):** Un `UPDATE` non sovrascrive la riga. Scrive una tupla completamente nuova nell'heap e imposta `xmax` sulla vecchia tupla in modo che punti alla transazione di aggiornamento.
*   **Bloat (gonfiamento):** La vecchia versione della riga diventa una \"tupla morta\" (dead tuple) una volta che nessuna transazione attiva può più vederla.
*   **Vacuuming:** Postgres richiede `VACUUM` (gestito dal demone autovacuum) per scansionare le pagine, recuperare lo spazio occupato dalle tuple morte e aggiornare la mappa di visibilità (visibility map). Se l'autovacuum non riesce a stare al passo con carichi di scrittura intensi, il bloat di tabelle e indici compromette le prestazioni delle query.

### MySQL InnoDB MVCC & Undo Log
Il motore InnoDB di MySQL gestisce il MVCC in modo diverso.
*   **Aggiornamenti nell'indice clusterizzato:** InnoDB esegue gli aggiornamenti in-place all'interno del nodo foglia dell'indice clusterizzato.
*   **Undo Log & Rollback Segment:** La vecchia versione della riga non viene memorizzata nella tabella principale. Al contrario, InnoDB scrive lo stato storico della riga nell'**Undo Log** e memorizza un puntatore di rollback all'interno della riga aggiornata. Quando una transazione di lettura necessita di una versione precedente, legge la riga corrente e ricostruisce il vecchio stato utilizzando gli undo log.
*   **Thread di purga (Purge Threads):** Al termine delle vecchie transazioni, un processo in background chiamato **Purge Thread** pulisce gli undo log e cancella i record contrassegnati per l'eliminazione. In caso di carichi di scrittura elevati, può verificarsi un **Purge Lag** (ritardo di purga), che comporta una crescita massiccia del file di undo log e un calo delle prestazioni.

---

## 5. Errori comuni

### Errore 1: Paginazione profonda con OFFSET
**Cattiva pratica:**
```sql
-- database/queries/bad_pagination.sql
SELECT * FROM users ORDER BY id LIMIT 20 OFFSET 50000;
```
*Perché è un errore:* Il database deve scansionare l'indice/heap per 50.000 record, caricarli in memoria e scartarli, consumando CPU e I/O del disco.

**Buena pratica:**
```sql
-- database/queries/good_pagination.sql
-- Paginazione keyset utilizzando l'ultimo ID visto (es. 50000)
SELECT * FROM users WHERE id > 50000 ORDER BY id LIMIT 20;
```
*Perché è corretto:* Il database esegue una ricerca nell'indice per passare direttamente a `id > 50000` in tempo $O(\log N)$, evitando scansioni di righe non necessarie.

### Errore 2: Mancanza di un indice sulle chiavi esterne (Foreign Key)
**Cattiva pratica:**
```sql
-- database/migrations/bad_foreign_key.sql
CREATE TABLE orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```
*Perché è un errore:* PostgreSQL e MySQL non creano automaticamente gli indici sulle colonne delle chiavi esterne. Se si esegue frequentemente il join delle tabelle su `user_id` o si eliminano utenti (il che attiva controlli a cascata), il motore deve eseguire una scansione sequenziale sulla tabella figlio.

---

## 6. Quiz di autovalutazione

### Domanda 1
Durante l'esecuzione di `EXPLAIN ANALYZE` in Postgres, si nota un nodo etichettato come `Bitmap Heap Scan` che segue un `Bitmap Index Scan`. Cosa indica?

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta:**
Indica che Postgres ha stabilito che la lettura diretta delle righe tramite ricerche di indici singoli comporterebbe un I/O casuale troppo lento. Invece:
1. Il `Bitmap Index Scan` ha scansionato l'indice e ha creato una bitmap in memoria degli indirizzi delle pagine della tabella contenenti le righe corrispondenti.
2. Il `Bitmap Heap Scan` ha quindi letto le pagine della tabella in ordine fisico sequenziale, accedendo alle righe corrispondenti su tali pagine. Questo trasforma l'I/O casuale in letture sequenziali del disco, più veloci.
</details>

---

### Domanda 2
In presenza di un carico di scrittura elevato in MySQL InnoDB, perché la dimensione del tablespace dell'undo log si gonfia e in che modo questo influisce sulle query di lettura che richiedono dati più vecchi?

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta:**
Quando le scritture sono molto intensive, il Purge Thread può accumulare ritardo (Purge Lag) nella pulizia dei segmenti di rollback storici. Di conseguenza, lo spazio dell'undo log cresce rapidamente per tenere traccia delle versioni precedenti delle righe. Le transazioni di lettura più vecchie subiranno prestazioni inferiori perché devono scorrere una lunga catena di undo log per ricostruire lo stato dei dati al momento dell'avvio della transazione stessa.
</details>

---

### Domanda 3
Perché una CTE definita con una clausola `WITH` standard in Postgres 10 può causare un collo di bottiglia nelle prestazioni se la query esterna filtra per un ID specifico?

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta:**
In Postgres 10 (e versioni precedenti), le CTE fungono da barriera di ottimizzazione. Il motore valuta la CTE in modo indipendente e materializza i suoi risultati completi in memoria o su disco. Solo a quel punto esegue la query esterna e applica il filtro `WHERE id = 42`. In Postgres 12+ (o scrivendo `WITH ... AS NOT MATERIALIZED`), l'ottimizzatore può incorporare (inline) la CTE, consentendogli di spingere il filtro `WHERE id = 42` all'interno della sottoquery, abilitando scansioni guidate dagli indici.
</details>
