# Concurrency, MVCC, and Table Bloat

In a high-throughput database, thousands of transactions read and write data concurrently. If every read locked the row, databases would grind to a halt. To solve this, modern relational databases use **Multi-Version Concurrency Control (MVCC)**. The core philosophy of MVCC is simple: *readers do not block writers, and writers do not block readers*. However, MySQL and PostgreSQL implement this philosophy in fundamentally different ways, leading to completely different performance profiles, maintenance requirements, and optimization strategies.

---

## 1. How MVCC Works Under the Hood

When a transaction updates a row, the database does not overwrite the old data immediately. Instead, it maintains multiple versions of that row, allowing active transactions to see a consistent snapshot of the data based on their isolation level.

### PostgreSQL: Tuple Versioning (In-Heap)
PostgreSQL keeps all row versions (called "tuples") directly in the table heap. 
* **xmin**: The transaction ID (TxID) of the transaction that inserted the row.
* **xmax**: The transaction ID of the transaction that deleted or updated the row (initially `0` or null).

When you update a row in Postgres, it performs a logical `DELETE` followed by an `INSERT`. It marks the existing row's `xmax` with the current TxID and inserts a new row with `xmin` equal to the current TxID.

### MySQL (InnoDB): Undo Logs & Rollback Segments
MySQL's InnoDB engine takes a different approach. It performs updates in-place inside the tablespace but writes the old version of the row to a dedicated structure called the **Undo Log**.
* Each row header contains a `DB_TRX_ID` (the transaction ID that last modified the row) and a `DB_ROLL_PTR` (a roll pointer pointing to the undo log record containing the prior version).
* When a reader needs an older snapshot, InnoDB starts with the active row and traverses the undo chain backward using the roll pointer to reconstruct the data on the fly.

---

## 2. PostgreSQL Bloat, VACUUM, and HOT Updates

Because PostgreSQL stores dead tuples (old versions of updated/deleted rows) directly in the table pages, tables naturally grow in size over time. This phenomenon is known as **table bloat**.

```
PostgreSQL Heap Page Update:
+-------------------------------------------------------------+
| [Tuple v1 (xmin: 100, xmax: 101)] -> Dead Tuple (Bloat)     |
| [Tuple v2 (xmin: 101, xmax: 0)]   -> Live Tuple             |
+-------------------------------------------------------------+
```

### The Role of VACUUM
To reclaim space occupied by dead tuples, PostgreSQL runs a background process called **Autovacuum**.
* **Standard VACUUM**: Scans pages, marks dead tuples as reusable for future inserts. It does *not* return space to the OS (unless pages at the very end of the table are completely empty).
* **VACUUM FULL**: Rebuilds the entire table, shrinking it and returning space to the OS. **Warning**: This locks the table exclusively (`ACCESS EXCLUSIVE`), blocking all reads and writes.

### Heap-Only Tuple (HOT) Optimization
To avoid updating index pointers every time a row version changes, PostgreSQL uses **HOT updates**. If an update does not modify indexed columns and the page has enough free space:
1. Postgres places the new tuple on the same page.
2. It chains the old tuple to the new tuple.
3. Indexes continue to point to the old tuple; readers follow the chain.

> [!WARNING]
> **Autovacuum Tuning is Mandatory!**
> Out-of-the-box PostgreSQL autovacuum settings are notoriously conservative. On write-heavy tables, this leads to runaway bloat, causing query degradation because the database must scan dead tuples. Always tune `autovacuum_vacuum_scale_factor` (default 0.20 or 20% changed rows) down to `0.05` or lower for large tables.

---

## 3. MySQL InnoDB: Undo Purging and Truncation

In MySQL, table bloat is much less of an issue because old row versions are stored in the undo logs rather than the table space. However, InnoDB has its own concurrency bottlenecks.

### InnoDB Purge Threads
Once the oldest active transaction no longer needs an undo log record, a background process called the **Purge Thread** cleans up the undo log pages.
* If a transaction remains open for hours (e.g., a long-running reporting query), InnoDB cannot purge any undo logs created since that transaction started.
* This causes the **Undo Tablespace** to swell. In older MySQL versions, undo tablespaces could not shrink, requiring a full database rebuild to reclaim disk space.

```sql
-- MySQL: Inspecting InnoDB Undo Log & Transaction States
SELECT 
    trx_id, trx_state, trx_started, 
    TIMESTAMPDIFF(SECOND, trx_started, NOW()) AS duration_sec
FROM information_schema.innodb_trx
ORDER BY trx_started ASC;
```

> [!TIP]
> **Automatic Undo Truncation**
> In MySQL 8.0, InnoDB automatically truncates undo tablespaces by default. Ensure that `innodb_undo_log_truncate = ON` and `innodb_max_undo_log_size` (typically 1GB) are configured to automatically reclaim undo tablespace disk space.

---

## 4. MVCC Comparison Matrix

| Feature | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Old Version Storage** | In-Heap (directly in the table files) | Undo Logs (separate rollback segments) |
| **Update Mechanism** | Delete + Insert (creates new tuple) | In-place update + write old version to Undo |
| **Index Updates** | Requires updating all indexes (unless HOT) | Only updates index if indexed column changed |
| **Disk Space Cleanup** | Vacuum / Autovacuum (reclaims page slots) | Purge Threads (cleans up Undo log pages) |
| **Risk of Bloat** | High table & index bloat | High Undo log bloat with long-running transactions |

---

## 5. Summary & Best Practices

1. **Keep Transactions Short**: Both engines suffer if transactions remain open too long. In Postgres, it prevents autovacuum from cleaning dead tuples; in MySQL, it prevents purge threads from cleaning undo logs.
2. **Configure PostgreSQL Fillfactor**: For tables with high update volumes, reduce the table `fillfactor` (e.g., to 80 or 90) to leave free space on each page for HOT updates.
3. **Monitor Undo Space in MySQL**: Set alerts for long-running active transactions to prevent undo space explosion.
