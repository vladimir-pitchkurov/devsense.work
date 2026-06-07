---
title: "PHP on the server: FPM, Swoole, workers & event-loop runtimes | DevSense"
description: "Domine los entornos de ejecución modernos de PHP: compare PHP-FPM, servidores de aplicaciones de larga duración (Swoole, RoadRunner, FrankenPHP) y bucles de eventos asíncronos de ReactPHP/AMPHP."
faq:
  - question: "¿Cuál es la diferencia entre PHP-FPM y Swoole?"
    answer: "PHP-FPM genera un nuevo proceso o reutiliza un estado de proceso limpio para cada solicitud entrante, liberando memoria al terminar. Swoole es un servidor de aplicaciones de larga duración que arranca el código de la aplicación una vez y maneja las solicitudes posteriores en memoria, aumentando enormemente el rendimiento pero persistiendo el estado global."
  - question: "¿Qué es una fuga de memoria (memory leak) en los entornos de ejecución persistentes de PHP?"
    answer: "Dado que los procesos no finalizan después de cada solicitud, las propiedades estáticas, los singletons o las variables globales que acumulan datos crecerán en tamaño indefinidamente, lo que eventualmente hará que el proceso de trabajo supere los límites de memoria y se bloquee."
  - question: "¿Cómo maneja RoadRunner los procesos de trabajo (workers) de PHP?"
    answer: "RoadRunner está escrito en Go y actúa como un equilibrador de carga y administrador de procesos. Se comunica con los procesos de trabajo de PHP persistentes utilizando el protocolo Goridge, manejando HTTP, gRPC y colas de forma externa."
  - question: "¿Por qué las funciones bloqueantes detienen los bucles de eventos de ReactPHP o AMPHP?"
    answer: "ReactPHP y AMPHP son bucles de eventos de un solo hilo. Si llama a una función bloqueante (como sleep() o una consulta de base de datos síncrona), el hilo se detiene por completo, congelando el servidor para todas las demás conexiones concurrentes."
---

# PHP en el Servidor: FPM, Swoole, Workers y Event Loops

Muchos desarrolladores todavía piensan que PHP solo se ejecuta en un modelo de un solo hilo y corta duración de \"compartir nada\" donde las variables desaparecen después de recargar la página. Pero el PHP moderno en producción alberga WebSockets de alta concurrencia, maneja miles de solicitudes por segundo en un solo hilo utilizando workers impulsados por Go y opera bucles de eventos asíncronos. Si implementa un entorno de ejecución de PHP moderno asumiendo el ciclo clásico de solicitud/respuesta, una sola fuga de memoria en una propiedad estática puede colapsar todo su clúster de producción.

Con la aparición de Swoole, RoadRunner, FrankenPHP y ReactPHP, PHP ha salido de los límites de PHP-FPM. Sin embargo, los entornos de ejecución de larga duración traen nuevos peligros, específicamente fugas de memoria, contaminación del estado compartido y llamadas de E/S bloqueantes que detienen el bucle de eventos.

> [!IMPORTANT]
> El PHP moderno ya no está limitado a un solo modelo de ejecución; elegir el entorno de ejecución adecuado requiere comprender el contrato entre las solicitudes de corta duración, los workers persistentes y los bucles de eventos asíncronos.

---

