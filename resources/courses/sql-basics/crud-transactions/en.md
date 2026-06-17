# CRUD and Transactions

Retrieving data is only half the battle. To build dynamic applications, you must write, modify, and delete records (operations collectively known as CRUD: Create, Read, Update, Delete). Furthermore, when executing multiple related database modifications (such as transferring money between two accounts), you must guarantee that either all operations succeed or none do. This is where **Transactions** come in.

In this final chapter, we will master mutating data, implementing transactions, and understanding key differences in how PostgreSQL and MySQL manage transactional states and write conflicts.

---

## 1. Modifying Data: INSERT, UPDATE, and DELETE

### Inserting Records
You can insert a single row or perform a bulk insert in a single query.
```sql
-- MySQL & PostgreSQL
-- Single insert
INSERT INTO users (email, name) VALUES ('bob@devsense.work', 'Bob');

-- Bulk insert (recommended for performance)
INSERT INTO users (email, name) 
VALUES 
    ('alice@devsense.work', 'Alice'),
    ('charlie@devsense.work', 'Charlie');
```

### Updating Records
Modifies existing records. Always ensure you filter your update using a `WHERE` clause.
```sql
-- MySQL & PostgreSQL
UPDATE users SET is_active = true WHERE id = 42;
```

### Deleting Records
Removes records permanently. Like `UPDATE`, it requires a `WHERE` clause to avoid catastrophic data loss.
```sql
-- MySQL & PostgreSQL
DELETE FROM users WHERE is_active = false;
```

> [!WARNING]
> **The Missing `WHERE` Clause Trap!**
> If you run `UPDATE users SET is_active = true;` or `DELETE FROM users;` without a `WHERE` clause, the engine will modify or delete **every single row** in your table. Always double-check your query before execution!

---

## 2. Transactions: Safeguarding Data Integrity

A transaction is a sequence of SQL statements executed as a single, indivisible unit of work. Transactions adhere to the **ACID** standards:
* **Atomicity**: All statements succeed, or the entire transaction is rolled back (all-or-nothing).
* **Consistency**: Ensures the database transitions from one valid state to another, respecting all constraints.
* **Isolation**: Transactions executing concurrently do not interfere with each other.
* **Durability**: Once committed, changes are permanently written to disk and survive system crashes.

### Transaction Commands
* **`BEGIN` / `START TRANSACTION`**: Starts the transaction block.
* **`COMMIT`**: Saves all changes made during the transaction permanently.
* **`ROLLBACK`**: Discards all changes made during the transaction, restoring the database to its pre-transaction state.

```sql
-- MySQL & PostgreSQL
BEGIN; -- Start transaction

UPDATE accounts SET balance = balance - 100 WHERE id = 1;
UPDATE accounts SET balance = balance + 100 WHERE id = 2;

-- If everything is fine:
COMMIT;

-- If a query failed or we changed our mind:
-- ROLLBACK;
```

---

## 3. Key Differences: MySQL vs. PostgreSQL

### Upsert Syntax (Insert or Update on Key Conflict)
An "upsert" inserts a row if it doesn't exist, or updates it if it conflicts with an existing unique index or primary key.
* **MySQL**: Uses the `ON DUPLICATE KEY UPDATE` clause.
* **PostgreSQL**: Uses the `ON CONFLICT (conflict_column) DO UPDATE` clause.

```sql
-- MySQL (ON DUPLICATE KEY UPDATE)
INSERT INTO users (id, email, visits) 
VALUES (1, 'user@devsense.work', 1)
ON DUPLICATE KEY UPDATE visits = visits + 1;

-- PostgreSQL (ON CONFLICT DO UPDATE)
INSERT INTO users (id, email, visits) 
VALUES (1, 'user@devsense.work', 1)
ON CONFLICT (id) DO UPDATE SET visits = users.visits + 1;
```

### Transactional DDL (Data Definition Language)
This is one of the most critical structural differences between the two databases.
* **PostgreSQL** supports fully transactional DDL. You can run commands like `CREATE TABLE`, `DROP TABLE`, or `ALTER TABLE` inside a transaction and safely roll them back if something goes wrong.
* **MySQL** does NOT support transactional DDL. If you run a DDL statement inside a transaction in MySQL, it triggers an **implicit commit** (also known as automatic commit). MySQL immediately commits the transaction up to that point, executing the DDL, and you cannot roll back any preceding writes.

```sql
-- PostgreSQL (This works and will be fully rolled back!)
BEGIN;
DROP TABLE users;
ROLLBACK; -- Table 'users' is safely restored!

-- MySQL (This will fail to roll back!)
START TRANSACTION;
INSERT INTO logs (message) VALUES ('Deleting users table');
DROP TABLE users; -- Triggers implicit commit!
ROLLBACK; -- Does nothing; the insert and DROP are already committed!
```

### Transaction Start syntax
* **PostgreSQL**: Prefers the standard `BEGIN` command (though it accepts `START TRANSACTION`).
* **MySQL**: Prefers `START TRANSACTION` (though it accepts `BEGIN` in most client contexts; however, inside stored procedures, `BEGIN` is reserved for block structures, so `START TRANSACTION` is required).

> [!TIP]
> **Did you know?**
> PostgreSQL supports the `RETURNING` clause for write operations. This allows you to immediately retrieve the generated primary key or computed columns without running a separate `SELECT` query.
> `INSERT INTO users (email) VALUES ('new@devsense.work') RETURNING id, created_at;`
> *MySQL does not support `RETURNING` and requires client libraries to call functions like `LAST_INSERT_ID()` to retrieve the generated key.*

---

## 4. Summary & Best Practices

1. **Keep Transactions Short**: Long-running transactions hold locks on database rows, blocking other users and increasing the likelihood of deadlocks.
2. **Beware of Implicit Commits in MySQL**: Do not mix schema changes (DDL like `ALTER TABLE`) with data changes (DML like `UPDATE`) inside transactions if you plan to rely on rollbacks.
3. **Use Upserts Carefully**: Match the correct syntax for your database (`ON CONFLICT` for PostgreSQL and `ON DUPLICATE KEY UPDATE` for MySQL) to handle duplicate key constraint violations gracefully.
4. **Always Specify WHERE**: Protect yourself from accidental tables wipes by ensuring `UPDATE` and `DELETE` queries contain strict `WHERE` clauses.
