---
title: "Laravel Sail: resolución de problemas de WSL2, permisos, puertos, reconstrucciones, OPcache y Vite | DevSense"
description: "Resuelve problemas comunes con Laravel Sail: sincronización de archivos de WSL2, permisos de UID/GID y almacenamiento, conflictos de puertos FORWARD_*, capas de Docker obsoletas, OPcache y Xdebug en desarrollo, npm/Vite en host frente a contenedor, y reinicios seguros de volúmenes."
faq:
  - question: "¿Por qué la compilación de assets de Vite en Windows con WSL2 es extremadamente lenta?"
    answer: "Esto generalmente ocurre porque los archivos de tu proyecto se guardan en el sistema de archivos de Windows (/mnt/c/...). Para solucionarlo, mueve la carpeta de tu proyecto al sistema de archivos nativo de Linux (por ejemplo, ~/projects/) dentro de WSL2."
  - question: "¿Cómo soluciono los errores de permiso denegado (permission denied) en el directorio storage?"
    answer: "Ejecuta 'sail artisan cache:clear' y configura las variables de entorno WWWUSER y WWWGROUP en tu archivo .env para que coincidan con el ID de usuario de tu máquina host, de modo que los archivos generados por herramientas CLI en contenedores conserven los derechos de propiedad correctos."
  - question: "¿Por qué no se actualizan mis cambios de código en el contenedor en ejecución?"
    answer: "Si es un cambio web, OPcache podría estar activo en la configuración de PHP. Si es un worker de colas, ha guardado en caché el arranque (bootstrap). Si es un cambio de configuración del Dockerfile, debes ejecutar 'sail build --no-cache' y reiniciar los contenedores."
  - question: "¿Cómo puedo comprobar qué extensiones están cargadas en mi entorno PHP de Sail?"
    answer: "Ejecuta el comando 'sail exec laravel.test php -m' para listar todos los módulos PHP activos que se ejecutan dentro del contenedor, ya que esto diferirá del entorno de tu máquina host."
---

# Sail: resolución de problemas y rendimiento

Tu aplicación funcionaba perfectamente ayer, pero hoy `sail up` arroja un conflicto de asignación de puertos, los assets no se compilan mediante Vite y las escrituras en la base de datos fallan con errores de permisos. Cuando Sail falla, rara vez es un error de Laravel; casi siempre es un punto de fricción entre el entorno virtualizado del contenedor y el sistema de archivos, la red o el estado de los procesos de tu máquina host.

Las capas de virtualización (especialmente WSL2 en Windows) y los espacios de nombres de red de los contenedores introducen particularidades únicas. Comprender cómo diagnosticar y evitar estos obstáculos es fundamental para mantener un flujo de desarrollo rápido y fluido.

En esta guía, recopilamos los síntomas más comunes de Sail, explicamos por qué ocurren y proporcionamos comandos directos para solucionarlos.

