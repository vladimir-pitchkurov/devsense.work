# Dialects, JSON, and JSON Search

Modern applications frequently handle semi-structured data, such as API payloads, dynamic user settings, or polymorphic attributes. Traditionally, this required EAV (Entity-Attribute-Value) anti-patterns or switching to NoSQL databases. Today, relational databases natively support JSON with binary storage formats, indexing, and rich querying capabilities. However, PostgreSQL and MySQL approach JSON representation, path syntax, and indexing mechanisms differently.

---

## 1. Storage Internals: Text vs. Binary JSON

How JSON is stored dictates query performance and index viability.

### PostgreSQL: `json` vs. `jsonb`
PostgreSQL offers two JSON data types:
* **`json`**: Stores data as an exact plain-text copy of the input JSON. It preserves whitespace, formatting, and duplicate keys. However, it requires parsing the text on every read operation, making search queries slow.
* **`jsonb`**: Decomposes the JSON into a parsed binary format. It strips whitespace, removes duplicate keys (keeping the last one), and sorts object keys for fast lookups. Parsing occurs once during write operations, making indexing and searching extremely fast.

### MySQL: The `JSON` Data Type
MySQL provides a single `JSON` type. Under the hood, MySQL stores JSON in a binary format similar to PostgreSQL's `jsonb`. It validates JSON syntax on insert and allows rapid read-access to document elements without reparsing the raw text.

---

## 2. Querying JSON: Syntax and Path Expressions

Extracting data from JSON objects requires specific path operators and syntax.

### PostgreSQL JSON Operators
PostgreSQL provides operators to extract data and test document structure:
* `->` returns a JSON object or array element (retaining type `jsonb`).
* `->>` returns the element as a plain text string.
* `#>` and `#>>` extract nested objects using a path array.
* `@>` tests containment (whether the left JSON contains the right JSON).

```sql
-- PostgreSQL: Querying jsonb
SELECT 
    data -> 'user' ->> 'name' AS username,
    data #>> '{user, profile, age}' AS age
FROM app_logs
WHERE data @> '{"status": "error"}';
```

### MySQL JSON Functions & Operators
MySQL uses standard functions or inline operators using the `$` path syntax:
* `JSON_EXTRACT(col, 'path')` extracts data.
* `->` acts as an alias for `JSON_EXTRACT`.
* `->>` (inline path operator) extracts data and unquotes the result (equivalent to `JSON_UNQUOTE(JSON_EXTRACT(...))`).
* `JSON_CONTAINS(target, candidate, [path])` checks if a document contains another.

```sql
-- MySQL: Querying JSON
SELECT 
    data->'$.user.name' AS username,
    data->>'$.user.profile.age' AS age
FROM app_logs
WHERE JSON_CONTAINS(data, '"error"', '$.status');
```

---

## 3. Indexing JSON Documents

Scanning every JSON document in a million-row table is a performance killer. We must index the data.

### PostgreSQL: GIN (Generalized Inverted Indexes)
PostgreSQL's `jsonb` fully integrates with **GIN indexes**, which index every key and value inside the JSON document.
* **Default GIN** (`jsonb_ops`): Indexes keys, values, and paths. Supports queries containing `@>`, `?`, `?|`, and `?&`.
* **Path-Specific GIN** (`jsonb_path_ops`): Indexes only path-value pairs. Creates smaller index files and is faster for containment queries (`@>`), but does not support key-existence checks (`?`).

```sql
-- PostgreSQL: Creating GIN Indexes
CREATE INDEX idx_logs_data ON app_logs USING gin (data);
CREATE INDEX idx_logs_data_path ON app_logs USING gin (data jsonb_path_ops);
```

### MySQL: Virtual Columns & Multi-Valued Indexes
MySQL does not support indexing the entire JSON document directly. Instead, it relies on two techniques:
1. **Generated (Virtual) Columns + B-Tree**: Extract a specific JSON key into a virtual column and index that column.
2. **Multi-Valued Indexes**: Introduced in MySQL 8.0.17, these allow you to index arrays inside a JSON document, supporting searches via `MEMBER OF()`, `JSON_CONTAINS()`, and `JSON_OVERLAPS()`.

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
> **Data Type Traps with Generated Columns**
> When creating virtual columns in MySQL, always match the extracted data type. If your JSON field contains integers, cast the virtual column or use the correct type. Mismatches prevent the optimizer from using the index during query execution.

---

## 4. Feature Comparison Matrix

| Feature | PostgreSQL (`jsonb`) | MySQL (`JSON`) |
| :--- | :--- | :--- |
| **Storage Format** | Sorted binary representation | Native binary representation |
| **Path Operator (Unquoted)** | `->>` or `#>>` | `->>` |
| **Standard Path Language** | SQL/JSON Path (PostgreSQL 12+) | JSONPath syntax (`$.key`) |
| **Containment Check** | `@>` operator | `JSON_CONTAINS()` function |
| **Full Document Indexing** | Yes (via GIN indexes) | No (requires generated cols / functional index) |
| **Array Indexing** | Yes (built-in GIN) | Yes (Multi-Valued Indexes, MySQL 8.0.17+) |

---

## 5. Summary & Best Practices

1. **Always Use Binary Types**: In PostgreSQL, always choose `jsonb` over `json` unless you only store and retrieve without querying or changing.
2. **Design for Indexes**: In PostgreSQL, use GIN indexes for flexible searching. In MySQL, define virtual generated columns for frequently searched nested keys.
3. **Handle Quotes Correctly**: Be careful with `->` vs `->>` (or `JSON_EXTRACT` vs `JSON_UNQUOTE`). Using the wrong operator leaves quotes around string values, causing comparison checks to fail.
