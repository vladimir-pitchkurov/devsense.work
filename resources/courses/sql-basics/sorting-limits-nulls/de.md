# Sortierung, Limits und NULL-Werte

Das Abfragen von Daten in einer zufälligen Reihenfolge ist selten nützlich. In realen Anwendungen müssen Datensätze alphabetisch, numerisch oder chronologisch sortiert dargestellt werden. Darüber hinaus enthalten Datenbanken oft riesige Datenmengen, was es erforderlich macht, die Anzahl der abgerufenen Zeilen zu begrenzen (z. B. für die Seitennummerierung bzw. Pagination). Schließlich müssen Sie verstehen, wie SQL mit fehlenden oder unbekannten Daten umgeht, die durch `NULL` dargestellt werden.

In diesem Kapitel untersuchen wir, wie Sie Daten mithilfe von `ORDER BY`, `LIMIT` und `NULL`-Vergleichen organisieren, einschränken und sicher abfragen können, sowie die unterschiedlichen Herangehensweisen von MySQL und PostgreSQL bei diesen Funktionen.

---

## 1. Ergebnisse sortieren mit `ORDER BY`

Standardmäßig geben relationale Datenbank-Engines Zeilen in einer unbestimmten Reihenfolge zurück (häufig basierend darauf, wie sie physisch auf der Festplatte gespeichert sind). Um eine bestimmte Reihenfolge zu garantieren, müssen Sie die `ORDER BY`-Klausel verwenden.

### Aufsteigende und absteigende Sortierung
* **`ASC` (Ascending)**: Sortiert Werte vom niedrigsten zum höchsten Wert (Standardverhalten).
* **`DESC` (Descending)**: Sortiert Werte vom höchsten zum niedrigsten Wert.

```sql
-- MySQL & PostgreSQL
-- Sort products by price, highest first
SELECT name, price FROM products ORDER BY price DESC;
```

### Sortierung nach mehreren Spalten
Sie können nach mehreren Spalten sortieren. Die Engine sortiert zuerst nach der ersten Spalte, und wenn es doppelte Werte gibt, sortiert sie diese Duplikate nach der zweiten Spalte und so weiter.
```sql
-- MySQL & PostgreSQL
-- Sort by category alphabetically, and then by price descending within each category
SELECT category, name, price 
FROM products 
ORDER BY category ASC, price DESC;
```

---

## 2. Ausgabe einschränken: `LIMIT` und `OFFSET`

Das Abrufen von Millionen von Zeilen kann Ihren Anwendungsserver zum Absturz bringen und Datenbankressourcen blockieren. Um dies zu verhindern, können Sie die Ergebnismenge einschränken.

* **`LIMIT`**: Gibt die maximale Anzahl der zurückzugebenden Zeilen an.
* **`OFFSET`**: Überspringt eine bestimmte Anzahl von Zeilen vor der Rückgabe der Ergebnisse (häufig für die Seitennummerierung verwendet).

```sql
-- MySQL & PostgreSQL
-- Get the second page of products (items 11-20)
SELECT name, price 
FROM products 
ORDER BY price DESC 
LIMIT 10 OFFSET 10;
```

---

## 3. Das Geheimnis der `NULL`-Werte

In SQL repräsentiert `NULL` das Fehlen von Daten, einen unbekannten Wert oder ein fehlendes Attribut. Es ist **nicht** gleichbedeutend mit einer leeren Zeichenkette `''` oder der Zahl `0`.

### Die Falle der dreiwertigen Logik (Three-Valued Logic)
In Standard-Programmiersprachen sind `true` und `false` die einzigen booleschen Zustände. SQL verwendet jedoch eine **dreiwertige Logik**: `true`, `false` und `unknown` (unbekannt, dargestellt durch `NULL`).

Da `NULL` „unbekannt“ bedeutet, können Sie es nicht mit Standard-Operatoren wie `=` oder `!=` vergleichen. Zum Beispiel:
* Ist ein unbekannter Wert gleich 5? **Unbekannt (`NULL`)**.
* Ist ein unbekannter Wert gleich einem anderen unbekannten Wert? **Unbekannt (`NULL`)**.

```sql
-- THIS WILL NOT WORK! It returns zero rows.
SELECT * FROM users WHERE middle_name = NULL;
```

### Korrekte NULL-Vergleiche
Um zu prüfen, ob eine Spalte leer oder gefüllt ist, müssen Sie `IS NULL` oder `IS NOT NULL` verwenden.
```sql
-- MySQL & PostgreSQL
-- Correct way to find users without a middle name
SELECT email FROM users WHERE middle_name IS NULL;

-- Correct way to find users with a middle name
SELECT email FROM users WHERE middle_name IS NOT NULL;
```

---

