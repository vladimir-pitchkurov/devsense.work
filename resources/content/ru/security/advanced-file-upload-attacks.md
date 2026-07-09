---
title: "Продвинутые атаки через загрузку файлов: методы обхода и защита"
description: "Подробное руководство для разработчиков по уязвимостям загрузки файлов, включая обход MIME-типов, полиглоты, Zip Slip, SVG XSS, PDF SSRF и безопасную реализацию на Laravel."
published: 2026-07-09
faq:
  - question: "Почему проверка MIME-типа на стороне клиента небезопасна?"
    answer: "Проверки MIME-типа на стороне клиента и заголовок HTTP Content-Type полностью контролируются клиентом. Злоумышленники могут перехватить запрос на загрузку с помощью прокси (например, Burp Suite) и изменить этот заголовок (например, с 'application/x-php' на 'image/png'), чтобы обойти простые фильтры бэкенда."
  - question: "Что такое полиглот-изображение и как оно выполняет код?"
    answer: "Изображение-полиглот — это файл, валидный для нескольких форматов одновременно (например, корректное изображение и рабочий PHP-скрипт). Код внедряется в метаданные или комментарии изображения. Если сервер сохраняет его в веб-директории с расширением .php, код будет выполнен интерпретатором."
  - question: "Как предотвратить XSS-атаки через загрузку SVG-файлов?"
    answer: "Файлы SVG представляют собой XML-документы и могут содержать встроенный JavaScript. Чтобы предотвратить XSS, следует очищать SVG (удаляя скрипты и обработчики событий), конвертировать SVG в растровый формат (например, PNG/JPEG) при загрузке или отдавать SVG с заголовком 'Content-Disposition: attachment'."
---

# Продвинутые атаки через загрузку файлов: методы обхода и защита

Функционал загрузки файлов — стандартная возможность во многих веб-приложениях. Однако это также один из наиболее опасных векторов атак. Если безопасность этого функционала настроена некорректно, это может привести к удаленному исполнению кода (RCE), раскрытию локальных файлов (LFD), подделке серверных запросов (SSRF) и межсайтовому скриптингу (XSS).

В этом руководстве мы рассмотрим продвинутые техники обхода проверок при загрузке файлов и покажем, как реализовать надежную защиту на стороне бэкенда на PHP и Laravel.

---

## 1. Обход проверки MIME-типа (MIME-Type Check Bypass)

### Уязвимость
Веб-приложения часто проверяют заголовок `Content-Type`, отправляемый браузером клиента, чтобы определить, безопасен ли файл. Например, при загрузке изображения браузер автоматически отправляет:

```http
Content-Type: image/png
```

Если бэкенд приложения проверяет только этот заголовок, возникает критическая уязвимость.

### Атака
Атакующий перехватывает запрос на загрузку с помощью прокси-инструмента, такого как Burp Suite. Он загружает вредоносный PHP-вебшелл (`shell.php`), но изменяет заголовок `Content-Type`:

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="shell.php"
Content-Type: image/png

<?php system($_GET['cmd']); ?>
```

Поскольку бэкенд считывает измененный заголовок и считает, что файл является безопасным изображением PNG, он разрешает загрузку. После сохранения файла в публичной директории атакующий обращается по адресу `http://vulnerable-app.com/uploads/shell.php?cmd=id` для выполнения команд в операционной системе.

---

## 2. Манипуляции с Magic Bytes и сигнатурами файлов

### Уязвимость
Для борьбы с простым обходом MIME-типов разработчики часто внедряют проверку сигнатур (также называемых Magic Bytes). Каждый формат файлов начинается с определенной последовательности байтов:
- **GIF**: `GIF89a` (`47 49 46 38 39 61`)
- **PNG**: `\x89PNG\r\n\x1a\n` (`89 50 4E 47 0D 0A 1A 0A`)
- **JPEG**: `\xFF\xD8\xFF` (`FF D8 FF`)

Если сервер проверяет только первые байты файла, его все еще можно обойти.

### Атака
Атакующий добавляет сигнатуру легитимного файла в начало вредоносного кода. Например, он создает файл, начинающийся с сигнатуры `GIF89a;`, за которой следует код PHP:

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="shell.gif.php"
Content-Type: image/gif

