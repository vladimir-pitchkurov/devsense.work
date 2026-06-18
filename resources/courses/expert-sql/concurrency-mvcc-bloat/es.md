# Concurrency, MVCC, and Table Bloat

En una base de datos de alto rendimiento, miles de transacciones leen y escriben datos de forma concurrente. Si cada lectura bloqueara la fila, las bases de datos se paralizarían por completo. Para solucionar esto, las bases de datos relacionales modernas utilizan el **Control de concurrencia multiversión (MVCC - Multi-Version Concurrency Control)**. La filosofía central de MVCC es simple: *los lectores no bloquean a los escritores y los escritores no bloquean a los lectores*. Sin embargo, MySQL y PostgreSQL implementan esta filosofía de maneras fundamentalmente diferentes, lo que da lugar a perfiles de rendimiento, requisitos de mantenimiento y estrategias de optimización completamente distintos.

---

## 1. Cómo funciona MVCC bajo el capó

Cuando una transacción actualiza una fila, la base de datos no sobrescribe los datos antiguos de forma inmediata. En su lugar, mantiene múltiples versiones de esa fila, permitiendo que las transacciones activas vean una instantánea (snapshot) consistente de los datos basada en su nivel de aislamiento.

### PostgreSQL: Versionado de tuplas (In-Heap)
PostgreSQL mantiene todas las versiones de fila (llamadas "tuplas") directamente en el área de almacenamiento de datos de la tabla (table heap).
* **`xmin`**: El ID de transacción (TxID) de la transacción que insertó la fila.
* **`xmax`**: El ID de transacción de la transacción que eliminó o actualizó la fila (inicialmente `0` o nulo).

Al actualizar una fila en Postgres, se realiza un `DELETE` lógico seguido de un `INSERT`. Marca el `xmax` de la fila existente con el TxID actual e inserta una fila nueva con el `xmin` igual al TxID actual.

### MySQL (InnoDB): Registros de deshacer (Undo Logs) y segmentos de reversión (Rollback Segments)
El motor InnoDB de MySQL adopta un enfoque diferente. Realiza las actualizaciones directamente (in-place) dentro del espacio de tablas (tablespace) pero escribe la versión antigua de la fila en una estructura dedicada llamada **Undo Log** (registro de deshacer).
* Cada encabezado de fila contiene un `DB_TRX_ID` (el ID de transacción que modificó la fila por última vez) y un `DB_ROLL_PTR` (un puntero de reversión que apunta al registro del undo log que contiene la versión anterior).
* Cuando un lector necesita una instantánea anterior, InnoDB comienza con la fila activa y recorre la cadena de deshacer hacia atrás utilizando el puntero de reversión para reconstruir los datos sobre la marcha.

---

## 2. Obsolescencia (Bloat) en PostgreSQL, VACUUM y actualizaciones HOT

Dado que PostgreSQL almacena las tuplas muertas (versiones antiguas de filas actualizadas o eliminadas) directamente en las páginas de la tabla, el tamaño de las tablas crece de forma natural con el tiempo. Este fenómeno se conoce como **obsolescencia de la tabla (table bloat)**.

```
Actualización de página Heap en PostgreSQL:
+-------------------------------------------------------------+
| [Tupla v1 (xmin: 100, xmax: 101)] -> Tupla muerta (Bloat)   |
| [Tupla v2 (xmin: 101, xmax: 0)]   -> Tupla viva             |
+-------------------------------------------------------------+
```

### El rol de VACUUM
Para recuperar el espacio ocupado por tuplas muertas, PostgreSQL ejecuta un proceso en segundo plano llamado **Autovacuum**.
* **VACUUM estándar**: Escanea las páginas y marca las tuplas muertas como reutilizables para futuras inserciones. *No* devuelve espacio al sistema operativo (a menos que las páginas al final de la tabla estén completamente vacías).
* **VACUUM FULL**: Reconstruye la tabla por completo, encogiéndola y devolviendo espacio al sistema operativo. **Advertencia**: Esto bloquea la tabla de forma exclusiva (`ACCESS EXCLUSIVE`), bloqueando todas las lecturas y escrituras.

