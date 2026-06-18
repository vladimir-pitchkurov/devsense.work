# Stored Procedures, Funktionen und Trigger

Die Verlagerung von Geschäftslogik näher an Ihre Daten kann die Netzwerklatenz erheblich verringern und eine strikte Datenintegrität gewährleisten. Wenn Sie Programmierlogik schreiben, die direkt in der Datenbank ausgeführt wird, verwenden Sie drei Hauptkonstrukte: **Funktionen (Functions)**, **gespeicherte Prozeduren (Stored Procedures)** und **Trigger**.

Das Verständnis darüber, wie diese Elemente Transaktionen verwalten, auf den Speicher zugreifen und sich in MySQL und PostgreSQL unterscheiden, ist für den Entwurf zuverlässiger Datenbanken von entscheidender Bedeutung.

---

## 1. User-Defined Functions (UDFs) vs. Stored Procedures

Obwohl beide SQL-Anweisungen zusammenfassen, dienen sie sehr unterschiedlichen Zwecken. Der wichtigste Unterschied liegt in der **Transaktionskontrolle**.

| Feature | User-Defined Function (UDF) | Gespeicherte Prozedur (Stored Procedure) |
| :--- | :--- | :--- |
| **Aufruf** | Wird inline in Abfragen aufgerufen (z. B. `SELECT my_func(val)`) | Wird eigenständig mit `CALL my_proc(val)` aufgerufen |
| **Rückgabewert** | Muss einen einzelnen Wert oder eine Tabelle zurückgeben | Gibt keinen Wert zurück (verwendet stattdessen `OUT`-Parameter) |
| **Transaktionen** | **Kann nicht** `COMMIT` oder `ROLLBACK` ausführen | **Kann** `COMMIT` oder `ROLLBACK` ausführen |
| **Anwendungskontext** | Daten in Abfragen lesen/transformieren | Komplexe Batch-Schreibvorgänge und -Operationen orchestrieren |

### Beispiel für Transaktionskontrolle (Stored Procedure)
Gespeicherte Prozeduren können Transaktionen nativ steuern, sodass Sie Änderungen während der Ausführung festschreiben oder zurückrollen können.

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

## 2. Trigger: Automatisierung von Geschäftsregeln

Ein **Trigger** is ein Datenbankobjekt, das automatisch eine bestimmte Reihe von SQL-Anweisungen ausführt, wenn ein Ereignis (wie `INSERT`, `UPDATE` oder `DELETE`) auf einer Tabelle auftritt.

### Trigger-Ausführungszeitpunkt
* **`BEFORE`**: Wird ausgeführt, *bevor* die Datenbank die Änderungen auf die Festplatte schreibt. Wird verwendet, um eingehende Werte zu validieren oder zu ändern.
* **`AFTER`**: Wird ausgeführt, *nachdem* die Datenbank die Änderungen auf die Festplatte geschrieben hat. Wird für Logging, Audits oder das Aktualisieren anderer Tabellen verwendet.
* **`INSTEAD OF`**: Wird auf Views verwendet, um standardmäßige Schreibaktionen durch benutzerdefinierte Logik zu überschreiben.

### Row-Level- vs. Statement-Level-Trigger
* **`FOR EACH ROW`**: Der Trigger wird für jede einzelne Zeile ausgeführt, die von der Abfrage betroffen ist. Wenn ein Update 10.000 Zeilen betrifft, läuft der Trigger 10.000-mal.
* **`FOR EACH STATEMENT`**: Der Trigger wird genau einmal pro SQL-Anweisung ausgeführt, unabhängig davon, wie viele Zeilen geändert werden. Nützlich für Protokollierung oder Massenprüfungen.

### Die Pseudo-Datensätze `OLD` und `NEW`
Trigger haben Zugriff auf spezielle Variablen, die den Zustand der Zeile darstellen:
* **`NEW`**: Enthält die neuen Zeilenwerte (verfügbar bei `INSERT` und `UPDATE`).
* **`OLD`**: Enthält die ursprünglichen Zeilenwerte (verfügbar bei `UPDATE` und `DELETE`).

---

## 3. MySQL vs. PostgreSQL: Syntaktische und strukturelle Unterschiede

Die Ausführungsarchitektur für Trigger und Prozeduren unterscheidet sich zwischen diesen beiden Datenbanken erheblich.

### 1. Trigger-Architektur: Funktionen vs. Inline
* **PostgreSQL**: Erfordert einen zweistufigen Prozess. Zuerst müssen Sie eine Trigger-Funktion schreiben, die den speziellen Typ `TRIGGER` zurückgibt. Zweitens binden Sie diese Funktion mit `CREATE TRIGGER` an die Tabelle.

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

* **MySQL**: Ermöglicht es Ihnen, die Trigger-Logik direkt inline innerhalb der Trigger-Definition zu schreiben.

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

### 2. Sprachunterstützung
* **MySQL**: Unterstützt nur seine proprietäre SQL-Syntaxerweiterung.
* **PostgreSQL**: Unterstützt mehrere Sprachen. Während `PL/pgSQL` der Standard ist, können Sie Datenbankfunktionen und -trigger in `PL/Python`, `PL/Perl` oder `PL/v8` (JavaScript) schreiben.

> [!WARNING]
> **Performance-Overhead von Triggern!**
> Row-Level-Trigger (`FOR EACH ROW`) können bei Massen-Aktualisierungen zu schwerwiegenden Leistungseinbußen führen. Wenn Sie 1 Million Zeilen aktualisieren, zwingt ein Row-Level-Trigger die Datenbank zu 1 Million Kontextwechseln zwischen der SQL-Ausführungs-Engine und dem prozeduralen Interpreter, wodurch eine schnelle, mengenbasierte Abfrage in eine langsame Zeilen-für-Zeilen-Schleife umgewandelt wird.

> [!TIP]
> **Transaktions-Einschränkung in PostgreSQL:**
> Obwohl gespeicherte Prozeduren in PostgreSQL 11+ Transaktionsbefehle (`COMMIT`/`ROLLBACK`) unterstützen, können sie diese nicht ausführen, wenn die Prozedur aus einem bereits aktiven Transaktionsblock aufgerufen wird (z. B. wenn Sie `BEGIN; CALL my_procedure();` ausführen).

---

## 4. Zusammenfassung & Best Practices

1. **Funktionen für Berechnungen**: Verwenden Sie UDFs für Transformationen und Berechnungen innerhalb von Abfragen. Versuchen Sie nicht, darin Daten zu ändern oder Transaktionen zu steuern.
2. **Prozeduren für Workflows**: Verwenden Sie gespeicherte Prozeduren, um Batch-Updates auszuführen, komplexe Schreibvorgänge zu orchestrieren und Transaktionen zu steuern.
3. **Trigger leichtgewichtig halten**: Verwenden Sie Trigger nur für kritische Datenintegrität, Logging oder Audits. Wenn Sie sie verwenden müssen, halten Sie die Logik minimal, um Schreibengpässe zu vermeiden.
4. **Vorsicht bei Massenoperationen**: Deaktivieren oder umgehen Sie Trigger bei großen Datenimporten oder Migrationen, um einen Leistungseinbruch des Servers zu vermeiden.
