# Partitioning and Pagination

As tables grow to tens or hundreds of millions of rows, standard queries and simple pagination structures begin to fail. Performing updates, backups, and index lookups over massive datasets introduces high disk latency. To scale, databases employ two primary strategies: **Table Partitioning** (splitting a massive table into smaller physical pieces under the hood) and **Efficient Pagination** (retrieving paginated records without scanning millions of offset rows).

---

## 1. Table Partitioning: Declarative Rules and Pruning

Table partitioning divides a single logical table into multiple physical child tables (partitions). The parent table acts as a routing interface.

### Types of Partitioning
1. **Range Partitioning**: Maps rows to partitions based on a range of values (e.g., partitioning a log table by month).
2. **List Partitioning**: Maps rows to partitions based on explicit key values (e.g., partitioning by country code).
3. **Hash Partitioning**: Distributes rows across a fixed number of partitions using a hash modulo function. Ideal for evening out write loads.

### PostgreSQL Declarative Partitioning
Since version 10, PostgreSQL supports declarative partitioning. Partitions are declared using the `PARTITION BY` clause.

```sql
-- PostgreSQL: Declarative Range Partitioning
CREATE TABLE app_logs (
    id BIGINT NOT NULL,
    log_date DATE NOT NULL,
    message TEXT
) PARTITION BY RANGE (log_date);

-- Create individual partitions
CREATE TABLE app_logs_y2026m01 PARTITION OF app_logs
    FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');
CREATE TABLE app_logs_y2026m02 PARTITION OF app_logs
    FOR VALUES FROM ('2026-02-01') TO ('2026-03-01');
```

### MySQL Partitioning Syntax
MySQL implements partitioning directly inside the table definition without needing separate child table statements.

```sql
-- MySQL: InnoDB Range Partitioning
CREATE TABLE app_logs (
    id BIGINT NOT NULL,
    log_date DATE NOT NULL,
    message TEXT,
    PRIMARY KEY (id, log_date)
) ENGINE=InnoDB
PARTITION BY RANGE COLUMNS(log_date) (
    PARTITION p2026m01 VALUES LESS THAN ('2026-02-01'),
    PARTITION p2026m02 VALUES LESS THAN ('2026-03-01')
);
```

### Partition Pruning
The main benefit of partitioning is **Partition Pruning**. The query optimizer analyzes the `WHERE` clause filter and excludes partitions that cannot contain matching rows, avoiding full scans of those physical files.

```sql
-- Triggering Partition Pruning
EXPLAIN SELECT * FROM app_logs WHERE log_date = '2026-02-15';
-- PostgreSQL plan will only scan 'app_logs_y2026m02'
-- MySQL plan will list partitions: 'p2026m02'
```

> [!WARNING]
> **Primary Key Restrictions**
> In both MySQL and PostgreSQL, any unique constraint or primary key on a partitioned table **MUST** include all partition key columns. In MySQL, you cannot have a standalone `PRIMARY KEY (id)` if you partition by `log_date`; it must be defined as `PRIMARY KEY (id, log_date)`. This prevents databases from needing to scan all partitions to enforce uniqueness during inserts.

---

## 2. Pagination: Offset vs. Keyset (Cursor)

Retrieving lists of data in pages is a core application requirement. However, the default SQL approach can lead to major performance bottlenecks.

### The Pitfall of OFFSET Pagination
* **Syntax**: `SELECT * FROM orders ORDER BY created_at DESC LIMIT 10 OFFSET 500000;`
* **Under the Hood**: The database engine cannot jump directly to row 500,000. It must scan the index, read all 500,000 preceding rows, discard them, and return only the next 10 rows. This causes high CPU usage and disk I/O.

### Keyset (Cursor-Based) Pagination
* **Syntax**: Instead of offsets, use the last retrieved values to filter subsequent queries.
  ```sql
  -- MySQL & PostgreSQL: Keyset Pagination
  SELECT * FROM orders 
  WHERE created_at < '2026-06-17 10:00:00' 
  ORDER BY created_at DESC 
  LIMIT 10;
  ```
* **Performance**: With a composite index on `(created_at, id)`, the query performs an index seek directly to the starting point, running in $O(1)$ time regardless of the page number.

### Multi-Column Keyset Pagination (Sorting by non-unique fields)
If the sorting field (like `created_at` or `price`) can contain duplicate values, you must append a unique tie-breaker column (usually the primary key) to avoid skipping rows.
* **Tuple Comparison Syntax (PostgreSQL)**:
  ```sql
  -- PostgreSQL supports row value comparisons natively
  SELECT * FROM orders
  WHERE (created_at, id) < ('2026-06-17 10:00:00', 9876)
  ORDER BY created_at DESC, id DESC
  LIMIT 10;
  ```
* **Explicit Logical Expansion (MySQL)**:
  *MySQL 8.0 supports row-value comparison, but older versions or poor optimizers run it inefficiently. Expand it explicitly for safety:*
  ```sql
  -- MySQL Safe Keyset Expansion
  SELECT * FROM orders
  WHERE created_at < '2026-06-17 10:00:00'
     OR (created_at = '2026-06-17 10:00:00' AND id < 9876)
  ORDER BY created_at DESC, id DESC
  LIMIT 10;
  ```

> [!TIP]
> **Multi-Category Pagination with LATERAL JOIN**
> If you need to paginate and fetch the "top N items per category" (e.g., top 3 products for each of 20 categories), standard `GROUP BY` won't work. Use a `LATERAL` join (supported in PostgreSQL 10+ and MySQL 8.0.14+).
> ```sql
> SELECT c.name, p.title, p.price
> FROM categories c
> INNER JOIN LATERAL (
>     SELECT title, price 
>     FROM products 
>     WHERE category_id = c.id 
>     ORDER BY price DESC LIMIT 3
> ) p ON TRUE;
> ```
> *This executes an index-driven subquery for each category, which is extremely fast compared to full window function scans.*

---

## 3. Feature Comparison Matrix

| Feature | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Partitioning Definition** | Declarative child tables | Defined directly on parent table |
| **Row Routing** | Handled by parent routing | Handled internally by InnoDB |
| **PK Restriction** | PK must contain partition key | PK must contain partition key |
| **Row Value Comparison** | Natively optimized (e.g. `(a, b) > (x, y)`) | Supported but optimizer traps can occur |
| **Lateral Joins** | Supported (PostgreSQL 9.3+) | Supported (MySQL 8.0.14+) |

---

## 4. Summary & Best Practices

1. **Partition by Date for Archiving**: Range partitioning is perfect for transaction logs. When data becomes old, you can drop the partition using `DROP TABLE partition_name` (instant) instead of running a massive `DELETE` (slow, generates undo bloat).
2. **Never Use Large Offsets**: Implement keyset pagination for infinite scroll or paginated lists.
3. **Index Your Pagination Keys**: Always ensure your keyset pagination filters match a composite B-Tree index (e.g., index on `(created_at, id)`).
