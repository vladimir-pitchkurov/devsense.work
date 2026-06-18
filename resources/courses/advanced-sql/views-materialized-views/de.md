# Views und Materialized Views: Abstraktion vs. Caching

Im hochperformanten Datenbankdesign stehen wir oft vor zwei konkurrierenden Problemen:
1. **Komplexität von Abfragen**: Das Schreiben und Warten langer, verschachtelter Abfragen, die Dutzende von Joins umfassen.
2. **Ausführungslatenz**: Das Ausführen von abfrageintensiven Aggregationen (wie Verkaufsberichten) über Millionen von Zeilen bei jedem Seitenaufruf.

SQL begegnet diesen Herausforderungen mit **Views** (Ansichten) und **Materialized Views** (materialisierten Ansichten). Obwohl sie ähnlich klingen, sind ihre zugrunde liegenden Ausführungsmodelle völlig unterschiedlich: Das eine ist eine logische Abkürzung (Abstraktion), das andere ein physischer Tabellencache.

---

## 1. Standard-Views (Virtuelle Ansichten)

Eine Standard-**View** ist eine gespeicherte Abfragedefinition. Sie ist eine virtuelle Tabelle – sie speichert keine physischen Daten auf der Festplatte. Wenn Sie eine View abfragen, fügt die Datenbank-Engine die Abfragedefinition der View in die Hauptabfrage ein und führt diese zusammen aus.

### Grundlegende Syntax
```sql
-- MySQL & PostgreSQL
CREATE VIEW active_customer_summary AS
SELECT c.id, c.name, COUNT(o.id) AS total_orders
FROM customers c
LEFT JOIN orders o ON c.id = o.customer_id
WHERE c.status = 'active'
GROUP BY c.id, c.name;
```

### Aktualisierbare Views
Können Sie `INSERT`-, `UPDATE`- oder `DELETE`-Anweisungen auf einer View ausführen? Ja, unter strengen Bedingungen. Eine View ist nur dann aktualisierbar, wenn die Datenbank-Engine die Schreiboperationen direkt auf eine einzelne zugrunde liegende physische Tabelle zurückführen kann.
* **Regeln**: Die View darf Folgendes nicht enthalten:
  - Aggregatfunktionen (`SUM`, `COUNT`, `AVG`).
  - `GROUP BY`-, `HAVING`- oder `DISTINCT`-Klauseln.
  - Mengenoperatoren (`UNION`, `INTERSECT`, `EXCEPT`).
  - Window-Funktionen.

> [!WARNING]
> **Die `WITH CHECK OPTION`-Klausel**
> Beim Aktualisieren von Daten über eine View können Sie versehentlich Daten schreiben, die dazu führen, dass die Zeile aus der View selbst verschwindet!
> ```sql
> CREATE VIEW premium_customers AS 
> SELECT * FROM customers WHERE balance > 1000;
> ```
> Wenn Sie `UPDATE premium_customers SET balance = 500 WHERE id = 1` ausführen, ist das Update erfolgreich, aber der Kunde verschwindet aus der View. Um dies zu verhindern, hängen Sie `WITH CHECK OPTION` an die View-Definition an. Dies zwingt die Datenbank, alle Inserts oder Updates abzulehnen, die die `WHERE`-Klausel der View verletzen.

---

## 2. Materialized Views (PostgreSQL)

Im Gegensatz zu Standard-Views speichert eine **Materialized View** die Abfrageergebnisse physisch auf der Festplatte und verhält sich wie eine normale Tabelle. Das Abfragen einer Materialized View ist extrem schnell, da Joins und Aggregationsberechnungen umgangen werden. Die Daten können jedoch veralten.

### Syntax und Aktualisierung
```sql
-- PostgreSQL Only
CREATE MATERIALIZED VIEW monthly_revenue_report AS
SELECT extract(year from order_date) as year, extract(month from order_date) as month, SUM(total_amount) as revenue
FROM orders
GROUP BY 1, 2;
```

Um die Daten zu aktualisieren, müssen Sie manuell einen Refresh auslösen:
```sql
REFRESH MATERIALIZED VIEW monthly_revenue_report;
```

### Blockierungsfreie Aktualisierungen: `CONCURRENTLY`
Standardmäßig sperrt `REFRESH MATERIALIZED VIEW` die View exklusiv und blockiert alle Leseoperationen (`SELECT`), bis die Aktualisierung abgeschlossen ist. Um die View zu aktualisieren, ohne Ihre Benutzer auszusperren, verwenden Sie die Option `CONCURRENTLY`.

