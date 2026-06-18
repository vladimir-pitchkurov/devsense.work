# Aggregates and Grouping

Hasta ahora, solo hemos recuperado filas individuales. Sin embargo, en muchos escenarios es necesario analizar los datos a un nivel superior: encontrar el salario promedio de un empleado, contar el total de pedidos o buscar el producto más caro de una categoría específica.

SQL proporciona potentes **funciones de agregación** y la cláusula `GROUP BY` para resumir miles de filas en resúmenes significativos. En este capítulo, aprenderemos a agrupar datos, filtrar esos grupos mediante la cláusula `HAVING` y examinar cómo difieren MySQL y PostgreSQL en su ejecución.

---

## 1. Funciones de agregación

Las funciones de agregación realizan un cálculo sobre un conjunto de valores y devuelven un único valor.

* **`COUNT()`**: Devuelve el número de filas.
* **`SUM()`**: Devuelve la suma de los valores numéricos.
* **`AVG()`**: Devuelve el promedio de los valores numéricos.
* **`MIN()`**: Devuelve el valor más pequeño.
* **`MAX()`**: Devuelve el valor más grande.

```sql
-- MySQL & PostgreSQL
-- Calculate count, average price, and max price for all products
SELECT COUNT(*) AS total_products, AVG(price) AS average_price, MAX(price) AS highest_price 
FROM products;
```

### Funciones de agregación y valores `NULL`
Es un error común pensar que las funciones de agregación incluyen los valores `NULL` en sus cálculos.
* La mayoría de las funciones de agregación (como `SUM`, `AVG`, `MIN`, `MAX`) **ignoran por completo los valores NULL**.
* `COUNT(nombre_columna)` cuenta únicamente las filas donde la columna especificada **no es NULL**.
* `COUNT(*)` cuenta cada fila, incluidas aquellas con valores `NULL`.

```sql
-- If we have 5 users, and only 3 have a middle name:
SELECT COUNT(*) FROM users;            -- Returns 5
SELECT COUNT(middle_name) FROM users;  -- Returns 3
```

---

## 2. Agrupación de datos con `GROUP BY`

La cláusula `GROUP BY` divide las filas de una tabla en grupos. A continuación, el motor de la base de datos aplica las funciones de agregación a cada grupo de forma independiente.

```sql
-- MySQL & PostgreSQL
-- Get average price per product category
SELECT category, AVG(price) AS avg_price 
FROM products 
GROUP BY category;
```

---

## 3. Filtrar grupos con `HAVING`

¿Qué pasa si quieres encontrar solo las categorías donde el precio promedio es mayor a $100? Podrías intentar escribir:
```sql
-- THIS WILL FAIL!
SELECT category, AVG(price) FROM products WHERE AVG(price) > 100 GROUP BY category;
```
Esto falla porque la cláusula `WHERE` filtra las filas **antes** de agruparlas y agregarlas. El motor de la base de datos aún no conoce el precio promedio cuando evalúa la condición `WHERE`.

Para filtrar grupos, debes utilizar la cláusula `HAVING`, la cual se ejecuta **después** de la agrupación.

| Cláusula | Dónde filtra | ¿Puede usar agregaciones? |
| :--- | :--- | :--- |
| **`WHERE`** | Filas individuales (antes de agrupar). | No |
| **`HAVING`** | Resultados agrupados (después de agrupar). | Sí |

```sql
-- MySQL & PostgreSQL
-- Correct way to filter aggregated groups
SELECT category, AVG(price) AS avg_price 
FROM products 
WHERE is_available = true -- 1. Filters rows
GROUP BY category         -- 2. Groups remaining rows
HAVING AVG(price) > 100;  -- 3. Filters groups
```

---

## 4. Diferencias clave: MySQL vs. PostgreSQL

