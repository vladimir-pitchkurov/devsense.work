---
title: "SSRF y subidas de archivos seguras: Prevención de Server-Side Request Forgery y ejecución remota de código | DevSense"
description: "Proteja la infraestructura de su backend. Aprenda a prevenir la falsificación de solicitudes del lado del servidor (SSRF) y las subidas de archivos maliciosos que conducen a la ejecución remota de código (RCE)."
published: 2026-06-19
faq:
  - question: "¿Por qué la resolución de dominio es clave para prevenir el SSRF?"
    answer: "Los atacantes pueden eludir los filtros de nombres de host utilizando técnicas de rebote de DNS (DNS Rebinding) o de redireccionamiento. Para evitar esto, debe resolver el dominio a su dirección IP, verificar que la IP no pertenezca a bucles locales (loopback) o subredes privadas, y luego realizar la solicitud HTTP directamente a esa dirección IP validada."
  - question: "¿Cómo explota un atacante las subidas de archivos inseguras para obtener RCE?"
    answer: "Si la aplicación almacena los archivos subidos en un directorio de acceso público (raíz web) y conserva su extensión PHP original (por ejemplo, `shell.php`), el atacante puede ejecutar el archivo directamente accediendo a su URL, lo que desencadena la ejecución de código arbitrario en el servidor."
  - question: "¿Qué es un ataque XSS mediante SVG?"
    answer: "Los archivos SVG son documentos XML que pueden incrustar HTML y JavaScript en línea. Si una aplicación permite a los usuarios subir archivos SVG y los muestra en línea en el navegador, cualquier JavaScript incrustado dentro del SVG se ejecutará bajo el dominio de la aplicación, lo que resulta en un XSS almacenado."
---

# SSRF y subidas de archivos seguras: Prevención de Server-Side Request Forgery y ejecución remota de código

A medida que las aplicaciones de backend se conectan a APIs externas y permiten a los usuarios subir archivos multimedia, exponen vías de comunicación directa con los servidores internos y el sistema de archivos. Si no se aseguran estos límites, se producen ataques de falsificación de solicitudes del lado del servidor (SSRF) y ejecución remota de código (RCE).

En esta guía, analizaremos cómo ocurren las vulnerabilidades de SSRF y subida de archivos en PHP y Laravel, y crearemos filtros robustos para protegerlas.

**Guías relacionadas:** [Vulnerabilidades y mitigaciones de aplicaciones web](web-app-security) · [Observability & monitoring](observability-monitoring-laravel)

## Contenido

