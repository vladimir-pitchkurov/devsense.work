# Indexes: Deep Dive into B-Tree, GIN, BRIN, and Clustered Indexes

Los índices son la herramienta principal para acelerar el rendimiento de las consultas. Sin embargo, aplicar índices a ciegas puede degradar el rendimiento de las escrituras y consumir cantidades masivas de espacio en disco. Las bases de datos relacionales admiten múltiples tipos de índices, cada uno diseñado para distribuciones de datos, patrones de consulta y espacio de almacenamiento específicos. Comprender los aspectos internos de B-Tree, la agrupación (clustering) de claves primarias en MySQL y los tipos avanzados de índices en PostgreSQL es esencial para la optimización profesional de bases de datos.

---

## 1. Aspectos internos de B-Tree y comportamientos de agrupación (Clustering)

El **B-Tree** (árbol balanceado) es el tipo de índice predeterminado en casi todas las bases de datos relacionales. Mantiene los datos ordenados y permite la búsqueda, el acceso secuencial, las inserciones y las eliminaciones en tiempo logarítmico ($O(\log n)$).

### MySQL InnoDB: Índices agrupados (Clustered) frente a secundarios
En el motor InnoDB de MySQL, todas las tablas están organizadas físicamente alrededor de un **Índice agrupado (Clustered Index)**.
* **Índice agrupado (Clustered Index)**: Los nodos hoja del índice contienen los datos reales de la fila. Por defecto, esta es la `PRIMARY KEY` de la tabla. Si no se define ninguna clave primaria, InnoDB selecciona el primer índice `UNIQUE` que contenga solo columnas no nulas. Si no existe ninguno, InnoDB genera un ID de fila oculto de 6 bytes.
* **Índice secundario**: Los nodos hoja de cualquier índice secundario *no* contienen punteros de datos. En su lugar, almacenan el **valor de la clave primaria (Primary Key)** de la fila.
* **Búsqueda por marcador (Bookmark Lookup)**: Al consultar utilizando un índice secundario, MySQL primero busca en el índice secundario para encontrar la clave primaria y luego realiza una segunda búsqueda en el índice agrupado para recuperar la fila.

```
Búsqueda de índice en MySQL InnoDB:
[Búsqueda en índice secundario] ---> Devuelve el valor de la PK (por ejemplo, ID: 42)
                                          |
                                          v
[Búsqueda en índice agrupado]   ---> Devuelve los datos de la fila (Nombre, Correo, etc.)
```

### PostgreSQL: Indexación basada en Heap
A diferencia de MySQL, PostgreSQL no utiliza tablas agrupadas por defecto. Las tablas se organizan como un "heap" de páginas.
* Todos los índices (incluido el índice de la clave primaria) son **Índices secundarios**.
* Los nodos hoja de un índice de PostgreSQL apuntan directamente a la dirección física (TID - Tuple ID, compuesto por el número de página y el desplazamiento/offset) de la fila en el heap de la tabla.
* **Sin búsqueda por marcador (Bookmark Lookup)**: Postgres va directamente desde el nodo hoja del índice a la página heap. Sin embargo, actualizar una fila en Postgres cambia su dirección física, lo que requiere que se actualicen todos los índices (a menos que ocurra una actualización HOT).

---

## 2. Tipos avanzados de índices en PostgreSQL

PostgreSQL ofrece tipos de índices especializados que no tienen un equivalente nativo en MySQL.

### BRIN (Índice de rango de bloques - Block Range Index)
* **Cómo funciona**: En lugar de indexar cada fila individualmente, un índice BRIN divide la tabla en rangos de bloques físicos (por defecto 128 páginas o 1 MB de datos) y almacena únicamente el valor **mínimo** y **máximo** de cada rango.
* **Cuándo utilizarlo**: Tablas sumamente grandes (cientos de gigabytes) donde los datos están ordenados de forma natural en el disco (por ejemplo, IDs autoincrementales, marcas de tiempo `created_at`).
* **Beneficio**: Espacio ocupado increíblemente pequeño. Un índice B-Tree de 10 GB a menudo se puede reemplazar por un índice BRIN de 10 MB.

```sql
-- PostgreSQL: Creating a BRIN index
CREATE INDEX idx_orders_date_brin ON orders USING brin (created_at);
```

