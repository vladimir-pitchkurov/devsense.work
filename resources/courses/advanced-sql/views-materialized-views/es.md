# Views and Materialized Views: Abstraction vs. Caching

En el diseño de bases de datos de alto rendimiento, a menudo nos enfrentamos a dos problemas contrapuestos:
1. **Complejidad de las consultas**: Escribir y mantener consultas largas y anidadas que abarcan docenas de uniones.
2. **Latencia de ejecución**: Ejecutar consultas con agregaciones pesadas (como informes de ventas) sobre millones de filas en cada carga de página.

SQL aborda estos desafíos con las **Vistas** y las **Vistas materializadas**. Aunque suenan similares, sus modelos de ejecución subyacentes son completamente diferentes: uno es un atajo lógico (abstracción) y el otro es un almacenamiento caché físico en una tabla.

---

## 1. Vistas estándar (virtuales)

Una **Vista** estándar es una definición de consulta guardada. Es una tabla virtual; no almacena ningún dato físico en el disco. Cuando consultas una vista, el motor de la base de datos fusiona la definición de consulta de la vista en la consulta principal y las ejecuta juntas.

### Sintaxis básica
```sql
-- MySQL & PostgreSQL
CREATE VIEW active_customer_summary AS
SELECT c.id, c.name, COUNT(o.id) AS total_orders
FROM customers c
LEFT JOIN orders o ON c.id = o.customer_id
WHERE c.status = 'active'
GROUP BY c.id, c.name;
```

### Vistas actualizables
¿Se pueden ejecutar sentencias `INSERT`, `UPDATE` o `DELETE` en una vista? Sí, bajo condiciones estrictas. Una vista es actualizable únicamente si el motor de la base de datos puede mapear las operaciones de escritura directamente a una sola tabla física subyacente.
* **Reglas**: La vista no debe contener:
  - Funciones de agregación (`SUM`, `COUNT`, `AVG`).
  - Cláusulas `GROUP BY`, `HAVING` o `DISTINCT`.
  - Operadores de conjuntos (`UNION`, `INTERSECT`, `EXCEPT`).
  - Funciones de ventana.

> [!WARNING]
> **La cláusula `WITH CHECK OPTION`**
> Al actualizar datos a través de una vista, ¡puedes escribir accidentalmente datos que hagan que la fila desaparezca de la propia vista!
> ```sql
> CREATE VIEW premium_customers AS 
> SELECT * FROM customers WHERE balance > 1000;
> ```
> Si ejecutas `UPDATE premium_customers SET balance = 500 WHERE id = 1`, la actualización tendrá éxito, pero el cliente desaparecerá de la vista. Para evitar esto, añade `WITH CHECK OPTION` a la definición de la vista. Esto obliga a la base de datos a rechazar cualquier inserción o actualización que viole la cláusula `WHERE` de la vista.

---

## 2. Vistas materializadas (PostgreSQL)

A diferencia de las vistas estándar, una **Vista materializada** almacena físicamente los resultados de la consulta en el disco, comportándose como una tabla normal. Consultar una vista materializada es sumamente rápido porque evita realizar las uniones y los cálculos de agregación. Sin embargo, los datos pueden quedar desactualizados.

### Sintaxis y actualización (refresh)
```sql
-- PostgreSQL Only
CREATE MATERIALIZED VIEW monthly_revenue_report AS
SELECT extract(year from order_date) as year, extract(month from order_date) as month, SUM(total_amount) as revenue
FROM orders
GROUP BY 1, 2;
```

Para actualizar los datos, debes desencadenar manualmente una actualización:
```sql
REFRESH MATERIALIZED VIEW monthly_revenue_report;
```

### Actualizaciones no bloqueantes: `CONCURRENTLY`
Por defecto, `REFRESH MATERIALIZED VIEW` coloca un bloqueo exclusivo sobre la vista, bloqueando todas las operaciones de lectura (`SELECT`) hasta que finalice la actualización. Para actualizar la vista sin bloquear a tus usuarios, utiliza la opción `CONCURRENTLY`.

```sql
-- PostgreSQL Only: Non-blocking refresh
CREATE UNIQUE INDEX idx_monthly_rev ON monthly_revenue_report (year, month);
REFRESH MATERIALIZED VIEW CONCURRENTLY monthly_revenue_report;
```
* **Requisito**: Debes crear un índice único en una o más columnas de la vista materializada antes de poder utilizar la cláusula `CONCURRENTLY`.

---

## 3. Soluciones alternativas en MySQL para vistas materializadas

MySQL **no** admite de forma nativa las vistas materializadas. Si necesitas esta funcionalidad en MySQL, debes simularla utilizando una de las dos soluciones alternativas comunes.

### Solución alternativa 1: Tabla normal + Programador de eventos (Event Scheduler)
Puedes crear una tabla normal para que actúe como caché y escribir un evento de base de datos programado para actualizarla periódicamente.

```sql
-- MySQL Only
-- 1. Create the physical table
CREATE TABLE monthly_revenue_report_cache AS
SELECT YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as revenue
FROM orders GROUP BY 1, 2;

-- 2. Create an event to refresh the table every hour
CREATE EVENT refresh_revenue_report
ON SCHEDULE EVERY 1 HOUR
DO
  BEGIN
    TRUNCATE TABLE monthly_revenue_report_cache;
    INSERT INTO monthly_revenue_report_cache
    SELECT YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as revenue
    FROM orders GROUP BY 1, 2;
  END;
```

### Solución alternativa 2: Disparadores de base de datos (Triggers)
Si necesitas vistas materializadas en tiempo real en MySQL, puedes escribir disparadores `AFTER INSERT/UPDATE/DELETE` en la tabla de origen para actualizar de manera incremental la tabla caché de resumen.

---

## 4. Comparación de características: MySQL vs. PostgreSQL

| Característica | MySQL 8.0 | PostgreSQL |
| :--- | :--- | :--- |
| Vistas virtuales estándar | Soportado de forma nativa | Soportado de forma nativa |
| Vistas actualizables | Soportado (con restricciones) | Soportado (con restricciones) |
| Vistas materializadas nativas | *No soportado* | Soportado de forma nativa |
| Actualización concurrente/no bloqueante | *No soportado* | Soportado de forma nativa (mediante `CONCURRENTLY`) |
| Seguridad de la vista (DEFINER/INVOKER) | Soportado de forma nativa | Soportado de forma nativa |

> [!TIP]
> **Sugerencia de seguridad: INVOKER vs. DEFINER**
> Por defecto, las vistas estándar en MySQL y PostgreSQL se ejecutan con los privilegios del usuario que *creó* la vista (`DEFINER`). Esta es una característica potente que te permite otorgar a los usuarios acceso a subconjuntos de datos específicos en una tabla (como excluir una columna de contraseña) sin concederles permisos de lectura en toda la tabla subyacente.

---

## 5. Resumen y mejores prácticas

1. **Usa vistas estándar para abstracción**: Las vistas estándar son excelentes para simplificar consultas complejas e implementar roles de seguridad en la base de datos.
2. **Usa vistas materializadas para almacenamiento en caché**: Para agregaciones pesadas en sistemas con lecturas intensivas, almacena físicamente los datos en caché.
3. **Actualiza siempre de forma concurrente**: En entornos de producción de PostgreSQL, crea siempre un índice único en tus vistas materializadas para poder actualizarlas de manera concurrente.
4. **Usa programadores (Scheduler) o disparadores (Triggers) en MySQL**: Si utilizas MySQL, diseña una tabla caché mediante eventos o almacenamiento en caché a nivel de aplicación para simular las vistas materializadas.
