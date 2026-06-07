---
title: "Datenbank-Indizes: Blick unter die Haube und Deep Dive | DevSense"
description: "Ein umfassender Leitfaden für Entwickler zu B+-Bäumen in InnoDB, Heap-Strukturen in Postgres, Index-Knoten-Pointern, zusammengesetzten Indizes und Write Amplification."
published: 2026-05-31
faq:
  - question: "Warum verwendet InnoDB einen Clustered Index, während Postgres eine Heap-Struktur nutzt?"
    answer: "InnoDB speichert Zeilendaten direkt in den Blattknoten des B+-Baums des Primärschlüssels, um Primärschlüssel-Abfragen extrem schnell zu machen und zusammengehörige Daten kontinuierlich zu speichern. Postgres speichert alle Daten in einer Heap-Datei und macht alle Indizes (einschließlich des Primärschlüssels) zu Sekundärindizes, die über TIDs auf Heap-Speicherorte verweisen. Dies vereinfacht das Verschieben von Zeilen bei Updates und vermeidet doppelte Abfragen bei Sekundärindizes, erfordert jedoch Heap-Zugriffe für Primärschlüsselabfragen."
  - question: "Was ist ein GIN-Index und wann sollte ich ihn in Postgres verwenden?"
    answer: "Ein Generalized Inverted Index (GIN) ist für die Indizierung von zusammengesetzten Werten wie JSONB oder Arrays konzipiert. Er ordnet einzelne Elemente (Schlüssel oder Werte) ihren entsprechenden TIDs zu und ermöglicht so einen hocheffizienten Abgleich von Abfragen, die Operatoren wie `@>` (enthält) oder `?` (hat Schlüssel) enthalten."
  - question: "Wie kommt es bei der Indexpflege zu Write Amplification (Schreibverstärkung)?"
    answer: "Write Amplification tritt auf, weil jedes INSERT, UPDATE oder DELETE die Datenbank dazu zwingt, sowohl die Tabellendaten als auch alle zugehörigen Indizes zu aktualisieren. Wenn ein Insert in eine volle B-Tree-Seite fällt, muss die Seite außerdem geteilt werden (Page Split), was das Schreiben mehrerer Seiten auf die Festplatte erfordert und zu Speicherfragmentierung führt."
  - question: "Wie kann ich doppelte Lookups in Sekundärindizes unter InnoDB vermeiden?"
    answer: "Um doppelte Lookups zu vermeiden (Sekundärindex-Scan gefolgt von einem Clustered-Index-Lookup über den Primärschlüssel), können Sie einen abdeckenden Index (Covering Index) entwerfen, der alle von der SELECT-Abfrage angeforderten Spalten enthält, sodass die Datenbank die Abfrage vollständig aus dem Sekundärindex bedienen kann."
---

# Datenbank-Indizes: Blick unter die Haube und Deep Dive

Jede Datenbankabfrage, die Sie schreiben, ist ein Wettlauf gegen die Festplatten-I/O. Wenn Ihr Datensatz in den RAM passt, sind Abfragen augenblicklich fertig. Wenn Ihre Daten jedoch auf Millionen von Zeilen anwachsen und auf die Festplatte ausgelagert werden müssen, muss Ihre Datenbank-Engine entweder jede einzelne Seite auf dem Laufwerk durchsuchen (ein sequenzieller Scan) oder eine Karte verwenden, um direkt zu den benötigten Daten zu springen. Diese Karte ist ein Index.

Zu verstehen, wie Indizes auf Festplatten- und Seitenebene funktionieren, unterscheidet Junior-Entwickler, die willkürlich Indizes an Tabellen anhängen, von Datenbankarchitekten, die selbstoptimierende Speicher-Engines mit hohem Durchsatz entwerfen. In diesem Deep Dive werfen wir einen Blick hinter die Abstraktionsschicht von MySQL (InnoDB) und PostgreSQL, um zu sehen, wie sie Indizes auf der Festplatte darstellen, wie die Reihenfolge zusammengesetzter Spalten aufgelöst wird und was die wahren Kosten von Write Amplification (Schreibverstärkung) sind.

