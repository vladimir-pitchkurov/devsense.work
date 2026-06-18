# Common Table Expressions (CTE) e ricorsione

Immagina di dover eseguire il debug di una query SQL di 200 righe piena di sottoquery nidificate a più livelli, in cui la stessa sottoquery viene duplicata tre volte solo per eseguire dei self-join. È un incubo a livello di manutenzione e gli analizzatori dei piani di query dei database fanno fatica a ottimizzarla. Oppure pensa a come rappresentare un organigramma aziendale (manager e subordinati) o un albero delle categorie di prodotti a più livelli. In linguaggi imperativi come PHP o JavaScript, recupereresti tutte le righe ed eseguiresti cicli ricorsivi. Tuttavia, fare questo via rete è lento e altamente inefficiente. È qui che le **Common Table Expressions (CTE)** e le **CTE ricorsive** corrono in nostro aiuto.

Una CTE funge da set di risultati temporaneo e nominato che esiste solo all'interno dell'ambito di esecuzione di una singola query. Funziona come una vista inline dinamica e leggibile.

---

## 1. CTE non ricorsive e sequenziali

### Sintassi e struttura
* **Punto**: Le CTE consentono di definire set di risultati temporanei utilizzando la clausola `WITH` prima dell'istruzione principale `SELECT`, `INSERT`, `UPDATE` o `DELETE`.
* **Perché è importante**: Suddivide query complesse in passaggi logici e leggibili, sostituendo le sottoquery nidificate e rendendo il codice auto-documentato.
* **Esempio**:
  ```sql
  -- MySQL & PostgreSQL
  WITH regional_sales AS (
      SELECT region, SUM(amount) AS total_sales
      FROM orders
      GROUP BY region
  ),
  top_regions AS (
      SELECT region
      FROM regional_sales
      WHERE total_sales > 100000
  )
  SELECT o.employee_id, o.amount, o.region
  FROM orders o
  JOIN top_regions t ON o.region = t.region;
  ```
* **Conseguenza**: La query viene letta linearmente dall'alto verso il basso. Si evita di duplicare le sottoquery e il debug diventa semplice come selezionare i dati da una singola CTE.

> [!TIP]
> **Lo sapevi?**
> Le CTE possono essere utilizzate per isolare le operazioni di scrittura. In PostgreSQL, puoi scrivere dati in una CTE e selezionare gli ID risultanti per inserirli in un'altra tabella all'interno della stessa query.
> ```sql
> -- PostgreSQL Only: Writing in a CTE
> WITH inserted_user AS (
>     INSERT INTO users (name, email)
>     VALUES ('Alice', 'alice@devsense.work')
>     RETURNING id
> )
> INSERT INTO profiles (user_id, bio)
> SELECT id, 'Software Engineer' FROM inserted_user;
> ```
> *MySQL non supporta le istruzioni di modifica dei dati (INSERT/UPDATE/DELETE) all'interno delle CTE.*

---

## 2. CTE ricorsive (`WITH RECURSIVE`)

Quando è necessario scorrere strutture di dati gerarchiche, i join SQL standard falliscono perché la profondità dell'albero è sconosciuta. Le **CTE ricorsive** risolvono questo problema eseguendo ripetutamente una query fino a quando non vengono restituite nuove righe.

### L'anatomia della ricorsione
Una CTE ricorsiva si compone di tre parti:
1. **Membro ancora (Anchor Member)**: La query di base che inizializza il set di risultati (viene eseguita una sola volta).
2. **Membro ricorsivo (Recursive Member)**: La query che fa riferimento alla CTE stessa e viene unita al risultato del passaggio precedente.
3. **Condizione di terminazione**: Si attiva implicitamente quando il membro ricorsivo restituisce zero righe.

```
Flusso di esecuzione:
[Query ancora] ---> Righe iniziali
      |
      +---> [Query ricorsiva] (Eseguita sulle righe ancora) ---> Righe del Passaggio 1
                  |
                  +---> [Query ricorsiva] (Eseguita sulle righe del Passaggio 1) ---> Righe del Passaggio 2
                              |
                              +---> Restituisce un set vuoto ---> TERMINAZIONE
```

