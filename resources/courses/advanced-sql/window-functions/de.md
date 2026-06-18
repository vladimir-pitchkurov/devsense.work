# Window-Funktionen: Partitionierung, Sortierung und Framing

Stellen Sie sich vor, Sie erstellen ein Dashboard zur Anzeige einer Liste von Kundentransaktionen. Sie müssen die Transaktionsdetails anzeigen, möchten aber auch eine laufende Summe der Ausgaben für jeden Kunden, deren Rang basierend auf dem Transaktionsbetrag und den Betrag der vorherigen Transaktion einblenden.

Die Verwendung eines standardmäßigen `GROUP BY` fasst Ihre Zeilen zusammen, wodurch individuelle Transaktionsdetails verloren gehen. Dies in der Anwendungslogik zu tun, erfordert das Abrufen aller Datensätze und das Durchlaufen dieser in Schleifen, was langsam und speicherintensiv ist. **Window-Funktionen** lösen dies, indem sie Berechnungen über eine Reihe von Tabellenzeilen durchführen, die mit der aktuellen Zeile in Beziehung stehen, ohne die Ergebnismenge zusammenzufassen.

---

## 1. Die Kernmechanik: Gruppierung vs. Windowing

Im Gegensatz zu `GROUP BY`, das mehrere Zeilen zu einer einzigen Zusammenfassungszeile aggregiert, berechnen Window-Funktionen ein Aggregat oder einen Rang für jede Zeile einzeln, während alle Detailfelder erhalten bleiben.

```
GROUP BY:
[Zeile 1] \
[Zeile 2]  --> [Aggregierte Zeile]
[Zeile 3] /

WINDOW-FUNKTION:
[Zeile 1] --> [Zeile 1] [Berechneter Wert 1]
[Zeile 2] --> [Zeile 2] [Berechneter Wert 2]
[Zeile 3] --> [Zeile 3] [Berechneter Wert 3]
```

### Grundlegende Syntax
```sql
-- MySQL 8.0+ & PostgreSQL
SELECT 
    employee_id, 
    department_id, 
    salary,
    SUM(salary) OVER(PARTITION BY department_id) AS dept_total_salary
FROM employees;
```
* **`PARTITION BY`**: Unterteilt Zeilen in Gruppen (Partitionen), die dieselben Werte teilen. Wenn weggelassen, wird die gesamte Ergebnismenge als eine einzige Partition behandelt.
* **`ORDER BY`**: Definiert die physische Sortierreihenfolge der Zeilen innerhalb jeder Partition. Dies bestimmt, wie Werte sequentiell verarbeitet werden.

---

## 2. Ranking- und Wertefunktionen

Window-Funktionen werden in Aggregate (wie `SUM` oder `AVG`), Ranking-Funktionen (Rangfolgefunktionen) und Werteabfragefunktionen unterteilt.

### Ranking: `ROW_NUMBER()`, `RANK()` und `DENSE_RANK()`
Wenn Werte identisch sind (Gleichstand/Ties), verhalten sich diese Funktionen unterschiedlich:
* **`ROW_NUMBER()`**: Weist eine eindeutige, sequentielle Ganzzahl ab 1 zu. Gleichstände werden willkürlich aufgelöst.
* **`RANK()`**: Weist einen Rang mit Lücken zu. Wenn zwei Zeilen um den 1. Platz konkurrieren, erhalten beide den Rang 1, und der nächste Rang ist 3.
* **`DENSE_RANK()`**: Weist einen Rang ohne Lücken zu. Wenn zwei Zeilen um den 1. Platz konkurrieren, erhalten beide den Rang 1, und der nächste Rang ist 2.

| Mitarbeiter | Gehalt | `ROW_NUMBER()` | `RANK()` | `DENSE_RANK()` |
| :--- | :--- | :--- | :--- | :--- |
| Alice | $10,000 | 1 | 1 | 1 |
| Bob | $10,000 | 2 | 1 | 1 |
| Charlie | $8,000 | 3 | 3 | 2 |
| David | $7,000 | 4 | 4 | 3 |

### Wertefunktionen: `LAG()`, `LEAD()` und `FIRST_VALUE()`
* **`LAG(col, offset, default)`**: Greift auf einen Wert aus einer Zeile mit einem bestimmten physischen Versatz (Offset) *vor* der aktuellen Zeile zu.
* **`LEAD(col, offset, default)`**: Greift auf einen Wert aus einer Zeile mit einem bestimmten physischen Versatz (Offset) *nach* der aktuellen Zeile zu.

```sql
-- Fetch current and previous transaction amount to calculate the difference
SELECT 
    transaction_date,
    amount,
    LAG(amount, 1, 0) OVER(ORDER BY transaction_date) AS prev_amount
FROM transactions;
```

---

