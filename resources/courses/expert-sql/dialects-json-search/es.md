# Dialects, JSON, and JSON Search

Las aplicaciones modernas manejan con frecuencia datos semiestructurados, como cargas útiles (payloads) de API, configuraciones dinámicas de usuario o atributos polimórficos. Tradicionalmente, esto requería antipatrones EAV (Entidad-Atributo-Valor) o cambiar a bases de datos NoSQL. Hoy en día, las bases de datos relacionales admiten JSON de forma nativa con formatos de almacenamiento binario, indexación y ricas capacidades de consulta. Sin embargo, PostgreSQL y MySQL abordan la representación JSON, la sintaxis de rutas y los mecanismos de indexación de manera diferente.

---

## 1. Aspectos internos de almacenamiento: JSON de texto frente a binario

Cómo se almacena el JSON determina el rendimiento de las consultas y la viabilidad de los índices.

### PostgreSQL: `json` frente a `jsonb`
PostgreSQL ofrece dos tipos de datos JSON:
* **`json`**: Almacena los datos como una copia exacta en texto plano del JSON de entrada. Conserva espacios en blanco, formatos y claves duplicadas. Sin embargo, requiere analizar el texto en cada operación de lectura, lo que ralentiza las consultas de búsqueda.
* **`jsonb`**: Descompone el JSON en un formato binario ya analizado (parsed). Elimina espacios en blanco, remueve claves duplicadas (conservando la última) y ordena las claves de los objetos para búsquedas rápidas. El análisis ocurre una sola vez durante las operaciones de escritura, lo que hace que la indexación y las búsquedas sean sumamente rápidas.

### MySQL: El tipo de datos `JSON`
MySQL proporciona un único tipo `JSON`. Bajo el capó, MySQL almacena el JSON en un formato binario similar al `jsonb` de PostgreSQL. Valida la sintaxis del JSON al insertar y permite un acceso rápido de lectura a los elementos del documento sin volver a analizar el texto crudo.

---

## 2. Consultar JSON: sintaxis y expresiones de ruta

La extracción de datos de objetos JSON requiere operadores de ruta y sintaxis específicos.

### Operadores JSON de PostgreSQL
PostgreSQL proporciona operadores para extraer datos y verificar la estructura del documento:
* `->` devuelve un elemento de objeto o matriz JSON (conservando el tipo `jsonb`).
* `->>` devuelve el elemento como una cadena de texto plano.
* `#>` y `#>>` extraen objetos anidados utilizando una matriz de rutas.
* `@>` verifica la contención (si el JSON de la izquierda contiene al de la derecha).

```sql
-- PostgreSQL: Querying jsonb
SELECT 
    data -> 'user' ->> 'name' AS username,
    data #>> '{user, profile, age}' AS age
FROM app_logs
WHERE data @> '{"status": "error"}';
```

### Funciones y operadores JSON de MySQL
MySQL utiliza funciones estándar o operadores en línea utilizando la sintaxis de ruta `$`:
* `JSON_EXTRACT(col, 'path')` extrae datos.
* `->` actúa como un alias para `JSON_EXTRACT`.
* `->>` (operador de ruta en línea) extrae datos y elimina las comillas del resultado (equivalente a `JSON_UNQUOTE(JSON_EXTRACT(...))`).
* `JSON_CONTAINS(target, candidate, [path])` comprueba si un documento contiene a otro.

```sql
-- MySQL: Querying JSON
SELECT 
    data->'$.user.name' AS username,
    data->>'$.user.profile.age' AS age
FROM app_logs
WHERE JSON_CONTAINS(data, '"error"', '$.status');
```

---

## 3. Indexación de documentos JSON

Escanear cada documento JSON en una tabla de un millón de filas destruye el rendimiento. Debemos indexar los datos.

