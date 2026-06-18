# Dialekte, JSON und JSON-Suche

Moderne Anwendungen verarbeiten häufig semistrukturierte Daten wie API-Payloads, dynamische Benutzereinstellungen oder polymorphe Attribute. Traditionell erforderte dies EAV-Anti-Pattern (Entity-Attribute-Value) oder den Wechsel zu NoSQL-Datenbanken. Heute unterstützen relationale Datenbanken JSON nativ mit binären Speicherformaten, Indizierung und umfangreichen Abfragemöglichkeiten. PostgreSQL und MySQL unterscheiden sich jedoch in der JSON-Darstellung, der Pfadsyntax und den Indizierungsmechanismen.

---

## 1. Speicher-Interna: Text- vs. Binär-JSON

Wie JSON gespeichert wird, bestimmt die Abfrageleistung und die Indizierbarkeit.

### PostgreSQL: `json` vs. `jsonb`
PostgreSQL bietet zwei JSON-Datentypen an:
* **`json`**: Speichert Daten als exakte Klartextkopie des eingegebenen JSON. Es behält Leerzeichen, Formatierungen und doppelte Schlüssel bei. Allerdings muss der Text bei jedem Lesevorgang geparst werden, was Suchabfragen verlangsamt.
* **`jsonb`**: Zerlegt das JSON in ein geparstes Binärformat. Es entfernt Leerzeichen, bereinigt doppelte Schlüssel (wobei der letzte beibehalten wird) und sortiert die Schlüssel von Objekten für schnelle Suchvorgänge. Das Parsen erfolgt einmalig bei Schreibvorgängen, was die Indizierung und Suche extrem schnell macht.

### MySQL: Der Datentyp `JSON`
MySQL bietet einen einzigen `JSON`-Typ an. Unter der Haube speichert MySQL JSON in einem Binärformat, das dem `jsonb` von PostgreSQL ähnelt. Es validiert die JSON-Syntax beim Einfügen und ermöglicht einen schnellen Lesezugriff auf Dokumentelemente, ohne den Rohtext erneut parsen zu müssen.

---

## 2. JSON abfragen: Syntax und Pfadausdrücke

Das Extrahieren von Daten aus JSON-Objekten erfordert spezielle Pfadoperatoren und Syntax.

### PostgreSQL-JSON-Operatoren
PostgreSQL bietet Operatoren zum Extrahieren von Daten und zum Testen der Dokumentenstruktur:
* `->` gibt ein JSON-Objekt oder ein Array-Element zurück (behält den Typ `jsonb` bei).
* `->>` gibt das Element als einfachen Text-String zurück.
* `#>` und `#>>` extrahieren verschachtelte Objekte mithilfe eines Pfad-Arrays.
* `@>` prüft auf Enthaltensein (ob das linke JSON das rechte JSON enthält).

```sql
-- PostgreSQL: Querying jsonb
SELECT 
    data -> 'user' ->> 'name' AS username,
    data #>> '{user, profile, age}' AS age
FROM app_logs
WHERE data @> '{"status": "error"}';
```

### MySQL-JSON-Funktionen & -Operatoren
MySQL verwendet Standardfunktionen oder Inline-Operatoren mit der `$`-Pfadsyntax:
* `JSON_EXTRACT(col, 'path')` extrahiert Daten.
* `->` fungiert als Alias für `JSON_EXTRACT`.
* `->>` (Inline-Pfadoperator) extrahiert Daten und entfernt Anführungszeichen aus dem Ergebnis (äquivalent zu `JSON_UNQUOTE(JSON_EXTRACT(...))`).
* `JSON_CONTAINS(target, candidate, [path])` prüft, ob ein Dokument ein anderes enthält.

```sql
-- MySQL: Querying JSON
SELECT 
    data->'$.user.name' AS username,
    data->>'$.user.profile.age' AS age
FROM app_logs
WHERE JSON_CONTAINS(data, '"error"', '$.status');
```

---

## 3. JSON-Dokumente indizieren

Das Scannen jedes JSON-Dokuments in einer Tabelle mit Millionen von Zeilen ist ein Performance-Killer. Wir müssen die Daten indizieren.

