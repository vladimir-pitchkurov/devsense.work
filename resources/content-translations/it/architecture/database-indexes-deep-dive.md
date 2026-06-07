---
title: "Indici del database: Sotto il cofano e analisi approfondita | DevSense"
description: "Una guida completa per gli sviluppatori su B+Tree in InnoDB, strutture Heap in Postgres, puntatori ai nodi dell'indice, regole dell'indice composto e amplificazione della scrittura."
published: 2026-05-31
faq:
  - question: "Perché InnoDB usa un indice clusterizzato mentre Postgres usa una struttura heap?"
    answer: "InnoDB memorizza i dati delle righe direttamente all'interno dei nodi foglia del B+Tree della chiave primaria per rendere le ricerche della chiave primaria estremamente veloci e mantenere contigui i dati correlati. Postgres memorizza tutti i dati in un file heap e rende tutti gli indici (inclusa la chiave primaria) indici secondari che puntano alle posizioni dell'heap tramite TID. Ciò semplifica gli spostamenti delle righe durante gli aggiornamenti ed evita doppie ricerche per gli indici secondari, ma richiede accessi all'heap per le query sulla chiave primaria."
  - question: "Cos'è un indice GIN e quando dovrei usarlo in Postgres?"
    answer: "Un Generalized Inverted Index (GIN) è progettato per indicizzare valori composti come JSONB o array. Mappa i singoli elementi (chiavi o valori) sui corrispondenti TID, consentendo una corrispondenza altamente efficiente per le query contenenti operatori come `@>` (contiene) o `?` (ha la chiave)."
  - question: "Come si verifica l'amplificazione della scrittura durante la manutenzione degli indici?"
    answer: "L'amplificazione della scrittura si verifica perché ogni INSERT, UPDATE o DELETE richiede al database di aggiornare sia i dati della tabella che tutti gli indici associati. Inoltre, se un inserimento avviene in una pagina B-Tree piena, la pagina deve essere divisa (page split), scrivendo più pagine su disco e causando la frammentazione."
  - question: "Come posso evitare le doppie ricerche negli indici secondari con InnoDB?"
    answer: "Per evitare doppie ricerche (scansione dell'indice secondario seguita da ricerca della chiave primaria nell'indice clusterizzato), è possibile progettare un indice di copertura (covering index) che contenga tutte le colonne richieste dalla query SELECT, consentendo al database di soddisfare interamente la query dall'indice secondario."
---

# Indici del database: Sotto il cofano e analisi approfondita

Ogni query del database che scrivi è una corsa contro l'I/O del disco. Quando il tuo set di dati entra nella RAM, le query sono istantanee. Ma quando i dati crescono fino a milioni di righe e traboccano sul disco, il motore del database deve cercare in ogni singola pagina dell'unità (una scansione sequenziale) o utilizzare una mappa per passare direttamente ai dati di cui ha bisogno. Quella mappa è un indice.

Capire come funzionano gli indici a livello di disco e di pagina distingue gli sviluppatori junior che aggiungono indici alle tabelle in modo casuale, dagli architetti di database che progettano motori di archiviazione auto-ottimizzanti e ad alto rendimento. In questa analisi approfondita, solleveremo lo strato di astrazione di MySQL (InnoDB) e PostgreSQL per vedere come rappresentano gli indici su disco, come viene risolto l'ordine delle colonne composte e il vero costo dell'amplificazione di scrittura.

---

## 1. Sotto il cofano: B+Tree in InnoDB vs. Heap e B-Tree in Postgres

I motori di database non memorizzano le tabelle come semplici array di righe. Le strutturano su disco utilizzando le pagine (in genere 16KB in InnoDB, 8KB in PostgreSQL). Tuttavia, le loro architetture di archiviazione sono fondamentalmente diverse.

### InnoDB: L'indice clusterizzato (B+Tree)
Nel motore InnoDB di MySQL, **la tabella è l'indice**. Più specificamente, la tabella è strutturata come un B+Tree costruito attorno alla chiave primaria (Primary Key). Questo è noto come **indice clusterizzato** (Clustered Index).

