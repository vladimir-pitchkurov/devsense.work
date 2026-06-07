---
title: "Ataques y defensas web: XSS, CSRF, SQLi, SSRF, IDOR y carga de archivos | DevSense"
description: "Los ataques web que encontrarás con más frecuencia: inyecciones (SQL/comandos), XSS, CSRF, IDOR/errores de control de acceso, SSRF, cargas de archivos no seguras, clickjacking y fallas de configuración. Mitigaciones prácticas, encabezados y listas de verificación."
published: 2026-04-14
faq:
  - question: "¿Por qué no basta con sanitizar la entrada HTML para evitar la inyección SQL?"
    answer: "La inyección SQL se produce cuando se concatenan datos no fiables en las consultas a la base de datos. La sanitización de HTML elimina etiquetas como `<script>`, pero no impide que caracteres como las comillas o los puntos y comas alteren la estructura de una consulta SQL. La única solución robusta consiste en utilizar consultas parametrizadas (sentencias preparadas)."
  - question: "¿Pueden las cookies SameSite sustituir por completo a los tokens CSRF?"
    answer: "Aunque `SameSite=Lax` o `Strict` protegen contra las solicitudes de sitios cruzados en los navegadores modernos, no son una garantía de seguridad completa. Es posible que los navegadores antiguos no admitan SameSite, y los errores o los cambios de estado mediante solicitudes GET pueden omitirlo. Los tokens CSRF siguen siendo fundamentales para una defensa en profundidad."
  - question: "¿En qué se diferencia SSRF de CSRF?"
    answer: "CSRF obliga al navegador de un usuario a enviar solicitudes a una aplicación en la que está autenticado. SSRF obliga al *servidor* de la aplicación a realizar solicitudes no autorizadas a recursos internos (como endpoints de metadatos o instancias de Redis internas) que son inaccesibles desde la internet pública."
  - question: "¿Por qué es inseguro comprobar únicamente la extensión del archivo para la carga de archivos?"
    answer: "Los atacantes pueden eludir fácilmente las comprobaciones de extensión (por ejemplo, utilizando extensiones dobles o renombrando archivos) o explotar vulnerabilidades en las que el servidor web ejecuta archivos con extensiones correctas pero con contenido malicioso (como código PHP incrustado en una imagen PNG). Debes validar el contenido del archivo (tipo MIME) y almacenar las cargas fuera de la raíz web."
---

# Ataques y defensas web que todo desarrollador debe conocer

Tu aplicación funciona sin problemas, el tráfico crece y el último despliegue se realizó sin contratiempos. De repente, una sola consulta mal formada de un usuario no autenticado borra una tabla de la base de datos, o un microservicio interno filtra registros de clientes a la internet pública debido a un cliente HTTP expuesto. La seguridad tiende a fallar **en las costuras**: la validación existe, pero no en el límite; la autorización está implementada, pero falta en un endpoint; la salida está escapada, pero una plantilla utiliza HTML sin procesar. La buena noticia: la mayoría de los incidentes reales pertenecen a clases repetitivas de errores, por lo que puedes corregirlos sistemáticamente.

**Guías relacionadas:** [Observability & monitoring](observability-monitoring-laravel) · [Databases under load](database-performance-and-scaling)

## Índice

