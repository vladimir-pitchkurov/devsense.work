# CRUD and Transactions

Recuperar datos es solo la mitad de la batalla. Para crear aplicaciones dinámicas, debes escribir, modificar y eliminar registros (operaciones conocidas colectivamente como CRUD: Create, Read, Update, Delete - Crear, Leer, Actualizar, Eliminar). Además, cuando ejecutas múltiples modificaciones de base de datos relacionadas (como transferir dinero entre dos cuentas), debes garantizar que todas las operaciones tengan éxito o ninguna lo tenga. Aquí es donde entran las **Transacciones**.

En este último capítulo, dominaremos la mutación de datos, la implementación de transacciones y comprenderemos las diferencias clave en cómo PostgreSQL y MySQL gestionan los estados transaccionales y los conflictos de escritura.

---

## 1. Modificación de datos: INSERT, UPDATE y DELETE

### Insertar registros
Puedes insertar una sola fila o realizar una inserción masiva (bulk insert) en una sola consulta.
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

### Actualizar registros
Modifica registros existentes. Asegúrate siempre de filtrar tu actualización utilizando una cláusula `WHERE`.
```sql
-- MySQL & PostgreSQL
UPDATE users SET is_active = true WHERE id = 42;
```

### Eliminar registros
Elimina registros de forma permanente. Al igual que `UPDATE`, requiere una cláusula `WHERE` para evitar una pérdida catastrófica de datos.
```sql
-- MySQL & PostgreSQL
DELETE FROM users WHERE is_active = false;
```

> [!WARNING]
> **¡La trampa de la cláusula `WHERE` omitida!**
> Si ejecutas `UPDATE users SET is_active = true;` o `DELETE FROM users;` sin una cláusula `WHERE`, el motor modificará o eliminará **cada una de las filas** de tu tabla. ¡Comprueba siempre dos veces tu consulta antes de ejecutarla!

---

## 2. Transacciones: salvaguardar la integridad de los datos

Una transacción es una secuencia de sentencias SQL ejecutadas como una unidad de trabajo única e indivisible. Las transacciones se adhieren a los estándares **ACID**:
* **Atomicidad (Atomicity)**: Todas las sentencias tienen éxito o toda la transacción se revierte (todo o nada).
* **Consistencia (Consistency)**: Asegura que la base de datos pase de un estado válido a otro, respetando todas las restricciones.
* **Aislamiento (Isolation)**: Las transacciones que se ejecutan simultáneamente no interfieren entre sí.
* **Durabilidad (Durability)**: Una vez confirmada (committed), los cambios se escriben permanentemente en el disco y sobreviven a caídas del sistema.

### Comandos de transacción
* **`BEGIN` / `START TRANSACTION`**: Inicia el bloque de transacción.
* **`COMMIT`**: Guarda de forma permanente todos los cambios realizados durante la transacción.
* **`ROLLBACK`**: Descarta todos los cambios realizados durante la transacción, restaurando la base de datos a su estado anterior a la transacción.

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

## 3. Diferencias clave: MySQL vs. PostgreSQL

### Sintaxis de Upsert (Insertar o actualizar en conflicto de claves)
Un "upsert" inserta una fila si no existe, o la actualiza si entra en conflicto con un índice único o clave primaria existente.
* **MySQL**: Utiliza la cláusula `ON DUPLICATE KEY UPDATE`.
* **PostgreSQL**: Utiliza la cláusula `ON CONFLICT (columna_conflicto) DO UPDATE`.

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

### DDL transaccional (Lenguaje de Definición de Datos)
Esta es una de las diferencias estructurales más críticas entre ambas bases de datos.
* **PostgreSQL** admite DDL totalmente transaccional. Puedes ejecutar comandos como `CREATE TABLE`, `DROP TABLE` o `ALTER TABLE` dentro de una transacción y revertirlos de forma segura si algo sale mal.
* **MySQL** NO admite DDL transaccional. Si ejecutas una sentencia DDL dentro de una transacción en MySQL, esto desencadena una **confirmación implícita** (también conocida como commit automático). MySQL confirma inmediatamente la transacción hasta ese momento, ejecuta el DDL y no podrás revertir ninguna escritura precedente.

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

### Sintaxis de inicio de transacción
* **PostgreSQL**: Prefiere el comando estándar `BEGIN` (aunque acepta `START TRANSACTION`).
* **MySQL**: Prefiere `START TRANSACTION` (aunque acepta `BEGIN` en la mayoría de los contextos de cliente; sin embargo, dentro de procedimientos almacenados, `BEGIN` está reservado para estructuras de bloque, por lo que se requiere `START TRANSACTION`).

> [!TIP]
> **¿Sabías que...?**
> PostgreSQL admite la cláusula `RETURNING` para operaciones de escritura. Esto te permite recuperar inmediatamente la clave primaria generada o las columnas calculadas sin ejecutar una consulta `SELECT` separada.
> `INSERT INTO users (email) VALUES ('new@devsense.work') RETURNING id, created_at;`
> *MySQL no admite `RETURNING` y requiere que las bibliotecas del cliente llamen a funciones como `LAST_INSERT_ID()` para recuperar la clave generada.*

---

## 4. Resumen y mejores prácticas

1. **Mantén las transacciones cortas**: Las transacciones de larga duración retienen bloqueos en las filas de la base de datos, lo que bloquea a otros usuarios e incrementa la probabilidad de interbloqueos (deadlocks).
2. **Cuidado con las confirmaciones implícitas en MySQL**: No mezcles cambios de esquema (DDL como `ALTER TABLE`) con cambios de datos (DML como `UPDATE`) dentro de las transacciones si planeas depender de las reversiones (rollbacks).
3. **Usa los upserts con cuidado**: Adapta la sintaxis correcta para tu base de datos (`ON CONFLICT` para PostgreSQL y `ON DUPLICATE KEY UPDATE` para MySQL) para manejar con elegancia las violaciones de restricciones de clave duplicada.
4. **Especifica siempre el WHERE**: Protégete de borrados accidentales de tablas asegurándote de que las consultas `UPDATE` y `DELETE` contengan cláusulas `WHERE` estrictas.
