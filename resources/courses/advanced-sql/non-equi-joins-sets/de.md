# Non-Equi Joins und Mengenoperationen

Die meisten SQL-Tutorials konzentrieren sich stark auf das Verknüpfen von Tabellen mithilfe des Gleichheitsoperators (`ON a.id = b.id`). Reale Datenbankprobleme erfordern jedoch häufig das Abgleichen von Datensätzen basierend auf Bereichen, Intervallen, Ungleichungen oder das Zusammenführen von Datensätzen mithilfe der mathematischen Mengenlehre.

Das Verständnis von **Non-Equi Joins** und **fortgeschrittenen Mengenoperationen** unterscheidet Junior-SQL-Entwickler von erfahrenen Datenbank-Engineers.

---

## 1. Non-Equi Joins: Jenseits der Gleichheit

Ein **Non-Equi Join** ist eine Join-Bedingung, die andere Operatoren als das Gleichheitszeichen (`=`) verwendet, wie z. B. `<`, `>`, `<=`, `>=`, `BETWEEN` oder `!=`.

### Anwendungsfall: Überlappende Datumsintervalle
Stellen Sie sich vor, Sie verwalten Hotelzimmer-Buchungen. Sie müssen prüfen, ob sich eine neue Buchungsanfrage mit bestehenden Buchungen überschneidet.
* **Logik**: Eine Kollision tritt auf, wenn das angeforderte Startdatum vor dem Enddatum einer bestehenden Buchung liegt UND das angeforderte Enddatum nach dem Startdatum der bestehenden Buchung liegt.

```sql
-- MySQL & PostgreSQL
SELECT 
    b1.room_id,
    b1.booking_id AS booking_1,
    b2.booking_id AS booking_2
FROM bookings b1
JOIN bookings b2 ON b1.room_id = b2.room_id
    AND b1.booking_id < b2.booking_id -- Avoid self-matching and duplicate pairs
    AND b1.start_date < b2.end_date 
    AND b1.end_date > b2.start_date;
```

### Anwendungsfall: Bereichsgruppierung (z. B. Preiskategorien)
Sie können Produkte Preiskategorien zuweisen, ohne die Stufen fest zu codieren oder komplexe verschachtelte Schleifen zu verwenden.

```sql
-- MySQL & PostgreSQL
SELECT 
    p.product_name, 
    p.price, 
    t.tier_name
FROM products p
JOIN price_tiers t ON p.price BETWEEN t.min_price AND t.max_price;
```

> [!WARNING]
> **Performance-Falle bei Non-Equi Joins!**
> Während moderne Abfrageoptimierer effiziente **Hash Joins** für Equi-Joins verwenden, können sie diese nicht für Non-Equi-Bedingungen nutzen. Stattdessen weichen sie auf **Nested Loop Joins** oder **Block Nested Loops** aus. Dies kann zu einer Zeitkomplexität von `O(N * M)` führen.
> In PostgreSQL können Sie dies durch die Verwendung von **Bereichstypen (Range Types)** und **GiST-Indizes** abmildern. MySQL verfügt über keine nativen Bereichstypen, was bedeutet, dass Sie zusammengesetzte B-Tree-Indizes sorgfältig optimieren müssen.

---

## 2. Fortgeschrittene Mengenoperationen

Mengenoperationen kombinieren die Ergebnisse von zwei oder mehr Abfragen zu einer einzigen Ergebnismenge.

```
Mengenoperationen:
[Abfrage 1] UNION [Abfrage 2]      --> Gibt alle eindeutigen Zeilen aus beiden Abfragen zurück.
[Abfrage 1] INTERSECT [Abfrage 2]  --> Gibt Zeilen zurück, die in BEIDEN Abfragen vorhanden sind.
[Abfrage 1] EXCEPT [Abfrage 2]     --> Gibt Zeilen aus Abfrage 1 zurück, die NICHT in Abfrage 2 sind.
```

### `UNION` vs. `UNION ALL`
* **`UNION`**: Führt Ergebnismengen zusammen und entfernt Duplikate. Dazu muss die Datenbank die Daten sortieren oder eine temporäre Hash-Tabelle erstellen, was Leistungseinbußen verursacht.
* **`UNION ALL`**: Führt Ergebnismengen zusammen, behält jedoch alle Duplikate bei. Es führt keine Sortierung oder Duplikatbereinigung durch, was es viel schneller macht.