*   **Nodi interni (Internal Nodes):** Contengono solo chiavi e puntatori alle pagine figlie. Guidano il motore durante le ricerche.
*   **Nodi foglia (Leaf Nodes):** Contengono le righe di dati effettive. Le pagine foglia sono collegate sequenzialmente in una lista doppiamente concatenata, rendendo le scansioni di intervalli (`WHERE id BETWEEN 10 AND 50`) incredibilmente veloci.
*   **Indici secondari (Secondary Indexes):** Qualsiasi indice diverso dalla chiave primaria in InnoDB è un indice secondario. I nodi foglia di un indice secondario *non* puntano a indirizzi fisici sul disco. Al contrario, memorizzano il **valore della chiave primaria** della riga.

> [!NOTE]
> Poiché gli indici secondari di InnoDB memorizzano la chiave primaria, la ricerca di una riga tramite un indice secondario (ad esempio, `WHERE email = 'user@example.com'`) richiede una ricerca in due fasi: prima si attraversa l'indice secondario per trovare la chiave primaria, quindi si attraversa il B+Tree dell'indice clusterizzato per recuperare i dati della riga.

```
[Secondary Index (Email)]
  Leaf Node: 'user@example.com' -> PK: 42
          |
          v
[Clustered Index (ID)]
  Leaf Node: PK: 42 -> Row Data: {id: 42, email: 'user@example.com', name: 'John Doe'}
```

### PostgreSQL: L'Heap e il B-Tree
PostgreSQL non utilizza indici clusterizzati per impostazione predefinita. Al contrario, memorizza i dati delle righe in una struttura non ordinata chiamata **Heap** (accumulo).

*   **Pagine Heap:** Le righe della tabella vengono aggiunte alle pagine nell'ordine in cui vengono inserite (o dove c'è spazio disponibile).
*   **Indici B-Tree:** Ogni indice in Postgres (inclusa la chiave primaria) è un indice secondario.
*   **Nodi foglia:** I nodi foglia di un indice B-Tree di Postgres contengono un **Tuple Identifier (TID)**, che è un indirizzo fisico sul disco composto da un numero di blocco (pagina) e un indice di offset all'interno di quella pagina (ad esempio, `(Page 14, Offset 3)`).

> [!WARNING]
> Poiché gli indici di Postgres memorizzano TID fisici, qualsiasi operazione che sposti una riga su disco (come un UPDATE che modifica la dimensione della riga o una migrazione di pagina MVCC) interromperebbe questi TID. Postgres gestisce questo problema tramite il vacuuming e l'ottimizzazione HOT (Heap Only Tuples), ma significa che tutti gli indici puntano direttamente all'Heap.

```
[Postgres Index (Email)]                         [Postgres Heap (Unordered)]
  Leaf: 'user@example.com' -> TID: (Page 14, Offset 3) ----> Page 14, Slot 3: {id: 42, ...}
```

---

## 2. Regole sull'ordine delle colonne negli indici composti

Quando si crea un indice su più colonne — un **indice composto** — l'ordine delle colonne nella dichiarazione dell'indice è fondamentale. Un ordine errato renderà l'indice completamente inutile per determinate query.

Le regole d'oro per la progettazione di indici composti sono:
1.  **La regola del prefisso più a sinistra (Leftmost Prefix Rule):** Il database può utilizzare un indice composto solo se la query filtra prima sulla colonna più a sinistra dell'indice. Un indice su `(A, B, C)` può ottimizzare le query che filtrano su `(A)`, `(A, B)` e `(A, B, C)`, ma *non* può ottimizzare le query che filtrano solo su `(B)` o `(C)`.
2.  **Uguaglianza per prima, intervallo per ultimo (Equality First, Range Last):** Le colonne filtrate con uguaglianza esatta (`=`, `IN`) devono venire prima nell'indice. Le colonne filtrate con intervalli (`<`, `>`, `BETWEEN`, `LIKE`) devono venire per ultime. Una volta valutata una colonna a intervallo, il database non può utilizzare le colonne successive dell'indice per il filtraggio.
3.  **Alta cardinalità per prima:** Le colonne con alta cardinalità (molti valori univoci, ad esempio `user_id`) dovrebbero generalmente precedere le colonne con bassa cardinalità (pochi valori univoci, ad esempio `status`), a condizione che vengano interrogate entrambe con uguaglianza.

Vediamo una migrazione e una query concrete:

