# Funzioni finestra: partizionamento, ordinamento e frame

Immagina di dover creare una dashboard per visualizzare l'elenco delle transazioni dei clienti. Devi mostrare i dettagli delle transazioni, ma vuoi anche visualizzare il totale parziale della spesa per ciascun cliente, la loro posizione (rank) in base all'importo della transazione e l'importo della transazione precedente.

L'uso del `GROUP BY` standard comprime le righe, facendo perdere i dettagli delle singole transazioni. L'implementazione di questa logica a livello di applicazione richiederebbe il recupero di tutti i record e l'esecuzione di cicli di iterazione su di essi, il che è lento e richiede molta memoria. Le **funzioni finestra** (window functions) risolvono questo problema eseguendo calcoli su un insieme di righe della tabella correlate alla riga corrente, senza comprimere il set di risultati.

---

## 1. Il funzionamento di base: Raggruppamento vs. Finestra (Windowing)

A differenza di `GROUP BY`, che aggrega più righe in una singola riga di riepilogo, le funzioni finestra calcolano un'aggregazione o un rango per ciascuna riga singolarmente, preservando tutti i campi di dettaglio.

```
GROUP BY:
[Riga 1] \
[Riga 2]  --> [Riga aggregata]
[Riga 3] /

FUNZIONE FINESTRA:
[Riga 1] --> [Riga 1] [Valore calcolato 1]
[Riga 2] --> [Riga 2] [Valore calcolato 2]
[Riga 3] --> [Riga 3] [Valore calcolato 3]
```

### Sintassi di base
```sql
-- MySQL 8.0+ & PostgreSQL
SELECT 
    employee_id, 
    department_id, 
    salary,
    SUM(salary) OVER(PARTITION BY department_id) AS dept_total_salary
FROM employees;
```
* **`PARTITION BY`**: Divide le righe in gruppi (partizioni) che condividono gli stessi valori. Se omesso, l'intero set di risultati viene trattato come un'unica partizione.
* **`ORDER BY`**: Definisce l'ordine fisico delle righe all'interno di ciascuna partizione. Questo determina come i valori vengono elaborati sequenzialmente.

---

## 2. Funzioni di classificazione (Ranking) e di valore

Le funzioni finestra si suddividono in funzioni di aggregazione (come `SUM` o `AVG`), funzioni di classificazione (ranking) e funzioni di recupero dei valori.

### Classificazione: `ROW_NUMBER()`, `RANK()` e `DENSE_RANK()`
Quando i valori sono identici (pareggi o ties), queste funzioni si comportano in modo diverso:
* **`ROW_NUMBER()`**: Assegna un intero sequenziale unico a partire da 1. I pareggi vengono risolti in modo arbitrario.
* **`RANK()`**: Assegna un rango con la presenza di salti. Se due righe sono a pari merito per il 1° posto, entrambe ottengono il rango 1 e il rango successivo assegnato sarà il 3.
* **`DENSE_RANK()`**: Assegna un rango senza salti. Se due righe sono a pari merito per il 1° posto, entrambe ottengono il rango 1 e il rango successivo assegnato sarà il 2.

| Dipendente | Stipendio | `ROW_NUMBER()` | `RANK()` | `DENSE_RANK()` |
| :--- | :--- | :--- | :--- | :--- |
| Alice | $10.000 | 1 | 1 | 1 |
| Bob | $10.000 | 2 | 1 | 1 |
| Charlie | $8.000 | 3 | 3 | 2 |
| David | $7.000 | 4 | 4 | 3 |

### Funzioni di valore: `LAG()`, `LEAD()` e `FIRST_VALUE()`
* **`LAG(col, offset, predefinito)`**: Accede a un valore di una riga che si trova a un determinato offset fisico *prima* della riga corrente.
* **`LEAD(col, offset, predefinito)`**: Accede a un valore di una riga che si trova a un determinato offset fisico *dopo* la riga corrente.

```sql
-- Fetch current and previous transaction amount to calculate the difference
SELECT 
    transaction_date,
    amount,
    LAG(amount, 1, 0) OVER(ORDER BY transaction_date) AS prev_amount
FROM transactions;
```

---

