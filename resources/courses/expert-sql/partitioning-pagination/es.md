# Partitioning and Pagination

A medida que las tablas crecen a decenas o cientos de millones de filas, las consultas estándar y las estructuras de paginación simples comienzan a fallar. Realizar actualizaciones, copias de seguridad y búsquedas de índices en conjuntos de datos masivos introduce una alta latencia de disco. Para escalar, las bases de datos emplean dos estrategias principales: el **Particionado de tablas** (dividir una tabla masiva en piezas físicas más pequeñas bajo el capó) y la **Paginación eficiente** (recuperar registros paginados sin escanear millones de filas desplazadas/offset).

---

## 1. Particionado de tablas: reglas declarativas y poda (Pruning)

El particionado de tablas divide una única tabla lógica en múltiples tablas hijas físicas (particiones). La tabla padre actúa como una interfaz de enrutamiento.

### Tipos de particionado
1. **Particionado por rango (Range Partitioning)**: Mapea filas a las particiones basándose en un rango de valores (por ejemplo, particionar una tabla de registros por mes).
2. **Particionado por lista (List Partitioning)**: Mapea filas a las particiones basándose en valores de clave explícitos (por ejemplo, particionar por código de país).
3. **Particionado por hash (Hash Partitioning)**: Distribuye las filas a lo largo de un número fijo de particiones utilizando una función de módulo hash. Ideal para equilibrar las cargas de escritura.

### Particionado declarativo en PostgreSQL
Desde la versión 10, PostgreSQL admite el particionado declarativo. Las particiones se declaran utilizando la cláusula `PARTITION BY`.

```sql
-- PostgreSQL: Declarative Range Partitioning
CREATE TABLE app_logs (
    id BIGINT NOT NULL,
    log_date DATE NOT NULL,
    message TEXT
) PARTITION BY RANGE (log_date);

-- Create individual partitions
CREATE TABLE app_logs_y2026m01 PARTITION OF app_logs
    FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');
CREATE TABLE app_logs_y2026m02 PARTITION OF app_logs
    FOR VALUES FROM ('2026-02-01') TO ('2026-03-01');
```

### Sintaxis de particionado en MySQL
MySQL implementa el particionado directamente dentro de la definición de la tabla, sin necesidad de sentencias independientes para las tablas hijas.

```sql
-- MySQL: InnoDB Range Partitioning
CREATE TABLE app_logs (
    id BIGINT NOT NULL,
    log_date DATE NOT NULL,
    message TEXT,
    PRIMARY KEY (id, log_date)
) ENGINE=InnoDB
PARTITION BY RANGE COLUMNS(log_date) (
    PARTITION p2026m01 VALUES LESS THAN ('2026-02-01'),
    PARTITION p2026m02 VALUES LESS THAN ('2026-03-01')
);
```

### Poda de particiones (Partition Pruning)
El beneficio principal del particionado es la **Poda de particiones (Partition Pruning)**. El optimizador de consultas analiza el filtro de la cláusula `WHERE` y excluye las particiones que no pueden contener filas coincidentes, evitando escaneos completos de esos archivos físicos.

```sql
-- Triggering Partition Pruning
EXPLAIN SELECT * FROM app_logs WHERE log_date = '2026-02-15';
-- PostgreSQL plan will only scan 'app_logs_y2026m02'
-- MySQL plan will list partitions: 'p2026m02'
```

> [!WARNING]
> **Restricciones en la clave primaria**
> Tanto en MySQL como en PostgreSQL, cualquier restricción única o clave primaria en una tabla particionada **DEBE** incluir todas las columnas de la clave de partición. En MySQL, no puedes tener una `PRIMARY KEY (id)` independiente si particionas por `log_date`; debe definirse como `PRIMARY KEY (id, log_date)`. Esto evita que las bases de datos tengan que escanear todas las particiones para imponer la unicidad durante las inserciones.

---

## 2. Paginación: Offset frente a Keyset (Cursor)

Recuperar listas de datos en páginas es un requisito fundamental de las aplicaciones. Sin embargo, el enfoque SQL predeterminado puede provocar importantes cuellos de botella en el rendimiento.

