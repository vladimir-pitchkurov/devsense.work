---
title: "Laravel Sail: bases de datos, Redis, Postgres, MongoDB, RabbitMQ en Docker Compose | DevSense"
description: "Recetas preparadas para Laravel Sail: añadir Redis, cambiar a PostgreSQL, ejecutar MongoDB con la extensión PHP, contenedor de RabbitMQ, configuración de Mailpit y Meilisearch, healthchecks y volumes nombrados."
faq:
  - question: "¿Por qué falla la conexión de mi aplicación Laravel a MySQL/PostgreSQL en Sail?"
    answer: "Esto suele ocurrir porque DB_HOST está configurado como 127.0.0.1 o localhost en el archivo .env. Dentro de la red de Docker, laravel.test debe conectarse usando el nombre del servicio de Compose (por ejemplo, mysql o pgsql) como nombre de host."
  - question: "¿Cómo agrego una nueva extensión de PHP (como MongoDB) a Sail?"
    answer: "Debes publicar los Dockerfiles de Sail usando 'php artisan sail:publish', editar el Dockerfile correspondiente a tu versión de PHP (por ejemplo, pecl install mongodb) y reconstruir la imagen con 'sail build --no-cache'."
  - question: "¿Cómo limpio la base de datos sin perder otros volúmenes del proyecto?"
    answer: "En lugar de ejecutar 'sail down -v', que destruye todos los volúmenes, identifica el nombre del volumen específico usando 'docker volume ls' y elimínalo individualmente usando 'docker volume rm <nombre_del_volumen>'."
  - question: "¿Cómo puedo evitar fallas en las migraciones al iniciar Sail?"
    answer: "Configura healthchecks de Docker para tu base de datos en docker-compose.yml y define el servicio 'laravel.test' para que dependa de él con 'condition: service_healthy' para que las migraciones esperen a que la base de datos esté lista."
---

# Sail: bases de datos y servicios de Docker

Ejecutas `sail up -d`, escribes `php artisan migrate` y obtienes un muro rojo de error `Connection refused`. A pesar de que Docker Desktop muestra que el contenedor de tu base de datos está funcionando y saludable, la aplicación interna se niega a comunicarse con él. Esta ruptura de conexión resalta una regla fundamental del desarrollo en contenedores: en Docker Compose, `127.0.0.1` se refiere al propio contenedor, no a la red.

La configuración de servicios externos (bases de datos, cachés, gestores de mensajes) para un proyecto Laravel puede llevar rápidamente a problemas de discrepancia de entornos entre los equipos de desarrollo. Sail resuelve esto tratando tus servicios como contenedores independientes y reproducibles, pero debes configurar correctamente su conectividad de red, requisitos de compilación y orden de inicio.

In esta guía, te mostramos cómo cambiar y configurar bases de datos (Postgres, MongoDB), conectar cachés y brokers (Redis, RabbitMQ), y manejar de forma segura los healthchecks y reinicios de volúmenes.

