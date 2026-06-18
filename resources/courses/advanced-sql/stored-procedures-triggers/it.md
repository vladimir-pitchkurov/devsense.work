# Stored procedure, funzioni e trigger

Spostare la logica di business più vicino ai propri dati può ridurre significativamente la latenza di rete e garantire una rigorosa integrità dei dati. Quando si scrive logica di programmazione da eseguire direttamente all'interno del database, si utilizzano tre costrutti principali: **Funzioni (Functions)**, **Stored procedure** e **Trigger**.

Comprendere come questi elementi gestiscono le transazioni, accedono alla memoria e si differenziano tra MySQL e PostgreSQL è essenziale per la progettazione di database affidabili.

---

## 1. Funzioni definite dall'utente (UDF) vs. Stored procedure

Sebbene entrambi i costrutti raggruppino istruzioni SQL, essi servono a scopi molto diversi. La differenza più critica risiede nel **controllo delle transazioni**.

| Funzionalità | Funzione definita dall'utente (UDF) | Stored procedure |
| :--- | :--- | :--- |
| **Invocazione** | Chiamata inline all'interno delle query (ad es., `SELECT mia_funzione(val)`) | Chiamata autonoma tramite `CALL mia_procedura(val)` |
| **Valore restituito** | Deve restituire un singolo valore o una tabella | Non restituisce valori (utilizza invece parametri `OUT`) |
| **Transazioni** | **Non può** eseguire `COMMIT` o `ROLLBACK` | **Può** eseguire `COMMIT` o `ROLLBACK` |
| **Contesto d'uso** | Leggere/Trasformare i dati nelle query | Orchestrare scritture e operazioni batch complesse |

### Esempio di controllo delle transazioni (Stored procedure)
Le stored procedure possono controllare le transazioni in modo nativo, consentendo di confermare (commit) o annullare (rollback) le modifiche a metà dell'esecuzione.

```sql
-- PostgreSQL 11+ Syntax
CREATE PROCEDURE transfer_funds(sender INT, receiver INT, amount DECIMAL)
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE accounts SET balance = balance - amount WHERE id = sender;
    UPDATE accounts SET balance = balance + amount WHERE id = receiver;
    
    -- Commit the transaction inside the procedure
    COMMIT;
EXCEPTION WHEN OTHERS THEN
    -- Rollback changes if anything fails
    ROLLBACK;
END;
$$;

-- MySQL Syntax
CREATE PROCEDURE transfer_funds(IN sender INT, IN receiver INT, IN amount DECIMAL)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;
    UPDATE accounts SET balance = balance - amount WHERE id = sender;
    UPDATE accounts SET balance = balance + amount WHERE id = receiver;
    COMMIT;
END;
```

---

## 2. Trigger: automatizzare le regole di business

Un **Trigger** è un oggetto del database che esegue automaticamente un insieme specificato di istruzioni SQL quando si verifica un evento (come `INSERT`, `UPDATE` o `DELETE`) su una tabella.

### Tempistiche di esecuzione dei trigger
* **`BEFORE`**: Viene eseguito *prima* che il database scriva le modifiche sul disco. Utilizzato per convalidare o modificare i valori in ingresso.
* **`AFTER`**: Viene eseguito *dopo* che il database ha scritto le modifiche sul disco. Utilizzato per scopi di tracciamento (logging), audit o per aggiornare altre tabelle.
* **`INSTEAD OF`**: Utilizzato sulle viste per sostituire le azioni di scrittura predefinite con una logica personalizzata.

### Trigger a livello di riga vs. a livello di istruzione
* **`FOR EACH ROW`**: Il trigger viene eseguito una volta per ogni singola riga interessata dalla query. Se un aggiornamento interessa 10.000 righe, il trigger verrà eseguito 10.000 volte.
* **`FOR EACH STATEMENT`**: Il trigger viene eseguito esattamente una volta per istruzione SQL, indipendentemente dal numero di righe modificate. Utile per il logging o per controlli di massa.