```sql
-- database/migrations/2026_05_31_create_orders_table.sql
CREATE TABLE orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    status VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP NOT NULL
);

-- database/migrations/2026_05_31_add_composite_index.sql
-- Layout ottimale dell'indice per: user_id (uguaglianza) + status (uguaglianza) + created_at (intervallo/ordinamento)
CREATE INDEX idx_orders_user_status_created ON orders (user_id, status, created_at);
```

Per la query seguente, l'indice consente al motore di filtrare immediatamente per `user_id`, quindi per `status` e quindi di leggere i valori pre-ordinati di `created_at` in ordine inverso senza richiedere un'operazione filesort separata.

```sql
-- database/queries/find_user_orders.sql
SELECT id, user_id, status, created_at, amount
FROM orders
WHERE user_id = 42
  AND status = 'completed'
ORDER BY created_at DESC
LIMIT 10;
```

---

## 3. Analisi approfondita dei tipi di indici

Pattern di query diversi richiedono strutture dati diverse. Di seguito è riportato un confronto tra i tipi di indici disponibili in MySQL e PostgreSQL.

### B-Tree
Il cavallo di battaglia dei motori di database. Supporta l'uguaglianza (`=`), gli intervalli (`>`, `<`, `BETWEEN`) e l'ordinamento (`ORDER BY`). I B-Tree rimangono bilanciati, garantendo che le operazioni di ricerca, inserimento e cancellazione vengano eseguite in tempo $O(\log n)$.

### Hash
Gli indici hash memorizzano un hash del valore indicizzato e puntano alla riga corrispondente.
*   **Postgres:** Supporta indici Hash espliciti. Sono estremamente veloci ($O(1)$) per le ricerche di uguaglianza, ma non supportano query a intervalli, ordinamenti o corrispondenze parziali su più colonne.
*   **MySQL (InnoDB):** Non supporta la creazione esplicita di indici Hash. Al contrario, utilizza un **Adaptive Hash Index** — una funzionalità interna in cui InnoDB monitora automaticamente i pattern di query sui B-Tree e compila tabelle hash in memoria per le ricerche altamente frequenti.

### GIN (Generalized Inverted Index) - Postgres
Il GIN è progettato per indicizzare valori composti in cui è necessario cercare elementi *all'interno* del valore (come documenti JSONB o Array). Il GIN mappa i singoli elementi all'interno del documento sui rispettivi TID di riga fisici.

```sql
-- database/migrations/2026_05_31_postgres_features.sql
-- Creare una tabella con JSONB e aggiungere un indice GIN in PostgreSQL
CREATE TABLE user_profiles (
    id BIGINT PRIMARY KEY,
    metadata JSONB NOT NULL
);

CREATE INDEX idx_user_metadata_gin ON user_profiles USING GIN (metadata);

-- Questa query utilizza l'indice GIN per trovare istantaneamente le chiavi corrispondenti
SELECT * FROM user_profiles 
WHERE metadata @> '{"role": "administrator", "status": "active"}';
```

### Indici parziali / filtrati
Un indice parziale contiene solo un sottoinsieme delle righe di una tabella, definito da una condizione `WHERE`. Questo consente di risparmiare una quantità enorme di spazio su disco e mantiene l'indice piccolo e veloce.
*   **Postgres:** Supporta nativamente gli indici parziali.
*   **MySQL:** Non supporta direttamente gli indici parziali (a partire dalla versione 8.0). È necessario utilizzare indici funzionali con espressioni che restituiscono `NULL` per ottenere un effetto simile, il che è meno elegante.

```sql
-- database/migrations/2026_05_31_partial_index.sql
-- Solo Postgres: Indicizza solo gli ordini attivi non pagati per mantenere l'indice compatto
CREATE INDEX idx_orders_active_unpaid ON orders (user_id) 
WHERE status = 'pending' AND amount > 100.00;
```

### Indici di espressione / funzionali
È possibile indicizzare il risultato di una funzione o di un'espressione anziché il valore non elaborato della colonna. Ciò è molto utile quando le query eseguono manipolazioni sulle colonne nella clausola `WHERE`.

```sql
-- database/migrations/2026_05_31_functional_index.sql
-- Creare un indice di espressione per ottimizzare le ricerche che non fanno distinzione tra maiuscole e minuscole
-- PostgreSQL:
CREATE INDEX idx_orders_lower_status ON orders (LOWER(status));

-- MySQL 8.0+:
CREATE INDEX idx_orders_lower_status ON orders ((LOWER(status)));
```

