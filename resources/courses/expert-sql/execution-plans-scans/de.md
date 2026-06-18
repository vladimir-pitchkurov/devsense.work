# Ausführungspläne und Scan-Typen

Um langsame Datenbankabfragen zu optimieren, müssen Sie verstehen, wie die Datenbank Daten abruft. Der Abfrageoptimierer bewertet mehrere Ausführungspfade und erstellt auf der Grundlage von Kostenschätzungen einen **Ausführungsplan (Execution Plan)**. Durch das Analysieren von Ausführungsplänen mithilfe von `EXPLAIN` können Sie Engpässe identifizieren, wie z. B. vollständige Tabellenscans (Full Table Scans), falsche Join-Reihenfolgen oder ignorierte Indizes. PostgreSQL und MySQL verwenden unterschiedliche Terminologien und Techniken zur Visualisierung von Plänen, aber ihre zugrunde liegenden Scan-Methoden teilen wichtige Konzepte.

---

## 1. Ausführungspläne erstellen und lesen

Beide Datenbanken bieten Tools zur Inspektion der Ausführung von Abfragen.

### PostgreSQL: EXPLAIN und EXPLAIN ANALYZE
In Postgres gibt `EXPLAIN` die geschätzten Kosten des Planners zurück. Das Hinzufügen von `ANALYZE` zwingt die Datenbank, die Abfrage auszuführen, wodurch tatsächliche Laufzeiten und reale Zeilenanzahlen ermittelt werden.
* **Kostenmetriken**: Dargestellt als `cost=startup..total` (z. B. `cost=0.00..45.10`). Die Kosten sind eine relative Einheit (wobei `1.0` den Kosten für das sequentielle Lesen einer einzelnen Seite entspricht).
* **Tatsächliche Laufzeiten**: Dargestellt in Millisekunden.

```sql
-- PostgreSQL: Inspect actual execution stats
EXPLAIN (ANALYZE, BUFFERS, COSTS)
SELECT email FROM users WHERE status = 'active';
```
*Die Option `BUFFERS` zeigt die Zugriffe auf Shared-Memory-Blöcke (Hits und Lesezugriffe auf die Festplatte).*

### MySQL: EXPLAIN und EXPLAIN ANALYZE
MySQL verwendete historisch ein tabellarisches Format für `EXPLAIN`. MySQL 8.0 führte `EXPLAIN ANALYZE` ein, das Pläne in einer Baumstruktur mit tatsächlichen Ausführungskosten und -zeiten anzeigt.

```sql
-- MySQL: Visualizing with Tree structure & actual runtimes
EXPLAIN ANALYZE
SELECT email FROM users WHERE status = 'active';
```

---

## 2. Scan-Typen in PostgreSQL

PostgreSQL wählt basierend auf dem Vorhandensein von Indizes, der Tabellengröße und der Datenselektivität aus mehreren Scan-Methoden aus.

```
PostgreSQL Scan-Auswahlfluss:
Selektivität:  Hoch (1-2 Zeilen)     Mittel (5-15%)        Niedrig (Volle Tabelle)
Methode:       [Index Scan]   ->  [Bitmap Scan]   ->  [Seq Scan]
```

### Sequential Scan (Seq Scan)
* **Funktionsweise**: Liest die gesamte Heap-Datei der Tabelle von Anfang bis Ende und wertet die `WHERE`-Klausel für jede Zeile aus.
* **Wann er auftritt**: Wird verwendet, wenn die Abfrage keinen passenden Index hat oder wenn der Planner schätzt, dass das Abrufen des Großteils der Tabelle schneller ist als die Verwendung eines Index.

### Index Scan
* **Funktionsweise**: Scannt den B-Tree-Index, um die physischen Adressen (TIDs) der übereinstimmenden Zeilen zu finden, und ruft dann diese spezifischen Blöcke aus dem Tabellen-Heap ab.
* **Nachteil**: Wenn viele Zeilen übereinstimmen, das Hin- und Herspringen zwischen Indexseiten und Heap-Seiten verursacht zufällige I/O-Engpässe (Random I/O).

### Bitmap Index Scan & Bitmap Heap Scan
* **Funktionsweise**: Wird verwendet, wenn Postgres eine moderate Anzahl von Zeilen abruft.
  1. Der **Bitmap Index Scan** liest den Index und erstellt im Arbeitsspeicher eine Bitmap der übereinstimmenden Heap-Seiten, wobei die TIDs nach ihrer physischen Seitenreihenfolge sortiert werden.
  2. Der **Bitmap Heap Scan** liest die sortierten Seiten sequentiell, was zufällige I/O-Zugriffe und das doppelte Lesen von Seiten verhindert.

