---
title: "SSRF и сигурни качвания на файлове: Предотвратяване на Server-Side Request Forgery и отдалечено изпълнение на код | DevSense"
description: "Защитете вашата бекенд инфраструктура. Научете как да предотвратите Server-Side Request Forgery (SSRF) и зловредно качване на файлове, водещо до отдалечено изпълнение на код (RCE)."
published: 2026-06-19
faq:
  - question: "Защо разрешаването на домейни (domain resolution) е от ключово значение за предотвратяване на SSRF?"
    answer: "Нападателите могат да заобиколят филтрите за имена на хостове, използвайки DNS Rebinding или техники за пренасочване. За да се предотврати това, трябва да разрешите домейна до неговия IP адрес, да проверите дали IP адресът не принадлежи на loopback или частни подмрежи и след това да извършите HTTP заявката директно към този валидиран IP адрес."
  - question: "Как нападателят експлоатира небезопасното качване на файлове, за да получи RCE?"
    answer: "Ако приложението съхранява качените файлове в публично достъпна директория (уеб корен) и запазва тяхното оригинално PHP разширение (напр. `shell.php`), нападателят може да изпълни файла директно, като достъпи неговия URL адрес, което задейства изпълнение на произволен код на сървъра."
  - question: "Какво представлява SVG XSS атаката?"
    answer: "SVG файловете са XML документи, в които може да се вгражда HTML и вграден (inline) JavaScript. Ако дадено приложение позволява на потребителите да качват SVG файлове и ги показва директно в браузъра, всеки вграден JavaScript в SVG файла ще се изпълни в контекста на домейна на приложението, което води до съхранен XSS."
---

# SSRF и сигурни качвания на файлове: Предотвратяване на Server-Side Request Forgery и отдалечено изпълнение на код

Тъй като бекенд приложенията се свързват с външни API и позволяват на потребителите да качват медийни файлове, те излагат на риск директните комуникационни пътища към вътрешни сървъри и файловата система. Неуспехът да се защитят тези граници води до Server-Side Request Forgery (SSRF) и отдалечено изпълнение на код (Remote Code Execution - RCE).

В това ръководство ще анализираме как възникват уязвимостите за SSRF и качване на файлове в PHP и Laravel и ще изградим надеждни филтри за тяхната защита.

**Свързани ръководства:** [Уязвимости на уеб приложения и предотвратяване](web-app-security) · [Наблюдаемост и мониторинг](observability-monitoring-laravel)

## Съдържание

