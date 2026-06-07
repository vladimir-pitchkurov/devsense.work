---
title: "Bases de datos bajo carga: consultas, índices, MySQL vs Postgres, escalabilidad | DevSense"
description: "Cómo optimizar SQL y esquemas, elegir tipos de índices, cuándo la lógica en el lado de la base de datos se convierte en un inconveniente, cómo se diferencian MySQL y PostgreSQL en producción, y qué cuestan realmente el escalado vertical, las réplicas, la descomposición y el sharding."
published: 2026-04-13
faq:
  - question: "¿Por qué la paginación por búsqueda (keyset) es más rápida que la paginación con OFFSET en grandes conjuntos de datos?"
    answer: "La paginación OFFSET requiere que el motor de la base de datos escanee y descarte todas las filas omitidas (por ejemplo, escanear 100,000 filas para devolver 20). La paginación keyset utiliza una condición de filtro (por ejemplo, `WHERE (created_at, id) < (?, ?)`) basada en la última fila vista, lo que permite al motor saltar directamente a las filas de destino utilizando un índice B-tree sin escanear las filas omitidas."
  - question: "¿Cuándo debería usar un índice de cobertura en lugar de un índice regular?"
    answer: "Deberías usar un índice de cobertura (a menudo usando la cláusula `INCLUDE` o un índice compuesto que contenga todos los campos seleccionados) cuando una consulta de alta frecuencia lee solo unas pocas columnas. Esto permite que el motor de la base de datos devuelva el resultado directamente desde la estructura del índice (Index Only Scan) sin realizar una búsqueda secundaria en el disco o Heap para obtener la fila completa."
  - question: "¿Cuáles son las principales desventajas de usar réplicas de lectura?"
    answer: "Las réplicas de lectura liberan de operaciones de lectura a la base de datos primaria, aumentando el rendimiento de lectura. Sin embargo, el retraso de replicación (replication lag) significa que las réplicas pueden servir datos obsoletos (consistencia eventual). Los desarrolladores deben diseñar el enrutamiento de la aplicación para dirigir las lecturas dependientes de escritura a la base de datos primaria para evitar errores de consistencia tipo 'read-your-writes'."
  - question: "¿Por qué se considera al sharding como el último recurso para el escalado de bases de datos?"
    answer: "El sharding divide los datos en instancias de bases de datos físicamente separadas, lo que aumenta la complejidad operativa. Impide el uso de joins entre shards, la integridad de transacciones globales (sin confirmaciones de dos fases lentas) y restricciones de unicidad global simples, al tiempo que introduce la dificultad de rebalancear los shards cuando cambia la distribución de los datos."
---

# Bases de datos bajo carga: optimización de consultas, índices, motores y compromisos de escalabilidad

La mayoría de las historias sobre rendimiento son aburridas hasta que dejan de serlo. El panel de control se ve bien a noventa y cinco milisegundos, luego se lanza una campaña, un informe une seis tablas y, de repente, **el proceso de pago** y **el restablecimiento de contraseña** comparten una cola detrás del mismo motor de almacenamiento. Las soluciones rara vez se reducen a una sola perilla: consisten en **menos viajes de ida y vuelta**, **índices que coincidan con predicados reales**, **cálculos honestos de capacidad** y, a veces, **admitir que una base de datos lógica no puede ser infinita**.

**Guías relacionadas:** [High-load event ingestion](high-load-event-ingestion) · [Message queues compared](message-queues-compared) · [Observability and monitoring](observability-monitoring-laravel)

## Índice