### GIN (Índice invertido generalizado - Generalized Inverted Index)
* **Cómo funciona**: Asocia valores (como elementos de matrices/arrays, palabras en un texto o claves JSON) con las filas donde aparecen.
* **Cuándo utilizarlo**: Indexar matrices, documentos JSONB o columnas de búsqueda de texto completo (full-text).

```sql
-- PostgreSQL: GIN index for arrays
CREATE INDEX idx_user_tags ON users USING gin (tags);
```

### GiST (Árbol de búsqueda generalizado - Generalized Search Tree)
* **Cómo funciona**: Una plantilla para construir estructuras B-Tree personalizadas. Se utiliza para indexar coordenadas geométricas, tipos de rango y direcciones de red.

---

## 3. Índices de cobertura, parciales y funcionales

### Índices de cobertura (Optimización de Index-Only Scan)
Un índice de cobertura contiene todas las columnas solicitadas por una consulta. Puedes añadir columnas adicionales de carga útil (payload) al nodo hoja de un índice utilizando la cláusula `INCLUDE`.
* **Sintaxis en PostgreSQL y MySQL**:
  ```sql
  -- PostgreSQL (using INCLUDE)
  CREATE INDEX idx_users_email_include ON users (email) INCLUDE (username, status);

  -- MySQL (using Composite Index - columns must be ordered)
  CREATE INDEX idx_users_email_cover ON users (email, username, status);
  ```

### Índices parciales
Indexan solo un subconjunto de filas que coinciden con una condición de filtrado específica. Esto reduce el tamaño del índice y la sobrecarga de escritura.
* **Solo en PostgreSQL**:
  ```sql
  -- Index only active accounts
  CREATE INDEX idx_users_active_email ON users (email) WHERE status = 'active';
  ```
* *MySQL no admite índices parciales de forma nativa. Debes utilizar índices funcionales con `CASE WHEN` para imitar este comportamiento.*

### Índices funcionales (de expresión)
Indexan el resultado de una función o expresión en lugar de los valores crudos de la columna.
* **Sintaxis en MySQL y PostgreSQL**:
  ```sql
  -- PostgreSQL
  CREATE INDEX idx_users_lower_email ON users (LOWER(email));

  -- MySQL 8.0+
  CREATE INDEX idx_users_lower_email ON users ((LOWER(email)));
  ```

> [!WARNING]
> **Trampas sintácticas en índices funcionales**
> En MySQL 8.0, los índices de expresiones DEBEN encerrarse entre comillas dobles (paréntesis dobles): `((expresión))`. Omitir los paréntesis externos provoca un error de sintaxis.

> [!TIP]
> **Combinación de índices (Index Merging)**
> Cuando el filtro de una consulta contiene `AND` u `OR` en múltiples columnas, las bases de datos pueden realizar una **Combinación de índices (Index Merge)**. Escanea múltiples índices de una sola columna e intersecta o une los mapas de bits resultantes. Sin embargo, un único índice compuesto (múltiples columnas) casi siempre es más rápido que combinar índices separados.

---

## 4. Matriz comparativa de características de indexación

| Característica | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Disposición de tabla agrupada (Clustered)** | No (las tablas están basadas en Heap) | Sí (tabla agrupada por B-Tree de la PK) |
| **Búsqueda de clave primaria** | Índice -> Página Heap | Directamente en el nodo hoja del B-Tree agrupado |
| **Índices parciales** | Sí (cláusula `WHERE`) | No (solución con índice funcional) |
| **Cláusula de índice de cobertura** | Sí (`INCLUDE`) | No (debe definirse como clave compuesta) |
| **Índices de rango de bloques (BRIN)** | Sí | No |
| **Índices invertidos (GIN)** | Sí | No |

---

## 5. Resumen y mejores prácticas

1. **Evita la sobrecarga de la PK autoincremental en MySQL**: Debido a que InnoDB agrupa las tablas por clave primaria, insertar UUIDs aleatorios como claves primarias provoca graves divisiones de página y fragmentación. Utiliza IDs secuenciales o UUIDs ordenados.
2. **Aprovecha BRIN para datos de series temporales grandes**: Si tienes una tabla de registros de varios gigabytes ordenada por marcas de tiempo, utiliza un índice BRIN. Ahorra gigabytes de memoria en comparación con un B-Tree estándar.
3. **Usa índices parciales para columnas dispersas**: Si consultas con frecuencia una tabla para un estado poco común (por ejemplo, `WHERE status = 'retry'`), crea un índice parcial sobre esa condición para mantener el tamaño del índice muy pequeño.
