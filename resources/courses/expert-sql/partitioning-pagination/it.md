# Partizionamento e paginazione

Quando le tabelle crescono fino a decine o centinaia di milioni di righe, le query standard e le strutture di paginazione semplici iniziano a mostrare limiti prestazionali. L'esecuzione di aggiornamenti, backup e ricerche di indici su set di dati enormi introduce un'elevata latenza del disco. Per scalare, i database adottano due strategie primarie: il **Partizionamento delle tabelle** (suddivisione di una tabella enorme in parti fisiche più piccole a livello interno) e la **Paginazione efficiente** (recupero dei record paginati senza dover scansionare milioni di righe dell'offset).

---

## 1. Partizionamento delle tabelle: regole dichiarative e pruning

Il partizionamento delle tabelle divide una singola tabella logica in più tabelle figlie fisiche (partizioni). La tabella padre funge da interfaccia di instradamento (routing).

### Tipi di partizionamento
1. **Partizionamento per intervalli (Range Partitioning)**: Associa le righe alle partizioni in base a un intervallo di valori (ad es., partizionare una tabella di log per mese).
2. **Partizionamento per elenco (List Partitioning)**: Associa le righe alle partizioni in base a valori di chiave espliciti (ad es., partizionare per codice paese).
3. **Partizionamento per hash (Hash Partitioning)**: Distribuisce le righe su un numero fisso di partizioni utilizzando una funzione modulo di hash. È l'ideale per bilanciare il carico di scrittura.

### Partizionamento dichiarativo in PostgreSQL
A partire dalla versione 10, PostgreSQL supporta il partizionamento dichiarativo. Le partizioni vengono dichiarate utilizzando la clausola `PARTITION BY`.

```sql
-- PostgreSQL: Declarative Range Partitioning
CREATE TABLE app_logs (
    id BIGINT NOT NULL,
    log_date DATE NOT NULL,
    message TEXT
) PARTITION BY RANGE (log_date);

-- Create individual partitions
CREATE TABLE app_logs_y2026m01 PARTITION OF app_logs
    FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');
CREATE TABLE app_logs_y2026m02 PARTITION OF app_logs
    FOR VALUES FROM ('2026-02-01') TO ('2026-03-01');
```

### Sintassi di partizionamento in MySQL
MySQL implementa il partizionamento direttamente all'interno della definizione della tabella, senza richiedere istruzioni separate per le tabelle figlie.

```sql
-- MySQL: InnoDB Range Partitioning
CREATE TABLE app_logs (
    id BIGINT NOT NULL,
    log_date DATE NOT NULL,
    message TEXT,
    PRIMARY KEY (id, log_date)
) ENGINE=InnoDB
PARTITION BY RANGE COLUMNS(log_date) (
    PARTITION p2026m01 VALUES LESS THAN ('2026-02-01'),
    PARTITION p2026m02 VALUES LESS THAN ('2026-03-01')
);
```

### Partition Pruning (Potatura delle partizioni)
Il vantaggio principale del partizionamento è il **Partition Pruning**. L'ottimizzatore di query analizza il filtro della clausola `WHERE` ed esclude le partizioni che non possono contenere righe corrispondenti, evitando scansioni complete di quei file fisici.

```sql
-- Triggering Partition Pruning
EXPLAIN SELECT * FROM app_logs WHERE log_date = '2026-02-15';
-- PostgreSQL plan will only scan 'app_logs_y2026m02'
-- MySQL plan will list partitions: 'p2026m02'
```

> [!WARNING]
> **Restrizioni sulla chiave primaria**
> Sia in MySQL che in PostgreSQL, qualsiasi vincolo di univocità o chiave primaria su una tabella partizionata **DEVE** includere tutte le colonne della chiave di partizionamento. In MySQL, non è possibile avere una `PRIMARY KEY (id)` autonoma se si effettua il partizionamento per `log_date`; deve essere definita come `PRIMARY KEY (id, log_date)`. Ciò evita che i database debbano scansionare tutte le partizioni per imporre l'univocità durante gli inserimenti.

---

## 2. Paginazione: Offset vs. Keyset (Cursore)

Il recupero di elenchi di dati impaginati è un requisito fondamentale per le applicazioni. Tuttavia, l'approccio SQL predefinito può causare gravi colli di bottiglia nelle prestazioni.

### L'inconveniente della paginazione basata su OFFSET
* **Sintassi**: `SELECT * FROM orders ORDER BY created_at DESC LIMIT 10 OFFSET 500000;`
* **Funzionamento interno**: Il motore del database non può saltare direttamente alla riga 500.000. Deve scansionare l'indice, leggere tutte le 500.000 righe precedenti, scartarle e quindi restituire solo le 10 righe successive. Ciò comporta un elevato utilizzo della CPU e dell'I/O del disco.

### Paginazione Keyset (basata su cursore)
* **Sintassi**: Invece degli offset, utilizza gli ultimi valori recuperati per filtrare le query successive.
  ```sql
  -- MySQL & PostgreSQL: Keyset Pagination
  SELECT * FROM orders 
  WHERE created_at < '2026-06-17 10:00:00' 
  ORDER BY created_at DESC 
  LIMIT 10;
  ```
* **Prestazioni**: Con un indice composto su `(created_at, id)`, la query esegue un index seek posizionandosi direttamente al punto di partenza, con tempi di esecuzione pari a $O(1)$ indipendentemente dal numero di pagina.

### Paginazione Keyset multicolonna (ordinamento per campi non univoci)
Se il campo di ordinamento (come `created_at` o `price`) può contenere valori duplicati, è necessario aggiungere una colonna univoca di spareggio (di solito la chiave primaria) per evitare di saltare delle righe.
* **Sintassi di confronto delle tuple (PostgreSQL)**:
  ```sql
  -- PostgreSQL supports row value comparisons natively
  SELECT * FROM orders
  WHERE (created_at, id) < ('2026-06-17 10:00:00', 9876)
  ORDER BY created_at DESC, id DESC
  LIMIT 10;
  ```
* **Espansione logica esplicita (MySQL)**:
  *MySQL 8.0 supporta il confronto dei valori di riga, ma le versioni precedenti o ottimizzatori inefficienti potrebbero eseguirlo in modo non ottimale. Espandilo esplicitamente per sicurezza:*
  ```sql
  -- MySQL Safe Keyset Expansion
  SELECT * FROM orders
  WHERE created_at < '2026-06-17 10:00:00'
     OR (created_at = '2026-06-17 10:00:00' AND id < 9876)
  ORDER BY created_at DESC, id DESC
  LIMIT 10;
  ```

> [!TIP]
> **Paginazione multicategoria con LATERAL JOIN**
> Se hai bisogno di impaginare e recuperare i \"primi N elementi per categoria\" (ad es., i primi 3 prodotti per ciascuna delle 20 categorie), il `GROUP BY` standard non funzionerà. Utilizza un join `LATERAL` (supportato in PostgreSQL 10+ e MySQL 8.0.14+).
> ```sql
> SELECT c.name, p.title, p.price
> FROM categories c
> INNER JOIN LATERAL (
>     SELECT title, price 
>     FROM products 
>     WHERE category_id = c.id 
>     ORDER BY price DESC LIMIT 3
> ) p ON TRUE;
> ```
> *Ciò esegue una sottoquery guidata dall'indice per ciascuna categoria, il che risulta estremamente veloce rispetto alle scansioni complete con le funzioni finestra.*

---

## 3. Matrice di confronto delle funzionalità

| Funzionalità | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Definizione del partizionamento** | Tabelle figlie dichiarative | Definito direttamente sulla tabella padre |
| **Instradamento delle righe (Routing)** | Gestito dal routing del padre | Gestito internamente da InnoDB |
| **Restrizione sulla PK** | La PK deve contenere la chiave di partizionamento | La PK deve contenere la chiave di partizionamento |
| **Confronto del valore di riga** | Ottimizzato nativamente (ad es. `(a, b) > (x, y)`) | Supportato, ma possono verificarsi trappole dell'ottimizzatore |
| **Lateral Join** | Supportato (PostgreSQL 9.3+) | Supportato (MySQL 8.0.14+) |

---

## 4. Riepilogo e buone pratiche

1. **Partiziona per data per l'archiviazione**: Il partizionamento per intervalli (range partitioning) è perfetto per i log delle transazioni. Quando i dati diventano vecchi, puoi eliminare la partizione utilizzando `DROP TABLE nome_partizione` (operazione istantanea) anziché eseguire un `DELETE` massivo (lento, genera bloat nell'undo).
2. **Non usare mai offset elevati**: Implementa la paginazione keyset per gli elenchi a scorrimento infinito (infinite scroll) o per gli elenchi impaginati.
3. **Indicizza le chiavi di paginazione**: Assicurati sempre che i filtri di paginazione del keyset corrispondano a un indice B-Tree composto (ad es., indice su `(created_at, id)`).
