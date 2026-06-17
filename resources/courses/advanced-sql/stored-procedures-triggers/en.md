# Stored Procedures, Functions, and Triggers

Moving business logic closer to your data can significantly reduce network latency and ensure strict data integrity. When you write programming logic that executes directly inside the database, you use three main constructs: **Functions**, **Stored Procedures**, and **Triggers**. 

Understanding how these elements manage transactions, access memory, and differ across MySQL and PostgreSQL is essential for designing reliable databases.

---

## 1. User-Defined Functions (UDFs) vs. Stored Procedures

While both group SQL statements together, they serve very different purposes. The most critical difference lies in **transaction control**.

| Feature | User-Defined Function (UDF) | Stored Procedure |
| :--- | :--- | :--- |
| **Invocation** | Called inline inside queries (e.g., `SELECT my_func(val)`) | Called standalone using `CALL my_proc(val)` |
| **Return Value** | Must return a single value or table | Does not return a value (uses `OUT` parameters instead) |
| **Transactions** | **Cannot** execute `COMMIT` or `ROLLBACK` | **Can** execute `COMMIT` or `ROLLBACK` |
| **Usage Context** | Read/Transform data in queries | Orchestrate complex batch writes and operations |

### Transaction Control Example (Stored Procedure)
Stored procedures can control transactions natively, allowing you to commit or rollback changes mid-execution.

```sql
-- PostgreSQL 11+ Syntax
CREATE PROCEDURE transfer_funds(sender INT, receiver INT, amount DECIMAL)
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE accounts SET balance = balance - amount WHERE id = sender;
    UPDATE accounts SET balance = balance + amount WHERE id = receiver;
    
    -- Commit the transaction inside the procedure
    COMMIT;
EXCEPTION WHEN OTHERS THEN
    -- Rollback changes if anything fails
    ROLLBACK;
END;
$$;

-- MySQL Syntax
CREATE PROCEDURE transfer_funds(IN sender INT, IN receiver INT, IN amount DECIMAL)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;
    UPDATE accounts SET balance = balance - amount WHERE id = sender;
    UPDATE accounts SET balance = balance + amount WHERE id = receiver;
    COMMIT;
END;
```

---

## 2. Triggers: Automating Business Rules

A **Trigger** is a database object that automatically runs a specified set of SQL statements when an event (such as `INSERT`, `UPDATE`, or `DELETE`) occurs on a table.

### Trigger Execution Timing
* **`BEFORE`**: Executes *before* the database writes the changes to disk. Used to validate or modify incoming values.
* **`AFTER`**: Executes *after* the database writes the changes to disk. Used for logging, auditing, or updating other tables.
* **`INSTEAD OF`**: Used on views to override default write actions with custom logic.

### Row-Level vs. Statement-Level Triggers
* **`FOR EACH ROW`**: The trigger executes once for every single row affected by the query. If an update affects 10,000 rows, the trigger runs 10,000 times.
* **`FOR EACH STATEMENT`**: The trigger executes exactly once per SQL statement, regardless of how many rows are modified. Useful for logging or bulk checks.

### The `OLD` and `NEW` Pseudo-Records
Triggers have access to special variables representing the state of the row:
* **`NEW`**: Holds the new row values (available in `INSERT` and `UPDATE`).
* **`OLD`**: Holds the original row values (available in `UPDATE` and `DELETE`).

---

## 3. MySQL vs. PostgreSQL: Syntactic and Structural Differences

The execution architecture for triggers and procedures differs heavily between these two databases.

### 1. Trigger Architecture: Functions vs. Inline
* **PostgreSQL**: Requires a two-step process. First, you must write a trigger function that returns the special type `TRIGGER`. Second, you bind that function to the table using `CREATE TRIGGER`.
```sql
-- PostgreSQL: 1. Create Trigger Function
CREATE FUNCTION log_salary_change() RETURNS TRIGGER AS $$
BEGIN
    IF NEW.salary <> OLD.salary THEN
        INSERT INTO salary_audit(emp_id, old_val, new_val)
        VALUES (OLD.id, OLD.salary, NEW.salary);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- PostgreSQL: 2. Bind Function to Table
CREATE TRIGGER after_salary_update
AFTER UPDATE ON employees
FOR EACH ROW EXECUTE FUNCTION log_salary_change();
```

* **MySQL**: Allows you to write the trigger logic directly inline inside the trigger definition.
```sql
-- MySQL: Direct inline trigger definition
CREATE TRIGGER after_salary_update
AFTER UPDATE ON employees
FOR EACH ROW
BEGIN
    IF NEW.salary <> OLD.salary THEN
        INSERT INTO salary_audit(emp_id, old_val, new_val)
        VALUES (OLD.id, OLD.salary, NEW.salary);
    END IF;
END;
```

### 2. Language Support
* **MySQL**: Only supports its proprietary SQL syntax extension.
* **PostgreSQL**: Supports multiple languages. While `PL/pgSQL` is the default, you can write database functions and triggers in `PL/Python`, `PL/Perl`, or `PL/v8` (JavaScript).

> [!WARNING]
> **Performance Overhead of Triggers!**
> Row-level triggers (`FOR EACH ROW`) can cause severe performance degradation on bulk updates. If you update 1 million rows, a row-level trigger forces the database to context-switch between the SQL execution engine and the procedural interpreter 1 million times, turning a fast set-based query into a slow row-by-row loop.

> [!TIP]
> **Transaction Constraint in PostgreSQL:**
> Although PostgreSQL 11+ stored procedures support transaction commands (`COMMIT`/`ROLLBACK`), they cannot execute them if the procedure is called from inside an already active transaction block (e.g., if you run `BEGIN; CALL my_procedure();`).

---

## 4. Summary & Best Practices

1. **Functions for Calculations**: Use UDFs for transformations and calculations inside queries. Do not try to modify data or control transactions inside them.
2. **Procedures for Workflows**: Use stored procedures to run batch updates, orchestrate complex writes, and control transactions.
3. **Keep Triggers Lightweight**: Only use triggers for critical data integrity, logging, or auditing. If you must use them, keep the logic minimal to prevent write bottlenecks.
4. **Be Careful with Bulk Ops**: Disable or bypass triggers during massive data imports or migrations to avoid server performance collapse.
