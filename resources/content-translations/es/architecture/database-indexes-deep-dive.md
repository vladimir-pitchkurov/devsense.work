---
title: "Índices de bases de datos: bajo el capó y análisis profundo | DevSense"
description: "Una guía completa para desarrolladores sobre árboles B+ en InnoDB, estructuras Heap en Postgres, punteros a nodos de índices, reglas de índices compuestos y amplificación de escritura."
published: 2026-05-31
faq:
  - question: "¿Por qué InnoDB utiliza un índice agrupado (clustered index) mientras que Postgres utiliza una estructura Heap?"
    answer: "InnoDB almacena los datos de las filas directamente dentro de los nodos hoja del árbol B+ de la clave primaria para que las búsquedas por clave primaria sean extremadamente rápidas y los datos relacionados se mantengan contiguos. Postgres almacena todos los datos en un archivo Heap y hace que todos los índices (incluida la clave primaria) sean índices secundarios que apuntan a ubicaciones del Heap mediante TIDs. Esto simplifica los movimientos de filas durante las actualizaciones y evita búsquedas dobles en índices secundarios, pero requiere accesos al Heap para consultas por clave primaria."
  - question: "¿Qué es un índice GIN y cuándo debería usarlo en Postgres?"
    answer: "Un índice invertido generalizado (GIN) está diseñado para indexar valores compuestos como JSONB o arrays. Mapea elementos individuales (claves o valores) a sus TIDs correspondientes, permitiendo una coincidencia altamente eficiente en consultas que contienen operadores como `@>` (contiene) o `?` (tiene clave)."
  - question: "¿Cómo se produce la amplificación de escritura durante el mantenimiento de índices?"
    answer: "La amplificación de escritura ocurre porque cada INSERT, UPDATE o DELETE requiere que la base de datos actualice tanto los datos de la tabla como todos los índices asociados. Además, si una inserción cae en una página de árbol B llena, la página debe dividirse (page split), escribiendo múltiples páginas en el disco y causando fragmentación."
  - question: "¿Cómo puedo evitar búsquedas dobles en índices secundarios bajo InnoDB?"
    answer: "Para evitar búsquedas dobles (un escaneo de índice secundario seguido de una búsqueda en el índice agrupado de la clave primaria), puedes diseñar un índice de cobertura (covering index) que contenga todas las columnas solicitadas por la consulta SELECT, permitiendo que la base de datos resuelva la consulta completamente desde el índice secundario."
---

# Índices de bases de datos: bajo el capó y análisis profundo

Cada consulta de base de datos que escribes es una carrera contra la E/S de disco. Cuando tu conjunto de datos cabe en la RAM, las consultas son instantáneas. Pero a medida que tus datos crecen a millones de filas y se desbordan en el disco, tu motor de base de datos debe buscar a través de cada página en la unidad (un escaneo secuencial) o usar un mapa para saltar directamente a los datos que necesita. Ese mapa es un índice.

Comprender cómo funcionan los índices a nivel de disco y de página diferencia a los desarrolladores junior que agregan índices a las tablas al azar, de los arquitectos de bases de datos que diseñan motores de almacenamiento auto-optimizados y de alto rendimiento. En este análisis profundo, levantaremos la capa de abstracción de MySQL (InnoDB) y PostgreSQL para ver cómo representan los índices en el disco, cómo se resuelve el orden de las columnas compuestas y el costo real de la amplificación de escritura.

---

## 1. Bajo el capó: Árbol B+ en InnoDB frente a Heap y Árbol B en Postgres

Los motores de bases de datos no almacenan las tablas como simples arrays de filas. Las estructuran en el disco utilizando páginas (típicamente 16KB en InnoDB, 8KB en PostgreSQL). Sin embargo, sus arquitecturas de almacenamiento son fundamentalement diferentes.

### InnoDB: El índice agrupado (Árbol B+)
En el motor InnoDB de MySQL, **la tabla es el índice**. Más específicamente, la tabla está estructurada como un árbol B+ construido alrededor de la clave primaria (Primary Key). Esto se conoce como un **índice agrupado** (Clustered Index).

