---
title: "Databases under load: queries, indexes, MySQL vs Postgres, scaling | DevSense"
description: "How to optimize SQL and schema, choose index types, when database-side logic becomes a liability, how MySQL and PostgreSQL differ in production, and what vertical scale, replicas, decomposition, and sharding really cost."
published: 2026-04-13
faq:
  - question: "Why is seek (keyset) pagination faster than OFFSET pagination on large datasets?"
    answer: "OFFSET pagination requires the database engine to scan and discard all skipped rows (e.g., scanning 100,000 rows to return 20). Keyset seek pagination uses a filter condition (e.g., `WHERE (created_at, id) < (?, ?)`) based on the last seen row, allowing the engine to jump directly to the target rows using a B-tree index without scanning the skipped rows."
  - question: "When should you use a covering index instead of a regular index?"
    answer: "You should use a covering index (often using the `INCLUDE` clause or a composite index containing all selected fields) when a high-frequency query reads only a few columns. This allows the database engine to return the result directly from the index structure (Index Only Scan) without performing a secondary disk/heap lookup for the full row."
  - question: "What are the main trade-offs of using read replicas?"
    answer: "Read replicas offload read operations from the primary database, increasing read throughput. However, replication lag means replicas may serve stale data (eventual consistency). Developers must design application routing to direct write-dependent reads to the primary database to avoid 'read-your-writes' consistency bugs."
  - question: "Why is sharding considered a last resort for database scaling?"
    answer: "Sharding splits data across physically separate database instances, which increases operational complexity. It prevents the use of cross-shard joins, global transaction integrity (without slow two-phase commits), and simple global uniqueness constraints, while introducing the difficulty of rebalancing shards when data distribution changes."
---

# Databases under load: query tuning, indexes, engines, and scaling trade-offs

Most performance stories are boring until they are not. The dashboard looks fine at ninety-five milliseconds, then a campaign ships, a report joins six tables, and suddenly **checkout** and **password reset** share a queue behind the same storage engine. The fixes are rarely a single knob: they are **fewer round-trips**, **indexes that match real predicates**, **honest capacity math**, and sometimes **admitting that one logical database cannot be infinite**.

**Related guides:** [High-load event ingestion](high-load-event-ingestion) · [Message queues compared](message-queues-compared) · [Observability and monitoring](observability-monitoring-laravel)

## Contents

