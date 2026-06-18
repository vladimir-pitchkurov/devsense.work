# Bases de datos relacionales y la sentencia SELECT

En el núcleo del almacenamiento de datos moderno se encuentra el modelo de base de datos relacional, propuesto por primera vez por Edgar F. Codd en 1970. En lugar de almacenar datos en archivos de texto no estructurados o árboles jerárquicos rígidos, una base de datos relacional organiza la información en tablas estructuradas que se pueden unir y consultar de forma dinámica utilizando el Lenguaje de Consulta Estructurado (SQL). Ya sea que utilices MySQL o PostgreSQL, es fundamental comprender los conceptos fundamentales del diseño relacional y cómo recuperar datos de manera eficiente.

---

## 1. El modelo relacional: tablas, columnas y claves

En una base de datos relacional, los datos se representan como una colección de tablas de relaciones. Cada tabla consta de:
* **Columnas (Atributos)**: Definen el tipo de datos y las propiedades de la información almacenada (por ejemplo, `user_id` como un entero, `email` como una cadena de texto).
* **Filas (Registros/Tuplas)**: Representan instancias individuales de datos (por ejemplo, el registro de un usuario específico).

Para mantener la integridad, las tablas se apoyan en dos conceptos esenciales:
1. **Clave primaria (PK - Primary Key)**: Una columna (o conjunto de columnas) que identifica de forma única cada fila de una tabla. No puede contener valores `NULL`.
2. **Clave foránea o externa (FK - Foreign Key)**: Una columna en una tabla que hace referencia a la clave primaria de otra tabla, estableciendo una relación entre ellas.

| Concepto | Descripción | Analogía |
| :--- | :--- | :--- |
| **Tabla** | Una cuadrícula estructurada de columnas y filas. | Una pestaña de hoja de cálculo. |
| **Fila** | Un único registro de datos. | Una sola línea en una hoja de cálculo. |
| **Clave primaria** | Identificador único para una fila. | Número de pasaporte. |
| **Clave foránea** | Referencia a la clave primaria de otra tabla. | El ID de departamento de un empleado. |

---

## 2. Recuperación de datos con `SELECT`

La operación más fundamental en SQL es recuperar datos mediante la sentencia `SELECT`. En su forma más simple, una consulta requiere una cláusula `SELECT` (qué columnas recuperar) y una cláusula `FROM` (qué tabla consultar).

### Seleccionar todas las columnas frente a columnas específicas
Puedes recuperar todas las columnas utilizando el comodín de asterisco (`*`), o especificar solo las columnas que necesites.
```sql
-- MySQL & PostgreSQL
-- Retrieve all columns (not recommended for production)
SELECT * FROM users;

-- Retrieve specific columns (recommended)
SELECT id, email, first_name FROM users;
```

> [!TIP]
> **¿Sabías que...?**
> Utilizar `SELECT *` en producción se considera un antipatrón. Fuerza al motor de la base de datos a realizar operaciones de E/S de disco adicionales para leer columnas que no necesitas, consume ancho de banda de red innecesario y puede romper tu aplicación si se añade una nueva columna o se elimina una existente de la tabla.

### Alias de columnas (`AS`)
Los alias te permiten renombrar las columnas en el conjunto de resultados de tu consulta para una mejor legibilidad o para adaptarse a lo que espera la aplicación.
```sql
-- MySQL & PostgreSQL
SELECT first_name AS given_name, last_name AS surname FROM users;
```

---

## 3. Filtrar resultados con `WHERE`

Para recuperar solo filas específicas, utiliza la cláusula `WHERE`. La cláusula `WHERE` evalúa una condición booleana para cada fila y devuelve únicamente aquellas que resultan verdaderas.

### Operadores estándar
SQL admite los operadores de comparación estándar:
* `=` (igual a)
* `<>` o `!=` (diferente de)
* `>` (mayor que), `<` (menor que)
* `>=` (mayor o igual que), `<=` (menor o igual que)

```sql
-- Retrieve active users registered after a specific ID
SELECT email FROM users WHERE is_active = true AND id > 100;
```

### Operadores de filtrado avanzados
* **`BETWEEN`**: Filtra valores dentro de un rango específico (incluyendo los extremos).
* **`IN`**: Comprueba si un valor coincide con algún valor de una lista especificada.
* **`LIKE`**: Realiza una búsqueda de patrones simple utilizando comodines:
  - `%` representa cero o más caracteres.
  - `_` representa exactamente un carácter.