*   **Nodos internos (Internal Nodes):** Contienen únicamente claves y punteros a páginas hijas. Guían al motor durante las búsquedas.
*   **Nodos hoja (Leaf Nodes):** Contienen las filas de datos reales. Las páginas hoja están vinculadas secuencialmente en una lista doblemente enlazada, lo que hace que los escaneos de rango (`WHERE id BETWEEN 10 AND 50`) sean increíblemente rápidos.
*   **Índices secundarios (Secondary Indexes):** Cualquier índice que no sea la clave primaria en InnoDB es un índice secundario. Los nodos hoja de un índice secundario *no* apuntan a direcciones físicas de disco. En su lugar, almacenan el **valor de la clave primaria** de la fila.

> [!NOTE]
> Debido a que los índices secundarios de InnoDB almacenan la clave primaria, buscar una fila a través de un índice secundario (por ejemplo, `WHERE email = 'user@example.com'`) requiere una búsqueda en dos pasos: primero, recorrer el índice secundario para encontrar la clave primaria, y segundo, recorrer el árbol B+ del índice agrupado para recuperar los datos de la fila.

```
[Secondary Index (Email)]
  Leaf Node: 'user@example.com' -> PK: 42
          |
          v
[Clustered Index (ID)]
  Leaf Node: PK: 42 -> Row Data: {id: 42, email: 'user@example.com', name: 'John Doe'}
```

### PostgreSQL: El Heap y el Árbol B
PostgreSQL no utiliza índices agrupados por defecto. En su lugar, almacena los datos de las filas en una estructura no ordenada llamada **Heap** (montículo).

*   **Páginas del Heap:** Las filas de la tabla se añaden a las páginas en el orden en que se insertan (o donde haya espacio disponible).
*   **Índices B-Tree:** Cada índice en Postgres (incluida la clave primaria) es un índice secundario.
*   **Nodos hoja:** Los nodos hoja de un índice B-Tree de Postgres contienen un **Identificador de Tupla (TID)**, que es una dirección física en el disco compuesta por un número de bloque (página) y un índice de desplazamiento (offset) dentro de esa página (por ejemplo, `(Page 14, Offset 3)`).

> [!WARNING]
> Dado que los índices de Postgres almacenan TIDs físicos, cualquier operación que mueva una fila en el disco (como un UPDATE que cambie el tamaño de la fila o una migración de página MVCC) rompería estos TIDs. Postgres gestiona esto mediante vacuuming y la optimización HOT (Heap Only Tuples), pero significa que todos los índices apuntan directamente al Heap.

```
[Postgres Index (Email)]                         [Postgres Heap (Unordered)]
  Leaf: 'user@example.com' -> TID: (Page 14, Offset 3) ----> Page 14, Slot 3: {id: 42, ...}
```

---

## 2. Reglas de orden de columnas en índices compuestos

Al crear un índice sobre múltiples columnas —un **índice compuesto**— el orden de las columnas en la declaración del índice es crítico. Un orden de columnas incorrecto hará que el índice sea completamente inútil para ciertas consultas.

Las reglas de oro del diseño de índices compuestos son:
1.  **La regla del prefijo más a la izquierda (Leftmost Prefix Rule):** La base de datos solo puede usar un índice compuesto si la consulta filtra primero por la columna más a la izquierda del índice. Un índice sobre `(A, B, C)` puede optimizar consultas que filtran sobre `(A)`, `(A, B)` y `(A, B, C)`, pero *no puede* optimizar consultas que filtran sobre `(B)` o `(C)` únicamente.
2.  **Igualdad primero, rango al final (Equality First, Range Last):** Las columnas filtradas con igualdad exacta (`=`, `IN`) deben ir primero en el índice. Las columnas filtradas con rangos (`<`, `>`, `BETWEEN`, `LIKE`) deben ir al final. Una vez que se evalúa una columna de rango, la base de datos no puede usar las columnas siguientes del índice para filtrar.
3.  **Alta cardinalidad primero:** Las columnas con alta cardinalidad (muchos valores únicos, por ejemplo, `user_id`) generalmente deben preceder a las columnas con baja cardinalidad (pocos valores únicos, por ejemplo, `status`), siempre que ambas se consulten con igualdad.

Veamos una migración y consulta concretas:

