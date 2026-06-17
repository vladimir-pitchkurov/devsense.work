# Aggregates and Grouping

So far, we have only retrieved individual rows. In many scenarios, however, you need to analyze data at a higher level: finding the average salary of an employee, counting total orders, or finding the most expensive product in a specific category. 

SQL provides powerful **aggregate functions** and the `GROUP BY` clause to collapse thousands of rows into meaningful summaries. In this chapter, we will master grouping data, filtering those groups using the `HAVING` clause, and examining how MySQL and PostgreSQL differ in their execution.

---

## 1. Aggregate Functions

Aggregate functions perform a calculation on a set of values and return a single value.

* **`COUNT()`**: Returns the number of rows.
* **`SUM()`**: Returns the sum of numeric values.
* **`AVG()`**: Returns the average of numeric values.
* **`MIN()`**: Returns the smallest value.
* **`MAX()`**: Returns the largest value.

```sql
-- MySQL & PostgreSQL
-- Calculate count, average price, and max price for all products
SELECT COUNT(*) AS total_products, AVG(price) AS average_price, MAX(price) AS highest_price 
FROM products;
```

### Aggregate Functions and `NULL` Values
It is a common misconception that aggregate functions include `NULL` values in their math.
* Most aggregate functions (like `SUM`, `AVG`, `MIN`, `MAX`) **completely ignore NULL values**.
* `COUNT(column_name)` counts only rows where the specified column is **not NULL**.
* `COUNT(*)` counts every row, including rows with `NULL` values.

```sql
-- If we have 5 users, and only 3 have a middle name:
SELECT COUNT(*) FROM users;            -- Returns 5
SELECT COUNT(middle_name) FROM users;  -- Returns 3
```

---

## 2. Grouping Data with `GROUP BY`

The `GROUP BY` clause divides the rows of a table into groups. The database engine then applies the aggregate functions to each group independently.

```sql
-- MySQL & PostgreSQL
-- Get average price per product category
SELECT category, AVG(price) AS avg_price 
FROM products 
GROUP BY category;
```

---

## 3. Filtering Groups with `HAVING`

What if you want to find only the categories where the average price is greater than $100? You might try to write:
```sql
-- THIS WILL FAIL!
SELECT category, AVG(price) FROM products WHERE AVG(price) > 100 GROUP BY category;
```
This fails because the `WHERE` clause filters rows **before** they are grouped and aggregated. The database engine does not know the average price yet when evaluating the `WHERE` condition.

To filter groups, you must use the `HAVING` clause, which executes **after** grouping.

| Clause | Where It Filters | Can Use Aggregates? |
| :--- | :--- | :--- |
| **`WHERE`** | Individual rows (before grouping). | No |
| **`HAVING`** | Grouped results (after grouping). | Yes |

```sql
-- MySQL & PostgreSQL
-- Correct way to filter aggregated groups
SELECT category, AVG(price) AS avg_price 
FROM products 
WHERE is_available = true -- 1. Filters rows
GROUP BY category         -- 2. Groups remaining rows
HAVING AVG(price) > 100;  -- 3. Filters groups
```

---

## 4. Key Differences: MySQL vs. PostgreSQL

### The Strict Grouping Rule
Standard SQL dictates that when using `GROUP BY`, any column in your `SELECT` list that is not wrapped in an aggregate function **must** be declared in the `GROUP BY` clause.
* **PostgreSQL** enforces this rule strictly. If you select a column not in the `GROUP BY` clause (and not functionally dependent on primary keys in the `GROUP BY`), Postgres throws a syntax error.
* **MySQL** behaves similarly under its default `ONLY_FULL_GROUP_BY` SQL mode. However, if this mode is disabled, MySQL allows you to select columns not listed in `GROUP BY`, returning a random row's value for those columns—a frequent source of bugs.

```sql
-- Violating the strict grouping rule
SELECT category, brand, AVG(price) 
FROM products 
GROUP BY category;
```
* **PostgreSQL**: Fails immediately with `ERROR: column "products.brand" must appear in the GROUP BY clause...`
* **MySQL**: Fails only if `ONLY_FULL_GROUP_BY` is enabled. If disabled, it returns the category, a random brand from that category, and the average price.

### String Aggregation (`GROUP_CONCAT` vs. `string_agg`)
If you want to combine text values from grouped rows into a single comma-separated string:
* **MySQL** uses the `GROUP_CONCAT()` function.
* **PostgreSQL** uses the `string_agg()` function and requires an explicit `ORDER BY` clause syntax if order is desired.

```sql
-- MySQL: Combines tags per product
SELECT product_id, GROUP_CONCAT(tag_name ORDER BY tag_name SEPARATOR ', ') AS tags
FROM product_tags
GROUP BY product_id;

-- PostgreSQL: Combines tags per product
SELECT product_id, string_agg(tag_name, ', ' ORDER BY tag_name) AS tags
FROM product_tags
GROUP BY product_id;
```

> [!WARNING]
> **The `AVG()` Null Trap!**
> Because `AVG()` ignores `NULL` values, it can distort your business metrics. For example, if you calculate the average employee bonus, and 9 out of 10 employees have `NULL` (unknown/none) bonuses, `AVG(bonus)` will calculate the average based on the single employee with a bonus, rather than dividing the total by 10. Use `COALESCE(bonus, 0)` inside the aggregate to treat `NULL` as `0`.

> [!TIP]
> **Did you know?**
> You can perform conditional aggregation by combining `SUM` or `COUNT` with expressions. In modern PostgreSQL, you can use the cleaner `FILTER` clause:
> `SELECT count(*) FILTER (WHERE price > 100) AS expensive_count FROM products;`
> In MySQL, you must use a `CASE` statement inside the aggregate:
> `SELECT SUM(CASE WHEN price > 100 THEN 1 ELSE 0 END) AS expensive_count FROM products;`

---

## 5. Summary & Best Practices

1. **Keep SELECT Clean**: Ensure every non-aggregated column in your `SELECT` list is also in your `GROUP BY` clause to maintain cross-engine compatibility.
2. **`WHERE` vs `HAVING`**: Use `WHERE` to filter raw rows before aggregation, and `HAVING` to filter aggregated groups.
3. **Handle NULLs in Metrics**: Remember that aggregate calculations (except `COUNT(*)`) ignore `NULL`. Wrap columns in `COALESCE` to default them to 0 if necessary.
4. **Learn Dialect Functions**: Use `GROUP_CONCAT` in MySQL and `string_agg` in PostgreSQL when concatenating strings across grouped rows.
