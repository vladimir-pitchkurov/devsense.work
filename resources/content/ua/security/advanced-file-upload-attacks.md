---
title: "Просунуті атаки через завантаження файлів: методи обходу та захист"
description: "Детальний посібник для розробників щодо уязвимостей завантаження файлів, включаючи обхід MIME-типів, поліглоти, Zip Slip, SVG XSS, PDF SSRF та безпечну реалізацію на Laravel."
published: 2026-07-09
faq:
  - question: "Чому перевірка MIME-типу на стороні клієнта є небезпечною?"
    answer: "Клієнтські перевірки MIME-типу та HTTP-заголовок Content-Type повністю контролюються клієнтом. Зловмисники можуть перехопити запит за допомогою проксі (наприклад, Burp Suite) і змінити цей заголовок (наприклад, з 'application/x-php' на 'image/png'), щоб обійти прості фільтри бекенду."
  - question: "Що таке поліглот-зображення і як воно виконує код?"
    answer: "Зображення-поліглот — це файл, який є коректним для кількох форматів одночасно (наприклад, валідне зображення та робочий PHP-скрипт). Код впроваджується в метадані або коментарі зображення. Якщо сервер збереже його в веб-директорії з розширенням .php, код виконається."
  - question: "Як запобігти XSS-атакам через завантаження SVG-файлів?"
    answer: "Файли SVG — це XML-документи, тому вони можуть містити вбудований JavaScript. Для запобігання XSS слід очищати SVG (видаляючи скрипти та обробники подій), конвертувати SVG у растровий формат (наприклад, PNG/JPEG) під час завантаження або віддавати SVG з заголовком 'Content-Disposition: attachment'."
---

# Просунуті атаки через завантаження файлів: методи обходу та захист

Функціонал завантаження файлів є стандартним для багатьох веб-додатків. Однак це також один із найкритичніших векторів атак. Недостатній рівень безпеки тут може призвести до віддаленого виконання коду (RCE), розкриття локальних файлів (LFD), підробки запитів на стороні сервера (SSRF) та міжсайтового скриптингу (XSS).

У цьому посібнику ми детально розглянемо просунуті техніки обходу перевірок завантаження файлів та продемонструємо, як реалізувати надійний захист на стороні бекенду в PHP та Laravel.

---

## 1. Обхід перевірки MIME-типу (MIME-Type Check Bypass)

### Уразливість
Веб-додатки часто перевіряють заголовок `Content-Type`, надісланий браузером клієнта, щоб визначити безпечність файлу. Наприклад, при завантаженні зображення браузер автоматично надсилає:

```http
Content-Type: image/png
```

Якщо бекенд додатка перевіряє тільки цей заголовок, виникає серйозна діра в безпеці.

### Атака
Зловмисник перехоплює запит за допомогою проксі (наприклад, Burp Suite). Він завантажує шкідливий PHP-вебшелл (`shell.php`), але змінює заголовок `Content-Type`:

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="shell.php"
Content-Type: image/png

<?php system($_GET['cmd']); ?>
```

Бекенд зчитує змінений заголовок, вважає файл безпечним PNG-зображенням і дозволяє завантаження. Після збереження файлу в доступній веб-директорії зловмисник звертається до `http://vulnerable-app.com/uploads/shell.php?cmd=id` для виконання команд операційної системи.

---

## 2. Маніпуляція з Magic Bytes та сигнатурами

### Уразливість
Для захисту від простого обходу MIME-типу розробники використовують перевірку сигнатур (Magic Bytes). Кожен формат має унікальний набір початкових байтів:
- **GIF**: `GIF89a` (`47 49 46 38 39 61`)
- **PNG**: `\x89PNG\r\n\x1a\n` (`89 50 4E 47 0D 0A 1A 0A`)
- **JPEG**: `\xFF\xD8\xFF` (`FF D8 FF`)

Якщо сервер перевіряє лише початкові байти файлу, його все одно можна обдурити.

### Атака
Атакуючий додає сигнатуру дозволеного типу перед шкідливим кодом. Наприклад, створює текстовий файл, який починається з `GIF89a;`, після чого йде код PHP:

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="shell.gif.php"
Content-Type: image/gif

