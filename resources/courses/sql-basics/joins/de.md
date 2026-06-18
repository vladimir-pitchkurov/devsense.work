# Tabellen verknüpfen (Joins)

In einer normalisierten relationalen Datenbank werden Daten auf mehrere Tabellen aufgeteilt, um Redundanz zu vermeiden und die Integrität zu wahren. Anstatt beispielsweise die Details einer Abteilung für jeden Mitarbeiter zu wiederholen, speichern Sie die Mitarbeiter in einer Tabelle und die Abteilungen in einer anderen und verknüpfen sie über einen Fremdschlüssel.

Um die einheitliche Ansicht Ihrer Daten wiederherzustellen, müssen Sie **Joins** verwenden. Ein Join verbindet Spalten aus zwei oder mehr Tabellen basierend auf einer gemeinsamen Spalte zwischen ihnen. In diesem Kapitel werden wir die verschiedenen Arten von Joins und deren Bedingungen kennenlernen und untersuchen, wie PostgreSQL und MySQL diese ausführen.

---

## 1. Visualisierung von Join-Typen

SQL bietet verschiedene Möglichkeiten, Tabellen zu verknüpfen, die jeweils einer eigenen Logik folgen:

| Join-Typ | Beschreibung |
| :--- | :--- |
| **`INNER JOIN`** | Gibt Datensätze zurück, die übereinstimmende Werte in beiden Tabellen aufweisen. |
| **`LEFT JOIN`** | Gibt alle Datensätze aus der linken Tabelle und die übereinstimmenden Datensätze aus der rechten Tabelle zurück. Gibt `NULL` für die rechte Tabelle zurück, wenn keine Übereinstimmung vorliegt. |
| **`RIGHT JOIN`** | Gibt alle Datensätze aus der rechten Tabelle und die übereinstimmenden Datensätze aus der linken Tabelle zurück. Gibt `NULL` für die linke Tabelle zurück, wenn keine Übereinstimmung vorliegt. |
| **`FULL JOIN`** | Gibt alle Datensätze zurück, wenn eine Übereinstimmung in der linken oder der rechten Tabelle vorliegt. |
| **`CROSS JOIN`** | Gibt das kartesische Produkt beider Tabellen zurück (jede Zeile aus Tabelle A wird mit jeder Zeile aus Tabelle B verknüpft). |

---

## 2. Syntax und Beispiele

Nehmen wir an, wir haben zwei Tabellen: `employees` (mit einer Spalte `department_id`) und `departments` (mit einer Spalte `id`).

### Inner Join
Fragt nur Mitarbeiter ab, die einer Abteilung angehören, und nur Abteilungen, die Mitarbeiter haben.
```sql
-- MySQL & PostgreSQL
SELECT e.name AS employee_name, d.name AS department_name
FROM employees e
INNER JOIN departments d ON e.department_id = d.id;
```

### Left Join (Häufigster Outer Join)
Fragt alle Mitarbeiter ab, einschließlich derer, die keiner Abteilung angehören (ihr `department_name` wird als `NULL` zurückgegeben).
```sql
-- MySQL & PostgreSQL
SELECT e.name AS employee_name, d.name AS department_name
FROM employees e
LEFT JOIN departments d ON e.department_id = d.id;
```

### Join-Bedingungen: `ON` vs. `USING`
Wenn die Spalten, über die verknüpft wird, in beiden Tabellen exakt denselben Namen haben (z. B. `department_id` sowohl in `employees` als auch in `departments`), können Sie die sauberere Kurzschreibweise `USING` anstelle von `ON` verwenden.
```sql
-- MySQL & PostgreSQL
SELECT e.name, d.name
FROM employees e
INNER JOIN departments d USING (department_id);
```

---

## 3. Wichtige Unterschiede: MySQL vs. PostgreSQL

### Nativer Support für `FULL OUTER JOIN`
* **PostgreSQL** unterstützt standardmäßige `FULL OUTER JOIN`s (oder einfach `FULL JOIN`) nativ.
* **MySQL** unterstützt `FULL JOIN` NICHT. Wenn Sie versuchen, es zu verwenden, wirft MySQL einen Syntaxfehler.