---

## 1. Unter der Haube: B+-Baum in InnoDB vs. Heap & B-Baum in Postgres

Datenbank-Engines speichern Tabellen nicht als einfache Arrays von Zeilen. Sie strukturieren sie auf der Festplatte in Form von Seiten (in der Regel 16 KB bei InnoDB, 8 KB bei PostgreSQL). Ihre Speicherarchitekturen unterscheiden sich jedoch grundlegend.

### InnoDB: Der Clustered Index (B+-Baum)
In der InnoDB-Engine von MySQL gilt: **Die Tabelle ist der Index**. Genauer gesagt ist die Tabelle als B+-Baum strukturiert, der um den Primärschlüssel (Primary Key) herum aufgebaut ist. Dies wird als **Clustered Index** bezeichnet.

*   **Innere Knoten (Internal Nodes):** Enthalten nur Schlüssel und Pointer auf Kindseiten. Sie führen die Engine bei der Suche.
*   **Blattknoten (Leaf Nodes):** Enthalten die eigentlichen Datenzeilen. Die Blattseiten sind sequenziell in einer doppelt verketteten Liste verbunden, was Bereichsscans (`WHERE id BETWEEN 10 AND 50`) unglaublich schnell macht.
*   **Sekundärindizes (Secondary Indexes):** Jeder andere Index als der Primärschlüssel in InnoDB ist ein Sekundärindex. Die Blattknoten eines Sekundärindex zeigen *nicht* auf physische Festplattenadressen. Stattdessen speichern sie den **Primärschlüssel-Wert** der Zeile.

> [!NOTE]
> Da InnoDB-Sekundärindizes den Primärschlüssel speichern, erfordert das Nachschlagen einer Zeile über einen Sekundärindex (z. B. `WHERE email = 'user@example.com'`) einen zweistufigen Prozess: Zuerst wird der Sekundärindex durchlaufen, um den Primärschlüssel zu finden, und danach wird der Clustered-Index-B+-Baum durchlaufen, um die Zeilendaten abzurufen.

```
[Secondary Index (Email)]
  Leaf Node: 'user@example.com' -> PK: 42
          |
          v
[Clustered Index (ID)]
  Leaf Node: PK: 42 -> Row Data: {id: 42, email: 'user@example.com', name: 'John Doe'}
```

### PostgreSQL: Der Heap und B-Baum
PostgreSQL verwendet standardmäßig keine Clustered Indizes. Stattdessen speichert es Zeilendaten in einer ungeordneten Struktur, die als **Heap** bezeichnet wird.

*   **Heap-Seiten (Heap Pages):** Tabellenzeilen werden in der Reihenfolge ihres Einfügens (oder dort, wo Platz verfügbar ist) an Seiten angehängt.
*   **B-Tree-Indizes:** Jeder Index in Postgres (einschließlich des Primärschlüssels) ist ein Sekundärindex.
*   **Blattknoten (Leaf Nodes):** Die Blattknoten eines Postgres-B-Tree-Index enthalten einen **Tuple Identifier (TID)**. Dies ist eine physische Adresse auf der Festplatte, die sich aus einer Blocknummer (Seite) und einem Offset-Index innerhalb dieser Seite zusammensetzt (z. B. `(Page 14, Offset 3)`).

> [!WARNING]
> Da Postgres-Indizes physische TIDs speichern, jede Operation, die eine Zeile auf der Festplatte verschiebt (z. B. ein UPDATE, das die Zeilengröße ändert, oder eine MVCC-Seitenmigration), diese TIDs ungültig machen würde. Postgres verwaltet dies mithilfe von Vacuuming und der HOT-Optimierung (Heap Only Tuples), aber es bedeutet, dass alle Indizes direkt auf den Heap verweisen.

```
[Postgres Index (Email)]                         [Postgres Heap (Unordered)]
  Leaf: 'user@example.com' -> TID: (Page 14, Offset 3) ----> Page 14, Slot 3: {id: 42, ...}
```

---

## 2. Regeln für die Spaltenreihenfolge in zusammengesetzten Indizes

