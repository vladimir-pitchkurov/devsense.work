# Ordenación, límites y valores NULL

Recuperar datos en un orden aleatorio rara vez es útil. En aplicaciones del mundo real, necesitas presentar registros ordenados alfabética, numérica o cronológicamente. Además, las bases de datos a menudo contienen conjuntos de datos masivos, por lo que es necesario limitar el volumen de filas recuperadas (por ejemplo, para la paginación). Por último, debes comprender cómo maneja SQL los datos faltantes o desconocidos representados por `NULL`.

En este capítulo, exploraremos cómo organizar, restringir y consultar datos de forma segura utilizando comparaciones con `ORDER BY`, `LIMIT` y `NULL`, junto con las distintas formas en que MySQL y PostgreSQL manejan estas características.

---

## 1. Ordenación de resultados con `ORDER BY`

Por defecto, los motores de bases de datos relacionales devuelven las filas en un orden no especificado (a menudo basado en cómo están almacenadas físicamente en el disco). Para garantizar un orden específico, debes utilizar la cláusula `ORDER BY`.

### Orden ascendente y descendente
* **`ASC` (Ascending)**: Ordena los valores de menor a mayor (comportamiento por defecto).
* **`DESC` (Descending)**: Ordena los valores de mayor a menor.

```sql
-- MySQL & PostgreSQL
-- Sort products by price, highest first
SELECT name, price FROM products ORDER BY price DESC;
```

### Ordenación por múltiples columnas
Puedes ordenar por múltiples columnas. El motor ordena primero por la primera columna y, si hay valores duplicados, ordena esos duplicados por la segunda columna, y así sucesivamente.
```sql
-- MySQL & PostgreSQL
-- Sort by category alphabetically, and then by price descending within each category
SELECT category, name, price 
FROM products 
ORDER BY category ASC, price DESC;
```

---

## 2. Restringir el resultado: `LIMIT` y `OFFSET`

Recuperar millones de filas puede colapsar el servidor de tu aplicación y bloquear recursos de la base de datos. Para evitar esto, puedes restringir el conjunto de resultados.

* **`LIMIT`**: Especifica el número máximo de filas a devolver.
* **`OFFSET`**: Omite un número específico de filas antes de devolver los resultados (comúnmente utilizado para la paginación).

```sql
-- MySQL & PostgreSQL
-- Get the second page of products (items 11-20)
SELECT name, price 
FROM products 
ORDER BY price DESC 
LIMIT 10 OFFSET 10;
```

---

## 3. El misterio de los valores `NULL`

En SQL, `NULL` representa la falta de datos, un valor desconocido o un atributo ausente. **No** es equivalente a una cadena vacía `''` o al número `0`.

### La trampa de la lógica de tres valores
En los lenguajes de programación estándar, `true` y `false` son los únicos estados booleanos. SQL, sin embargo, utiliza **Lógica de tres valores**: `true`, `false` y `unknown` (desconocido, representado por `NULL`).

Debido a que `NULL` significa "desconocido", no puedes compararlo usando operadores estándar como `=` o `!=`. Por ejemplo:
* ¿Es un valor desconocido igual a 5? **Desconocido (`NULL`)**.
* ¿Es un valor desconocido igual a otro valor desconocido? **Desconocido (`NULL`)**.

```sql
-- THIS WILL NOT WORK! It returns zero rows.
SELECT * FROM users WHERE middle_name = NULL;
```

### Comparaciones correctas de NULL
Para verificar si una columna está vacía o tiene datos, debes utilizar `IS NULL` o `IS NOT NULL`.
```sql
-- MySQL & PostgreSQL
-- Correct way to find users without a middle name
SELECT email FROM users WHERE middle_name IS NULL;

-- Correct way to find users with a middle name
SELECT email FROM users WHERE middle_name IS NOT NULL;
```

---

## 4. Diferencias clave: MySQL vs. PostgreSQL

