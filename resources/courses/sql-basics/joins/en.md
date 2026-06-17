# Joining Tables

In a normalized relational database, data is split across multiple tables to eliminate redundancy and maintain integrity. For instance, rather than repeating department details for every employee, you store employees in one table and departments in another, linking them via a Foreign Key. 

To rebuild the unified view of your data, you must use **Joins**. A join combines columns from two or more tables based on a related column between them. In this chapter, we will master the different types of joins, their conditions, and explore how PostgreSQL and MySQL execute them.

---

## 1. Visualizing Join Types

SQL offers several ways to join tables, each serving a different logic:

| Join Type | Description |
| :--- | :--- |
| **`INNER JOIN`** | Returns records that have matching values in both tables. |
| **`LEFT JOIN`** | Returns all records from the left table, and matched records from the right. Returns `NULL` for the right table if no match. |
| **`RIGHT JOIN`** | Returns all records from the right table, and matched records from the left. Returns `NULL` for the left table if no match. |
| **`FULL JOIN`** | Returns all records when there is a match in either left or right table. |
| **`CROSS JOIN`** | Returns the Cartesian product of both tables (every row from table A paired with every row from table B). |

---

## 2. Syntax and Examples

Let's assume we have two tables: `employees` (with a `department_id` column) and `departments` (with an `id` column).

### Inner Join
Retrieves only employees who belong to a department, and only departments that have employees.
```sql
-- MySQL & PostgreSQL
SELECT e.name AS employee_name, d.name AS department_name
FROM employees e
INNER JOIN departments d ON e.department_id = d.id;
```

### Left Join (Most Common Outer Join)
Retrieves all employees, including those who do not belong to any department (their `department_name` will be returned as `NULL`).
```sql
-- MySQL & PostgreSQL
SELECT e.name AS employee_name, d.name AS department_name
FROM employees e
LEFT JOIN departments d ON e.department_id = d.id;
```

### Join Conditions: `ON` vs. `USING`
When the columns you are joining on have the exact same name in both tables (e.g. `department_id` in both `employees` and `departments`), you can use the cleaner `USING` shorthand instead of `ON`.
```sql
-- MySQL & PostgreSQL
SELECT e.name, d.name
FROM employees e
INNER JOIN departments d USING (department_id);
```

---

## 3. Key Differences: MySQL vs. PostgreSQL

### Native Support for `FULL OUTER JOIN`
* **PostgreSQL** natively supports standard `FULL OUTER JOIN` (or simply `FULL JOIN`).
* **MySQL** does NOT support `FULL JOIN`. If you attempt to use it, MySQL will throw a syntax error.

#### The MySQL Workaround for FULL JOIN
To achieve a Full Outer Join in MySQL, you must write a `LEFT JOIN` and a `RIGHT JOIN` of the same tables, and combine their results using the `UNION` operator (which automatically removes duplicate rows).

```sql
-- PostgreSQL (Native syntax)
SELECT e.name, d.name
FROM employees e
FULL JOIN departments d ON e.department_id = d.id;

-- MySQL Workaround (Emulation)
SELECT e.name, d.name
FROM employees e
LEFT JOIN departments d ON e.department_id = d.id
UNION
SELECT e.name, d.name
FROM employees e
RIGHT JOIN departments d ON e.department_id = d.id;
```

### Join Algorithms and Performance Under the Hood
How do the database engines compute joins? They use different internal algorithms:
* **PostgreSQL**: Features a highly sophisticated optimizer that has supported **Nested Loop Joins**, **Hash Joins**, and **Merge Joins** for decades. It selects the best algorithm based on table size and index availability.
* **MySQL**: Historically, MySQL only supported **Nested Loop Joins** (which are slow for large tables because they require looping through the second table for every row of the first). MySQL 8.0 introduced **Hash Joins** to optimize join operations on large, non-indexed columns, bringing it closer to PostgreSQL's join performance.

> [!WARNING]
> **The Cartesian Product Trap!**
> Running a `CROSS JOIN` (or forgetting a join condition in older SQL syntax like `FROM table_a, table_b`) creates a Cartesian product. If Table A has 10,000 rows and Table B has 10,000 rows, a CROSS JOIN generates **100,000,000 (100 million) rows**, which can instantly exhaust server memory and freeze the database.

> [!TIP]
> **Did you know?**
> When filtering columns in a `LEFT JOIN`, putting the filter in the `ON` clause versus the `WHERE` clause changes the result completely.
> - **In `ON`**: Filters the right table *before* joining. The left table still returns all its rows.
> - **In `WHERE`**: Filters the result *after* joining, effectively turning your `LEFT JOIN` into a restrictive `INNER JOIN` because it checks for values in a column that might have become `NULL`.

---

## 4. Summary & Best Practices

1. **Prefer `LEFT JOIN` over `RIGHT JOIN`**: Left joins are much easier to read and visualize since SQL queries are read left-to-right (top-to-bottom).
2. **Be Careful with `WHERE` on Outer Joins**: Don't filter columns of the right table in the `WHERE` clause unless you are checking for `IS NULL` to find unmatched rows.
3. **Remember MySQL's `FULL JOIN` Limitation**: Use the `LEFT JOIN UNION RIGHT JOIN` workaround in MySQL.
4. **Use Explicit Join Syntax**: Always use explicit join keywords (`INNER JOIN`, `LEFT JOIN`) instead of comma-separated tables in the `FROM` clause (`FROM table_a, table_b`) to prevent accidental Cartesian products.
