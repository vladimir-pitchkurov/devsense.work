---
title: "Laravel Sail: estructura de .env, redirección de puertos, CI y local frente a producción | DevSense"
description: "Separa la configuración de Laravel Sail y del host: .env.example, puertos FORWARD_*, APP_URL en Docker, env_file opcional, GitHub Actions con docker compose y listas de verificación cuando Sail no es tu servidor."
faq:
  - question: "¿Por qué recibo errores de 'puerto ya asignado' al ejecutar sail up?"
    answer: "Esto ocurre cuando otro servicio en tu máquina host (como un MySQL local u otra instancia de Sail) ya está utilizando el puerto. Puedes resolver esto cambiando FORWARD_DB_PORT o APP_PORT en tu archivo .env."
  - question: "¿Debería subir mi archivo .env a Git?"
    answer: "No, nunca debes subir tu archivo .env. Contiene secretos y configuraciones específicas del desarrollador. En su lugar, mantén y sube valores seguros por defecto dentro de .env.example."
  - question: "¿Cómo cargo configuraciones de contenedor específicas de desarrolladores sin modificar la configuración compartida?"
    answer: "Puedes especificar una lista 'env_file' bajo tu servicio dentro de docker-compose.yml para cargar un archivo local secundario (por ejemplo, .env.docker.local) que contenga sobreescrituras locales."
  - question: "¿Puedo ejecutar Laravel Sail en un servidor de producción?"
    answer: "No. Sail está diseñado para la ergonomía del desarrollador y carece de seguridad de nivel de producción, terminación TLS, sistemas de respaldo y optimizaciones de escalado. Realiza tus despliegues utilizando configuraciones dedicadas (por ejemplo, Kubernetes, Ansible o Laravel Forge)."
---

# Sail: entornos y despliegue

Subes un archivo `.env` local que contiene contraseñas de bases de datos a GitHub, y tus escáneres de seguridad activan inmediatamente una alerta. O un compañero de equipo hace un pull de tu repositorio, ejecuta `sail up` y observa cómo el proceso se cae porque su puerto MySQL local `3306` ya está ocupado por otro proyecto. La gestión del entorno no se trata solo de definir pares clave-valor; se trata de establecer un límite estricto entre la ergonomía local, las pruebas de CI/CD y los despliegues de producción seguros.

El desarrollo en contenedores cambia el comportamiento de las variables de entorno. Dado que PHP se ejecuta dentro de `laravel.test` mientras las bases de datos se vinculan a los puertos del host, las rutas, URLs y mapeos de puertos deben ajustarse dependiendo de si se accede a ellos desde la máquina host o desde dentro de la red de Docker Compose.

En esta guía, te mostramos cómo estructurar los archivos `.env`, evitar colisiones de puertos de host, configurar pipelines de CI basados en Docker y auditar tu configuración antes de pasar a producción.

