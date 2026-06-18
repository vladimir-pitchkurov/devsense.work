# Aggregazioni e raggruppamenti

Finora abbiamo recuperato solo singole righe. In molti scenari, tuttavia, è necessario analizzare i dati a un livello superiore: trovare lo stipendio medio di un dipendente, contare gli ordini totali o individuare il prodotto più costoso in una categoria specifica.

SQL fornisce potenti **funzioni di aggregazione** e la clausola `GROUP BY` per comprimere migliaia di righe in riepiloghi significativi. In questo capitolo impareremo a raggruppare i dati, a filtrare tali raggruppamenti utilizzando la clausola `HAVING` e a esaminare le differenze di esecuzione tra MySQL e PostgreSQL.

---

## 1. Funzioni di aggregazione

Le funzioni di aggregazione eseguono un calcolo su un insieme di valori e restituiscono un singolo valore.

* **`COUNT()`**: Restituisce il numero di righe.
* **`SUM()`**: Restituisce la somma di valori numerici.
* **`AVG()`**: Restituisce la media di valori numerici.
* **`MIN()`**: Restituisce il valore più piccolo.
* **`MAX()`**: Restituisce il valore più grande.

```sql
-- MySQL & PostgreSQL
-- Calculate count, average price, and max price for all products
SELECT COUNT(*) AS total_products, AVG(price) AS average_price, MAX(price) AS highest_price 
FROM products;
```

### Funzioni di aggregazione e valori `NULL`
È un errore comune pensare che le funzioni di aggregazione includano i valori `NULL` nei loro calcoli.
* La maggior parte delle funzioni di aggregazione (come `SUM`, `AVG`, `MIN`, `MAX`) **ignora completamente i valori NULL**.
* `COUNT(nome_colonna)` conta solo le righe in cui la colonna specificata **non è NULL**.
* `COUNT(*)` conta tutte le righe, comprese quelle con valori `NULL`.

```sql
-- If we have 5 users, and only 3 have a middle name:
SELECT COUNT(*) FROM users;            -- Returns 5
SELECT COUNT(middle_name) FROM users;  -- Returns 3
```

---

## 2. Raggruppare i dati con `GROUP BY`

La clausola `GROUP BY` divide le righe di una tabella in gruppi. Il motore del database applica quindi le funzioni di aggregazione a ciascun gruppo in modo indipendente.

```sql
-- MySQL & PostgreSQL
-- Get average price per product category
SELECT category, AVG(price) AS avg_price 
FROM products 
GROUP BY category;
```

---

## 3. Filtrare i gruppi con `HAVING`

E se volessi trovare solo le categorie in cui il prezzo medio è superiore a $100? Si potrebbe provare a scrivere:
```sql
-- THIS WILL FAIL!
SELECT category, AVG(price) FROM products WHERE AVG(price) > 100 GROUP BY category;
```
Questo tentativo fallisce perché la clausola `WHERE` filtra le righe **prima** che vengano raggruppate e aggregate. Il motore del database non conosce ancora il prezzo medio quando valuta la condizione `WHERE`.

Per filtrare i gruppi, è necessario utilizzare la clausola `HAVING`, che viene eseguita **dopo** il raggruppamento.

| Clausola | Ambito di filtraggio | Può usare aggregazioni? |
| :--- | :--- | :--- |
| **`WHERE`** | Singole righe (prima del raggruppamento). | No |
| **`HAVING`** | Risultati raggruppati (dopo il raggruppamento). | Sì |

```sql
-- MySQL & PostgreSQL
-- Correct way to filter aggregated groups
SELECT category, AVG(price) AS avg_price 
FROM products 
WHERE is_available = true -- 1. Filters rows
GROUP BY category         -- 2. Groups remaining rows
HAVING AVG(price) > 100;  -- 3. Filters groups
```

---

## 4. Differenze principali: MySQL vs. PostgreSQL