## 3. Frame della finestra: la finestra scorrevole

Il frame della finestra (window frame) definisce un sottoinsieme dinamico di righe all'interno della partizione, relativo alla riga corrente. Il frame si sposta man mano che il motore di database elabora ciascuna riga.

### Tipi di frame: `ROWS` vs. `RANGE` vs. `GROUPS`
* **`ROWS`**: Conta le righe fisiche rispetto alla riga corrente (ad es., le 5 righe precedenti).
* **`RANGE`**: Conta i valori logici basandosi sulla colonna presente nella clausola `ORDER BY`. Include tutte le righe che condividono gli stessi valori della riga corrente (i pareggi vengono elaborati insieme).
* **`GROUPS`**: Raggruppa i valori duplicati in base alla colonna di ordinamento.

```sql
-- Running Total Frame Example
SUM(amount) OVER(
    PARTITION BY user_id 
    ORDER BY transaction_date
    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
)
```

> [!WARNING]
> **La trappola di `LAST_VALUE()`!**
> Se si scrive `LAST_VALUE(salary) OVER(PARTITION BY dept_id ORDER BY salary)`, si potrebbe pensare di ottenere lo stipendio più alto del dipartimento. Invece, restituirà lo stipendio della riga corrente!
> Questo accade perché quando è presente `ORDER BY`, il frame predefinito è:
> `RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW`
> Per risolvere questo problema, specifica esplicitamente il frame:
> `LAST_VALUE(salary) OVER(PARTITION BY dept_id ORDER BY salary RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING)`

---

## 4. MySQL vs. PostgreSQL: Differenze architetturali

Sebbene entrambi i motori siano conformi agli standard ANSI SQL, differiscono in termini di funzionalità, estensioni di sintassi e prestazioni di esecuzione.

### 1. La clausola `FILTER` (Solo PostgreSQL)
PostgreSQL supporta la clausola `FILTER` con le funzioni finestra di aggregazione, consentendo di aggregare selettivamente le righe senza utilizzare costrutti complessi con `CASE WHEN`.
```sql
-- PostgreSQL Only
SELECT 
    department_id,
    COUNT(employee_id) FILTER (WHERE salary > 5000) OVER(PARTITION BY department_id) AS highly_paid_count
FROM employees;
```
In MySQL, si deve scrivere:
```sql
-- MySQL Equivalent
SELECT 
    department_id,
    SUM(CASE WHEN salary > 5000 THEN 1 ELSE 0 END) OVER(PARTITION BY department_id) AS highly_paid_count
FROM employees;
```

### 2. Esclusioni di frame (Solo PostgreSQL 11+)
PostgreSQL supporta opzioni avanzate di esclusione del frame, consentendo di escludere righe specifiche dal calcolo del frame della finestra.
* `EXCLUDE CURRENT ROW`: Esclude la riga corrente dal frame.
* `EXCLUDE GROUP`: Esclude la riga corrente e tutti i suoi duplicati di ordinamento.
* *MySQL 8.0 non supporta le clausole `EXCLUDE` nella definizione del frame della finestra.*

> [!TIP]
> **Suggerimento per le prestazioni:**
> Le funzioni finestra vengono eseguite durante la fase finale dell'elaborazione della query (dopo `WHERE` e `GROUP BY`). Per ottimizzarle, crea un indice composto che corrisponda alle colonne presenti in `PARTITION BY` e `ORDER BY`. Ciò consente al motore di query di recuperare direttamente i dati ordinati, evitando costose operazioni di ordinamento su file (filesort).

---

## 5. Riepilogo e buone pratiche

1. **Mantieni l'identità delle righe**: Utilizza le funzioni finestra quando hai bisogno di calcoli aggregati affiancati ai campi delle singole righe.
2. **Attenzione ai valori predefiniti**: Ricorda che l'aggiunta di `ORDER BY` modifica automaticamente il frame predefinito della finestra, il che influisce sulle somme cumulative e su funzioni come `LAST_VALUE()`.
3. **Usa gli indici**: Verifica sempre che le chiavi di `PARTITION BY` e `ORDER BY` siano indicizzate per evitare che il database crei tabelle temporanee su disco.
