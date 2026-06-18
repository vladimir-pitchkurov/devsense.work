# Piani di esecuzione e tipi di scansione

Per ottimizzare le query lente del database, è essenziale comprendere in quale modo il database recupera i dati. L'ottimizzatore di query valuta molteplici percorsi di esecuzione e crea un **Piano di esecuzione** basato su stime dei costi. Analizzando i piani di esecuzione tramite il comando `EXPLAIN`, puoi identificare i colli di bottiglia, come scansioni complete delle tabelle (full table scan), ordini di join errati o indici che vengono ignorati dal motore. PostgreSQL e MySQL utilizzano terminologie e tecniche di visualizzazione dei piani differenti, ma i loro metodi di scansione sottostanti condividono concetti chiave.

---

## 1. Generare e leggere i piani di esecuzione

Entrambi i database forniscono strumenti per ispezionare il modo in cui vengono eseguite le query.

### PostgreSQL: EXPLAIN e EXPLAIN ANALYZE
In Postgres, `EXPLAIN` restituisce la stima dei costi calcolata dal pianificatore (planner). Aggiungendo `ANALYZE`, costringi il database a eseguire effettivamente la query, fornendo i tempi di esecuzione reali (runtime) e il numero effettivo di righe.
* **Metriche di costo**: Rappresentate nel formato `cost=avvio..totale` (ad es., `cost=0.00..45.10`). Il costo è un'unità relativa (in cui `1.0` rappresenta il costo di lettura sequenziale di una singola pagina).
* **Tempi di esecuzione effettivi**: Indicati in millisecondi.

```sql
-- PostgreSQL: Inspect actual execution stats
EXPLAIN (ANALYZE, BUFFERS, COSTS)
SELECT email FROM users WHERE status = 'active';
```
*L'opzione `BUFFERS` mostra le letture dei blocchi di memoria condivisa (hit e letture dal disco).*

### MySQL: EXPLAIN e EXPLAIN ANALYZE
MySQL ha storicamente utilizzato un formato tabellare per l'output di `EXPLAIN`. Con la versione 8.0 è stato introdotto `EXPLAIN ANALYZE`, che mostra i piani in una struttura ad albero con i costi e i tempi di esecuzione effettivi.

```sql
-- MySQL: Visualizing with Tree structure & actual runtimes
EXPLAIN ANALYZE
SELECT email FROM users WHERE status = 'active';
```

---

## 2. Tipi di scansione in PostgreSQL

PostgreSQL sceglie tra diversi metodi di scansione in base alla presenza di indici, alle dimensioni della tabella e alla selettività dei dati.

```
Selezione della scansione in PostgreSQL:
Selettività:   Alta (1-2 righe)        Media (5-15%)        Bassa (Intera tabella)
Metodo:        [Index Scan]     ->  [Bitmap Scan]     ->  [Seq Scan]
```

### Scansione sequenziale (Seq Scan)
* **Cosa fa**: Legge l'intero file heap della tabella dall'inizio alla fine, valutando la clausola `WHERE` per ciascuna riga.
* **Quando si verifica**: Si attiva quando la query non dispone di un indice corrispondente o quando il pianificatore stima che sia più veloce scansionare la maggior parte della tabella piuttosto che utilizzare un indice.

### Scansione dell'indice (Index Scan)
* **Cosa fa**: Scansiona l'indice B-Tree per individuare gli indirizzi fisici (TID) delle righe corrispondenti, quindi recupera le specifiche pagine dall'heap della tabella.
* **Svantaggio**: Se le righe corrispondenti sono molte, il continuo passaggio tra le pagine dell'indice e le pagine dell'heap genera colli di bottiglia legati all'I/O casuale.

### Bitmap Index Scan e Bitmap Heap Scan
* **Cosa fa**: Viene utilizzato quando Postgres deve recuperare un numero moderato di righe.
  1. Il **Bitmap Index Scan** legge l'indice e crea in memoria una mappa di bit (bitmap) delle pagine dell'heap corrispondenti, ordinando i TID in base all'ordine fisico delle pagine.
  2. Il **Bitmap Heap Scan** legge le pagine ordinate in modo sequenziale, evitando l'I/O casuale e la doppia lettura delle pagine.

