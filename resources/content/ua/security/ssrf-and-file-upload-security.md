---
title: "SSRF та безпека завантаження файлів: Запобігання підробці запитів на стороні сервера та віддаленому виконанню коду | DevSense"
description: "Захистіть свою бекенд-інфраструктуру. Дізнайтеся, як запобігти підробці запитів на стороні сервера (SSRF) та завантаженню шкідливих файлів, що призводять до віддаленого виконання коду (RCE)."
published: 2026-06-19
faq:
  - question: "Чому розпізнавання доменних імен є ключовим для запобігання SSRF?"
    answer: "Зловмисники можуть обійти фільтри хостів за допомогою DNS Rebinding або методів перенаправлення. Щоб запобігти цьому, ви повинні розпізнати домен в його IP-адресу, перевірити, що IP-адреса не належить до локальних або приватних підмереж, а потім виконати HTTP-запит безпосередньо до цієї перевіреної IP-адреси."
  - question: "Як зловмисник може експлуатувати небезпечне завантаження файлів для отримання RCE?"
    answer: "Якщо додаток зберігає завантажені файли у загальнодоступній директорії (web root) і зберігає їхнє оригінальне розширення PHP (наприклад, `shell.php`), зловмисник може виконати файл безпосередньо, звернувшись за його URL-адресою, що запустить виконання довільного коду на сервері."
  - question: "Що таке XSS-атака через файли SVG?"
    answer: "Файли SVG — це документи XML, які можуть містити вбудований HTML та вбудований JavaScript. Якщо додаток дозволяє користувачам завантажувати файли SVG та відображає їх вбудованими (inline) у браузері, будь-який вбудований JavaScript всередині SVG виконається в контексті домену додатка, що призведе до накопиченого XSS (Stored XSS)."
---

# SSRF та безпека завантаження файлів: Запобігання підробці запитів на стороні сервера та віддаленому виконанню коду

Оскільки бекенд-додатки підключаються до зовнішніх API та дозволяють користувачам завантажувати медіафайли, вони відкривають прямі шляхи комунікації до внутрішніх серверів і файлової системи. Відсутність захисту цих меж призводить до підробки запитів на стороні сервера (SSRF) та віддаленого виконання коду (RCE).

У цьому посібнику ми проаналізуємо, як виникають вразливості SSRF та завантаження файлів у PHP та Laravel, і побудуємо надійні фільтри для їхнього захисту.

**Супутні посібники:** [Вразливості вебдодатків та їх усунення](web-app-security) · [Спостережуваність та моніторинг](observability-monitoring-laravel)

## Зміст

