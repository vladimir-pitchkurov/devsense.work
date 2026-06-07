---
title: "Datenbanken unter Last: Abfragen, Indizes, MySQL vs. Postgres, Skalierung | DevSense"
description: "Wie Sie SQL und Schemata optimieren, Indextypen auswählen, wann datenbankseitige Logik zum Nachteil wird, wie sich MySQL und PostgreSQL in der Produktion unterscheiden und was vertikale Skalierung, Replikate, Dekomposition und Sharding wirklich kosten."
published: 2026-04-13
faq:
  - question: "Warum ist Seek-Pagination (Keyset) bei großen Datensätzen schneller als OFFSET-Pagination?"
    answer: "Die OFFSET-Pagination erfordert, dass die Datenbank-Engine alle übersprungenen Zeilen scannt und verwirft (z. B. das Scannen von 100.000 Zeilen, um 20 zurückzugeben). Die Keyset-Seek-Pagination verwendet eine Filterbedingung (z. B. `WHERE (created_at, id) < (?, ?)`) basierend auf der zuletzt gelesenen Zeile. Dadurch kann die Engine direkt zu den Zielzeilen springen, indem sie einen B-Tree-Index verwendet, ohne die übersprungenen Zeilen zu scannen."
  - question: "Wann sollte man einen abdeckenden Index (Covering Index) anstelle eines normalen Index verwenden?"
    answer: "Sie sollten einen abdeckenden Index verwenden (häufig unter Verwendung der `INCLUDE`-Klausel oder eines zusammengesetzten Index, der alle ausgewählten Felder enthält), wenn eine hochfrequente Abfrage nur wenige Spalten liest. Dies ermöglicht es der Datenbank-Engine, das Ergebnis direkt aus der Indexstruktur zurückzugeben (Index Only Scan), ohne ein sekundäres Nachschlagen auf der Festplatte/im Heap für die vollständige Zeile durchzuführen."
  - question: "Was sind die wichtigsten Kompromisse bei der Verwendung von Read Replicas?"
    answer: "Read Replicas entlasten die Primärdatenbank von Lesezugriffen und erhöhen so den Lesedurchsatz. Der Replikations-Lag (Replikationsverzögerung) führt jedoch dazu, dass Replicas veraltete Daten liefern können (Eventual Consistency). Entwickler müssen das Anwendungsrouting so gestalten, dass schreibabhängige Lesezugriffe an die Primärdatenbank geleitet werden, um Konsistenzfehler nach dem Prinzip 'Read-Your-Writes' zu vermeiden."
  - question: "Warum gilt Sharding als letztes Mittel zur Datenbankskalierung?"
    answer: "Sharding teilt Daten auf physisch getrennte Datenbankinstanzen auf, was die Betriebskomplexität massiv erhöht. Es verhindert die Verwendung von shard-übergreifenden Joins, globaler Transaktionsintegrität (ohne langsame Zwei-Phasen-Commits) und einfachen globalen Eindeutigkeitsbeschränkungen, während es gleichzeitig die Schwierigkeit mit sich bringt, Shards neu zu verteilen (Rebalancing), wenn sich die Datenverteilung ändert."
---

# Datenbanken unter Last: Abfrage-Tuning, Indizes, Engines und Skalierungs-Kompromisse

Die meisten Performance-Geschichten sind langweilig, bis sie es plötzlich nicht mehr sind. Das Dashboard sieht bei 95 Millisekunden gut aus, dann startet eine Kampagne, ein Bericht verknüpft sechs Tabellen, und plötzlich teilen sich **Checkout** und **Passwort-Zurücksetzen** eine Warteschlange hinter derselben Storage Engine. Die Lösungen sind selten ein einzelner Regler: Es geht um **weniger Round-Trips**, **Indizes, die zu echten Prädikaten passen**, **ehrliche Kapazitätsberechnungen** und manchmal um das **Eingeständnis, dass eine logische Datenbank nicht unendlich groß sein kann**.

**Verwandte Leitfäden:** [High-load event ingestion](high-load-event-ingestion) · [Message queues compared](message-queues-compared) · [Observability and monitoring](observability-monitoring-laravel)

## Inhalt

