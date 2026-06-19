---
title: "SSRF и безопасная загрузка файлов: предотвращение Server-Side Request Forgery и Remote Code Execution | DevSense"
description: "Защитите свою бэкенд-инфраструктуру. Узнайте, как предотвратить подделку запросов на стороне сервера (SSRF) и загрузку вредоносных файлов, приводящую к удаленному выполнению кода (RCE)."
published: 2026-06-19
faq:
  - question: "Почему разрешение домена является ключом к предотвращению SSRF?"
    answer: "Злоумышленники могут обходить фильтры хостов, используя методы DNS Rebinding или перенаправления. Чтобы предотвратить это, необходимо разрешить домен в его IP-адрес, убедиться, что этот IP-адрес не относится к петлевым (loopback) или частным подсетям, и затем выполнить HTTP-запрос напрямую по этому валидированному IP-адресу."
  - question: "Как злоумышленник может использовать небезопасную загрузку файлов для получения RCE?"
    answer: "Если приложение сохраняет загруженные файлы в общедоступном каталоге (корневой папке веб-сервера) и сохраняет их исходное расширение PHP (например, `shell.php`), злоумышленник может выполнить файл напрямую, обратившись к нему по URL-адресу, что приведет к выполнению произвольного кода на сервере."
  - question: "Что такое XSS-атака через файлы SVG?"
    answer: "Файлы SVG представляют собой документы XML, в которые можно внедрять HTML-код и встроенный JavaScript. Если приложение позволяет пользователям загружать файлы SVG и отображает их внутри браузера (inline), любой встроенный в SVG JavaScript-код будет выполнен в контексте домена приложения, что приведет к хранимой XSS (Stored XSS)."
---

# SSRF и безопасная загрузка файлов: предотвращение Server-Side Request Forgery и Remote Code Execution

Когда бэкенд-приложения подключаются к внешним API и позволяют пользователям загружать медиафайлы, они открывают прямые пути взаимодействия с внутренними серверами и файловой системой. Отсутствие должной защиты этих границ приводит к уязвимостям подделки запросов на стороне сервера (SSRF) и удаленного выполнения кода (RCE).

В этом руководстве мы разберем, как уязвимости SSRF и небезопасной загрузки файлов возникают в PHP и Laravel, и построим надежные фильтры для защиты от них.

**Сопутствующие руководства:** [Уязвимости веб-приложений и методы их устранения](web-app-security) · [Наблюдаемость и мониторинг в Laravel](observability-monitoring-laravel)

## Содержание