**Navegación:** [Todos los herramientas](../) · [Aperçu de Sail](sail#what-sail-is) · [Colas](sail-queues#connections) · [Env y despliegue](sail-env-deploy#forward-ports) · [Resolución de problemas](sail-troubleshooting#wsl-filesync)

## Índice

* [Reglas de red y nombre de host](#networking)
* [Servicio Redis (ejemplo completo)](#redis-service)
* [Reemplazar MySQL con PostgreSQL](#postgres-swap)
* [MongoDB + extensión PHP en Sail](#mongodb)
* [RabbitMQ como contenedor](#rabbitmq-sidecar)
* [Mailpit (desarrollo SMTP)](#mailpit)
* [Meilisearch / Typesense (búsqueda opcional)](#search-engines)
* [Healthchecks y orden de inicio](#healthchecks)
* [Volúmenes nombrados y reinicio de datos](#volumes)
* [Errores comunes](#common-mistakes)
* [Preguntas de autoevaluación](#self-check)

---

<a id="networking"></a>
## Reglas de red y nombre de host

Todos los hosts del lado de la aplicación en **`.env`** deben ser **nombres de servicio de Compose** (`mysql`, `pgsql`, `redis`, `mongo`), no `127.0.0.1`. Esto se debe a que PHP se ejecuta **dentro** del contenedor `laravel.test` y se comunica a través de una red de Docker aislada.

> [!NOTE]
> **Máquina host frente a Red del contenedor**
> Desde tu máquina host (IDE, terminal, DBeaver), te conectas a las bases de datos a través de `127.0.0.1` utilizando el puerto reenviado (por ejemplo, `FORWARD_DB_PORT`). Pero dentro de los contenedores, los servicios deben comunicarse entre sí utilizando sus nombres de servicio de Compose.

---

<a id="redis-service"></a>
## Servicio Redis (ejemplo completo)

Para añadir Redis a tu entorno de Sail, define el servicio y monta un volumen nombrado.

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
        healthcheck:
            test: ["CMD", "redis-cli", "ping"]
            retries: 3
            timeout: 5s
```

Registra **`sail-redis`** bajo la sección principal **`volumes:`** y declara la dependencia dentro de **`laravel.test`**:

```yaml
# docker-compose.yml
services:
    laravel.test:
        depends_on:
            redis:
                condition: service_healthy
```

```dotenv
# .env
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Ejecuta `sail exec redis redis-cli ping` para verificar la conexión.

---

<a id="postgres-swap"></a>
## Reemplazar MySQL con PostgreSQL

Si tu servidor de producción utiliza PostgreSQL, ejecutar MySQL localmente es un riesgo innecesario.

1. Comenta o elimina el servicio `mysql` y su volumen asociado en `docker-compose.yml`.
2. Añade el bloque del servicio `pgsql` utilizando el stub estándar de Sail:

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
        healthcheck:
            test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME} -d ${DB_DATABASE}"]
            retries: 3
            timeout: 5s
```

3. Actualiza el array `depends_on` de `laravel.test` para apuntar a `pgsql`.
4. Actualiza el archivo **`.env`**:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

Reconstruye e inicia los contenedores usando `sail build --no-cache && sail up -d`.

---

<a id="mongodb"></a>
## MongoDB + extensión PHP en Sail

MongoDB no está soportado de fábrica por los entornos de ejecución por defecto de Sail y requiere instalar la extensión de PHP MongoDB dentro del contenedor de la aplicación.

1. Añade el servicio MongoDB a tu archivo de Compose:

```yaml
# docker-compose.yml
services:
    mongo:
        image: 'mongo:7'
        ports:
            - '${FORWARD_MONGO_PORT:-27017}:27017'
        volumes:
            - 'sail-mongo:/data/db'
        networks:
            - sail
```

2. Registra el volumen `sail-mongo` bajo `volumes:`.
3. Publica los Dockerfiles de Sail si no lo has hecho ya:
   ```bash
   php artisan sail:publish
   ```
4. Edita el Dockerfile del entorno de ejecución (por ejemplo, `docker/8.3/Dockerfile`) para instalar la extensión PECL:

```dockerfile
# docker/8.3/Dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

5. Reconstruye tus imágenes de Sail e inicia:
   ```bash
   sail build --no-cache
   sail up -d
   ```

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ como contenedor

Para proyectos que requieran características de AMQP avanzadas en lugar de Redis o colas basadas en bases de datos, ejecuta RabbitMQ como un contenedor sidecar.

```yaml
# docker-compose.yml
services:
    rabbitmq:
        image: 'rabbitmq:3-management-alpine'
        hostname: rabbitmq
        ports:
            - '${FORWARD_RABBITMQ_PORT:-5672}:5672'
            - '${FORWARD_RABBITMQ_MANAGEMENT:-15672}:15672'
        volumes:
            - 'sail-rabbitmq:/var/lib/rabbitmq'
        networks:
            - sail
        environment:
            RABBITMQ_DEFAULT_USER: '${RABBITMQ_USER:-sail}'
            RABBITMQ_DEFAULT_PASS: '${RABBITMQ_PASSWORD:-password}'
```

> [!NOTE]
> Asegúrate de declarar `sail-rabbitmq` bajo tu lista principal `volumes:`. Accede al panel de administración localmente en `http://localhost:15672` utilizando tus credenciales personalizadas (por ejemplo, `sail` / `password`).

---

<a id="mailpit"></a>
## Mailpit (desarrollo SMTP)

Sail incluye Mailpit para atrapar correos electrónicos salientes. Esto evita que se envíen accidentalmente mensajes reales a los usuarios durante las pruebas locales.

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

Todos los correos electrónicos enviados por la aplicación se pueden leer a través de la interfaz web en `http://localhost:8025`.

---

<a id="search-engines"></a>
## Meilisearch / Typesense (búsqueda opcional)

Para habilitar una búsqueda local ultrarrápida con Laravel Scout:

```bash
php artisan sail:install --with=meilisearch
```

O copia el stub del servicio Meilisearch en tu configuración de Compose. Recuerda apuntar tus variables de entorno al nombre del servicio:

```dotenv
# .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
```

---

<a id="healthchecks"></a>
## Healthchecks y orden de inicio

Por defecto, Docker Compose inicia los contenedores en paralelo. Un `depends_on: [mysql]` estándar solo asegura que el contenedor de MySQL *se inicie*, no que el motor de la base de datos esté *completamente listo* para recibir conexiones.

La implementación de un `healthcheck` en el servicio de la base de datos y la configuración de `condition: service_healthy` en el servicio de la aplicación aseguran que las migraciones se ejecuten limpiamente durante la inicialización.

---

<a id="volumes"></a>
## Volúmenes nombrados y reinicio de datos

Los volúmenes nombrados actúan como unidades virtuales persistentes.
- Ejecutar `sail down` detiene los contenedores pero deja intactos tus volúmenes de base de datos.
- Ejecutar `sail down -v` destruye **todos** los volúmenes, reiniciando por completo tu entorno local.
- Para reiniciar una sola base de datos: localiza el volumen objetivo con `docker volume ls` y elimínalo usando `docker volume rm <nombre_del_volumen>`.

---

## ⚠️ Errores comunes

**1. Usar `127.0.0.1` o `localhost` dentro de `.env` para conexiones de contenedores**
Configurar `DB_HOST=127.0.0.1` hace que Laravel busque la base de datos dentro del propio contenedor PHP, lo que provoca un fallo de conexión.
*Solución:* Usa `DB_HOST=mysql` o `DB_HOST=pgsql`.

**2. Reinicios de volúmenes destructivos con `sail down -v`**
Ejecutar `sail down -v` cuando solo quieres detener tus contenedores borrará tus bases de datos, registros de semilla (seeders) y claves de Redis.
*Solución:* Usa `sail down` para detener los contenedores de forma segura. Solo usa `-v` si pretendes borrar por completo el estado.

**3. Modificar Dockerfiles personalizados sin reconstruir la imagen**
Editar los Dockerfiles de runtime publicados para instalar extensiones de PHP (como `mongodb` o `gd`) no tiene efecto hasta que fuerces una reconstrucción.
*Solución:* Ejecuta siempre `sail build --no-cache` después de editar Dockerfiles.

---

## 🧠 Preguntas de autoevaluación

1. **¿Por qué falla la configuración `DB_HOST=127.0.0.1` al ejecutar migraciones de Laravel dentro de Sail?**
2. **¿En qué se diferencia `depends_on` con `condition: service_healthy` de un array `depends_on` básico?**
3. **¿Qué sucede con tus datos locales de PostgreSQL si ejecutas `sail down -v`?**
4. **¿Por qué necesitas publicar los Dockerfiles de Sail (a través de `sail:publish`) antes de poder usar la extensión MongoDB?**

<details>
<summary><b>Mostrar respuestas</b></summary>

1. Dentro de Docker Compose, cada contenedor tiene su propia interfaz de red de bucle de retorno (loopback). `127.0.0.1` se refiere al propio contenedor `laravel.test`, el cual no ejecuta la base de datos. Debes utilizar el nombre del servicio del contenedor de la base de datos como nombre de host.
2. Un `depends_on` básico solo comprueba si el contenedor se ha iniciado (en estado `running`). El modificador `condition: service_healthy` impide que el contenedor dependiente se inicie hasta que la base de datos pase su comprobación interna de salud (por ejemplo, que esté lista para aceptar conexiones socket).
3. Todos los datos locales de PostgreSQL se destruirán permanentemente porque se elimina el volumen que almacena `/var/lib/postgresql/data`.
4. Sail utiliza imágenes de Docker genéricas preconstruidas. Para instalar extensiones PECL personalizadas, debes acceder y modificar la configuración subyacente del Dockerfile y compilar una imagen local.
</details>