### Index Only Scan
* **Cosa fa**: Recupera i dati direttamente dai nodi foglia dell'indice senza dover accedere all'heap della tabella.
* **Il limite della mappa di visibilità**: Postgres deve controllare la **Mappa di visibilità (Visibility Map)** per assicurarsi che le pagine non siano state modificate da transazioni non ancora ripulite dal vacuum. Se una pagina è contrassegnata come "dirty" (sporca), Postgres è costretto ad accedere comunque all'heap, degradando le prestazioni.

---

## 3. Tipi di scansione in MySQL (InnoDB)

L'output di `EXPLAIN` in MySQL utilizza la colonna `type` per descrivere come vengono recuperate le righe. I tipi, ordinati dal più veloce al più lento, includono:

### const / system
* La tabella ha al massimo una riga corrispondente (ad es., interrogazione di una `PRIMARY KEY` o di un indice `UNIQUE` con un valore costante). È un'operazione estremamente veloce.

### eq_ref
* Utilizzato nei join quando MySQL legge una riga da questa tabella per ogni combinazione di righe della tabella precedente (si verifica con chiavi primarie o univoche).

### ref
* Utilizzato quando si cercano corrispondenze con righe su un indice non univoco. Possono corrispondere più righe.

### range
* Utilizza un indice per selezionare un intervallo di righe (ad es., query che utilizzano `>`, `<`, `BETWEEN` o `IN`).

### index (Scansione completa dell'indice)
* MySQL esegue una scansione completa dell'albero dell'indice. Equivale all'Index Only Scan di PostgreSQL. Evita di scansionare lo spazio fisico della tabella, ma legge comunque l'intero indice.

### ALL (Scansione completa della tabella)
* MySQL legge ogni riga della tabella dal disco. È il tipo di accesso più lento e dovrebbe essere evitato per le tabelle di grandi dimensioni.

---

## 4. Matrice di confronto dei tipi di scansione

| Concetto di scansione | Nome in PostgreSQL | Tipo in MySQL (InnoDB) | Descrizione |
| :--- | :--- | :--- | :--- |
| **Scansione completa tabella** | `Seq Scan` | `ALL` | Scansiona l'intera tabella; I/O su disco elevato. |
| **Ricerca tramite indice** | `Index Scan` | `ref` o `range` | Scorre l'indice, poi recupera la riga dal tablespace. |
| **Ricerca solo su indice** | `Index Only Scan` | `index` | Recupera i dati esclusivamente dai nodi foglia dell'indice. |
| **Ricerca indice massiva** | `Bitmap Index/Heap Scan` | N.D. | Raggruppa i TID per pagina per ottimizzare l'accesso al disco. |
| **Ricerca costante** | `Index Scan` (1 riga) | `const` | Ricerca istantanea su un indice univoco. |

---

## 5. Riepilogo e buone pratiche

1. **Usa sempre ANALYZE per dati reali**: Il comando `EXPLAIN` standard mostra solo delle stime. Esegui sempre `EXPLAIN ANALYZE` (in ambienti sicuri) per visualizzare il numero reale di righe e l'utilizzo effettivo della memoria.
2. **Attenzione al degrado di \"Index Only Scan\"**: Se un Index Only Scan in Postgres mostra un numero elevato di letture dall'heap (heap fetches), esegui `VACUUM` sulla tabella per aggiornare la mappa di visibilità.
3. **Evita il tipo `ALL` in MySQL**: Se una query su una tabella di grandi dimensioni mostra `type: ALL` o `Extra: Using join buffer`, aggiungi un indice che copra le colonne utilizzate per la ricerca.
4. **Aggiorna le statistiche delle tabelle**: Se l'ottimizzatore sceglie un tipo di scansione inefficiente, le statistiche della tabella potrebbero essere obsolete. Esegui `ANALYZE TABLE mia_tabella;` in MySQL o `ANALYZE mia_tabella;` in PostgreSQL.
