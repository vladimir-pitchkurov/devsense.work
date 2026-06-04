---
title: "Database Indexes: Under the Hood and Deep Dive"
description: "A comprehensive developer's guide to B+Trees in InnoDB, Heap structures in Postgres, index node pointers, composite index rules, advanced index types, and write amplification."
published: 2026-05-31
faq:
  - question: "Why does InnoDB use a clustered index while Postgres uses a heap structure?"
    answer: "InnoDB stores row data directly within the leaf nodes of the primary key B+Tree to make primary key lookups extremely fast and keep related data contiguous. Postgres stores all data in a heap file and makes all indexes (including the primary key) secondary indexes pointing to heap locations via TIDs. This simplifies row moves during updates and avoids double-lookups for secondary indexes, but requires heap visits for primary key queries."
  - question: "What is a GIN index and when should I use it in Postgres?"
    answer: "A Generalized Inverted Index (GIN) is designed for indexing composite values like JSONB or arrays. It maps individual elements (keys or values) to their corresponding TIDs, allowing highly efficient matching of queries containing operators like `@>` (contains) or `?` (has key)."
  - question: "How does write amplification occur during index maintenance?"
    answer: "Write amplification occurs because every INSERT, UPDATE, or DELETE requires the database to update the table data as well as all associated indexes. Additionally, if an insert falls into a full B-Tree page, the page must be split, writing multiple pages to disk and causing disk fragmentation."
  - question: "How can I avoid double-lookups in secondary indexes under InnoDB?"
    answer: "To avoid double-lookups (secondary index scan followed by clustered index primary key lookup), you can design a covering index that contains all the columns requested by the SELECT query, allowing the database to satisfy the query entirely from the secondary index."
---

# Database Indexes: Under the Hood and Deep Dive

Every database query you write is a race against disk I/O. When your dataset fits in RAM, queries are instantaneous. But as your data grows to millions of rows and spills onto disk, your database engine must either search through every single page on the drive (a sequential scan) or use a map to jump straight to the data it needs. That map is an index.

Understanding how indexes work at the disk and page levels separates junior developers who randomly append indexes to tables from database architects who design self-optimizing, high-throughput storage engines. In this deep dive, we will peel back the abstraction layer of MySQL (InnoDB) and PostgreSQL to see how they represent indexes on disk, how composite column orders are resolved, and the true cost of write amplification.

---

## 1. Under the Hood: B+Tree in InnoDB vs. Heap & B-Tree in Postgres

Database engines do not store tables as simple arrays of rows. They structure them on disk using pages (typically 16KB in InnoDB, 8KB in PostgreSQL). However, their storage architectures are fundamentally different.

### InnoDB: The Clustered Index (B+Tree)
In MySQL’s InnoDB engine, **the table is the index**. More specifically, the table is structured as a B+Tree built around the Primary Key. This is known as a **Clustered Index**.

*   **Internal Nodes:** Contain only keys and pointers to child pages. They guide the engine during searches.
*   **Leaf Nodes:** Contain the actual data rows. The leaf pages are linked sequentially in a doubly-linked list, making range scans (`WHERE id BETWEEN 10 AND 50`) incredibly fast.
*   **Secondary Indexes:** Any index other than the primary key in InnoDB is a secondary index. The leaf nodes of a secondary index do *not* point to physical disk addresses. Instead, they store the **Primary Key value** of the row.

> [!NOTE]
> Because InnoDB secondary indexes store the Primary Key, looking up a row via a secondary index (e.g., `WHERE email = 'user@example.com'`) requires a two-step lookup: first, traversing the secondary index to find the primary key, and second, traversing the clustered index B+Tree to retrieve the row data.

```
[Secondary Index (Email)]
  Leaf Node: 'user@example.com' -> PK: 42
         |
         v
[Clustered Index (ID)]
  Leaf Node: PK: 42 -> Row Data: {id: 42, email: 'user@example.com', name: 'John Doe'}
```