* [Подделка запросов на стороне сервера (SSRF)](#ssrf)
* [Устранение уязвимости SSRF в PHP](#ssrf-mitigation)
* [Уязвимости загрузки файлов](#file-upload-vulnerabilities)
* [Реализация безопасной загрузки файлов](#secure-file-upload)
* [Распространенные ошибки](#common-mistakes)
* [Контрольный список](#checklist)
* [Резюме](#summary)
* [Тест для самопроверки](#self-test-quiz)

---

<a id="ssrf"></a>
## Подделка запросов на стороне сервера (SSRF)

**Подделка запроса на стороне сервера (Server-Side Request Forgery, SSRF)** возникает, когда приложение отправляет запрос на удаленный URL, предоставленный пользователем, без проверки конечного адреса назначения.

Поскольку запрос исходит от бэкенд-сервера, злоумышленник может использовать сервер в качестве прокси-сервера для:
- Сканирования внутренних сетей (например, `http://10.0.0.5:80`).
- Доступа к внутренним микросервисам, у которых отсутствует аутентификация (например, Redis на `http://127.0.0.1:6379`).
- Доступа к эндпоинтам метаданных облачных провайдеров (например, `http://169.254.169.254/latest/meta-data/` в AWS/OpenStack) для извлечения временных учетных данных безопасности IAM.

---

<a id="ssrf-mitigation"></a>
## Устранение уязвимости SSRF в PHP

Для предотвращения SSRF мы должны валидировать как протокол (схему URL), так и разрешенный IP-адрес, чтобы убедиться, что они не указывают на loopback-интерфейсы или частные подсети.

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
## Уязвимости загрузки файлов

Предоставление пользователям возможности загружать файлы несет серьезные риски для безопасности, если процесс загрузки не защищен должным образом:
1. **Удаленное выполнение кода (RCE):** Злоумышленник загружает PHP-скрипт (например, `backdoor.php`), обращается к файлу напрямую через браузер и выполняет команды терминала.
2. **Обход каталога (Directory Traversal):** Использование имени файла вида `../../index.php` для перезаписи файлов с исходным кодом приложения.
3. **Подмена MIME-типа (MIME Spoofing):** Изменение расширения файла скрипта на `.jpg` или `.png` при сохранении внутренней полезной нагрузки в формате PHP.
4. **XSS через SVG:** Загрузка файла `.svg`, содержащего вредоносный встроенный JavaScript. При отображении файла в браузере (inline) скрипт выполняется в контексте безопасности домена приложения.

---

<a id="secure-file-upload"></a>
## Реализация безопасной загрузки файлов

Для безопасной загрузки файлов в Laravel мы должны применять строгие правила валидации:
- Проверять файлы по их **MIME-типу**, а не по расширению.
- Генерировать **случайное имя файла** (например, UUID или хеш) для предотвращения обхода каталогов (Directory Traversal) и конфликтов имен.
- Хранить файлы **за пределами публичной корневой директории веб-сервера (web root)**, используя изолированное облачное хранилище (например, Amazon S3) или приватные локальные папки.

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
## Распространенные ошибки

1. **Доверие методу `getClientOriginalExtension`:** Использование оригинальных расширений пользователей напрямую, что позволяет злоумышленникам загружать файлы с именами вроде `script.php.png` или `script.php`.
2. **Слабая проверка DNS:** Валидация имени хоста с помощью регулярных выражений вместо разрешения его IP-адреса, что делает приложение уязвимым для DNS Rebinding.
3. **Публичные папки загрузки:** Сохранение загруженных файлов внутри директории `public/uploads` и разрешение прямого HTTP-выполнения PHP-скриптов в этих папках.
4. **Разрешение загрузки файлов SVG как изображений:** Допуск неконтролируемой загрузки SVG без использования строгих фильтров очистки (санитизации), что создает вектор для хранимой XSS.

---

<a id="checklist"></a>
## Контрольный список

1. **Проверка хоста:** Проверяет ли ваш класс отправки HTTP-запросов разрешенный IP-адрес, или же он проверяет только текстовую структуру строки URL?
2. **Изоляция хранилища:** Сохраняются ли загружаемые файлы в исполняемом каталоге внутри веб-корня, или же они сохраняются вне публичной папки или на S3?
3. **Анализ MIME-типа:** Проверяете ли вы тип файла с помощью PHP `finfo` или метода Laravel `getMimeType()`, или вы просто считываете исходное расширение?
4. **Санитизация имени файла:** Сохраняете ли вы оригинальные имена файлов, или генерируете случайные UUID/хеш-строки?

---

<a id="summary"></a>
## Резюме

SSRF и небезопасная загрузка файлов могут привести к полной компрометации сервера. Предотвращайте **SSRF**, ограничивая протоколы схемами HTTP/HTTPS и проверяя, что разрешенные IP-адреса не указывают на частные сети (RFC 1918). Обеспечивайте безопасность **загрузки файлов**, анализируя MIME-тип содержимого файлов, переименовывая файлы в случайные хеши и сохраняя их за пределами публичной корневой директории веб-сервера для предотвращения удаленного выполнения кода.

---

<a id="self-test-quiz"></a>
## Тест для самопроверки

### Вопрос 1: Почему разрешение имени хоста в его IP-адрес критически важно перед проверкой на предмет SSRF?
- A) Потому что IP-адреса запрашиваются быстрее, чем домены.
- B) Для предотвращения атак DNS Rebinding, при которых злоумышленник изменяет DNS-запись домена, указывая ее на внутренний IP-адрес (например, `127.0.0.1`), сразу после прохождения проверки.
- C) Поскольку PHP-расширение Curl не поддерживает домены.

<details>
<summary>Нажмите, чтобы увидеть ответ</summary>

**Ответ: B**
В атаке DNS Rebinding домен изначально разрешается в общедоступный IP-адрес для прохождения проверки хоста. Во время выполнения запроса злоумышленник обновляет DNS-запись домена так, чтобы она указывала на внутренний IP (например, localhost или службы метаданных). Однократное разрешение IP и выполнение запроса напрямую по этому IP-адресу устраняет эту уязвимость.
</details>

### Вопрос 2: Каков риск сохранения пользовательских файлов в каталоге `public` в Laravel без отключения выполнения PHP в настройках Nginx/Apache?
- A) Файлы автоматически повредятся.
- B) Злоумышленник может загрузить вредоносный `.php` скрипт и выполнить его напрямую, перейдя по его URL в браузере, что приведет к удаленному выполнению кода (RCE).
- C) Это вызывает ошибки блокировки базы данных.

<details>
<summary>Нажмите, чтобы увидеть ответ</summary>

**Ответ: B**
Веб-серверы по умолчанию настроены на обработку и выполнение любых файлов с расширением `.php`. Если загруженный файл остается в корневом каталоге веб-сервера, любой желающий может получить к нему прямой доступ, заставив веб-сервер выполнить код.
</details>

### Вопрос 3: Какой атрибут файла необходимо использовать для надежной проверки его типа?
- A) MIME-тип файла, определенный на основе анализа контента (с помощью `finfo` или расширения PHP fileinfo).
- B) Расширение файла, полученное из `getClientOriginalExtension()`.
- C) Размер файла в байтах.

<details>
<summary>Нажмите, чтобы увидеть ответ</summary>

**Ответ: A**
Имена файлов и расширения представляют собой заголовки, отправляемые клиентом, которые могут быть легко подделаны. Анализ фактических байтов файла (сигнатура файла / magic bytes) — единственный надежный способ определить его реальный формат.
</details>
