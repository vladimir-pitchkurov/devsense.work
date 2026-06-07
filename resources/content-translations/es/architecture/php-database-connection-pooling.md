---
title: "Las aplicaciones PHP y el cuello de botella del pool de conexiones a la base de datos | DevSense"
description: "Por qué PHP-FPM y los trabajadores multiplican las sesiones de base de datos, cómo los poolers y proxies de nivel intermedio comparten las conexiones reales del servidor, y qué deben saber los equipos de Laravel sobre los modos de PgBouncer, ProxySQL y las sentencias preparadas."
published: 2026-04-14
faq:
  - question: "¿Cuál es la diferencia principal entre Session pooling y Transaction pooling en PgBouncer?"
    answer: "Session pooling asigna una conexión de servidor a un cliente durante toda la duración de su conexión, liberándola solo cuando el cliente se desconecta. Transaction pooling libera la conexión del servidor de regreso al pool inmediatamente después de cada transacción (`COMMIT` o `ROLLBACK`). Transaction pooling permite relaciones de cliente a servidor mucho más altas, pero rompe el estado a nivel de sesión como tablas temporales, bloqueos consultivos (advisory locks) y configuraciones persistentes."
  - question: "¿Por qué a veces fallan las sentencias preparadas (prepared statements) cuando se utiliza el agrupamiento por transacciones?"
    answer: "Bajo transaction pooling, las consultas secuenciales en la misma sesión de cliente pueden enrutarse a diferentes backends de servidor de base de datos. Si la consulta del cliente A prepara una sentencia en el backend 1, y la consulta B intenta ejecutar esa sentencia en el backend 2, la ejecución de la sentencia fallará porque el backend 2 no tiene conocimiento de ella."
  - question: "¿Cómo afecta el modelo de procesos de PHP-FPM a las conexiones de base de datos en comparación con Node.js o Go?"
    answer: "PHP-FPM ejecuta un modelo de proceso por solicitud, donde cada proceso hijo maneja una solicitud a la vez y normalmente cierra los recursos al final de la solicitud. En sistemas de alto tráfico, esto conduce a una 'tormenta de conexiones' (negociaciones TCP y autenticaciones repetidas). Por el contrario, Node.js y Go utilizan entornos de ejecución asíncronos de un solo proceso que mantienen un único pool de larga duración de conexiones de base de datos compartidas entre miles de solicitudes concurrentes."
  - question: "¿El agrupamiento de conexiones (connection pooling) soluciona las consultas lentas a la base de datos?"
    answer: "No. El agrupamiento de conexiones solo resuelve la sobrecarga de establecer conexiones y evita superar los límites de conexión en el servidor de la base de datos. No acelera la ejecución lenta de SQL, ni resuelve la falta de índices, ni reduce la carga de CPU/disco causada por consultas no optimizadas."
---

# Las aplicaciones PHP y el cuello de botella de la base de datos: poolers, proxies y la realidad

En muchas pilas (stacks) de desarrollo, la base de datos es lo suficientemente rápida y las consultas son razonables; sin embargo, producción sigue tropezando con errores de **`too many connections`**, **`remaining connection slots are reserved`** o **paradas misteriosas** justo después de un despliegue. El culpable a menudo no es un SQL lento sino la **aritmética de conexiones**: el modelo de solicitud de PHP crea **ráfagas de conexión + autenticación + TLS**, y la base de datos tiene un **límite estricto** en los backends concurrentes. Los **poolers** y **proxies gestionados** de nivel intermedio existen precisamente para colocar un **grupo pequeño y estable de sesiones del lado del servidor** detrás de una **gran bandada de clientes PHP de vida corta**.

**Guías relacionadas:** [Databases under load: queries & scaling](database-performance-and-scaling) · [Observability and monitoring](observability-monitoring-laravel)

## Índice