### PostgreSQL: The Heap and B-Tree
PostgreSQL does not use clustered indexes by default. Instead, it stores row data in an unordered structure called a **Heap**.

*   **Heap Pages:** Table rows are appended to pages in the order they are inserted (or where space is available).
*   **B-Tree Indexes:** Every index in Postgres (including the Primary Key) is a secondary index.
*   **Leaf Nodes:** The leaf nodes of a Postgres B-Tree index contain a **Tuple Identifier (TID)**, which is a physical address on disk composed of a block number (page) and an offset index within that page (e.g., `(Page 14, Offset 3)`).

> [!WARNING]
> Because Postgres indexes store physical TIDs, any operation that moves a row on disk (such as an UPDATE that changes row size or MVCC page migration) would break these TIDs. Postgres manages this using vacuuming and HOT (Heap Only Tuples) optimization, but it means all indexes point directly to the Heap.

```
[Postgres Index (Email)]                         [Postgres Heap (Unordered)]
  Leaf: 'user@example.com' -> TID: (Page 14, Offset 3) ----> Page 14, Slot 3: {id: 42, ...}
```

---

## 2. Composite Index Column Order Rules

When creating an index on multiple columns—a **composite index**—the order of columns in the index declaration is critical. A wrong column order will render the index completely useless for certain queries.

The golden rules of composite index design are:
1.  **The Leftmost Prefix Rule:** The database can only use a composite index if the query filters on the leftmost column of the index first. An index on `(A, B, C)` can optimize queries filtering on `(A)`, `(A, B)`, and `(A, B, C)`, but *cannot* optimize queries filtering on `(B)` or `(C)` alone.
2.  **Equality First, Range Last:** Columns filtered with exact equality (`=`, `IN`) must come first in the index. Columns filtered with ranges (`<`, `>`, `BETWEEN`, `LIKE`) must come last. Once a range column is evaluated, the database cannot use subsequent columns in the index for filtering.
3.  **High Cardinality First:** Columns with high cardinality (many unique values, e.g., `user_id`) should generally precede columns with low cardinality (few unique values, e.g., `status`), provided they are both queried with equality.

Let's look at a concrete migration and query:

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
-- Optimal index layout for: user_id (equality) + status (equality) + created_at (range/sort)
CREATE INDEX idx_orders_user_status_created ON orders (user_id, status, created_at);
```

For the query below, the index allows the engine to immediately filter by `user_id`, then filter by `status`, and then read the pre-sorted `created_at` values in reverse order without needing a separate filesort operation.

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

## 3. Deep Dive into Index Types

Different query patterns require different data structures. Here is a comparison of index types available in MySQL and PostgreSQL.

### B-Tree
The workhorse of database engines. It supports equality (`=`), ranges (`>`, `<`, `BETWEEN`), and sorting (`ORDER BY`). B-Trees remain balanced, ensuring that search, insert, and delete operations all run in $O(\log n)$ time.

### Hash
Hash indexes store a hash of the indexed value and point to the corresponding row.
*   **Postgres:** Supports explicit Hash indexes. They are extremely fast ($O(1)$) for equality searches but do not support range queries, sorting, or multi-column partial matches.
*   **MySQL (InnoDB):** Does not support explicit creation of Hash indexes. Instead, it uses an **Adaptive Hash Index**—an internal feature where InnoDB automatically monitors query patterns on B-Trees and builds in-memory hash tables for highly frequent lookups.

### GIN (Generalized Inverted Index) - Postgres
GIN is designed for indexing composite values where you need to search for elements *inside* the value (such as JSONB documents or Arrays). GIN maps individual elements inside the document to their physical row TIDs.

```sql
-- database/migrations/2026_05_31_postgres_features.sql
-- Create a table with JSONB and add a GIN index in PostgreSQL
CREATE TABLE user_profiles (
    id BIGINT PRIMARY KEY,
    metadata JSONB NOT NULL
);