* [Messen vor dem „Optimieren“](#measure)
* [Optimierungen auf Abfrageebene mit Beispielen](#queries)
* [Schema-Entscheidungen, die gut altern](#schema)
* [Index-Typen und wann sie helfen](#indexes)
* [Warum schwere Logik innerhalb der Datenbank Teams schadet](#db-logic)
* [MySQL versus PostgreSQL in der Praxis](#mysql-vs-postgres)
* [Skalierungspfade und die damit verbundenen Probleme](#scaling)
* [Dekomposition ohne Märchen](#decomposition)
* [Sharding: Keys, shard-übergreifender Schmerz, Rebalancing](#sharding)
* [Häufige Fehler](#common-mistakes)
* [Checkliste vor dem Sharding oder Hardware-Kauf](#checklist)
* [Selbsttest-Quiz](#self-test-quiz)

---

<a id="measure"></a>
## Messen vor dem „Optimieren“

**Latenz-Perzentile** sind aussagekräftiger als Durchschnittswerte. Ein Mittelwert von 40 ms kann einen Ausreißer verbergen, bei dem **ein Prozent** der Anfragen aufgrund von Sperrwartezeiten oder kalten Caches länger als zwei Sekunden dauert.

Praktische Signale:
* **Slow-Query-Logs** mit einem Schwellenwert, den Sie bei wachsendem Datenbestand anpassen – nicht „alles“ loggen, sonst ignorieren Entwickler das Rauschen.
* **`EXPLAIN` (Postgres)** / **`EXPLAIN` (MySQL 8+)** auf den tatsächlich in der Produktion ausgeführten Query-Templates mit Parameter-Bindings, nicht nur manuell erstellte Literale.
* **Verbindungszahlen** und **Pool-Sättigung**; viele Vorfälle, bei denen die „Datenbank langsam“ ist, sind auf das **Warten auf eine Verbindung** zurückzuführen, nicht auf die Festplatte.

> [!NOTE]
> **Gemeinsamer Zustand (Shared State)**
> Die Datenbank ist ein gemeinsam genutzter Zustand. Alles, was **CPU, Sperren oder I/O** auf dem Primärserver erhöht, konkurriert letztendlich mit allem anderen auf demselben Pfad.

---

<a id="queries"></a>
## Optimierungen auf Abfrageebene mit Beispielen

### Nur abrufen, was benötigt wird

Ein breites `SELECT *` über große Zeilen zwingt die Engine, Bytes zu bewegen, die Sie in PHP oder Node ohnehin verwerfen. Bevorzugen Sie explizite Spalten, insbesondere bei Tabellen mit großen Text- oder JSON-Inhalten.

```sql
-- database/queries/fetch_users.sql
-- Vermeiden (überträgt jede Spalte, einschließlich nicht benötigter Blobs)
SELECT * FROM users WHERE country = 'DE' LIMIT 50;

-- Bevorzugen
SELECT id, email, display_name, created_at
FROM users
WHERE country = 'DE'
ORDER BY created_at DESC
LIMIT 50;
```

### Join-Form und Kardinalität

Nested Loops (verschachtelte Schleifen) sind günstig, wenn die innere Seite **indexbasiert** und klein ist; Hash-Joins glänzen bei größeren Datenmengen – **welche Strategie Sie erhalten**, hängt von der Engine und den Statistiken ab. Wenn ein Join die Zeilenanzahl explodieren lässt, weil eine Beziehung **Many-to-Many ohne Constraint** ist, reparieren Sie das Modell – nicht den Timeout.

### Pagination ohne Scannen der gesamten Tabelle

Die `OFFSET`-Pagination ist trügerisch einfach. Bei großen Offsets scannt die Engine oft **trotzdem** alle übersprungenen Zeilen.

```sql
-- database/queries/offset_pagination.sql
-- Wird langsamer, je größer :offset wird
SELECT id, title, created_at
FROM posts
ORDER BY created_at DESC, id DESC
LIMIT 20 OFFSET 100000;
```

**Keyset- (Seek-) Pagination** verwendet den zuletzt gesehenen Sortierschlüssel:

```sql
-- database/queries/seek_pagination.sql
SELECT id, title, created_at
FROM posts
WHERE (created_at, id) < (:last_created_at, :last_id)
ORDER BY created_at DESC, id DESC
LIMIT 20;
```

Sie benötigen einen **unterstützenden Index**, der zur Sortierreihenfolge passt, beispielsweise `(created_at DESC, id DESC)`.

### `EXISTS` versus `IN` für Existenzprüfungen

Für Muster der Art „Gibt es eine passende Zeile?“ verhalten sich Pläne im Semi-Join-Stil oft sehr gut:

```sql
-- database/queries/exists_lookup.sql
SELECT o.id, o.total_cents
FROM orders o
WHERE EXISTS (
  SELECT 1 FROM order_items i
  WHERE i.order_id = o.id AND i.sku = 'GOLD-001'
);
```

### Aggregationen und Reporting

Schwere `GROUP BY`-Abfragen über rohe OLTP-Tabellen sind ein klassischer Weg, um die **Latenz-Perzentile zu verschlechtern**. Berechnen Sie Daten vorab in **Zusammenfassungstabellen** (Summary Tables), **Materialized Views** (Postgres) oder ein **OLAP-System**, wenn das Unternehmen tatsächlich interaktive Analysen benötigt.

---

<a id="schema"></a>
## Schema-Entscheidungen, die gut altern

* **Typen, die der Realität entsprechen** – Speichern Sie Geldbeträge als **Ganzzahl in der kleinsten Währungseinheit** (z. B. Cents) oder als **Decimal mit expliziter Präzision**, nicht als binäre Floats.
* **Nullwertfähigkeit (Nullability)** – Nullbare Spalten erschweren Indizierung und Statistiken; verwenden Sie sie, wenn „unbekannt“ eine Bedeutung hat, nicht als bequemen Standard.
* **Fremdschlüssel (Foreign Keys)** – Sie kosten beim Schreiben ein wenig und sichern die **referenzielle Integrität**. Teams, die sie für „mehr Geschwindigkeit“ deaktivieren, zahlen oft mit **verwaisten Zeilen** und **nicht-deterministischen Anwendungsfehlern**.
* **Über-Normalisierung vs. Lesepfade** – Die reine Theorie ignoriert, wie oft Sie verknüpfte Daten lesen. Ein gezielt eingesetztes, dokumentiertes **denormalisiertes Feld** kann endlose Joins schlagen – auf Kosten der **Schreibkomplexität** und **Invarianz-Disziplin**.

---

<a id="indexes"></a>
## Index-Typen und wann sie helfen

Nicht jeder Index ist ein B-Tree, und nicht jeder B-Tree hat die richtige Form.

| Idee | Typische Verwendung | Hinweise |
|------|---------------------|----------|
| **B-tree (Standard)** | Gleichheit und Bereich auf sortierbaren Typen | Die meisten „normalen“ Indizes; zusammengesetzte Spalten: **Reihenfolge zählt** – führende Spalten müssen zu häufigen `WHERE`/`ORDER BY` passen. |
| **Hash** | Exakte Gleichheit (wo unterstützt) | Postgres **Hash**-Indizes hatten historisch Replikationseinschränkungen. MySQL stellt Hash-Konzepte hauptsächlich über **MEMORY** und interne **Adaptive Hash**-Mechanismen bereit. |
| **Full-text** | Token-Suche in Texten | MySQL **InnoDB FTS** vs. Postgres **`tsvector` + GIN** – unterschiedliche Parser und Wartungsprofile. |
| **GIN / GiST (Postgres)** | JSONB-Containment, Arrays, Volltext, einige geometrische Typen | Leistungsstark; **Indexaufbau und Bloat** müssen überwacht werden. |
| **Spatial** | Geo-Abfragen | Engine-spezifisch (**PostGIS** auf Postgres; MySQL Spatial-Typen). |

### Zusammengesetzte Indizes: Spaltenreihenfolge

Wenn Abfragen fast immer nach `tenant_id` filtern, ist es meist richtig, diese Spalte **zuerst** zu setzen:

```sql
-- database/migrations/create_composite_index.sql
-- Gut für Abfragen wie: WHERE tenant_id = ? AND status = 'open'
CREATE INDEX orders_tenant_status_idx ON orders (tenant_id, status);
```

### Abdeckende Indizes (Covering Indexes)

Wenn der Index **alle Spalten enthält**, die die Abfrage liest, kann die Engine die Abfrage allein aus dem Index bedienen (**Index Only Scan** in Postgres). Beispielmuster:

```sql
-- database/migrations/create_covering_index.sql
CREATE INDEX sessions_lookup_idx ON sessions (user_id) INCLUDE (last_seen_at);
```

### Partielle / gefilterte Indizes

Wenn ein Prädikat **stabil und selektiv** ist, indizieren Sie nur den heißen Bereich:

```sql
-- database/migrations/create_partial_index.sql
-- Postgres-spezifischer partieller Index
CREATE INDEX invoices_open_due_idx
ON invoices (due_at)
WHERE status = 'open' AND due_at IS NOT NULL;
```

---

<a id="db-logic"></a>
## Warum schwere Logik innerhalb der Datenbank Teams schadet

Stored Procedures, Trigger und komplexe Views sind **keine böse Physik**. Sie sind Entscheidungen über **Deployment und Zuständigkeit**.

Was oft schiefgeht:
* **Versionierung** – Anwendungscode wird über Git, CI und Rolling Deploys bereitgestellt; Datenbank-Routinen leben **woanders**. Der Drift zwischen Branches und Umgebungen wird schmerzhaft.
* **Testing** – Geschäftsregeln in PHP/Java werden in vertrauten Testumgebungen getestet; Trigger, die Zeilen beim Einfügen verändern, sind isoliert **schwerer zu durchschauen**.
* **Portabilität** – Logik in der Anwendung kann zwischen MySQL, Postgres oder anderen Herstellern verschoben werden; Logik in PL/pgSQL oder MySQL Stored Procedures **bindet Sie fest an den Anbieter**.
* **Observability** – Stack-Traces und APM-Spans konzentrieren sich auf die Anwendungsschicht; tiefe Trigger-Ketten äußern sich als **mysteriöse Latenz**, wenn Sie nicht sorgfältig instrumentieren.

Wann datenbankseitige Logik **dennoch** sinnvoll ist:
* **Constraints** – `CHECK`, Fremdschlüssel und **gut gewählte Eindeutigkeit** sichern Invarianten günstiger ab, als darauf zu hoffen, dass jeder Dienst im Code daran denkt.
* **Idempotenz-Guards** – Ein **eindeutiger Index (Unique Index)** auf einem Business-Key schlägt Race Conditions im Stil von „zuerst abfragen, dann einfügen“.

---

<a id="mysql-vs-postgres"></a>
## MySQL versus PostgreSQL in der Praxis

Beide sind produktionsreif. Unterschiede werden dann zum Problem, wenn man davon ausgeht, dass sie austauschbar sind.

| Thema | MySQL (typisch InnoDB) | PostgreSQL |
|-------|------------------------|------------|
| **Speichermodell** | Clustered **Primary Key** organisiert Zeilen; Sekundärindizes zeigen auf PK | **Heap**-Tabellen; Indizes sind separat; **CLUSTER** ist eine Wartungsoperation |
| **MVCC / Garbage** | **Undo**-Historie; **Purge Lags** können bei langen Transaktionen wichtig sein | **Tote Zeilen (Dead Tuples)**; **VACUUM** (Autovacuum) ist das tägliche Brot im Betrieb |
| **SQL-Features** | Solides Core-SQL; historisch strenger an einigen Stellen | Umfangreichere **CTEs**, **Window-Funktionen**, **LATERAL**, **JSONB**-Operatoren, **Range-Typen** |
| **Erweiterungen** | Weniger im Core; Ökosystem über Plugins | **PostGIS**, **pgvector**, **Citext**, viele andere |
| **Replikation** | Ausgereiftes **Binlog**-Streaming; viele gehostete Topologien | **Physische** und **logische** Replikation; **Publication/Subscription** |

Abfragebeispiele, die sich unterscheiden:

Postgres – **JSONB-Containment**:
```sql
-- database/queries/postgres_jsonb_containment.sql
SELECT id FROM events WHERE payload @> '{"kind":"purchase"}'::jsonb;
```

MySQL – **JSON**-Extraktion (8.x):
```sql
-- database/queries/mysql_json_extract.sql
SELECT id FROM events WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.kind')) = 'purchase';
```

---

<a id="scaling"></a>
## Skalierungspfade und die damit verbundenen Probleme

### Vertikale Skalierung („größere Maschine“)
Einfach, bis die Physik Grenzen setzt. Festplatten-Bandbreite und CPU-NUMA-Effekte treten auf. Zudem konzentrieren Sie den Single Point of Failure.

### Read Replicas
* **Vorteile:** Entlasten bei **leseintensivem** Traffic, Backups, Klonen für das Reporting.
* **Nachteile:** **Replikationsverzögerung (Replication Lag)** bedeutet, dass Lesezugriffe veraltet sein können.

### Connection Pooling
Das Öffnen einer TCP+Auth-Session pro HTTP-Anfrage skaliert nicht. Tools wie **PgBouncer** (Postgres) oder ProxySQL (MySQL) sitzen zwischen Anwendung und Datenbank.

### Caching (Redis etc.)
Caches maskieren häufig abgefragte Schlüssel (Hot Keys); sie lösen jedoch nicht die **Schreibverstärkung (Write Amplification)** in der Primärdatenbank.

---

<a id="decomposition"></a>
## Dekomposition ohne Märchen

**Schema-pro-Service** ist keine Magie – es bedeutet **weniger unbeabsichtigte Joins** und einen **klareren Schadensradius**. Es bringt jedoch **verteilte Transaktionen** oder **Sagas** mit sich, wenn eine Benutzeraktion tatsächlich mehrere Datenspeicher betrifft.

Anti-Pattern: **Zwei Dienste schreiben in dieselbe Tabelle** über eine „gemeinsam genutzte Datenbank“ – Sie haben einen **verteilten Monolithen** mit zusätzlichen Netzwerk-Hops gebaut.

---

<a id="sharding"></a>
## Sharding: Keys, shard-übergreifender Schmerz, Rebalancing

**Sharding** verteilt Zeilen über **viele Primärdatenbanken** hinweg mithilfe eines **Shard-Keys** (oft User-ID oder Tenant-ID).

Was sich verbessert:
* Die Obergrenzen für den **Schreibdurchsatz** pro Maschine sinken, da jeder Shard nur ein Segment besitzt.
* Der **Schadensradius (Blast Radius)** kann schrumpfen, wenn ein Ausfall einzelne Shards isoliert.

Was schmerzt:
* **Shard-übergreifende Joins** und **globale Eindeutigkeit** – Sie benötigen Koordination oder Regeln auf Anwendungsebene.
* **Rebalancing** (Neiverteilung), wenn ein sehr aktiver Mandant einen Shard dominiert – Resharding ist betrieblich hochkomplex.

---

<a id="common-mistakes"></a>
## Häufige Fehler

1. **Nutzung von OFFSET für große Datensätze**: Verwendung der standardmäßigen `LIMIT ... OFFSET`-Pagination, wodurch die Engine gezwungen wird, Millionen von Zeilen zu scannen, um nur wenige zurückzugeben.
2. **Ignorieren der Spaltenreihenfolge in zusammengesetzten Indizes**: Platzieren einer Spalte mit Bereichsfiltern (z. B. `created_at`) vor Spalten mit Gleichheitsfiltern in Verzeichnissen zusammengesetzter Indizes.
3. **Gemeinsam genutztes Datenbankmuster in Microservices**: Mehreren Diensten das Schreiben in dieselbe Tabelle erlauben, was zu enger Kopplung und Integrationsengpässen führt.
4. **Schreiben von Geschäftslogik in Triggern**: Platzieren komplexer Validierungen und Mutationen in Triggern, wodurch Tests, CI und Tracing umgangen werden.

---

<a id="checklist"></a>
## Checkliste vor dem Sharding oder Hardware-Kauf

1. **Kann `EXPLAIN` einen sequenziellen Scan aufzeigen**, den ein passender Index beheben würde?
2. **Kommen N+1-Abfragen** aus der ORM-Schicht, unabhängig von der Festplattengeschwindigkeit?
3. **Liegt der Engpass bei den Verbindungen** oder der CPU der Datenbank – oder an **Sperrkonflikten (Lock Contention)** durch lange Transaktionen?
4. **Haben Sie die Replikationsverzögerung gemessen**, falls Sie Lesezugriffe auf Replicas auslagern?
5. **Ist das Datenwachstum durch Aufbewahrungsfristen (Retention) begrenzt** (Archivierung kalter Partitionen), was günstiger wäre als eine neue Topologie?

---

## Zusammenfassung

Datenbanken belohnen **unspektakuläre Korrektheit** und **Messung**. Indizes und die Wahl der Engine verschaffen Ihnen Zeit; die **Architektur** – Warteschlangen, separate Lesemodelle und manchmal Sharding – verschafft Ihnen **Skalierungspotenzial**.

---

<a id="self-test-quiz"></a>
## Selbsttest-Quiz

### Frage 1: Warum wird eine Abfrage wie `SELECT * FROM users ORDER BY created_at DESC LIMIT 1` auf einer Tabelle mit 10 Mio. Zeilen langsam ausgeführt, selbst wenn `created_at` indiziert ist?
- A) Indizes können nicht in absteigender Reihenfolge gescannt werden.
- B) Wenn die Spalte nullbar ist, scannt die Datenbank-Engine möglicherweise die gesamte Tabelle, um nach NULL-Werten zu suchen.
- C) Sie müssen einen abdeckenden Index mit `INCLUDE` verwenden.

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort: B**
Wenn die sortierte Spalte nullbar ist und die Abfrage NULL-Werte nicht herausfiltert (z. B. `WHERE created_at IS NOT NULL`), fällt die Engine möglicherweise auf einen Full-Table-Scan zurück, um Nullwerte zu finden, und umgeht den Index-Scan.
</details>

---

### Frage 2: Welcher Index-Typ eignet sich am besten für die Suche nach Schlüsseln in beliebigen JSON-Dokumenten in PostgreSQL?
- A) B-Tree Index.
- B) GIN (Generalized Inverted Index) Index.
- C) Hash Index.

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort: B**
GIN-Indizes sind speziell für die Indizierung mehrwertiger Elemente wie Arrays und JSONB-Strukturen konzipiert und ermöglichen schnelle Containment-Abfragen.
</details>