**Navegación:** [Todos los herramientas](../) · [Aperçu de Sail](sail#what-sail-is) · [Bases de datos](sail-databases#networking) · [Colas](sail-queues#connections) · [Resolución de problemas](sail-troubleshooting#wsl-filesync)

## Índice

* [`.env`, `.env.example` y secretos](#env-files)
* [Puertos `FORWARD_*` y colisiones](#forward-ports)
* [`APP_URL` y proxies de confianza](#app-url)
* [`env_file` opcional en Compose](#compose-env-file)
* [CI: patrón de GitHub Actions](#ci-example)
* [Sail frente a servidores de dev/staging/prod](#not-production)
* [Lista de verificación antes de pasar a producción](#checklist)
* [Errores comunes](#common-mistakes)
* [Preguntas de autoevaluación](#self-check)

---

<a id="env-files"></a>
## `.env`, `.env.example` y secretos

- **`.env`**: Almacena secretos locales y detalles del entorno. **Nunca subas este archivo al control de versiones.**
- **`.env.example`**: Subido a Git. Las claves por defecto deben representar valores locales seguros para que un nuevo desarrolleur pueda copiarlo a `.env` y ejecutar `sail up` inmediatamente.

```dotenv
# .env.example
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306

# Sobreescrituras específicas de Sail
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
```

---

<a id="forward-ports"></a>
## Puertos `FORWARD_*` y colisiones

Por defecto, Sail vincula los puertos de la base de datos y de la aplicación a localhost. Si ejecutas múltiples proyectos, puertos como el `3306` (MySQL) o el `80` (HTTP) colisionarán.

Para solucionar esto, cambia los puertos reenviados en `.env`:

```dotenv
# .env
APP_PORT=8080
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
```

> [!NOTE]
> **Puertos internos frente a externos**
> Modificar `FORWARD_DB_PORT` cambia el puerto expuesto en tu máquina host. Dentro de la red de Docker, MySQL sigue comunicándose en el puerto predeterminado `3306`. Tu configuración de Laravel no necesita cambiar su variable `DB_PORT`.

---

<a id="app-url"></a>
## `APP_URL` y proxies de confianza

Laravel utiliza `APP_URL` para generar URLs firmadas y redirecciones de rutas.

```dotenv
# .env
APP_URL=http://localhost:8080
```

Asegúrate de que coincida con el puerto mapeado en tu host. Si colocas un balanceador de carga o un proxy inverso (como Nginx o Traefik) frente a tu aplicación de producción, configura `TrustProxies` en Laravel para leer la IP original del cliente y el protocolo (HTTPS) correctamente.

---

<a id="compose-env-file"></a>
## `env_file` opcional en Compose

Si tienes variables de entorno que solo se aplican al entorno de Docker, cárgalas en `docker-compose.yml`:

```yaml
# docker-compose.yml
services:
    laravel.test:
        env_file:
            - .env
            - .env.docker.local
```

Esto evita contaminar el archivo `.env` compartido con variables de entorno que solo importan dentro de los contenedores.

---

<a id="ci-example"></a>
## CI: patrón de GitHub Actions

Puedes usar los servicios de Docker Compose de Sail dentro de tu pipeline de CI/CD para ejecutar pruebas automatizadas en un entorno limpio.

```yaml
# .github/workflows/tests.yml
name: Run Tests
on: [push]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Copy CI Env
        run: cp .env.ci .env
      - name: Start Sail
        run: docker compose up -d
      - name: Run Pest/PHPUnit
        run: docker compose exec -T laravel.test php artisan test
```

---

<a id="not-production"></a>
## Sail frente a servidores de dev/staging/prod

- **Sail** está optimizado para la velocidad del desarrollador local (incluye herramientas como Mailpit, Meilisearch y entornos de ejecución con detección de cambios en archivos).
- **Los entornos de Staging/Producción** requieren endurecimiento de seguridad, agregación de registros, rutinas de respaldo automatizadas, certificados SSL/TLS y servidores de bases de datos independientes (por ejemplo, AWS RDS).

> [!NOTE]
> No intentes usar `sail up` en un servidor en la nube de acceso público. Expone servicios de desarrollo y utiliza plantillas de configuración que son sumamente inseguras.

---

<a id="checklist"></a>
## Lista de verificación antes de pasar a producción

- [ ] `APP_ENV=production` y `APP_DEBUG=false` en el entorno de producción.
- [ ] Genera una clave `APP_KEY` segura usando `php artisan key:generate`.
- [ ] Conéctate a instancias de bases de datos gestionadas, no a bases de datos locales en contenedores.
- [ ] Configura `QUEUE_CONNECTION=redis` o `sqs` con sistemas de workers supervisados.
- [ ] Habilita la terminación de TLS/HTTPS.
- [ ] Configura controladores de logs centralizados (por ejemplo, Bugsnag, Sentry o AWS CloudWatch).

---

## ⚠️ Errores comunes

**1. Subir el archivo `.env` a Git**
Exponer claves de API, credenciales de bases de datos o contraseñas de aplicaciones en los historiales de los repositorios.
*Solución:* Verifica que `.env` esté listado en tu archivo `.gitignore` antes de hacer un commit.

**2. Cambiar `DB_PORT` en lugar de `FORWARD_DB_PORT` para conflictos locales**
Cambiar `DB_PORT=3307` en tu `.env` sin modificar la configuración de Compose de Sail romperá la conexión interna porque la base de datos del contenedor sigue escuchando en el `3306`.
*Solución:* Mantén `DB_PORT=3306` (para la conexión interna del contenedor) y configura `FORWARD_DB_PORT=3307` (para cambiar el puerto de la máquina host).

**3. Despliegue directo del `docker-compose.yml` de Sail a producción**
Levantar bases de datos junto al contenedor de la aplicación sin replicación persistente, copias de seguridad o políticas de acceso seguro.
*Solución:* El archivo Compose de Sail es una utilidad de desarrollo, no un manifiesto de despliegue.

---

## 🧠 Preguntas de autoevaluación

1. **¿Por qué cambiar `DB_PORT=3307` dentro de `.env` rompe las migraciones en Sail, mientras que cambiar `FORWARD_DB_PORT=3307` no lo hace?**
2. **¿Cuál es el riesgo de seguridad de desplegar el archivo Compose de Sail directamente en un servidor de producción público?**
3. **¿Cómo ayuda el bloque `env_file` en `docker-compose.yml` a estructurar las sobreescrituras del entorno?**
4. **¿Qué archivo deberías actualizar para asegurar que tus compañeros tengan una lista de todas las claves de API requeridas para una nueva funcionalidad?**

<details>
<summary><b>Mostrar respuestas</b></summary>

1. Cambiar `DB_PORT` le indica a Laravel que busque la base de datos en el puerto `3307` dentro de la red del contenedor, donde el contenedor de la base de datos sigue escuchando en el `3306`. Cambiar `FORWARD_DB_PORT` modifica únicamente el puerto externo expuesto a tu sistema host, dejando intacta la red interna del contenedor.
2. Expone herramientas exclusivas de desarrollo (como Mailpit y Meilisearch) al internet público, ejecuta contenedores con privilegios de desarrollador y expone bases de datos sin replicación ni copias de seguridad de producción.
3. Te permite dividir tus variables en múltiples archivos (como `.env` y `.env.docker.local`), cargando sobreescrituras específicas de Docker sin saturar el archivo de configuración principal.
4. Debes actualizar `.env.example` con claves vacías o marcadores de posición seguros y documentar su uso.
</details>
