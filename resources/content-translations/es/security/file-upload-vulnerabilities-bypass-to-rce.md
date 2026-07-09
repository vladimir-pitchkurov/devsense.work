---
title: "Vulnerabilidades de subida de archivos: Evadir filtros para la ejecución remota de código (RCE) | DevSense"
description: "Domine los mecanismos de subida segura de archivos. Aprenda cómo los atacantes evaden comprobaciones en el cliente, explotan listas negras de extensiones, sobrescriben configuraciones de servidores web y cómo escribir código Laravel seguro."
published: 2026-07-09
faq:
  - question: "¿Por qué es más segura una lista blanca de extensiones que una lista negra?"
    answer: "Una lista negra intenta bloquear extensiones peligrosas conocidas (p. ej., .php, .exe) pero a menudo olvida extensiones ejecutables alternativas (p. ej., .phtml, .phar, .php5) o trucos de nomenclatura específicos del SO. Una lista blanca define estrictamente los formatos permitidos y seguros (p. ej., .jpg, .pdf) y rechaza cualquier otra cosa por defecto."
  - question: "¿Cómo funciona la evasión a través de Alternate Data Streams (ADS) de NTFS en Windows?"
    answer: "En sistemas Windows, agregar '::$DATA' al final de un nombre de archivo (como 'shell.php::$DATA') le indica a NTFS que escriba el contenido en el flujo de datos predeterminado de 'shell.php'. Al guardar, Windows descarta el sufijo '::$DATA', dejando un archivo 'shell.php' completamente ejecutable en el disco y evadiendo los filtros de listas negras simples."
  - question: "¿Cómo conduce a la ejecución de código la sobrescritura de un archivo .user.ini?"
    answer: "En entornos PHP CGI/FastCGI, subir un archivo '.user.ini' personalizado a un directorio de subidas permite a un atacante redefinir directivas de configuración de PHP para ese directorio. Al definir 'auto_prepend_file=image.png', el motor de PHP ejecuta automáticamente el código PHP embebido en 'image.png' cada vez que se accede a cualquier script PHP en ese directorio."
---

# Vulnerabilidades de subida de archivos: Evadir filtros para la ejecución remota de código (RCE)

Los formularios de subida de archivos se encuentran entre las características de mayor riesgo en las aplicaciones web. Cuando una aplicación permite a los usuarios subir archivos, abre una vía de acceso directo al sistema de archivos del servidor. Si el backend no valida correctamente los archivos subidos, un atacante puede subir una shell web, evadir las restricciones de ejecución y lograr la **ejecución remota de código (RCE)**.

En esta guía analizaremos la mecánica técnica de las subidas de archivos, las técnicas de evasión de filtros y cómo construir una canalización de validación de archivos robusta en PHP y Laravel.

---

## Contenido

