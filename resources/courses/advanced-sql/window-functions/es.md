# Window Functions: Partitioning, Ordering, and Framing

Imagina que estás construyendo un panel de control para mostrar una lista de transacciones de clientes. Necesitas mostrar los detalles de la transacción, pero también quieres mostrar un total acumulado de gastos para cada cliente, su rango o posición basada en el monto de la transacción y el monto de su transacción anterior.

El uso de un `GROUP BY` estándar colapsa las filas, perdiendo los detalles de las transacciones individuales. Hacer esto en la lógica de la aplicación requiere recuperar todos los registros y recorrerlos en un bucle, lo cual es lento y consume mucha memoria. Las **funciones de ventana (Window functions)** solucionan esto al realizar cálculos a través de un conjunto de filas de la tabla que están relacionadas con la fila actual, sin colapsar el conjunto de resultados.

---

## 1. La mecánica central: agrupación frente a ventanas

A diferencia de `GROUP BY`, que agrega múltiples filas en una sola fila de resumen, las funciones de ventana calculan una agregación o rango para cada fila de forma individual mientras conservan todos los campos detallados.

```
GROUP BY:
[Fila 1] \
[Fila 2]  --> [Fila agregada]
[Fila 3] /

FUNCIÓN DE VENTANA (WINDOW FUNCTION):
[Fila 1] --> [Fila 1] [Valor calculado 1]
[Fila 2] --> [Fila 2] [Valor calculado 2]
[Fila 3] --> [Fila 3] [Valor calculado 3]
```

### Sintaxis básica
```sql
-- MySQL 8.0+ & PostgreSQL
SELECT 
    employee_id, 
    department_id, 
    salary,
    SUM(salary) OVER(PARTITION BY department_id) AS dept_total_salary
FROM employees;
```
* **`PARTITION BY`**: Divide las filas en grupos (particiones) que comparten los mismos valores. Si se omite, todo el conjunto de resultados se trata como una única partición.
* **`ORDER BY`**: Define el orden físico de las filas dentro de cada partición. Esto determina cómo se procesan secuencialmente los valores.

---

## 2. Funciones de rango y de valor

Las funciones de ventana se categorizan en agregaciones (como `SUM` o `AVG`), funciones de rango y funciones de recuperación de valores.

### Rango: `ROW_NUMBER()`, `RANK()` y `DENSE_RANK()`
Cuando los valores son idénticos (empates), estas funciones se comportan de manera diferente:
* **`ROW_NUMBER()`**: Asigna un entero secuencial y único que comienza en 1. Los empates se resuelven de forma arbitraria.
* **`RANK()`**: Asigna un rango con saltos. Si dos filas empatan en el primer puesto, ambas obtienen el rango 1 y el siguiente rango será el 3.
* **`DENSE_RANK()`**: Asigna un rango sin saltos. Si dos filas empatan en el primer puesto, ambas obtienen el rango 1 y el siguiente rango será el 2.

| Empleado | Salario | `ROW_NUMBER()` | `RANK()` | `DENSE_RANK()` |
| :--- | :--- | :--- | :--- | :--- |
| Alice | $10,000 | 1 | 1 | 1 |
| Bob | $10,000 | 2 | 1 | 1 |
| Charlie | $8,000 | 3 | 3 | 2 |
| David | $7,000 | 4 | 4 | 3 |

### Funciones de valor: `LAG()`, `LEAD()` y `FIRST_VALUE()`
* **`LAG(col, offset, default)`**: Accede a un valor de una fila en un desplazamiento físico específico *antes* de la fila actual.
* **`LEAD(col, offset, default)`**: Accede a un valor de una fila en un desplazamiento físico específico *después* de la fila actual.

```sql
-- Fetch current and previous transaction amount to calculate the difference
SELECT 
    transaction_date,
    amount,
    LAG(amount, 1, 0) OVER(ORDER BY transaction_date) AS prev_amount
FROM transactions;
```

---

## 3. Enmarcado de ventanas: la ventana deslizante