GIF89a;
<?php system($_GET['cmd']); ?>
```

Бекенд зчитує перші байти, бачить сигнатуру `GIF89a` і вважає файл зображенням. Якщо файл зберігається на сервері з розширенням `.php`, веб-сервер успішно виконає код.

---

## 3. Ін'єкції в метадані EXIF та файли-поліглоти

### Уразливість
Файл-поліглот є валідним одночасно для кількох форматів (наприклад, коректне зображення і водночас робочий PHP-скрипт). Просунуті валідатори використовують глибинні інструменти (наприклад, бібліотеки GD або ImageMagick) для перевірки структури зображення. Проте формати зображень дозволяють зберігати коментарі та метадані (наприклад, EXIF-теги у JPEG або текстові блоки в PNG).

### Атака
За допомогою інструменту `exiftool` атакуючий впроваджує PHP-код у коментар або метадані зображення, не ламаючи структуру файлу:

```bash
exiftool -Comment="<?php system($_GET['cmd']); ?>" exploit.jpg
```

Якщо бекенд перевіряє тільки валідність структури (наприклад, за допомогою `getimagesize()` у PHP), але зберігає файл із розширенням `.php` (або спрацьовує обхід подвійного розширення чи нуль-байта), інтерпретатор PHP виконає прихований код.

> [!WARNING]
> **Обхід стиснення та зміни розміру GD/ImageMagick**: Звичайна зміна розміру зображення на сервері не є гарантією безпеки. Розроблено спеціальні «GD-proof» поліглоти (особливо для PNG та JPEG), де корисне навантаження розміщується в областях даних зображення (наприклад, чанк PLTE або таблиці квантування), що залишаються неушкодженими після стиснення та зміни розміру.

---

## 4. Обхід путей через ім'я файлу (Path Traversal)

### Уразливість
Під час завантаження файлів браузер надсилає заголовок `Content-Disposition`, який містить початкове ім'я файлу. Якщо бекенд довіряє цьому імені та додає його напряму до шляху збереження, це веде до вразливості Path Traversal.

### Атака
Зловмисник змінює значення `filename`, додаючи послідовності виходу з директорії (`../`):

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="../../../../var/www/html/shell.php"
Content-Type: image/png

<?php system($_GET['cmd']); ?>
```

Якщо шлях для завантажень налаштований як `/var/www/html/storage/uploads/`, а додаток динамічно створює шлях без очищення імені, файл буде записано у `/var/www/html/shell.php` замість цільової папки. Це дозволяє виконувати шелл безпосередньо з кореня сайту.

---

## 5. Zip Slip (Path Traversal всередині архівів)

### Уразливість
Якщо додаток дозволяє завантаження стиснутих архівів (`.zip` або `.tar`), бекенд повинен їх розпакувати. Якщо під час розпакування шлях кожного файлу не перевіряється, додаток є вразливим до "Zip Slip".

### Атака
Зловмисник створює архів, де ім'я файлу містить послідовність виходу з директорії:

```text
Malicious Archive:
└── ../../../../var/www/html/shell.php
```

Якщо бекенд розпаковує архів без перевірки канонічного шляху для кожного елемента, веб-шелл буде збережено за межами тимчасової папки безпосередньо у веб-корінь.

---

## 6. Клієнтський XSS через завантаження SVG-файлів

### Уразливість
Формат SVG (Scalable Vector Graphics) заснований на XML. Оскільки це XML-документ, він може містити теги `<script>` та HTML-обробники подій.

### Атака
Якщо додаток дозволяє завантаження SVG (наприклад, для аватарів) і віддає його з заголовком `Content-Type: image/svg+xml`, браузер виконає вбудований скрипт.

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

При прямому відкритті такого файлу браузер виконає JavaScript у контексті домену додатка, що може призвести до викрадення сесій, токенів або конфіденційної інформації.

---

## 7. SSRF та LFD через обробку PDF-файлів

### Уразливість
Веб-сайти часто обробляють завантажені PDF-файли для генерації мініатюр або парсингу вмісту. Для цього використовуються такі утиліти, як Ghostscript, `pdftoppm` або конвертери PDF-в-HTML. Багато з цих інструментів мають відомі критичні вразливості виконання коду (наприклад, у Ghostscript).

### Атака
1. **Розкриття локальних файлів (LFD)**: Зловмисник завантажує PDF, що посилається на локальні ресурси (наприклад, `/etc/passwd` або `C:\Windows\win.ini`). Під час генерації прев'ю вміст цих файлів рендериться прямо на зображення мініатюри.
2. **Підробка запитів на стороні сервера (SSRF)**: Атакуючий додає посилання на внутрішні адреси (наприклад, `http://169.254.169.254/` або внутрішні панелі керування) у структуру PDF. При спробі обробки документа сервер надішле запити у внутрішню закриту мережу.

---

## 8. Приклад безпечної реалізації на PHP/Laravel (Багаторівневий захист)