### La regla de agrupación estricta
El estándar SQL dicta que cuando se utiliza `GROUP BY`, cualquier columna en tu lista de `SELECT` que no esté dentro de una función de agregación **debe** declararse en la cláusula `GROUP BY`.
* **PostgreSQL** aplica esta regla de forma estricta. Si seleccionas una columna que no está en la cláusula `GROUP BY` (y no depende funcionalmente de las claves primarias en el `GROUP BY`), Postgres arrojará un error de sintaxis.
* **MySQL** se comporta de manera similar bajo su modo SQL predeterminado `ONLY_FULL_GROUP_BY`. Sin embargo, si este modo está desactivado, MySQL permite seleccionar columnas que no figuran en `GROUP BY`, devolviendo el valor de una fila aleatoria para esas columnas, lo cual es una fuente común de errores (bugs).

```sql
-- Violating the strict grouping rule
SELECT category, brand, AVG(price) 
FROM products 
GROUP BY category;
```
* **PostgreSQL**: Falla inmediatamente con el error `ERROR: column "products.brand" must appear in the GROUP BY clause...`
* **MySQL**: Falla solo si `ONLY_FULL_GROUP_BY` está habilitado. Si está deshabilitado, devuelve la categoría, una marca aleatoria de esa categoría y el precio promedio.

### Agregación de cadenas (`GROUP_CONCAT` vs. `string_agg`)
Si quieres combinar valores de texto de filas agrupadas en una única cadena separada por comas:
* **MySQL** utiliza la función `GROUP_CONCAT()`.
* **PostgreSQL** utiliza la función `string_agg()` y requiere una sintaxis explícita de la cláusula `ORDER BY` si se desea un orden específico.

```sql
-- MySQL: Combines tags per product
SELECT product_id, GROUP_CONCAT(tag_name ORDER BY tag_name SEPARATOR ', ') AS tags
FROM product_tags
GROUP BY product_id;

-- PostgreSQL: Combines tags per product
SELECT product_id, string_agg(tag_name, ', ' ORDER BY tag_name) AS tags
FROM product_tags
GROUP BY product_id;
```

> [!WARNING]
> **¡La trampa de los nulos en `AVG()`!**
> Debido a que `AVG()` ignora los valores `NULL`, puede distorsionar tus métricas de negocio. Por ejemplo, si calculas el bono promedio de los empleados y 9 de cada 10 empleados tienen bonos `NULL` (desconocido/ninguno), `AVG(bonus)` calculará el promedio basándose únicamente en el único empleado que sí tiene un bono, en lugar de dividir el total entre 10. Utiliza `COALESCE(bonus, 0)` dentro de la agregación para tratar `NULL` como `0`.

> [!TIP]
> **¿Sabías que...?**
> Puedes realizar agregaciones condicionales combinando `SUM` o `COUNT` con expresiones. En el PostgreSQL moderno, puedes usar la cláusula `FILTER`, que es más limpia:
> `SELECT count(*) FILTER (WHERE price > 100) AS expensive_count FROM products;`
> En MySQL, debes usar una sentencia `CASE` dentro de la agregación:
> `SELECT SUM(CASE WHEN price > 100 THEN 1 ELSE 0 END) AS expensive_count FROM products;`

---

## 5. Resumen y mejores prácticas

1. **Mantén el SELECT limpio**: Asegúrate de que cada columna no agregada en tu lista de `SELECT` esté también en tu cláusula `GROUP BY` para mantener la compatibilidad entre motores.
2. **`WHERE` vs `HAVING`**: Utiliza `WHERE` para filtrar filas crudas antes de la agregación, y `HAVING` para filtrar grupos agregados.
3. **Maneja los NULL en tus métricas**: Recuerda que los cálculos de agregación (excepto `COUNT(*)`) ignoran `NULL`. Envuelve las columnas en `COALESCE` para darles un valor predeterminado de 0 si es necesario.
4. **Aprende las funciones de cada dialecto**: Utiliza `GROUP_CONCAT` en MySQL y `string_agg` en PostgreSQL al concatenar cadenas entre filas agrupadas.