#### Der MySQL-Workaround für FULL JOIN
Um in MySQL einen Full Outer Join zu erzielen, müssen Sie einen `LEFT JOIN` und einen `RIGHT JOIN` der gleichen Tabellen schreiben und deren Ergebnisse mit dem `UNION`-Operator kombinieren (der doppelte Zeilen automatisch entfernt).

```sql
-- PostgreSQL (Native syntax)
SELECT e.name, d.name
FROM employees e
FULL JOIN departments d ON e.department_id = d.id;

-- MySQL Workaround (Emulation)
SELECT e.name, d.name
FROM employees e
LEFT JOIN departments d ON e.department_id = d.id
UNION
SELECT e.name, d.name
FROM employees e
RIGHT JOIN departments d ON e.department_id = d.id;
```

### Join-Algorithmen und Performance im Hintergrund
Wie berechnen die Datenbank-Engines Verknpfungen? Sie verwenden unterschiedliche interne Algorithmen:
* **PostgreSQL**: Bietet einen hoch entwickelten Optimierer, der seit Jahrzehnten **Nested Loop Joins**, **Hash Joins** und **Merge Joins** unterstützt. Er wählt den besten Algorithmus basierend auf Tabellengröße und Indexverfügbarkeit aus.
* **MySQL**: Historisch gesehen unterstützte MySQL nur **Nested Loop Joins** (die bei großen Tabellen langsam sind, da sie für jede Zeile der ersten Tabelle die zweite Tabelle durchlaufen müssen). MySQL 8.0 führte **Hash Joins** ein, um Join-Operationen bei großen, nicht-indizierten Spalten zu optimieren und nähert sich damit der Join-Performance von PostgreSQL an.

> [!WARNING]
> **Die Falle des kartesischen Produkts!**
> Das Ausführen eines `CROSS JOIN`s (oder das Vergessen einer Join-Bedingung in der älteren SQL-Syntax wie `FROM table_a, table_b`) erzeugt ein kartesisches Produkt. Wenn Tabelle A 10.000 Zeilen und Tabelle B 10.000 Zeilen hat, erzeugt ein CROSS JOIN **100.000.000 (100 Millionen) Zeilen**, was den Server-Arbeitsspeicher sofort erschöpfen und die Datenbank einfrieren kann.

> [!TIP]
> **Wussten Sie schon?**
> Wenn Sie Spalten in einem `LEFT JOIN` filtern, ändert die Platzierung des Filters in der `ON`-Klausel im Vergleich zur `WHERE`-Klausel das Ergebnis völlig.
> - **In `ON`**: Filtert die rechte Tabelle *vor* dem Join. Die linke Tabelle gibt weiterhin alle ihre Zeilen zurück.
> - **In `WHERE`**: Filtert das Ergebnis *nach* dem Join, wodurch Ihr `LEFT JOIN` praktisch in einen einschränkenden `INNER JOIN` umgewandelt wird, da auf Werte in einer Spalte geprüft wird, die andernfalls `NULL` sein könnten.

---

## 4. Zusammenfassung & Best Practices

1. **`LEFT JOIN` gegenüber `RIGHT JOIN` bevorzugen**: Left Joins sind viel einfacher zu lesen und zu visualisieren, da SQL-Abfragen von links nach rechts (und von oben nach unten) gelesen werden.
2. **Vorsicht bei `WHERE` in Outer Joins**: Filtern Sie keine Spalten der rechten Tabelle in der `WHERE`-Klausel, es sei denn, Sie prüfen auf `IS NULL`, um nicht übereinstimmende Zeilen zu finden.
3. **Denken Sie an die `FULL JOIN`-Einschränkung von MySQL**: Verwenden Sie in MySQL den Workaround `LEFT JOIN UNION RIGHT JOIN`.
4. **Explizite Join-Syntax verwenden**: Verwenden Sie immer explizite Join-Schlüsselwörter (`INNER JOIN`, `LEFT JOIN`) anstelle von durch Kommata getrennten Tabellennamen in der `FROM`-Klausel (`FROM table_a, table_b`), um versehentliche kartesische Produkte zu verhindern.
