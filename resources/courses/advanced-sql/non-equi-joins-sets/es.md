# Non-Equi Joins and Set Operations

La mayoría de los tutoriales de SQL se centran en gran medida en la unión de tablas utilizando el operador de igualdad (`ON a.id = b.id`). Sin embargo, los problemas de bases de datos del mundo real a menudo requieren hacer coincidir registros basados en rangos, intervalos, desigualdades o fusionar conjuntos de datos utilizando la teoría matemática de conjuntos.

Comprender las **uniones no equitativas (non-equi joins)** y las **operaciones de conjuntos avanzadas** es lo que separa a los escritores de SQL principiantes (juniors) de los ingenieros de bases de datos experimentados (seniors).

---

## 1. Uniones no equitativas (Non-Equi Joins): más allá de la igualdad

Una **unión no equitativa (non-equi join)** es una condición de unión que utiliza operadores distintos al signo de igualdad (`=`), como `<`, `>`, `<=`, `>=`, `BETWEEN` o `!=`.

### Caso de uso: Intervalos de fechas superpuestos
Imagina que estás gestionando las reservas de habitaciones de un hotel. Necesitas comprobar si una nueva solicitud de reserva se superpone con alguna reserva existente.
* **Lógica**: Ocurre una colisión si la fecha de inicio solicitada es anterior a la fecha de finalización de una reserva existente, Y la fecha de finalización solicitada es posterior a la fecha de inicio de la reserva existente.

```sql
-- MySQL & PostgreSQL
SELECT 
    b1.room_id,
    b1.booking_id AS booking_1,
    b2.booking_id AS booking_2
FROM bookings b1
JOIN bookings b2 ON b1.room_id = b2.room_id
    AND b1.booking_id < b2.booking_id -- Avoid self-matching and duplicate pairs
    AND b1.start_date < b2.end_date 
    AND b1.end_date > b2.start_date;
```

### Caso de uso: Agrupación por rangos (por ejemplo, niveles de precios)
Puedes asignar productos a rangos de precios sin escribir a mano los niveles ni utilizar subconsultas complejas.

```sql
-- MySQL & PostgreSQL
SELECT 
    p.product_name, 
    p.price, 
    t.tier_name
FROM products p
JOIN price_tiers t ON p.price BETWEEN t.min_price AND t.max_price;
```

> [!WARNING]
> **¡Problema de rendimiento con uniones no equitativas!**
> Aunque los planificadores de consultas modernos utilizan **Hash Joins** (uniones hash) eficientes para las uniones equitativas (equi-joins), no pueden utilizarlas para condiciones no equitativas. En su lugar, recurren a **Nested Loop Joins** (uniones de bucle anidado) o **Block Nested Loops** (bucles anidados por bloques). Esto puede resultar en una complejidad temporal de `O(N * M)`.
> En PostgreSQL, puedes mitigar esto utilizando **Tipos de rango (Range Types)** e **Índices GiST**. MySQL carece de tipos de rango nativos, lo que significa que debes optimizar cuidadosamente los índices B-Tree compuestos.

---

## 2. Operaciones avanzadas de conjuntos

Las operaciones de conjuntos combinan los resultados de dos o más consultas en un único conjunto de resultados.

```
Operaciones de conjuntos:
[Consulta 1] UNION [Consulta 2]      --> Devuelve todas las filas únicas de ambas consultas.
[Consulta 1] INTERSECT [Consulta 2]  --> Devuelve las filas presentes en AMBAS consultas.
[Consulta 1] EXCEPT [Consulta 2]     --> Devuelve las filas de la Consulta 1 pero NO de la Consulta 2.
```

### `UNION` vs. `UNION ALL`
* **`UNION`**: Fusiona los conjuntos de resultados y elimina los duplicados. Para hacer esto, la base de datos debe ordenar los datos o construir una tabla hash temporal, lo que incurre en un costo de rendimiento.
* **`UNION ALL`**: Fusiona los conjuntos de resultados pero conserva todos los duplicados. No realiza ninguna ordenación ni deduplicación, lo que la hace mucho más rápida.

