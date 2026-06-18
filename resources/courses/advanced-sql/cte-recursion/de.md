# Common Table Expressions (CTE) & Rekursion

Stellen Sie sich vor, Sie debuggen eine 200 Zeilen lange SQL-Abfrage voller tief verschachtelter Unterabfragen, bei der dieselbe Unterabfrage dreimal dupliziert wird, nur um Self-Joins durchzuführen. Dies ist ein Albtraum für die Wartung, und die Abfrageoptimierer der Datenbank tun sich schwer, sie zu optimieren. Oder denken Sie an die Darstellung eines Organigramms (Vorgesetzte und Untergebene) oder eines mehrstufigen Produktkategoriebaums. In imperativen Sprachen wie PHP oder JavaScript würden Sie alle Zeilen abrufen und rekursive Schleifen ausführen. Dies über das Netzwerk zu tun, ist jedoch langsam und äußerst ineffizient. Hier schaffen **Common Table Expressions (CTEs)** und **rekursive CTEs** Abhilfe.

Eine CTE fungiert als temporäre, benannte Ergebnismenge, die nur innerhalb des Ausführungsbereichs einer einzelnen Abfrage existiert. Sie verhält sich wie eine dynamische, gut lesbare Inline-View.

---

## 1. Nicht-rekursive & sequentielle CTEs

### Syntax und Struktur

* **Punkt**: CTEs ermöglichen es Ihnen, temporäre Ergebnismengen mithilfe der `WITH`-Klausel vor Ihrer Hauptanweisung (`SELECT`, `INSERT`, `UPDATE` oder `DELETE`) zu definieren.
* **Warum es wichtig ist**: Sie zerlegt komplexe Abfragen in logische, lesbare Schritte, ersetzt verschachtelte Unterabfragen und macht den Code selbstdokumentierend.
* **Beispiel**:
  ```sql
  -- MySQL & PostgreSQL
  WITH regional_sales AS (
      SELECT region, SUM(amount) AS total_sales
      FROM orders
      GROUP BY region
  ),
  top_regions AS (
      SELECT region
      FROM regional_sales
      WHERE total_sales > 100000
  )
  SELECT o.employee_id, o.amount, o.region
  FROM orders o
  JOIN top_regions t ON o.region = t.region;
  ```
* **Auswirkung**: Die Abfrage liest sich linear von oben nach unten. Sie vermeiden die Duplizierung von Unterabfragen, und das Debuggen wird so einfach wie das Selektieren aus einer einzelnen CTE.

> [!TIP]
> **Wussten Sie schon?**
> CTEs können verwendet werden, um Schreiboperationen zu isolieren. In PostgreSQL können Sie Daten in einer CTE schreiben und die resultierenden IDs auswählen, um sie in derselben Abfrage in eine andere Tabelle einzufügen.
> ```sql
> -- PostgreSQL Only: Writing in a CTE
> WITH inserted_user AS (
>     INSERT INTO users (name, email)
>     VALUES ('Alice', 'alice@devsense.work')
>     RETURNING id
> )
> INSERT INTO profiles (user_id, bio)
> SELECT id, 'Software Engineer' FROM inserted_user;
> ```
> *MySQL unterstützt keine datenverändernden Anweisungen (INSERT/UPDATE/DELETE) innerhalb von CTEs.*

---

## 2. Rekursive CTEs (`WITH RECURSIVE`)

Wenn Sie hierarchische Datenstrukturen durchlaufen müssen, scheitern Standard-SQL-Joins, da die Tiefe des Baums unbekannt ist. **Rekursive CTEs** lösen dies, indem sie eine Abfrage wiederholt ausführen, bis keine neuen Zeilen mehr zurückgegeben werden.

### Die Anatomie der Rekursion

Eine rekursive CTE besteht aus drei Teilen:
1. **Anker-Element (Anchor Member)**: Die Basisabfrage, die die Ergebnismenge initialisiert (wird einmal ausgeführt).
2. **Rekursives Element (Recursive Member)**: Die Abfrage, die sich auf die CTE selbst bezieht und mit dem Ergebnis des vorherigen Schritts verknüpft wird.
3. **Abbruchbedingung (Termination Condition)**: Wird implizit ausgelöst, wenn das rekursive Element null Zeilen zurückgibt.

```
Ausführungsfluss:
[Anker-Abfrage] ---> Initiale Zeilen
        |
        +---> [Rekursive Abfrage] (Läuft auf Anker-Zeilen) ---> Schritt-1-Zeilen
                    |
                    +---> [Rekursive Abfrage] (Läuft auf Schritt-1-Zeilen) ---> Schritt-2-Zeilen
                                |
                                +---> Gibt leere Menge zurück ---> TERMINIEREN
```