### Indici di copertura (Covering Indexes)
Un indice di copertura è un indice secondario che contiene tutte le colonne richieste da una query, consentendo al database di soddisfare interamente la query dalla pagina dell'indice senza toccare i dati della tabella (Heap o Indice clusterizzato).
*   In Postgres, puoi aggiungere esplicitamente colonne di payload non chiave a un indice utilizzando la clausola `INCLUDE`.
*   In MySQL, aggiungi semplicemente le colonne alle chiavi dell'indice composto.

```sql
-- database/migrations/2026_05_31_covering_index.sql
-- Postgres: L'indice è ordinato per user_id, ma trasporta il payload amount/created_at
CREATE INDEX idx_orders_covering ON orders (user_id) INCLUDE (amount, created_at);
```

---

## 4. Amplificazione della scrittura e costo di manutenzione

Gli indici non sono gratuiti. Ogni indice aggiunto a una tabella influisce negativamente sulle prestazioni di scrittura. Questa penalità si manifesta come **amplificazione della scrittura** (Write Amplification).

### Page Split nei B+Tree
Le pagine del database vengono allocate con spazio libero (fillfactor) per consentire aggiornamenti futuri. Quando si inserisce una riga, il database deve scriverla nel nodo dell'indice B-Tree appropriato. Se la pagina del nodo dell'indice è piena, si verifica un **Page Split** (divisione della pagina):
1.  Viene allocata una nuova pagina.
2.  Circa il 50% delle chiavi della pagina piena viene spostato nella nuova pagina.
3.  La pagina padre viene aggiornata con un puntatore alla nuova pagina.

Questa operazione richiede più scritture su disco e causa la frammentazione dell'indice, riducendo le prestazioni delle letture sequenziali.

### Vacuuming vs. Purge Lag
Quando le righe vengono aggiornate o eliminate, lo spazio occupato dai vecchi record non può essere immediatamente riutilizzato a causa del MVCC (Multi-Version Concurrency Control).

*   **Postgres (Autovacuum):** Quando si verifica un UPDATE, Postgres scrive una nuova tupla nell'heap e aggiorna gli indici per fare in modo che puntino ad essa. Ciò lascia una \"tupla morta\" (dead tuple) nella pagina dell'heap. Se il demone autovacuum non riesce a stare al passo con la pulizia di queste tuple morte, la tabella e i suoi indici si gonfiano (bloat), provocando un grave degrado delle prestazioni della memoria e del disco.
*   **MySQL InnoDB (Purge Lag):** In InnoDB, gli aggiornamenti vengono eseguiti in-place nell'indice clusterizzato e le vecchie versioni vengono scritte nel log di Undo. Tuttavia, gli indici secondari vengono modificati contrassegnando il vecchio record come \"eliminato\" e inserendo il nuovo record. Un thread in background chiamato **Purge Thread** si occupa di rimuovere questi record di indici secondari contrassegnati come eliminati. Se le scritture sono troppo intense, il ritardo di purga (**Purge Lag**) cresce, consumando spazio di archiviazione e rallentando le query.

---

## 5. Errori comuni

### Errore 1: Ordine errato delle colonne negli indici composti
**Cattiva pratica:**
Lo sviluppatore si aspetta che l'indice ottimizzi le query su entrambe le colonne, ma ordina per prima la colonna della query a intervallo.
```sql
-- database/queries/bad_composite_order.sql
-- Indice definito come: (created_at, user_id)
CREATE INDEX idx_bad_order ON orders (created_at, user_id);

-- Query:
SELECT * FROM orders 
WHERE created_at >= '2026-01-01 00:00:00' 
  AND user_id = 42;
```
*Perché è un errore:* Il filtro a intervallo su `created_at` impedisce al database di utilizzare la parte `user_id` dell'indice per individuare le righe corrispondenti. Il motore deve scansionare l'indice per tutti i record successivi alla data, controllando ciascuno per verificare l'ID utente.