```sql
-- database/migrations/2026_05_31_create_orders_table.sql
CREATE TABLE orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    status VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP NOT NULL
);

-- database/migrations/2026_05_31_add_composite_index.sql
-- Diseño óptimo de índice para: user_id (igualdad) + status (igualdad) + created_at (rango/ordenación)
CREATE INDEX idx_orders_user_status_created ON orders (user_id, status, created_at);
```

Para la consulta a continuación, el índice permite al motor filtrar inmediatamente por `user_id`, luego por `status` y luego leer los valores preordenados de `created_at` en orden inverso sin necesidad de una operación filesort separada.

```sql
-- database/queries/find_user_orders.sql
SELECT id, user_id, status, created_at, amount
FROM orders
WHERE user_id = 42
  AND status = 'completed'
ORDER BY created_at DESC
LIMIT 10;
```

---

## 3. Análisis profundo de los tipos de índices

Diferentes patrones de consulta requieren diferentes estructuras de datos. A continuación, se presenta una comparación de los tipos de índices disponibles en MySQL y PostgreSQL.

### B-Tree
El caballo de batalla de los motores de bases de datos. Soporta igualdad (`=`), rangos (`>`, `<`, `BETWEEN`) y ordenación (`ORDER BY`). Los árboles B se mantienen balanceados, garantizando que las operaciones de búsqueda, inserción y eliminación se ejecuten en un tiempo de $O(\log n)$.

### Hash
Los índices Hash almacenan un hash del valor indexado y apuntan a la fila correspondiente.
*   **Postgres:** Soporta índices Hash explícitos. Son extremadamente rápidos ($O(1)$) para búsquedas de igualdad, pero no soportan consultas de rango, ordenación o coincidencias parciales de múltiples columnas.
*   **MySQL (InnoDB):** No soporta la creación explícita de índices Hash. En su lugar, utiliza un **índice Hash adaptativo** (Adaptive Hash Index)—una funcionalidad interna donde InnoDB monitorea automáticamente los patrones de consulta en los árboles B y construye tablas hash en memoria para las búsquedas más frecuentes.

### GIN (Generalized Inverted Index) - Postgres
GIN está diseñado para indexar valores compuestos donde necesitas buscar elementos *dentro* del valor (como documentos JSONB o Arrays). GIN mapea elementos individuales dentro del documento a sus TIDs de fila físicos.

```sql
-- database/migrations/2026_05_31_postgres_features.sql
-- Crear una tabla con JSONB y agregar un índice GIN en PostgreSQL
CREATE TABLE user_profiles (
    id BIGINT PRIMARY KEY,
    metadata JSONB NOT NULL
);

CREATE INDEX idx_user_metadata_gin ON user_profiles USING GIN (metadata);

-- Esta consulta utiliza el índice GIN para coincidir instantáneamente con las claves correspondientes
SELECT * FROM user_profiles 
WHERE metadata @> '{"role": "administrator", "status": "active"}';
```

### Índices parciales / filtrados
Un índice parcial contiene solo un subconjunto de las filas de una tabla, definido por una condición `WHERE`. Esto ahorra una gran cantidad de espacio en disco y mantiene el índice pequeño y rápido.
*   **Postgres:** Soporta nativamente índices parciales.
*   **MySQL:** No soporta índices parciales directamente (hasta la versión 8.0). Debes usar índices funcionales con expresiones que evalúen a `NULL` para lograr un efecto similar, lo cual es menos elegante.

```sql
-- database/migrations/2026_05_31_partial_index.sql
-- Solo Postgres: Indexar solo pedidos activos no pagados para mantenerlo compacto
CREATE INDEX idx_orders_active_unpaid ON orders (user_id) 
WHERE status = 'pending' AND amount > 100.00;
```

### Índices de expresión / funcionales
Puedes indexar el resultado de una función o expresión en lugar del valor bruto de la columna. Esto es muy útil cuando las consultas realizan manipulaciones en las columnas dentro de la cláusula `WHERE`.

```sql
-- database/migrations/2026_05_31_functional_index.sql
-- Crear un índice de expresión para optimizar búsquedas en minúsculas
-- PostgreSQL:
CREATE INDEX idx_orders_lower_status ON orders (LOWER(status));

-- MySQL 8.0+:
CREATE INDEX idx_orders_lower_status ON orders ((LOWER(status)));
```

