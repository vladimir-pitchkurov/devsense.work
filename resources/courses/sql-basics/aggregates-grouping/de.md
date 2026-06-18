# Aggregationen und Gruppierung

Bisher haben wir nur einzelne Zeilen abgerufen. In vielen Szenarien müssen Sie Daten jedoch auf einer höheren Ebene analysieren: den durchschnittlichen Lohn eines Mitarbeiters ermitteln, die Gesamtbestellungen zählen oder das teuerste Produkt in einer bestimmten Kategorie finden.

SQL bietet leistungsstarke **Aggregatfunktionen** und die `GROUP BY`-Klausel, um Tausende von Zeilen zu aussagekräftigen Zusammenfassungen zusammenzufassen. In diesem Kapitel werden wir lernen, wie man Daten gruppiert, diese Gruppen mithilfe der `HAVING`-Klausel filtert und wie sich MySQL und PostgreSQL in ihrer Ausführung unterscheiden.

---

## 1. Aggregatfunktionen

Aggregatfunktionen führen eine Berechnung für eine Reihe von Werten durch und geben einen einzelnen Wert zurück.

* **`COUNT()`**: Gibt die Anzahl der Zeilen zurück.
* **`SUM()`**: Gibt die Summe numerischer Werte zurück.
* **`AVG()`**: Gibt den Durchschnitt numerischer Werte zurück.
* **`MIN()`**: Gibt den kleinsten Wert zurück.
* **`MAX()`**: Gibt den größten Wert zurück.

```sql
-- MySQL & PostgreSQL
-- Calculate count, average price, and max price for all products
SELECT COUNT(*) AS total_products, AVG(price) AS average_price, MAX(price) AS highest_price 
FROM products;
```

### Aggregatfunktionen und `NULL`-Werte

Es ist ein weit verbreiteter Irrglaube, dass Aggregatfunktionen `NULL`-Werte in ihre Berechnungen einbeziehen.
* Die meisten Aggregatfunktionen (wie `SUM`, `AVG`, `MIN`, `MAX`) **ignorieren NULL-Werte vollständig**.
* `COUNT(spaltenname)` zählt nur Zeilen, in denen die angegebene Spalte **nicht NULL** ist.
* `COUNT(*)` zählt jede Zeile, einschließlich Zeilen mit `NULL`-Werten.

```sql
-- If we have 5 users, and only 3 have a middle name:
SELECT COUNT(*) FROM users;            -- Returns 5
SELECT COUNT(middle_name) FROM users;  -- Returns 3
```

---

## 2. Daten gruppieren mit `GROUP BY`

Die `GROUP BY`-Klausel unterteilt die Zeilen einer Tabelle in Gruppen. Die Datenbank-Engine wendet die Aggregatfunktionen dann auf jede Gruppe unabhängig an.

```sql
-- MySQL & PostgreSQL
-- Get average price per product category
SELECT category, AVG(price) AS avg_price 
FROM products 
GROUP BY category;
```

---

## 3. Gruppen filtern mit `HAVING`

Was ist, wenn Sie nur die Kategorien finden möchten, in denen der Durchschnittspreis über 100 $ liegt? Sie könnten versuchen zu schreiben:
```sql
-- THIS WILL FAIL!
SELECT category, AVG(price) FROM products WHERE AVG(price) > 100 GROUP BY category;
```
Dies schlägt fehl, da die `WHERE`-Klausel Zeilen filtert, **bevor** sie gruppiert und aggregiert werden. Die Datenbank-Engine kennt den durchschnittlichen Preis noch nicht, wenn sie die `WHERE`-Bedingung auswertet.

Um Gruppen zu filtern, müssen Sie die `HAVING`-Klausel verwenden, die **nach** der Gruppierung ausgeführt wird.

| Klausel | Wo gefiltert wird | Kann Aggregatfunktionen verwenden? |
| :--- | :--- | :--- |
| **`WHERE`** | Einzelne Zeilen (vor der Gruppierung). | Nein |
| **`HAVING`** | Gruppierte Ergebnisse (nach der Gruppierung). | Ja |

```sql
-- MySQL & PostgreSQL
-- Correct way to filter aggregated groups
SELECT category, AVG(price) AS avg_price 
FROM products 
WHERE is_available = true -- 1. Filters rows
GROUP BY category         -- 2. Groups remaining rows
HAVING AVG(price) > 100;  -- 3. Filters groups
```

---

## 4. Wichtige Unterschiede: MySQL vs. PostgreSQL

