# Indizes: Deep Dive in B-Tree, GIN, BRIN und Clustered Indizes

Indizes sind das wichtigste Werkzeug zur Beschleunigung der Abfrageleistung. Ein blindes Erstellen von Indizes kann jedoch die Schreibleistung beeinträchtigen und enorme Mengen an Festplattenspeicher verbrauchen. Relationale Datenbanken unterstützen mehrere Indextypen, die jeweils für bestimmte Datenverteilungen, Abfragemuster und Speicherplatzanforderungen ausgelegt sind. Das Verständnis von B-Tree-Interna, der Primärschlüssel-Gruppierung (Clustering) in MySQL und fortgeschrittenen Indextypen in PostgreSQL ist für professionelles Datenbanktuning unerlässlich.

---

## 1. B-Tree-Interna und Clustering-Verhalten

Der **B-Tree** (Balanced Tree) ist the Standardindextyp in fast allen relationalen Datenbanken. Er hält die Daten sortiert und ermöglicht das Suchen, den sequentiellen Zugriff sowie das Einfügen und Löschen in logarithmischer Zeit ($O(\log n)$).

### MySQL InnoDB: Clustered vs. Secondary Indizes
In der InnoDB-Engine von MySQL sind alle Tabellen physisch um einen **Clustered Index** herum organisiert.
* **Clustered Index**: Die Blattknoten des Index enthalten die eigentlichen Zeilendaten. Standardmäßig ist dies der `PRIMARY KEY` der Tabelle. Wenn kein Primärschlüssel definiert ist, wählt InnoDB den ersten `UNIQUE`-Index aus, der nur Spalten ohne Nullwerte enthält. Falls keiner existiert, generiert InnoDB eine versteckte 6-Byte-Zeilen-ID.
* **Secondary Index (Sekundärindex)**: Die Blattknoten eines Sekundärindex enthalten *keine* Daten-Pointer. Stattdessen speichern sie den **Primärschlüsselwert** der Zeile.
* **Bookmark Lookup**: Wenn Sie eine Abfrage über einen Sekundärindex ausführen, durchsucht MySQL zuerst den Sekundärindex, um den Primärschlüssel zu finden, und führt dann einen zweiten Lookup im Clustered Index durch, um die Zeile abzurufen.

```
MySQL InnoDB Index-Lookup:
[Sekundärindex-Suche] ---> Gibt Primärschlüsselwert zurück (z. B. ID: 42)
                                 |
                                 v
[Clustered-Index-Suche] ---> Gibt Zeilendaten zurück (Name, E-Mail etc.)
```

### PostgreSQL: Heap-basierte Indizierung
Im Gegensatz zu MySQL verwendet PostgreSQL standardmäßig keine Clustered-Tabellen. Tabellen sind als ein „Heap“ von Seiten organisiert.
* Alle Indizes (einschließlich des Primärschlüssel-Index) sind **Sekundärindizes**.
* Blattknoten eines PostgreSQL-Index zeigen direkt auf die physische Adresse (TID – Tuple ID, bestehend aus Seitennummer und Offset) der Zeile im Tabellen-Heap.
* **Kein Bookmark Lookup**: Postgres springt direkt vom Index-Blattknoten zur Heap-Seite. Das Aktualisieren einer Zeile in Postgres ändert jedoch deren physische Adresse, was eine Aktualisierung aller Indizes erfordert (es sei denn, es findet ein HOT-Update statt).

---

## 2. Fortgeschrittene PostgreSQL-Indextypen

PostgreSQL bietet spezialisierte Indextypen an, die in MySQL keine native Entsprechung haben.

### BRIN (Block Range Index)
* **Funktionsweise**: Anstatt jede einzelne Zeile zu indizieren, unterteilt ein BRIN-Index die Tabelle in physische Blockbereiche (Standard ist 128 Seiten oder 1 MB Daten) und speichert für jeden Bereich nur den **Minimal-** und **Maximalwert**.
* **Einsatzszenario**: Extrem große Tabellen (Hunderte von Gigabytes), bei denen die Daten physisch auf der Festplatte vorsortiert sind (z. B. Auto-Increment-IDs, `created_at`-Zeitstempel).
* **Vorteil**: Unglaublich geringer Speicherplatzbedarf. Ein B-Tree-Index von 10 GB kann oft durch einen 10 MB großen BRIN-Index ersetzt werden.

```sql
-- PostgreSQL: Creating a BRIN index
CREATE INDEX idx_orders_date_brin ON orders USING brin (created_at);
```

### GIN (Generalized Inverted Index)
* **Funktionsweise**: Ordnet Werte (wie Array-Elemente, Wörter im Text oder JSON-Schlüssel) den Zeilen zu, in denen sie vorkommen.
* **Einsatzszenario**: Indizierung von Arrays, JSONB-Dokumenten oder Spalten für die Volltextsuche.