```sql
-- PostgreSQL Only: Non-blocking refresh
CREATE UNIQUE INDEX idx_monthly_rev ON monthly_revenue_report (year, month);
REFRESH MATERIALIZED VIEW CONCURRENTLY monthly_revenue_report;
```
* **Anforderung**: Sie müssen einen eindeutigen Index (Unique Index) auf einer oder mehreren Spalten der Materialized View erstellen, bevor Sie die `CONCURRENTLY`-Klausel verwenden können.

---

## 3. MySQL-Workarounds für Materialized Views

MySQL unterstützt Materialized Views **nicht** nativ. Wenn Sie diese Funktionalität in MySQL benötigen, müssen Sie sie mithilfe eines von zwei gängigen Workarounds simulieren.

### Workaround 1: Reguläre Tabelle + Event Scheduler
Sie können eine normale Tabelle erstellen, die als Cache fungiert, und ein geplantes Datenbank-Event (Event Scheduler) einrichten, um diese regelmäßig zu aktualisieren.

```sql
-- MySQL Only
-- 1. Create the physical table
CREATE TABLE monthly_revenue_report_cache AS
SELECT YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as revenue
FROM orders GROUP BY 1, 2;

-- 2. Create an event to refresh the table every hour
CREATE EVENT refresh_revenue_report
ON SCHEDULE EVERY 1 HOUR
DO
  BEGIN
    TRUNCATE TABLE monthly_revenue_report_cache;
    INSERT INTO monthly_revenue_report_cache
    SELECT YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as revenue
    FROM orders GROUP BY 1, 2;
  END;
```

### Workaround 2: Datenbank-Trigger
Wenn Sie Echtzeit-Materialized-Views in MySQL benötigen, können Sie `AFTER INSERT/UPDATE/DELETE`-Trigger auf der Quelltabelle schreiben, die die Zusammenfassungs-Cache-Tabelle inkrementell aktualisieren.

---

## 4. Feature-Vergleich: MySQL vs. PostgreSQL

| Feature | MySQL 8.0 | PostgreSQL |
| :--- | :--- | :--- |
| Standard Virtual Views (Virtuelle Views) | Nativ unterstützt | Nativ unterstützt |
| Updatable Views (Aktualisierbare Views) | Unterstützt (mit Einschränkungen) | Unterstützt (mit Einschränkungen) |
| Native Materialized Views | *Nicht unterstützt* | Nativ unterstützt |
| Konkurrierender/Blockierungsfreier Refresh | *Nicht unterstützt* | Nativ unterstützt (über `CONCURRENTLY`) |
| View-Sicherheit (DEFINER/INVOKER) | Nativ unterstützt | Nativ unterstützt |

> [!TIP]
> **Sicherheits-Tipp: INVOKER vs. DEFINER**
> Standardmäßig werden Standard-Views in MySQL und PostgreSQL mit den Rechten des Benutzers ausgeführt, der die View *erstellt* hat (`DEFINER`). Dies ist ein mächtiges Feature, mit dem Sie Benutzern Zugriff auf bestimmte Teilmengen von Daten in einer Tabelle gewähren können (z. B. unter Ausschluss einer Passwortspalte), ohne ihnen Leserechte für die gesamte zugrunde liegende Tabelle erteilen zu müssen.

---

## 5. Zusammenfassung & Best Practices

1. **Standard-Views zur Abstraktion verwenden**: Standard-Views eignen sich hervorragend zur Vereinfachung komplexer Abfragen und zur Implementierung von Sicherheitsrollen in der Datenbank.
2. **Materialized Views zum Caching verwenden**: Cachen Sie Daten für rechenintensive Aggregationen auf Systemen mit hoher Leselast physisch.
3. **Aktualisierung immer konkurrierend (concurrently) ausführen**: Erstellen Sie in PostgreSQL-Produktionsumgebungen immer einen eindeutigen Index auf Ihren Materialized Views, damit Sie diese blockierungsfrei aktualisieren können.
4. **Scheduler oder Trigger in MySQL verwenden**: Wenn Sie MySQL verwenden, entwerfen Sie eine Cache-Tabelle unter Verwendung von Events oder Caching auf Anwendungsebene, um Materialized Views zu simulieren.