### Esempio pratico: Gerarchia organizzativa
* **Punto**: Scorrere ricorsivamente una tabella contenente relazioni padre-figlio.
* **Esempio**:
  ```sql
  -- MySQL & PostgreSQL
  WITH RECURSIVE org_chart AS (
      -- 1. Anchor: Find the CEO
      SELECT id, name, manager_id, 1 AS depth
      FROM employees
      WHERE manager_id IS NULL
      
      UNION ALL
      
      -- 2. Recursive Member: Join employees with their managers
      SELECT e.id, e.name, e.manager_id, o.depth + 1
      FROM employees e
      INNER JOIN org_chart o ON e.manager_id = o.id
  )
  SELECT * FROM org_chart ORDER BY depth;
  ```
* **Conseguenza**: Recuperi l'intero albero dei subordinati, completo del loro livello di profondità, in un'unica query eseguita interamente all'interno del database.

> [!WARNING]
> **Protezione contro i cicli infiniti!**
> Se i dati contengono un riferimento circolare (ad es. l'impiegato A riporta a B, B riporta a C, C riporta ad A), una CTE ricorsiva verrà eseguita all'infinito, causando l'esaurimento della memoria del server.
> - **PostgreSQL** fornisce la clausola `CYCLE` per prevenire i cicli:
>   `CYCLE id SET is_cycle USING path`
> - **MySQL** non dispone della clausola `CYCLE` ma consente di limitare la profondità di ricorsione a livello globale o per singola query:
>   `SET max_sp_recursion_depth = 255;` o utilizzando gli hint dell'ottimizzatore: `/*+ MAX_EXECUTION_TIME(1000) */`

---

## 3. Funzionamento interno del database: Materializzazione e ottimizzazione

In che modo i motori di database eseguono le CTE? Le eseguono come tabelle temporanee o copiano-incollano il loro SQL direttamente nella query principale? I motori si comportano in modo diverso:

### Regole di materializzazione di PostgreSQL
* **PostgreSQL < 12**: Storicamente, Postgres trattava tutte le CTE come "optimization fence" (barriere di ottimizzazione). Eseguiva sempre prima la CTE, memorizzava i risultati in una tabella temporanea (la materializzava) e poi eseguiva il join. Questo impediva all'ottimizzatore di applicare i filtri `WHERE` esterni all'interno della CTE, causando importanti colli di bottiglia nelle prestazioni.
* **PostgreSQL 12+**: Il comportamento predefinito è cambiato in **NOT MATERIALIZED**. Se una CTE viene richiamata una sola volta, Postgres unisce la sua logica alla query principale (eseguendone l'inlining) in modo da poter utilizzare le scansioni degli indici.
* **Override manuale**:
  - `WITH cte AS MATERIALIZED (...)` costringe Postgres a valutarla una volta e a memorizzare il risultato nella cache.
  - `WITH cte AS NOT MATERIALIZED (...)` costringe Postgres a eseguire l'inlining.

### Calcolo dei costi inline in MySQL
* **MySQL 8.0**: Utilizza un ottimizzatore basato sui costi per decidere se eseguire l'inlining di una CTE o materializzarla in una tabella temporanea. Se la CTE è semplice, MySQL esegue sempre l'inlining. Se vi si fa riferimento più volte, MySQL la materializza per evitare esecuzioni ripetute.

---

## 4. Riepilogo e buone pratiche

1. **Usa le CTE per la leggibilità**: Sostituisci sottoquery nidificate complesse con CTE sequenziali e nominate.
2. **Attenzione alla barriera di ottimizzazione (optimization fence)**: Se utilizzi PostgreSQL, fai attenzione ai riferimenti multipli alla stessa CTE. Usa `AS NOT MATERIALIZED` se desideri che l'ottimizzatore applichi le scansioni degli indici verso il basso.
3. **Proteggiti dalla crescita incontrollata dei cicli**: Verifica sempre la presenza di riferimenti circolari nei dati prima di eseguire `WITH RECURSIVE`, oppure imposta limiti di esecuzione.
