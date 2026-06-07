---
title: "Laravel Sail: workers de colas, Horizon, Redis, RabbitMQ y trabajos fallidos | DevSense"
description: "Ejecuta colas de Laravel en Sail: sync frente a redis frente a database, queue:work y Horizon localmente, RabbitMQ con drivers de la comunidad, failed_jobs, queue:restart y diferencias en producción."
faq:
  - question: "¿Por qué no se ejecutan mis tareas en segundo plano en Sail?"
    answer: "A diferencia del driver 'sync', los drivers como 'database' o 'redis' requieren un proceso worker activo. Debes iniciar un contenedor worker o ejecutar 'sail artisan queue:work' en tu terminal."
  - question: "¿Por qué mis cambios en las clases Job no surten efecto en la cola?"
    answer: "Los workers de colas de Laravel cargan el código de la aplicación en memoria una sola vez. Cuando realices modificaciones en el código, debes ejecutar 'sail artisan queue:restart' para obligar a los workers a recargarse."
  - question: "¿Cómo ejecuto el programador en Laravel Sail sin una tarea cron en el host?"
    answer: "Puedes ejecutar 'sail run --rm laravel.test php artisan schedule:work' en una ventana de terminal separada, lo que consulta y ejecuta tareas cada minuto localmente."
  - question: "¿En qué se diferencia la gestión de colas en Sail con respecto a producción?"
    answer: "En Sail, los workers se ejecutan en primer plano en tu terminal y se detienen cuando la cierras. En producción, los gestores de procesos como Supervisor o Kubernetes mantienen a los workers ejecutándose de forma persistente."
---

# Sail: colas y workers

Activas una tarea en segundo plano en tu aplicación Laravel, pero no ocurre nada. La página del trabajo dice \"Pendiente\" (Pending) y los registros de tu base de datos permanecen inalterados. Entonces te das cuenta: en un entorno de contenedores, las tareas en segundo plano no se ejecutan solas. Sin un proceso worker activo escuchando dentro de la red del contenedor Sail, tus colas son solo archivos muertos o listas silenciosas de Redis.

Ejecutar workers de colas y programadores de tareas en tu sistema operativo host fallará porque carecen de acceso a las bases de datos en contenedores, a las variables de entorno y a las extensiones PHP de Sail. Para reflejar la ejecución de producción, debes ejecutar y administrar tus entornos de ejecución de workers dentro de la red de Compose.

In esta guía, te mostramos cómo configurar conexiones de colas, ejecutar workers y Horizon, manejar fallos de trabajos y gestionar actualizaciones de código sin que persistan cachés obsoletas de los workers.

