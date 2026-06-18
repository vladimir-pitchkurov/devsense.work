# Execution Plans and Scan Types

Para optimizar consultas lentas en bases de datos, debes comprender cómo recupera los datos la base de datos. El optimizador de consultas evalúa múltiples rutas de ejecución y construye un **Plan de ejecución** basado en estimaciones de costos. Al analizar los planes de ejecución mediante `EXPLAIN`, puedes identificar cuellos de botella, como escaneos completos de tablas (full table scans), ordenaciones incorrectas de unión (joins) o índices que son ignorados. PostgreSQL y MySQL utilizan terminologías y técnicas de visualización de planes diferentes, pero sus métodos de escaneo subyacentes comparten conceptos clave.

---

## 1. Generación y lectura de planes de ejecución

Ambas bases de datos proporcionan herramientas para inspeccionar cómo se ejecutan las consultas.

### PostgreSQL: EXPLAIN y EXPLAIN ANALYZE
En Postgres, `EXPLAIN` devuelve el costo estimado por el planificador. Al añadir `ANALYZE`, se obliga a la base de datos a ejecutar la consulta, lo que proporciona tiempos de ejecución reales y recuentos de filas reales.
* **Métricas de costo**: Representadas como `cost=inicio..total` (por ejemplo, `cost=0.00..45.10`). El costo es una unidad relativa (donde `1.0` es el costo de leer una sola página secuencialmente).
* **Tiempos de ejecución reales**: Se muestran en milisegundos.

```sql
-- PostgreSQL: Inspect actual execution stats
EXPLAIN (ANALYZE, BUFFERS, COSTS)
SELECT email FROM users WHERE status = 'active';
```
*La opción `BUFFERS` muestra las lecturas de bloques en la memoria compartida (aciertos en caché y lecturas desde disco).*

### MySQL: EXPLAIN y EXPLAIN ANALYZE
Históricamente, MySQL utilizaba un formato tabular para `EXPLAIN`. MySQL 8.0 introdujo `EXPLAIN ANALYZE`, el cual muestra los planes en formato de árbol con los costos y tiempos reales de ejecución.

```sql
-- MySQL: Visualizing with Tree structure & actual runtimes
EXPLAIN ANALYZE
SELECT email FROM users WHERE status = 'active';
```

---

## 2. Tipos de escaneo en PostgreSQL

PostgreSQL elige entre varios métodos de escaneo basándose en la presencia de índices, el tamaño de la tabla y la selectividad de los datos.

```
Flujo de selección de escaneo de PostgreSQL:
Selectividad: Alta (1-2 filas)     Media (5-15%)         Baja (Tabla completa)
Método:       [Index Scan]   ->  [Bitmap Scan]   ->  [Seq Scan]
```

### Sequential Scan (Escaneo secuencial - Seq Scan)
* **Qué hace**: Lee el archivo heap completo de la tabla de principio a fin, evaluando la cláusula `WHERE` para cada fila.
* **Cuándo ocurre**: Se utiliza cuando la consulta no tiene un índice coincidente o cuando el planificador estima que recuperar la mayor parte de la tabla es más rápido que utilizar un índice.

### Index Scan (Escaneo de índice)
* **Qué hace**: Escanea el índice B-Tree para encontrar las ubicaciones físicas (TIDs) de las filas coincidentes y luego recupera esos bloques específicos desde el heap de la tabla.
* **Desventaja**: Si coinciden muchas filas, saltar de un lado a otro entre las páginas de índice y las páginas heap causa cuellos de botella de E/S aleatoria.

### Bitmap Index Scan y Bitmap Heap Scan
* **Qué hace**: Se utiliza cuando Postgres recupera una cantidad moderada de filas.
  1. **Bitmap Index Scan** lee el índice y construye un mapa de bits de las páginas heap coincidentes en memoria, ordenando los TIDs por orden físico de página.
  2. **Bitmap Heap Scan** lee las páginas ordenadas secuencialmente, evitando la E/S aleatoria y la doble lectura de páginas.

