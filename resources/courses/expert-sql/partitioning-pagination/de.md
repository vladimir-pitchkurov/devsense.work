# Partitionierung und Seitennummerierung (Pagination)

Wenn Tabellen auf zig oder Hunderte von Millionen Zeilen anwachsen, stoßen Standardabfragen und einfache Seitennummerierungs-Strukturen an ihre Grenzen. Das Ausführen von Updates, Backups und Index-Lookups über riesige Datensätze führt zu hoher Festplattenlatenz. Um zu skalieren, setzen Datenbanken zwei Hauptstrategien ein: **Tabellenpartitionierung (Table Partitioning)** – das physische Aufteilen einer riesigen Tabelle in kleinere Stücke unter der Haube – und **effiziente Seitennummerierung (Pagination)** – das Abrufen paginierter Datensätze, ohne Millionen von Offset-Zeilen scannen zu müssen.

---

## 1. Tabellenpartitionierung: Deklarative Regeln und Pruning

Die Tabellenpartitionierung unterteilt eine einzelne logische Tabelle in mehrere physische Kind-Tabellen (Partitionen). Die Eltern-Tabelle fungiert als Routing-Schnittstelle.

### Arten der Partitionierung
1. **Range-Partitionierung (Bereichs-Partitionierung)**: Ordnet Zeilen Partitionen basierend auf einem Wertebereich zu (z. B. Partitionierung einer Protokolltabelle nach Monat).
2. **List-Partitionierung (Listen-Partitionierung)**: Ordnet Zeilen Partitionen basierend auf expliziten Schlüsselwerten zu (z. B. Partitionierung nach Ländercode).
3. **Hash-Partitionierung**: Verteilt Zeilen über eine feste Anzahl von Partitionen mithilfe einer Hash-Modulo-Funktion. Ideal zum gleichmäßigen Verteilen von Schreiblasten.

### Deklarative Partitionierung in PostgreSQL
Seit Version 10 unterstützt PostgreSQL die deklarative Partitionierung. Partitionen werden mit der `PARTITION BY`-Klausel deklariert.

```sql
-- PostgreSQL: Declarative Range Partitioning
CREATE TABLE app_logs (
    id BIGINT NOT NULL,
    log_date DATE NOT NULL,
    message TEXT
) PARTITION BY RANGE (log_date);

-- Create individual partitions
CREATE TABLE app_logs_y2026m01 PARTITION OF app_logs
    FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');
CREATE TABLE app_logs_y2026m02 PARTITION OF app_logs
    FOR VALUES FROM ('2026-02-01') TO ('2026-03-01');
```

### MySQL-Partitionierungssyntax
MySQL implementiert die Partitionierung direkt innerhalb der Tabellendefinition, ohne dass separate Anweisungen für Kind-Tabellen erforderlich sind.

```sql
-- MySQL: InnoDB Range Partitioning
CREATE TABLE app_logs (
    id BIGINT NOT NULL,
    log_date DATE NOT NULL,
    message TEXT,
    PRIMARY KEY (id, log_date)
) ENGINE=InnoDB
PARTITION BY RANGE COLUMNS(log_date) (
    PARTITION p2026m01 VALUES LESS THAN ('2026-02-01'),
    PARTITION p2026m02 VALUES LESS THAN ('2026-03-01')
);
```

### Partition Pruning (Partitions-Ausschluss)
Der Hauptvorteil der Partitionierung ist das **Partition Pruning** (Partitions-Ausschluss). Der Abfrageoptimierer analysiert den Filter der `WHERE`-Klausel und schließt Partitionen aus, die keine übereinstimmenden Zeilen enthalten können, wodurch vollständige Scans dieser physischen Dateien vermieden werden.

```sql
-- Triggering Partition Pruning
EXPLAIN SELECT * FROM app_logs WHERE log_date = '2026-02-15';
-- PostgreSQL plan will only scan 'app_logs_y2026m02'
-- MySQL plan will list partitions: 'p2026m02'
```

> [!WARNING]
> **Primärschlüssel-Einschränkungen**
> Sowohl in MySQL als auch in PostgreSQL **MUSS** jedes Unique-Constraint oder jeder Primärschlüssel auf einer partitionierten Tabelle alle Spalten des Partitionsschlüssels enthalten. In MySQL können Sie keine eigenständige `PRIMARY KEY (id)` haben, wenn Sie nach `log_date` partitionieren; sie muss als `PRIMARY KEY (id, log_date)` definiert werden. Dies verhindert, dass Datenbanken bei Inserts alle Partitionen scannen müssen, um die Eindeutigkeit durchzusetzen.

---

## 2. Seitennummerierung: Offset vs. Keyset (Cursor)

Das Abrufen von Datenlisten in Seiten ist eine grundlegende Anwendungsanforderung. Der standardmäßige SQL-Ansatz kann jedoch zu schwerwiegenden Performance-Engpässen führen.

