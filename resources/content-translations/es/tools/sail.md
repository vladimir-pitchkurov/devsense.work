---
title: "Laravel Sail: stack local de Docker, versiones de PHP, Redis, Postgres, colas y notas de despliegue | DevSense"
description: "Guía práctica de Laravel Sail: cambiar la versión de PHP, añadir Redis o RabbitMQ, cambiar MySQL a PostgreSQL, notas sobre MongoDB, workers de colas en contenedores, separación de entornos y diferencias entre Sail y servidores de dev/staging/producción."
faq:
  - question: "¿Qué es Laravel Sail?"
    answer: "Laravel Sail es una interfaz de línea de comandos ligera para interactuar con el entorno de desarrollo Docker por defecto de Laravel, basado en Docker Compose."
  - question: "¿Cómo cambio la versión de PHP en Laravel Sail?"
    answer: "Cambia el argumento de build PHP_VERSION en tu archivo docker-compose.yml bajo el servicio laravel.test, luego reconstruye utilizando sail build --no-cache."
  - question: "¿Por qué obtengo errores de conexión rechazada (connection refused) a Redis o MySQL?"
    answer: "Porque estás intentando conectarte a través de 127.0.0.1. Dentro de la red del contenedor de Sail, debes usar el nombre del servicio (por ejemplo, mysql o redis) como host."
  - question: "¿Puedo ejecutar Laravel Sail en producción?"
    answer: "No, Laravel Sail está estrictamente diseñado para desarrollo local. Para producción, debes compilar imágenes Docker optimizadas y desplegarlas utilizando una orquestación adecuada como Kubernetes, ECS o Docker Swarm."
---

# Laravel Sail: La guía definitiva de stack local

Laravel Sail es un excelente wrapper de **Docker Compose** diseñado para levantar un entorno de desarrollo completo (PHP-FPM, MySQL, Redis, Meilisearch, Mailpit, etc.) sin gestionar configuraciones de servidores en tu máquina host.

Sin embargo, Sail **no** es un entorno de producción. Comprender el límite entre el Sail local y un despliegue de producción real es clave para evitar el temido síndrome de \"funciona en mi máquina\". ¡Dominemos Sail juntos!

---

