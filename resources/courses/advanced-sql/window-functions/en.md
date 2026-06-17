# Window Functions: Partitioning, Ordering, and Framing

Imagine you are building a dashboard to display a list of customer transactions. You need to show the transaction details, but you also want to display a running total of spending for each customer, their rank based on transaction amount, and the amount of their previous transaction. 

Using standard `GROUP BY` collapses your rows, losing individual transaction details. Doing this in application logic requires fetching all records and looping through them, which is slow and memory-intensive. **Window functions** solve this by performing calculations across a set of table rows that are related to the current row, without collapsing the result set.

---

## 1. The Core Mechanics: Grouping vs. Windowing

Unlike `GROUP BY`, which aggregates multiple rows into a single summary row, window functions compute an aggregate or rank for each row individually while preserving all detail fields.

```
GROUP BY:
[Row 1] \
[Row 2]  --> [Aggregated Row]
[Row 3] /

WINDOW FUNCTION:
[Row 1] --> [Row 1] [Calculated Value 1]
[Row 2] --> [Row 2] [Calculated Value 2]
[Row 3] --> [Row 3] [Calculated Value 3]
```

### Basic Syntax
```sql
-- MySQL 8.0+ & PostgreSQL
SELECT 
    employee_id, 
    department_id, 
    salary,
    SUM(salary) OVER(PARTITION BY department_id) AS dept_total_salary
FROM employees;
```
* **`PARTITION BY`**: Divides rows into groups (partitions) that share the same values. If omitted, the entire result set is treated as a single partition.
* **`ORDER BY`**: Defines the physical sorting order of rows inside each partition. This determines how values are processed sequentially.

---

## 2. Ranking and Value Functions

Window functions are categorized into aggregates (like `SUM` or `AVG`), ranking functions, and value-retrieval functions.

### Ranking: `ROW_NUMBER()`, `RANK()`, and `DENSE_RANK()`
When values are identical (ties), these functions behave differently:
* **`ROW_NUMBER()`**: Assigns a unique, sequential integer starting at 1. Ties are resolved arbitrarily.
* **`RANK()`**: Assigns a rank with gaps. If two rows tie for 1st place, both get rank 1, and the next rank is 3.
* **`DENSE_RANK()`**: Assigns a rank without gaps. If two rows tie for 1st place, both get rank 1, and the next rank is 2.

| Employee | Salary | `ROW_NUMBER()` | `RANK()` | `DENSE_RANK()` |
| :--- | :--- | :--- | :--- | :--- |
| Alice | $10,000 | 1 | 1 | 1 |
| Bob | $10,000 | 2 | 1 | 1 |
| Charlie | $8,000 | 3 | 3 | 2 |
| David | $7,000 | 4 | 4 | 3 |

### Value Functions: `LAG()`, `LEAD()`, and `FIRST_VALUE()`
* **`LAG(col, offset, default)`**: Accesses a value from a row at a specific physical offset *before* the current row.
* **`LEAD(col, offset, default)`**: Accesses a value from a row at a specific physical offset *after* the current row.

```sql
-- Fetch current and previous transaction amount to calculate the difference
SELECT 
    transaction_date,
    amount,
    LAG(amount, 1, 0) OVER(ORDER BY transaction_date) AS prev_amount
FROM transactions;
```

---

## 3. Window Framing: The Sliding Window

The window frame defines a dynamic subset of rows within the partition, relative to the current row. The frame shifts as the database engine processes each row.

### Frame Types: `ROWS` vs. `RANGE` vs. `GROUPS`
* **`ROWS`**: Counts physical rows relative to the current row (e.g., 5 rows before).
* **`RANGE`**: Counts logical values based on the column in the `ORDER BY` clause. It includes all rows sharing the same values as the current row (ties are treated together).
* **`GROUPS`**: Groups of duplicate values based on the ordering column.

```sql
-- Running Total Frame Example
SUM(amount) OVER(
    PARTITION BY user_id 
    ORDER BY transaction_date
    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
)
```

> [!WARNING]
> **The `LAST_VALUE()` Trap!**
> If you write `LAST_VALUE(salary) OVER(PARTITION BY dept_id ORDER BY salary)`, you might expect it to return the highest salary in the department. Instead, it returns the salary of the current row!
> This is because when `ORDER BY` is present, the default frame is:
> `RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW`
> To fix this, explicitly specify the frame:
> `LAST_VALUE(salary) OVER(PARTITION BY dept_id ORDER BY salary RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING)`

---

## 4. MySQL vs. PostgreSQL: Architectural Differences

While both engines comply with ANSI SQL standards, they differ in features, syntax extensions, and execution performance.

### 1. The `FILTER` Clause (PostgreSQL Only)
PostgreSQL supports the `FILTER` clause with aggregate window functions, allowing you to selectively aggregate rows without using complex `CASE WHEN` constructs.
```sql
-- PostgreSQL Only
SELECT 
    department_id,
    COUNT(employee_id) FILTER (WHERE salary > 5000) OVER(PARTITION BY department_id) AS highly_paid_count
FROM employees;
```
In MySQL, you must write:
```sql
-- MySQL Equivalent
SELECT 
    department_id,
    SUM(CASE WHEN salary > 5000 THEN 1 ELSE 0 END) OVER(PARTITION BY department_id) AS highly_paid_count
FROM employees;
```

### 2. Frame Exclusions (PostgreSQL 11+ Only)
PostgreSQL supports advanced frame exclusion options, allowing you to exclude specific rows from the window frame calculation.
* `EXCLUDE CURRENT ROW`: Excludes the current row from the frame.
* `EXCLUDE GROUP`: Excludes the current row and all of its ordering peers.
* *MySQL 8.0 does not support `EXCLUDE` clauses in window framing.*

> [!TIP]
> **Performance Tip:**
> Window functions are executed during the final stage of query processing (after `WHERE` and `GROUP BY`). To optimize them, create a composite index matching the `PARTITION BY` and `ORDER BY` columns. This allows the query engine to retrieve sorted data directly, avoiding expensive sorting operations (filesorts).

---

## 5. Summary & Best Practices

1. **Keep Row Identity**: Use window functions when you need aggregate calculations alongside individual row fields.
2. **Watch the Defaults**: Remember that adding an `ORDER BY` automatically changes the default window frame, which affects cumulative sums and functions like `LAST_VALUE()`.
3. **Use Indexes**: Always verify that your `PARTITION BY` and `ORDER BY` keys are indexed to prevent database temporary tables on disk.
