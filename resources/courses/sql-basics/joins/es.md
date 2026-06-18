# Joining Tables

En una base de datos relacional normalizada, los datos se dividen en varias tablas para eliminar la redundancia y mantener la integridad. Por ejemplo, en lugar de repetir los detalles del departamento para cada empleado, almacenas los empleados en una tabla y los departamentos en otra, vinculándolos mediante una clave foránea (Foreign Key).

Para reconstruir la vista unificada de tus datos, debes utilizar **Uniones (Joins)**. Una unión combina columnas de dos o más tablas basándose en una columna relacionada entre ellas. En este capítulo, dominaremos los diferentes tipos de uniones, sus condiciones y exploraremos cómo los ejecutan PostgreSQL y MySQL.

---

## 1. Visualización de los tipos de unión (Join)

SQL ofrece varias formas de unir tablas, cada una con una lógica diferente:

| Tipo de unión | Descripción |
| :--- | :--- |
| **`INNER JOIN`** | Devuelve los registros que tienen valores coincidentes en ambas tablas. |
| **`LEFT JOIN`** | Devuelve todos los registros de la tabla izquierda y los registros coincidentes de la derecha. Devuelve `NULL` para la tabla derecha si no hay coincidencia. |
| **`RIGHT JOIN`** | Devuelve todos los registros de la tabla derecha y los registros coincidentes de la izquierda. Devuelve `NULL` para la tabla izquierda si no hay coincidencia. |
| **`FULL JOIN`** | Devuelve todos los registros cuando hay una coincidencia en cualquiera de las tablas (izquierda o derecha). |
| **`CROSS JOIN`** | Devuelve el producto cartesiano de ambas tablas (cada fila de la tabla A emparejada con cada fila de la tabla B). |

---

## 2. Sintaxis y ejemplos

Supongamos que tenemos dos tablas: `employees` (con una columna `department_id`) y `departments` (con una columna `id`).

### Inner Join
Recupera únicamente a los empleados que pertenecen a un departamento y solo a los departamentos que tienen empleados.
```sql
-- MySQL & PostgreSQL
SELECT e.name AS employee_name, d.name AS department_name
FROM employees e
INNER JOIN departments d ON e.department_id = d.id;
```

### Left Join (La unión externa más común)
Recupera a todos los empleados, incluidos aquellos que no pertenecen a ningún departamento (su `department_name` se devolverá como `NULL`).
```sql
-- MySQL & PostgreSQL
SELECT e.name AS employee_name, d.name AS department_name
FROM employees e
LEFT JOIN departments d ON e.department_id = d.id;
```

### Condiciones de unión: `ON` vs. `USING`
Cuando las columnas sobre las que estás realizando la unión tienen exactamente el mismo nombre en ambas tablas (por ejemplo, `department_id` tanto en `employees` como en `departments`), puedes utilizar la abreviación `USING`, más limpia, en lugar de `ON`.
```sql
-- MySQL & PostgreSQL
SELECT e.name, d.name
FROM employees e
INNER JOIN departments d USING (department_id);
```

---

## 3. Diferencias clave: MySQL vs. PostgreSQL

### Soporte nativo para `FULL OUTER JOIN`
* **PostgreSQL** admite de forma nativa el estándar `FULL OUTER JOIN` (or simplemente `FULL JOIN`).
* **MySQL** NO admite `FULL JOIN`. Si intentas utilizarlo, MySQL lanzará un error de sintaxis.

#### Solución alternativa en MySQL para FULL JOIN
Para lograr un Full Outer Join en MySQL, debes escribir un `LEFT JOIN` y un `RIGHT JOIN` de las mismas tablas, y combinar sus resultados mediante el operador `UNION` (que elimina automáticamente las filas duplicadas).

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

### Algoritmos de unión y rendimiento bajo el capó
¿Cómo calculan las uniones los motores de bases de datos? Utilizan diferentes algoritmos internos:
* **PostgreSQL**: Cuenta con un optimizador muy sofisticado que ha admitido **Nested Loop Joins** (uniones de bucle anidado), **Hash Joins** (uniones hash) y **Merge Joins** (uniones por mezcla) durante décadas. Selecciona el mejor algoritmo en función del tamaño de la tabla y la disponibilidad de índices.
* **MySQL**: Históricamente, MySQL solo admitía **Nested Loop Joins** (que son lentas para tablas grandes porque requieren recorrer en bucle la segunda tabla para cada fila de la primera). MySQL 8.0 introdujo las **Hash Joins** para optimizar las operaciones de unión en columnas grandes no indexadas, acercándolo al rendimiento de unión de PostgreSQL.

> [!WARNING]
> **¡La trampa del producto cartesiano!**
> Ejecutar un `CROSS JOIN` (o calificar sin condición de unión en la sintaxis SQL antigua como `FROM tabla_a, tabla_b`) crea un producto cartesiano. Si la Tabla A tiene 10,000 filas y la Tabla B tiene 10,000 filas, un CROSS JOIN generará **100,000,000 (100 millones) de filas**, lo que puede agotar instantáneamente la memoria del servidor y congelar la base de datos.

> [!TIP]
> **¿Sabías que...?**
> Al filtrar columnas en un `LEFT JOIN`, colocar el filtro en la cláusula `ON` frente a la cláusula `WHERE` cambia el resultado por completo.
> - **En `ON`**: Filtra la tabla de la derecha *antes* de realizar la unión. La tabla de la izquierda sigue devolviendo todas sus filas.
> - **En `WHERE`**: Filtra el resultado *después* de realizar la unión, lo que convierte efectivamente tu `LEFT JOIN` en un `INNER JOIN` restrictivo, ya que verifica valores en una columna que podría haberse convertido en `NULL`.

---

## 4. Resumen y mejores prácticas

1. **Prefiere `LEFT JOIN` sobre `RIGHT JOIN`**: Las uniones por la izquierda son mucho más fáciles de leer y visualizar, ya que las consultas SQL se leen de izquierda a derecha (de arriba a abajo).
2. **Ten cuidado con el `WHERE` en uniones externas**: No filtres columnas de la tabla de la derecha en la cláusula `WHERE` a menos que estés buscando coincidencia nula (`IS NULL`) para encontrar filas no emparejadas.
3. **Recuerda la limitación de `FULL JOIN` en MySQL**: Utiliza la solución alternativa de `LEFT JOIN UNION RIGHT JOIN` en MySQL.
4. **Usa sintaxis de unión explícita**: Utiliza siempre palabras clave de unión explícitas (`INNER JOIN`, `LEFT JOIN`) en lugar de separar las tablas por comas en la cláusula `FROM` (`FROM tabla_a, tabla_b`) para evitar productos cartesianos accidentales.