Beim Erstellen eines Index über mehrere Spalten – eines **zusammengesetzten Index** (Composite Index) – ist die Reihenfolge der Spalten in der Indexdeklaration entscheidend. Eine falsche Spaltenreihenfolge macht den Index für bestimmte Abfragen völlig nutzlos.

Die goldenen Regeln für den Entwurf zusammengesetzter Indizes lauten:
1.  **Die Leftmost-Prefix-Regel:** Die Datenbank kann einen zusammengesetzten Index nur verwenden, wenn die Abfrage zuerst nach der am weitesten links stehenden Spalte des Index filtert. Ein Index auf `(A, B, C)` kann Abfragen optimieren, die nach `(A)`, `(A, B)` und `(A, B, C)` filtern, aber *nicht* Abfragen, die nur nach `(B)` oder `(C)` filtern.
2.  **Gleichheit zuerst, Bereich zuletzt (Equality First, Range Last):** Spalten, die mit exakter Gleichheit (`=`, `IN`) gefiltert werden, müssen im Index zuerst kommen. Spalten, die mit Bereichen gefiltert werden (`<`, `>`, `BETWEEN`, `LIKE`), müssen zuletzt kommen. Sobald eine Bereichsspalte ausgewertet wird, kann die Datenbank nachfolgende Spalten im Index nicht mehr zur Filterung verwenden.
3.  **Hohe Kardinalität zuerst:** Spalten mit hoher Kardinalität (viele eindeutige Werte, z. B. `user_id`) sollten im Allgemeinen Spalten mit niedriger Kardinalität (wenige eindeutige Werte, z. B. `status`) vorangestellt werden, vorausgesetzt, beide werden mit Gleichheitsoperatoren abgefragt.

Sehen wir uns eine konkrete Migration und Abfrage an:

```sql
-- database/migrations/2026_05_31_create_orders_table.sql
CREATE TABLE orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    status VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP NOT NULL
);

-- database/migrations/2026_05_31_add_composite_index.sql
-- Optimales Index-Layout für: user_id (Gleichheit) + status (Gleichheit) + created_at (Bereich/Sortierung)
CREATE INDEX idx_orders_user_status_created ON orders (user_id, status, created_at);
```

Für die folgende Abfrage ermöglicht der Index der Engine, sofort nach `user_id` zu filtern, dann nach `status` zu filtern und anschließend die vorsortierten `created_at`-Werte in umgekehrter Reihenfolge zu lesen, ohne dass eine separate Filesort-Operation erforderlich ist.

```sql
-- database/queries/find_user_orders.sql
SELECT id, user_id, status, created_at, amount
FROM orders
WHERE user_id = 42
  AND status = 'completed'
ORDER BY created_at DESC
LIMIT 10;
```

---

## 3. Deep Dive in die Index-Typen

Verschiedene Abfragemuster erfordern unterschiedliche Datenstrukturen. Hier ist ein Vergleich der in MySQL und PostgreSQL verfügbaren Index-Typen.

### B-Tree
Das Arbeitstier der Datenbank-Engines. Er unterstützt Gleichheit (`=`), Bereiche (`>`, `<`, `BETWEEN`) und Sortierung (`ORDER BY`). B-Trees bleiben balanciert, wodurch sichergestellt wird, dass Such-, Einfüge- und Löschoperationen alle in $O(\log n)$ Zeit ablaufen.

### Hash
Hash-Indizes speichern einen Hash des indizierten Werts und verweisen auf die entsprechende Zeile.
*   **Postgres:** Unterstützt explizite Hash-Indizes. Sie sind extrem schnell ($O(1)$) für Gleichheitssuchen, unterstützen jedoch keine Bereichsabfragen, Sortierungen oder Teilübereinstimmungen bei mehreren Spalten.
*   **MySQL (InnoDB):** Unterstützt keine explizite Erstellung von Hash-Indizes. Stattdessen wird ein **Adaptive Hash Index** verwendet – ein internes Feature, bei dem InnoDB Abfragemuster auf B-Trees automatisch überwacht und In-Memory-Hashtabellen für sehr häufige Lookups erstellt.