```sql
-- PostgreSQL: GIN index for arrays
CREATE INDEX idx_user_tags ON users USING gin (tags);
```

### GiST (Generalized Search Tree)
* **Funktionsweise**: Eine Vorlage zum Erstellen benutzerdefinierter B-Tree-Strukturen. Wird zur Indizierung geometrischer Koordinaten, Bereichstypen (Range Types) und Netzwerkadressen verwendet.

---

## 3. Covering, Partial und Functional Indizes

### Covering Indizes (Index-Only Scan Optimierung)
Ein abdeckender Index (Covering Index) enthält alle von einer Abfrage angeforderten Spalten. Sie können einem Index-Blattknoten zusätzliche Nutzdaten-Spalten (Payload Columns) über die `INCLUDE`-Klausel hinzufügen.
* **PostgreSQL- & MySQL-Syntax**:
  ```sql
  -- PostgreSQL (using INCLUDE)
  CREATE INDEX idx_users_email_include ON users (email) INCLUDE (username, status);

  -- MySQL (using Composite Index - columns must be ordered)
  CREATE INDEX idx_users_email_cover ON users (email, username, status);
  ```

### Partial Indizes (Teil-Indizes)
Indizieren Sie nur eine Teilmenge von Zeilen, die eine bestimmte Filterbedingung erfüllen. Dies reduziert die Indexgröße und den Schreib-Overhead.
* **Nur PostgreSQL**:
  ```sql
  -- Index only active accounts
  CREATE INDEX idx_users_active_email ON users (email) WHERE status = 'active';
  ```
* *MySQL unterstützt Teil-Indizes nicht nativ. Sie müssen funktionale Indizes mit `CASE WHEN` verwenden, um dieses Verhalten nachzuahmen.*

### Functional Indizes (Funktionale / Ausdrucks-Indizes)
Indizieren Sie das Ergebnis einer Funktion oder eines Ausdrucks anstelle der rohen Spaltenwerte.
* **MySQL- & PostgreSQL-Syntax**:
  ```sql
  -- PostgreSQL
  CREATE INDEX idx_users_lower_email ON users (LOWER(email));

  -- MySQL 8.0+
  CREATE INDEX idx_users_lower_email ON users ((LOWER(email)));
  ```

> [!WARNING]
> **Syntaktische Fallen bei funktionalen Indizes**
> In MySQL 8.0 MÜSSEN Ausdrucks-Indizes in doppelte Klammern eingeschlossen werden: `((ausdruck))`. Das Weglassen der äußeren Klammern führt zu einem Syntaxfehler.

> [!TIP]
> **Index Merging (Index-Zusammenführung)**
> Wenn ein Abfragefilter ein `AND` oder `OR` über mehrere Spalten enthält, können Datenbanken ein **Index Merge** durchführen. Dabei werden mehrere einspaltige Indizes gescannt und die resultierenden Bitmaps geschnitten (Intersect) oder vereinigt (Union). Ein einzelner zusammengesetzter (mehrspaltiger) Index ist jedoch fast immer schneller als das Zusammenführen separater Indizes.

---

## 4. Vergleichsmatrix der Indizierungs-Features

| Feature | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Clustered-Tabellen-Layout** | Nein (Tabellen sind Heap-basiert) | Ja (Tabelle sortiert nach PK-B-Tree) |
| **Primärschlüssel-Lookup** | Index -> Heap-Seite | Direkt am Clustered-B-Tree-Blatt |
| **Teil-Indizes (Partial Indexes)** | Ja (`WHERE`-Klausel) | Nein (Workaround über funktionalen Index) |
| **Covering-Index-Klausel** | Ja (`INCLUDE`) | Nein (Muss als zusammengesetzter Key definiert werden) |
| **Block Range Indizes (BRIN)**| Ja | Nein |
| **Inverted Indizes (GIN)** | Ja | Nein |

---

## 5. Zusammenfassung & Best Practices

1. **Auto-Increment-PK-Overhead in MySQL vermeiden**: Da InnoDB Tabellen nach dem Primärschlüssel sortiert, führt das Einfügen zufälliger UUIDs als Primärschlüssel zu starkem Page-Splitting und Fragmentierung. Verwenden Sie sequentielle IDs oder sortierte UUIDs.
2. **BRIN für große Zeitreihendaten nutzen**: Wenn Sie eine Multi-Gigabyte-Protokolltabelle haben, die nach Zeitstempel sortiert ist, verwenden Sie einen BRIN-Index. Dies spart im Vergleich zu einem Standard-B-Tree Gigabytes an Speicherplatz.
3. **Teil-Indizes für dünn besetzte Spalten verwenden**: Wenn Sie eine Tabelle häufig nach einem seltenen Status abfragen (z. B. `WHERE status = 'retry'`), erstellen Sie einen Teil-Index auf diese Bedingung, um den Index winzig zu halten.