### Índices de cobertura (Covering Indexes)
Un índice de cobertura es un índice secundario que contiene todas las columnas requeridas por una consulta, lo que permite a la base de datos resolver la consulta por completo desde la página del índice sin tocar los datos de la tabla (Heap o Índice agrupado).
*   En Postgres, puedes agregar explícitamente columnas de datos que no son clave a un índice usando la cláusula `INCLUDE`.
*   En MySQL, simplemente agregas las columnas a las claves del índice compuesto.

```sql
-- database/migrations/2026_05_31_covering_index.sql
-- Postgres: El índice está ordenado por user_id, pero contiene las columnas amount/created_at
CREATE INDEX idx_orders_covering ON orders (user_id) INCLUDE (amount, created_at);
```

---

## 4. Amplificación de escritura y costo de mantenimiento

Los índices no son gratuitos. Cada índice que agregas a una tabla actúa como un freno en el rendimiento de escritura. Esta penalización se manifiesta como **amplificación de escritura** (Write Amplification).

### Divisiones de página (Page Splits) en árboles B+
Las páginas de la base de datos se asignan con espacio libre (fillfactor) para permitir futuras actualizaciones. Cuando insertas una fila, la base de datos debe escribirla en el nodo del índice B-Tree adecuado. Si la página del nodo del índice está llena, se produce una **división de página** (Page Split):
1.  Se asigna una nueva página.
2.  Aproximadamente el 50% de las claves de la página llena se mueven a la nueva página.
3.  La página padre se actualiza con un puntero a la nueva página.

Esta operación requiere múltiples escrituras en disco y causa fragmentación en el índice, lo que degrada el rendimiento de lectura secuencial.

### Vacuuming frente a Purge Lag
Cuando se actualizan o eliminan filas, el espacio ocupado por los registros antiguos no se puede reutilizar inmediatamente debido a MVCC (Multi-Version Concurrency Control).

*   **Postgres (Autovacuum):** Cuando ocurre un UPDATE, Postgres escribe una nueva tupla en el Heap y actualiza los índices para que apunten a ella. Esto deja una \"tupla muerta\" (dead tuple) en la página del Heap. Si el demonio autovacuum no puede mantener el ritmo de limpieza de estas tuplas muertas, la tabla y sus índices se inflan (bloat), lo que provoca una degradación masiva del rendimiento de la memoria y del disco.
*   **MySQL InnoDB (Purge Lag):** En InnoDB, las actualizaciones se realizan en el lugar (in-place) en el índice agrupado, y las versiones antiguas se escriben en el Undo Log. Sin embargo, los índices secundarios se modifican marcando el registro antiguo como \"eliminado\" e insertando el nuevo registro. Un hilo en segundo plano llamado **Purge Thread** se encarga de eliminar estos registros de índices secundarios marcados como eliminados. Si las escrituras son demasiado intensas, el retraso de purga (**Purge Lag**) crece, consumiendo espacio de almacenamiento y ralentizando las consultas.

---

## 5. Errores comunes

### Error 1: Orden de columnas incorrecto en índices compuestos
**Mala práctica:**
El desarrollador espera que el índice optimice las consultas en ambas columnas, pero coloca primero la columna de la consulta de rango.
```sql
-- database/queries/bad_composite_order.sql
-- Índice definido como: (created_at, user_id)
CREATE INDEX idx_bad_order ON orders (created_at, user_id);

-- Consulta:
SELECT * FROM orders 
WHERE created_at >= '2026-01-01 00:00:00' 
  AND user_id = 42;
```
*Por qué es malo:* El filtro de rango en `created_at` evita que la base de datos use la parte de `user_id` del índice para localizar las filas coincidentes. El motor debe escanear el índice para todos los registros posteriores a la fecha, verificando cada uno para el ID de usuario.

**Buena práctica:**
```sql
-- database/queries/good_composite_order.sql
-- Índice definido como: (user_id, created_at)
CREATE INDEX idx_good_order ON orders (user_id, created_at);

-- Consulta:
SELECT * FROM orders 
WHERE user_id = 42 
  AND created_at >= '2026-01-01 00:00:00';
```
*Por qué es bueno:* El filtro de igualdad exacta en `user_id` permite a la base de datos saltar instantáneamente a los registros del usuario, y luego usar los nodos hoja ordenados del índice `created_at` para escanear el rango.