### GIN (Generalized Inverted Index) - Postgres
GIN ist für die Indizierung zusammengesetzter Werte konzipiert, bei denen Sie nach Elementen *innerhalb* des Werts suchen müssen (wie JSONB-Dokumente oder Arrays). GIN bildet einzelne Elemente innerhalb des Dokuments auf deren physische Zeilen-TIDs ab.

```sql
-- database/migrations/2026_05_31_postgres_features.sql
-- Tabelle mit JSONB erstellen und einen GIN-Index in PostgreSQL hinzufügen
CREATE TABLE user_profiles (
    id BIGINT PRIMARY KEY,
    metadata JSONB NOT NULL
);

CREATE INDEX idx_user_metadata_gin ON user_profiles USING GIN (metadata);

-- Diese Abfrage nutzt den GIN-Index, um übereinstimmende Schlüssel sofort zu finden
SELECT * FROM user_profiles 
WHERE metadata @> '{"role": "administrator", "status": "active"}';
```

### Partielle / gefilterte Indizes
Ein partieller Index enthält nur eine Teilmenge der Zeilen einer Tabelle, die durch eine `WHERE`-Bedingung definiert ist. Dies spart massiv Speicherplatz und hält den Index klein und schnell.
*   **Postgres:** Unterstützt partielle Indizes nativ.
*   **MySQL:** Unterstützt partielle Indizes nicht direkt (Stand 8.0). Sie müssen funktionale Indizes mit Ausdrücken verwenden, die zu `NULL` auswerten, um einen ähnlichen Effekt zu erzielen, was weniger elegant ist.

```sql
-- database/migrations/2026_05_31_partial_index.sql
-- Nur Postgres: Nur aktive unbezahlte Bestellungen indizieren, um den Index kompakt zu halten
CREATE INDEX idx_orders_active_unpaid ON orders (user_id) 
WHERE status = 'pending' AND amount > 100.00;
```

### Expressions- / funktionale Indizes
Sie können das Ergebnis einer Funktion oder eines Ausdrucks anstelle des rohen Spaltenwerts indizieren. Dies ist sehr nützlich, wenn Abfragen Manipulationen an Spalten in der `WHERE`-Klausel vornehmen.

```sql
-- database/migrations/2026_05_31_functional_index.sql
-- Einen Expressions-Index erstellen, um die Suche unabhängig von Groß-/Kleinschreibung zu optimieren
-- PostgreSQL:
CREATE INDEX idx_orders_lower_status ON orders (LOWER(status));

-- MySQL 8.0+:
CREATE INDEX idx_orders_lower_status ON orders ((LOWER(status)));
```

### Abdeckende Indizes (Covering Indexes)
Ein abdeckender Index (Covering Index) ist ein Sekundärindex, der alle für eine Abfrage erforderlichen Spalten enthält. Dadurch kann die Datenbank die Abfrage vollständig aus der Indexseite bedienen, ohne die Tabellendaten (Heap oder Clustered Index) anzufassen.
*   In Postgres können Sie Nicht-Schlüssel-Nutzdaten-Spalten explizit mithilfe der `INCLUDE`-Klausel an einen Index anhängen.
*   In MySQL fügen Sie die Spalten einfach den Schlüsseln des zusammengesetzten Index hinzu.

```sql
-- database/migrations/2026_05_31_covering_index.sql
-- Postgres: Index ist nach user_id sortiert, trägt aber die Nutzdaten amount/created_at
CREATE INDEX idx_orders_covering ON orders (user_id) INCLUDE (amount, created_at);
```

---

## 4. Write Amplification & Wartungskosten

Indizes sind nicht kostenlos. Jeder Index, den Sie einer Tabelle hinzufügen, beeinträchtigt die Schreibgeschwindigkeit. Diese Strafe äußert sich als **Write Amplification** (Schreibverstärkung).

