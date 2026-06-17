# Relational Databases & The SELECT Statement

At the core of modern data storage is the relational database model, first proposed by Edgar F. Codd in 1970. Instead of storing data in unstructured text files or rigid hierarchical trees, a relational database organizes information into structured tables that can be joined and queried dynamically using Structured Query Language (SQL). Whether you are using MySQL or PostgreSQL, understanding the foundational concepts of relational design and how to retrieve data efficiently is crucial.

---

## 1. The Relational Model: Tables, Columns, and Keys

In a relational database, data is represented as a collection of relation tables. Every table consists of:
* **Columns (Attributes)**: Define the data type and properties of the information stored (e.g., `user_id` as an integer, `email` as a string).
* **Rows (Records/Tuples)**: Represent individual instances of data (e.g., a specific user's record).

To maintain integrity, tables rely on two essential concepts:
1. **Primary Key (PK)**: A column (or set of columns) that uniquely identifies each row in a table. It cannot contain `NULL` values.
2. **Foreign Key (FK)**: A column in one table that references the Primary Key of another table, establishing a relationship between them.

| Concept | Description | Analogy |
| :--- | :--- | :--- |
| **Table** | A structured grid of columns and rows. | A spreadsheet tab. |
| **Row** | A single data record. | A single line in a spreadsheet. |
| **Primary Key** | Unique identifier for a row. | Passport number. |
| **Foreign Key** | Reference to another table's PK. | An employee's department ID. |

---

## 2. Retrieving Data with `SELECT`

The most fundamental operation in SQL is retrieving data using the `SELECT` statement. In its simplest form, a query requires a `SELECT` clause (what columns to retrieve) and a `FROM` clause (what table to query).

### Selecting All Columns vs. Specific Columns
You can retrieve all columns using the asterisk wildcard (`*`), or specify only the columns you need.
```sql
-- MySQL & PostgreSQL
-- Retrieve all columns (not recommended for production)
SELECT * FROM users;

-- Retrieve specific columns (recommended)
SELECT id, email, first_name FROM users;
```

> [!TIP]
> **Did you know?**
> Using `SELECT *` in production is considered an anti-pattern. It forces the database engine to perform extra disk I/O to read columns you don't need, consumes unnecessary network bandwidth, and can break your application if a new column is added or an old one is removed from the table.

### Column Aliasing (`AS`)
Aliases allow you to rename columns in your query result set for better readability or to match application expectations.
```sql
-- MySQL & PostgreSQL
SELECT first_name AS given_name, last_name AS surname FROM users;
```

---

## 3. Filtering Results with `WHERE`

To retrieve only specific rows, use the `WHERE` clause. The `WHERE` clause evaluates a boolean condition for each row, returning only those that evaluate to true.

### Standard Operators
SQL supports standard comparison operators:
* `=` (equal to)
* `<>` or `!=` (not equal to)
* `>` (greater than), `<` (less than)
* `>=` (greater than or equal to), `<=` (less than or equal to)

```sql
-- Retrieve active users registered after a specific ID
SELECT email FROM users WHERE is_active = true AND id > 100;
```

### Advanced Filtering Operators
* **`BETWEEN`**: Filters values within a specific range (inclusive of endpoints).
* **`IN`**: Checks if a value matches any value in a specified list.
* **`LIKE`**: Performs simple pattern matching using wildcards:
  - `%` represents zero or more characters.
  - `_` represents exactly one character.

```sql
-- Retrieve users with IDs 1, 3, or 5
SELECT email FROM users WHERE id IN (1, 3, 5);

-- Retrieve users registered in a specific range
SELECT email FROM users WHERE id BETWEEN 10 AND 50;

-- Retrieve users whose email starts with 'admin'
SELECT email FROM users WHERE email LIKE 'admin%';
```

---

## 4. Key Differences: MySQL vs. PostgreSQL

While both engines follow standard SQL, they diverge in syntax and default behaviors.

### Quoting Identifiers
Identifiers (table names, column names) need to be quoted if they conflict with SQL reserved keywords or contain special characters/spaces.
* **MySQL**: Uses backticks (`` ` ``).
* **PostgreSQL**: Uses double quotes (`"`).

```sql
-- MySQL
SELECT `select`, `group` FROM `my_table`;

-- PostgreSQL
SELECT "select", "group" FROM "my_table";
```

### Case Sensitivity in Pattern Matching
* **PostgreSQL** is strictly case-sensitive for `LIKE`. To perform a case-insensitive search, you must use the PostgreSQL-specific `ILIKE` operator.
* **MySQL**'s `LIKE` is case-insensitive by default under standard collations (e.g., `utf8mb4_0900_ai_ci`). To make it case-sensitive, you must cast the string to binary or use a binary collation.

```sql
-- Case-insensitive search for 'john'
-- PostgreSQL
SELECT email FROM users WHERE email ILIKE 'john%';

-- MySQL
SELECT email FROM users WHERE email LIKE 'john%';
```

### String Concatenation
* **PostgreSQL** uses the standard SQL double-pipe operator (`||`).
* **MySQL** does not support `||` for concatenation by default (it treats `||` as the logical `OR` operator unless the `PIPES_AS_CONCAT` SQL mode is enabled). Instead, MySQL uses the `CONCAT()` function.

```sql
-- PostgreSQL
SELECT first_name || ' ' || last_name AS full_name FROM users;

-- MySQL
SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users;
```

### Boolean Data Types
* **PostgreSQL** has a native `BOOLEAN` type supporting literal values `true` and `false`.
* **MySQL** does not have a true boolean type; it aliases `BOOLEAN` to `TINYINT(1)`, where `0` is false and `1` is true.

```sql
-- PostgreSQL: Returns true/false
SELECT is_active FROM users;

-- MySQL: Returns 1/0
SELECT is_active FROM users;
```

> [!WARNING]
> **Quoting Trap!**
> Never use double quotes (`"`) for string literals (text values) in PostgreSQL. PostgreSQL treats double quotes as identifier quotes (for column or table names), which will cause a `column "value" does not exist` syntax error. Always use single quotes (`'`) for string literals in both MySQL and PostgreSQL.

---

## 5. Summary & Best Practices

1. **Be Specific**: Explicitly list column names instead of using `SELECT *` to improve performance and code durability.
2. **Standardize Quotes**: Use single quotes (`'`) for string literals across all database engines.
3. **Know Your Operator**: Use `ILIKE` in PostgreSQL for case-insensitive matches, and remember that `||` concatenation is standard in Postgres but requires `CONCAT()` in MySQL.
4. **Boolean Checks**: Remember that MySQL stores booleans as `1` or `0`, which can affect how your application code parses query results.