GIF89a;
<?php system($_GET['cmd']); ?>
```

Бэкенд считывает первые байты, обнаруживает сигнатуру `GIF89a` и валидирует файл как GIF-изображение. Если файл сохраняется на сервере с расширением `.php`, веб-сервер выполняет встроенный PHP-код.

---

## 3. Внедрение метаданных EXIF и файлы-полиглоты

### Уязвимость
Файл-полиглот — это файл, который корректен сразу в нескольких форматах (например, является одновременно валидным изображением и исполняемым скриптом). Продвинутые валидаторы используют библиотеки обработки изображений (такие как GD или ImageMagick) для проверки внутренней структуры файла. Тем не менее, форматы изображений позволяют хранить текстовые комментарии или метаданные (например, EXIF-теги в JPEG или текстовые чанки в PNG).

### Атака
Используя инструмент `exiftool`, атакующий может внедрить PHP-payload в комментарий или поле метаданных изображения, не нарушая структуру самого файла:

```bash
exiftool -Comment="<?php system($_GET['cmd']); ?>" exploit.jpg
```

Если бэкенд проверяет только то, является ли файл валидным изображением (например, с помощью функции `getimagesize()` в PHP), но сохраняет его с расширением `.php` (или если работает обход двойных расширений/нуль-байта), парсер PHP выполнит код, спрятанный в метаданных.

> [!WARNING]
> **Обход сжатия и ресайза GD/ImageMagick**: Простое изменение размера изображения на сервере не гарантирует безопасность. Существуют специальные «GD-proof» полиглоты (особенно для PNG и JPEG), в которых полезная нагрузка помещается в такие области данных изображения (например, чанк PLTE или таблицы квантования), которые выдерживают сжатие и изменение размера.

---

## 4. Обход путей через имя файла (Path Traversal)

### Уязвимость
При загрузке файлов браузер передает заголовок `Content-Disposition`, содержащий оригинальное имя файла. Если бэкенд доверяет этому имени и склеивает его напрямую с путем к директории загрузки, возникает уязвимость Path Traversal.

### Атака
Атакующий изменяет параметр `filename`, добавляя последовательности обхода директорий (`../`):

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="../../../../var/www/html/shell.php"
Content-Type: image/png

<?php system($_GET['cmd']); ?>
```

Если директория для загрузок настроена как `/var/www/html/storage/uploads/`, а приложение динамически разрешает путь без фильтрации, файл запишется в `/var/www/html/shell.php` вместо целевой папки. Это позволяет атакующему разместить шелл в корне сайта или перезаписать важные конфигурационные файлы.

---

## 5. Zip Slip (Path Traversal внутри архивов)

### Уязвимость
Когда веб-приложения принимают сжатые архивы (`.zip` или `.tar`), бэкенд должен их распаковать. Если распаковщик не проверяет имена файлов внутри архива, система уязвима к атаке "Zip Slip".

### Атака
Атакующий создает архив, содержащий файлы с путями, использующими обход директорий. При распаковке эти файлы сохраняются вне целевой папки:

```text
Malicious Archive:
└── ../../../../var/www/html/shell.php
```

Если бэкенд использует стандартные функции распаковки без проверки канонического пути для каждого файла, веб-шелл будет записан прямо в корень веб-сервера.

---

## 6. Клиентский XSS через загрузку SVG-файлов

### Уязвимость
Формат SVG (Scalable Vector Graphics) основан на XML. Так как SVG-файлы представляют собой XML-документы, они могут содержать теги `<script>` и HTML-обработчики событий.

### Атака
Если приложение позволяет загружать SVG (например, в качестве аватара) и отдает их с заголовком `Content-Type: image/svg+xml`, браузер обработает файл как активный документ.

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

При переходе по прямой ссылке на этот SVG-файл встроенный JavaScript выполнится в контексте домена приложения, что приведет к краже сессий, куки или учетных данных.

---

## 7. SSRF и LFD через обработку PDF-файлов

### Уязвимость
Приложения часто обрабатывают загруженные PDF-файлы для генерации превью/миниатюр или парсинга содержимого. Для этого обычно используются утилиты вроде Ghostscript, `pdftoppm` или конвертеры в HTML. Многие из этих инструментов имеют долгую историю критических уязвимостей (например, в Ghostscript).

### Атака
1. **Раскрытие локальных файлов (LFD)**: Атакующий загружает PDF, содержащий ссылки на локальные файлы (например, `/etc/passwd` или `C:\Windows\win.ini`). В процессе генерации миниатюр парсер считывает их содержимое и выводит его прямо на изображение-превью.
2. **Подделка серверных запросов (SSRF)**: Атакующий внедряет в структуру PDF ссылки на внутренние ресурсы (например, метаданные облачного провайдера `http://169.254.169.254/` или внутренние панели администрирования). При попытке обработать документ сервер отправляет запросы в закрытую внутреннюю сеть.