* [Por qué PHP amplifica el problema](#why-php)
* [Por qué estás limitado en realidad](#limits)
* [Poolers y proxies de nivel intermedio](#middle-tier)
* [PostgreSQL: PgBouncer en la práctica](#pgbouncer)
* [MySQL y MariaDB: ProxySQL y amigos](#proxysql)
* [Proxies gestionados (RDS Proxy, otros)](#managed)
* [Otros poolers: PgCat, Odyssey, pgpool-II](#other-tools)
* [Notas específicas de Laravel](#laravel)
* [Lo que los poolers *no* solucionan](#not-a-cure)
* [Errores comunes](#common-mistakes)
* [Lista de verificación](#checklist)
* [Cuestionario de autoevaluación](#self-test-quiz)

---

<a id="why-php"></a>
## Por qué PHP amplifica el problema

El clásico **PHP-FPM** ejecuta una solicitud, habla con los servicios, devuelve una respuesta y destruye los recursos específicos de la solicitud. A menos que utilices **conexiones persistentes** (y aceptes sus compensaciones), cada solicitud que toca la base de datos normalmente **abre o verifica** una sesión TCP, se **autentica**, opcionalmente negocia **TLS** y luego ejecuta las consultas.

Bajo carga:

* **`pm.max_children`** en FPM define cuántos procesos PHP pueden ejecutarse **al mismo tiempo** en esa máquina. Si la mayoría de las solicitudes golpean la base de datos, es posible que necesites **hasta esa cantidad** de sesiones de base de datos concurrentes **por servidor de trabajo (worker)**.
* **Los trabajadores de la cola** (`queue:work`, Horizon) son **procesos de larga duración**: cada trabajador concurrente a menudo mantiene **una o más** conexiones abiertas mientras se ejecutan las tareas.
* **La escala horizontal** lo multiplica todo: tres nodos de aplicación con ochenta procesos hijos cada uno son **doscientas cuarenta** sesiones potenciales antes de contar trabajadores, programadores y tareas de CLI únicas.

La base de datos ve **tormentas de conexiones (connection storms)** en los despliegues y picos de tráfico: cientos de negociaciones (handshakes) en segundos. Incluso cuando `max_connections` es lo suficientemente alto, la **memoria por backend** (especialmente en Postgres) y la **CPU para autenticación** se convierten en el límite real.

---

<a id="limits"></a>
## Por qué estás limitado en realidad

* **`max_connections` (Postgres)** / **`max_connections` (MySQL)**: un límite global. Los slots reservados para superusuarios y replicación pueden reducir los disponibles para las aplicaciones.
* **Memoria**: cada backend del servidor lleva búferes y estado; \"simplemente aumentar el límite\" puede provocar un error por falta de memoria (**OOM**) en la instancia.
* **Latencia de conexión**: TLS + verificación de contraseña + LDAP opcional añaden de **milisegundos a decenas de milisegundos** por solicitud si te conectas cada vez.
* **Avalancha de conexiones (Thundering herd)**: después del reinicio, cada proceso PHP puede intentar conectarse **a la vez**, saturando la cola de aceptación o la ruta de autenticación.

> [!NOTE]
> **Aritmética de carga total**
> Regla general: cuenta **todos** los programas que hablan SQL (web, trabajadores, cron, herramientas de administración, BI), no solo HTTP. Cada entorno suma a la huella total de la base de datos.

---

<a id="middle-tier"></a>
## Poolers y proxies de nivel intermedio

Un **pooler** se sitúa **entre** PHP y la base de datos. PHP abre una conexión barata **al pooler**; el pooler mantiene un **pool más pequeño** de conexiones reales a Postgres/MySQL y las **reutiliza** entre muchos clientes.

### Beneficios
* Menos **backends de servidor** y menos **RAM** en el host de la base de datos.
* **Multiplexación**: muchos clientes PHP inactivos no mantienen bloqueada una sesión de servidor inactiva cada uno.
* Comportamiento más suave bajo tráfico **con picos**.

### Costos y advertencias
* Otro **salto (hop)** (latencia, dominio de falla, configuración para asegurar y monitorear).
* **La semántica de la sesión** cambia según el **modo** de agrupamiento (pooling mode); consulta PgBouncer a continuación.
* Aún debes dimensionar el pooler para que no se convierta en el **nuevo** cuello de botella (CPU, descriptores de archivos, inanición del pool).

---

<a id="pgbouncer"></a>
## PostgreSQL: PgBouncer en la práctica

**PgBouncer** es el estándar de facto para el agrupamiento de Postgres en pilas de desarrollo PHP.

**Modos de pool** comunes:

| Modo | Comportamiento | Compatibilidad con PHP / Laravel |
|------|----------------|-----------------------------------|
| **Session** | Una conexión de servidor para toda la sesión de cliente hasta la desconexión. | Compatibilidad más segura: `SET`, `LISTEN`, bloqueos consultivos (advisory locks), tablas temporales, sentencias preparadas funcionan. **Menor ganancia de multiplexación** si los clientes permanecen conectados durante mucho tiempo (trabajadores) o si abres conexiones por solicitud de todos modos. |
| **Transaction** | La conexión del servidor se devuelve al pool **después de cada transacción** (COMMIT/ROLLBACK). | **Fuerte multiplexación** para solicitudes web cortas. Rompe características **de ámbito de sesión**: `SET LOCAL` a través de múltiples idas y vueltas sin una transacción, `LISTEN`, tablas temporarias de larga duración, algunos patrones de **sentencia preparada** a menos que se configure con cuidado. |
| **Statement** | La conexión del servidor se libera después de **cada sentencia**. | Poco común para ORMs; rompe transacciones de múltiples sentencias. No es un objetivo típico de Laravel. |

### Sentencias preparadas y agrupamiento de transacciones (Transaction Pooling)

Muchos controladores preparan las sentencias **por nombre** en la sesión. Cuando la conexión física del servidor cambia debajo de ti, las **sentencias preparadas con nombre** pueden fallar. Mitigaciones utilizadas en producción:

* Prefiere sentencias preparadas **sin nombre** / protocolo de **consulta simple** para ese salto, o
* **Deshabilita** las sentencias preparadas en el lado del servidor para la conexión del pooler (específico del controlador; a menudo `PDO::ATTR_EMULATE_PREPARES` u opciones del framework).

```php
// config/database.php
'connections' => [
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
        // ...
        'options' => [
            PDO::ATTR_EMULATE_PREPARES => true, // Emula sentencias preparadas localmente en PHP
        ],
    ],
],
```

---

<a id="proxysql"></a>
## MySQL y MariaDB: ProxySQL y amigos

**ProxySQL** es un nivel intermedio muy popular para el **protocolo MySQL**: enrutamiento, reglas de consulta, división de lectura/escritura y **agrupamiento de conexiones** con reglas de multiplexación ajustadas por usuario/esquema.

Los equipos lo usan para:
* Limitar las **conexiones backend** mientras muchos procesos hijos de PHP-FPM se conectan a ProxySQL.
* Enrutar **lecturas** a réplicas con reglas explícitas (aún monitoreando el **retraso de replicación**).
* Descartar o reescribir ciertos patrones de consulta (con cuidado: la lógica en el proxy sigue siendo **complejidad operativa**).

**MySQL Router** (InnoDB Cluster) y algunos **balanceadores de carga en la nube** exponen un comportamiento similar al agrupamiento, pero **revisa la documentación**: no todas las capas multiplexan de la misma manera que lo hace ProxySQL.

**MariaDB MaxScale** puede actuar como un enrutador con características de gestión de conexiones según la edición y los módulos; verifica las **licencias** y las capacidades para tu implementación.

---

<a id="managed"></a>
## Proxies gestionados (RDS Proxy, otros)

Los proveedores de la nube ofrecen **proxies de conexión gestionados** frente a RDS, Aurora, Cloud SQL, etc. Normalmente manejan:
* **Agrupamiento** e integración de **autenticación IAM o por tokens**.
* Facilidad de **conmutación por error (failover)** (reconectando backends sin reconectar cada proceso PHP a la vez).

Aún obedecen a la **semántica de la base de datos**: si el producto multiplexa agresivamente, te enfrentas a las mismas limitaciones de **sentencia preparada** y **estado de sesión** que con PgBouncer autohospedado; lee la **matriz de servicio** para tu motor y controlador.

---

<a id="other-tools"></a>
## Otros poolers: PgCat, Odyssey, pgpool-II

* **PgCat** y **Odyssey**: poolers de Postgres con un número creciente de seguidores; compara los **modos de pool**, métricas y particularidades del controlador con PgBouncer antes de cambiar.
* **pgpool-II**: a menudo se despliega para **replicación** y enrutamiento tanto como para agrupamiento; **operativamente más pesado** que PgBouncer si solo necesitas multiplexación.

---

<a id="laravel"></a>
## Notas específicas de Laravel

* **`config/database.php`**: `connections.*.options` y los flags del controlador son donde alineas el comportamiento de **PDO** con tu pooler (por ejemplo, emular sentencias preparadas cuando sea necesario).
* **División de lectura/escritura**: Laravel puede enviar selecciones a hosts de `lectura` (read); en combinación con un pooler, asegúrate de que la semántica **sticky** coincida con tus expectativas (retraso de réplica frente a la configuración `sticky`).
* **Octane / Swoole / FrankenPHP**: los trabajadores de **larga duración** cambian el cálculo: las conexiones persistentes pueden **funcionar bien**, pero debes evitar la **filtración** de estados de conexión entre solicitudes y vigilar el **tiempo de espera de inactividad (idle timeout)** en el servidor y el pooler.
* **Horizon / `queue:work`**: la concurrencia × los trabajadores añade conexiones **sostenidas**; realiza un pool **por trabajador** o usa el **modo transacción** con configuraciones compatibles.
* **Telescope, Nightwatch, barras de depuración** en producción pueden mantener las transacciones abiertas más tiempo de lo que crees; limítalos **solo a entornos que no sean de producción**.

Ejemplo de configuración de entorno usando un pooler:
```env
# .env
# PHP se conecta a PgBouncer en el puerto 6432; PgBouncer se conecta a Postgres en el puerto 5432
DB_HOST=pgbouncer.internal
DB_PORT=6432
DB_DATABASE=app
DB_USERNAME=app_rw
```

---

<a id="not-a-cure"></a>
## Lo que los poolers *not* solucionan

* **Las consultas N+1** y los índices faltantes siguen quemando **CPU y E/S** en el servidor; el agrupamiento solo limita **cuántas sesiones** expresan esa carga.
* **Las transacciones largas** retienen backends de servidor del pool: tus ganancias del **modo transacción** desaparecen si el código mantiene las transacciones abiertas durante llamadas HTTP externas.
* **Bloqueos globales** y **migraciones**: ejecutar `migrate` a través de un pooler saturado puede interactuar mal con los **bloqueos**; algunos equipos usan una ruta de administración **directa** para DDL.

---

<a id="common-mistakes"></a>
## Errores comunes

1. **Transaction Pooling con variables de sesión**: Establecer configuraciones específicas de la sesión (como `SET TIMEZONE` o usar tablas temporales) dentro de un entorno PgBouncer con agrupamiento por transacciones, lo que provoca que estas configuraciones se filtren a otras sesiones de clientes.
2. **Olvidar emular sentencias preparadas**: No configurar `PDO::ATTR_EMULATE_PREPARES => true` cuando se utiliza el agrupamiento por transacciones, lo que genera excepciones de tipo \"prepared statement already exists\" o \"prepared statement not found\".
3. **Escalar los límites del pooler más allá de los límites de la base de datos**: Configurar el `max_client_conn` de PgBouncer y el tamaño del pool del backend con valores mayores que el valor físico `max_connections` de PostgreSQL.
4. **Conexiones persistentes incorrectas con FPM**: Habilitar `PDO::ATTR_PERSISTENT` en servidores web sin gestionar la vida útil de los procesos hijos de FPM, dejando las conexiones inactivas abiertas para siempre.

---

<a id="checklist"></a>
## Lista de verificación

1. **Haz un inventario** de cada tipo de proceso que abre SQL (FPM max children × nodos, trabajadores de Horizon, cron, CLI).
2. Compara los totales con **`max_connections`** y **RAM por conexión** en la base de datos; decide primero sobre **pooler frente a más disciplina en la aplicación**.
3. Elige el **modo de pool** (Postgres) o las **reglas de multiplexación** (MySQL) que coincidan con las capacidades de **ORM + controlador**.
4. Valida **sentencias preparadas** y **características de sesión** (`SET`, tablas temporales, bloqueos consultivos) bajo pruebas de carga.
5. Monitorea el **tiempo de espera del pooler** y las **conexiones activas del servidor**: si la cola del pooler crece, la base de datos o la mezcla de consultas sigue siendo el límite.

---

## Resumen

Los poolers de nivel intermedio son **infraestructura que tú operas** (o compras). Bien utilizados, transforman \"PHP abrió ochocientas conexiones\" en \"Postgres ve sesenta backends ocupados\", que es exactamente la forma para la que se diseñaron la mayoría de las bases de datos OLTP.

---

<a id="self-test-quiz"></a>
## Cuestionario de autoevaluación

### Pregunta 1: ¿Qué sucede si intentas usar bloqueos consultivos (advisory locks) de PostgreSQL a través de PgBouncer ejecutándose en modo transaction pooling?
- A) Los bloqueos funcionan correctamente porque PgBouncer los intercepta.
- B) Los bloqueos pueden bloquear la sesión incorrecta o perderse silenciosamente cuando la conexión cambia de backend.
- C) El analizador de PgBouncer lanza una excepción SQL inmediata.

<details>
<summary><b>Mostrar respuestas</b></summary>

**Respuesta: B**
Los bloqueos consultivos están vinculados a la sesión física del backend. En el modo de transacción, su próxima consulta puede enrutarse a una conexión física diferente, lo que significa que el bloqueo se pierde de su lado mientras permanece bloqueado en el backend original.
</details>

---

### Pregunta 2: ¿Por qué PHP-FPM crea tormentas de conexiones en comparación con los entornos de ejecución de trabajadores persistentes como Go o Node.js?
- A) Los procesos de PHP-FPM no admiten TCP.
- B) PHP-FPM finaliza el estado de la solicitud al final de la ejecución, lo que cierra y vuelve a abrir los manejadores de la base de datos repetidamente.
- C) Node.js y Go usan motores de base de datos personalizados.

<details>
<summary><b>Mostrar respuestas</b></summary>

**Respuesta: B**
Debido a que PHP-FPM tiene un ámbito limitado a la solicitud, las conexiones se negocian y destruyen en cada solicitud a menos que los manejadores persistentes se configuren cuidadosamente.
</details>