CREATE INDEX idx_user_metadata_gin ON user_profiles USING GIN (metadata);

-- This query utilizes the GIN index to instantly match matching keys
SELECT * FROM user_profiles 
WHERE metadata @> '{"role": "administrator", "status": "active"}';
```

### Partial / Filtered Indexes
A partial index contains only a subset of the rows in a table, defined by a `WHERE` condition. This saves massive disk space and keeps the index small and fast.
*   **Postgres:** Natively supports partial indexes.
*   **MySQL:** Does not support partial indexes directly (as of 8.0). You must use functional indexes with expressions that evaluate to `NULL` to achieve a similar effect, which is less elegant.

```sql
-- database/migrations/2026_05_31_partial_index.sql
-- Postgres only: Index only active unpaid orders to keep it compact
CREATE INDEX idx_orders_active_unpaid ON orders (user_id) 
WHERE status = 'pending' AND amount > 100.00;
```

### Expression / Functional Indexes
You can index the result of a function or expression rather than the raw column value. This is highly useful when queries perform manipulations on columns in the `WHERE` clause.

```sql
-- database/migrations/2026_05_31_functional_index.sql
-- Create an expression index to optimize lowercase searches
-- PostgreSQL:
CREATE INDEX idx_orders_lower_status ON orders (LOWER(status));

-- MySQL 8.0+:
CREATE INDEX idx_orders_lower_status ON orders ((LOWER(status)));
```

### Covering Indexes
A covering index is a secondary index that contains all columns required by a query, allowing the database to satisfy the query entirely from the index page without touching the table data (Heap or Clustered Index).
*   In Postgres, you can explicitly append non-key payload columns to an index using the `INCLUDE` clause.
*   In MySQL, you simply add the columns to the composite index keys.

```sql
-- database/migrations/2026_05_31_covering_index.sql
-- Postgres: Index is sorted by user_id, but carries amount/created_at payload
CREATE INDEX idx_orders_covering ON orders (user_id) INCLUDE (amount, created_at);
```

---

## 4. Write Amplification & Maintenance Cost

Indexes are not free. Every index you add to a table acts as a drag on write performance. This penalty manifests as **Write Amplification**.

### Page Splits in B+Trees
Database pages are allocated with free space (fillfactor) to allow future updates. When you insert a row, the database must write it into the appropriate B-Tree index node. If the index node page is full, a **Page Split** occurs:
1.  A new page is allocated.
2.  Approximately 50% of the keys from the full page are moved to the new page.
3.  The parent page is updated with a pointer to the new page.

This operation requires multiple disk writes and causes index fragmentation, which degrades sequential read performance.

### Vacuuming vs. Purge Lag
When rows are updated or deleted, the space occupied by the old records cannot be immediately reused due to MVCC (Multi-Version Concurrency Control).

*   **Postgres (Autovacuum):** When an UPDATE occurs, Postgres writes a new tuple to the heap and updates the indexes to point to it. This leaves a "dead tuple" in the heap page. If the autovacuum daemon cannot keep up with cleaning these dead tuples, the table and its indexes bloat, leading to massive memory and disk performance degradation.
*   **MySQL InnoDB (Purge Lag):** In InnoDB, updates are performed in-place in the clustered index, and old versions are written to the Undo Log. However, secondary indexes are modified by marking the old record as "deleted" and inserting the new record. A background thread called the **Purge Thread** is responsible for removing these delete-marked secondary index records. If writes are too heavy, the **Purge Lag** grows, consuming storage space and causing query slowdowns.

---

## 5. Common Mistakes

### Mistake 1: Wrong Column Order in Composite Indexes
**Bad Practice:**
The developer expects the index to optimize queries on both columns, but orders the range query column first.
```sql
-- database/queries/bad_composite_order.sql
-- Index defined as: (created_at, user_id)
CREATE INDEX idx_bad_order ON orders (created_at, user_id);