### Optimización de tuplas solo en Heap (HOT - Heap-Only Tuple)
Para evitar la actualización de los punteros de índice cada vez que cambia una versión de fila, PostgreSQL utiliza **actualizaciones HOT**. Si una actualización no modifica las columnas indexadas y la página tiene suficiente espacio libre:
1. Postgres coloca la nueva tupla en la misma página.
2. Encadena la tupla antigua con la tupla nueva.
3. Los índices siguen apuntando a la tupla antigua; los lectores siguen la cadena.

> [!WARNING]
> **¡La configuración de Autovacuum es obligatoria!**
> La configuración predeterminada de autovacuum en PostgreSQL es notablemente conservadora. En tablas con alta frecuencia de escritura, esto provoca una obsolescencia descontrolada, degradando el rendimiento de las consultas debido a que la base de datos debe escanear tuplas muertas. Ajusta siempre `autovacuum_vacuum_scale_factor` (por defecto 0.20 o el 20% de filas modificadas) a `0.05` o menos para tablas grandes.

---

## 3. MySQL InnoDB: depuración y truncamiento de Undo

En MySQL, la obsolescencia de las tablas es un problema mucho menor porque las versiones antiguas de las filas se almacenan en los undo logs en lugar de en el espacio de tablas. Sin embargo, InnoDB tiene sus propios cuellos de botella de concurrencia.

### Hilos de depuración de InnoDB (Purge Threads)
Una vez que la transacción activa más antigua ya no necesita un registro del undo log, un proceso en segundo plano llamado **Purge Thread** limpia las páginas del undo log.
* Si una transacción permanece abierta durante horas (por ejemplo, una consulta de generación de informes de larga duración), InnoDB no puede depurar ningún undo log creado desde que comenzó esa transacción.
* Esto provoca que el **espacio de tablas de deshacer (Undo Tablespace)** se hinche. En versiones anteriores de MySQL, los espacios de tablas de deshacer no podían encogerse, lo que requería una reconstrucción completa de la base de datos para recuperar espacio en disco.

```sql
-- MySQL: Inspecting InnoDB Undo Log & Transaction States
SELECT 
    trx_id, trx_state, trx_started, 
    TIMESTAMPDIFF(SECOND, trx_started, NOW()) AS duration_sec
FROM information_schema.innodb_trx
ORDER BY trx_started ASC;
```

> [!TIP]
> **Truncamiento automático de Undo**
> En MySQL 8.0, InnoDB trunca automáticamente los espacios de tablas de deshacer por defecto. Asegúrate de que `innodb_undo_log_truncate = ON` e `innodb_max_undo_log_size` (típicamente 1 GB) estén configurados para recuperar automáticamente el espacio de disco del undo tablespace.

---

## 4. Matriz comparativa de MVCC

| Característica | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Almacenamiento de versiones antiguas** | In-Heap (directamente en los archivos de la tabla) | Undo Logs (segmentos de reversión separados) |
| **Mecanismo de actualización** | Delete + Insert (crea una nueva tupla) | Actualización in-place + escribir versión antigua en Undo |
| **Actualización de índices** | Requiere actualizar todos los índices (a menos que sea HOT) | Solo actualiza el índice si la columna indexada cambia |
| **Limpieza de espacio en disco** | Vacuum / Autovacuum (recupera ranuras de página) | Purge Threads (limpia las páginas de Undo log) |
| **Riesgo de obsolescencia (Bloat)** | Alta obsolescencia de tablas e índices | Alta obsolescencia de Undo log con transacciones largas |

---

## 5. Resumen y mejores prácticas

1. **Mantén las transacciones cortas**: Ambos motores sufren si las transacciones permanecen abiertas demasiado tiempo. En Postgres, evita que autovacuum limpie las tuplas muertas; en MySQL, evita que los purge threads limpien los undo logs.
2. **Configura el fillfactor de PostgreSQL**: Para tablas con un alto volumen de actualizaciones, reduce el `fillfactor` de la tabla (por ejemplo, a 80 o 90) para dejar espacio libre en cada página para las actualizaciones HOT.
3. **Monitorea el espacio de Undo en MySQL**: Configura alertas para transacciones activas de larga duración para evitar la explosión del espacio de deshacer.