### PostgreSQL: GIN (Índices invertidos generalizados - Generalized Inverted Indexes)
El tipo `jsonb` de PostgreSQL se integra completamente con los **índices GIN**, los cuales indexan cada clave y valor dentro del documento JSON.
* **GIN por defecto** (`jsonb_ops`): Indexa claves, valores y rutas. Admite consultas que contienen `@>`, `?`, `?|` y `?&`.
* **GIN específico de ruta** (`jsonb_path_ops`): Indexa únicamente parejas de ruta-valor. Crea archivos de índice más pequeños y es más rápido para consultas de contención (`@>`), pero no admite comprobaciones de existencia de claves (`?`).

```sql
-- PostgreSQL: Creating GIN Indexes
CREATE INDEX idx_logs_data ON app_logs USING gin (data);
CREATE INDEX idx_logs_data_path ON app_logs USING gin (data jsonb_path_ops);
```

### MySQL: Columnas virtuales e índices de valores múltiples
MySQL no admite la indexación directa de todo el documento JSON. En su lugar, se apoya en dos técnicas:
1. **Columnas generadas (virtuales) + B-Tree**: Extraer una clave JSON específica en una columna virtual e indexar esa columna.
2. **Índices de valores múltiples (Multi-Valued Indexes)**: Introducidos en MySQL 8.0.17, permiten indexar matrices (arrays) dentro de un documento JSON, admitiendo búsquedas a través de `MEMBER OF()`, `JSON_CONTAINS()` y `JSON_OVERLAPS()`.

```sql
-- MySQL: Generated Column Indexing
ALTER TABLE app_logs ADD COLUMN log_status VARCHAR(50) 
    GENERATED ALWAYS AS (data->>'$.status') VIRTUAL;
CREATE INDEX idx_logs_status ON app_logs(log_status);

-- MySQL: Multi-Valued Index on JSON Array
-- If data contains: {"tags": ["admin", "system", "web"]}
CREATE INDEX idx_logs_tags ON app_logs( (CAST(data->'$.tags' AS UNSIGNED ARRAY)) );

-- Query using Multi-Valued Index
SELECT * FROM app_logs WHERE 3 MEMBER OF (data->'$.tags');
```

> [!WARNING]
> **La trampa de los tipos de datos con columnas generadas**
> Al crear columnas virtuales en MySQL, asegúrate siempre de que coincida el tipo de datos extraído. Si tu campo JSON contiene enteros, convierte la columna virtual o utiliza el tipo correcto. Las discrepancias impiden que el optimizador utilice el índice durante la ejecución de la consulta.

---

## 4. Matriz de comparación de características

| Característica | PostgreSQL (`jsonb`) | MySQL (`JSON`) |
| :--- | :--- | :--- |
| **Formato de almacenamiento** | Representación binaria ordenada | Representación binaria nativa |
| **Operador de ruta (sin comillas)** | `->>` o `#>>` | `->>` |
| **Lenguaje de rutas estándar** | SQL/JSON Path (PostgreSQL 12+) | Sintaxis JSONPath (`$.key`) |
| **Verificación de contención** | Operador `@>` | Función `JSON_CONTAINS()` |
| **Indexación de documento completo** | Sí (mediante índices GIN) | No (requiere columnas generadas / índice funcional) |
| **Indexación de matrices (arrays)** | Sí (GIN integrado) | Sí (índices de valores múltiples, MySQL 8.0.17+) |

---

## 5. Resumen y mejores prácticas

1. **Utiliza siempre tipos binarios**: En PostgreSQL, elige siempre `jsonb` sobre `json`, a menos que solo almacenes y recuperes la información sin realizar consultas o modificaciones.
2. **Diseña para los índices**: En PostgreSQL, utiliza índices GIN para búsquedas flexibles. En MySQL, define columnas virtuales generadas para las claves anidadas que se busquen con frecuencia.
3. **Maneja las comillas correctamente**: Ten cuidado con `->` frente a `->>` (o `JSON_EXTRACT` frente a `JSON_UNQUOTE`). Utilizar el operador incorrecto deja comillas alrededor de los valores de cadena, lo que provoca que fallen las verificaciones de comparación.