-- Query:
SELECT * FROM orders 
WHERE created_at >= '2026-01-01 00:00:00' 
  AND user_id = 42;
```
*Why it's bad:* The range filter on `created_at` prevents the database from using the `user_id` part of the index to locate the matching rows. The engine must scan the index for all records after the date, checking each one for the user ID.

**Good Practice:**
```sql
-- database/queries/good_composite_order.sql
-- Index defined as: (user_id, created_at)
CREATE INDEX idx_good_order ON orders (user_id, created_at);

-- Query:
SELECT * FROM orders 
WHERE user_id = 42 
  AND created_at >= '2026-01-01 00:00:00';
```
*Why it's good:* The exact equality filter on `user_id` allows the database to instantly jump to the user's records, and then use the ordered `created_at` index leaf nodes to scan the range.

### Mistake 2: Indexing Low-Cardinality Columns
**Bad Practice:**
Adding an index on a boolean column (e.g., `is_active` or `has_paid`).
```sql
-- database/queries/bad_low_cardinality.sql
CREATE INDEX idx_orders_active ON orders (status); -- status only has 'pending', 'completed'
```
*Why it's bad:* If the status values are evenly distributed, the index does not help. The query optimizer will look at the cost of traversing the secondary index and then performing random I/O lookups to the clustered index/heap, and decide that a sequential scan of the table is faster. The index remains unused but still degrades write performance.

---

## 6. Self-Test Quiz

Test your understanding of index architectures:

### Question 1
Suppose you have a composite index `idx_test (A, B, C)` on a table. Which of the following queries will **NOT** be able to use the index?
1. `WHERE A = 1 AND B = 2`
2. `WHERE B = 2 AND C = 3`
3. `WHERE A = 1 AND C = 3`

<details>
<summary>Click here to reveal the answer</summary>

**Answer: Query 2 (`WHERE B = 2 AND C = 3`)**

**Explanation:**
According to the leftmost prefix rule, the query must filter on the first column of the index (`A`) to utilize it. Since Query 2 does not filter on `A`, the database cannot traverse the B-Tree root nodes and must perform a full-table scan. Query 3 *can* use the index, but only the `A` part; it will locate records where `A = 1` and then filter them manually for `C = 3`.
</details>

---

### Question 2
Why does updating a non-indexed column in PostgreSQL sometimes trigger index write amplification, whereas in MySQL InnoDB it does not?

<details>
<summary>Click here to reveal the answer</summary>

**Answer:**
Because of the difference between Heap and Clustered structures.
*   In PostgreSQL, if an UPDATE cannot perform a HOT (Heap Only Tuples) optimization (e.g., if there is no room on the page), a new row version is written to a different page. This changes the physical address (TID) of the row. Consequently, *all* indexes on that table must be updated to point to the new TID, causing index write amplification.
*   In MySQL InnoDB, the row remains in the same clustered index leaf node (unless the primary key itself is updated). Secondary indexes point to the primary key value, not a physical disk address, meaning their leaf nodes do not need to be updated.
</details>

---

### Question 3
You have a table with 10 million rows. You run the following query:
`SELECT user_id, status FROM orders WHERE user_id = 100500;`
The index on `user_id` is defined as: `CREATE INDEX idx_user ON orders (user_id);`
How can you optimize this query to prevent any table data page lookups?

<details>
<summary>Click here to reveal the answer</summary>

**Answer:**
Create a **covering index** that includes `status`.
*   In PostgreSQL: `CREATE INDEX idx_user_status ON orders (user_id) INCLUDE (status);`
*   In MySQL (or PostgreSQL): `CREATE INDEX idx_user_status ON orders (user_id, status);`

**Explanation:**
By adding `status` to the index, all columns queried in the `SELECT` and `WHERE` clauses are contained entirely within the index page. The database engine can perform an **Index Only Scan**, bypassing the heap or clustered index lookup entirely.
</details>
