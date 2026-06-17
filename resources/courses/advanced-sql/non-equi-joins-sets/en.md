# Non-Equi Joins and Set Operations

Most SQL tutorials focus heavily on joining tables using the equality operator (`ON a.id = b.id`). However, real-world database problems often require matching records based on ranges, intervals, inequalities, or merging datasets using mathematical set theory. 

Understanding **non-equi joins** and **advanced set operations** is what separates junior SQL writers from senior database engineers.

---

## 1. Non-Equi Joins: Beyond Equality

A **non-equi join** is a join condition that uses operators other than the equals sign (`=`), such as `<`, `>`, `<=`, `>=`, `BETWEEN`, or `!=`.

### Use Case: Overlapping Date Intervals
Imagine you are managing hotel room bookings. You need to check if a new booking request overlaps with any existing bookings.
* **Logic**: A collision occurs if the requested start date is before an existing booking's end date, AND the requested end date is after the existing booking's start date.

```sql
-- MySQL & PostgreSQL
SELECT 
    b1.room_id,
    b1.booking_id AS booking_1,
    b2.booking_id AS booking_2
FROM bookings b1
JOIN bookings b2 ON b1.room_id = b2.room_id
    AND b1.booking_id < b2.booking_id -- Avoid self-matching and duplicate pairs
    AND b1.start_date < b2.end_date 
    AND b1.end_date > b2.start_date;
```

### Use Case: Range Grouping (e.g., Price Tiers)
You can assign products to price brackets without hardcoding the tiers or using complex nested loops.

```sql
-- MySQL & PostgreSQL
SELECT 
    p.product_name, 
    p.price, 
    t.tier_name
FROM products p
JOIN price_tiers t ON p.price BETWEEN t.min_price AND t.max_price;
```

> [!WARNING]
> **Performance Gotcha with Non-Equi Joins!**
> While modern query planners use efficient **Hash Joins** for equi-joins, they cannot use them for non-equi conditions. Instead, they fall back to **Nested Loop Joins** or **Block Nested Loops**. This can result in `O(N * M)` time complexity.
> In PostgreSQL, you can mitigate this using **Range Types** and **GiST Indexes**. MySQL lacks native range types, meaning you must optimize composite B-Tree indexes carefully.

---

## 2. Advanced Set Operations

Set operations combine the results of two or more queries into a single result set.

```
Set operations:
[Query 1] UNION [Query 2]      --> Returns all unique rows from both queries.
[Query 1] INTERSECT [Query 2]  --> Returns rows present in BOTH queries.
[Query 1] EXCEPT [Query 2]     --> Returns rows in Query 1 but NOT in Query 2.
```

### `UNION` vs. `UNION ALL`
* **`UNION`**: Merges result sets and removes duplicates. To do this, the database must sort the data or build a temporary hash table, which incurs a performance cost.
* **`UNION ALL`**: Merges result sets but preserves all duplicates. It does not perform any sorting or deduplication, making it much faster.

### `INTERSECT` and `EXCEPT` (With and Without `ALL`)
Standard SQL defines two modes for set operations:
1. **Default (Distinct)**: Removes duplicate rows before returning results.
2. **`ALL`**: Preserves duplicate cardinality. For example, if a row appears 3 times in Query 1 and 2 times in Query 2:
   - `INTERSECT ALL` returns it `MIN(3, 2) = 2` times.
   - `EXCEPT ALL` returns it `3 - 2 = 1` time.

---

## 3. MySQL vs. PostgreSQL: Compatibility & Syntax

This is one of the areas where the two database engines diverge significantly, particularly regarding older versions.

### Set Operations Support Table

| Operator | PostgreSQL (All versions) | MySQL 8.0.31+ | MySQL < 8.0.31 |
| :--- | :--- | :--- | :--- |
| `UNION` / `UNION ALL` | Natively Supported | Natively Supported | Natively Supported |
| `INTERSECT` (Distinct) | Natively Supported | Natively Supported | *Not Supported* |
| `EXCEPT` (Distinct) | Natively Supported | Natively Supported | *Not Supported* |
| `INTERSECT ALL` | Natively Supported | *Not Supported* | *Not Supported* |
| `EXCEPT ALL` | Natively Supported | *Not Supported* | *Not Supported* |

### Workarounds for MySQL (Simulation)

If you are working on MySQL versions older than 8.0.31, you must simulate `INTERSECT` and `EXCEPT` using joins or subqueries.

#### Simulating `INTERSECT`:
```sql
-- MySQL < 8.0.31 Equivalent of INTERSECT
SELECT DISTINCT a.email 
FROM users_a a
INNER JOIN users_b b ON a.email = b.email;
```

#### Simulating `EXCEPT`:
```sql
-- MySQL < 8.0.31 Equivalent of EXCEPT
SELECT DISTINCT a.email 
FROM users_a a
LEFT JOIN users_b b ON a.email = b.email
WHERE b.email IS NULL;
```

> [!TIP]
> **PostgreSQL Exclusive: Exclusion Constraints**
> In PostgreSQL, you can enforce that no two rows in a table overlap using an `EXCLUSION CONSTRAINT` with a GiST index.
> ```sql
> -- PostgreSQL Only: Prevent overlapping bookings at the database schema level
> ALTER TABLE bookings ADD CONSTRAINT no_overlap 
> EXCLUDE USING gist (room_id WITH =, tsrange(start_date, end_date) WITH &&);
> ```
> *In MySQL, enforcing this rule requires custom BEFORE INSERT/UPDATE triggers.*

---

## 4. Summary & Best Practices

1. **Prefer `UNION ALL` over `UNION`**: Unless you explicitly need to filter out duplicates, always use `UNION ALL` to avoid the database sort overhead.
2. **Mind the Joins**: When writing non-equi joins, verify the query execution plan (`EXPLAIN`) to ensure the database isn't running a slow nested loop on millions of rows.
3. **Handle Compatibility**: If writing queries for cross-database applications, avoid native `INTERSECT`/`EXCEPT` or simulation-heavy syntax. Use `EXISTS` and `LEFT JOIN` structures instead.