---

## 8. Пример безопасной реализации на PHP/Laravel (Глубокая защита)

Для защиты функционала загрузки файлов необходимо использовать многоуровневый подход. Обычной валидации на бэкенде недостаточно.

### Безопасный контроллер загрузки в Laravel

Ниже представлена готовая к использованию и безопасная реализация контроллера в Laravel.

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
     * Обработка безопасной загрузки, валидации и рендеринга изображений.
     */
    public function upload(SecureUploadRequest $request)
    {
        if (!$request->hasFile('uploaded_file')) {
            return response()->json(['error' => 'Файл не загружен.'], 400);
        }

        $file = $request->file('uploaded_file');

        // 1. Строгий белый список расширений
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        
        if (!in_array($extension, $allowedExtensions)) {
            return response()->json(['error' => 'Недопустимое расширение файла.'], 400);
        }

        // 2. Определение реального MIME-типа на стороне сервера (через fileinfo)
        $realMime = $file->getMimeType();
        $mimeToExtensionMap = [
            'image/jpeg'    => ['jpg', 'jpeg'],
            'image/png'     => ['png'],
            'image/gif'     => ['gif'],
            'image/svg+xml' => ['svg'],
        ];

        if (!isset($mimeToExtensionMap[$realMime]) || !in_array($extension, $mimeToExtensionMap[$realMime])) {
            return response()->json(['error' => 'Несоответствие MIME-типа и расширения.'], 400);
        }

        // 3. Генерация случайного имени на основе UUID для предотвращения Path Traversal и утечек данных
        $uuid = Str::uuid()->toString();
        $secureFilename = "{$uuid}.{$extension}";

        // 4. Отдельная обработка файлов SVG (Очистка от XSS и XXE)
        if ($realMime === 'image/svg+xml') {
            try {
                $sanitizedSvg = $this->sanitizeSvg($file->getRealPath());
                
                // Сохранение во внешнее хранилище (например, AWS S3) за пределами веб-корня
                Storage::disk('s3')->put("uploads/{$secureFilename}", $sanitizedSvg, [
                    'visibility' => 'private',
                    'ContentType' => 'image/svg+xml',
                    'ContentDisposition' => 'attachment; filename="' . $secureFilename . '"' // Принудительное скачивание для предотвращения XSS
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Ошибка очистки SVG.'], 400);
            }
        } else {
            // 5. Перекодирование растровых изображений (JPEG/PNG/GIF) для удаления метаданных EXIF и разрушения полиглотов
            try {
                // Использование Intervention Image (на базе GD или ImageMagick)
                $img = Image::make($file->getRealPath());

                // Реконструкция пиксельной структуры удаляет EXIF и встроенные PHP-payload
                $stream = $img->stream($extension, 85); // перекодирование с качеством 85%

                // Сохранение в защищенное приватное облако
                Storage::disk('s3')->put("uploads/{$secureFilename}", $stream->__toString(), [
                    'visibility' => 'private',
                    'ContentType' => $realMime,
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Ошибка обработки изображения.'], 500);
            }
        }

        return response()->json([
            'message' => 'Файл успешно загружен.',
            'file_id' => $uuid,
            'filename' => $secureFilename
        ], 200);
    }

    /**
     * Очистка содержимого SVG от скриптов и inline-событий.
     */
    private function sanitizeSvg(string $filePath): string
    {
        $xml = file_get_contents($filePath);
        if ($xml === false) {
            throw new \Exception('Не удалось прочитать SVG-файл.');
        }

        $dom = new \DOMDocument();
        
        // Отключение внешних сущностей (защита от XXE)
        libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);

        if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOENT | LIBXML_DTDLOAD)) {
            libxml_clear_errors();
            throw new \Exception('Некорректная структура XML.');
        }

        // 1. Удаление всех тегов <script>
        $scripts = $dom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $script = $scripts->item(0);
            $script->parentNode->removeChild($script);
        }

        // 2. Удаление всех встроенных обработчиков событий (onload, onclick и т.д.)
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

### Настройка безопасности веб-сервера
В дополнение к коду приложения, необходимо настроить веб-сервер для предотвращения выполнения файлов в папках загрузки.

#### Для Nginx
Запрет выполнения PHP в папке загрузок:
```nginx
location ~* ^/storage/uploads/.*\.php$ {
    deny all;
    return 404;
}
```

#### Для Apache
Отключение автоиндексации и выполнения скриптов через `.htaccess`:
```apache
Options -Indexes
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8
php_flag engine off
```
