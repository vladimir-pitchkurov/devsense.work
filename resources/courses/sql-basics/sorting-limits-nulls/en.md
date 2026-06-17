# Sorting, Limits, and NULL Values

Retrieving data in a random order is rarely useful. In real-world applications, you need to present records sorted alphabetically, numerically, or chronologically. Furthermore, databases often contain massive datasets, making it necessary to limit the volume of retrieved rows (e.g., for pagination). Finally, you must understand how SQL handles missing or unknown data represented by `NULL`. 

In this chapter, we explore how to organize, restrict, and safely query data using `ORDER BY`, `LIMIT`, and `NULL` comparisons, along with the distinct ways MySQL and PostgreSQL handle these features.

---

## 1. Sorting Results with `ORDER BY`

By default, relational database engines return rows in an unspecified order (often based on how they are physically stored on disk). To guarantee a specific order, you must use the `ORDER BY` clause.

### Ascending and Descending Order
* **`ASC` (Ascending)**: Sorts values from lowest to highest (default behavior).
* **`DESC` (Descending)**: Sorts values from highest to lowest.

```sql
-- MySQL & PostgreSQL
-- Sort products by price, highest first
SELECT name, price FROM products ORDER BY price DESC;
```

### Multi-Column Sorting
You can sort by multiple columns. The engine first sorts by the first column, and if there are duplicate values, it sorts those duplicates by the second column, and so on.
```sql
-- MySQL & PostgreSQL
-- Sort by category alphabetically, and then by price descending within each category
SELECT category, name, price 
FROM products 
ORDER BY category ASC, price DESC;
```

---

## 2. Restricting Output: `LIMIT` and `OFFSET`

Retrieving millions of rows can crash your application server and lock up database resources. To prevent this, you can restrict the result set.

* **`LIMIT`**: Specifies the maximum number of rows to return.
* **`OFFSET`**: Skips a specific number of rows before returning results (commonly used for pagination).

```sql
-- MySQL & PostgreSQL
-- Get the second page of products (items 11-20)
SELECT name, price 
FROM products 
ORDER BY price DESC 
LIMIT 10 OFFSET 10;
```

---

## 3. The Mystery of `NULL` Values

In SQL, `NULL` represents a lack of data, an unknown value, or a missing attribute. It is **not** equivalent to an empty string `''` or the number `0`.

### The Three-Valued Logic Trap
In standard programming languages, `true` and `false` are the only boolean states. SQL, however, uses **Three-Valued Logic**: `true`, `false`, and `unknown` (represented by `NULL`). 

Because `NULL` means "unknown," you cannot compare it using standard operators like `=` or `!=`. For example:
* Is an unknown value equal to 5? **Unknown (`NULL`)**.
* Is one unknown value equal to another unknown value? **Unknown (`NULL`)**.

```sql
-- THIS WILL NOT WORK! It returns zero rows.
SELECT * FROM users WHERE middle_name = NULL;
```

### Correct NULL Comparisons
To check if a column is empty or populated, you must use `IS NULL` or `IS NOT NULL`.
```sql
-- MySQL & PostgreSQL
-- Correct way to find users without a middle name
SELECT email FROM users WHERE middle_name IS NULL;

-- Correct way to find users with a middle name
SELECT email FROM users WHERE middle_name IS NOT NULL;
```

---

## 4. Key Differences: MySQL vs. PostgreSQL

### Null Ordering (`NULLS FIRST` vs. `NULLS LAST`)
When sorting a column that contains `NULL` values, how does the engine position them?
* **MySQL**: Treats `NULL` as the lowest possible value. In ascending order (`ASC`), `NULL`s appear first. In descending order (`DESC`), `NULL`s appear last.
* **PostgreSQL**: Treats `NULL` as the highest possible value. In ascending order (`ASC`), `NULL`s appear last. In descending order (`DESC`), `NULL`s appear first.

However, PostgreSQL supports the standard SQL override: `NULLS FIRST` or `NULLS LAST`. MySQL does not support this natively.

| Database | Order | Default NULL Position | Custom Override |
| :--- | :--- | :--- | :--- |
| **MySQL** | `ASC` | First | None (Requires workaround) |
| **MySQL** | `DESC` | Last | None (Requires workaround) |
| **PostgreSQL** | `ASC` | Last | `ORDER BY price ASC NULLS FIRST` |
| **PostgreSQL** | `DESC` | First | `ORDER BY price DESC NULLS LAST` |

#### MySQL Workaround for Null Ordering
To force `NULL`s to the end of an ascending sort in MySQL, you can use a boolean expression check:
```sql
-- MySQL: NULLs sorted last in ascending order
SELECT name, price FROM products ORDER BY price IS NULL ASC, price ASC;
```

### Non-Standard `LIMIT` Syntax
* **MySQL** supports a shorthand comma-separated syntax: `LIMIT offset, row_count`.
* **PostgreSQL** does not support this syntax and will throw a syntax error.

```sql
-- MySQL Only (Shorthand syntax: limit 10 rows, skipping the first 5)
SELECT name FROM products LIMIT 5, 10;

-- PostgreSQL & MySQL Standard (Recommended)
SELECT name FROM products LIMIT 10 OFFSET 5;
```

> [!WARNING]
> **Offset Performance Trap!**
> Using a high `OFFSET` (e.g., `LIMIT 10 OFFSET 500000`) forces the database engine to scan and discard 500,000 rows before returning the 10 rows you requested. This causes severe performance degradation on large tables. For deep pagination, prefer cursor-based pagination (keyset pagination) using `WHERE id > last_seen_id LIMIT 10`.

> [!TIP]
> **Did you know?**
> The SQL standard defines `FETCH FIRST n ROWS ONLY` instead of `LIMIT`. While both MySQL and PostgreSQL support `LIMIT`, PostgreSQL also supports the official standard:
> `SELECT name FROM products ORDER BY price DESC FETCH FIRST 10 ROWS ONLY;`

---

## 5. Summary & Best Practices

1. **Always Use `ORDER BY` with `LIMIT`**: Without sorting, `LIMIT` will return a random set of rows depending on database state.
2. **Never Use `=` with `NULL`**: Always use `IS NULL` or `IS NOT NULL`.
3. **Use Standard `LIMIT/OFFSET`**: Avoid MySQL's comma-separated `LIMIT` shorthand to keep queries portable.
4. **Mind the Null Sorts**: Be aware that MySQL places `NULL`s first on `ASC`, whereas PostgreSQL places them last. Use Postgres's `NULLS FIRST/LAST` modifiers or MySQL IS NULL sorting tricks when exact behavior is required.