## Índice
* [Modelo mental y prerrequisitos](#mental-model)
* [Atajos de CLI esenciales](#shortcuts)
* [Cambiar versiones de PHP](#php-version)
* [Personalización de servicios (Redis, RabbitMQ, PostgreSQL)](#customizing-services)
* [Depuración con Mailpit y Xdebug](#debugging)
* [Optimización de rendimiento de WSL2](#performance)
* [Comparación de Sail (Local) frente a Producción](#local-vs-deploy)
* [🧠 Preguntas de autoevaluación](#self-check)

---

<a id="mental-model"></a>
## Modelo mental y prerrequisitos

Sail no instala PHP ni Node en tu máquina host. En su lugar, ejecuta comandos **dentro de entornos de ejecución en contenedores**.

Si estás en Windows, **DEBES** ejecutar Docker dentro de **WSL2** (Subsistema de Windows para Linux) y mantener la carpeta de tu proyecto dentro del sistema de archivos de Linux (por ejemplo, `~/Development/my-app`), no en la partición `C:\` de Windows. Trabajar a través del límite de montaje Windows-Linux provoca cuellos de botella extremos en las E/S del disco.

---

<a id="shortcuts"></a>
## Atajos de CLI esenciales

¿Cansado de escribir `./vendor/bin/sail` cada vez? ¡Añade un alias de shell!

```bash
# ~/.zshrc o ~/.bashrc
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Ahora puedes ejecutar comandos diarios de forma eficiente:
- `sail up -d` — Inicia todos los servicios en segundo plano.
- `sail down` — Detiene los servicios.
- `sail artisan migrate` — Ejecuta las migraciones de la base de datos.
- `sail npm run dev` — Ejecuta los assets de Vite localmente.

---

<a id="php-version"></a>
## Cambiar versiones de PHP

Para actualizar o degradar tu versión de PHP en Sail, debes modificar tu configuración de Compose.

```yaml
# docker-compose.yml
services:
    laravel.test:
        build:
            context: ./vendor/laravel/sail/runtimes/8.4
            dockerfile: Dockerfile
            args:
                WWWGROUP: '${WWWGROUP}'
                # Cambia este argumento de compilación a la versión menor deseada:
                PHP_VERSION: '8.4'
```

Después de modificar el archivo, reconstruye las capas del contenedor sin usar la caché:

```bash
# Terminal
sail build --no-cache
sail up -d
```

---

<a id="customizing-services"></a>
## Personalización de servicios

### 1. Conexión a Redis
Si omitiste Redis durante la instalación, puedes añadirlo editando tu configuración de Compose:

```yaml
# docker-compose.yml
services:
    redis:
        image: 'redis:alpine'
        ports:
            - '${FORWARD_REDIS_PORT:-6379}:6379'
        volumes:
            - 'sail-redis:/data'
        networks:
            - sail
```

Asegúrate de que tu archivo de entorno coincida con esto:

```dotenv
# .env
REDIS_HOST=redis
REDIS_PORT=6379
```

> [!NOTE]
> **¿Sabías que?**
> Dentro de la red de Sail, los contenedores se comunican entre sí utilizando sus nombres de servicio (como `redis` o `mysql`) como nombres de host. ¡Usar `127.0.0.1` fallará porque apunta al propio contenedor de la aplicación!

### 2. Cambiar de MySQL a PostgreSQL
Para cambiar los motores de bases de datos, reemplaza el bloque `mysql` por `pgsql` en tu `docker-compose.yml`:

```yaml
# docker-compose.yml
services:
    pgsql:
        image: 'postgres:15-alpine'
        ports:
            - '${FORWARD_DB_PORT:-5432}:5432'
        environment:
            POSTGRES_DB: '${DB_DATABASE}'
            POSTGRES_USER: '${DB_USERNAME}'
            POSTGRES_PASSWORD: '${DB_PASSWORD}'
        volumes:
            - 'sail-pgsql:/var/lib/postgresql/data'
        networks:
            - sail
```

Y actualiza tu `.env`:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
```

---

<a id="debugging"></a>
## Depuración con Mailpit y Xdebug

### Mailpit (Servidor de correo falso)
Sail enruta automáticamente el correo saliente a Mailpit. Intercepta los correos electrónicos para que no envíes accidentalmente mensajes de prueba a clientes reales. Configura el SMTP en el `.env`:

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```
Puedes ver los correos electrónicos interceptados abriendo la interfaz de usuario web en `http://localhost:8025`.

### Xdebug
Para habilitar la depuración paso a paso, simplemente establece el modo Xdebug en tu entorno:

```dotenv
# .env
SAIL_XDEBUG_MODE=develop,debug
```
Reinicia Sail (`sail down && sail up -d`) para aplicar la configuración.

---

<a id="local-vs-deploy"></a>
## Comparación de Sail (Local) frente a Producción

| Característica | Laravel Sail (Dev Local) | Entorno de Producción |
|----------------|--------------------------|-----------------------|
| **Servidor HTTP** | Servidor interno de PHP | Nginx / Apache + PHP-FPM, u Octane |
| **Colas** | Se ejecuta mediante terminal `sail artisan queue:work` | Gestionado a través de Supervisor o Systemd |
| **SSL / HTTPS** | HTTP plano en localhost | TLS terminado en Nginx, Cloudflare o balanceador de carga |
| **Caché y Sesiones** | Almacenamiento de archivos o contenedor Redis local | Redis en clúster o Memcached gestionado |
| **Base de datos** | Contenedor efímero | BD gestionada (RDS, Cloud SQL) con respaldos |

---

## ⚠️ Errores comunes

**1. Conectarse a `127.0.0.1` para Redis/Base de datos dentro de Sail**
Si tu `DB_HOST` está configurado como `127.0.0.1` en tu `.env`, tu aplicación no podrá encontrar el contenedor de la base de datos. Utiliza el nombre del servicio (`mysql` o `pgsql`) en su lugar.

**2. Ejecutar comandos en el host en lugar del contenedor**
Ejecutar `composer install` o `php artisan migrate` en tu terminal local puede provocar incompatibilidades de versiones o errores por falta de extensiones PHP. Ejecútalos siempre a través de Sail:
```bash
# Terminal
sail composer install
sail artisan migrate
```

---

<a id="self-check"></a>
## 🧠 Preguntas de autoevaluación

1. **¿Por qué es importante utilizar rutas del sistema de archivos de WSL2 (como `~/Development`) en lugar de montar directamente desde particiones de Windows (`/mnt/c/...`)?**
2. **¿Verdadero o Falso?** Para habilitar PostgreSQL, debes descargar y compilar manualmente el controlador `pdo_pgsql` dentro del contenedor Sail.
3. **¿Qué nombre de host debes usar en `.env` para enviar correos electrónicos a través de Mailpit dentro de Sail?**
4. **¿Cómo se reinicia un queue worker en Sail para que cargue los cambios de código actualizados?**

<details>
<summary><b>Mostrar respuestas</b></summary>

1. Montar desde unidades de Windows (`C:\`) a un contenedor Docker de Linux introduce capas severas de traducción y virtualización, lo que ralentiza las operaciones de archivos y la compilación de assets (Vite/Mix) hasta 10 veces.
2. **Falso.** Los entornos de ejecución de PHP precompilados de Sail ya incluyen las extensiones estándar, incluida `pdo_pgsql`. Solo necesitas cambiar el servicio en `docker-compose.yml` y `.env`.
3. Debes usar `mailpit` como host.
4. Ejecuta `sail artisan queue:restart`, o ejecuta el worker con `--max-jobs=1` durante el desarrollo.
</details>