### Error 2: Indexar columnas de baja cardinalidad
**Mala práctica:**
Agregar un índice en una columna booleana (por ejemplo, `is_active` o `has_paid`).
```sql
-- database/queries/bad_low_cardinality.sql
CREATE INDEX idx_orders_active ON orders (status); -- status solo tiene 'pending', 'completed'
```
*Por qué es malo:* Si los valores de estado están distribuidos uniformemente, el índice no ayuda. El optimizador de consultas evaluará el costo de recorrer el índice secundario y luego realizar búsquedas de E/S aleatorias en el índice agrupado/Heap, y decidirá que un escaneo secuencial de la tabla es más rápido. El índice permanece sin usar pero sigue degradando el rendimiento de escritura.

---

## 6. Cuestionario de autoevaluación

Prueba tu comprensión de las arquitecturas de índices:

### Pregunta 1
Supongamos que tienes un índice compuesto `idx_test (A, B, C)` en una tabla. ¿Cuál de las siguientes consultas **NO** podrá utilizar el índice?
1. `WHERE A = 1 AND B = 2`
2. `WHERE B = 2 AND C = 3`
3. `WHERE A = 1 AND C = 3`

<details>
<summary><b>Mostrar respuestas</b></summary>

**Respuesta: Consulta 2 (`WHERE B = 2 AND C = 3`)**

**Explicación:**
De acuerdo con la regla del prefijo más a la izquierda, la consulta debe filtrar por la primera columna del índice (`A`) para poder utilizarlo. Dado que la Consulta 2 no filtra por `A`, la base de datos no puede recorrer los nodos raíz del árbol B y debe realizar un escaneo completo de la tabla. La Consulta 3 *puede* usar el índice, pero solo la parte `A`; localizará los registros donde `A = 1` y luego los filtrará manualmente para `C = 3`.
</details>

---

### Pregunta 2
¿Por qué la actualización de una columna no indexada en PostgreSQL a veces desencadena la amplificación de escritura del índice, mientras que en MySQL InnoDB no ocurre?

<details>
<summary><b>Mostrar respuestas</b></summary>

**Respuesta:**
Debido a la diferencia entre las estructuras Heap y Agrupadas (Clustered).
*   En PostgreSQL, si un UPDATE no puede realizar una optimización HOT (Heap Only Tuples) (por ejemplo, si no hay espacio en la página), se escribe una nueva versión de la fila en una página diferente. Esto cambia la dirección física (TID) de la fila. En consecuencia, *todos* los índices de esa tabla deben actualizarse para apuntar al nuevo TID, causando la amplificación de escritura del índice.
*   En MySQL InnoDB, la fila permanece en el mismo nodo hoja del índice agrupado (a menos que se actualice la clave primaria en sí). Los índices secundarios apuntan al valor de la clave primaria, no a una dirección física del disco, lo que significa que sus nodos hoja no necesitan ser actualizados.
</details>

---

### Pregunta 3
Tienes una tabla con 10 millones de filas. Ejecutas la siguiente consulta:
`SELECT user_id, status FROM orders WHERE user_id = 100500;`
El índice en `user_id` está definido como: `CREATE INDEX idx_user ON orders (user_id);`
¿Cómo puedes optimizar esta consulta para evitar búsquedas en las páginas de datos de la tabla?

<details>
<summary><b>Mostrar respuestas</b></summary>

**Respuesta:**
Crea un **índice de cobertura** (covering index) que incluya `status`.
*   En PostgreSQL: `CREATE INDEX idx_user_status ON orders (user_id) INCLUDE (status);`
*   En MySQL (o PostgreSQL): `CREATE INDEX idx_user_status ON orders (user_id, status);`

**Explicación:**
Al agregar `status` al índice, todas las columnas consultadas en las cláusulas `SELECT` y `WHERE` se contienen por completo dentro de la página del índice. El motor de la base de datos puede realizar un **Index Only Scan**, evitando por completo la búsqueda en el Heap o en el índice agrupado.
</details>