* [Mecánica de Multipart Form-Data](#multipart-mechanics)
* [Evasión de comprobaciones en el lado del cliente](#client-side-bypass)
* [Evasión de filtros de extensiones](#extension-bypasses)
* [Sobrescritura de configuraciones del servidor web](#config-overrides)
* [Normalización de nombres de archivos en Windows](#windows-normalization)
* [Implementación segura en Laravel y PHP](#secure-implementation)
* [Prácticas comunes de endurecimiento del servidor](#server-hardening)
* [Lista de comprobación de seguridad](#checklist)

---

<a id="multipart-mechanics"></a>
## Mecánica de Multipart Form-Data

Al subir un archivo a través de HTTP, los navegadores utilizan el esquema de codificación `multipart/form-data`, definido en **RFC 7578**. Comprender esta estructura es crucial para entender las discrepancias entre diferentes analizadores (parsers).

### Estructura de una petición Multipart
Una petición típica de subida de archivos se ve así:

```http
POST /upload.php HTTP/1.1
Host: example.com
Content-Type: multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Length: 345

------WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Disposition: form-data; name="avatar"; filename="backdoor.php"
Content-Type: application/x-php

<?php system($_GET['cmd']); ?>
------WebKitFormBoundary7MA4YWxkTrZu0gW--
```

### Elementos críticos de Multipart:
1. **Parámetro Boundary:** El atributo `boundary` en la cabecera `Content-Type` define la cadena única utilizada para delimitar los campos del formulario.
2. **Doble guion:** En el cuerpo del mensaje, cada límite (boundary) va precedido de dos guiones (`--`). El límite final que marca el término de los datos también debe acabar con dos guiones (`--boundary--`).
3. **Discrepancias de los analizadores:** Los distintos lenguajes de programación y servidores web procesan los datos multipart de manera diferente.
   - Algunos analizadores toleran la falta de cabeceras o guiones mal formateados de manera muy permisiva.
   - Por ejemplo, se descubrió una vulnerabilidad en la popular biblioteca de multipart de Node.js **Multer**, donde omitir los dos guiones de cierre (`--`) provocaba que el analizador esperara indefinidamente más datos, causando una denegación de servicio (DoS).

---

<a id="client-side-bypass"></a>
## Evasión de comprobaciones en el lado del cliente

Muchos desarrolladores implementan la validación de archivos únicamente mediante JavaScript en el navegador para proporcionar una respuesta rápida al usuario. El filtrado típico limita el elemento input:

```html
<!-- Restricción en el lado del cliente -->
<input type="file" id="avatar" accept=".jpg, .png" onchange="validateFile()">
```

### El mecanismo de evasión
Las comprobaciones en el cliente son completamente inseguras porque el cliente está bajo el control directo del atacante. Un atacante puede evadir estos filtros de las siguientes formas:
1. **Deshabilitar JavaScript:** Utilizar las herramientas de desarrollador del navegador para desactivar por completo la ejecución de JS, permitiendo enviar cualquier archivo a través del formulario.
2. **Proxy de interceptación (p. ej., Burp Suite):**
   - Seleccione un archivo legítimo (p. ej., `profile.png`).
   - Envíe el formulario.
   - Intercepte la petición saliente en Burp Suite.
   - Modifique el parámetro filename a `shell.php`, cambie el `Content-Type` a `application/x-php` y reemplace el contenido binario de la imagen por el código PHP: `<?php phpinfo(); ?>`.

> [!WARNING]
> Nunca dependa de la validación en el cliente para la seguridad. Todos los filtros del cliente sirven únicamente para mejorar la experiencia de usuario. La seguridad debe aplicarse obligatoriamente en el servidor backend.

---

<a id="extension-bypasses"></a>
## Evasión de filtros de extensiones

Cuando los desarrolladores configuran filtros en el servidor, suelen basarse en la extensión del archivo. Esto se puede estructurar mediante una **lista negra** (bloquear extensiones dañinas conocidas) o una **lista blanca** (permitir solo extensiones seguras específicas).

### Evasión de listas negras
Las listas negras son intrínsecamente débiles. Los atacantes pueden evadir listas negras sencillas que bloquean `.php` de varias maneras:

1. **Extensiones ejecutables alternativas:**
   Dependiendo de la configuración del servidor web, se pueden ejecutar extensiones alternativas compatibles con PHP:
   - `.phtml`, `.php3`, `.php4`, `.php5`, `.php7`, `.phps`
   - `.phar` (archivo de PHP que puede desencadenar deserializaciones)
   - `.pht`

2. **Diferencias de mayúsculas y minúsculas:**
   Si la validación solo busca la cadena `.php` exacta en minúsculas, pero el entorno del servidor no distingue entre mayúsculas y minúsculas al abrir archivos, los desarrolladores pueden olvidar:
   - `.pHp`, `.Php`, `.PHp`, `.pHTML`

3. **Extensiones dobles y anidadas:**
   - **Extensiones dobles:** Si el servidor está mal configurado y procesa archivos que contienen `.php` en cualquier parte de su nombre (p. ej., Apache `AddHandler`), un atacante puede subir `shell.php.jpg`.
   - **Evasión de limpieza de texto:** Si el código intenta eliminar el término `.php` de forma no recursiva:
     `shell.p.phphp.hp` -> Eliminar `php` una sola vez dejará `shell.php`.

---

<a id="config-overrides"></a>
## Sobrescritura de configuraciones del servidor web

Si la aplicación utiliza una lista negra estricta pero permite subir archivos de configuración del servidor, un atacante puede alterar las directivas de ejecución para el directorio de subidas.

### 1. Sobrescritura en Apache (`.htaccess`)
Si el servidor Apache está configurado con `AllowOverride All` para la ruta de subidas, un atacante puede subir un archivo `.htaccess` personalizado:

```apache
# Forzar a que los archivos .png se ejecuten como PHP en .htaccess
AddType application/x-httpd-php .png
```
O bien:
```apache
<Files "logo.png">
    ForceType application/x-httpd-php
</Files>
```

Tras subir este archivo `.htaccess`, el atacante sube un archivo llamado `logo.png` con código PHP. Apache procesará `logo.png` como un script PHP y lo ejecutará cuando se acceda a su URL.

### 2. Sobrescritura en PHP CGI/FastCGI (`.user.ini`)
En servidores Nginx o IIS que emplean PHP-FPM o PHP CGI, no se procesan los archivos `.htaccess`. Sin embargo, PHP soporta archivos de configuración locales por carpeta llamados `.user.ini`.

Un atacante puede subir un `.user.ini` que contenga:

```ini
# Ejecutar código PHP integrado dentro de un archivo PNG
auto_prepend_file=avatar.png
```

Si el atacante sube después `avatar.png` (que incluye el payload) y accede a cualquier archivo `.php` legítimo existente en esa carpeta (incluso un archivo de inicio por defecto vacío), el motor PHP ejecutará el contenido de `avatar.png` en primer lugar.

---

<a id="windows-normalization"></a>
## Normalización de nombres de archivos en Windows

Cuando la aplicación corre sobre un servidor Windows (IIS o Apache en Windows), la creación de archivos sigue las reglas de normalización de la API de Win32 y NTFS. Esto da lugar a evasiones particulares.

### 1. Puntos y espacios al final del nombre
Windows elimina de forma automática los puntos y espacios finales al escribir un archivo en el disco.
- Si la aplicación valida las extensiones con una lista negra (p. ej., bloqueando `.php`), el atacante puede enviar `shell.php.` o `shell.php `.
- La comprobación mediante expresiones regulares evalúa `shell.php.` (que no equivale a `.php`) y autoriza la subida.
- El sistema de archivos Win32 escribe el archivo en el disco y normaliza el nombre a `shell.php`, dejándolo ejecutable.

### 2. NTFS Alternate Data Streams (ADS)
NTFS utiliza flujos de datos alternativos para almacenar metadatos. El flujo de datos principal predeterminado se denomina `::$DATA`.
- El atacante sube un archivo llamado `shell.php::$DATA`.
- La lógica del filtro evalúa la extensión como `.php::$DATA` (o lo aprueba porque busca el último punto).
- Al guardar el archivo, Windows procesa el nombre `shell.php` y escribe el contenido dentro del flujo principal de datos. El resultado es un archivo `shell.php` funcional en la raíz del sitio web.

---

<a id="secure-implementation"></a>
## Implementación segura en Laravel y PHP

Para blindar la subida de archivos, debe aplicar el principio de **defensa en profundidad**. No confíe en un único paso de validación.

### Controlador seguro de subida de archivos en Laravel

A continuación se detalla cómo implementar un controlador seguro en Laravel utilizando listas blancas estrictas, comprobación de tipo MIME y almacenamiento fuera de la raíz pública del servidor:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SecureUploadController extends Controller
{
    // 1. Lista blanca estricta de extensiones permitidas y sus tipos MIME asociados
    private const ALLOWED_TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'pdf'  => 'application/pdf',
    ];

    public function store(Request $request): JsonResponse
    {
        // Verificar si el archivo fue subido
        if (!$request->hasFile('document')) {
            return response()->json(['error' => 'No file uploaded.'], 400);
        }

        $file = $request->file('document');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return response()->json(['error' => 'Invalid or corrupted file.'], 400);
        }

        // 2. Validar tamaño del archivo (p. ej., Máx 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json(['error' => 'File size exceeds 5MB limit.'], 400);
        }

        // 3. Inspeccionar el contenido real del archivo para validar el tipo MIME (no confiar en las cabeceras del cliente)
        $realMimeType = $file->getMimeType();
        $originalName = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // 4. Validar que la extensión y el tipo MIME coincidan con la lista blanca
        if (!array_key_exists($extension, self::ALLOWED_TYPES)) {
            return response()->json(['error' => 'Unsupported file extension.'], 400);
        }

        if (self::ALLOWED_TYPES[$extension] !== $realMimeType) {
            return response()->json(['error' => 'MIME type and file extension mismatch.'], 400);
        }

        // 5. Generar un nombre de archivo completamente aleatorio (UUID o Hash criptográfico)
        // Esto neutraliza Path Traversal, trucos de normalización de Windows y colisiones de nombres.
        $safeName = Str::uuid()->toString() . '.' . $extension;

        // 6. Guardar FUERA de la raíz web pública (p. ej., disco privado local o bucket de S3 con acceso restringido)
        // Evite utilizar public_path(). Utilice almacenamiento local privado en su lugar.
        $path = $file->storeAs('uploads/secure_docs', $safeName, 'local');

        if ($path === false) {
            return response()->json(['error' => 'Failed to store file.'], 500);
        }

        return response()->json([
            'message'   => 'File uploaded securely.',
            'safe_name' => $safeName
        ], 201);
    }
}
```

---

<a id="server-hardening"></a>
## Prácticas comunes de endurecimiento del servidor

Incluso si el código de la aplicación cuenta con debilidades, la configuración de seguridad a nivel de servidor de aplicaciones e infraestructura puede evitar la ejecución de código dañino.

### 1. Deshabilitar la ejecución de PHP en carpetas de subida
Para Nginx, configure el bloque de servidor para bloquear solicitudes de ejecución de archivos PHP bajo el directorio de almacenamiento:

```nginx
# Deshabilitar ejecución de PHP en la ruta de subidas
location ~* ^/uploads/.*\.php$ {
    deny all;
    return 403;
}
```

Para Apache, añada estas reglas en el archivo de configuración del host para desactivar el procesamiento de scripts:

```apache
<Directory "/var/www/html/uploads">
    # Eliminar manejadores de scripts
    RemoveHandler .php .phtml .php3
    RemoveType .php .phtml .php3
    
    # O forzar el servicio como texto plano sin procesar
    ForceType text/plain
    
    # Deshabilitar sobrescritura de archivos htaccess en esta ruta
    AllowOverride None
</Directory>
```

### 2. Ejecutar el servidor web con mínimos privilegios
Asegúrese de que el usuario de ejecución del servidor web (p. ej., `www-data`, `nginx`) solo disponga de permisos de lectura y escritura en los directorios de subida específicamente autorizados, sin capacidad de escritura en el código fuente de la aplicación o archivos del sistema.

---

<a id="checklist"></a>
## Lista de comprobación de seguridad

| Capa de seguridad | Comprobación de implementación |
| :--- | :--- |
| **Listas blancas** | Valide las extensiones frente a una lista blanca estricta (nunca use listas negras). |
| **Tipo MIME** | Valide el tipo real del contenido con herramientas del servidor (p. ej., php `fileinfo`). |
| **Renombrar** | Genere nombres de archivo aleatorios (como UUID) durante el proceso de guardado. |
| **Ubicación de guardado**| Almacene los archivos cargados fuera del directorio raíz público de la web. |
| **Config. de servidor** | Impida la sobrescritura de archivos de configuración (`.htaccess`, `.user.ini`) en las carpetas. |
| **Bloqueo de ejecución**| Desactive el motor de ejecución de código para scripts en las rutas de subida. |