El marco de la ventana (window frame) define un subconjunto dinámico de filas dentro de la partición, en relación con la fila actual. El marco se desplaza a medida que el motor de la base de datos procesa cada fila.

### Tipos de marcos: `ROWS` vs. `RANGE` vs. `GROUPS`
* **`ROWS`**: Cuenta filas físicas en relación con la fila actual (por ejemplo, 5 filas antes).
* **`RANGE`**: Cuenta valores lógicos basados en la columna de la cláusula `ORDER BY`. Incluye todas las filas que comparten los mismos valores que la fila actual (los empates se tratan juntos).
* **`GROUPS`**: Grupos de valores duplicados basados en la columna de ordenación.

```sql
-- Running Total Frame Example
SUM(amount) OVER(
    PARTITION BY user_id 
    ORDER BY transaction_date
    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
)
```

> [!WARNING]
> **¡La trampa de `LAST_VALUE()`!**
> Si escribes `LAST_VALUE(salary) OVER(PARTITION BY dept_id ORDER BY salary)`, podrías esperar que devuelva el salario más alto del departamento. En su lugar, ¡devuelve el salario de la fila actual!
> Esto se debe a que cuando `ORDER BY` está presente, el marco predeterminado es:
> `RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW`
> Para solucionar esto, especifica explícitamente el marco:
> `LAST_VALUE(salary) OVER(PARTITION BY dept_id ORDER BY salary RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING)`

---

## 4. MySQL vs. PostgreSQL: diferencias arquitectónicas

Aunque ambos motores cumplen con los estándares ANSI SQL, difieren en características, extensiones de sintaxis y rendimiento de ejecución.

### 1. La cláusula `FILTER` (Solo PostgreSQL)
PostgreSQL admite la cláusula `FILTER` con funciones de ventana de agregación, lo que te permite agregar filas selectivamente sin utilizar estructuras complejas de `CASE WHEN`.
```sql
-- PostgreSQL Only
SELECT 
    department_id,
    COUNT(employee_id) FILTER (WHERE salary > 5000) OVER(PARTITION BY department_id) AS highly_paid_count
FROM employees;
```
En MySQL, debes escribir:
```sql
-- MySQL Equivalent
SELECT 
    department_id,
    SUM(CASE WHEN salary > 5000 THEN 1 ELSE 0 END) OVER(PARTITION BY department_id) AS highly_paid_count
FROM employees;
```

### 2. Exclusiones de marcos (Solo PostgreSQL 11+)
PostgreSQL admite opciones avanzadas de exclusión de marcos, lo que te permite excluir filas específicas del cálculo del marco de la ventana.
* `EXCLUDE CURRENT ROW`: Excluye la fila actual del marco.
* `EXCLUDE GROUP`: Excluye la fila actual y todos sus pares de ordenación (filas con el mismo valor de ordenación).
* *MySQL 8.0 no admite cláusulas `EXCLUDE` en el enmarcado de ventanas.*

> [!TIP]
> **Sugerencia de rendimiento:**
> Las funciones de ventana se ejecutan durante la etapa final del procesamiento de la consulta (después de `WHERE` y `GROUP BY`). Para optimizarlas, crea un índice compuesto que coincida con las columnas `PARTITION BY` y `ORDER BY`. Esto permite al motor de la consulta recuperar los datos ya ordenados directamente, evitando costosas operaciones de ordenación en disco (filesorts).

---

## 5. Resumen y mejores prácticas

1. **Conserva la identidad de la fila**: Utiliza funciones de ventana cuando necesites cálculos de agregación junto con campos individuales de la fila.
2. **Atención a los valores predeterminados**: Recuerda que añadir un `ORDER BY` cambia automáticamente el marco de la ventana predeterminado, lo que afecta a las sumas acumulativas y a funciones como `LAST_VALUE()`.
3. **Usa índices**: Comprueba siempre que tus claves de `PARTITION BY` y `ORDER BY` estén indexadas para evitar tablas temporales en el disco de la base de datos.
