# Database relazionali e l'istruzione SELECT

Alla base del moderno storage dei dati c'è il modello di database relazionale, proposto per la prima volta da Edgar F. Codd nel 1970. Invece di memorizzare i dati in file di testo non strutturati o in rigide strutture ad albero gerarchiche, un database relazionale organizza le informazioni in tabelle strutturate che possono essere collegate (join) e interrogate dinamicamente utilizzando lo Structured Query Language (SQL). Sia che si utilizzi MySQL o PostgreSQL, la comprensione dei concetti fondamentali della progettazione relazionale e di come recuperare i dati in modo efficiente è fondamentale.

---

## 1. Il modello relazionale: Tabelle, colonne e chiavi

In un database relazionale, i dati sono rappresentati come una raccolta di tabelle di relazioni. Ogni tabella è composta da:
* **Colonne (Attributi)**: Definiscono il tipo di dati e le proprietà delle informazioni memorizzate (ad es., `user_id` come intero, `email` come stringa).
* **Righe (Record/Tuple)**: Rappresentano singole istanze di dati (ad es., il record di un utente specifico).

Per mantenere l'integrità, le tabelle si affidano a due concetti essenziali:
1. **Chiave primaria (PK)**: Una colonna (o un insieme di colonne) che identifica in modo univoco ogni riga di una tabella. Non può contenere valori `NULL`.
2. **Chiave esterna (FK)**: Una colonna in una tabella che fa riferimento alla chiave primaria di un'altra tabella, stabilendo una relazione tra di esse.

| Concetto | Descrizione | Analogia |
| :--- | :--- | :--- |
| **Tabella** | Una griglia strutturata di colonne e righe. | Una scheda di un foglio di calcolo. |
| **Riga** | Un singolo record di dati. | Una singola riga in un foglio di calcolo. |
| **Chiave primaria** | Identificativo univoco per una riga. | Numero di passaporto. |
| **Chiave esterna** | Riferimento alla PK di un'altra tabella. | L'ID del dipartimento di un dipendente. |

---

## 2. Recuperare dati con `SELECT`

L'operazione più fondamentale in SQL è il recupero dei dati tramite l'istruzione `SELECT`. Nella sua forma più semplice, una query richiede una clausola `SELECT` (quali colonne recuperare) e una clausola `FROM` (da quale tabella eseguire la query).

### Selezionare tutte le colonne vs. Colonne specifiche
È possibile recuperare tutte le colonne utilizzando il carattere jolly asterisco (`*`), oppure specificare solo le colonne necessarie.
```sql
-- MySQL & PostgreSQL
-- Retrieve all columns (not recommended for production)
SELECT * FROM users;

-- Retrieve specific columns (recommended)
SELECT id, email, first_name FROM users;
```

> [!TIP]
> **Lo sapevi?**
> L'uso di `SELECT *` in produzione è considerato un anti-pattern. Costringe il motore del database a eseguire I/O su disco aggiuntivo per leggere colonne non necessarie, consuma larghezza di banda di rete superflua e può compromettere l'applicazione se viene aggiunta una nuova colonna o se ne viene rimossa una vecchia dalla tabella.

### Alias delle colonne (`AS`)
Gli alias consentono di rinominare le colonne nel set di risultati della query per una migliore leggibilità o per soddisfare le esigenze dell'applicazione.
```sql
-- MySQL & PostgreSQL
SELECT first_name AS given_name, last_name AS surname FROM users;
```

---

## 3. Filtrare i risultati con `WHERE`

Per recuperare solo righe specifiche, si utilizza la clausola `WHERE`. La clausola `WHERE` valuta una condizione booleana per ciascuna riga, restituendo solo quelle per le quali la condizione risulta vera.

### Operatori standard
SQL supporta gli operatori di confronto standard:
* `=` (uguale a)
* `<>` o `!=` (diverso da)
* `>` (maggiore di), `<` (minore di)
* `>=` (maggiore o uguale a), `<=` (minore o uguale a)

```sql
-- Retrieve active users registered after a specific ID
SELECT email FROM users WHERE is_active = true AND id > 100;
```

### Operatori di filtraggio avanzati
* **`BETWEEN`**: Filtra i valori all'interno di un intervallo specifico (inclusi gli estremi).
* **`IN`**: Verifica se un valore corrisponde a un qualsiasi valore in un elenco specificato.
* **`LIKE`**: Esegue una semplice ricerca di pattern utilizzando caratteri jolly:
  - `%` rappresenta zero o più caratteri.
  - `_` rappresenta esattamente un carattere.