**Navegación:** [Todos los herramientas](../) · [Aperçu de Sail](sail#what-sail-is) · [Bases de datos](sail-databases#networking) · [Colas](sail-queues#connections) · [Env y despliegue](sail-env-deploy#forward-ports)

## Índice

* [WSL2, Docker Desktop y sincronización de archivos](#wsl-filesync)
* [Permisos, `storage` y `vendor`](#permissions)
* [Puerto ya asignado / `FORWARD_*`](#ports)
* [Discrepancias entre host y contenedor](#host-vs-container)
* [Código obsoleto: reconstrucciones y capas](#rebuild)
* [OPcache y autoload en desarrollo local](#opcache)
* [Xdebug se siente lento](#xdebug)
* [Vite, npm y Node dentro frente a fuera de Sail](#frontend)
* [Registros (logs) y diagnósticos rápidos](#logs)
* [Cuándo restablecer los volúmenes (pérdida de datos)](#volumes)
* [Errores comunes](#common-mistakes)
* [Preguntas de autoevaluación](#self-check)

---

<a id="wsl-filesync"></a>
## WSL2, Docker Desktop y sincronización de archivos

En Windows + WSL2, montar archivos desde la partición de Windows (`/mnt/c/...`) en contenedores Linux provoca cuellos de botella masivos de E/S de disco. Los escuchadores de archivos del reemplazo en caliente de módulos (HMR) de Vite y Webpack también fallarán al no recibir notificaciones de cambios.

Conserva tus carpetas de proyectos por completo **dentro del sistema de archivos nativo de WSL** (por ejemplo, `~/projects/...`). Ábrelas en VS Code utilizando la **extensión WSL** para garantizar que los eventos de sincronización de archivos y las compilaciones tarden milisegundos en lugar de segundos.

---

<a id="permissions"></a>
## Permisos, `storage` y `vendor`

Laravel requiere permisos de escritura en `storage/` y `bootstrap/cache/`. Si Docker se ejecuta como usuario root, los archivos creados dentro de los contenedores se volverán de solo lectura en tu máquina host.

Comprueba los permisos dentro del contenedor:

```bash
# Terminal
sail exec laravel.test ls -la storage bootstrap/cache
```

> [!NOTE]
> **Mapeo de UID y GID**
> En hosts Linux, haz coincidir tus UID y GID del host definiendo las variables `WWWUSER` y `WWWGROUP` en tu entorno:
> ```dotenv
> # .env
> WWWUSER=1000
> WWWGROUP=1000
> ```
> Reinicia Sail después de aplicar estos cambios.

---

<a id="ports"></a>
## Puerto ya asignado / `FORWARD_*`

Si otro servicio (como una base de datos local u otra instancia de Sail en ejecución) ya está usando puertos como `3306` o `80`, verás un error de dirección ya en uso (`address already in use`).

Define puertos alternativos en tu archivo `.env`:

```dotenv
# .env
APP_PORT=8081
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
```

Ejecuta `sail down && sail up -d` para aplicar los cambios.

---

<a id="host-vs-container"></a>
## Discrepancias entre host y contenedor

Un error común es ejecutar comandos como `php artisan migrate` o `composer install` directamente en tu máquina host. Si tu host usa una versión diferente de PHP o carece de extensiones (como `redis` o `mongodb`), el comando fallará o corromperá tu archivo lock.

- **Host (Mal):** `composer install`
- **Sail (Bien):** `sail composer install`

---

<a id="rebuild"></a>
## Código obsoleto: reconstrucciones y capas

Cuando modificas dependencias, entornos de ejecución de PHP o paquetes personalizados en tus Dockerfiles publicados, Docker no reconstruirá automáticamente tus imágenes.

Fuerza una reconstrucción completa de la imagen sin utilizar capas en caché:

```bash
# Terminal
sail build --no-cache
sail up -d
```

---

<a id="opcache"></a>
## OPcache y autoload en desarrollo local

Si los cambios en el código no se reflejan en tu navegador, comprueba si OPcache está activo en el contenedor. En desarrollo, OPcache debe validar las marcas de tiempo en cada solicitud.

Asegúrate de configurar lo siguiente en el archivo `php.ini` de tu contenedor:

```ini
# php.ini
opcache.validate_timestamps=1
opcache.revalidate_freq=0
```

Si añades nuevas clases y obtienes errores de espacio de nombres, ejecuta `sail composer dump-autoload`.

---

<a id="xdebug"></a>
## Xdebug se siente lento

Xdebug añade una sobrecarga de rendimiento a cada solicitud. Desactívalo cuando no estés depurando activamente configurando lo siguiente:

```dotenv
# .env
SAIL_XDEBUG_MODE=off
```

Reinicia Sail con `sail down && sail up -d`.

---

<a id="frontend"></a>
## Vite, npm y Node dentro frente a fuera de Sail

Puedes compilar los assets del frontend ya sea en la máquina host o dentro de Sail.

- **Compilación en el host (La más rápida):** Ejecuta `npm run dev` en tu terminal local.
- **Compilación en contenedor (Cero configuración):** Ejecuta `sail npm run dev`.

Asegúrate de que tu `APP_URL` y los puertos de Vite coincidan, de lo contrario experimentarás fallos de conexión HMR en tu consola de desarrollador.

---

<a id="logs"></a>
## Registros (logs) y diagnósticos rápidos

Ejecuta estos comandos para inspeccionar la salud de los contenedores y el estado de PHP:

```bash
# Terminal
sail logs -f laravel.test
docker compose ps
sail exec laravel.test php -v
sail exec laravel.test php -m
```

---

<a id="volumes"></a>
## Cuándo restablecer los volúmenes (pérdida de datos)

Si tu base de datos local se corrompe o si las actualizaciones del esquema se bloquean, restablece el volumen. Advertencia: **`sail down -v` elimina todas las tablas de bases de datos y claves de caché de Redis.**

Para restablecer de forma segura un solo volumen de base de datos:
1. Lista los volúmenes: `docker volume ls`
2. Elimina el volumen objetivo: `docker volume rm <nombre_del_volumen>`

---

## ⚠️ Errores comunes

**1. Montar archivos a través de los límites de las particiones de Windows**
Guardar la carpeta de tu proyecto en `C:/Users/...` y usar WSL2 para ejecutar Sail. Esto reduce la velocidad de E/S hasta 10 veces.
*Solución:* Mueve los archivos al directorio nativo de Linux, como `\\wsl$\Ubuntu\home\ubuntu\projects\`.

**2. Extensiones de PHP faltantes en contenedores**
Ejecutar una migración de base de datos que usa PostgreSQL, cuando el entorno de ejecución de tu contenedor no ha sido compilado con el controlador PostgreSQL.
*Solución:* Comprueba los módulos activos usando `sail exec laravel.test php -m`. Publica los Dockerfiles y reconstruye si falta alguna extensión.

**3. Colisión de puertos en localhost**
Ejecutar dos aplicaciones de Sail al mismo tiempo sin modificar la variable `APP_PORT` en alguna de las configuraciones `.env`.
*Solución:* Ajusta `APP_PORT` y las variables de puerto `FORWARD_*` en el archivo `.env` del segundo proyecto.

---

## 🧠 Preguntas de autoevaluación

1. **¿Por qué los archivos de proyecto en WSL2 no deben guardarse en la ruta `/mnt/c/`?**
2. **¿Qué comando debes ejecutar para reconstruir tus imágenes de Sail después de modificar un Dockerfile publicado?**
3. **¿Verdadero o Falso? Ejecutar `composer install` en tu terminal host es siempre seguro si tienes instalada la misma versión de PHP que Sail.**
4. **¿Cómo desactivas Xdebug en Sail cuando no lo estás utilizando para evitar ralentizaciones?**

<details>
<summary><b>Mostrar respuestas</b></summary>

1. Acceder a archivos cruzando el límite del sistema de archivos de Windows (`/mnt/c/`) introduce pesadas capas de traducción de virtualización, lo que ralentiza las operaciones de disco e interrumpe los escuchadores de cambios de archivos.
2. Ejecuta `sail build --no-cache` seguido de `sail up -d`.
3. **Falso.** Aunque coincida la versión menor de PHP, es posible que tu máquina host carezca de las bibliotecas de compilación necesarias o de las extensiones PHP presentes en el contenedor. Ejecuta siempre los comandos a través de Sail.
4. Establece `SAIL_XDEBUG_MODE=off` en tu archivo `.env` y reinicia los contenedores de Sail.
</details>