### La regola di raggruppamento rigorosa
Lo standard SQL stabilisce che quando si utilizza `GROUP BY`, qualsiasi colonna nell'elenco `SELECT` che non è racchiusa in una funzione di aggregazione **deve** essere dichiarata nella clausola `GROUP BY`.
* **PostgreSQL** applica questa regola in modo rigoroso. Se si seleziona una colonna non presente nella clausola `GROUP BY` (e non funzionalmente dipendente dalle chiavi primarie presenti in `GROUP BY`), Postgres restituisce un errore di sintassi.
* **MySQL** si comporta in modo simile nella modalità SQL predefinita `ONLY_FULL_GROUP_BY`. Tuttavia, se questa modalità è disabilitata, MySQL consente di selezionare colonne non elencate in `GROUP BY`, restituendo il valore di una riga casuale per tali colonne: una fonte frequente di bug.

```sql
-- Violating the strict grouping rule
SELECT category, brand, AVG(price) 
FROM products 
GROUP BY category;
```
* **PostgreSQL**: Fallisce immediatamente con `ERROR: column "products.brand" must appear in the GROUP BY clause...`
* **MySQL**: Fallisce solo se `ONLY_FULL_GROUP_BY` è abilitata. Se disabilitata, restituisce la categoria, un brand casuale da quella categoria e il prezzo medio.

### Aggregazione di stringhe (`GROUP_CONCAT` vs. `string_agg`)
Se si desidera combinare valori di testo provenienti da righe raggruppate in una singola stringa separata da virgole:
* **MySQL** utilizza la funzione `GROUP_CONCAT()`.
* **PostgreSQL** utilizza la funzione `string_agg()` e richiede una sintassi esplicita con la clausola `ORDER BY` se si desidera un ordine specifico.

```sql
-- MySQL: Combines tags per product
SELECT product_id, GROUP_CONCAT(tag_name ORDER BY tag_name SEPARATOR ', ') AS tags
FROM product_tags
GROUP BY product_id;

-- PostgreSQL: Combines tags per product
SELECT product_id, string_agg(tag_name, ', ' ORDER BY tag_name) AS tags
FROM product_tags
GROUP BY product_id;
```

> [!WARNING]
> **La trappola del Null con `AVG()`!**
> Poiché `AVG()` ignora i valori `NULL`, può distorcere le metriche di business. Ad esempio, se si calcola il bonus medio dei dipendenti e 9 dipendenti su 10 hanno un bonus `NULL` (sconosciuto/nessuno), `AVG(bonus)` calcolerà la media basandosi sul singolo dipendente che ha ricevuto il bonus, anziché dividere il totale per 10. Usa `COALESCE(bonus, 0)` all'interno dell'aggregazione per trattare `NULL` come `0`.

> [!TIP]
> **Lo sapevi?**
> È possibile eseguire aggregazioni condizionali combinando `SUM` o `COUNT` con espressioni. Nel moderno PostgreSQL, puoi utilizzare la clausola più pulita `FILTER`:
> `SELECT count(*) FILTER (WHERE price > 100) AS expensive_count FROM products;`
> In MySQL, devi utilizzare un'istruzione `CASE` all'interno della funzione di aggregazione:
> `SELECT SUM(CASE WHEN price > 100 THEN 1 ELSE 0 END) AS expensive_count FROM products;`

---

## 5. Riepilogo e buone pratiche

1. **Mantieni pulita la SELECT**: Assicurati che ogni colonna non aggregata nel tuo elenco `SELECT` sia presente anche nella clausola `GROUP BY` per mantenere la compatibilità tra i diversi motori di database.
2. **`WHERE` vs `HAVING`**: Usa `WHERE` per filtrare le righe grezze prima dell'aggregazione e `HAVING` per filtrare i gruppi aggregati.
3. **Gestisci i valori NULL nelle metriche**: Ricorda che i calcoli aggregati (eccetto `COUNT(*)`) ignorano `NULL`. Racchiudi le colonne in `COALESCE` per impostare un valore predefinito a 0 se necessario.
4. **Impara le funzioni dialettali**: Usa `GROUP_CONCAT` in MySQL e `string_agg` in PostgreSQL quando concateni stringhe su righe raggruppate.