## 4. Wichtige Unterschiede: MySQL vs. PostgreSQL

### Sortierung von Null-Werten (`NULLS FIRST` vs. `NULLS LAST`)
Wie positioniert die Engine `NULL`-Werte, wenn eine Spalte sortiert wird, die diese enthält?
* **MySQL**: Behandelt `NULL` als den kleinstmöglichen Wert. Bei aufsteigender Sortierung (`ASC`) erscheinen `NULL`-Werte zuerst. Bei absteigender Sortierung (`DESC`) erscheinen `NULL`-Werte zuletzt.
* **PostgreSQL**: Behandelt `NULL` als den größtmöglichen Wert. Bei aufsteigender Sortierung (`ASC`) erscheinen `NULL`-Werte zuletzt. Bei absteigender Sortierung (`DESC`) erscheinen `NULL`-Werte zuerst.

PostgreSQL unterstützt jedoch die Standard-SQL-Überschreibung: `NULLS FIRST` oder `NULLS LAST`. MySQL unterstützt dies nativ nicht.

| Datenbank | Sortierung | Standard-NULL-Position | Benutzerdefinierte Überschreibung |
| :--- | :--- | :--- | :--- |
| **MySQL** | `ASC` | Zuerst | Keine (Erfordert Workaround) |
| **MySQL** | `DESC` | Zuletzt | Keine (Erfordert Workaround) |
| **PostgreSQL** | `ASC` | Zuletzt | `ORDER BY price ASC NULLS FIRST` |
| **PostgreSQL** | `DESC` | Zuerst | `ORDER BY price DESC NULLS LAST` |

#### MySQL-Workaround für die Sortierung von NULL-Werten
Um `NULL`-Werte in MySQL bei einer aufsteigenden Sortierung an das Ende zu zwingen, können Sie eine Prüfung mit einem booleschen Ausdruck verwenden:
```sql
-- MySQL: NULLs sorted last in ascending order
SELECT name, price FROM products ORDER BY price IS NULL ASC, price ASC;
```

### Nicht-standardmäßige `LIMIT`-Syntax
* **MySQL** unterstützt eine verkürzte, durch Kommata getrennte Syntax: `LIMIT offset, row_count`.
* **PostgreSQL** unterstützt diese Syntax nicht und wirft einen Syntaxfehler.

```sql
-- MySQL Only (Shorthand syntax: limit 10 rows, skipping the first 5)
SELECT name FROM products LIMIT 5, 10;

-- PostgreSQL & MySQL Standard (Recommended)
SELECT name FROM products LIMIT 10 OFFSET 5;
```

> [!WARNING]
> **Offset-Performance-Falle!**
> Die Verwendung eines hohen `OFFSET`-Werts (z. B. `LIMIT 10 OFFSET 500000`) zwingt die Datenbank-Engine dazu, 500.000 Zeilen zu scannen und zu verwerfen, bevor sie die 10 von Ihnen angeforderten Zeilen zurückgibt. Dies führt bei großen Tabellen zu schwerwiegenden Leistungseinbußen. Bevorzugen Sie für eine tiefe Seitennummerierung die cursorbasierte Seitennummerierung (Keyset-Pagination) mittels `WHERE id > last_seen_id LIMIT 10`.

> [!TIP]
> **Wussten Sie schon?**
> Der SQL-Standard definiert `FETCH FIRST n ROWS ONLY` anstelle von `LIMIT`. Während sowohl MySQL als auch PostgreSQL `LIMIT` unterstützen, PostgreSQL auch den offiziellen Standard:
> `SELECT name FROM products ORDER BY price DESC FETCH FIRST 10 ROWS ONLY;`

---

## 5. Zusammenfassung & Best Practices

1. **Verwenden Sie `LIMIT` immer zusammen mit `ORDER BY`**: Ohne Sortierung gibt `LIMIT` je nach Zustand der Datenbank eine zufällige Auswahl an Zeilen zurück.
2. **Verwenden Sie niemals `=` mit `NULL`**: Nutzen Sie immer `IS NULL` oder `IS NOT NULL`.
3. **Verwenden Sie standardmäßiges `LIMIT/OFFSET`**: Vermeiden Sie die durch Kommata getrennte `LIMIT`-Schreibweise von MySQL, um die Portabilität der Abfragen zu wahren.
4. **Achten Sie auf die Null-Sortierung**: Denken Sie daran, dass MySQL `NULL`-Werte bei `ASC` an erster Stelle platziert, während PostgreSQL sie an letzter Stelle platziert. Verwenden Sie die PostgreSQL-Modifikatoren `NULLS FIRST/LAST` oder die MySQL-Sortiertricks mit `IS NULL`, wenn ein bestimmtes Verhalten erforderlich ist.
