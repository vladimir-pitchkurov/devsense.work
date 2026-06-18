# Ordinamento, limiti e valori NULL

Recuperare dati in un ordine casuale è raramente utile. Nelle applicazioni reali, è necessario presentare i record ordinati alfabeticamente, numericamente o cronologicamente. Inoltre, i database spesso contengono enormi set di dati, il che rende necessario limitare il volume di righe recuperate (ad esempio, per la paginazione). Infine, è fondamentale comprendere come SQL gestisce i dati mancanti o sconosciuti rappresentati da `NULL`.

In questo capitolo esploreremo come organizzare, limitare e interrogare in modo sicuro i dati utilizzando le clausole `ORDER BY`, `LIMIT` e i confronti con `NULL`, analizzando le differenze nel modo in cui MySQL e PostgreSQL gestiscono queste funzionalità.

---

## 1. Ordinare i risultati con `ORDER BY`

Per impostazione predefinita, i motori di database relazionali restituiscono le righe in un ordine non specificato (spesso basato su come sono memorizzate fisicamente sul disco). Per garantire un ordine specifico, è necessario utilizzare la clausola `ORDER BY`.

### Ordine ascendente e discendente
* **`ASC` (Ascendente)**: Ordina i valori dal più basso al più alto (comportamento predefinito).
* **`DESC` (Discendente)**: Ordina i valori dal più alto al più basso.

```sql
-- MySQL & PostgreSQL
-- Sort products by price, highest first
SELECT name, price FROM products ORDER BY price DESC;
```

### Ordinamento multi-colonna
È possibile ordinare per più colonne. Il motore di database ordina prima in base alla prima colonna e, in caso di valori duplicati, ordina i duplicati in base alla seconda colonna, e così via.
```sql
-- MySQL & PostgreSQL
-- Sort by category alphabetically, and then by price descending within each category
SELECT category, name, price 
FROM products 
ORDER BY category ASC, price DESC;
```

---

## 2. Limitare l'output: `LIMIT` e `OFFSET`

Il recupero di milioni di righe può mandare in crash il server dell'applicazione e bloccare le risorse del database. Per evitare questo problema, è possibile limitare il set di risultati.

* **`LIMIT`**: Specifica il numero massimo di righe da restituire.
* **`OFFSET`**: Salta un numero specifico di righe prima di restituire i risultati (comunemente usato per la paginazione).

```sql
-- MySQL & PostgreSQL
-- Get the second page of products (items 11-20)
SELECT name, price 
FROM products 
ORDER BY price DESC 
LIMIT 10 OFFSET 10;
```

---

## 3. Il mistero dei valori `NULL`

In SQL, `NULL` rappresenta l'assenza di dati, un valore sconosciuto o un attributo mancante. **Non** equivale a una stringa vuota `''` o al numero `0`.

### La trappola della logica a tre valori
Nei linguaggi di programmazione standard, `true` e `false` sono gli unici stati booleani. SQL, tuttavia, utilizza una **logica a tre valori**: `true`, `false` e `unknown` (sconosciuto, rappresentato da `NULL`).

Poiché `NULL` significa "sconosciuto", non è possibile confrontarlo utilizzando operatori standard come `=` o `!=`. Ad esempio:
* Un valore sconosciuto è uguale a 5? **Sconosciuto (`NULL`)**.
* Un valore sconosciuto è uguale a un altro valore sconosciuto? **Sconosciuto (`NULL`)**.

```sql
-- THIS WILL NOT WORK! It returns zero rows.
SELECT * FROM users WHERE middle_name = NULL;
```

### Confronti corretti con NULL
Per verificare se una colonna è vuota o popolata, è necessario utilizzare `IS NULL` o `IS NOT NULL`.
```sql
-- MySQL & PostgreSQL
-- Correct way to find users without a middle name
SELECT email FROM users WHERE middle_name IS NULL;

-- Correct way to find users with a middle name
SELECT email FROM users WHERE middle_name IS NOT NULL;
```

---

## 4. Differenze principali: MySQL vs. PostgreSQL