## 3. Window-Framing: Das gleitende Fenster (Sliding Window)

Der Window-Frame (Fensterrahmen) definiert eine dynamische Teilmenge von Zeilen innerhalb der Partition, bezogen auf die aktuelle Zeile. Der Rahmen verschiebt sich, während die Datenbank-Engine jede Zeile verarbeitet.

### Frame-Typen: `ROWS` vs. `RANGE` vs. `GROUPS`
* **`ROWS`**: Zählt physische Zeilen relativ zur aktuellen Zeile (z. B. 5 Zeilen davor).
* **`RANGE`**: Zählt logische Werte basierend auf der Spalte in der `ORDER BY`-Klausel. Es schließt alle Zeilen ein, die dieselben Werte wie die aktuelle Zeile teilen (Gleichstände werden zusammen behandelt).
* **`GROUPS`**: Gruppen von doppelten Werten basierend auf der Sortierspalte.

```sql
-- Running Total Frame Example
SUM(amount) OVER(
    PARTITION BY user_id 
    ORDER BY transaction_date
    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
)
```

> [!WARNING]
> **Die Falle von `LAST_VALUE()`!**
> Wenn Sie `LAST_VALUE(salary) OVER(PARTITION BY dept_id ORDER BY salary)` schreiben, erwarten Sie möglicherweise, dass das höchste Gehalt in der Abteilung zurückgegeben wird. Stattdessen wird das Gehalt der aktuellen Zeile zurückgegeben!
> Dies liegt daran, dass bei Vorhandensein von `ORDER BY` der Standardrahmen wie folgt lautet:
> `RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW`
> Um dies zu beheben, geben Sie den Rahmen explizit an:
> `LAST_VALUE(salary) OVER(PARTITION BY dept_id ORDER BY salary RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING)`

---

## 4. MySQL vs. PostgreSQL: Architektonische Unterschiede

Obwohl beide Engines die ANSI-SQL-Standards erfüllen, unterscheiden sie sich in Funktionen, Syntaxerweiterungen und Ausführungsleistung.

### 1. Die `FILTER`-Klausel (Nur PostgreSQL)
PostgreSQL unterstützt die `FILTER`-Klausel bei aggregierten Window-Funktionen, wodurch Sie Zeilen selektiv aggregieren können, ohne komplexe `CASE WHEN`-Konstrukte verwenden zu müssen.
```sql
-- PostgreSQL Only
SELECT 
    department_id,
    COUNT(employee_id) FILTER (WHERE salary > 5000) OVER(PARTITION BY department_id) AS highly_paid_count
FROM employees;
```
In MySQL, you must write:
```sql
-- MySQL Equivalent
SELECT 
    department_id,
    SUM(CASE WHEN salary > 5000 THEN 1 ELSE 0 END) OVER(PARTITION BY department_id) AS highly_paid_count
FROM employees;
```

### 2. Frame-Ausschlüsse (Nur PostgreSQL 11+)
PostgreSQL unterstützt erweiterte Frame-Ausschlussoptionen, mit denen Sie bestimmte Zeilen von der Fensterrahmen-Berechnung ausschließen können.
* `EXCLUDE CURRENT ROW`: Schließt die aktuelle Zeile aus dem Rahmen aus.
* `EXCLUDE GROUP`: Schließt die aktuelle Zeile und alle ihre Sortierpartner (Zeilen mit identischen Sortierwerten) aus.
* *MySQL 8.0 unterstützt keine `EXCLUDE`-Klauseln beim Window-Framing.*

> [!TIP]
> **Performance-Tipp:**
> Window-Funktionen werden in der Endphase der Abfrageverarbeitung ausgeführt (nach `WHERE` und `GROUP BY`). Um sie zu optimieren, erstellen Sie einen zusammengesetzten Index (Composite Index), der den Spalten in `PARTITION BY` und `ORDER BY` entspricht. Dies ermöglicht es der Abfrage-Engine, sortierte Daten direkt abzurufen, wodurch teure Sortieroperationen (Filesorts) vermieden werden.

---

## 5. Zusammenfassung & Best Practices

1. **Zeilenidentität beibehalten**: Verwenden Sie Window-Funktionen, wenn Sie Aggregationsberechnungen neben individuellen Zeilenfeldern benötigen.
2. **Achten Sie auf die Standards**: Denken Sie daran, dass das Hinzufügen von `ORDER BY` den Standard-Window-Frame automatisch ändert, was sich auf kumulative Summen und Funktionen wie `LAST_VALUE()` auswirkt.
3. **Indizes verwenden**: Stellen Sie immer sicher, dass Ihre `PARTITION BY`- und `ORDER BY`-Schlüssel indiziert sind, um zu verhindern, dass die Datenbank temporäre Tabellen auf der Festplatte erstellt.