### Praktisches Beispiel: Organisationshierarchie

* **Punkt**: Rekursives Durchlaufen einer Tabelle, die Eltern-Kind-Beziehungen enthält.
* **Beispiel**:
  ```sql
  -- MySQL & PostgreSQL
  WITH RECURSIVE org_chart AS (
      -- 1. Anchor: Find the CEO
      SELECT id, name, manager_id, 1 AS depth
      FROM employees
      WHERE manager_id IS NULL
      
      UNION ALL
      
      -- 2. Recursive Member: Join employees with their managers
      SELECT e.id, e.name, e.manager_id, o.depth + 1
      FROM employees e
      INNER JOIN org_chart o ON e.manager_id = o.id
  )
  SELECT * FROM org_chart ORDER BY depth;
  ```
* **Auswirkung**: Sie rufen den gesamten Baum der Untergebenen, komplett mit ihrer Hierarchietiefe, in einer einzigen Abfrage ab, die vollständig in der Datenbank ausgeführt wird.

> [!WARNING]
> **Schutz vor Endlosschleifen!**
> Wenn Ihre Daten einen Zirkelbezug enthalten (z. B. Mitarbeiter A berichtet an B, B berichtet an C, C berichtet an A), läuft eine rekursive CTE unendlich, was den Arbeitsspeicher des Servers erschöpft.
> - **PostgreSQL** bietet die `CYCLE`-Klausel, um Schleifen zu verhindern:
>   `CYCLE id SET is_cycle USING path`
> - **MySQL** hat keine `CYCLE`-Klausel, ermöglicht es Ihnen jedoch, die Rekursionstiefe global oder pro Abfrage zu begrenzen:
>   `SET max_sp_recursion_depth = 255;` oder unter Verwendung von Optimierer-Hinweisen (Optimizer Hints): `/*+ MAX_EXECUTION_TIME(1000) */`

---

## 3. Datenbank-Interna: Materialisierung & Optimierung

Wie führen Datenbank-Engines CTEs aus? Führen sie diese als temporäre Tabellen aus, oder fügen sie deren SQL direkt in die Hauptabfrage ein? Die Engines verhalten sich unterschiedlich:

### PostgreSQL-Materialisierungsregeln

* **PostgreSQL < 12**: Historisch gesehen behandelte Postgres alle CTEs als „Optimierungsbarrieren“ (Optimization Fences). Es führte die CTE immer zuerst aus, speicherte die Ergebnisse in einer temporären Tabelle (materialisierte sie) und verknüpfte sie anschließend. Dies verhinderte, dass der Optimierer äußere `WHERE`-Filter nach unten in die CTE verschieben konnte, was zu erheblichen Performance-Engpässen führte.
* **PostgreSQL 12+**: Änderte das Standardverhalten in **NOT MATERIALIZED**. Wenn eine CTE nur einmal aufgerufen wird, fügt Postgres deren Logik in die Hauptabfrage ein (Inlining), sodass sie Index-Scans nutzen kann.
* **Manuelle Überschreibung**:
  - `WITH cte AS MATERIALIZED (...)` zwingt Postgres, sie einmal auszuwerten und das Ergebnis zu cachen.
  - `WITH cte AS NOT MATERIALIZED (...)` zwingt Postgres, sie inline einzufügen.

### MySQL Inline-Kostenberechnung

* **MySQL 8.0**: Verwendet einen kostenbasierten Optimierer, um zu entscheiden, ob eine CTE inline eingefügt oder in eine temporäre Tabelle materialisiert werden soll. Wenn die CTE einfach ist, bettet MySQL sie immer inline ein. Wenn sie mehrfach referenziert wird, materialisiert MySQL sie, um wiederholte Ausführungen zu vermeiden.

---

## 4. Zusammenfassung & Best Practices

1. **CTEs für die Lesbarkeit nutzen**: Ersetzen Sie komplexe verschachtelte Unterabfragen durch sequentielle, benannte CTEs.
2. **Achten Sie auf die Optimierungsbarriere**: Wenn Sie PostgreSQL verwenden, seien Sie vorsichtig bei mehrfachen Verweisen auf dieselbe CTE. Verwenden Sie `AS NOT MATERIALIZED`, wenn Sie möchten, dass der Optimierer Index-Scans nach unten durchreicht.
3. **Schutz vor Zyklen-Explosion**: Überprüfen Sie Ihre Daten immer auf Zirkelbezüge, bevor Sie `WITH RECURSIVE` ausführen, oder legen Sie Ausführungslimits fest.