### Ordinamento dei Null (`NULLS FIRST` vs. `NULLS LAST`)
Quando si ordina una colonna che contiene valori `NULL`, in quale posizione vengono inseriti dal motore di database?
* **MySQL**: Tratta `NULL` come il valore più basso possibile. In ordine ascendente (`ASC`), i valori `NULL` compaiono per primi. In ordine discendente (`DESC`), i valori `NULL` compaiono per ultimi.
* **PostgreSQL**: Tratta `NULL` come il valore più alto possibile. In ordine ascendente (`ASC`), i valori `NULL` compaiono per ultimi. In ordine discendente (`DESC`), i valori `NULL` compaiono per primi.

Tuttavia, PostgreSQL supporta l'override dello standard SQL: `NULLS FIRST` o `NULLS LAST`. MySQL non supporta questa funzionalità in modo nativo.

| Database | Ordine | Posizione NULL predefinita | Override personalizzato |
| :--- | :--- | :--- | :--- |
| **MySQL** | `ASC` | Primo | Nessuno (Richiede una soluzione alternativa) |
| **MySQL** | `DESC` | Ultimo | Nessuno (Richiede una soluzione alternativa) |
| **PostgreSQL** | `ASC` | Ultimo | `ORDER BY price ASC NULLS FIRST` |
| **PostgreSQL** | `DESC` | Primo | `ORDER BY price DESC NULLS LAST` |

#### Soluzione alternativa in MySQL per l'ordinamento dei Null
Per forzare i valori `NULL` alla fine di un ordinamento ascendente in MySQL, è possibile utilizzare il controllo di un'espressione booleana:
```sql
-- MySQL: NULLs sorted last in ascending order
SELECT name, price FROM products ORDER BY price IS NULL ASC, price ASC;
```

### Sintassi `LIMIT` non standard
* **MySQL** supporta una sintassi abbreviata separata da virgole: `LIMIT offset, row_count`.
* **PostgreSQL** non supporta questa sintassi e restituirà un errore di sintassi.

```sql
-- MySQL Only (Shorthand syntax: limit 10 rows, skipping the first 5)
SELECT name FROM products LIMIT 5, 10;

-- PostgreSQL & MySQL Standard (Recommended)
SELECT name FROM products LIMIT 10 OFFSET 5;
```

> [!WARNING]
> **La trappola delle prestazioni di Offset!**
> L'utilizzo di un `OFFSET` elevato (ad es., `LIMIT 10 OFFSET 500000`) costringe il motore del database a scansionare e scartare 500.000 righe prima di restituire le 10 righe richieste. Ciò causa un grave degrado delle prestazioni su tabelle di grandi dimensioni. Per la paginazione profonda, preferire la paginazione basata su cursore (paginazione keyset) utilizzando `WHERE id > last_seen_id LIMIT 10`.

> [!TIP]
> **Lo sapevi?**
> Lo standard SQL definisce `FETCH FIRST n ROWS ONLY` invece di `LIMIT`. Sebbene sia MySQL che PostgreSQL supportino `LIMIT`, PostgreSQL supporta anche lo standard ufficiale:
> `SELECT name FROM products ORDER BY price DESC FETCH FIRST 10 ROWS ONLY;`

---

## 5. Riepilogo e buone pratiche

1. **Usa sempre `ORDER BY` con `LIMIT`**: Senza ordinamento, `LIMIT` restituirà un set di righe casuale a seconda dello stato del database.
2. **Non usare mai `=` con `NULL`**: Usa sempre `IS NULL` o `IS NOT NULL`.
3. **Usa lo standard `LIMIT/OFFSET`**: Evita la sintassi abbreviata di `LIMIT` di MySQL separata da virgole per mantenere le query portabili.
4. **Attenzione all'ordinamento dei Null**: Ricorda che MySQL posiziona i valori `NULL` per primi con `ASC`, mentre PostgreSQL li posiziona per ultimi. Usa i modificatori `NULLS FIRST/LAST` di Postgres o i trucchi di ordinamento `IS NULL` di MySQL quando è richiesto un comportamento esatto.