Для надійного захисту завантаження файлів необхідно впроваджувати багаторівневу стратегію. Простої валідації на стороні бекенду недостатньо.

### Контролер безпечного завантаження в Laravel

Нижче наведено повноцінний і безпечний приклад реалізації контролера в Laravel.

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
     * Обробляє безпечне завантаження, валідацію та очищення зображень.
     */
    public function upload(SecureUploadRequest $request)
    {
        if (!$request->hasFile('uploaded_file')) {
            return response()->json(['error' => 'Файл не завантажено.'], 400);
        }

        $file = $request->file('uploaded_file');

        // 1. Суворий білий список дозволених розширень
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        
        if (!in_array($extension, $allowedExtensions)) {
            return response()->json(['error' => 'Недопустиме розширення файлу.'], 400);
        }

        // 2. Визначення реального MIME-типу на сервері (через fileinfo)
        $realMime = $file->getMimeType();
        $mimeToExtensionMap = [
            'image/jpeg'    => ['jpg', 'jpeg'],
            'image/png'     => ['png'],
            'image/gif'     => ['gif'],
            'image/svg+xml' => ['svg'],
        ];

        if (!isset($mimeToExtensionMap[$realMime]) || !in_array($extension, $mimeToExtensionMap[$realMime])) {
            return response()->json(['error' => 'Невідповідність MIME-типу та розширення.'], 400);
        }

        // 3. Генерація випадкового імені на базі UUID для захисту від Path Traversal та витоку даних
        $uuid = Str::uuid()->toString();
        $secureFilename = "{$uuid}.{$extension}";

        // 4. Окрема обробка файлів SVG (Очищення від XSS та XXE)
        if ($realMime === 'image/svg+xml') {
            try {
                $sanitizedSvg = $this->sanitizeSvg($file->getRealPath());
                
                // Зберігання у хмарі (наприклад, AWS S3) поза межами публічного веб-кореня
                Storage::disk('s3')->put("uploads/{$secureFilename}", $sanitizedSvg, [
                    'visibility' => 'private',
                    'ContentType' => 'image/svg+xml',
                    'ContentDisposition' => 'attachment; filename="' . $secureFilename . '"' // Примусове завантаження для запобігання XSS в браузері
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Помилка очищення SVG.'], 400);
            }
        } else {
            // 5. Перекодування растрових зображень (JPEG/PNG/GIF) для видалення метаданих EXIF та руйнування поліглотів
            try {
                // Використання бібліотеки Intervention Image (на базі GD або ImageMagick)
                $img = Image::make($file->getRealPath());

                // Реконструкція пікселів видаляє EXIF, текстові блоки та вбудовані PHP-payloads
                $stream = $img->stream($extension, 85); // перекодування з якістю 85%

                // Зберігання у приватному сховищі
                Storage::disk('s3')->put("uploads/{$secureFilename}", $stream->__toString(), [
                    'visibility' => 'private',
                    'ContentType' => $realMime,
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Помилка обробки зображення.'], 500);
            }
        }

        return response()->json([
            'message' => 'Файл завантажено безпечно.',
            'file_id' => $uuid,
            'filename' => $secureFilename
        ], 200);
    }

    /**
     * Очищає вміст SVG-файлу від тегів script та інлайнових подій.
     */
    private function sanitizeSvg(string $filePath): string
    {
        $xml = file_get_contents($filePath);
        if ($xml === false) {
            throw new \Exception('Не вдалося прочитати SVG-файл.');
        }

        $dom = new \DOMDocument();
        
        // Відключення завантаження зовнішніх сутностей (запобігання XXE)
        libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);

        if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOENT | LIBXML_DTDLOAD)) {
            libxml_clear_errors();
            throw new \Exception('Некоректна XML структура.');
        }

        // 1. Видалення всіх тегів <script>
        $scripts = $dom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $script = $scripts->item(0);
            $script->parentNode->removeChild($script);
        }

        // 2. Видалення всіх інлайнових обробників подій (onload, onclick тощо)
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

### Налаштування безпеки веб-сервера
Крім коду програми, веб-сервер має бути налаштований так, щоб не дозволяти виконання скриптів у папках завантаження.

#### Для Nginx
Блокування виконання PHP у папці завантажень:
```nginx
location ~* ^/storage/uploads/.*\.php$ {
    deny all;
    return 404;
}
```

#### Для Apache
Блокування автоіндексації та запуску PHP через `.htaccess`:
```apache
Options -Indexes
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8
php_flag engine off
```