### PostgreSQL: GIN (Generalized Inverted Indexes)
Das `jsonb` von PostgreSQL lässt sich vollständig in **GIN-Indizes** integrieren, die jeden Schlüssel und jeden Wert innerhalb des JSON-Dokuments indizieren.
* **Standard-GIN** (`jsonb_ops`): Indiziert Schlüssel, Werte und Pfade. Unterstützt Abfragen, die `@>`, `?`, `?|` und `?&` enthalten.
* **Pfadspezifisches GIN** (`jsonb_path_ops`): Indiziert nur Pfad-Wert-Paare. Erstellt kleinere Indexdateien und ist schneller bei Enthaltensein-Abfragen (`@>`), unterstützt jedoch keine Schlüssel-Existenzprüfungen (`?`).

```sql
-- PostgreSQL: Creating GIN Indexes
CREATE INDEX idx_logs_data ON app_logs USING gin (data);
CREATE INDEX idx_logs_data_path ON app_logs USING gin (data jsonb_path_ops);
```

### MySQL: Virtuelle Spalten & Multi-Valued Indizes
MySQL unterstützt die direkte Indizierung des gesamten JSON-Dokuments nicht. Stattdessen stützt es sich auf zwei Techniken:
1. **Generierte (virtuelle) Spalten + B-Tree**: Extrahieren Sie einen bestimmten JSON-Schlüssel in eine virtuelle Spalte und indizieren Sie diese Spalte.
2. **Multi-Valued-Indizes (mehrwertige Indizes)**: Eingeführt in MySQL 8.0.17. Diese ermöglichen es Ihnen, Arrays innerhalb eines JSON-Dokuments zu indizieren und unterstützen Suchen über `MEMBER OF()`, `JSON_CONTAINS()` und `JSON_OVERLAPS()`.

```sql
-- MySQL: Generated Column Indexing
ALTER TABLE app_logs ADD COLUMN log_status VARCHAR(50) 
    GENERATED ALWAYS AS (data->>'$.status') VIRTUAL;
CREATE INDEX idx_logs_status ON app_logs(log_status);

-- MySQL: Multi-Valued Index on JSON Array
-- If data contains: {"tags": ["admin", "system", "web"]}
CREATE INDEX idx_logs_tags ON app_logs( (CAST(data->'$.tags' AS UNSIGNED ARRAY)) );

-- Query using Multi-Valued Index
SELECT * FROM app_logs WHERE 3 MEMBER OF (data->'$.tags');
```

> [!WARNING]
> **Datentyp-Fallen bei generierten Spalten**
> Achten Sie beim Erstellen virtueller Spalten in MySQL immer darauf, dass der Datentyp mit dem extrahierten Wert übereinstimmt. Wenn Ihr JSON-Feld Ganzzahlen enthält, casten Sie die virtuelle Spalte oder verwenden Sie den richtigen Datentyp. Abweichungen verhindern, dass der Optimierer den Index bei der Abfrageausführung nutzt.

---

## 4. Feature-Vergleichsmatrix

| Feature | PostgreSQL (`jsonb`) | MySQL (`JSON`) |
| :--- | :--- | :--- |
| **Speicherformat** | Sortierte Binärdarstellung | Native Binärdarstellung |
| **Pfadoperator (ohne Anführungszeichen)** | `->>` oder `#>>` | `->>` |
| **Standard-Pfadsprache** | SQL/JSON Path (PostgreSQL 12+) | JSONPath-Syntax (`$.key`) |
| **Containment-Prüfung** | `@>`-Operator | `JSON_CONTAINS()`-Funktion |
| **Vollständige Dokumentindizierung** | Ja (über GIN-Indizes) | Nein (erfordert generierte Spalten / funktionalen Index) |
| **Array-Indizierung** | Ja (integrierter GIN) | Ja (Multi-Valued-Indizes, MySQL 8.0.17+) |

---

## 5. Zusammenfassung & Best Practices

1. **Immer binäre Typen verwenden**: Wählen Sie in PostgreSQL immer `jsonb` statt `json`, es sei denn, Sie speichern und rufen Daten nur ab, ohne sie abzufragen oder zu ändern.
2. **Auf Indizes auslegen**: Verwenden Sie in PostgreSQL GIN-Indizes für flexibles Suchen. Definieren Sie in MySQL virtuelle, generierte Spalten für häufig gesuchte verschachtelte Schlüssel.
3. **Anführungszeichen richtig handhaben**: Seien Sie vorsichtig bei `->` vs. `->>` (oder `JSON_EXTRACT` vs. `JSON_UNQUOTE`). Die Verwendung des falschen Operators hinterlässt Anführungszeichen um String-Werte, was dazu führt, dass Vergleichsprüfungen fehlschlagen.
