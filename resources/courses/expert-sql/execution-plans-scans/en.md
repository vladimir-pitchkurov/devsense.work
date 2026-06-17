# Execution Plans and Scan Types

To optimize slow database queries, you must understand how the database retrieves data. The query optimizer evaluates multiple execution paths and builds an **Execution Plan** based on cost estimates. By analyzing execution plans using `EXPLAIN`, you can identify bottlenecks, such as full table scans, incorrect join orders, or indexes that are ignored. PostgreSQL and MySQL use different terminology and plan visualization techniques, but their underlying scanning methods share key concepts.

---

## 1. Generating and Reading Execution Plans

Both databases provide tools to inspect how queries are executed.

### PostgreSQL: EXPLAIN and EXPLAIN ANALYZE
In Postgres, `EXPLAIN` returns the planner's estimated cost. Adding `ANALYZE` forces the database to execute the query, providing real runtimes and actual row counts.
* **Cost Metrics**: Represented as `cost=startup..total` (e.g., `cost=0.00..45.10`). Cost is a relative unit (where `1.0` is the cost of reading a single page sequentially).
* **Actual Runtimes**: Shown in milliseconds.

```sql
-- PostgreSQL: Inspect actual execution stats
EXPLAIN (ANALYZE, BUFFERS, COSTS)
SELECT email FROM users WHERE status = 'active';
```
*The `BUFFERS` option displays shared memory block reads (hits and reads from disk).*

### MySQL: EXPLAIN and EXPLAIN ANALYZE
MySQL historically used a tabular format for `EXPLAIN`. MySQL 8.0 introduced `EXPLAIN ANALYZE`, which displays plans in tree format with actual execution costs and times.

```sql
-- MySQL: Visualizing with Tree structure & actual runtimes
EXPLAIN ANALYZE
SELECT email FROM users WHERE status = 'active';
```

---

## 2. Scan Types in PostgreSQL

PostgreSQL chooses from several scanning methods based on index presence, table size, and data selectivity.

```
PostgreSQL Scan Selection Flow:
Selectivity:  High (1-2 rows)      Medium (5-15%)        Low (Full Table)
Method:       [Index Scan]   ->  [Bitmap Scan]   ->  [Seq Scan]
```

### Sequential Scan (Seq Scan)
* **What it does**: Reads the entire table heap file from start to finish, evaluating the `WHERE` clause for every row.
* **When it occurs**: Used when the query has no matching index, or when the planner estimates that fetching most of the table is faster than using an index.

### Index Scan
* **What it does**: Scans the B-Tree index to find the locations (TIDs) of matching rows, then fetches those specific blocks from the table heap.
* **Downside**: If many rows match, jumping back and forth between index pages and heap pages causes random I/O bottlenecks.

### Bitmap Index Scan & Bitmap Heap Scan
* **What it does**: Used when Postgres fetches a moderate number of rows.
  1. **Bitmap Index Scan** reads the index and builds a bitmap of matching heap pages in memory, sorting TIDs by physical page order.
  2. **Bitmap Heap Scan** reads the sorted pages sequentially, preventing random I/O and double-reading of pages.

### Index Only Scan
* **What it does**: Fetches data directly from index leaf nodes without visiting the table heap.
* **Visibility Map Caveat**: Postgres must check the **Visibility Map** to ensure pages haven't been modified by unvacuumed transactions. If a page is marked "dirty," Postgres must visit the heap anyway, degrading performance.

---

## 3. Scan Types in MySQL (InnoDB)

MySQL's `EXPLAIN` output uses the `type` column to describe how rows are fetched. The types, ordered from fastest to slowest, include:

### const / system
* The table has at most one matching row (e.g., querying a `PRIMARY KEY` or `UNIQUE` index with a constant value). This is extremely fast.

### eq_ref
* Used in joins when MySQL reads one row from this table for each combination of rows from the preceding table (occurs with primary or unique keys).

### ref
* Used when matching rows against a non-unique index. Multiple rows may match.

### range
* Uses an index to select a range of rows (e.g., queries using `>`, `<`, `BETWEEN`, or `IN`).

### index (Full Index Scan)
* MySQL performs a full scan of the index tree. This is equivalent to PostgreSQL's Index Only Scan. It avoids scanning the actual table space but still reads the entire index.

### ALL (Full Table Scan)
* MySQL reads every row in the table from disk. This is the slowest access type and should be avoided for large tables.

---

## 4. Scan Types Comparison Matrix

| Scan Concept | PostgreSQL Name | MySQL (InnoDB) Type | Description |
| :--- | :--- | :--- | :--- |
| **Full Table Scan** | `Seq Scan` | `ALL` | Scans the entire table; high disk I/O. |
| **Index Lookup** | `Index Scan` | `ref` or `range` | Traverses index, then fetches row from tablespace. |
| **Index-Only Lookup**| `Index Only Scan` | `index` | Retrieves data strictly from index leaf nodes. |
| **Bulk Index Search** | `Bitmap Index/Heap Scan` | N/A | Groups TIDs by page to optimize disk access. |
| **Constant Lookup** | `Index Scan` (1 row) | `const` | Instant lookup on a unique index. |

---

## 5. Summary & Best Practices

1. **Always Use ANALYZE for Real Data**: Standard `EXPLAIN` only shows estimates. Always run `EXPLAIN ANALYZE` (in safe environments) to see real row counts and memory usage.
2. **Watch out for "Index Only Scan" Degradation**: If a Postgres Index Only Scan displays a high number of heap fetches, run `VACUUM` on the table to update the Visibility Map.
3. **Avoid the `ALL` Type in MySQL**: If a query on a large table shows `type: ALL` or `Extra: Using join buffer`, add an index to cover the search columns.
4. **Update Table Statistics**: If the optimizer chooses a bad scan type, table statistics might be outdated. Run `ANALYZE TABLE my_table;` in MySQL or `ANALYZE my_table;` in PostgreSQL.
