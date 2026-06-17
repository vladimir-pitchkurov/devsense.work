# Views and Materialized Views: Abstraction vs. Caching

In high-performance database design, we often face two competing problems:
1. **Query Complexity**: Writing and maintaining long, nested queries that span dozens of joins.
2. **Execution Latency**: Running aggregate heavy queries (like sales reports) over millions of rows on every page load.

SQL addresses these challenges with **Views** and **Materialized Views**. While they sound similar, their underlying execution models are completely different: one is a logical shortcut (abstraction), and the other is a physical table cache.

---

## 1. Standard (Virtual) Views

A standard **View** is a saved query definition. It is a virtual table—it does not store any physical data on disk. When you query a view, the database engine merges the view's query definition into the main query and executes them together.

### Basic Syntax
```sql
-- MySQL & PostgreSQL
CREATE VIEW active_customer_summary AS
SELECT c.id, c.name, COUNT(o.id) AS total_orders
FROM customers c
LEFT JOIN orders o ON c.id = o.customer_id
WHERE c.status = 'active'
GROUP BY c.id, c.name;
```

### Updatable Views
Can you run `INSERT`, `UPDATE`, or `DELETE` statements on a view? Yes, under strict conditions. A view is updatable only if the database engine can map the write operations directly back to a single underlying physical table.
* **Rules**: The view must not contain:
  - Aggregate functions (`SUM`, `COUNT`, `AVG`).
  - `GROUP BY`, `HAVING`, or `DISTINCT` clauses.
  - Set operators (`UNION`, `INTERSECT`, `EXCEPT`).
  - Window functions.

> [!WARNING]
> **The `WITH CHECK OPTION` Clause**
> When updating data through a view, you can accidentally write data that makes the row disappear from the view itself!
> ```sql
> CREATE VIEW premium_customers AS 
> SELECT * FROM customers WHERE balance > 1000;
> ```
> If you run `UPDATE premium_customers SET balance = 500 WHERE id = 1`, the update succeeds, but the customer vanishes from the view. To prevent this, append `WITH CHECK OPTION` to the view definition. This forces the database to reject any inserts or updates that violate the view's `WHERE` clause.

---

## 2. Materialized Views (PostgreSQL)

Unlike standard views, a **Materialized View** physically stores the query results on disk, behaving like a regular table. Querying a materialized view is extremely fast because it bypasses joins and aggregation calculations. However, the data can become stale.

### Syntax and Refreshing
```sql
-- PostgreSQL Only
CREATE MATERIALIZED VIEW monthly_revenue_report AS
SELECT extract(year from order_date) as year, extract(month from order_date) as month, SUM(total_amount) as revenue
FROM orders
GROUP BY 1, 2;
```

To update the data, you must manually trigger a refresh:
```sql
REFRESH MATERIALIZED VIEW monthly_revenue_report;
```

### Non-Blocking Updates: `CONCURRENTLY`
By default, `REFRESH MATERIALIZED VIEW` places an exclusive lock on the view, blocking all read operations (`SELECT`) until the refresh finishes. To update the view without locking out your users, use the `CONCURRENTLY` option.

```sql
-- PostgreSQL Only: Non-blocking refresh
CREATE UNIQUE INDEX idx_monthly_rev ON monthly_revenue_report (year, month);
REFRESH MATERIALIZED VIEW CONCURRENTLY monthly_revenue_report;
```
* **Requirement**: You must create a unique index on one or more columns of the materialized view before you can use the `CONCURRENTLY` clause.

---

## 3. MySQL Workarounds for Materialized Views

MySQL does **not** natively support materialized views. If you need this functionality in MySQL, you must simulate it using one of two common workarounds.

### Workaround 1: Regular Table + Event Scheduler
You can create a normal table to act as your cache, and write a scheduled database event to periodically refresh it.

```sql
-- MySQL Only
-- 1. Create the physical table
CREATE TABLE monthly_revenue_report_cache AS
SELECT YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as revenue
FROM orders GROUP BY 1, 2;

-- 2. Create an event to refresh the table every hour
CREATE EVENT refresh_revenue_report
ON SCHEDULE EVERY 1 HOUR
DO
  BEGIN
    TRUNCATE TABLE monthly_revenue_report_cache;
    INSERT INTO monthly_revenue_report_cache
    SELECT YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as revenue
    FROM orders GROUP BY 1, 2;
  END;
```

### Workaround 2: Database Triggers
If you need real-time materialized views in MySQL, you can write `AFTER INSERT/UPDATE/DELETE` triggers on the source table that incrementally update the summary cache table.

---

## 4. Feature Comparison: MySQL vs. PostgreSQL

| Feature | MySQL 8.0 | PostgreSQL |
| :--- | :--- | :--- |
| Standard Virtual Views | Natively Supported | Natively Supported |
| Updatable Views | Supported (with restrictions) | Supported (with restrictions) |
| Native Materialized Views | *Not Supported* | Natively Supported |
| Concurrent/Non-Blocking Refresh | *Not Supported* | Natively Supported (via `CONCURRENTLY`) |
| View Security (DEFINER/INVOKER) | Natively Supported | Natively Supported |

> [!TIP]
> **Security Tip: INVOKER vs. DEFINER**
> By default, standard views in MySQL and PostgreSQL run with the privileges of the user who *created* the view (`DEFINER`). This is a powerful feature that allows you to grant users access to specific subsets of data in a table (like excluding a password column) without granting them read permissions to the entire underlying table.

---

## 5. Summary & Best Practices

1. **Use Standard Views for Abstraction**: Standard views are excellent for simplifying complex queries and implementing database security roles.
2. **Use Materialized Views for Caching**: For heavy aggregations on read-heavy systems, cache data physically.
3. **Always Refresh Concurrently**: In production PostgreSQL environments, always create a unique index on your materialized views so you can refresh them concurrently.
4. **Use Scheduler or Triggers in MySQL**: If using MySQL, architect a cache table using events or application-level caching to simulate materialized views.