### Page Splits in B+-Bäumen
Datenbankseiten werden mit freiem Speicherplatz (Fillfactor) zugewiesen, um zukünftige Updates zu ermöglichen. Wenn Sie eine Zeile einfügen, muss die Datenbank diese in den entsprechenden B-Tree-Indexknoten schreiben. Wenn die Seite des Indexknotens voll ist, kommt es zu einem **Page Split** (Seitenteilung):
1.  Eine neue Seite wird zugewiesen.
2.  Ungefähr 50 % der Schlüssel aus der vollen Seite werden auf die neue Seite verschoben.
3.  Die übergeordnete Seite wird mit einem Pointer auf die neue Seite aktualisiert.

Diese Operation erfordert mehrere Schreibvorgänge auf der Festplatte und führt zu einer Indexfragmentierung, was die sequenzielle Leseleistung beeinträchtigt.

### Vacuuming vs. Purge Lag
Wenn Zeilen aktualisiert oder gelöscht werden, kann der von den alten Datensätzen belegte Speicherplatz aufgrund von MVCC (Multi-Version Concurrency Control) nicht sofort wiederverwendet werden.

*   **Postgres (Autovacuum):** Wenn ein UPDATE durchgeführt wird, schreibt Postgres ein neues Tuple in den Heap und aktualisiert die Indizes, damit sie darauf zeigen. Dadurch bleibt ein "totes Tuple" (Dead Tuple) auf der Heap-Seite zurück. Wenn der Autovacuum-Daemon mit der Bereinigung dieser toten Tuples nicht hinterherkommt, blähen sich die Tabelle und ihre Indizes auf (Bloat), was zu massiven Leistungseinbußen bei Speicher und Festplatte führt.
*   **MySQL InnoDB (Purge Lag):** In InnoDB werden Updates im Clustered Index direkt an Ort und Stelle durchgeführt, und alte Versionen werden in das Undo-Log geschrieben. Sekundärindizes werden jedoch geändert, indem der alte Datensatz als "gelöscht" markiert und der neue Datensatz eingefügt wird. Ein Hintergrundthread namens **Purge Thread** ist dafür verantwortlich, diese als gelöscht markierten Sekundärindex-Datensätze zu entfernen. Wenn die Schreiblast zu hoch ist, wächst der **Purge Lag**, was Speicherplatz verbraucht und Abfragen verlangsamt.

---

## 5. Häufige Fehler

### Fehler 1: Falsche Spaltenreihenfolge in zusammengesetzten Indizes
**Schlechte Praxis:**
Der Entwickler erwartet, dass der Index Abfragen auf beiden Spalten optimiert, ordnet jedoch die Spalte für die Bereichsabfrage zuerst an.
```sql
-- database/queries/bad_composite_order.sql
-- Index definiert als: (created_at, user_id)
CREATE INDEX idx_bad_order ON orders (created_at, user_id);

-- Abfrage:
SELECT * FROM orders 
WHERE created_at >= '2026-01-01 00:00:00' 
  AND user_id = 42;
```
*Warum es schlecht ist:* Der Bereichsfilter auf `created_at` verhindert, dass die Datenbank den `user_id`-Teil des Index verwendet, um die passenden Zeilen zu finden. Die Engine muss den Index für alle Datensätze nach dem Datum scannen und jeden einzelnen auf die Benutzer-ID überprüfen.

**Gute Praxis:**
```sql
-- database/queries/good_composite_order.sql
-- Index definiert als: (user_id, created_at)
CREATE INDEX idx_good_order ON orders (user_id, created_at);

-- Abfrage:
SELECT * FROM orders 
WHERE user_id = 42 
  AND created_at >= '2026-01-01 00:00:00';
```
*Warum es gut ist:* Der exakte Gleichheitsfilter auf `user_id` ermöglicht es der Datenbank, sofort zu den Datensätzen des Benutzers zu springen und dann die geordneten Blattknoten des `created_at`-Index zu verwenden, um den Bereich zu scannen.