### La trampa de la paginación con OFFSET
* **Sintaxis**: `SELECT * FROM orders ORDER BY created_at DESC LIMIT 10 OFFSET 500000;`
* **Bajo el capó**: El motor de la base de datos no puede saltar directamente a la fila 500,000. Debe escanear el índice, leer las 500,000 filas precedentes, descartarlas y devolver únicamente las 10 filas siguientes. Esto provoca un alto uso de CPU y E/S de disco.

### Paginación Keyset (basada en cursores)
* **Sintaxis**: En lugar de offsets, utiliza los últimos valores recuperados para filtrar las consultas posteriores.

```sql
-- MySQL & PostgreSQL: Keyset Pagination
SELECT * FROM orders 
WHERE created_at < '2026-06-17 10:00:00' 
ORDER BY created_at DESC 
LIMIT 10;
```
* **Rendimiento**: Con un índice compuesto en `(created_at, id)`, la consulta realiza una búsqueda de índice (index seek) directamente en el punto de inicio, ejecutándose en un tiempo $O(1)$ independientemente del número de página.

### Paginación Keyset multicolumna (Ordenar por campos no únicos)
Si el campo de ordenación (como `created_at` o `price`) puede contener valores duplicados, debes añadir una columna de desempate única (normalmente la clave primaria) para evitar omitir filas.
* **Sintaxis de comparación de tuplas (PostgreSQL)**:
  ```sql
  -- PostgreSQL supports row value comparisons natively
  SELECT * FROM orders
  WHERE (created_at, id) < ('2026-06-17 10:00:00', 9876)
  ORDER BY created_at DESC, id DESC
  LIMIT 10;
  ```
* **Expansión lógica explícita (MySQL)**:
  *MySQL 8.0 admite la comparación de valores de fila, pero las versiones anteriores o los optimizadores deficientes la ejecutan de manera ineficiente. Expándela explícitamente por seguridad:*
  ```sql
  -- MySQL Safe Keyset Expansion
  SELECT * FROM orders
  WHERE created_at < '2026-06-17 10:00:00'
     OR (created_at = '2026-06-17 10:00:00' AND id < 9876)
  ORDER BY created_at DESC, id DESC
  LIMIT 10;
  ```

> [!TIP]
> **Paginación multicategoría con LATERAL JOIN**
> Si necesitas paginar y recuperar los "top N items per category" (e.g., top 3 productos para cada una de las 20 categorías), el `GROUP BY` estándar no funcionará. Utiliza una unión `LATERAL` (admitida en PostgreSQL 10+ y MySQL 8.0.14+).
> ```sql
> SELECT c.name, p.title, p.price
> FROM categories c
> INNER JOIN LATERAL (
>     SELECT title, price 
>     FROM products 
>     WHERE category_id = c.id 
>     ORDER BY price DESC LIMIT 3
> ) p ON TRUE;
> ```
> *Esto ejecuta una subconsulta impulsada por índices para cada categoría, lo cual es extremadamente rápido en comparación con los escaneos completos de funciones de ventana.*

---

## 3. Matriz comparativa de características

| Característica | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Definición de particionado** | Tablas hijas declarativas | Definido directamente en la tabla padre |
| **Enrutamiento de filas** | Gestionado por el enrutamiento padre | Gestionado internamente por InnoDB |
| **Restricción de PK** | La PK debe contener la clave de partición | La PK debe contener la clave de partición |
| **Comparación de valores de fila** | Optimizado de forma nativa (ej. `(a, b) > (x, y)`) | Admitido pero pueden ocurrir trampas del optimizador |
| **Uniones laterales (Lateral Joins)** | Soportado (PostgreSQL 9.3+) | Soportado (MySQL 8.0.14+) |

---

## 4. Resumen y mejores prácticas

1. **Particiona por fecha para archivar**: El particionado por rango es perfecto para los registros de transacciones. Cuando los datos envejecen, puedes eliminar la partición utilizando `DROP TABLE nombre_particion` (instantáneo) en lugar de ejecutar un `DELETE` masivo (lento, genera obsolescencia en el undo log).
2. **Nunca utilices offsets grandes**: Implementa paginación keyset para desplazamientos infinitos (infinite scroll) o listas paginadas.
3. **Indexa tus claves de paginación**: Asegúrate siempre de que tus filtros de paginación keyset coincidan con un índice B-Tree compuesto (por ejemplo, un índice en `(created_at, id)`).