### Index Only Scan
* **Funktionsweise**: Ruft Daten direkt aus den Index-Blattknoten ab, ohne den Tabellen-Heap zu besuchen.
* **Einschränkung durch die Visibility Map**: Postgres muss die **Visibility Map** (Sichtbarkeitskarte) prüfen, um sicherzustellen, dass die Seiten nicht von noch nicht bereinigten (unvacuumed) Transaktionen geändert wurden. Wenn eine Seite als „dirty“ markiert ist, muss Postgres dennoch den Heap besuchen, was die Leistung beeinträchtigt.

---

## 3. Scan-Typen in MySQL (InnoDB)

Die `EXPLAIN`-Ausgabe von MySQL verwendet die Spalte `type`, um zu beschreiben, wie Zeilen abgerufen werden. Die Typen, sortiert von schnell nach langsam, umfassen:

### const / system
* Die Tabelle hat höchstens eine übereinstimmende Zeile (z. B. Abfrage eines `PRIMARY KEY` oder `UNIQUE`-Index mit einem konstanten Wert). Dies ist extrem schnell.

### eq_ref
* Wird bei Joins verwendet, wenn MySQL für jede Kombination von Zeilen aus der vorherigen Tabelle genau eine Zeile aus dieser Tabelle liest (tritt bei Primär- oder Unique-Schlüsseln auf).

### ref
* Wird verwendet, wenn Zeilen mit einem nicht-eindeutigen Index abgeglichen werden. Es können mehrere Zeilen übereinstimmen.

### range
* Verwendet einen Index, um einen Bereich von Zeilen auszuwählen (z. B. Abfragen mit `>`, `<`, `BETWEEN` oder `IN`).

### index (Full Index Scan)
* MySQL führt einen vollständigen Scan des Indexbaums durch. Dies entspricht dem Index Only Scan von PostgreSQL. Es vermeidet das Scannen des eigentlichen Tabellenbereichs, liest aber dennoch den gesamten Index.

### ALL (Full Table Scan)
* MySQL liest jede Zeile in der Tabelle von der Festplatte. Dies ist die langsamste Zugriffsart und sollte bei großen Tabellen vermieden werden.

---

## 4. Vergleichsmatrix der Scan-Typen

| Scan-Konzept | PostgreSQL-Name | MySQL (InnoDB) Typ | Beschreibung |
| :--- | :--- | :--- | :--- |
| **Vollständiger Tabellenscan** | `Seq Scan` | `ALL` | Scannt die gesamte Tabelle; hohes Festplatten-I/O. |
| **Index-Lookup** | `Index Scan` | `ref` oder `range` | Durchläuft den Index, ruft dann die Zeile aus dem Tablespace ab. |
| **Index-Only-Lookup**| `Index Only Scan` | `index` | Ruft Daten ausschließlich aus den Index-Blattknoten ab. |
| **Massen-Indexsuche** | `Bitmap Index/Heap Scan` | N/A | Gruppiert TIDs nach Seiten, um den Festplattenzugriff zu optimieren. |
| **Konstanter Lookup** | `Index Scan` (1 Zeile) | `const` | Sofortiger Lookup auf einem eindeutigen Index. |

---

## 5. Zusammenfassung & Best Practices

1. **Verwenden Sie für echte Daten immer ANALYZE**: Ein standardmäßiges `EXPLAIN` zeigt nur Schätzungen. Führen Sie (in sicheren Umgebungen) immer `EXPLAIN ANALYZE` aus, um reale Zeilenanzahlen und Speicherplatzbelegungen zu sehen.
2. **Achten Sie auf Leistungseinbußen beim „Index Only Scan“**: Wenn ein Postgres Index Only Scan eine hohe Anzahl von Heap-Fetches anzeigt, führen Sie `VACUUM` auf der Tabelle aus, um die Visibility Map zu aktualisieren.
3. **Vermeiden Sie den Typ `ALL` in MySQL**: Wenn eine Abfrage auf einer großen Tabelle `type: ALL` oder `Extra: Using join buffer` anzeigt, fügen einen Index hinzu, der die Suchspalten abdeckt.
4. **Tabellenstatistiken aktualisieren**: Wenn der Optimierer einen schlechten Scan-Typ wählt, sind die Tabellenstatistiken möglicherweise veraltet. Führen Sie `ANALYZE TABLE meine_tabelle;` in MySQL oder `ANALYZE meine_tabelle;` in PostgreSQL aus.
