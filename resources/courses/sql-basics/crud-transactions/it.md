# CRUD e transazioni

Il recupero dei dati rappresenta solo metà dell'opera. Per creare applicazioni dinamiche, è necessario scrivere, modificare ed eliminare i record (operazioni note collettivamente come CRUD: Create, Read, Update, Delete). Inoltre, quando si eseguono diverse modifiche correlate nel database (come il trasferimento di denaro tra due conti), è necessario garantire che tutte le operazioni vadano a buon fine o che nessuna di esse venga applicata. È qui che entrano in gioco le **Transazioni**.

In questo capitolo finale impareremo a modificare i dati, a implementare le transazioni e a comprendere le differenze principali nel modo in cui PostgreSQL e MySQL gestiscono gli stati transazionali e i conflitti di scrittura.

---

## 1. Modificare i dati: INSERT, UPDATE e DELETE

### Inserire record
È possibile inserire una singola riga o eseguire un inserimento multiplo (bulk) in un'unica query.
```sql
-- MySQL & PostgreSQL
-- Single insert
INSERT INTO users (email, name) VALUES ('bob@devsense.work', 'Bob');

-- Bulk insert (recommended for performance)
INSERT INTO users (email, name) 
VALUES 
    ('alice@devsense.work', 'Alice'),
    ('charlie@devsense.work', 'Charlie');
```

### Aggiornare record
Consente di modificare record esistenti. Assicurati sempre di filtrare l'aggiornamento utilizzando una clausola `WHERE`.
```sql
-- MySQL & PostgreSQL
UPDATE users SET is_active = true WHERE id = 42;
```

### Eliminare record
Rimuove i record in modo permanente. Analogamente a `UPDATE`, richiede una clausola `WHERE` per evitare una catastrofica perdita di dati.
```sql
-- MySQL & PostgreSQL
DELETE FROM users WHERE is_active = false;
```

> [!WARNING]
> **La trappola della clausola `WHERE` mancante!**
> Se si esegue `UPDATE users SET is_active = true;` o `DELETE FROM users;` senza una clausola `WHERE`, il motore di database modificherà o eliminerà **ogni singola riga** della tabella. Controlla sempre attentamente la query prima di eseguirla!

---

## 2. Transazioni: salvaguardare l'integrità dei dati

Una transazione è una sequenza di istruzioni SQL eseguite come un'unica unità di lavoro indivisibile. Le transazioni aderiscono agli standard **ACID**:
* **Atomicità (Atomicity)**: Tutte le istruzioni hanno successo, oppure l'intera transazione viene annullata (tutto o niente).
* **Coerenza (Consistency)**: Garantisce che il database passi da uno stato valido a un altro, rispettando tutti i vincoli.
* **Isolamento (Isolation)**: Le transazioni eseguite in contemporanea non interferiscono tra loro.
* **Durabilità (Durability)**: Una volta confermate (commit), le modifiche vengono scritte in modo permanente sul disco e sopravvivono ai crash di sistema.

### Comandi delle transazioni
* **`BEGIN` / `START TRANSACTION`**: Avvia il blocco di transazione.
* **`COMMIT`**: Salva in modo permanente tutte le modifiche apportate durante la transazione.
* **`ROLLBACK`**: Scarta tutte le modifiche apportate durante la transazione, ripristinando il database allo stato precedente all'avvio della transazione.

```sql
-- MySQL & PostgreSQL
BEGIN; -- Start transaction

UPDATE accounts SET balance = balance - 100 WHERE id = 1;
UPDATE accounts SET balance = balance + 100 WHERE id = 2;

-- If everything is fine:
COMMIT;

-- If a query failed or we changed our mind:
-- ROLLBACK;
```

---

## 3. Differenze principali: MySQL vs. PostgreSQL