**Buona pratica:**
```sql
-- database/queries/good_composite_order.sql
-- Indice definito come: (user_id, created_at)
CREATE INDEX idx_good_order ON orders (user_id, created_at);

-- Query:
SELECT * FROM orders 
WHERE user_id = 42 
  AND created_at >= '2026-01-01 00:00:00';
```
*Perché è corretto:* Il filtro di uguaglianza esatta su `user_id` consente al database di passare istantaneamente ai record dell'utente e quindi di utilizzare i nodi foglia ordinati dell'indice `created_at` per scansionare l'intervallo.

### Errore 2: Indicizzazione di colonne a bassa cardinalità
**Cattiva pratica:**
Aggiungere un indice su una colonna booleana (ad esempio, `is_active` o `has_paid`).
```sql
-- database/queries/bad_low_cardinality.sql
CREATE INDEX idx_orders_active ON orders (status); -- status ha solo 'pending', 'completed'
```
*Perché è un errore:* Se i valori dello stato sono distribuiti in modo uniforme, l'indice non è utile. L'ottimizzatore delle query valuterà il costo dell'attraversamento dell'indice secondario e l'esecuzione di letture di I/O casuali sull'indice clusterizzato/heap, decidendo che una scansione sequenziale della tabella sia più veloce. L'indice rimane inutilizzato ma riduce comunque le prestazioni di scrittura.

---

## 6. Quiz di autovalutazione

Metti alla prova la tua comprensione delle architetture degli indici:

### Domanda 1
Supponiamo di avere un indice composto `idx_test (A, B, C)` su una tabella. Quale delle seguenti query **NON** sarà in grado di utilizzare l'indice?
1. `WHERE A = 1 AND B = 2`
2. `WHERE B = 2 AND C = 3`
3. `WHERE A = 1 AND C = 3`

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta: Query 2 (`WHERE B = 2 AND C = 3`)**

**Spiegazione:**
Secondo la regola del prefisso più a sinistra, la query deve filtrare sulla prima colonna dell'indice (`A`) per poterlo utilizzare. Poiché la Query 2 non filtra su `A`, il database non può scorrere i nodi radice del B-Tree e deve eseguire una scansione completa della tabella. La Query 3 *può* utilizzare l'indice, ma solo la parte `A`; individuerà i record in cui `A = 1` e quindi li filtrerà manualmente per `C = 3`.
</details>

---

### Domanda 2
Perché l'aggiornamento di una colonna non indicizzata in PostgreSQL a volte attiva l'amplificazione della scrittura dell'indice, mentre in MySQL InnoDB non lo fa?

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta:**
A causa della differenza tra le strutture Heap e Clusterizzate.
*   In PostgreSQL, se un UPDATE non può eseguire un'ottimizzazione HOT (Heap Only Tuples) (ad esempio, se non c'è spazio sulla pagina), viene scritta una nuova versione della riga su una pagina diversa. Questo cambia l'indirizzo fisico (TID) della riga. Di conseguenza, *tutti* gli indici su quella tabella devono essere aggiornati per puntare al nuovo TID, causando l'amplificazione della scrittura dell'indice.
*   In MySQL InnoDB, la riga rimane nello stesso nodo foglia dell'indice clusterizzato (a meno che non venga aggiornata la chiave primaria stessa). Gli indici secondari puntano al valore della chiave primaria, non a un indirizzo fisico sul disco, il che significa che i loro nodi foglia non devono essere aggiornati.
</details>

---

### Domanda 3
Hai una tabella con 10 milioni di righe. Esegui la query seguente:
`SELECT user_id, status FROM orders WHERE user_id = 100500;`
L'indice su `user_id` è definito come: `CREATE INDEX idx_user ON orders (user_id);`
Come puoi ottimizzare questa query per evitare qualsiasi ricerca nella pagina dei dati della tabella?

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta:**
Crea un **covering index** che includa `status`.
*   In PostgreSQL: `CREATE INDEX idx_user_status ON orders (user_id) INCLUDE (status);`
*   In MySQL (o PostgreSQL): `CREATE INDEX idx_user_status ON orders (user_id, status);`

**Spiegazione:**
Aggiungendo `status` all'indice, tutte le colonne interrogate nelle clausole `SELECT` e `WHERE` sono interamente contenute all'interno della pagina dell'indice. Il motore del database può eseguire un **Index Only Scan**, bypassando completamente la ricerca nell'heap o nell'indice clusterizzato.
</details>