### Gli pseudo-record `OLD` e `NEW`
I trigger hanno accesso a variabili speciali che rappresentano lo stato della riga:
* **`NEW`**: Contiene i valori della nuova riga (disponibile in `INSERT` e `UPDATE`).
* **`OLD`**: Contiene i valori della riga originale (disponibile in `UPDATE` e `DELETE`).

---

## 3. MySQL vs. PostgreSQL: differenze sintattiche e strutturali

L'architettura di esecuzione per trigger e procedure differisce pesantemente tra questi due database.

### 1. Architettura dei trigger: Funzioni vs. Inline
* **PostgreSQL**: Richiede un processo in due fasi. Per prima cosa, è necessario scrivere una funzione trigger che restituisca il tipo speciale `TRIGGER`. Successivamente, si associa tale funzione alla tabella tramite il comando `CREATE TRIGGER`.
```sql
-- PostgreSQL: 1. Create Trigger Function
CREATE FUNCTION log_salary_change() RETURNS TRIGGER AS $$
BEGIN
    IF NEW.salary <> OLD.salary THEN
        INSERT INTO salary_audit(emp_id, old_val, new_val)
        VALUES (OLD.id, OLD.salary, NEW.salary);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- PostgreSQL: 2. Bind Function to Table
CREATE TRIGGER after_salary_update
AFTER UPDATE ON employees
FOR EACH ROW EXECUTE FUNCTION log_salary_change();
```

* **MySQL**: Consente di scrivere la logica del trigger direttamente inline all'interno della definizione del trigger.
```sql
-- MySQL: Direct inline trigger definition
CREATE TRIGGER after_salary_update
AFTER UPDATE ON employees
FOR EACH ROW
BEGIN
    IF NEW.salary <> OLD.salary THEN
        INSERT INTO salary_audit(emp_id, old_val, new_val)
        VALUES (OLD.id, OLD.salary, NEW.salary);
    END IF;
END;
```

### 2. Supporto dei linguaggi
* **MySQL**: Supporta solo la propria estensione proprietaria della sintassi SQL.
* **PostgreSQL**: Supporta molteplici linguaggi. Sebbene `PL/pgSQL` sia quello predefinito, è possibile scrivere funzioni e trigger di database in `PL/Python`, `PL/Perl` o `PL/v8` (JavaScript).

> [!WARNING]
> **Impatto prestazionale dei trigger!**
> I trigger a livello di riga (`FOR EACH ROW`) possono causare un grave degrado delle prestazioni in caso di aggiornamenti di massa. Se si aggiorna 1 milione di righe, un trigger a livello di riga costringe il database a effettuare uno switch di contesto tra il motore di esecuzione SQL e l'interprete procedurale per 1 milione di volte, trasformando una veloce query basata su insiemi in un lento ciclo riga per riga.

> [!TIP]
> **Vincolo delle transazioni in PostgreSQL:**
> Sebbene le stored procedure di PostgreSQL 11+ supportino i comandi di transazione (`COMMIT`/`ROLLBACK`), non possono eseguirli se la procedura viene richiamata dall'interno di un blocco di transazione già attivo (ad esempio, se si esegue `BEGIN; CALL mia_procedura();`).

---

## 4. Riepilogo e buone pratiche

1. **Funzioni per i calcoli**: Usa le UDF per trasformazioni e calcoli all'interno delle query. Non cercare di modificare i dati o controllare le transazioni al loro interno.
2. **Procedure per i flussi di lavoro**: Usa le stored procedure per eseguire aggiornamenti batch, orchestrare scritture complesse e controllare le transazioni.
3. **Mantieni i trigger leggeri**: Utilizza i trigger solo per garantire l'integrità dei dati critici, per il logging o per l'auditing. Se devi utilizzarli, mantieni la logica al minimo per evitare colli di bottiglia in scrittura.
4. **Presta attenzione alle operazioni di massa**: Disabilita o aggira i trigger durante importazioni massive di dati o migrazioni per evitare il collasso delle prestazioni del server.