### Index Only Scan (Escaneo solo de índice)
* **Qué hace**: Recupera datos directamente de los nodos hoja del índice sin visitar el heap de la tabla.
* **Advertencia del Mapa de Visibilidad (Visibility Map)**: Postgres debe comprobar el **Visibility Map** para asegurarse de que las páginas no hayan sido modificadas por transacciones no limpiadas por vacuum. Si una página se marca como "sucia", Postgres debe visitar el heap de todos modos, lo que degrada el rendimiento.

---

## 3. Tipos de escaneo en MySQL (InnoDB)

La salida de `EXPLAIN` en MySQL utiliza la columna `type` para describir cómo se recuperan las filas. Los tipos, ordenados de más rápido a más lento, incluyen:

### const / system
* La tabla tiene como máximo una fila coincendente (por ejemplo, al consultar una `PRIMARY KEY` o un índice `UNIQUE` con un valor constante). Esto es extremadamente rápido.

### eq_ref
* Se utiliza en las uniones (joins) cuando MySQL lee una fila de esta tabla por cada combinación de filas de la tabla precedente (ocurre con claves primarias o únicas).

### ref
* Se utiliza al buscar coincidencias de filas con un índice no único. Pueden coincidir múltiples filas.

### range
* Utiliza un índice para seleccionar un rango de filas (por ejemplo, consultas que utilizan `>`, `<`, `BETWEEN` o `IN`).

### index (Escaneo completo de índice)
* MySQL realiza un escaneo completo del árbol de índices. Esto es equivalente al Index Only Scan de PostgreSQL. Evita escanear el espacio de la tabla real, pero sigue leyendo todo el índice.

### ALL (Escaneo completo de tabla)
* MySQL lee cada fila de la tabla desde el disco. Este es el tipo de acceso más lento y debe evitarse en tablas grandes.

---

## 4. Matriz comparativa de tipos de escaneo

| Concepto de escaneo | Nombre en PostgreSQL | Tipo en MySQL (InnoDB) | Descripción |
| :--- | :--- | :--- | :--- |
| **Escaneo completo de tabla** | `Seq Scan` | `ALL` | Escanea la tabla completa; alta E/S de disco. |
| **Búsqueda de índice** | `Index Scan` | `ref` o `range` | Recorre el índice y recupera la fila desde el espacio de tablas. |
| **Búsqueda solo de índice** | `Index Only Scan` | `index` | Recupera datos estrictamente de los nodos hoja del índice. |
| **Búsqueda de índice masiva** | `Bitmap Index/Heap Scan` | N/A | Agrupa TIDs por página para optimizar el acceso al disco. |
| **Búsqueda constante** | `Index Scan` (1 fila) | `const` | Búsqueda instantánea en un índice único. |

---

## 5. Resumen y mejores prácticas

1. **Utiliza siempre ANALYZE para datos reales**: El comando `EXPLAIN` estándar solo muestra estimaciones. Ejecuta siempre `EXPLAIN ANALYZE` (en entornos seguros) para ver recuentos de filas y uso de memoria reales.
2. **Cuidado con la degradación del "Index Only Scan"**: Si un Index Only Scan en Postgres muestra un número elevado de lecturas en el heap (heap fetches), ejecuta `VACUUM` en la tabla para actualizar el Visibility Map.
3. **Evita el tipo `ALL` en MySQL**: Si una consulta en una tabla grande muestra `type: ALL` o `Extra: Using join buffer`, añade un índice que cubra las columnas de búsqueda.
4. **Actualiza las estadísticas de las tablas**: Si el optimizador elige un tipo de escaneo adecuado, las estadísticas de la tabla podrían estar desactualizadas. Ejecuta `ANALYZE TABLE mi_tabla;` en MySQL o `ANALYZE mi_tabla;` en PostgreSQL.