### Die strenge Gruppierungsregel
Der SQL-Standard schreibt vor, dass bei der Verwendung von `GROUP BY` jede Spalte in Ihrer `SELECT`-Liste, die nicht von einer Aggregatfunktion umschlossen ist, in der `GROUP BY`-Klausel deklariert werden **muss**.
* **PostgreSQL** setzt diese Regel strikt durch. Wenn Sie eine Spalte auswählen, die nicht in der `GROUP BY`-Klausel enthalten ist (und nicht funktionell von den Primärschlüsseln im `GROUP BY` abhängt), wirft Postgres einen Syntaxfehler.
* **MySQL** verhält sich unter dem standardmäßigen SQL-Modus `ONLY_FULL_GROUP_BY` ähnlich. Wenn dieser Modus jedoch deaktiviert ist, erlaubt MySQL die Auswahl von Spalten, die nicht im `GROUP BY` aufgeführt sind, und gibt den Wert einer zufälligen Zeile für diese Spalten zurück – eine häufige Fehlerquelle.

```sql
-- Violating the strict grouping rule
SELECT category, brand, AVG(price) 
FROM products 
GROUP BY category;
```
* **PostgreSQL**: Fails immediately with `ERROR: column "products.brand" must appear in the GROUP BY clause...`
* **MySQL**: Fails only if `ONLY_FULL_GROUP_BY` is enabled. If disabled, it returns the category, a random brand from that category, and the average price.

### String-Aggregation (`GROUP_CONCAT` vs. `string_agg`)
Wenn Sie Textwerte aus gruppierten Zeilen zu einer einzigen, durch Kommata getrennten Zeichenkette verbinden möchten:
* **MySQL** verwendet die Funktion `GROUP_CONCAT()`.
* **PostgreSQL** verwendet die Funktion `string_agg()` und erfordert eine explizite Syntax für die `ORDER BY`-Klausel, wenn eine Sortierung gewünscht wird.

```sql
-- MySQL: Combines tags per product
SELECT product_id, GROUP_CONCAT(tag_name ORDER BY tag_name SEPARATOR ', ') AS tags
FROM product_tags
GROUP BY product_id;

-- PostgreSQL: Combines tags per product
SELECT product_id, string_agg(tag_name, ', ' ORDER BY tag_name) AS tags
FROM product_tags
GROUP BY product_id;
```

> [!WARNING]
> **Die Null-Falle von `AVG()`!**
> Da `AVG()` `NULL`-Werte ignoriert, kann dies Ihre Geschäftsmetriken verzerren. Wenn Sie beispielsweise den durchschnittlichen Mitarbeiterbonus berechnen und 9 von 10 Mitarbeitern einen `NULL`-Bonus (unbekannt/keiner) haben, berechnet `AVG(bonus)` den Durchschnitt basierend auf dem einzigen Mitarbeiter mit einem Bonus, anstatt die Summe durch 10 zu teilen. Verwenden Sie `COALESCE(bonus, 0)` innerhalb der Aggregation, um `NULL` als `0` zu behandeln.

> [!TIP]
> **Wussten Sie schon?**
> Sie können bedingte Aggregationen durchführen, indem Sie `SUM` oder `COUNT` mit Ausdrücken kombinieren. Im modernen PostgreSQL können Sie die sauberere `FILTER`-Klausel verwenden:
> `SELECT count(*) FILTER (WHERE price > 100) AS expensive_count FROM products;`
> In MySQL müssen Sie eine `CASE`-Anweisung innerhalb der Aggregation verwenden:
> `SELECT SUM(CASE WHEN price > 100 THEN 1 ELSE 0 END) AS expensive_count FROM products;`

---

## 5. Zusammenfassung & Best Practices

1. **SELECT sauber halten**: Stellen Sie sicher, dass jede nicht-aggregierte Spalte in Ihrer `SELECT`-Liste auch in Ihrer `GROUP BY`-Klausel vorhanden ist, um die Kompatibilität zwischen verschiedenen Engines zu gewährleisten.
2. **`WHERE` vs. `HAVING`**: Verwenden Sie `WHERE`, um Rohdaten vor der Aggregation zu filtern, und `HAVING`, um aggregierte Gruppen zu filtern.
3. **NULL-Werte in Metriken behandeln**: Denken Sie daran, dass Aggregationsberechnungen (außer `COUNT(*)`) `NULL` ignorieren. Umschließen Sie Spalten bei Bedarf mit `COALESCE`, um sie standardmäßig auf 0 zu setzen.
4. **Dialekt-Funktionen lernen**: Verwenden Sie `GROUP_CONCAT` in MySQL und `string_agg` in PostgreSQL, wenn Sie Zeichenketten über gruppierte Zeilen hinweg verketten.