### `INTERSECT` und `EXCEPT` (Mit und ohne `ALL`)
Der SQL-Standard definiert zwei Modi für Mengenoperationen:
1. **Standard (Distinct)**: Entfernt doppelte Zeilen vor der Rückgabe der Ergebnisse.
2. **`ALL`**: Behält die Multiplizität (Kardinalität) der Duplikate bei. Wenn beispielsweise eine Zeile dreimal in Abfrage 1 und zweimal in Abfrage 2 vorkommt:
   - `INTERSECT ALL` gibt sie `MIN(3, 2) = 2`-mal zurück.
   - `EXCEPT ALL` gibt sie `3 - 2 = 1`-mal zurück.

---

## 3. MySQL vs. PostgreSQL: Kompatibilität & Syntax

Dies ist einer der Bereiche, in denen die beiden Datenbank-Engines erheblich voneinander abweichen, insbesondere im Hinblick auf ältere Versionen.

### Unterstützungstabelle für Mengenoperationen

| Operator | PostgreSQL (Alle Versionen) | MySQL 8.0.31+ | MySQL < 8.0.31 |
| :--- | :--- | :--- | :--- |
| `UNION` / `UNION ALL` | Nativ unterstützt | Nativ unterstützt | Nativ unterstützt |
| `INTERSECT` (Distinct) | Nativ unterstützt | Nativ unterstützt | *Nicht unterstützt* |
| `EXCEPT` (Distinct) | Nativ unterstützt | Nativ unterstützt | *Nicht unterstützt* |
| `INTERSECT ALL` | Nativ unterstützt | *Nicht unterstützt* | *Nicht unterstützt* |
| `EXCEPT ALL` | Nativ unterstützt | *Nicht unterstützt* | *Nicht unterstützt* |

### Workarounds für MySQL (Simulation)
Wenn Sie mit MySQL-Versionen älter als 8.0.31 arbeiten, müssen Sie `INTERSECT` und `EXCEPT` mithilfe von Joins oder Unterabfragen simulieren.

#### Simulation von `INTERSECT`:
```sql
-- MySQL < 8.0.31 Equivalent of INTERSECT
SELECT DISTINCT a.email 
FROM users_a a
INNER JOIN users_b b ON a.email = b.email;
```

#### Simulation von `EXCEPT`:
```sql
-- MySQL < 8.0.31 Equivalent of EXCEPT
SELECT DISTINCT a.email 
FROM users_a a
LEFT JOIN users_b b ON a.email = b.email
WHERE b.email IS NULL;
```

> [!TIP]
> **PostgreSQL-Exklusiv: Exclusion Constraints (Ausschluss-Constraints)**
> In PostgreSQL können Sie mithilfe eines `EXCLUSION CONSTRAINT`s mit einem GiST-Index erzwingen, dass sich keine zwei Zeilen in einer Tabelle überschneiden.
> ```sql
> -- PostgreSQL Only: Prevent overlapping bookings at the database schema level
> ALTER TABLE bookings ADD CONSTRAINT no_overlap 
> EXCLUDE USING gist (room_id WITH =, tsrange(start_date, end_date) WITH &&);
> ```
> *In MySQL erfordert die Durchsetzung dieser Regel benutzerdefinierte `BEFORE INSERT/UPDATE`-Trigger.*

---

## 4. Zusammenfassung & Best Practices

1. **`UNION ALL` gegenüber `UNION` bevorzugen**: Sofern Sie Duplikate nicht explizit herausfiltern müssen, verwenden Sie immer `UNION ALL`, um den Sortier-Overhead der Datenbank zu vermeiden.
2. **Achten Sie auf die Joins**: Überprüfen Sie beim Schreiben von Non-Equi Joins den Ausführungsplan (`EXPLAIN`), um sicherzustellen, dass die Datenbank keine langsame Nested-Loop-Schleife über Millionen von Zeilen ausführt.
3. **Kompatibilität handhaben**: Wenn Sie Abfragen für datenbankübergreifende Anwendungen schreiben, vermeiden Sie native `INTERSECT`/`EXCEPT`-Operatoren oder syntaxintensive Simulationen. Verwenden Sie stattdessen `EXISTS`- und `LEFT JOIN`-Strukturen.