```sql
-- Retrieve users with IDs 1, 3, or 5
SELECT email FROM users WHERE id IN (1, 3, 5);

-- Retrieve users registered in a specific range
SELECT email FROM users WHERE id BETWEEN 10 AND 50;

-- Retrieve users whose email starts with 'admin'
SELECT email FROM users WHERE email LIKE 'admin%';
```

---

## 4. Differenze principali: MySQL vs. PostgreSQL

Sebbene entrambi i motori seguano lo standard SQL, differiscono nella sintassi e nei comportamenti predefiniti.

### Racchiudere tra virgolette gli identificatori
Gli identificatori (nomi di tabelle, nomi di colonne) devono essere racchiusi tra virgolette se sono in conflitto con le parole chiave riservate di SQL o se contengono caratteri speciali/spazi.
* **MySQL**: Utilizza gli accenti gravi o backtick (`` ` ``).
* **PostgreSQL**: Utilizza le virgolette doppie (`"`).

```sql
-- MySQL
SELECT `select`, `group` FROM `my_table`;

-- PostgreSQL
SELECT "select", "group" FROM "my_table";
```

### Sensibilità alle maiuscole/minuscole nel pattern matching
* **PostgreSQL** fa distinzione tra maiuscole e minuscole (case-sensitive) per `LIKE`. Per eseguire una ricerca che non distingua tra maiuscole e minuscole, è necessario utilizzare l'operatore `ILIKE` specifico di PostgreSQL.
* **MySQL** gestisce il `LIKE` come insensibile alle maiuscole/minuscole (case-insensitive) per impostazione predefinita con le regole di confronto standard (ad es., `utf8mb4_0900_ai_ci`). Per renderlo case-sensitive, è necessario eseguire il cast della stringa in binario o utilizzare una codifica binaria.

```sql
-- Case-insensitive search for 'john'
-- PostgreSQL
SELECT email FROM users WHERE email ILIKE 'john%';

-- MySQL
SELECT email FROM users WHERE email LIKE 'john%';
```

### Concatenazione di stringhe
* **PostgreSQL** utilizza l'operatore standard SQL con doppia barra verticale (`||`).
* **MySQL** non supporta `||` per la concatenazione come comportamento predefinito (tratta `||` come l'operatore logico `OR`, a meno che non sia abilitata la modalità SQL `PIPES_AS_CONCAT`). MySQL utilizza invece la funzione `CONCAT()`.

```sql
-- PostgreSQL
SELECT first_name || ' ' || last_name AS full_name FROM users;

-- MySQL
SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users;
```

### Tipi di dati booleani
* **PostgreSQL** ha un tipo `BOOLEAN` nativo che supporta i valori letterali `true` e `false`.
* **MySQL** non ha un vero tipo booleano; associa `BOOLEAN` a `TINYINT(1)`, dove `0` è falso e `1` è vero.

```sql
-- PostgreSQL: Returns true/false
SELECT is_active FROM users;

-- MySQL: Returns 1/0
SELECT is_active FROM users;
```

> [!WARNING]
> **La trappola delle virgolette!**
> Non usare mai le virgolette doppie (`"`) per i valori letterali stringa (valori di testo) in PostgreSQL. PostgreSQL tratta le virgolette doppie come virgolette per identificatori (per nomi di colonne o tabelle), il che causerà un errore di sintassi del tipo `column "value" does not exist`. Usa sempre le virgolette singole (`'`) per i valori letterali stringa sia in MySQL che in PostgreSQL.

---

## 5. Riepilogo e buone pratiche

1. **Sii specifico**: Elenca esplicitamente i nomi delle colonne invece di usare `SELECT *` per migliorare le prestazioni e la stabilità del codice.
2. **Standardizza le virgolette**: Usa le virgolette singole (`'`) per i valori letterali stringa in tutti i motori di database.
3. **Conosci il tuo operatore**: Usa `ILIKE` in PostgreSQL per ricerche insensibili alle maiuscole/minuscole, e ricorda che la concatenazione con `||` è lo standard in Postgres ma richiede `CONCAT()` in MySQL.
4. **Verifiche booleane**: Ricorda che MySQL memorizza i booleani come `1` o `0`, il che può influenzare il modo in cui il codice dell'applicazione analizza i risultati delle query.
