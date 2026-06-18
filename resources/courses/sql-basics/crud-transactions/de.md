# CRUD und Transaktionen

Das Abfragen von Daten ist nur die halbe Miete. Um dynamische Anwendungen zu erstellen, müssen Sie Datensätze schreiben, ändern und löschen (Operationen, die zusammenfassend als CRUD bekannt sind: Create, Read, Update, Delete). Wenn Sie außerdem mehrere zusammenhängende Datenbankänderungen ausführen (z. B. das Überweisen von Geld zwischen zwei Konten), müssen Sie garantieren, dass entweder alle Operationen erfolgreich sind oder keine. Hier kommen **Transaktionen** ins Spiel.

In diesem abschließenden Kapitel werden wir lernen, wie man Daten manipuliert, Transaktionen implementiert und die wichtigsten Unterschiede verstehen, wie PostgreSQL und MySQL Transaktionszustände und Schreibkonflikte verwalten.

---

## 1. Daten ändern: INSERT, UPDATE und DELETE

### Datensätze einfügen
Sie können eine einzelne Zeile einfügen oder einen Bulk-Insert (Massen-Einfügung) in einer einzigen Abfrage durchführen.
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

### Datensätze aktualisieren
Ändert bestehende Datensätze. Stellen Sie immer sicher, dass Sie Ihre Aktualisierung mit einer `WHERE`-Klausel filtern.
```sql
-- MySQL & PostgreSQL
UPDATE users SET is_active = true WHERE id = 42;
```

### Datensätze löschen
Entfernt Datensätze dauerhaft. Wie `UPDATE` erfordert auch dies eine `WHERE`-Klausel, um katastrophale Datenverluste zu vermeiden.
```sql
-- MySQL & PostgreSQL
DELETE FROM users WHERE is_active = false;
```

> [!WARNING]
> **Die Falle der fehlenden `WHERE`-Klausel!**
> Wenn Sie `UPDATE users SET is_active = true;` oder `DELETE FROM users;` ohne eine `WHERE`-Klausel ausführen, wird die Engine **jede einzelne Zeile** in Ihrer Tabelle ändern oder löschen. Überprüfen Sie Ihre Abfrage vor der Ausführung immer doppelt!

---

## 2. Transaktionen: Gewährleistung der Datenintegrität

Eine Transaktion ist eine Abfolge von SQL-Anweisungen, die als eine einzige, unteilbare Arbeitseinheit ausgeführt wird. Transaktionen halten sich an die **ACID**-Standards:
* **Atomarität (Atomicity)**: Alle Anweisungen sind erfolgreich, oder die gesamte Transaktion wird zurückgerollt (Alles-oder-nichts).
* **Konsistenz (Consistency)**: Stellt sicher, dass die Datenbank von einem gültigen Zustand in einen anderen übergeht und dabei alle Constraints (Einschränkungen) einhält.
* **Isolation (Isolation)**: Gleichzeitig ausgeführte Transaktionen stören sich nicht gegenseitig.
* **Dauerhaftigkeit (Durability)**: Einmal festgeschrieben (committed), werden Änderungen dauerhaft auf die Festplatte geschrieben und überstehen Systemabstürze.

### Transaktionsbefehle
* **`BEGIN` / `START TRANSACTION`**: Startet den Transaktionsblock.
* **`COMMIT`**: Speichert alle während der Transaktion vorgenommenen Änderungen dauerhaft.
* **`ROLLBACK`**: Verwirft alle während der Transaktion vorgenommenen Änderungen und stellt den Zustand der Datenbank vor der Transaktion wieder her.

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

## 3. Wichtige Unterschiede: MySQL vs. PostgreSQL

### Upsert-Syntax (Einfügen oder Aktualisieren bei Schlüsselkonflikt)
Ein „Upsert“ fügt eine Zeile ein, wenn sie noch nicht existiert, oder aktualisiert sie, wenn sie mit einem vorhandenen eindeutigen Index oder Primärschlüssel kollidiert.
* **MySQL**: Verwendet die `ON DUPLICATE KEY UPDATE`-Klausel.
* **PostgreSQL**: Verwendet die `ON CONFLICT (conflict_column) DO UPDATE`-Klausel.

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

### Transaktionale DDL (Data Definition Language)
Dies ist einer der kritischsten strukturellen Unterschiede zwischen den beiden Datenbanken.
* **PostgreSQL** unterstützt vollständig transaktionale DDL. Sie können Befehle wie `CREATE TABLE`, `DROP TABLE` oder `ALTER TABLE` innerhalb einer Transaktion ausführen und diese sicher zurückrollen, wenn etwas schiefgeht.
* **MySQL** unterstützt transaktionale DDL NICHT. Wenn Sie eine DDL-Anweisung innerhalb einer Transaktion in MySQL ausführen, löst dies ein **implizites Commit** (auch bekannt als automatisches Commit) aus. MySQL schreibt die Transaktion bis zu diesem Punkt sofort fest, führt die DDL aus, und Sie können keine der vorhergehenden Schreibvorgänge zurückrollen.

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

### Syntax zum Transaktionsstart
* **PostgreSQL**: Bevorzugt den Standardbefehl `BEGIN` (obwohl es auch `START TRANSACTION` akzeptiert).
* **MySQL**: Bevorzugt `START TRANSACTION` (obwohl es `BEGIN` in den meisten Client-Kontexten akzeptiert; innerhalb von gespeicherten Prozeduren ist `BEGIN` jedoch für Blockstrukturen reserviert, weshalb dort `START TRANSACTION` erforderlich ist).

> [!TIP]
> **Wussten Sie schon?**
> PostgreSQL unterstützt die `RETURNING`-Klausel für Schreiboperationen. Dies ermöglicht es Ihnen, den generierten Primärschlüssel oder berechnete Spalten sofort abzurufen, ohne eine separate `SELECT`-Abfrage auszuführen.
> `INSERT INTO users (email) VALUES ('new@devsense.work') RETURNING id, created_at;`
> *MySQL unterstützt `RETURNING` nicht und erfordert, dass Client-Bibliotheken Funktionen wie `LAST_INSERT_ID()` aufrufen, um den generierten Schlüssel abzurufen.*

---

## 4. Zusammenfassung & Best Practices

1. **Transaktionen kurz halten**: Lang laufende Transaktionen halten Sperren auf Datenbankzeilen, was andere Benutzer blockiert und die Wahrscheinlichkeit von Deadlocks erhöht.
2. **Vorsicht vor impliziten Commits in MySQL**: Mischen Sie keine Schemaänderungen (DDL wie `ALTER TABLE`) mit Datenänderungen (DML wie `UPDATE`) innerhalb von Transaktionen, wenn Sie sich auf Rollbacks verlassen wollen.
3. **Upserts vorsichtig verwenden**: Passen Sie die Syntax an Ihre Datenbank an (`ON CONFLICT` für PostgreSQL und `ON DUPLICATE KEY UPDATE` für MySQL), um Verletzungen von Unique-Key-Constraints elegant zu handhaben.
4. **Immer WHERE angeben**: Schützen Sie sich vor versehentlichem Löschen ganzer Tabellen, indem Sie sicherstellen, dass `UPDATE`- und `DELETE`-Abfragen strikte `WHERE`-Klauseln enthalten.