**Navegación:** [Todos los herramientas](../) · [Aperçu de Sail](sail#what-sail-is) · [Bases de datos](sail-databases#networking) · [Env y despliegue](sail-env-deploy#forward-ports) · [Resolución de problemas](sail-troubleshooting#wsl-filesync)

## Índice

* [Conexiones de un vistazo](#connections)
* [Ejecutar `queue:work` en Sail](#queue-work)
* [Horizon (Redis)](#horizon)
* [RabbitMQ y paquetes AMQP](#rabbitmq)
* [Trabajos fallidos y reintentos](#failed-jobs)
* [Cambios de código y `queue:restart`](#restart)
* [Programador (`schedule:run`)](#scheduler)
* [Contraste con producción](#production)
* [Errores comunes](#common-mistakes)
* [Preguntas de autoevaluación](#self-check)

---

<a id="connections"></a>
## Conexiones de un vistazo

Selecciona tu driver de cola en el archivo `.env`:

```dotenv
# .env
QUEUE_CONNECTION=redis
```

| Driver | Caso de uso en Sail |
|--------|-------------------|
| **`sync`** | Depurar la lógica de los trabajos de forma síncrona sin iniciar un worker. |
| **`database`** | Cola asíncrona simple; requiere ejecutar `php artisan queue:table` y un worker. |
| **`redis`** | Driver estándar de producción; se integra con Horizon; requiere un servicio Redis. |
| **`rabbitmq`** | Intercambios AMQP; requiere un contenedor sidecar de RabbitMQ y un paquete de la comunidad. |

> [!NOTE]
> **Caché de configuración**
> Si modificas `QUEUE_CONNECTION` o cualquier variable de entorno de cola, ejecuta `sail artisan config:clear` o `sail artisan config:cache` para aplicar los cambios a tus contenedores en ejecución.

---

<a id="queue-work"></a>
## Ejecutar `queue:work` en Sail

Para procesar trabajos, abre una ventana de terminal dedicada y ejecuta:

```bash
# Terminal
sail artisan queue:work
```

Para controles avanzados, especifica conexiones y opciones particulares:

```bash
# Terminal
sail artisan queue:work redis --queue=high,default --tries=3 --timeout=90
```

Para ejecutar un solo trabajo y salir (útil para depuración):

```bash
# Terminal
sail artisan queue:work --once
```

---

<a id="horizon"></a>
## Horizon (Redis)

Laravel Horizon proporciona un panel de control estético y configuración basada en código para las colas de Redis.

1. Instala Horizon a través de Composer:
   ```bash
   sail composer require laravel/horizon
   sail artisan horizon:install
   ```
2. Inicia Horizon dentro de tu contenedor:

```bash
# Terminal
sail artisan horizon
```

Accede al panel de control en `http://localhost/horizon`. En desarrollo, Horizon se detiene cuando presionas `Ctrl+C`.

---

<a id="rabbitmq"></a>
## RabbitMQ y paquetes AMQP

Laravel no incluye un driver nativo de RabbitMQ. Para usar AMQP:

1. Añade el servicio RabbitMQ a tu `docker-compose.yml` ([ver recetas de bases de datos](sail-databases#rabbitmq-sidecar)).
2. Instala un paquete mantenido por la comunidad (por ejemplo, `vyuldashev/laravel-queue-rabbitmq`).
3. Configura los detalles de tu conexión:

```dotenv
# .env
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
```

4. Empieza a procesar:
   ```bash
   sail artisan queue:work rabbitmq
   ```

---

<a id="failed-jobs"></a>
## Trabajos fallidos y reintentos

Cuando una tarea en segundo plano lanza una excepción, Laravel la registra en la tabla `failed_jobs`.

- Listar trabajos fallidos:
  ```bash
  sail artisan queue:failed
  ```
- Reintentar un trabajo específico:
  ```bash
  sail artisan queue:retry 5
  ```
- Eliminar todos los trabajos fallidos:
  ```bash
  sail artisan queue:flush
  ```

---

<a id="restart"></a>
## Cambios de código y `queue:restart`

Los workers de Laravel cargan la aplicación una sola vez y procesan los trabajos en un bucle continuo. Si editas un archivo de Job, el worker en ejecución continuará ejecutando el código antiguo almacenado en memoria.

Cada vez que guardes cambios en tu código base, reinicia los workers:

```bash
# Terminal
sail artisan queue:restart
```

> [!NOTE]
> **Recarga automática en desarrollo**
> Para evitar escribir `queue:restart` constantemente durante el desarrollo local rápido, puedes ejecutar el worker con los flags `--max-jobs=1` o `--once`.

---

<a id="scheduler"></a>
## Programador (`schedule:run`)

Sail no ejecuta un demonio cron del sistema de forma automática. Para ejecutar tareas programadas en desarrollo, tienes dos opciones:

1. Ejecutar el programador manualmente:
   ```bash
   sail artisan schedule:run
   ```
2. Ejecutar un bucle de sondeo continuo en segundo plano:

```bash
# Terminal
sail run --rm laravel.test php artisan schedule:work
```

---

<a id="production"></a>
## Contraste con producción

| Tema | Sail (Desarrollo Local) | Entorno de Producción |
|-------|------------------|------------------------|
| **Administrador de Workers** | Terminal en primer plano manual | Demonio **Supervisor** o systemd |
| **Escalabilidad** | Un solo contenedor Docker | Múltiples contenedores / Autoescalado en Kubernetes |
| **Tolerancia a fallos** | Reinicio manual de contenedores | Nodos redundantes y clustering |

---

## ⚠️ Errores comunes

**1. Modificar el código del Job sin reiniciar el worker**
Corriges un fallo en tu clase Job, pero el ejecutor de la cola sigue lanzando el mismo error.
*Solución:* El worker ha guardado en caché los archivos PHP antiguos. Ejecuta `sail artisan queue:restart`.

**2. Ejecutar `php artisan queue:work` en la terminal de tu host**
Ejecutar el comando directamente en tu máquina host fallará debido a la falta de controladores de base de datos o la imposibilidad de resolver nombres de host internos como `redis` o `mysql`.
*Solución:* Antepone siempre `sail` para ejecutarlo en el entorno del contenedor: `sail artisan queue:work`.

**3. Malentendido sobre el driver de cola `sync`**
Utilizar `QUEUE_CONNECTION=sync` ejecuta los trabajos inmediatamente dentro del ciclo de vida de la solicitud HTTP. Esto oculta problemas de concurrencia, tiempos de espera y errores de serialización que solo aparecerán en producción.
*Solución:* Utiliza `database` o `redis` localmente para igualar el comportamiento de producción.

---

## 🧠 Preguntas de autoevaluación

1. **¿Por qué un queue worker en ejecución no aplica los nuevos cambios de código que acabas de guardar en el disco?**
2. **¿Qué nombre de host debes escribir en tu `.env` para RabbitMQ al ejecutarlo dentro de Sail?**
3. **¿Cómo mantienes el programador de tareas de Laravel ejecutándose continuamente sin configurar tareas cron en el host?**
4. **¿Qué comando de artisan debes ejecutar para volver a encolar un trabajo que falló durante la ejecución?**

<details>
<summary><b>Mostrar respuestas</b></summary>

1. Los workers PHP CLI cargan todo el framework de la aplicación una vez y lo mantienen en memoria. No vuelven a leer los archivos del disco para los trabajos siguientes. Debes ejecutar `sail artisan queue:restart`.
2. Debes usar el nombre del servicio de Compose, que es `rabbitmq`.
3. Ejecuta `sail run --rm laravel.test php artisan schedule:work`, lo cual inicia un proceso de larga duración que ejecuta el programador cada minuto.
4. Ejecuta `sail artisan queue:retry {id}` donde `{id}` es el identificador de la tabla `failed_jobs` o `all` para reintentar todos los trabajos fallidos.
</details>
