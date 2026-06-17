# Common Table Expressions (CTE) & Recursion

Imagine debugging a 200-line SQL query filled with deeply nested subqueries, where the same subquery is duplicated three times just to perform self-joins. It is a maintenance nightmare, and database query plan analyzers struggle to optimize it. Or think about representing a corporate organizational chart (managers and subordinates) or a multi-level product category tree. In imperative languages like PHP or JavaScript, you would fetch all rows and run recursive loops. But doing so over the network is slow and highly inefficient. This is where **Common Table Expressions (CTEs)** and **Recursive CTEs** come to the rescue.

A CTE acts as a temporary, named result set that exists only within the execution scope of a single query. It functions like a dynamic, readable inline view.

---

## 1. Non-Recursive & Sequential CTEs

### Syntax and Structure
* **Point**: CTEs allow you to define temporary result sets using the `WITH` clause before your main `SELECT`, `INSERT`, `UPDATE`, or `DELETE` statement.
* **Why it matters**: It breaks down complex queries into logical, readable steps, replacing nested subqueries and making the code self-documenting.
* **Example**:
  ```sql
  -- MySQL & PostgreSQL
  WITH regional_sales AS (
      SELECT region, SUM(amount) AS total_sales
      FROM orders
      GROUP BY region
  ),
  top_regions AS (
      SELECT region
      FROM regional_sales
      WHERE total_sales > 100000
  )
  SELECT o.employee_id, o.amount, o.region
  FROM orders o
  JOIN top_regions t ON o.region = t.region;
  ```
* **Consequence**: The query reads linearly from top to bottom. You avoid duplicating subqueries, and debugging becomes as simple as selecting from a single CTE.

> [!TIP]
> **Did you know?**
> CTEs can be used to isolate write operations. In PostgreSQL, you can write data in a CTE and select the resulting IDs to insert into another table within the same query.
> ```sql
> -- PostgreSQL Only: Writing in a CTE
> WITH inserted_user AS (
>     INSERT INTO users (name, email)
>     VALUES ('Alice', 'alice@devsense.work')
>     RETURNING id
> )
> INSERT INTO profiles (user_id, bio)
> SELECT id, 'Software Engineer' FROM inserted_user;
> ```
> *MySQL does not support data-modifying statements (INSERT/UPDATE/DELETE) inside CTEs.*

---

## 2. Recursive CTEs (`WITH RECURSIVE`)

When you need to traverse hierarchical data structures, standard SQL joins fail because the depth of the tree is unknown. **Recursive CTEs** solve this by repeatedly executing a query until no new rows are returned.

### The Anatomy of Recursion
A recursive CTE consists of three parts:
1. **Anchor Member**: The base query that initializes the result set (runs once).
2. **Recursive Member**: The query that references the CTE itself and is joined with the previous step's result.
3. **Termination Condition**: Implicitly triggered when the recursive member returns zero rows.

```
Execution Flow:
[Anchor Query] ---> Initial Rows
        |
        +---> [Recursive Query] (Runs on Anchor rows) ---> Step 1 Rows
                    |
                    +---> [Recursive Query] (Runs on Step 1 rows) ---> Step 2 Rows
                                |
                                +---> Returns empty set ---> TERMINATE
```

### Practical Example: Organization Hierarchy
* **Point**: Recursively traverse a table containing parent-child relations.
* **Example**:
  ```sql
  -- MySQL & PostgreSQL
  WITH RECURSIVE org_chart AS (
      -- 1. Anchor: Find the CEO
      SELECT id, name, manager_id, 1 AS depth
      FROM employees
      WHERE manager_id IS NULL
      
      UNION ALL
      
      -- 2. Recursive Member: Join employees with their managers
      SELECT e.id, e.name, e.manager_id, o.depth + 1
      FROM employees e
      INNER JOIN org_chart o ON e.manager_id = o.id
  )
  SELECT * FROM org_chart ORDER BY depth;
  ```
* **Consequence**: You fetch the entire tree of subordinates, complete with their depth level, in a single query run entirely inside the database.

> [!WARNING]
> **Infinite Loop Protection!**
> If your data contains a circular reference (e.g., Employee A reports to B, B reports to C, C reports to A), a recursive CTE will run infinitely, causing server memory exhaustion.
> - **PostgreSQL** provides the `CYCLE` clause to prevent loops:
>   `CYCLE id SET is_cycle USING path`
> - **MySQL** does not have the `CYCLE` clause but lets you limit recursion depth globally or per-query:
>   `SET max_sp_recursion_depth = 255;` or using optimizer hints: `/*+ MAX_EXECUTION_TIME(1000) */`

---

## 3. Database Internals: Materialization & Optimization

How do database engines execute CTEs? Do they run them as temporary tables, or do they copy-paste their SQL directly into the main query? The engines behave differently:

### PostgreSQL Materialization Rules
* **PostgreSQL < 12**: Historically, Postgres treated all CTEs as "optimization fences." It always ran the CTE first, saved the results in a temporary table (materialized it), and then joined it. This prevented the optimizer from pushing outer `WHERE` filters down into the CTE, causing major performance bottlenecks.
* **PostgreSQL 12+**: Changed default behavior to **NOT MATERIALIZED**. If a CTE is called only once, Postgres merges its logic into the main query (inlining it) so it can use index scans.
* **Manual Override**:
  - `WITH cte AS MATERIALIZED (...)` forces Postgres to evaluate it once and cache the result.
  - `WITH cte AS NOT MATERIALIZED (...)` forces Postgres to inline it.

### MySQL Inline Costing
* **MySQL 8.0**: Uses a cost-based optimizer to decide whether to inline a CTE or materialize it into a temporary table. If the CTE is simple, MySQL always inlines it. If it is referenced multiple times, MySQL materializes it to avoid repeated executions.

---

## 4. Summary & Best Practices

1. **Use CTEs for Readability**: Replace complex nested subqueries with sequential, named CTEs.
2. **Watch the Optimization Fence**: If you are on PostgreSQL, be careful with multiple references to the same CTE. Use `AS NOT MATERIALIZED` if you want the optimizer to push index scans down.
3. **Protect Against Cycle Bloat**: Always check your data for circular references before executing `WITH RECURSIVE`, or set execution limits.