### Die Falle der OFFSET-Seitennummerierung
* **Syntax**: `SELECT * FROM orders ORDER BY created_at DESC LIMIT 10 OFFSET 500000;`
* **Unter der Haube**: Die Datenbank-Engine kann nicht direkt zur Zeile 500.000 springen. Sie muss den Index scannen, alle 500.000 vorhergehenden Zeilen lesen, diese verwerfen und erst dann die nächsten 10 Zeilen zurückgeben. Dies verursacht eine hohe CPU-Auslastung und massives Festplatten-I/O.

### Keyset-Seitennummerierung (Cursor-basiert)
* **Syntax**: Verwenden Sie anstelle von Offsets die zuletzt abgerufenen Werte, um nachfolgende Abfragen zu filtern.

```sql
-- MySQL & PostgreSQL: Keyset Pagination
SELECT * FROM orders 
WHERE created_at < '2026-06-17 10:00:00' 
ORDER BY created_at DESC 
LIMIT 10;
```
* **Performance**: Mit einem zusammengesetzten Index (Composite Index) auf `(created_at, id)` führt die Abfrage einen Index-Seek direkt zum Startpunkt aus und läuft unabhängig von der Seitenzahl in $O(1)$-Zeit.

### Mehrspaltige Keyset-Seitennummerierung (Sortierung nach nicht-eindeutigen Feldern)
Wenn das Sortierfeld (wie `created_at` oder `price`) doppelte Werte enthalten kann, müssen Sie eine eindeutige Spalte als Entscheidungshelfer (Tie-Breaker, üblicherweise den Primärschlüssel) anhängen, um zu verhindern, dass Zeilen übersprungen werden.
* **Tupel-Vergleichs-Syntax (PostgreSQL)**:
  ```sql
  -- PostgreSQL supports row value comparisons natively
  SELECT * FROM orders
  WHERE (created_at, id) < ('2026-06-17 10:00:00', 9876)
  ORDER BY created_at DESC, id DESC
  LIMIT 10;
  ```
* **Explizite logische Erweiterung (MySQL)**:
  *MySQL 8.0 unterstützt den Vergleich von Zeilenwerten, ältere Versionen oder schlechtere Optimierer führen dies jedoch ineffizient aus. Erweitern Sie dies zur Sicherheit explizit:*
  ```sql
  -- MySQL Safe Keyset Expansion
  SELECT * FROM orders
  WHERE created_at < '2026-06-17 10:00:00'
     OR (created_at = '2026-06-17 10:00:00' AND id < 9876)
  ORDER BY created_at DESC, id DESC
  LIMIT 10;
  ```

> [!TIP]
> **Seitennummerierung über mehrere Kategorien hinweg mit LATERAL JOIN**
> Wenn Sie eine Seitennummerierung durchführen und die „Top-N-Artikel pro Kategorie“ abrufen müssen (z. B. die Top 3 Produkte für jede von 20 Kategorien), funktioniert das Standard-`GROUP BY` nicht. Verwenden Sie einen `LATERAL` Join (unterstützt in PostgreSQL 10+ und MySQL 8.0.14+).
> ```sql
> SELECT c.name, p.title, p.price
> FROM categories c
> INNER JOIN LATERAL (
>     SELECT title, price 
>     FROM products 
>     WHERE category_id = c.id 
>     ORDER BY price DESC LIMIT 3
> ) p ON TRUE;
> ```
> *Dies führt eine indexgestützte Unterabfrage für jede Kategorie aus, was im Vergleich zu vollständigen Window-Funktions-Scans extrem schnell ist.*

---

## 3. Feature-Vergleichsmatrix

| Feature | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Partitionierungsdefinition** | Deklarative Kind-Tabellen | Direkt auf der Eltern-Tabelle definiert |
| **Zeilen-Routing** | Übernommen durch Eltern-Routing | Intern von InnoDB übernommen |
| **PK-Einschränkung** | PK muss Partitionsschlüssel enthalten | PK muss Partitionsschlüssel enthalten |
| **Zeilenwertvergleich** | Nativ optimiert (z. B. `(a, b) > (x, y)`) | Unterstützt, aber Optimierer-Fallen möglich |
| **Lateral Joins** | Unterstützt (PostgreSQL 9.3+) | Unterstützt (MySQL 8.0.14+) |

---

## 4. Zusammenfassung & Best Practices

1. **Nach Datum partitionieren zur Archivierung**: Die Range-Partitionierung eignet sich hervorragend für Transaktionsprotokolle. Wenn Daten veralten, können Sie die Partition mit `DROP TABLE partitionsname` löschen (sofort), anstatt ein riesiges `DELETE` auszuführen (langsam, erzeugt Undo-Bloat).
2. **Niemals große Offsets verwenden**: Implementieren Sie die Keyset-Seitennummerierung für Infinite-Scroll oder paginierte Listen.
3. **Seitennummerierungsschlüssel indizieren**: Stellen Sie immer sicher, dass Ihre Keyset-Filter mit einem zusammengesetzten B-Tree-Index übereinstimmen (z. B. Index auf `(created_at, id)`).
