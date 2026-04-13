---
title: "Databases under load: queries, indexes, MySQL vs Postgres, scaling | DevSense"
description: "How to optimize SQL and schema, choose index types, when database-side logic becomes a liability, how MySQL and PostgreSQL differ in production, and what vertical scale, replicas, decomposition, and sharding really cost."
published: 2026-04-13
---

# Databases under load: query tuning, indexes, engines, and scaling trade-offs

Most performance stories are boring until they are not. The dashboard looks fine at ninety-five milliseconds, then a campaign ships, a report joins six tables, and suddenly **checkout** and **password reset** share a queue behind the same storage engine. The fixes are rarely a single knob: they are **fewer round-trips**, **indexes that match real predicates**, **honest capacity math**, and sometimes **admitting that one logical database cannot be infinite**.

**Related:** [High-load event ingestion](high-load-event-ingestion) · [Message queues compared](message-queues-compared) · [Sail: databases & Docker services](../tools/sail-databases)

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
* [Checklist before you shard or buy metal](#checklist)

---

<a id="measure"></a>
## Measure before you “optimize”

**Latency percentiles** beat averages. A mean of 40 ms can hide a tail where **one percent** of requests exceed two seconds because of lock waits or cold caches.

Practical signals:

* **Slow query logs** with a threshold you revisit as data grows—not “everything,” or you will train people to ignore noise.
* **`EXPLAIN` (Postgres)** / **`EXPLAIN` (MySQL 8+)** on the shapes you actually run in production parameter bindings, not only hand-crafted literals.
* **Connection counts** and **pool saturation**; many “database is slow” incidents are **waiting for a connection**, not disk.

Keep one principle visible: **the database is shared state**. Anything that grows **CPU, locks, or I/O** on the primary eventually competes with everything else on that same path.

---

<a id="queries"></a>
## Query-level optimizations with examples

### Fetch only what you need

Wide `SELECT *` over fat rows forces the engine to move bytes you will throw away in PHP or Node. Prefer explicit columns, especially on tables with large text or JSON payloads.

```sql
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
-- Gets slower as :offset grows
SELECT id, title, created_at
FROM posts
ORDER BY created_at DESC, id DESC
LIMIT 20 OFFSET 100000;
```

**Keyset (seek) pagination** uses the last seen sort key:

```sql
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
SELECT o.id, o.total_cents
FROM orders o
WHERE EXISTS (
  SELECT 1 FROM order_items i
  WHERE i.order_id = o.id AND i.sku = 'GOLD-001'
);
```

Blindly rewriting every `IN (subquery)` is not a religion—**verify with `EXPLAIN`**.

### Aggregations and reporting

Heavy `GROUP BY` over raw OLTP tables is a classic way to **hurt tail latency**. Precompute into **summary tables**, **materialized views** (Postgres), or an **OLAP** store (see the event ingestion guide) when the business truly needs interactive analytics.

---

<a id="schema"></a>
## Schema choices that age well

* **Types that match reality** — store money as **integer minor units** or **decimal with explicit precision**, not binary floats, if you care about reconciliation.
* **Nullability** — nullable columns complicate indexing and statistics; use them when unknown is meaningful, not as a lazy default.
* **Foreign keys** — they cost a little on writes and buy **referential honesty**. Teams that disable them for “speed” often pay in **orphan rows** and **non-deterministic application bugs**.
* **Over-normalized versus read paths** — pure theory ignores **how often** you read joined shapes. A measured, documented **denormalized field** can beat endless joins—at the cost of **write complexity** and **invariant discipline**.

---

<a id="indexes"></a>
## Index types and when they help

Not every index is a B-tree, and not every B-tree is the right shape.

| Idea | Typical use | Notes |
|------|-------------|--------|
| **B-tree (default)** | Equality and range on sortable types | Most “normal” indexes; composite column **order matters**—leading columns must match common `WHERE`/`ORDER BY`. |
| **Hash** | Exact equality (where supported) | Postgres **hash** indexes historically had replication caveats; **check your version and ops guide**. MySQL exposes hash concepts mainly via **MEMORY** and **adaptive hash** internals on InnoDB—not a drop-in substitute for thinking in B-trees. |
| **Full-text** | Token search in text | MySQL **InnoDB FTS** vs Postgres **`tsvector` + GIN**—different parsers, ranking, and maintenance. |
| **GIN / GiST (Postgres)** | JSONB containment, arrays, full-text, some geometric types | Powerful; **index build and bloat** need monitoring. |
| **Spatial** | Geo queries | Engine-specific (**PostGIS** on Postgres is a common standard; MySQL has spatial types with its own rules). |

### Composite indexes: column order

If queries filter on `tenant_id` almost always, putting it **first** is usually correct:

```sql
-- Good when queries look like: WHERE tenant_id = ? AND status = 'open'
CREATE INDEX orders_tenant_status_idx ON orders (tenant_id, status);
```

A query that filters **only** `status` may **not** use that index efficiently—sometimes you need **another** index or must accept a trade-off.

### Covering indexes

If the index **contains all columns** the query reads, the engine can satisfy the query from the index alone (**Index Only Scan** in Postgres; **covering** behavior in InnoDB with the right clustering). Example pattern:

```sql
CREATE INDEX sessions_lookup_idx ON sessions (user_id) INCLUDE (last_seen_at);
-- INCLUDE is Postgres 11+; on MySQL, a composite index (user_id, last_seen_at) often plays a similar role.
```

### Partial / filtered indexes

When a predicate is **stable and selective**, index only the hot slice:

```sql
-- Postgres
CREATE INDEX invoices_open_due_idx
ON invoices (due_at)
WHERE status = 'open' AND due_at IS NOT NULL;
```

MySQL 8+ supports **functional and expression** indexes in many cases; “partial” equivalents are **less ergonomic** than in Postgres—teams sometimes emulate with **generated columns** plus a normal index.

---

<a id="db-logic"></a>
## Why heavy logic inside the database hurts teams

Stored procedures, triggers, and complex views are **not evil physics**. They are **deployment and ownership** choices.

What often goes wrong:

* **Versioning** — application code ships through Git, CI, and rolling deploys; database routines live **elsewhere**. Drift between branches and environments becomes painful.
* **Testing** — business rules in PHP/Java are unit-tested in familiar harnesses; triggers that mutate rows on insert are **harder to reason about** in isolation.
* **Portability** — logic in the app can move across MySQL, Postgres, or even vendors; logic in PL/pgSQL or MySQL stored procedures **locks you in** and complicates blue/green cutovers.
* **Observability** — stack traces and APM spans center on the app tier; deep trigger chains surface as **mysterious latency** unless you instrument carefully.

When database-side logic **still** makes sense:

* **Constraints** — `CHECK`, foreign keys, and **well-chosen uniqueness** express invariants cheaper than hoping every service remembers them.
* **Idempotent guards** — a **unique index** on a business key beats “select then insert” races.
* **Thin triggers** for audit columns—if the team documents them and tests migrations.

The useful slogan is not “never in the database,” but **“keep expensive business workflows in code you can test and roll back like any other release artifact.”**

---

<a id="mysql-vs-postgres"></a>
## MySQL versus PostgreSQL in practice

Both are production-grade. Differences bite when you assume they are interchangeable.

| Topic | MySQL (InnoDB typical) | PostgreSQL |
|-------|-------------------------|------------|
| **Storage model** | Clustered **primary key** organizes rows; secondary indexes point to PK | **Heap** tables; indexes are separate; **CLUSTER** is a maintenance operation |
| **MVCC / garbage** | **Undo** history; **purge** lags can matter for long transactions | **Dead tuples**; **VACUUM** (autovacuum) is operational bread and butter |
| **SQL features** | Solid core SQL; historically stricter about some corners | Richer **CTEs**, **window functions**, **LATERAL**, **JSONB** operators, **range types** |
| **Extensions** | Fewer in core; ecosystem via plugins/percona variants | **PostGIS**, **pgvector**, **Citext**, many others—**power and packaging overhead** |
| **Replication** | Mature **binlog** streaming; many hosted topologies | **Physical** and **logical** replication; **publication/subscription** for selective flows |
| **Isolation surprises** | Defaults have historically surprised people crossing from Postgres | **MVCC** semantics familiar to many backend devs; still has **serialization anomalies** to respect |
| **JSON** | **JSON** type with functional indexes in modern versions | **JSONB** binary format, **GIN** indexing, rich containment operators |

**Query examples that diverge subtly**

Postgres—**JSONB containment**:

```sql
SELECT id
FROM events
WHERE payload @> '{"kind":"purchase"}'::jsonb;
```

MySQL—**JSON** extraction and functional index (8.x):

```sql
SELECT id
FROM events
WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.kind')) = 'purchase';
-- Often paired with a generated STORED column + index for performance.
```

### Practical selection heuristics

* Choose **Postgres** when you want **rich SQL**, **JSONB-heavy** querying, **geospatial**, **extension** ecosystems, or **logical replication** patterns between tiers.
* Choose **MySQL/MariaDB** when your **hosting**, **team history**, or **ecosystem** (Percona toolkit habits, specific managed offerings) already center there—**either engine rewards competence**, neither forgives absent **VACUUM**/purge discipline and **bad indexes**.

---

<a id="scaling"></a>
## Scaling paths and the problems they import

### Vertical scale (“bigger box”)

Simple until physics disagrees. Single-threaded replication apply, **disk bandwidth**, and **NUMA** effects appear. You also **concentrate failure**: one machine is one outage domain.

### Read replicas

**Pros:** offload **read-mostly** traffic, backups, reporting clones. **Cons:** **replication lag** means reads can be stale; **routing** must be explicit (Laravel supports **read/write connections** when configured).

If code assumes **read-your-writes** across replica boundaries, users see “I saved, refresh, it vanished” ghosts.

### Connection pooling

Opening a TCP+auth session per HTTP request does not scale. Tools like **PgBouncer** (Postgres) or poolers in managed MySQL sit between app and DB. Watch **prepared statement** modes and **transaction pooling** caveats—some ORM features assume **session** affinity.

### Caching (Redis et al.)

Caches **mask** hot keys; they do not fix **write amplification** on the primary. Define **TTLs**, **invalidation**, and **cache stampede** strategy up front.

---

<a id="decomposition"></a>
## Decomposition without fairy tales

**Schema-per-service** is not magic—it is **fewer accidental joins** and **clearer blast radius**. It imports **distributed transactions** or **sagas** when a user action truly spans stores.

Patterns that work:

* **Outbox** table in the owning service, relay to a broker (ties cleanly to the queues guide).
* **Read models** rebuilt from events—eventual consistency with **explicit UX**.

Anti-pattern: **two services writing the same table** through a “shared database”—you have built a **distributed monolith** with extra network hops.

---

<a id="sharding"></a>
## Sharding: keys, cross-shard pain, rebalance

**Sharding** splits rows across **many primaries** using a **shard key** (often user id, tenant id, or geographic slice).

What improves:

* **Write throughput** ceilings per machine drop when each shard owns a slice.
* **Blast radius** can shrink if failure isolates shards—**if** your app handles partial degradation.

What hurts:

* **Cross-shard joins** and **global uniqueness**—you need **coordination**, **two-phase commit** (expensive), or **application-level** rules.
* **Rebalancing** when one hot tenant dominates a shard—**consistent hashing** and **resharding** projects are **migrations** in their own right.
* **Operational complexity**—N copies of backup, patching, monitoring, and **schema migration** orchestration.

Example sketch—application routes by tenant:

```sql
-- Each shard holds the same schema; data for tenant 42 lives on shard determined by hash(tenant_id).
-- A query that must list “all tenants” becomes a fan-out to every shard with merge—expensive and fragile.
```

Most teams should **exhaust** indexing, query fixes, caching, read replicas, and **archival/partitioning** before embracing **customer-facing sharded OLTP**.

---

<a id="checklist"></a>
## Checklist before you shard or buy metal

1. **Can `EXPLAIN` show a sequential scan** that an honest index would fix?
2. **Are N+1 queries** coming from the ORM layer regardless of disk speed?
3. **Is the bottleneck connections** or CPU on the database—or **lock contention** from long transactions?
4. **Have you measured replication lag** if you moved reads to replicas?
5. **Is the dataset growth bounded** by retention (archive cold partitions) cheaper than new topology?

Databases reward **boring correctness** and **measurement**. Indexes and engine choice buy time; **architecture**—queues, separate read models, and sometimes sharding—buys **ceiling**. Pick the smallest change that **removes the measured bottleneck**, then re-measure, because tomorrow’s bottleneck is rarely yesterday’s.