* [Server-Side Request Forgery (SSRF)](#ssrf)
* [Mitigación de SSRF en PHP](#ssrf-mitigation)
* [Vulnerabilidades de subida de archivos](#file-upload-vulnerabilities)
* [Implementación de subida de archivos segura](#secure-file-upload)
* [Errores comunes](#common-mistakes)
* [Lista de verificación](#checklist)
* [Resumen](#summary)
* [Cuestionario de autoevaluación](#self-test-quiz)

---

<a id="ssrf"></a>
## Server-Side Request Forgery (SSRF)

El **Server-Side Request Forgery (SSRF)** ocurre cuando una aplicación obtiene una URL remota proporcionada por el usuario sin validar el destino final. 

Dado que la solicitud se origina en el servidor backend, el atacante puede usar el servidor como un proxy para:
- Escanear redes internas (por ejemplo, `http://10.0.0.5:80`).
- Acceder a microservicios internos que carecen de autenticación (por ejemplo, Redis en `http://127.0.0.1:6379`).
- Acceder a endpoints de metadatos de la nube (por ejemplo, `http://169.254.169.254/latest/meta-data/` en AWS/OpenStack) para recuperar credenciales de seguridad temporales de IAM.

---

<a id="ssrf-mitigation"></a>
## Mitigación de SSRF en PHP

Para prevenir el SSRF, debemos validar tanto el esquema del protocolo como la dirección IP resuelta para asegurarnos de que no apunten a redes de bucle local o privadas.

```php
// app/Security/SafeHttpClient.php
declare(strict_types=1);

namespace App\Security;

use InvalidArgumentException;
use RuntimeException;

class SafeHttpClient
{
    public function fetch(string $url): string
    {
        $parsedUrl = parse_url($url);
        
        // 1. Restrict scheme to HTTP/HTTPS only
        $scheme = $parsedUrl['scheme'] ?? null;
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("Invalid URL scheme. Only HTTP and HTTPS are allowed.");
        }

        $host = $parsedUrl['host'] ?? null;
        if ($host === null) {
            throw new InvalidArgumentException("Invalid URL structure.");
        }

        // 2. Resolve hostname to IP address
        $ip = gethostbyname($host);
        if ($ip === $host) {
            throw new RuntimeException("Could not resolve host: {$host}");
        }

        // 3. Block private and loopback IP ranges
        if ($this->isPrivateIp($ip)) {
            throw new InvalidArgumentException("Access to internal IP range is restricted.");
        }

        // 4. Safe HTTP Request execution (using the resolved IP directly to prevent DNS rebinding)
        $port = $parsedUrl['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path = $parsedUrl['path'] ?? '/';
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$scheme}://{$ip}{$path}{$query}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Host: {$host}"]); // Restore Host header for routing
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $result = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new RuntimeException("HTTP Request failed: " . curl_error($ch));
        }
        
        curl_close($ch);
        return (string)$result;
    }

    private function isPrivateIp(string $ip): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return true; // Block invalid IP addresses
        }

        // Loopback: 127.0.0.0/8
        if (($ipLong & 0xFF000000) === 0x7F000000) {
            return true;
        }

        // Private IPv4 ranges (RFC 1918)
        // 10.0.0.0/8
        if (($ipLong & 0xFF000000) === 0x0A000000) {
            return true;
        }
        // 172.16.0.0/12
        if (($ipLong & 0xFFF00000) === 0xAC100000) {
            return true;
        }
        // 192.168.0.0/16
        if (($ipLong & 0xFFFF0000) === 0xC0A80000) {
            return true;
        }

        // Link-local: 169.254.0.0/16 (AWS / Cloud metadata)
        if (($ipLong & 0xFFFF0000) === 0xA9FE0000) {
            return true;
        }

        // Unspecified/Shared: 0.0.0.0/8, 100.64.0.0/10
        if (($ipLong & 0xFF000000) === 0x00000000) {
            return true;
        }

        return false;
    }
}
```

---

<a id="file-upload-vulnerabilities"></a>
## Vulnerabilidades de subida de archivos

Permitir a los usuarios subir archivos crea riesgos de seguridad significativos si el proceso de subida no está protegido:
1. **Ejecución remota de código (RCE):** El atacante sube un script PHP (por ejemplo, `backdoor.php`), accede a la ruta del archivo directamente a través del navegador y ejecuta comandos de terminal.
2. **Salto de directorio (Directory Traversal):** Utilizar un nombre de archivo como `../../index.php` para sobrescribir archivos de código de la aplicación.
3. **Suplantación de MIME (MIME Spoofing):** Cambiar la extensión del archivo de un script a `.jpg` o `.png`, manteniendo el contenido interno como PHP.
4. **XSS mediante SVG (SVG XSS):** Subir un archivo `.svg` que contiene JavaScript malicioso en línea. Cuando se sirve en línea, el navegador ejecuta el script bajo el alcance del origen de la aplicación.

---

<a id="secure-file-upload"></a>
## Implementación de subida de archivos segura

Para asegurar las subidas en Laravel, debemos imponer reglas de validación estrictas:
- Validar los archivos utilizando **tipos MIME** en lugar de extensiones.
- Generar un **nombre de archivo aleatorio** (por ejemplo, UUID o hash) para evitar saltos de directorio y conflictos de ejecución.
- Almacenar el archivo **fuera de la raíz web pública** utilizando almacenamiento aislado en la nube (como Amazon S3 o carpetas privadas).

```php
// app/Http/Controllers/UserMediaController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UserMediaController
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'application/pdf'
    ];

    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

    public function upload(Request $request): JsonResponse
    {
        $file = $request->file('avatar');

        if ($file === null || !$file->isValid()) {
            return response()->json(['error' => 'No valid file uploaded.'], 400);
        }

        // 1. Strict size check
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return response()->json(['error' => 'File size exceeds 5MB limit.'], 400);
        }

        // 2. Strict MIME type content analysis (bypasses extension spoofing)
        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            return response()->json(['error' => 'Invalid file format.'], 400);
        }

        // 3. Generate secure, random filename (prevents Directory Traversal)
        $extension = $file->getClientOriginalExtension();
        
        // Safety: Enforce safe extensions matching the MIME type
        $safeExtension = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => throw new InvalidArgumentException("Unsupported MIME type.")
        };

        $secureFilename = Str::uuid()->toString() . '.' . $safeExtension;

        // 4. Store outside the public web root (using private local or cloud storage disk)
        // This ensures the web server (Apache/Nginx) never executes the file
        $path = $file->storeAs('avatars/private', $secureFilename, 'local');

        return response()->json([
            'status' => 'success',
            'filename' => $secureFilename,
            'path' => $path
        ]);
    }
}
```

---

## Errores comunes

1. **Confiar en `getClientOriginalExtension`:** Utilizar directamente las extensiones originales del usuario, lo que permite a los atacantes subir archivos con nombres como `script.php.png` or `script.php`.
2. **Validación de DNS débil:** Validar el nombre de host con expresiones regulares en lugar de resolver su dirección IP, lo que hace que la aplicación sea vulnerable al rebote de DNS.
3. **Directorios de subida públicos:** Almacenar las subidas de archivos dentro de `public/uploads` y permitir la ejecución directa por HTTP de scripts PHP en esas carpetas.
4. **Permitir subidas de SVG como imágenes:** Permitir subidas de SVG sin restricciones sin ejecutar filtros de sanitización limpios, creando un vector de XSS almacenado.

---

## Lista de verificación

1. **Verificación de host:** ¿Su clase de obtención de URL verifica la dirección IP resuelta o solo valida la estructura de la cadena?
2. **Aislamiento de almacenamiento:** ¿Sus archivos subidos se guardan en un directorio ejecutable dentro de la raíz web, o se guardan fuera de la carpeta pública o en S3?
3. **Análisis del tipo MIME:** ¿Está verificando el tipo de archivo mediante `finfo` de PHP o `getMimeType()` de Laravel, o simplemente leyendo la extensión original?
4. **Sanitización del nombre de archivo:** ¿Está conservando los nombres de archivo originales o está generando cadenas UUID/hash aleatorias?

---

## Resumen

El SSRF y las subidas inseguras amenazan la integridad a nivel de servidor. Mitigue el **SSRF** limitando los protocolos a HTTP/HTTPS y verificando que las IPs resueltas no se mapeen a redes privadas (RFC 1918). Proteja las **subidas de archivos** analizando los tipos MIME del contenido del archivo, renombrando los archivos con hashes aleatorios y almacenándolos fuera de la raíz web pública para evitar la ejecución remota de código.

---

## Cuestionario de autoevaluación

### Pregunta 1: ¿Por qué es crítico resolver un nombre de host a su dirección IP antes de validarlo para SSRF?
- A) Porque las direcciones IP son más rápidas de obtener que los dominios.
- B) Para prevenir ataques de rebote de DNS (DNS Rebinding), donde un atacante cambia el registro de resolución DNS del dominio para que apunte a una IP interna (como `127.0.0.1`) después de que la validación ha sido aprobada.
- C) Porque la extensión Curl de PHP no admite dominios.

<details>
<summary>Haga clic para ver la respuesta</summary>

**Respuesta: B**
En un ataque de rebote de DNS (DNS Rebinding), el dominio se resuelve inicialmente a una IP pública para pasar la validación del host. Durante la ejecución, el atacante actualiza el registro DNS para resolver a una IP interna (como localhost o servicios de metadatos). Resolver la IP una vez y consultar esa IP directamente elimina esta ventana de vulnerabilidad.
</details>

### Pregunta 2: ¿Cuál es el riesgo de guardar las subidas de los usuarios dentro del directorio `public` de Laravel sin deshabilitar la ejecución de PHP en la configuración de Nginx/Apache?
- A) Los archivos se corromperán automáticamente.
- B) Un atacante puede subir un script `.php` malicioso y ejecutarlo directamente cargando su URL en el navegador, lo que resulta en una ejecución remota de código (RCE).
- C) Desencadena errores de bloqueo de la base de datos.

<details>
<summary>Haga clic para ver la respuesta</summary>

**Respuesta: B**
Los servidores web están configurados por defecto para analizar y ejecutar cualquier archivo que termine en `.php`. Si un archivo subido permanece dentro de la raíz web pública, cualquiera puede acceder a él directamente, haciendo que el servidor web ejecute el código.
</details>

### Pregunta 3: ¿Qué atributo de archivo debe utilizarse para validar el tipo de archivo de forma segura?
- A) El tipo MIME del archivo determinado por el análisis de contenido (a través de `finfo` o la extensión fileinfo de PHP).
- B) La extensión del archivo obtenida de `getClientOriginalExtension()`.
- C) El tamaño del archivo en bytes.

<details>
<summary>Haga clic para ver la respuesta</summary>

**Respuesta: A**
Los nombres de archivo y las extensiones son cabeceras enviadas por el cliente que se pueden suplantar fácilmente. Analizar los bytes reales del archivo (firma del archivo/bytes mágicos) es la única forma segura de identificar su formato real.
</details>
