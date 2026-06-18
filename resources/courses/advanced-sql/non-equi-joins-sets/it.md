# Non-equi join e operazioni sugli insiemi

La maggior parte dei tutorial su SQL si concentra principalmente sull'unione di tabelle che utilizzano l'operatore di uguaglianza (`ON a.id = b.id`). Tuttavia, i problemi reali nei database spesso richiedono l'associazione di record basati su intervalli, disuguaglianze o l'unione di set di dati mediante la teoria matematica degli insiemi.

Comprendere i **non-equi join** e le **operazioni avanzate sugli insiemi** è ciò che distingue gli sviluppatori SQL junior dagli ingegneri di database senior.

---

## 1. Non-equi join: oltre l'uguaglianza

Un **non-equi join** è una condizione di join che utilizza operatori diversi dal segno di uguale (`=`), come `<`, `>`, `<=`, `>=`, `BETWEEN` o `!=`.

### Caso d'uso: intervalli di date sovrapposti
Immagina di gestire le prenotazioni delle camere di un hotel. Devi verificare se una nuova richiesta di prenotazione si sovrappone a prenotazioni già esistenti.
* **Logica**: Si verifica una collisione se la data di inizio richiesta è antecedente alla data di fine di una prenotazione esistente, E la data di fine richiesta è successiva alla data di inizio della prenotazione esistente.

```sql
-- MySQL & PostgreSQL
SELECT 
    b1.room_id,
    b1.booking_id AS booking_1,
    b2.booking_id AS booking_2
FROM bookings b1
JOIN bookings b2 ON b1.room_id = b2.room_id
    AND b1.booking_id < b2.booking_id -- Avoid self-matching and duplicate pairs
    AND b1.start_date < b2.end_date 
    AND b1.end_date > b2.start_date;
```

### Caso d'uso: raggruppamento per intervalli (es. fasce di prezzo)
È possibile assegnare i prodotti a specifiche fasce di prezzo senza dover cablare (hardcodare) i livelli o utilizzare cicli nidificati complessi.

```sql
-- MySQL & PostgreSQL
SELECT 
    p.product_name, 
    p.price, 
    t.tier_name
FROM products p
JOIN price_tiers t ON p.price BETWEEN t.min_price AND t.max_price;
```

> [!WARNING]
> **Problema di prestazioni con i non-equi join!**
> Sebbene i moderni pianificatori di query utilizzino efficienti **Hash Join** per gli equi-join, non possono utilizzarli per le condizioni non-equi. Al loro posto, ricorrono a **Nested Loop Join** o **Block Nested Loop**. Ciò può comportare una complessità temporale di tipo `O(N * M)`.
> In PostgreSQL, puoi mitigare questo problema utilizzando i **Tipi intervallo (Range Types)** e gli **Indici GiST**. MySQL non dispone di tipi intervallo nativi, il che significa che dovrai ottimizzare attentamente gli indici B-Tree composti.

---

## 2. Operazioni avanzate sugli insiemi

Le operazioni sugli insiemi combinano i risultati di due o più query in un unico set di risultati.

```
Operazioni sugli insiemi:
[Query 1] UNION [Query 2]      --> Restituisce tutte le righe univoche di entrambe le query.
[Query 1] INTERSECT [Query 2]  --> Restituisce le righe presenti in ENTRAMBE le query.
[Query 1] EXCEPT [Query 2]     --> Restituisce le righe della Query 1 ma NON della Query 2.
```

### `UNION` vs. `UNION ALL`
* **`UNION`**: Unisce i set di risultati e rimuove i duplicati. Per fare ciò, il database deve ordinare i dati o creare una tabella hash temporanea, il che comporta un costo in termini di prestazioni.
* **`UNION ALL`**: Unisce i set di risultati ma conserva tutti i duplicati. Non esegue alcun ordinamento o deduplicazione, risultando molto più veloce.

### `INTERSECT` e `EXCEPT` (con e senza `ALL`)
Lo standard SQL definisce due modalità per le operazioni sugli insiemi:
1. **Predefinita (Distinct)**: Rimuove le righe duplicate prima di restituire i risultati.
2. **`ALL`**: Preserva la cardinalità dei duplicati. Ad esempio, se una riga appare 3 volte nella Query 1 e 2 volte nella Query 2:
   - `INTERSECT ALL` la restituisce `MIN(3, 2) = 2` volte.
   - `EXCEPT ALL` la restituisce `3 - 2 = 1` volta.

---

## 3. MySQL vs. PostgreSQL: compatibilità e sintassi

Questo è uno degli ambiti in cui i due motori di database divergono in modo significativo, in particolare per quanto riguarda le versioni meno recenti.

### Tabella di supporto per le operazioni sugli insiemi

| Operatore | PostgreSQL (Tutte le versioni) | MySQL 8.0.31+ | MySQL < 8.0.31 |
| :--- | :--- | :--- | :--- |
| `UNION` / `UNION ALL` | Supportato nativamente | Supportato nativamente | Supportato nativamente |
| `INTERSECT` (Distinct) | Supportato nativamente | Supportato nativamente | *Non supportato* |
| `EXCEPT` (Distinct) | Supportato nativamente | Supportato nativamente | *Non supportato* |
| `INTERSECT ALL` | Supportato nativamente | *Non supportato* | *Non supportato* |
| `EXCEPT ALL` | Supportato nativamente | *Non supportato* | *Non supportato* |

### Soluzioni alternative per MySQL (Simulazione)

Se utilizzi versioni di MySQL precedenti alla 8.0.31, devi simulare `INTERSECT` ed `EXCEPT` utilizzando join o sottoquery.

#### Simulare `INTERSECT`:
```sql
-- MySQL < 8.0.31 Equivalent of INTERSECT
SELECT DISTINCT a.email 
FROM users_a a
INNER JOIN users_b b ON a.email = b.email;
```

#### Simulare `EXCEPT`:
```sql
-- MySQL < 8.0.31 Equivalent of EXCEPT
SELECT DISTINCT a.email 
FROM users_a a
LEFT JOIN users_b b ON a.email = b.email
WHERE b.email IS NULL;
```

> [!TIP]
> **Esclusiva PostgreSQL: Vincoli di esclusione**
> In PostgreSQL, puoi imporre che due righe in una tabella non si sovrappongano utilizzando un vincolo `EXCLUSION CONSTRAINT` con un indice GiST.
> ```sql
> -- PostgreSQL Only: Prevent overlapping bookings at the database schema level
> ALTER TABLE bookings ADD CONSTRAINT no_overlap 
> EXCLUDE USING gist (room_id WITH =, tsrange(start_date, end_date) WITH &&);
> ```
> *In MySQL, l'imposizione di questa regola richiede trigger personalizzati BEFORE INSERT/UPDATE.*

---

## 4. Riepilogo e buone pratiche

1. **Preferisci `UNION ALL` rispetto a `UNION`**: A meno che tu non debba esplicitamente escludere i duplicati, usa sempre `UNION ALL` per evitare il sovraccarico dell'ordinamento nel database.
2. **Presta attenzione ai Join**: Quando scrivi dei non-equi join, verifica il piano di esecuzione della query (`EXPLAIN`) per assicurarti che il database non stia eseguendo un lento loop nidificato (nested loop) su milioni di righe.
3. **Gestisci la compatibilità**: Se scrivi query per applicazioni cross-database, evita l'uso di `INTERSECT`/`EXCEPT` nativi o di sintassi che richiedono simulazioni complesse. Utilizza invece strutture basate su `EXISTS` e `LEFT JOIN`.