## Índice
* [PHP-FPM (FastCGI Process Manager)](#php-fpm)
* [Servidores de aplicaciones de larga duración (Swoole y Laravel Octane)](#swoole)
* [RoadRunner (Supervisor impulsado por Go)](#roadrunner)
* [FrankenPHP (Modo de trabajo basado en Caddy)](#frankenphp)
* [Pilas de bucle de eventos (ReactPHP y AMPHP)](#event-loop)
* [Fugas de memoria: el asesino silencioso](#memory-leaks)
* [Errores comunes](#common-mistakes)
* [Recetas prácticas](#practical-recipes)
* [🧠 Preguntas de autoevaluación](#self-check)

---

<a id="php-fpm"></a>
## PHP-FPM (FastCGI Process Manager)

PHP-FPM es el administrador de procesos clásico y probado en batalla. Nginx finaliza el tráfico HTTP y reenvía las solicitudes `.php` a los workers de FPM a través de un socket Unix o TCP.

```nginx
# /etc/nginx/sites-available/default
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

* **El contrato**: cada proceso de trabajo maneja una solicitud, vacía la salida, restablece las variables y regresa al grupo.
* **Por qué es importante**: cero riesgo de acumulación de fugas de memoria entre diferentes solicitudes. Si un script falla, el proceso se recicla limpiamente.
* **Limitaciones**: alta sobrecarga de arranque (por ejemplo, cargar Laravel/Symfony desde cero en cada solicitud).

---

<a id="swoole"></a>
## Servidores de aplicaciones de larga duración (Swoole y Laravel Octane)

Swoole es una extensión en C que convierte a PHP en un servidor de red de alta concurrencia mediante el uso de corrutinas.

```php
// app/Servers/HttpServer.php
$server = new Swoole\Http\Server("127.0.0.1", 9501);
$server->on("request", function ($request, $response) {
    $response->header("Content-Type", "text/plain");
    $response->end("Hello World");
});
$server->start();
```

* **El contrato**: el código PHP se carga en memoria **una vez** al iniciar. Las solicitudes posteriores son manejadas por workers residentes en memoria.
* **Por qué es importante**: aumenta el rendimiento en 10 veces al eliminar la sobrecarga de arranque del framework.
* **Limitaciones**: cualquier fuga de memoria dentro de los singletons o las variables estáticas persistirá y crecerá hasta que el proceso se bloquee.

---

<a id="roadrunner"></a>
## RoadRunner (Supervisor impulsado por Go)

RoadRunner es un servidor de aplicaciones PHP, administrador de procesos y equilibrador de carga de alto rendimiento escrito en Go.

```yaml
# .rr.yaml
server:
  command: "php worker.php"
http:
  address: "0.0.0.0:8080"
  pool:
    num_workers: 4
```

* **El contrato**: Go maneja las solicitudes HTTP entrantes, los apretones de manos de WebSocket y los endpoints gRPC, y los organiza en procesos PHP persistentes a través del protocolo binario Goridge.
* **Por qué es importante**: combina la seguridad de concurrencia y la gestión de procesos de Go con la facilidad de desarrollo de PHP.

---

<a id="frankenphp"></a>
## FrankenPHP (Modo de trabajo basado en Caddy)

FrankenPHP es un servidor de aplicaciones PHP moderno construido sobre el servidor web Caddy. Trae un \"modo worker\" que mantiene su aplicación cargada en memoria.

* **Por qué es importante**: implementación sencilla como un único binario. Admite HTTP/1.1, HTTP/2, HTTP/3 y TLS automático listo para usar.

---

<a id="event-loop"></a>
## Pilas de bucle de eventos (ReactPHP y AMPHP)

A diferencia de Swoole, que se basa en una extensión de C, ReactPHP y AMPHP implementan bucles de eventos PHP puros utilizando multitarea cooperativa.

```php
// app/Servers/ReactServer.php
require __DIR__ . '/vendor/autoload.php';

$loop = React\EventLoop\Loop::get();
$loop->addPeriodicTimer(1.0, function () {
    echo "Tick\n";
});
$loop->run();
```

* **Por qué es importante**: perfecto para microservicios personalizados vinculados a E/S, manejadores de WebSocket y servidores proxy.
* **Limitaciones**: no puede ejecutar código bloqueante (como operaciones de base de datos PDO estándar o `file_get_contents`). Todos los controladores de bases de datos y clientes HTTP deben ser no bloqueantes.

---

<a id="memory-leaks"></a>
## Fugas de memoria: el asesino silencioso

En los entornos de ejecución persistentes (Swoole, RoadRunner, modo de trabajo FrankenPHP, demonios CLI), la memoria no se libera automáticamente después de una solicitud.

```php
// app/Services/DataLeak.php
class DataLeak
{
    private static array $cache = [];

    public function cacheRequest(array $data)
    {
        // ❌ ¡Fuga de memoria! Este array crece indefinidamente con cada solicitud
        self::$cache[] = $data; 
    }
}
```

> [!NOTE]
> Para evitar fugas de memoria, debe limitar los cachés en memoria utilizando la evicción LRU, evitar propiedades estáticas para el estado específico de la solicitud y utilizar comprobaciones de monitoreo de memoria como `memory_get_usage(true)`.

---

<a id="common-mistakes"></a>
## ⚠️ Errores comunes

### 1. Bloquear el bucle de eventos en ReactPHP / AMPHP
Llamar a una función bloqueante síncrona detiene el bucle de eventos, congelando todo el servidor para todos los demás usuarios.

```php
// app/Http/Handler.php
// ❌ Peligroso: detiene la ejecución de todas las solicitudes concurrentes
$data = file_get_contents("http://external-api.com/data");

// ✅ Enfoque correcto
// Utilice clientes HTTP asíncronos:
$browser = new React\Http\Browser();
$browser->get("http://external-api.com/data")->then(function ($response) {
    // Procesar respuesta de forma asíncrona
});
```

### 2. Modificar propiedades de clase estáticas para solicitudes de usuario
Almacenar los detalles de la sesión o la autenticación del usuario en propiedades estáticas dará como resultado la filtración de datos del usuario a otras solicitudes concurrentes.

```php
// app/Services/AuthService.php
// ❌ Peligroso: ¡El usuario 2 podría ver la identidad del usuario 1!
class AuthService
{
    public static ?User $currentUser = null;
}
```

---

<a id="practical-recipes"></a>
## Recetas prácticas

### Reciclaje seguro de workers
Para combatir las fugas de memoria en producción, configure su administrador de procesos para reiniciar los workers después de que hayan manejado un número específico de solicitudes o hayan alcanzado un cierto umbral de memoria.

```ini
# /etc/php/8.3/fpm/pool.d/www.conf (PHP-FPM)
; Reciclar workers después de 500 solicitudes para limpiar fugas menores
pm.max_requests = 500
```

```yaml
# .rr.yaml (RoadRunner)
http:
  pool:
    # Destruir workers cuando superen los 100 MB de RAM o realicen 1000 tareas
    max_jobs: 1000
    allocate_timeout: 60s
    destroy_timeout: 60s
    supervisor:
      watch_interval: 1s
      max_worker_memory: 100 # MB
```

---

## 🧠 Preguntas de autoevaluación

1. **¿Verdadero o falso?** In PHP-FPM, las variables estáticas filtran memoria entre solicitudes HTTP separadas.
2. ¿Qué sucede si ejecuta `sleep(5)` dentro de un manejador de solicitudes de ReactPHP?
3. ¿Cómo logra el modo de trabajo de FrankenPHP mejoras de rendimiento en comparación con PHP-FPM?
4. ¿Cuál es la función principal de `pm.max_requests` en PHP-FPM?

<details>
<summary><b>Mostrar respuestas</b></summary>

1. **Falso.** En PHP-FPM, toda la memoria del proceso se borra y se restablece entre solicitudes (o el proceso se recicla), lo que significa que las variables estáticas no persisten entre las solicitudes.
2. Bloquea el bucle de eventos de un solo hilo durante 5 segundos. Durante este tiempo, el servidor no puede aceptar ni responder a ninguna otra conexión.
3. Arranca la aplicación PHP y las bibliotecas del framework en la memoria una vez, evitando la carga del sistema de archivos de arranque y las fases del compilador en las solicitudes posteriores.
4. Reinicia el proceso de trabajo de PHP-FPM después de haber servido a un número configurado de solicitudes, evitando que las fugas de memoria en las extensiones o el código de terceros consuman la memoria del servidor.
</details>