* [Server-Side Request Forgery (SSRF)](#ssrf)
* [Предотвратяване на SSRF в PHP](#ssrf-mitigation)
* [Уязвимости при качване на файлове](#file-upload-vulnerabilities)
* [Внедряване на сигурно качване на файлове](#secure-file-upload)
* [Често срещани грешки](#common-mistakes)
* [Контролен списък](#checklist)
* [Резюме](#summary)
* [Тест за самопроверка](#self-test-quiz)

---

<a id="ssrf"></a>
## Server-Side Request Forgery (SSRF)

**Server-Side Request Forgery (SSRF)** възниква, когато дадено приложение извлича отдалечен URL адрес, предоставен от потребителя, без да валидира крайната дестинация.

Тъй като заявката произлиза от бекенд сървъра, нападателят може да използва сървъра като прокси за:
- Сканиране на вътрешни мрежи (напр. `http://10.0.0.5:80`).
- Достъп до вътрешни микроуслуги, при които липсва удостоверяване (напр. Redis на `http://127.0.0.1:6379`).
- Достъп до крайни точки за метаданни в облака (напр. `http://169.254.169.254/latest/meta-data/` в AWS/OpenStack) за извличане на временни потребителски сесийни данни за сигурност (IAM credentials).

---

<a id="ssrf-mitigation"></a>
## Предотвратяване на SSRF в PHP

За да предотвратим SSRF, трябва да валидираме както схемата на протокола, така и разрешения IP адрес, за да гарантираме, че те не сочат към loopback (обратна връзка) или частни мрежи.

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
## Уязвимости при качване на файлове

Позволяването на потребителите да качват файлове създава сериозни рискове за сигурността, ако процесът по качване не е защитен:
1. **Отдалечено изпълнение на код (RCE):** Нападателят качва PHP скрипт (напр. `backdoor.php`), достъпва директорията с файла директно през браузъра и изпълнява конзолни команди.
2. **Обхождане на директории (Directory Traversal):** Използване на име на файл от типа `../../index.php` с цел презаписване на файлове от кодовата база на приложението.
3. **Заобикаляне на MIME типа (MIME Spoofing):** Промяна на разширението на файла на скрипта на `.jpg` или `.png`, като в същото време се запазва вътрешният полезен товар като PHP код.
4. **SVG XSS:** Качване на `.svg` файл, съдържащ зловреден вграден (inline) JavaScript. Когато се визуализира директно, браузърът изпълнява скрипта в контекста на произхода (origin scope) на приложението.

---

<a id="secure-file-upload"></a>
## Внедряване на сигурно качване на файлове

За да осигурим защита при качване на файлове в Laravel, трябва да наложим строги правила за валидация:
- Валидирайте файловете по техния **MIME тип**, а не по разширение.
- Генерирайте **произволно име на файла** (напр. UUID или хеш), за да предотвратите обхождане на директории (directory traversal) и конфликти при изпълнение.
- Съхранявайте файла **извън публичната уеб директория (web root)**, като използвате изолирано облачно пространство за съхранение (като Amazon S3 или частни папки).

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

<a id="common-mistakes"></a>
## Често срещани грешки

1. **Доверяване на `getClientOriginalExtension`:** Използване на оригиналното потребителско разширение директно, което позволява на нападателите да качват файлове с имена като `script.php.png` или `script.php`.
2. **Слаба DNS валидация:** Валидиране на името на хоста с регулярни изрази вместо разрешаване на неговия IP адрес, което прави приложението уязвимо към DNS rebinding.
3. **Публични директории за качване:** Съхраняване на качените файлове в `public/uploads` и разрешаване на директно HTTP изпълнение на PHP скриптове в тези папки.
4. **Разрешаване на качване на SVG файлове като изображения:** Позволяване на неограничено качване на SVG без филтри за цялостно пречистване, което създава предпоставка за съхранен XSS.

---

<a id="checklist"></a>
## Контролен списък

1. **Проверка на хоста:** Дали вашият клас за извличане на URL адреси проверява разрешения IP адрес, или валидира само структурата на низа?
2. **Изолация на съхранението:** Качените файлове записват ли се в изпълнима директория в рамките на уеб корена, или се съхраняват извън публичната папка или в S3?
3. **Анализ на MIME типа:** Проверявате ли типа на файла, използвайки PHP функцията `finfo` или метода `getMimeType()` на Laravel, или просто четете оригиналното разширение?
4. **Саниране на имена на файлове:** Запазвате ли оригиналните имена на файловете, или генерирате произволни UUID/хеш низове?

---

<a id="summary"></a>
## Резюме

SSRF и небезопасното качване на файлове застрашават сървъра от пълен пробив. Ограничете последствията от **SSRF**, като позволите само протоколите HTTP/HTTPS и проверявате дали разрешените IP адреси не сочат към частни мрежи (RFC 1918). Защитете **качването на файлове** чрез анализ на MIME типа на съдържанието, преименуване на файловете с произволни хешове и съхраняването им извън публичния уеб корен, за да предотвратите отдалечено изпълнение на код.

---

<a id="self-test-quiz"></a>
## Тест за самопроверка

### Въпрос 1: Защо разрешаването на името на хоста до неговия IP адрес е от решаващо значение преди валидирането му за SSRF?
- A) Тъй като IP адресите се извличат по-бързо от домейните.
- B) За да се предотвратят атаки от типа DNS Rebinding, при които нападателят променя записа за DNS резолюция на домейна, за да сочи към вътрешен IP адрес (като `127.0.0.1`), след като валидацията вече е преминала.
- C) Тъй като разширението Curl на PHP не поддържа домейни.

<details>
<summary>Кликнете, за да видите отговора</summary>

**Отговор: B**
При атака тип DNS Rebinding домейнът първоначално се разрешава до публичен IP адрес, за да премине валидацията на хоста. По време на изпълнението нападателят актуализира DNS записа, за да се разреши до вътрешен IP адрес (като localhost или услуги за метаданни). Разрешаването на IP адреса веднъж и изпращането на заявка директно към този IP адрес елиминира тази възможност за уязвимост.
</details>

### Въпрос 2: Какъв е рискът от записване на потребителски файлове в публичната (`public`) директория на Laravel без деактивиране на изпълнението на PHP в конфигурацията на Nginx/Apache?
- A) Файловете автоматично ще се повредят.
- B) Нападателят може да качи зловреден `.php` скрипт и да го изпълни директно, като зареди неговия URL адрес в браузъра, което води до отдалечено изпълнение на код (RCE).
- C) Това предизвиква грешки при заключване на базата данни.

<details>
<summary>Кликнете, за да видите отговора</summary>

**Отговор: B**
Уеб сървърите са конфигурирани по подразбиране да анализират и изпълняват всеки файл, завършващ на `.php`. Ако каченият файл остане в публичния уеб корен, всеки може да го достъпи директно, което кара уеб сървъра да изпълни съдържащия се в него код.
</details>

### Въпрос 3: Кой атрибут на файла трябва да се използва за сигурно валидиране на типа на файла?
- A) MIME типът на файла, определен чрез анализ на съдържанието (чрез `finfo` или разширението fileinfo в PHP).
- B) Разширението на файла от метода `getClientOriginalExtension()`.
- C) Размерът на файла в байтове.

<details>
<summary>Кликнете, за да видите отговора</summary>

**Отговор: A**
Имената на файловете и разширенията са заглавни части (headers), изпратени от клиента, които лесно могат да бъдат фалшифицирани. Анализирането на действителните байтове на файла (файлов подпис / magic bytes) е единственият сигурен начин за идентифициране на неговия реален формат.
</details>
