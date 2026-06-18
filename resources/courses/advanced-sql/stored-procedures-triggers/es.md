# Stored Procedures, Functions, and Triggers

Mover la lógica de negocio más cerca de tus datos puede reducir significativamente la latencia de red y garantizar una integridad de datos estricta. Cuando escribes lógica de programación que se ejecuta directamente dentro de la base de datos, utilizas tres estructuras principales: **Funciones**, **Procedimientos almacenados** y **Disparadores (Triggers)**.

Comprender cómo manejan las transacciones estos elementos, cómo acceden a la memoria y en qué se diferencian en MySQL y PostgreSQL es esencial para diseñar bases de datos confiables.

---

## 1. Funciones definidas por el usuario (UDFs) vs. Procedimientos almacenados

Aunque ambos agrupan sentencias SQL, sirven para propósitos muy diferentes. La diferencia más crítica radica en el **control de las transacciones**.

| Característica | Función definida por el usuario (UDF) | Procedimiento almacenado |
| :--- | :--- | :--- |
| **Invocación** | Se llama en línea dentro de las consultas (por ejemplo, `SELECT my_func(val)`) | Se llama de forma independiente mediante `CALL my_proc(val)` |
| **Valor de retorno** | Debe devolver un único valor o una tabla | No devuelve un valor (en su lugar, utiliza parámetros `OUT`) |
| **Transacciones** | **No puede** ejecutar `COMMIT` o `ROLLBACK` | **Puede** ejecutar `COMMIT` o `ROLLBACK` |
| **Contexto de uso** | Leer/Transformar datos en consultas | Orquestar escrituras y operaciones complejas por lotes (batch) |

### Ejemplo de control de transacciones (Procedimiento almacenado)
Los procedimientos almacenados pueden controlar las transacciones de forma nativa, lo que te permite confirmar (commit) o revertir (rollback) los cambios a mitad de la ejecución.

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

## 2. Disparadores (Triggers): automatización de reglas de negocio

Un **Disparador (Trigger)** es un objeto de la base de datos que ejecuta automáticamente un conjunto específico de sentencias SQL cuando ocurre un evento (como `INSERT`, `UPDATE` o `DELETE`) en una tabla.

### Momento de ejecución del disparador (Trigger Execution Timing)
* **`BEFORE`**: Se ejecuta *antes* de que la base de datos escriba los cambios en el disco. Se utiliza para validar o modificar los valores entrantes.
* **`AFTER`**: Se ejecuta *después* de que la base de datos escriba los cambios en el disco. Se utiliza para el registro de eventos (logging), auditorías o actualización de otras tablas.
* **`INSTEAD OF`**: Se utiliza en vistas para anular las acciones de escritura predeterminadas con una lógica personalizada.

### Disparadores a nivel de fila frente a nivel de sentencia
* **`FOR EACH ROW`**: El disparador se ejecuta una vez por cada fila afectada por la consulta. Si una actualización afecta a 10,000 filas, el disparador se ejecuta 10,000 veces.
* **`FOR EACH STATEMENT`**: El disparador se ejecuta exactamente una vez por sentencia SQL, independientemente de cuántas filas se modifiquen. Útil para registros o comprobaciones masivas.

### Los registros virtuales `OLD` y `NEW`
Los disparadores tienen acceso a variables especiales que representan el estado de la fila:
* **`NEW`**: Contiene los nuevos valores de la fila (disponible en `INSERT` y `UPDATE`).
* **`OLD`**: Contiene los valores originales de la fila (disponible en `UPDATE` y `DELETE`).

---

## 3. MySQL vs. PostgreSQL: diferencias sintácticas y estructurales

La arquitectura de ejecución para disparadores y procedimientos difiere enormemente entre estas dos bases de datos.

### 1. Arquitectura de los disparadores: funciones frente a inserciones en línea (Inline)
* **PostgreSQL**: Requiere un proceso de dos pasos. Primero, debes escribir una función disparadora que devuelva el tipo especial `TRIGGER`. Segundo, vinculas esa función a la tabla utilizando `CREATE TRIGGER`.
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

* **MySQL**: Te permite escribir la lógica del disparador directamente en línea dentro de la definición del disparador.
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

### 2. Soporte de lenguajes
* **MySQL**: Solo admite su extensión propietaria de sintaxis SQL.
* **PostgreSQL**: Admite múltiples lenguajes. Aunque `PL/pgSQL` es el predeterminado, puedes escribir funciones y disparadores de base de datos en `PL/Python`, `PL/Perl` o `PL/v8` (JavaScript).

> [!WARNING]
> **¡Sobrecarga de rendimiento de los disparadores!**
> Los disparadores a nivel de fila (`FOR EACH ROW`) pueden provocar una grave degradación del rendimiento en actualizaciones masivas. Si actualizas 1 millón de filas, un disparador a nivel de fila obliga a la base de datos a cambiar de contexto entre el motor de ejecución SQL y el intérprete procedimental 1 millón de veces, convirtiendo una consulta rápida basada en conjuntos en un bucle lento fila por fila.

> [!TIP]
> **Restricción de transacción en PostgreSQL:**
> Aunque los procedimientos almacenados de PostgreSQL 11+ admiten comandos de transacción (`COMMIT`/`ROLLBACK`), no pueden ejecutarlos si el procedimiento es llamado desde dentro de un bloque de transacción que ya está activo (por ejemplo, si ejecutas `BEGIN; CALL mi_procedimiento();`).

---

## 4. Resumen y mejores prácticas

1. **Funciones para cálculos**: Utiliza UDFs para transformaciones y cálculos dentro de las consultas. No intentes modificar datos ni controlar transacciones dentro de ellas.
2. **Procedimientos para flujos de trabajo**: Utiliza procedimientos almacenados para ejecutar actualizaciones por lotes (batch), orquestar escrituras complejas y controlar transacciones.
3. **Mantén los disparadores ligeros**: Solo utiliza disparadores para la integridad de datos crítica, auditorías o registro de eventos. Si debes usarlos, mantén la lógica al mínimo para evitar cuellos de botella en la escritura.
4. **Cuidado con las operaciones masivas**: Desactiva u omite los disparadores durante importaciones de datos masivas o migraciones para evitar el colapso del rendimiento del servidor.