* [Measure before you “optimize”](#measure)
* [Query-level optimizations with examples](#queries)
* [Schema choices that age well](#schema)
* [Index types and when they help](#indexes)
* [Why heavy logic inside the database hurts teams](#db-logic)
* [MySQL versus PostgreSQL in practice](#mysql-vs-postgres)
* [Scaling paths and the problems they import](#scaling)
* [Decomposition without fairy tales](#decomposition)
* [Sharding: keys, cross-shard pain, rebalance](#sharding)
* [Common Mistakes](#common-mistakes)
* [Checklist before you shard or buy metal](#checklist)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="measure"></a>
## Measure before you “optimize”

**Latency percentiles** beat averages. A mean of 40 ms can hide a tail where **one percent** of requests exceed two seconds because of lock waits or cold caches.

Practical signals:
* **Slow query logs** with a threshold you revisit as data grows—not “everything,” or you will train people to ignore noise.
* **`EXPLAIN` (Postgres)** / **`EXPLAIN` (MySQL 8+)** on the shapes you actually run in production parameter bindings, not only hand-crafted literals.
* **Connection counts** and **pool saturation**; many “database is slow” incidents are **waiting for a connection**, not disk.

> [!NOTE]
> **State Sharing**
> The database is shared state. Anything that grows **CPU, locks, or I/O** on the primary eventually competes with everything else on that same path.

---

<a id="queries"></a>
## Query-level optimizations with examples

### Fetch only what you need

Wide `SELECT *` over fat rows forces the engine to move bytes you will throw away in PHP or Node. Prefer explicit columns, especially on tables with large text or JSON payloads.

```sql
-- database/queries/fetch_users.sql
-- Avoid (ships every column, including blobs you do not render)
SELECT * FROM users WHERE country = 'DE' LIMIT 50;

-- Prefer
SELECT id, email, display_name, created_at
FROM users
WHERE country = 'DE'
ORDER BY created_at DESC
LIMIT 50;
```

### Join shape and cardinality

Nested loops are cheap when the inner side is **index-backed** and small; hash joins shine on larger sets—**which strategy you get** depends on the engine and statistics. If a join explodes row counts because a relationship is **many-to-many without a constraint**, fix the model—not the timeout.

### Pagination without scanning the whole table

`OFFSET` pagination is deceptively simple. For large offsets the engine often **still walks** skipped rows.

```sql
-- database/queries/offset_pagination.sql
-- Gets slower as :offset grows
SELECT id, title, created_at
FROM posts
ORDER BY created_at DESC, id DESC
LIMIT 20 OFFSET 100000;
```

**Keyset (seek) pagination** uses the last seen sort key:

```sql
-- database/queries/seek_pagination.sql
SELECT id, title, created_at
FROM posts
WHERE (created_at, id) < (:last_created_at, :last_id)
ORDER BY created_at DESC, id DESC
LIMIT 20;
```

You need a **supporting index** that matches the sort order, for example `(created_at DESC, id DESC)`.

### `EXISTS` versus `IN` for existence checks

For “is there any matching row?” patterns, semi-join style plans often behave well:

```sql
-- database/queries/exists_lookup.sql
SELECT o.id, o.total_cents
FROM orders o
WHERE EXISTS (
  SELECT 1 FROM order_items i
  WHERE i.order_id = o.id AND i.sku = 'GOLD-001'
);
```

### Aggregations and reporting

Heavy `GROUP BY` over raw OLTP tables is a classic way to **hurt tail latency**. Precompute into **summary tables**, **materialized views** (Postgres), or an **OLAP** store when the business truly needs interactive analytics.

---

<a id="schema"></a>
## Schema choices that age well

* **Types that match reality** — store money as **integer minor units** (e.g., cents) or **decimal with explicit precision**, not binary floats.
* **Nullability** — nullable columns complicate indexing and statistics; use them when unknown is meaningful, not as a lazy default.
* **Foreign keys** — they cost a little on writes and buy **referential honesty**. Teams that disable them for “speed” often pay in **orphan rows** and **non-deterministic application bugs**.
* **Over-normalized versus read paths** — pure theory ignores how often you read joined shapes. A measured, documented **denormalized field** can beat endless joins—at the cost of **write complexity** and **invariant discipline**.

---

<a id="indexes"></a>
## Index types and when they help

Not every index is a B-tree, and not every B-tree is the right shape.

| Idea | Typical use | Notes |
|------|-------------|--------|
| **B-tree (default)** | Equality and range on sortable types | Most “normal” indexes; composite column **order matters**—leading columns must match common `WHERE`/`ORDER BY`. |
| **Hash** | Exact equality (where supported) | Postgres **hash** indexes historically had replication caveats. MySQL exposes hash concepts mainly via **MEMORY** and **adaptive hash** internals. |
| **Full-text** | Token search in text | MySQL **InnoDB FTS** vs Postgres **`tsvector` + GIN**—different parsers and maintenance profiles. |
| **GIN / GiST (Postgres)** | JSONB containment, arrays, full-text, some geometric types | Powerful; **index build and bloat** need monitoring. |
| **Spatial** | Geo queries | Engine-specific (**PostGIS** on Postgres; MySQL spatial types). |

### Composite indexes: column order

If queries filter on `tenant_id` almost always, putting it **first** is usually correct:

```sql
-- database/migrations/create_composite_index.sql
-- Good when queries look like: WHERE tenant_id = ? AND status = 'open'
CREATE INDEX orders_tenant_status_idx ON orders (tenant_id, status);
```

### Covering indexes

If the index **contains all columns** the query reads, the engine can satisfy the query from the index alone (**Index Only Scan** in Postgres). Example pattern:

```sql
-- database/migrations/create_covering_index.sql
CREATE INDEX sessions_lookup_idx ON sessions (user_id) INCLUDE (last_seen_at);
```

### Partial / filtered indexes

When a predicate is **stable and selective**, index only the hot slice:

```sql
-- database/migrations/create_partial_index.sql
-- Postgres-specific partial index
CREATE INDEX invoices_open_due_idx
ON invoices (due_at)
WHERE status = 'open' AND due_at IS NOT NULL;
```

---

<a id="db-logic"></a>
## Why heavy logic inside the database hurts teams

Stored procedures, triggers, and complex views are **not evil physics**. They are **deployment and ownership** choices.

What often goes wrong:
* **Versioning** — application code ships through Git, CI, and rolling deploys; database routines live **elsewhere**. Drift between branches and environments becomes painful.
* **Testing** — business rules in PHP/Java are unit-tested in familiar harnesses; triggers that mutate rows on insert are **harder to reason about** in isolation.
* **Portability** — logic in the app can move across MySQL, Postgres, or even vendors; logic in PL/pgSQL or MySQL stored procedures **locks you in**.
* **Observability** — stack traces and APM spans center on the app tier; deep trigger chains surface as **mysterious latency** unless you instrument carefully.

When database-side logic **still** makes sense:
* **Constraints** — `CHECK`, foreign keys, and **well-chosen uniqueness** express invariants cheaper than hoping every service remembers them.
* **Idempotent guards** — a **unique index** on a business key beats “select then insert” races.

---

<a id="mysql-vs-postgres"></a>
## MySQL versus PostgreSQL in practice

Both are production-grade. Differences bite when you assume they are interchangeable.

| Topic | MySQL (InnoDB typical) | PostgreSQL |
|-------|-------------------------|------------|
| **Storage model** | Clustered **primary key** organizes rows; secondary indexes point to PK | **Heap** tables; indexes are separate; **CLUSTER** is a maintenance operation |
| **MVCC / garbage** | **Undo** history; **purge** lags can matter for long transactions | **Dead tuples**; **VACUUM** (autovacuum) is operational bread and butter |
| **SQL features** | Solid core SQL; historically stricter about some corners | Richer **CTEs**, **window functions**, **LATERAL**, **JSONB** operators, **range types** |
| **Extensions** | Fewer in core; ecosystem via plugins | **PostGIS**, **pgvector**, **Citext**, many others |
| **Replication** | Mature **binlog** streaming; many hosted topologies | **Physical** and **logical** replication; **publication/subscription** |

Query examples that diverge:

Postgres—**JSONB containment**:
```sql
-- database/queries/postgres_jsonb_containment.sql
SELECT id FROM events WHERE payload @> '{"kind":"purchase"}'::jsonb;
```

MySQL—**JSON** extraction (8.x):
```sql
-- database/queries/mysql_json_extract.sql
SELECT id FROM events WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.kind')) = 'purchase';
```

---

<a id="scaling"></a>
## Scaling paths and the problems they import

### Vertical scale (“bigger box”)
Simple until physics disagrees. Disk bandwidth and CPU NUMA effects appear. You also concentrate failure.

### Read replicas
* **Pros:** offload **read-mostly** traffic, backups, reporting clones.
* **Cons:** **replication lag** means reads can be stale.

### Connection pooling
Opening a TCP+auth session per HTTP request does not scale. Tools like **PgBouncer** (Postgres) or ProxySQL (MySQL) sit between app and DB.

### Caching (Redis et al.)
Caches mask hot keys; they do not fix **write amplification** on the primary database.

---

<a id="decomposition"></a>
## Decomposition without fairy tales

**Schema-per-service** is not magic—it is **fewer accidental joins** and **clearer blast radius**. It imports **distributed transactions** or **sagas** when a user action truly spans stores.

Anti-pattern: **two services writing the same table** through a “shared database”—you have built a **distributed monolith** with extra network hops.

---

<a id="sharding"></a>
## Sharding: keys, cross-shard pain, rebalance

**Sharding** splits rows across **many primaries** using a **shard key** (often user id or tenant id).

What improves:
* **Write throughput** ceilings per machine drop when each shard owns a slice.
* **Blast radius** can shrink if failure isolates shards.

What hurts:
* **Cross-shard joins** and **global uniqueness**—you need coordination or application-level rules.
* **Rebalancing** when one hot tenant dominates a shard—resharding is operationally complex.

---

<a id="common-mistakes"></a>
## Common Mistakes

1. **Relying on OFFSET for Large Datasets**: Using standard `LIMIT ... OFFSET` pagination, forcing the engine to scan millions of rows to return a few rows.
2. **Ignoring Composite Index Column Order**: Placing a column with range filters (e.g., `created_at`) before columns with equality filters in composite index definitions.
3. **Shared Database Pattern in Microservices**: Allowing multiple services to write to the same table, creating tight coupling and integration bottlenecks.
4. **Writing Business Logic in Triggers**: Placing complex validation and mutations in triggers, bypassing tests, CI, and tracing.

---

<a id="checklist"></a>
## Checklist before you shard or buy metal

1. **Can `EXPLAIN` show a sequential scan** that an honest index would fix?
2. **Are N+1 queries** coming from the ORM layer regardless of disk speed?
3. **Is the bottleneck connections** or CPU on the database—or **lock contention** from long transactions?
4. **Have you measured replication lag** if you moved reads to replicas?
5. **Is the dataset growth bounded** by retention (archive cold partitions) cheaper than new topology?

---

## Summary

Databases reward **boring correctness** and **measurement**. Indexes and engine choice buy time; **architecture**—queues, separate read models, and sometimes sharding—buys **ceiling**.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: Why does a query like `SELECT * FROM users ORDER BY created_at DESC LIMIT 1` execute slowly on a 10M row table even if `created_at` is indexed?
- A) Indexes cannot be scanned in descending order.
- B) If the column is nullable, the database engine may scan the entire table to look for NULL values.
- C) You must use a covering index with `INCLUDE`.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
If the sorted column is nullable and the query does not filter out NULLs (e.g., `WHERE created_at IS NOT NULL`), the engine may fall back to a full table scan to locate null values, bypassing the index scan.
</details>

### Question 2: Which index type is best suited for searching keys inside arbitrary JSON documents in PostgreSQL?
- A) B-Tree Index.
- B) GIN (Generalized Inverted Index) Index.
- C) Hash Index.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
GIN indexes are specifically designed to index multi-valued elements like arrays and JSONB structures, enabling fast containment queries.
</details>