### `INTERSECT` y `EXCEPT` (Con y sin `ALL`)
El estándar SQL define dos modos para las operaciones de conjuntos:
1. **Predeterminado (Distinct)**: Elimina las filas duplicadas antes de devolver los resultados.
2. **`ALL`**: Conserva la cardinalidad de los duplicados. Por ejemplo, si una fila aparece 3 veces en la Consulta 1 y 2 veces en la Consulta 2:
   - `INTERSECT ALL` la devuelve `MIN(3, 2) = 2` veces.
   - `EXCEPT ALL` la devuelve `3 - 2 = 1` vez.

---

## 3. MySQL vs. PostgreSQL: compatibilidad y sintaxis

Esta es una de las áreas en las que ambos motores de bases de datos difieren significativamente, en particular con respecto a versiones anteriores.

### Tabla de soporte para operaciones de conjuntos

| Operador | PostgreSQL (Todas las versiones) | MySQL 8.0.31+ | MySQL < 8.0.31 |
| :--- | :--- | :--- | :--- |
| `UNION` / `UNION ALL` | Soportado de forma nativa | Soportado de forma nativa | Soportado de forma nativa |
| `INTERSECT` (Distinct) | Soportado de forma nativa | Soportado de forma nativa | *No soportado* |
| `EXCEPT` (Distinct) | Soportado de forma nativa | Soportado de forma nativa | *No soportado* |
| `INTERSECT ALL` | Soportado de forma nativa | *No soportado* | *No soportado* |
| `EXCEPT ALL` | Soportado de forma nativa | *No soportado* | *No soportado* |

### Soluciones alternativas para MySQL (Simulación)

Si estás trabajando con versiones de MySQL anteriores a la 8.0.31, debes simular `INTERSECT` y `EXCEPT` utilizando uniones o subconsultas.

#### Simulación de `INTERSECT`:
```sql
-- MySQL < 8.0.31 Equivalent of INTERSECT
SELECT DISTINCT a.email 
FROM users_a a
INNER JOIN users_b b ON a.email = b.email;
```

#### Simulación de `EXCEPT`:
```sql
-- MySQL < 8.0.31 Equivalent of EXCEPT
SELECT DISTINCT a.email 
FROM users_a a
LEFT JOIN users_b b ON a.email = b.email
WHERE b.email IS NULL;
```

> [!TIP]
> **Exclusivo de PostgreSQL: Restricciones de exclusión (Exclusion Constraints)**
> En PostgreSQL, puedes garantizar que no haya dos filas en una tabla que se superpongan utilizando una `EXCLUSION CONSTRAINT` con un índice GiST.
> ```sql
> -- PostgreSQL Only: Prevent overlapping bookings at the database schema level
> ALTER TABLE bookings ADD CONSTRAINT no_overlap 
> EXCLUDE USING gist (room_id WITH =, tsrange(start_date, end_date) WITH &&);
> ```
> *En MySQL, la aplicación de esta regla requiere disparadores (triggers) personalizados BEFORE INSERT/UPDATE.*

---

## 4. Resumen y mejores prácticas

1. **Prefiere `UNION ALL` sobre `UNION`**: A menos que necesites explícitamente filtrar duplicados, utiliza siempre `UNION ALL` para evitar la sobrecarga de ordenación de la base de datos.
2. **Atención a las uniones**: Al escribir uniones no equitativas, verifica el plan de ejecución de la consulta (`EXPLAIN`) para asegurarte de que la base de datos no esté ejecutando un bucle anidado lento en millones de filas.
3. **Maneja la compatibilidad**: Si estás escribiendo consultas para aplicaciones compatibles con múltiples bases de datos, evita `INTERSECT`/`EXCEPT` nativos o sintaxis complejas de simulación. En su lugar, utiliza estructuras `EXISTS` y `LEFT JOIN` estructuras.