```sql
-- Retrieve users with IDs 1, 3, or 5
SELECT email FROM users WHERE id IN (1, 3, 5);

-- Retrieve users registered in a specific range
SELECT email FROM users WHERE id BETWEEN 10 AND 50;

-- Retrieve users whose email starts with 'admin'
SELECT email FROM users WHERE email LIKE 'admin%';
```

---

## 4. Diferencias clave: MySQL vs. PostgreSQL

Aunque ambos motores siguen el estándar SQL, difieren en sintaxis y comportamientos predeterminados.

### Comillas en identificadores
Los identificadores (nombres de tablas, nombres de columnas) deben delimitarse con comillas si entran en conflicto con palabras clave reservadas de SQL o si contienen caracteres especiales o espacios.
* **MySQL**: Utiliza comillas invertidas (`` ` ``).
* **PostgreSQL**: Utiliza comillas dobles (`"`).

```sql
-- MySQL
SELECT `select`, `group` FROM `my_table`;

-- PostgreSQL
SELECT "select", "group" FROM "my_table";
```

### Sensibilidad a mayúsculas y minúsculas en la búsqueda de patrones
* **PostgreSQL** es estrictamente sensible a mayúsculas y minúsculas con `LIKE`. Para realizar una búsqueda insensible a mayúsculas y minúsculas, debes utilizar el operador `ILIKE`, específico de PostgreSQL.
* El operador `LIKE` de **MySQL** es insensible a mayúsculas y minúsculas por defecto bajo las colaciones estándar (por ejemplo, `utf8mb4_0900_ai_ci`). Para hacerlo sensible a mayúsculas y minúsculas, debes convertir la cadena a binario o usar una colación binaria.

```sql
-- Case-insensitive search for 'john'
-- PostgreSQL
SELECT email FROM users WHERE email ILIKE 'john%';

-- MySQL
SELECT email FROM users WHERE email LIKE 'john%';
```

### Concatenación de cadenas
* **PostgreSQL** utiliza el operador estándar de SQL de doble barra vertical (`||`).
* **MySQL** no admite `||` para la concatenación por defecto (trata `||` como el operador lógico `OR`, a menos que esté habilitado el modo SQL `PIPES_AS_CONCAT`). En su lugar, MySQL utiliza la función `CONCAT()`.

```sql
-- PostgreSQL
SELECT first_name || ' ' || last_name AS full_name FROM users;

-- MySQL
SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users;
```

### Tipos de datos booleanos
* **PostgreSQL** tiene un tipo `BOOLEAN` nativo que admite los valores literales `true` y `false`.
* **MySQL** no tiene un tipo booleano real; asigna el alias `BOOLEAN` a `TINYINT(1)`, donde `0` es falso y `1` es verdadero.

```sql
-- PostgreSQL: Returns true/false
SELECT is_active FROM users;

-- MySQL: Returns 1/0
SELECT is_active FROM users;
```

> [!WARNING]
> **¡La trampa de las comillas!**
> Nunca uses comillas dobles (`"`) para literales de cadena (valores de texto) en PostgreSQL. PostgreSQL trata las comillas dobles como comillas de identificador (para nombres de columnas o tablas), lo que provocará un error de sintaxis del tipo `column "value" does not exist`. Utiliza siempre comillas simples (`'`) para los literales de cadena tanto en MySQL como en PostgreSQL.

---

## 5. Resumen y mejores prácticas

1. **Sé específico**: Enumera explícitamente los nombres de las columnas en lugar de utilizar `SELECT *` para mejorar el rendimiento y la durabilidad del código.
2. **Estandariza las comillas**: Utiliza comillas simples (`'`) para los literales de cadena en todos los motores de bases de datos.
3. **Conoce tu operador**: Usa `ILIKE` en PostgreSQL para coincidencias insensibles a mayúsculas y minúsculas, y recuerda que la concatenación con `||` es el estándar en Postgres pero requiere `CONCAT()` in MySQL.
4. **Verificaciones booleanas**: Recuerda que MySQL almacena los booleanos como `1` o `0`, lo que puede afectar a cómo el código de tu aplicación procesa los resultados de las consultas.