* [Medir antes de “optimizar”](#measure)
* [Optimizaciones a nivel de consulta con ejemplos](#queries)
* [Elecciones de esquema que envejecen bien](#schema)
* [Tipos de índices y cuándo ayudan](#indexes)
* [Por qué la lógica pesada dentro de la base de datos perjudica a los equipos](#db-logic)
* [MySQL frente a PostgreSQL en la práctica](#mysql-vs-postgres)
* [Rutas de escalado y los problemas que importan](#scaling)
* [Descomposición sin cuentos de hadas](#decomposition)
* [Sharding: claves, dolor entre shards, rebalanceo](#sharding)
* [Errores comunes](#common-mistakes)
* [Lista de verificación antes de hacer sharding o comprar hardware](#checklist)
* [Cuestionario de autoevaluación](#self-test-quiz)

---

<a id="measure"></a>
## Medir antes de “optimizar”

Los **percentiles de latencia** superan a los promedios. Un promedio de 40 ms puede ocultar una cola donde el **uno por ciento** de las solicitudes supera los dos segundos debido a esperas de bloqueo o cachés fríos.

Señales prácticas:
* **Registros de consultas lentas** con un umbral que debes revisar a medida que crecen los datos (no registres \"todo\" o acostumbrarás al equipo a ignorar el ruido).
* **`EXPLAIN` (Postgres)** / **`EXPLAIN` (MySQL 8+)** en los formatos de consulta reales que ejecutas en producción con enlaces de parámetros, no solo literales escritos a mano.
* **Cuentas de conexión** y **saturación del pool**; muchos incidentes de \"base de datos lenta\" son en realidad **espera de una conexión**, no problemas de disco.

> [!NOTE]
> **Estado compartido (Shared State)**
> La base de datos es un estado compartido. Cualquier cosa que aumente la **CPU, los bloqueos o la E/S** en el nodo primario eventualmente competirá con todo lo demás en ese mismo camino.

---

<a id="queries"></a>
## Optimizaciones a nivel de consulta con ejemplos

### Recuperar solo lo que necesitas

Un `SELECT *` amplio sobre filas pesadas obliga al motor a mover bytes que terminarás desechando en PHP o Node. Prefiere columnas explícitas, especialmente en tablas con grandes cargas de texto o JSON.

```sql
-- database/queries/fetch_users.sql
-- Evitar (envía todas las columnas, incluidos los blobs que no renderizas)
SELECT * FROM users WHERE country = 'DE' LIMIT 50;

-- Preferir
SELECT id, email, display_name, created_at
FROM users
WHERE country = 'DE'
ORDER BY created_at DESC
LIMIT 50;
```

### Forma de join y cardinalidad

Los bucles anidados son económicos cuando el lado interno está **respaldado por un índice** y es pequeño; los joins hash brillan en conjuntos más grandes (la **estrategia que obtienes** depende del motor y de las estadísticas). Si un join explota el conteo de filas porque una relación es de **muchos a muchos sin restricciones**, corrige el modelo, no el tiempo de espera.

### Paginación sin escanear toda la tabla

La paginación con `OFFSET` es engañosamente simple. Para offsets grandes, el motor a menudo **sigue recorriendo** las filas omitidas.

```sql
-- database/queries/offset_pagination.sql
-- Se vuelve más lento a medida que crece :offset
SELECT id, title, created_at
FROM posts
ORDER BY created_at DESC, id DESC
LIMIT 20 OFFSET 100000;
```

La **paginación keyset (búsqueda)** utiliza la última clave de ordenación vista:

```sql
-- database/queries/seek_pagination.sql
SELECT id, title, created_at
FROM posts
WHERE (created_at, id) < (:last_created_at, :last_id)
ORDER BY created_at DESC, id DESC
LIMIT 20;
```

Necesitas un **índice de soporte** que coincida con el orden de clasificación, por ejemplo `(created_at DESC, id DESC)`.

### `EXISTS` frente a `IN` para comprobaciones de existencia

Para patrones de \"¿existe alguna fila coincidente?\", los planes de estilo semi-join suelen comportarse bien:

```sql
-- database/queries/exists_lookup.sql
SELECT o.id, o.total_cents
FROM orders o
WHERE EXISTS (
  SELECT 1 FROM order_items i
  WHERE i.order_id = o.id AND i.sku = 'GOLD-001'
);
```

### Agregaciones e informes

Los `GROUP BY` pesados sobre tablas OLTP puras son una forma clásica de **perjudicar la latencia de cola**. Precomputa los datos en **tablas de resumen**, **vistas materializadas** (Postgres) o un almacén **OLAP** cuando el negocio realmente requiera análisis interactivos.

---

<a id="schema"></a>
## Elecciones de esquema que envejecen bien

* **Tipos que coinciden con la realidad**: almacena dinero como **unidades menores enteras** (por ejemplo, centavos) o **decimal con precisión explícita**, no como flotantes binarios.
* **Nullabilidad**: las columnas que admiten valores nulos complican la indexación y las estadísticas; úsalas cuando el valor desconocido sea significativo, no como una opción predeterminada por pereza.
* **Claves foráneas**: cuestan un poco en las escrituras y garantizan la **integridad referencial**. Los equipos que las deshabilitan por \"velocidad\" a menudo pagan el precio con **filas huérfanas** y **errores de aplicación no deterministas**.
* **Sobrenormalización frente a rutas de lectura**: la teoría pura ignora la frecuencia con la que lees formas unidas. Un **campo desnormalizado** medido y documentado puede superar a los joins interminables, a costa de la **complejidad de escritura** y la **disciplina invariante**.

---

<a id="indexes"></a>
## Tipos de índices y cuándo ayudan

No todos los índices son árboles B, y no todos los árboles B tienen la forma correcta.

| Concepto | Uso típico | Notas |
|---------|------------|-------|
| **B-tree (predeterminado)** | Igualdad y rango en tipos ordenables | La mayoría de los índices \"normales\"; en índices compuestos **el orden importa** (las columnas iniciales deben coincidir con los filtros comunes `WHERE`/`ORDER BY`). |
| **Hash** | Igualdad exacta (donde se soporte) | Los índices **hash** en Postgres históricamente tenían advertencias de replicación. MySQL expone los conceptos de hash principalmente a través de **MEMORY** e internos de **hash adaptativo**. |
| **Full-text** | Búsqueda de tokens en texto | MySQL **InnoDB FTS** frente a Postgres **`tsvector` + GIN** (diferentes analizadores y perfiles de mantenimiento). |
| **GIN / GiST (Postgres)** | Contención de JSONB, arrays, búsqueda de texto completo, algunos tipos geométricos | Potente; requiere monitoreo de la **creación del índice y el bloat** (inflado). |
| **Spatial** | Consultas geográficas | Específico del motor (**PostGIS** en Postgres; tipos espaciales en MySQL). |

### Índices compuestos: orden de las columnas

Si las consultas se filtran casi siempre por `tenant_id`, colocarlo **primero** suele ser lo correcto:

```sql
-- database/migrations/create_composite_index.sql
-- Bueno cuando las consultas se ven como: WHERE tenant_id = ? AND status = 'open'
CREATE INDEX orders_tenant_status_idx ON orders (tenant_id, status);
```

### Índices de cobertura (Covering Indexes)

Si el índice **contiene todas las columnas** que lee la consulta, el motor puede resolver la consulta únicamente desde el índice (**Index Only Scan** en Postgres). Patrón de ejemplo:

```sql
-- database/migrations/create_covering_index.sql
CREATE INDEX sessions_lookup_idx ON sessions (user_id) INCLUDE (last_seen_at);
```

### Índices parciales / filtrados

Cuando un predicado es **estable y selectivo**, indexa únicamente la sección activa:

```sql
-- database/migrations/create_partial_index.sql
-- Índice parcial específico de Postgres
CREATE INDEX invoices_open_due_idx
ON invoices (due_at)
WHERE status = 'open' AND due_at IS NOT NULL;
```

---

<a id="db-logic"></a>
## Por qué la lógica pesada dentro de la base de datos perjudica a los equipos

Los procedimientos almacenados, los disparadores (triggers) y las vistas complejas **no son física malvada**. Son decisiones de **despliegue y propiedad**.

Lo que suele salir mal:
* **Control de versiones**: el código de la aplicación se implementa a través de Git, CI y despliegues progresivos; las rutinas de la base de datos viven **en otro lugar**. La discrepancia entre ramas y entornos se vuelve dolorosa.
* **Pruebas**: las reglas de negocio en PHP/Java se prueban unitariamente en entornos familiares; los triggers que mutan filas al insertar son **más difíciles de analizar** de forma aislada.
* **Portabilidad**: la lógica en la aplicación puede moverse entre MySQL, Postgres o incluso diferentes proveedores; la lógica en PL/pgSQL o en los procedimientos almacenados de MySQL te **encadena a un proveedor**.
* **Observabilidad**: los rastros de pila (stack traces) y los intervalos APM se centran en la capa de la aplicación; las cadenas de triggers profundas se manifiestan como **latencia misteriosa** a menos que instrumentes con cuidado.

Cuándo la lógica del lado de la base de datos **todavía** tiene sentido:
* **Restricciones**: `CHECK`, claves foráneas y la **unicidad bien elegida** expresan invariantes de manera más económica que esperar que cada servicio las recuerde en el código de la aplicación.
* **Protecciones de idempotencia**: un **índice único** sobre una clave de negocio supera a las condiciones de carrera tipo \"seleccionar y luego insertar\".

---

<a id="mysql-vs-postgres"></a>
## MySQL frente a PostgreSQL en la práctica

Ambos son de nivel de producción. Las diferencias surgen cuando asumes que son intercambiables.

| Tema | MySQL (InnoDB típico) | PostgreSQL |
|-------|-------------------------|------------|
| **Modelo de almacenamiento** | La **clave primaria** (agrupada) organiza las filas; los índices secundarios apuntan a la clave primaria | Tablas **Heap**; los índices están separados; **CLUSTER** es una operación de mantenimiento |
| **MVCC / limpieza** | Historial de **deshacer (Undo)**; los retrasos de purga pueden importar para transacciones largas | **Tuplas muertas**; **VACUUM** (autovacuum) es el pan de cada día operativo |
| **Características SQL** | SQL básico sólido; históricamente más estricto sobre algunas esquinas | **CTEs** más ricos, **funciones de ventana**, **LATERAL**, operadores **JSONB**, **tipos de rango** |
| **Extensiones** | Menos en el núcleo; ecosistema a través de complementos | **PostGIS**, **pgvector**, **Citext**, muchos otros |
| **Replicación** | Transmisión madura de **binlog**; muchas topologías alojadas | Replicación **física** y **lógica**; modelo **publicación/suscripción** |

Ejemplos de consultas que divergen:

Postgres—**Contención de JSONB**:
```sql
-- database/queries/postgres_jsonb_containment.sql
SELECT id FROM events WHERE payload @> '{"kind":"purchase"}'::jsonb;
```

MySQL—Extracción de **JSON** (8.x):
```sql
-- database/queries/mysql_json_extract.sql
SELECT id FROM events WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.kind')) = 'purchase';
```

---

<a id="scaling"></a>
## Rutas de escalado y los problemas que importan

### Escalado vertical (“máquina más grande”)
Simple hasta que la física no está de acuerdo. Aparecen el ancho de banda del disco y los efectos CPU NUMA. Además, concentras el punto de falla.

### Réplicas de lectura
* **Pros:** liberan tráfico de **solo lectura**, respaldos, clones de informes.
* **Cons:** el **retraso de replicación** (replication lag) significa que las lecturas pueden estar obsoletas.

### Pool de conexiones (Connection Pooling)
Abrir una sesión de TCP+auth por solicitud HTTP no escala. Herramientas como **PgBouncer** (Postgres) o ProxySQL (MySQL) se ubican entre la aplicación y la base de datos.

### Caching (Redis, etc.)
Los cachés enmascaran las claves calientes (hot keys); no solucionan la **amplificación de escritura** en la base de datos primaria.

---

<a id="decomposition"></a>
## Descomposición sin cuentos de hadas

**Esquema por servicio** no es magia: significa **menos joins accidentales** y un **radio de impacto de fallas más claro**. Introduce **transacciones distribuidas** o **sagas** cuando una acción del usuario realmente abarca múltiples almacenes.

Antipatrón: **dos servicios que escriben en la misma tabla** a través de una “base de datos compartida” (has construido un **monolito distribuido** con saltos de red adicionales).

---

<a id="sharding"></a>
## Sharding: claves, dolor entre shards, rebalanceo

El **sharding** divide las filas en **muchos nodos primarios** utilizando una **clave de particionado (shard key)** (a menudo el ID de usuario o el ID de tenant).

Qué mejora:
* Los límites máximos de **rendimiento de escritura** por máquina disminuyen cuando cada shard posee una fracción de los datos.
* El **radio de impacto de fallas** puede reducirse si una falla aísla los shards.

Qué duele:
* **Joins entre shards** y **unicidad global**: necesitas coordinación o reglas a nivel de aplicación.
* **Rebalancear** cuando un tenant muy activo domina un shard (el resharding es operativamente complejo).

---

<a id="common-mistakes"></a>
## Erreurs courantes (Errores comunes)

1. **Depender de OFFSET para grandes conjuntos de datos**: usar la paginación estándar `LIMIT ... OFFSET`, obligando al motor a escanear millones de filas para devolver solo unas pocas.
2. **Ignorar el orden de las columnas en índices compuestos**: colocar una columna con filtros de rango (por ejemplo, `created_at`) antes de columnas con filtros de igualdad en las definiciones de índices compuestos.
3. **Patrón de base de datos compartida en microservicios**: permitir que múltiples servicios escriban en la misma tabla, creando un acoplamiento fuerte y cuellos de botella de integración.
4. **Escribir lógica de negocio en triggers**: colocar validaciones y mutaciones complejas en triggers, eludiendo pruebas, CI y rastreo.

---

<a id="checklist"></a>
## Lista de verificación antes de hacer sharding o comprar hardware

1. **¿Puede `EXPLAIN` mostrar un escaneo secuencial** que un índice honesto corregiría?
2. **¿Se están generando consultas N+1** desde la capa del ORM independientemente de la velocidad del disco?
3. **¿El cuello de botella son las conexiones** o la CPU en la base de datos, o la **contención de bloqueos** de transacciones largas?
4. **¿Has medido el retraso de replicación** si movieras las lecturas a las réplicas?
5. **¿El crecimiento del conjunto de datos está limitado por la retención** (archivar particiones frías) de manera más económica que una nueva topología?

---

## Resumen

Las bases de datos recompensan la **corrección aburrida** y la **medición**. Los índices y la elección del motor compran tiempo; la **arquitectura** (colas, modelos de lectura separados y, a veces, sharding) compra **margen de crecimiento**.

---

<a id="self-test-quiz"></a>
## Cuestionario de autoevaluación

### Pregunta 1: ¿Por qué una consulta como `SELECT * FROM users ORDER BY created_at DESC LIMIT 1` se ejecuta lentamente en una tabla de 10 millones de filas incluso si `created_at` está indexado?
- A) Los índices no se pueden escanear en orden descendente.
- B) Si la columna admite valores nulos, el motor de la base de datos puede escanear toda la tabla para buscar valores NULL.
- C) Debes usar un índice de cobertura con `INCLUDE`.

<details>
<summary><b>Mostrar respuestas</b></summary>

**Respuesta: B**
Si la columna ordenada admite valores nulos y la consulta no filtra los nulos (por ejemplo, `WHERE created_at IS NOT NULL`), el motor puede recurrir a un escaneo completo de la tabla para ubicar los valores nulos, omitiendo el escaneo del índice.
</details>

---

### Pregunta 2: ¿Qué tipo de índice es el más adecuado para buscar claves dentro de documentos JSON arbitrarios en PostgreSQL?
- A) Índice B-Tree.
- B) Índice GIN (Generalized Inverted Index).
- C) Índice Hash.

<details>
<summary><b>Mostrar respuestas</b></summary>

**Respuesta: B**
Los índices GIN están diseñados específicamente para indexar elementos con múltiples valores, como arrays y estructuras JSONB, lo que permite consultas de contención rápidas.
</details>