### Sintassi di Upsert (inserimento o aggiornamento in caso di conflitto di chiavi)
Un "upsert" inserisce una riga se non esiste, oppure la aggiorna se va in conflitto con un indice univoco o una chiave primaria esistente.
* **MySQL**: Utilizza la clausola `ON DUPLICATE KEY UPDATE`.
* **PostgreSQL**: Utilizza la clausola `ON CONFLICT (colonna_conflitto) DO UPDATE`.

```sql
-- MySQL (ON DUPLICATE KEY UPDATE)
INSERT INTO users (id, email, visits) 
VALUES (1, 'user@devsense.work', 1)
ON DUPLICATE KEY UPDATE visits = visits + 1;

-- PostgreSQL (ON CONFLICT DO UPDATE)
INSERT INTO users (id, email, visits) 
VALUES (1, 'user@devsense.work', 1)
ON CONFLICT (id) DO UPDATE SET visits = users.visits + 1;
```

### DDL transazionale (Data Definition Language)
Questa è una delle differenze strutturali più critiche tra i due database.
* **PostgreSQL** supporta un DDL completamente transazionale. È possibile eseguire comandi come `CREATE TABLE`, `DROP TABLE` o `ALTER TABLE` all'interno di una transazione e annullarli in sicurezza tramite rollback in caso di problemi.
* **MySQL** NON supporta il DDL transazionale. Se si esegue un'istruzione DDL all'interno di una transazione in MySQL, questa attiva un **commit implicito** (noto anche come commit automatico). MySQL conferma immediatamente la transazione fino a quel punto ed esegue il DDL; non sarà più possibile annullare le scritture precedenti.

```sql
-- PostgreSQL (This works and will be fully rolled back!)
BEGIN;
DROP TABLE users;
ROLLBACK; -- Table 'users' is safely restored!

-- MySQL (This will fail to roll back!)
START TRANSACTION;
INSERT INTO logs (message) VALUES ('Deleting users table');
DROP TABLE users; -- Triggers implicit commit!
ROLLBACK; -- Does nothing; the insert and DROP are already committed!
```

### Sintassi di avvio della transazione
* **PostgreSQL**: Predilige il comando standard `BEGIN` (sebbene accetti anche `START TRANSACTION`).
* **MySQL**: Predilige `START TRANSACTION` (sebbene accetti `BEGIN` nella maggior parte dei contesti client; tuttavia, all'interno delle stored procedure, `BEGIN` è riservato alle strutture dei blocchi, rendendo obbligatorio l'uso di `START TRANSACTION`).

> [!TIP]
> **Lo sapevi?**
> PostgreSQL supporta la clausola `RETURNING` per le operazioni di scrittura. Ciò consente di recuperare immediatamente la chiave primaria generata o le colonne calcolate senza dover eseguire una query `SELECT` separata.
> `INSERT INTO users (email) VALUES ('new@devsense.work') RETURNING id, created_at;`
> *MySQL non supporta `RETURNING` e richiede alle librerie client di chiamare funzioni come `LAST_INSERT_ID()` per recuperare la chiave generata.*

---

## 4. Riepilogo e buone pratiche

1. **Mantieni le transazioni brevi**: Le transazioni a lungo termine mantengono i blocchi sulle righe del database, bloccando gli altri utenti e aumentando la probabilità di deadlock.
2. **Attenzione ai commit impliciti in MySQL**: Non mescolare modifiche allo schema (DDL come `ALTER TABLE`) con modifiche ai dati (DML come `UPDATE`) all'interno delle transazioni se prevedi di affidarti ai rollback.
3. **Usa gli upsert con attenzione**: Adotta la sintassi corretta per il tuo database (`ON CONFLICT` per PostgreSQL e `ON DUPLICATE KEY UPDATE` per MySQL) per gestire in modo pulito le violazioni dei vincoli di chiave duplicata.
4. **Specifica sempre la clausola WHERE**: Proteggiti dalla cancellazione accidentale delle tabelle assicurandoti che le query `UPDATE` e `DELETE` contengano clausole `WHERE` restrittive.
