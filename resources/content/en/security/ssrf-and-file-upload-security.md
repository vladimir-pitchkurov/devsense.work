---
title: "SSRF & Secure File Uploads: Preventing Server-Side Request Forgery and Remote Code Execution | DevSense"
description: "Secure your backend infrastructure. Learn how to prevent Server-Side Request Forgery (SSRF) and malicious file uploads leading to Remote Code Execution (RCE)."
published: 2026-06-19
faq:
  - question: "Why is domain resolution key to preventing SSRF?"
    answer: "Attackers can bypass hostname filters using DNS Rebinding or redirect techniques. To prevent this, you must resolve the domain to its IP address, verify that the IP does not belong to loopback or private subnets, and then make the HTTP request directly to that validated IP address."
  - question: "How does an attacker exploit unsafe file uploads to gain RCE?"
    answer: "If the application stores uploaded files in a publicly accessible directory (web root) and retains their original PHP extension (e.g., `shell.php`), the attacker can execute the file directly by accessing its URL, triggering arbitrary code execution on the server."
  - question: "What is a SVG XSS attack?"
    answer: "SVG files are XML documents that can embed HTML and inline JavaScript. If an application allows users to upload SVG files and displays them inline in the browser, any embedded JavaScript inside the SVG will execute under the application's domain, resulting in Stored XSS."
---

# SSRF & Secure File Uploads: Preventing Server-Side Request Forgery and Remote Code Execution

As backend applications connect to external APIs and allow users to upload media, they expose direct communication pathways to internal servers and the file system. Failing to secure these boundaries leads to Server-Side Request Forgery (SSRF) and Remote Code Execution (RCE).

In this guide, we will analyze how SSRF and file upload vulnerabilities occur in PHP and Laravel, and build robust filters to secure them.

**Related guides:** [Web Application Vulnerabilities & Mitigations](web-app-security) · [Observability & monitoring](observability-monitoring-laravel)

## Contents

* [Server-Side Request Forgery (SSRF)](#ssrf)
* [SSRF Mitigation in PHP](#ssrf-mitigation)
* [File Upload Vulnerabilities](#file-upload-vulnerabilities)
* [Secure File Upload Implementation](#secure-file-upload)
* [Common Mistakes](#common-mistakes)
* [Checklist](#checklist)
* [Summary](#summary)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="ssrf"></a>
## Server-Side Request Forgery (SSRF)

**Server-Side Request Forgery (SSRF)** occurs when an application fetches a remote URL provided by the user without validating the target destination. 

Because the request originates from the backend server, the attacker can use the server as a proxy to:
- Scan internal networks (e.g. `http://10.0.0.5:80`).
- Access internal microservices that lack authentication (e.g. Redis on `http://127.0.0.1:6379`).
- Access cloud metadata endpoints (e.g. `http://169.254.169.254/latest/meta-data/` on AWS/OpenStack) to retrieve temporary IAM security credentials.

---

<a id="ssrf-mitigation"></a>
## SSRF Mitigation in PHP

To prevent SSRF, we must validate both the protocol scheme and the resolved IP address to ensure they do not point to loopback or private networks.

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
## File Upload Vulnerabilities

Allowing users to upload files creates significant security risks if the upload process is not secured:
1. **Remote Code Execution (RCE):** The attacker uploads a PHP script (e.g. `backdoor.php`), accesses the file path directly via the browser, and executes terminal commands.
2. **Directory Traversal:** Using a filename like `../../index.php` to overwrite application code files.
3. **MIME Spoofing:** Changing the file extension of a script to `.jpg` or `.png`, while keeping the internal payload as PHP.
4. **SVG XSS:** Uploading an `.svg` file containing malicious inline JavaScript. When served inline, the browser runs the script under the application's origin scope.

---

<a id="secure-file-upload"></a>
## Secure File Upload Implementation

To secure uploads in Laravel, we must enforce strict validation rules:
- Validate files using **MIME types** rather than extensions.
- Generate a **random filename** (e.g., UUID or Hash) to prevent directory traversal and execution conflicts.
- Store the file **outside of the public web root** using isolated cloud storage (like Amazon S3 or private folders).

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
## Common Mistakes

1. **Trusting `getClientOriginalExtension`:** Using original user extensions directly, which allows attackers to upload files named `script.php.png` or `script.php`.
2. **Weak DNS validation:** Validating the hostname with regex instead of resolving its IP address, rendering the application vulnerable to DNS rebinding.
3. **Public Upload Directories:** Storing file uploads inside `public/uploads` and allowing direct HTTP execution of PHP scripts in those folders.
4. **Permitting SVG Uploads as Images:** Allowing unrestricted SVG uploads without running clean sanitization filters, creating a stored XSS vector.

---

<a id="checklist"></a>
## Checklist

1. **Host Verification:** Does your URL-fetching class verify the resolved IP address, or does it only validate the string structure?
2. **Storage Isolation:** Are your uploads saved in an executable directory inside the web root, or are they saved outside the public folder or on S3?
3. **MIME type analysis:** Are you verifying the file type using PHP `finfo` or Laravel's `getMimeType()`, or just reading the original extension?
4. **Filename Sanitization:** Are you keeping original filenames, or are you generating random UUID/hash strings?

---

<a id="summary"></a>
## Summary

SSRF and insecure uploads threaten server-level compromise. Mitigate **SSRF** by restricting protocols to HTTP/HTTPS and verifying that resolved IPs do not map to private networks (RFC 1918). Secure **File Uploads** by analyzing file content MIME types, renaming files to random hashes, and storing them outside of the public web root to prevent Remote Code Execution.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: Why is resolving a hostname to its IP address critical before validating it for SSRF?
- A) Because IP addresses are faster to fetch than domains.
- B) To prevent DNS Rebinding attacks, where an attacker changes the domain's DNS resolution record to point to an internal IP (like `127.0.0.1`) after validation has passed.
- C) Because PHP's Curl extension does not support domains.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
In a DNS Rebinding attack, the domain initially resolves to a public IP to pass host validation. During execution, the attacker updates the DNS record to resolve to an internal IP (like localhost or metadata services). Resolving the IP once and querying that IP directly eliminates this window of vulnerability.
</details>

### Question 2: What is the risk of saving user uploads inside Laravel's `public` directory without disabling PHP execution in Nginx/Apache configuration?
- A) The files will automatically become corrupted.
- B) An attacker can upload a malicious `.php` script and execute it directly by loading its URL in the browser, resulting in Remote Code Execution (RCE).
- C) It triggers database locking errors.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Web servers are configured by default to parse and execute any file ending in `.php`. If an uploaded file remains inside the public web root, anyone can access it directly, causing the web server to execute the code.
</details>

### Question 3: Which file attribute must be used to validate the file type securely?
- A) The file's MIME type determined by content analysis (via `finfo` or PHP's fileinfo extension).
- B) The file extension from `getClientOriginalExtension()`.
- C) The file size in bytes.

<details>
<summary>Click to view the answer</summary>

**Answer: A**
Filenames and extensions are headers sent by the client that can be easily spoofed. Analyzing the actual bytes of the file (file signature/magic bytes) is the only secure way to identify its actual format.
</details>