* [Підробка запитів на стороні сервера (SSRF)](#ssrf)
* [Запобігання SSRF у PHP](#ssrf-mitigation)
* [Вразливості завантаження файлів](#file-upload-vulnerabilities)
* [Реалізація безпечного завантаження файлів](#secure-file-upload)
* [Типові помилки](#common-mistakes)
* [Контрольний список](#checklist)
* [Підсумок](#summary)
* [Тест для самоперевірки](#self-test-quiz)

---

<a id="ssrf"></a>
## Підробка запитів на стороні сервера (SSRF)

**Підробка запитів на стороні сервера (Server-Side Request Forgery, SSRF)** виникає, коли додаток отримує дані за віддаленою URL-адресою, наданою користувачем, без перевірки кінцевого призначення. 

Оскільки запит надходить із бекенд-сервера, зловмисник може використовувати сервер як проксі-сервер, щоб:
- Сканувати внутрішні мережі (наприклад, `http://10.0.0.5:80`).
- Отримувати доступ до внутрішніх мікросервісів, які не мають автентифікації (наприклад, Redis на `http://127.0.0.1:6379`).
- Отримувати доступ до кінцевих точок метаданих хмари (наприклад, `http://169.254.169.254/latest/meta-data/` на AWS/OpenStack) для отримання тимчасових облікових даних безпеки IAM.

---

<a id="ssrf-mitigation"></a>
## Запобігання SSRF у PHP

Щоб запобігти SSRF, ми повинні перевірити як схему протоколу, так і розпізнану IP-адресу, щоб переконатися, що вони не вказують на локальні (loopback) або приватні мережі.

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
## Вразливості завантаження файлів

Дозвіл користувачам завантажувати файли створює значні ризики для безпеки, якщо процес завантаження не захищено:
1. **Віддалене виконання коду (Remote Code Execution, RCE):** Зловмисник завантажує PHP-скрипт (наприклад, `backdoor.php`), звертається до шляху файлу безпосередньо через браузер і виконує команди терміналу.
2. **Обхід каталогу (Directory Traversal):** Використання імені файлу на кшталт `../../index.php` для перезапису файлів коду додатка.
3. **Підміна типу MIME (MIME Spoofing):** Зміна розширення файлу скрипту на `.jpg` або `.png` при збереженні внутрішнього корисного навантаження у вигляді PHP-коду.
4. **SVG XSS:** Завантаження файлу `.svg`, який містить шкідливий вбудований JavaScript. При відображенні вбудованим у браузері, скрипт виконується в межах контексту безпеки (origin scope) додатка.

---

<a id="secure-file-upload"></a>
## Реалізація безпечного завантаження файлів

Щоб забезпечити безпеку завантажень у Laravel, ми повинні застосувати суворі правила валідації:
- Валідуйте файли за **типом MIME**, а не за розширеннями.
- Генеруйте **випадкове ім'я файлу** (наприклад, UUID або хеш), щоб запобігти обходу каталогу та конфліктам виконання.
- Зберігайте файл **поза межами загальнодоступної вебдиректорії (web root)**, використовуючи ізольовано хмарне сховище (наприклад, Amazon S3) або приватні папки.

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

## Типові помилки

1. **Довіра до `getClientOriginalExtension`:** Використання оригінальних розширень користувача безпосередньо, що дозволяє зловмисникам завантажувати файли з іменами на кшталт `script.php.png` або `script.php`.
2. **Слабка валідація DNS:** Валідація імені хоста за допомогою регулярних виразів замість розпізнавання його IP-адреси, що робить додаток вразливим до атак DNS rebinding.
3. **Публічні каталоги завантаження:** Збереження завантажених файлів всередині `public/uploads` та дозвіл на безпосереднє HTTP-виконання PHP-скриптів у цих папках.
4. **Дозвіл на завантаження SVG як зображень:** Дозвіл на необмежене завантаження файлів SVG без застосування фільтрів очищення (sanitization), що створює вектор для накопиченого XSS.

---

## Контрольний список

1. **Перевірка хоста:** Чи перевіряє ваш клас отримання URL-адрес розпізнану IP-адресу, чи він лише валідує структуру рядка?
2. **Ізоляція сховища:** Чи зберігаються ваші завантаження у виконуваній директорії всередині web root, чи вони зберігаються поза публічною папкою або на S3?
3. **Аналіз типу MIME:** Чи перевіряєте ви тип файлу за допомогою PHP `finfo` чи методу `getMimeType()` у Laravel, чи просто зчитуєте оригінальне розширення?
4. **Очищення імен файлів:** Чи зберігаєте ви оригінальні імена файлів, чи генеруєте випадкові рядки UUID/хешу?

---

## Підсумок

SSRF та незахищене завантаження файлів несуть загрозу компрометації на рівні сервера. Запобігайте **SSRF**, обмежуючи протоколи до HTTP/HTTPS та перевіряючи, що розпізнані IP-адреси не належать до приватних мереж (RFC 1918). Захищайте **завантаження файлів**, аналізуючи типи MIME вмісту файлів, перейменовуючи файли у випадкові хеші та зберігаючи їх поза межами загальнодоступної вебдиректорії для запобігання віддаленому виконанню коду.

---

<a id="self-test-quiz"></a>
## Тест для самоперевірки

### Запитання 1: Чому розпізнавання імені хоста в його IP-адресу є критично важливим перед його валідацією на SSRF?
- A) Тому що IP-адреси отримувати швидше, ніж домени.
- B) Для запобігання атакам DNS Rebinding, коли зловмисник змінює запис DNS-розділення домену так, щоб він вказував на внутрішню IP-адресу (наприклад, `127.0.0.1`) після проходження валідації.
- C) Тому що розширення Curl у PHP не підтримує домени.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: B**
В атаці DNS Rebinding домен спочатку розпізнається в публічну IP-адресу для проходження валідації хоста. Під час виконання зловмисник оновлює DNS-запис, щоб розпізнати його у внутрішню IP-адресу (наприклад, localhost або сервіси метаданих). Розпізнавання IP-адреси один раз і запит безпосередньо до цієї IP-адреси усуває це вікно вразливості.
</details>

### Запитання 2: Який ризик збереження завантажень користувачів у директорії `public` Laravel без вимкнення виконання PHP у конфігурації Nginx/Apache?
- A) Файли автоматично пошкодяться.
- B) Зловмисник може завантажити шкідливий скрипт `.php` і виконати його безпосередньо, відкривши його URL-адресу в браузері, що призведе до віддаленого виконання коду (RCE).
- C) Це викликає помилки блокування бази даних.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: B**
Вебсервери за замовчуванням налаштовані на аналіз та виконання будь-якого файлу, що закінчується на `.php`. Якщо завантажений файл залишається всередині загальнодоступної вебдиректорії, будь-хто може отримати до нього прямий доступ, змушуючи вебсервер виконати цей код.
</details>

### Запитання 3: Який атрибут файлу необхідно використовувати для безпечної валідації типу файлу?
- A) Тип MIME файлу, визначений шляхом аналізу вмісту (через `finfo` або розширення fileinfo у PHP).
- B) Розширення файлу з `getClientOriginalExtension()`.
- C) Розмір файлу в байтах.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: A**
Імена файлів та розширення — це заголовки, надіслані клієнтом, які можна легко підробити. Аналіз фактичних байтів файлу (сигнатури файлу / magic bytes) — єдиний безпечний спосіб визначити його справжній формат.
</details>