* [Modelado de amenazas: qué proteges y de quién](#threat-model)
* [Inyecciones: SQLi, inyección de comandos, inyección de plantillas](#injection)
* [XSS: reflejado, almacenado, basado en DOM](#xss)
* [CSRF: por qué “pero es GET” no es una defensa](#csrf)
* [Autenticación y sesiones: robo de tokens, fijación, cookies](#auth-sessions)
* [Control de acceso e IDOR: “es solo un ID”](#idor)
* [Carga de archivos y rutas: cargas, recorrido de rutas, RCE cercano](#uploads)
* [SSRF: cuando tu servidor obtiene datos del “lugar equivocado”](#ssrf)
* [Deserialización insegura y suplantación de objetos](#deserialization)
* [Defensas del navegador: clickjacking, CORS, encabezados](#browser)
* [DoS y abuso: límites de tasa, fuerza bruta, trabajo costoso](#dos)
* [Errores comunes](#common-mistakes)
* [Lista de verificación de lanzamiento](#checklist)
* [Cuestionario de autoevaluación](#self-test-quiz)

---

<a id="threat-model"></a>
## Modelado de amenazas: qué proteges y de quién

Antes de escribir la lógica de seguridad, debes establecer los parámetros de la amenaza:

- **Atacante**: usuario anónimo, usuario registrado, socio con una clave API, alguien dentro de la VPN, un actor con acceso a la CI/CD.
- **Activos**: dinero, información de identificación personal (PII), cuentas, paneles de administración, integraciones, secretos, acceso a la red interna.
- **Superficie de ataque**: formularios y APIs, redirecciones, webhooks, importaciones/exportaciones, cargas de archivos, integraciones de terceros (S3, SMTP, pagos).

> [!NOTE]
> **Regla pragmática de seguridad**
> Asume siempre que todas las entradas son maliciosas. Esto incluye encabezados, cookies, cargas útiles de webhooks, parámetros de URL y mensajes leídos de las colas.

---

<a id="injection"></a>
## Inyecciones: SQLi, inyección de comandos, inyección de plantillas

### Inyección SQL (SQLi)

La SQLi suele empezar como “un simple fragmento de SQL nativo”. La solución no es “escapar” los datos, sino **parametrizar**.

**Malo** (concatenación de cadenas):
```php
// app/Http/Controllers/UserController.php
$rows = DB::select("SELECT * FROM users WHERE email = '{$email}'");
```

**Mejor** (marcadores de posición/placeholders):
```php
// app/Http/Controllers/UserController.php
$rows = DB::select('SELECT * FROM users WHERE email = ?', [$email]);
```

Otra trampa común es el **ordenamiento / nombres de columna dinámicos**. Los parámetros no se aplican a los identificadores SQL, por lo que debes usar una lista de permisos estricta:
```php
// app/Http/Controllers/UserController.php
$allowed = ['created_at', 'email', 'id'];
$sort = in_array($request->get('sort'), $allowed, true) ? $request->get('sort') : 'created_at';

$users = User::query()->orderBy($sort, 'desc')->paginate();
```

### Inyección de comandos (Command injection)

Si llamas a la shell, la regla es: **nunca pases la entrada del usuario a una línea de comandos**. Incluso `escapeshellarg()` es una medida de protección de último recurso, no un diseño seguro.

Si debes ejecutar herramientas de CLI (convertir archivos, generar vistas previas):
- Prefiere el uso de una biblioteca antes que `exec()`.
- Si el uso de la CLI es inevitable, fija los binarios/argumentos/directorios y ejecútalos en un entorno restringido (contenedor/cola/trabajador bajo un usuario dedicado).

### Inyección de plantillas (Template injection)

El caso peligroso es permitir que los usuarios proporcionen plantillas que se ejecutan como Blade/Twig/Smarty.
- **Regla**: Las plantillas de usuario deben tratarse como datos, idealmente renderizadas a través de un formato limitado que no sea Turing completo (por ejemplo, Markdown con una lista de permisos estricta y renderizado seguro).

---

<a id="xss"></a>
## XSS: reflejado, almacenado, basado en DOM

El XSS consiste en ejecutar JavaScript en tu origen. Orígenes típicos:
- Salida sin procesar (`{!! ... !!}` en Blade, `innerHTML` en el frontend).
- Campos de texto enriquecido almacenados sin sanitizar.
- Inyección de valores en `<script>` o atributos HTML sin la codificación adecuada.

### Mitigaciones principales

- **Escapar por defecto**: en Blade utiliza `{{ $value }}`; evita la salida sin procesar a menos que esté justificada.
- **Respetar los contextos**: HTML frente a atributos frente a cadenas JS frente a URLs; las reglas de codificación varían.
- **Sanitizar la entrada HTML**: si permites formato, usa una lista de permisos (etiquetas/atributos) y elimina los controladores `on*`, URLs `javascript:`, etc.
- **CSP (Content Security Policy)**: no es una solución lógica, pero limita enormemente los daños.

CSP básica de inicio (idealmente con nonces y sin `'unsafe-inline'`):
```http
# /etc/nginx/nginx.conf
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'
```

---

<a id="csrf"></a>
## CSRF: por qué “pero es GET” no es una defensa

El CSRF ocurre cuando el navegador de un usuario envía una solicitud a tu sitio con sus cookies/sesión porque otro sitio lo provoca (formularios, imágenes, JS).

### Mitigaciones

- **Tokens CSRF** para solicitudes que cambian el estado (POST/PUT/PATCH/DELETE). Laravel hace esto de forma predeterminada.
- **Cookies `SameSite`**: al menos `Lax`; considera `Strict` para flujos sensibles; para casos entre sitios usa `None; Secure`.
- **Comprobaciones de Origin/Referer** para acciones especialmente sensibles (cambiar correo electrónico, pagos).
- **Nunca cambies de estado mediante solicitudes GET**.

---

<a id="auth-sessions"></a>
## Autenticación y sesiones: robo de tokens, fijación, cookies

Lo que suele fallar:
- Contraseñas débiles + falta de límites de tasa → credential stuffing.
- Robo de sesión mediante XSS; fijación de sesión; falta de invalidación.
- Tokens de larga duración sin rotación ni vinculación al dispositivo.

### Controles básicos

- **Límites de tasa** para el inicio de sesión/OTP/restablecimiento de contraseña.
- **Hashear contraseñas** con `password_hash` utilizando bcrypt o argon2. No uses criptografía personalizada.
- Cookies de sesión: `HttpOnly`, `Secure`, `SameSite`, mantén una vida útil corta.
- **Rotar IDs de sesión** después de iniciar sesión y tras cambios de privilegios.

---

<a id="idor"></a>
## Control de acceso e IDOR: “es solo un ID”

El IDOR (Insecure Direct Object Reference) ocurre cuando un usuario puede adivinar/iterar identificadores y leer o modificar los datos de otra persona.

Causas comunes:
- Comprobar “sesión iniciada” en lugar de la propiedad o rol.
- La autorización existe en la interfaz de usuario pero no en la API.
- Comportamiento de “administrador” controlado por un parámetro de solicitud.

### Mitigaciones

- **Políticas/puertas (policies/gates)** para cada recurso y acción.
- Para aplicaciones multi-inquilino (multi-tenant), filtra siempre por `tenant_id`/`account_id` a nivel de consulta.
- No confíes en “ocultar” como medida de seguridad.

```php
// app/Http/Controllers/OrderController.php
// Es preferible limitar la consulta al usuario:
$order = auth()->user()->orders()->findOrFail($id);

// En lugar de una búsqueda directa:
$order = Order::findOrFail($id);
```

---

<a id="uploads"></a>
## Carga de archivos y rutas: cargas, recorrido de rutas, RCE cercano

Las cargas de archivos son un área clásica para una \"ejecución remota de código (RCE) cercana\".

### Qué imponer

- Valida el **contenido**, no solo la extensión; el tipo MIME del navegador no es confiable.
- Límites en el tamaño y número de archivos.
- Almacena fuera de la raíz web; sirve los archivos a través de un controlador o un dominio/CDN dedicado.
- Nombres de archivos aleatorios (no confíes en los nombres originales como rutas).
- Deshabilita la ejecución en los directorios de carga a nivel del servidor web.

```nginx
# /etc/nginx/conf.d/uploads.conf
location /uploads {
    location ~ \.php$ {
        deny all;
    }
}
```

El recorrido de rutas (Path traversal) a menudo aparece como “descargar archivo por nombre”:
- Rechaza `../`, `\`, bytes nulos.
- Usa una lista de permisos de archivos/IDs reales, no una ruta de la solicitud.

---

<a id="ssrf"></a>
## SSRF: cuando tu servidor obtiene datos del “lugar equivocado”

El SSRF (Server-Side Request Forgery) aparece cuando aceptas una URL del usuario y **la obtienes desde el servidor** (importación de avatar, probador de webhooks, vista previa de enlaces, generador de PDF).

Riesgos: acceso a **servicios internos** (endpoints de metadatos de la nube, Redis/Consul, paneles de administración), eludiendo límites de red.

### Mitigaciones

- **Lista de permisos (allowlist) de hosts/dominios**, no una lista negra.
- Bloquea rangos de IP privadas (127.0.0.0/8, 10.0.0.0/8, 169.254.0.0/16, 172.16.0.0/12, 192.168.0.0/16, ::1, fc00::/7).
- Deshabilita las redirecciones o vuelve a validar cada salto.
- Tiempos de espera, límites en el tamaño de la respuesta, restricciones de protocolo (solo https).

---

<a id="deserialization"></a>
## Deserialización insegura y suplantación de objetos

Si deserializas datos controlados por el atacante (cookies, campos ocultos, cargas útiles de colas externas, cachés no firmadas), corres el riesgo de:
- Suplantación de roles/campos.
- Cadenas de dispositivos (gadget chains) y RCE cuando corresponda.

> [!NOTE]
> **Integridad de datos**
> Nunca almacenes objetos serializados de PHP/JS en lugares no seguros. Para los tokens, utiliza estructuras firmadas como JWT o HMAC. Para cargas útiles estructuradas, utiliza JSON + validación de esquema estricta.

---

<a id="browser"></a>
## Defensas del navegador: clickjacking, CORS, encabezados

### Clickjacking

Bloquear el encuadre (framing):
```http
# /etc/nginx/nginx.conf
X-Frame-Options: DENY
Content-Security-Policy: frame-ancestors 'none'
```

### CORS

CORS no es un “bloqueo de solicitudes”; es una regla del navegador para leer respuestas. Errores comunes:
- `Access-Control-Allow-Origin: *` con cookies/credenciales.
- Métodos/encabezados permitidos excesivamente amplios.
- Confiar en `Origin` sin una lista estricta.

### Encabezados de seguridad básicos
```http
# /etc/nginx/nginx.conf
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

---

<a id="dos"></a>
## DoS y abuso: límites de tasa, fuerza bruta, trabajo costoso

En las aplicaciones empresariales, el “mini-DoS” más común no es el ancho de banda, sino los endpoints baratos para el atacante pero costosos para ti:
- Búsquedas no indexadas, exportaciones gigantes, generación de PDFs.
- Falta de límites de tasa (inicio de sesión, envío de correos/OTP, “comprobación de código promocional”).
- Validaciones/expresiones regulares costosas sobre cadenas enormes.

### Controles
- Limitación de tasa por IP + cuenta + clave API.
- Poner en cola el trabajo costoso.
- Tiempos de espera, límites de tamaño, límites de profundidad de JSON.
- Almacenar en caché las respuestas públicas pesadas.

---

<a id="common-mistakes"></a>
## Errores comunes

1. **Confiar en la validación del lado del cliente**: Implementar validaciones en los formularios del front-end pero descuidarlas en el back-end, lo que permite a los usuarios enviar cargas útiles que eludan las reglas directamente a los endpoints de la API.
2. **SQL nativo para parámetros de Sort / Order by**: Dado que los marcadores de posición no pueden representar nombres de columnas, los desarrolladores suelen concatenar las entradas de usuario en las sentencias `ORDER BY`, abriendo vulnerabilidades de SQLi.
3. **Listas negras de IPs privadas en SSRF**: Intentar bloquear `127.0.0.1` y `10.0.0.1` pero olvidar las codificaciones hexadecimales (`0x7f.0.0.1`), las representaciones octales o los ataques de reenvío DNS (DNS rebinding).
4. **Deserialización incorrecta de PHP**: Almacenar objetos serializados de PHP en cookies, creyendo que es seguro porque los datos son “opacos”, lo que permite la inyección de objetos PHP (PHP Object Injection).

---

<a id="checklist"></a>
## Lista de verificación de lanzamiento

### Entradas y datos
- [ ] Validar en el límite (formularios/APIs/webhooks), normalizar tipos.
- [ ] Lista de permisos para ordenar/filtrar/campos que se convierten en identificadores SQL.
- [ ] No cambiar de estado mediante GET.

### Renderizado y navegador
- [ ] Escapar por defecto, sin HTML nativo no justificado.
- [ ] CSP/`frame-ancestors`, protección contra clickjacking.
- [ ] HSTS, `nosniff`, `Referrer-Policy` sensato.

### Acceso
- [ ] Autorización en el lado del servidor para cada acción (política/puerta), no solo en la interfaz de usuario.
- [ ] Sin IDOR: consultas limitadas al propietario/inquilino.
- [ ] Logs de auditoría para acciones sensibles.

### Integraciones
- [ ] Controles de SSRF: lista de permisos, bloqueos de IP privadas, tiempos de espera/límites.
- [ ] Webhooks: verificación de firma, protección contra reproducción, idempotencia.

### Cargas
- [ ] Comprobaciones de contenido/tamaño, almacenar fuera de la raíz web, deshabilitar la ejecución.
- [ ] Nombres aleatorios, sin rutas proporcionadas por el usuario.

---

## Resumen

La seguridad no es un producto final; es un conjunto de invariantes de límite. Valida la entrada una vez en el perímetro, escapa la salida en las plantillas, impón la autorización en el servidor, firma las integraciones externas y limita la tasa de cualquier cosa costosa.

---

<a id="self-test-quiz"></a>
## Cuestionario de autoevaluación

### Pregunta 1: ¿Cuál es el problema de seguridad principal con el siguiente código?
```php
// app/Http/Controllers/DownloadController.php
return response()->download(storage_path('reports/' . $request->get('file')));
```
- A) Inyección SQL.
- B) Secuencias de comandos en sitios cruzados (XSS).
- C) Recorrido de ruta (descarga de archivos arbitrarios).

<details>
<summary><b>Mostrar respuestas</b></summary>

**Respuesta: C**
Debido a que la entrada `$request->get('file')` se concatena directamente en la ruta sin verificar si contiene `../`, un atacante puede pasar `file=../../.env` para leer secretos de configuración confidenciales.
</details>

---

### Pregunta 2: ¿Por qué se requieren tokens CSRF para las solicitudes POST si la cookie de sesión ya está marcada como `SameSite=Lax`?
- A) `SameSite=Lax` no impide las solicitudes POST desencadenadas por navegaciones de nivel superior o scripts en navegadores más antiguos.
- B) Los tokens CSRF previenen la inyección SQL.
- C) Sin un token CSRF, no se puede leer la cookie de sesión.

<details>
<summary><b>Mostrar respuestas</b></summary>

**Respuesta: A**
Los atributos SameSite son una defensa útil, pero no proporcionan una cobertura de seguridad del 100% debido a problemas de compatibilidad del navegador, apropiaciones de subdominios vulnerables o modificaciones de estado basadas en GET.
</details>
