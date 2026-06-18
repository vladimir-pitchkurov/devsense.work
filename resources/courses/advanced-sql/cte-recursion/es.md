# Common Table Expressions (CTE) & Recursion

Imagina depurar una consulta SQL de 200 líneas repleta de subconsultas profundamente anidadas, donde la misma subconsulta se duplica tres veces solo para realizar uniones automáticas (self-joins). Es una pesadilla de mantenimiento y los analizadores de planes de consulta de bases de datos tienen dificultades para optimizarla. O piensa en representar un organigrama corporativo (gerentes y subordinados) o un árbol de categorías de productos de múltiples niveles. En lenguajes imperativos como PHP o JavaScript, recuperarías todas las filas y ejecutarías bucles recursivos. Pero hacerlo a través de la red es lento y sumamente ineficiente. Aquí es donde las **Expresiones de tabla comunes (CTEs - Common Table Expressions)** y las **CTEs recursivas** acuden al rescate.

Una CTE actúa como un conjunto de resultados temporal y con nombre que existe solo dentro del alcance de ejecución de una única consulta. Funciona como una vista en línea dinámica y legible.

---

## 1. CTEs no recursivas y secuenciales

### Sintaxis y estructura
* **Punto**: Las CTEs te permiten definir conjuntos de resultados temporales utilizando la cláusula `WITH` antes de tu sentencia principal `SELECT`, `INSERT`, `UPDATE` o `DELETE`.
* **Por qué es importante**: Descompone consultas complejas en pasos lógicos y legibles, reemplazando las subconsultas anidadas y haciendo que el código sea autodocumentado.
* **Ejemplo**:
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
* **Consecuencia**: La consulta se lee linealmente de arriba a abajo. Evitas duplicar subconsultas y la depuración se vuelve tan simple como seleccionar desde una sola CTE.

> [!TIP]
> **¿Sabías que...?**
> Las CTEs se pueden utilizar para aislar operaciones de escritura. En PostgreSQL, puedes escribir datos en una CTE y seleccionar los IDs resultantes para insertarlos en otra tabla dentro de la misma consulta.
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
> *MySQL no admite sentencias que modifiquen datos (INSERT/UPDATE/DELETE) dentro de las CTEs.*

---

## 2. CTEs recursivas (`WITH RECURSIVE`)

Cuando necesitas recorrer estructuras de datos jerárquicas, las uniones SQL estándar fallan porque se desconoce la profundidad del árbol. Las **CTEs recursivas** solucionan esto ejecutando repetidamente una consulta hasta que no se devuelvan nuevas filas.

### La anatomía de la recursividad
Una CTE recursiva consta de tres partes:
1. **Miembro ancla (Anchor Member)**: La consulta base que inicializa el conjunto de resultados (se ejecuta una vez).
2. **Miembro recursivo (Recursive Member)**: La consulta que hace referencia a la propia CTE y se une con el resultado del paso anterior.
3. **Condición de terminación (Termination Condition)**: Se activa implícitamente cuando el miembro recursivo devuelve cero filas.

```
Flujo de ejecución:
[Consulta ancla] ---> Filas iniciales
        |
        +---> [Consulta recursiva] (Se ejecuta sobre las filas ancla) ---> Filas del Paso 1
                    |
                    +---> [Consulta recursiva] (Se ejecuta sobre las filas del Paso 1) ---> Filas del Paso 2
                                |
                                +---> Devuelve un conjunto vacío ---> TERMINAR
```

### Ejemplo práctico: jerarquía organizativa
* **Punto**: Recorrer recursivamente una tabla que contiene relaciones padre-hijo.
* **Ejemplo**:
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
* **Consecuencia**: Obtienes todo el árbol de subordinados, completo con su nivel de profundidad, en una sola consulta ejecutada completamente dentro de la base de datos.

> [!WARNING]
> **¡Protección contra bucles infinitos!**
> Si tus datos contienen una referencia circular (por ejemplo, el Empleado A reporta a B, B reporta a C, C reporta a A), una CTE recursiva se ejecutará infinitamente, causando el agotamiento de la memoria del servidor.
> - **PostgreSQL** proporciona la cláusula `CYCLE` para evitar bucles:
>   `CYCLE id SET is_cycle USING path`
> - **MySQL** no tiene la cláusula `CYCLE` pero te permite limitar la profundidad de la recursividad de forma global o por consulta:
>   `SET max_sp_recursion_depth = 255;` o usando sugerencias del optimizador (optimizer hints): `/*+ MAX_EXECUTION_TIME(1000) */`

---

## 3. Aspectos internos de la base de datos: materialización y optimización

¿Cómo ejecutan los motores de bases de datos las CTEs? ¿Las ejecutan como tablas temporales o copian y pegan su SQL directamente en la consulta principal? Los motores se comportan de manera diferente:

### Reglas de materialización de PostgreSQL
* **PostgreSQL < 12**: Históricamente, Postgres trataba a todas las CTEs como "barreras de optimización" (optimization fences). Siempre ejecutaba primero la CTE, guardaba los resultados en una tabla temporal (la materializaba) y luego la unía. Esto evitaba que el optimizador pudiera empujar los filtros `WHERE` externos hacia el interior de la CTE, causando grandes cuellos de botella en el rendimiento.
* **PostgreSQL 12+**: Cambió el comportamiento predeterminado a **NOT MATERIALIZED** (no materializado). Si se llama a una CTE solo una vez, Postgres fusiona su lógica en la consulta principal (haciendo inlining) para que pueda utilizar escaneos de índices.
* **Anulación manual**:
  - `WITH cte AS MATERIALIZED (...)` obliga a Postgres a evaluarla una vez y almacenar en caché el resultado.
  - `WITH cte AS NOT MATERIALIZED (...)` obliga a Postgres a realizar la inserción en línea (inline).

### Estimación de costo en línea de MySQL
* **MySQL 8.0**: Utiliza un optimizador basado en costos para decidir si inserta en línea una CTE o la materializa en una tabla temporal. Si la CTE es simple, MySQL siempre la inserta en línea. Si se hace referencia a ella varias veces, MySQL la materializa para evitar ejecuciones repetidas.

---

## 4. Resumen y mejores prácticas

1. **Usa CTEs para mejorar la legibilidad**: Reemplaza subconsultas anidadas complejas con CTEs secuenciales y con nombre.
2. **Cuidado con las barreras de optimización**: Si estás en PostgreSQL, ten cuidado al hacer múltiples referencias a la misma CTE. Utiliza `AS NOT MATERIALIZED` si quieres que el optimizador empuje los escaneos de índices hacia abajo.
3. **Protégete contra el desbordamiento por ciclos**: Comprueba siempre tus datos en busca de referencias circulares antes de ejecutar `WITH RECURSIVE`, o establece límites de ejecución.