### Ordenación de nulos (`NULLS FIRST` vs. `NULLS LAST`)
Al ordenar una columna que contiene valores `NULL`, ¿cómo los posiciona el motor?
* **MySQL**: Trata a `NULL` como el valor más bajo posible. En orden ascendente (`ASC`), los `NULL` aparecen primero. En orden descendente (`DESC`), los `NULL` aparecen al final.
* **PostgreSQL**: Trata a `NULL` como el valor más alto posible. En orden ascendente (`ASC`), los `NULL` aparecen al final. En orden descendente (`DESC`), los `NULL` aparecen al principio.

Sin embargo, PostgreSQL admite la anulación estándar de SQL: `NULLS FIRST` o `NULLS LAST`. MySQL no admite esto de forma nativa.

| Base de datos | Orden | Posición de NULL por defecto | Anulación personalizada |
| :--- | :--- | :--- | :--- |
| **MySQL** | `ASC` | Primero | Ninguna (Requiere solución alternativa) |
| **MySQL** | `DESC` | Último | Ninguna (Requiere solución alternativa) |
| **PostgreSQL** | `ASC` | Último | `ORDER BY price ASC NULLS FIRST` |
| **PostgreSQL** | `DESC` | Primero | `ORDER BY price DESC NULLS LAST` |

#### Solución alternativa en MySQL para la ordenación de nulos
Para forzar que los `NULL` queden al final de una ordenación ascendente en MySQL, puedes utilizar una comprobación con una expresión booleana:
```sql
-- MySQL: NULLs sorted last in ascending order
SELECT name, price FROM products ORDER BY price IS NULL ASC, price ASC;
```

### Sintaxis de `LIMIT` no estándar
* **MySQL** admite una sintaxis abreviada separada por comas: `LIMIT offset, row_count`.
* **PostgreSQL** no admite esta sintaxis y lanzará un error de sintaxis.

```sql
-- MySQL Only (Shorthand syntax: limit 10 rows, skipping the first 5)
SELECT name FROM products LIMIT 5, 10;

-- PostgreSQL & MySQL Standard (Recommended)
SELECT name FROM products LIMIT 10 OFFSET 5;
```

> [!WARNING]
> **¡La trampa de rendimiento del Offset!**
> Usar un `OFFSET` alto (por ejemplo, `LIMIT 10 OFFSET 500000`) obliga al motor de la base de datos a escanear y descartar 500,000 filas antes de devolver las 10 filas que solicitaste. Esto provoca una degradación grave del rendimiento en tablas grandes. Para paginaciones profundas, prefiere la paginación basada en cursores (paginación keyset) utilizando `WHERE id > last_seen_id LIMIT 10`.

> [!TIP]
> **¿Sabías que...?**
> El estándar SQL define `FETCH FIRST n ROWS ONLY` en lugar de `LIMIT`. Aunque tanto MySQL como PostgreSQL admiten `LIMIT`, PostgreSQL también admite el estándar oficial:
> `SELECT name FROM products ORDER BY price DESC FETCH FIRST 10 ROWS ONLY;`

---

## 5. Resumen y mejores prácticas

1. **Utiliza siempre `ORDER BY` con `LIMIT`**: Sin ordenación, `LIMIT` devolverá un conjunto aleatorio de filas según el estado de la base de datos.
2. **Nunca uses `=` con `NULL`**: Utiliza siempre `IS NULL` or `IS NOT NULL`.
3. **Usa `LIMIT/OFFSET` estándar**: Evita la abreviatura de `LIMIT` separada por comas de MySQL para mantener la portabilidad de las consultas.
4. **Cuidado con la ordenación de nulos**: Ten en cuenta que MySQL coloca los `NULL` primero en `ASC`, mientras que PostgreSQL los coloca al final. Utiliza los modificadores `NULLS FIRST/LAST` de Postgres o los trucos de ordenación `IS NULL` de MySQL cuando requieras un comportamiento exacto.