### Fehler 2: Indizierung von Spalten mit niedriger Kardinalität
**Schlechte Praxis:**
Hinzufügen eines Index auf eine boolesche Spalte (z. B. `is_active` oder `has_paid`).
```sql
-- database/queries/bad_low_cardinality.sql
CREATE INDEX idx_orders_active ON orders (status); -- status hat nur die Werte 'pending', 'completed'
```
*Warum es schlecht ist:* Wenn die Statuswerte gleichmäßig verteilt sind, hilft der Index nicht. Der Abfrageoptimierer wägt die Kosten für das Durchlaufen des Sekundärindex und das anschließende Durchführen von zufälligen I/O-Lookups im Clustered Index bzw. Heap ab und entscheidet, dass ein sequenzieller Scan der Tabelle schneller ist. Der Index bleibt ungenutzt, verschlechtert aber dennoch die Schreibleistung.

---

## 6. Selbsttest-Quiz

Testen Sie Ihr Verständnis von Index-Architekturen:

### Frage 1
Angenommen, Sie haben einen zusammengesetzten Index `idx_test (A, B, C)` auf einer Tabelle. Welche der folgenden Abfragen kann den Index **NICHT** verwenden?
1. `WHERE A = 1 AND B = 2`
2. `WHERE B = 2 AND C = 3`
3. `WHERE A = 1 AND C = 3`

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort: Abfrage 2 (`WHERE B = 2 AND C = 3`)**

**Erklärung:**
Gemäß der Leftmost-Prefix-Regel muss die Abfrage nach der ersten Spalte des Index (`A`) filtern, um ihn nutzen zu können. Da Abfrage 2 nicht nach `A` filtert, kann die Datenbank die Wurzelknoten des B-Baums nicht durchlaufen und muss einen Full-Table-Scan durchführen. Abfrage 3 *kann* den Index verwenden, aber nur den `A`-Teil; sie findet Datensätze, bei denen `A = 1` ist, und filtert diese dann manuell nach `C = 3`.
</details>

---

### Frage 2
Warum führt das Aktualisieren einer nicht-indizierten Spalte in PostgreSQL manchmal zu einer Index-Schreibverstärkung (Write Amplification), während dies in MySQL InnoDB nicht der Fall ist?

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort:**
Wegen des Unterschieds zwischen Heap- und Clustered-Strukturen.
*   In PostgreSQL gilt: Wenn ein UPDATE keine HOT-Optimierung (Heap Only Tuples) durchführen kann (z. B. weil kein Platz mehr auf der Seite ist), wird eine neue Zeilenversion auf eine andere Seite geschrieben. Dadurch ändert sich die physische Adresse (TID) der Zeile. Folglich müssen *alle* Indizes auf dieser Tabelle aktualisiert werden, um auf die neue TID zu zeigen, was zu einer Index-Schreibverstärkung führt.
*   In MySQL InnoDB verbleibt die Zeile im selben Blattknoten des Clustered Index (es sei denn, der Primärschlüssel selbst wird aktualisiert). Sekundärindizes zeigen auf den Primärschlüsselwert, nicht auf eine physische Festplattenadresse, was bedeutet, dass ihre Blattknoten nicht aktualisiert werden müssen.
</details>

---

### Frage 3
Sie haben eine Tabelle mit 10 Millionen Zeilen. Sie führen die folgende Abfrage aus:
`SELECT user_id, status FROM orders WHERE user_id = 100500;`
Der Index auf `user_id` is definiert als: `CREATE INDEX idx_user ON orders (user_id);`
Wie können Sie diese Abfrage optimieren, um Zugriffe auf die Tabellendatenseiten komplett zu vermeiden?

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort:**
Erstellen Sie einen **abdeckenden Index** (Covering Index), der `status` enthält.
*   In PostgreSQL: `CREATE INDEX idx_user_status ON orders (user_id) INCLUDE (status);`
*   In MySQL (oder PostgreSQL): `CREATE INDEX idx_user_status ON orders (user_id, status);`

**Erklärung:**
Durch das Hinzufügen von `status` zum Index sind alle in den `SELECT`- und `WHERE`-Klauseln abgefragten Spalten vollständig auf der Indexseite enthalten. Die Datenbank-Engine kann einen **Index Only Scan** durchführen und das Nachschlagen im Heap oder Clustered Index komplett umgehen.
</details>
