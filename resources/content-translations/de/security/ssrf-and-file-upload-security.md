---
title: "SSRF & Sichere Datei-Uploads: Verhindern von Server-Side Request Forgery und Remote Code Execution | DevSense"
description: "Sichern Sie Ihre Backend-Infrastruktur. Erfahren Sie, wie Sie Server-Side Request Forgery (SSRF) und schädliche Datei-Uploads verhindern, die zu Remote Code Execution (RCE) führen."
published: 2026-06-19
faq:
  - question: "Warum ist die Domainauflösung der Schlüssel zur Verhinderung von SSRF?"
    answer: "Angreifer können Hostnamen-Filter mithilfe von DNS-Rebinding oder Weiterleitungstechniken umgehen. Um dies zu verhindern, müssen Sie die Domain in ihre IP-Adresse auflösen, überprüfen, ob die IP nicht zu Loopback- oder privaten Subnetzen gehört, und dann die HTTP-Anfrage direkt an diese validierte IP-Adresse richten."
  - question: "Wie nutzt ein Angreifer unsichere Datei-Uploads aus, um RCE zu erlangen?"
    answer: "Wenn die Anwendung hochgeladene Dateien in einem öffentlich zugänglichen Verzeichnis (Web-Root) speichert und deren ursprüngliche PHP-Erweiterung (z. B. `shell.php`) beibehält, kann der Angreifer die Datei direkt über deren URL ausführen und so eine beliebige Codeausführung auf dem Server auslösen."
  - question: "Was ist ein SVG-XSS-Angriff?"
    answer: "SVG-Dateien sind XML-Dokumente, die HTML und Inline-JavaScript einbetten können. Wenn eine Anwendung Benutzern das Hochladen von SVG-Dateien gestattet und diese im Browser inline anzeigt, wird jedes im SVG eingebettete JavaScript unter der Domain der Anwendung ausgeführt, was zu Stored XSS führt."
---

# SSRF & Sichere Datei-Uploads: Verhindern von Server-Side Request Forgery und Remote Code Execution

Da sich Backend-Anwendungen mit externen APIs verbinden und Benutzern das Hochladen von Medien ermöglichen, legen sie direkte Kommunikationswege zu internen Servern und dem Dateisystem offen. Wenn diese Grenzen nicht gesichert werden, führt dies zu Server-Side Request Forgery (SSRF) und Remote Code Execution (RCE).

In diesem Leitfaden werden wir analysieren, wie SSRF- und Datei-Upload-Sicherheitslücken in PHP und Laravel auftreten, und robuste Filter erstellen, um sie zu sichern.

**Verwandte Leitfäden:** [Web Application Vulnerabilities & Mitigations](web-app-security) · [Observability & monitoring](observability-monitoring-laravel)

## Inhalt

* [Server-Side Request Forgery (SSRF)](#ssrf)
* [SSRF-Abhilfemaßnahmen in PHP](#ssrf-mitigation)
* [Sicherheitslücken beim Datei-Upload](#file-upload-vulnerabilities)
* [Sichere Implementierung von Datei-Uploads](#secure-file-upload)
* [Häufige Fehler](#common-mistakes)
* [Checkliste](#checklist)
* [Zusammenfassung](#summary)
* [Selbsttest-Quiz](#self-test-quiz)

---

<a id="ssrf"></a>
## Server-Side Request Forgery (SSRF)

**Server-Side Request Forgery (SSRF)** tritt auf, wenn eine Anwendung eine vom Benutzer bereitgestellte Remote-URL abruft, ohne das Zielverzeichnis zu validieren. 

Da die Anfrage vom Backend-Server stammt, kann der Angreifer den Server als Proxy verwenden, um:
- interne Netzwerke zu scannen (z. B. `http://10.0.0.5:80`).
- auf interne Mikroservices zuzugreifen, denen eine Authentifizierung fehlt (z. B. Redis auf `http://127.0.0.1:6379`).
- auf Cloud-Metadaten-Endpunkte zuzugreifen (z. B. `http://169.254.169.254/latest/meta-data/` auf AWS/OpenStack), um temporäre IAM-Sicherheitsanmeldedaten abzurufen.

---

<a id="ssrf-mitigation"></a>
## SSRF-Abhilfemaßnahmen in PHP

Um SSRF zu verhindern, müssen wir sowohl das Protokollschema als auch die aufgelöste IP-Adresse validieren, um sicherzustellen, dass sie nicht auf Loopback- oder private Netzwerke verweisen.

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
## Sicherheitslücken beim Datei-Upload

Das Ermöglichen von Datei-Uploads durch Benutzer birgt erhebliche Sicherheitsrisiken, wenn der Upload-Prozess nicht gesichert ist:
1. **Remote Code Execution (RCE):** Der Angreifer lädt ein PHP-Skript hoch (z. B. `backdoor.php`), greift direkt über den Browser auf den Dateipfad zu und führt Terminalbefehle aus.
2. **Directory Traversal (Pfadüberquerung):** Verwendung eines Dateinamens wie `../../index.php`, um Anwendungscode-Dateien zu überschreiben.
3. **MIME-Spoofing:** Ändern der Dateiendung eines Skripts in `.jpg` oder `.png`, während die interne Payload als PHP beibehalten wird.
4. **SVG-XSS:** Hochladen einer `.svg`-Datei, die bösartiges Inline-JavaScript enthält. Wenn diese inline ausgeliefert wird, führt der Browser das Skript unter dem Origin-Scope der Anwendung aus.

---

<a id="secure-file-upload"></a>
## Sichere Implementierung von Datei-Uploads

Um Uploads in Laravel zu sichern, müssen wir strenge Validierungsregeln durchsetzen:
- Validieren Sie Dateien anhand von **MIME-Typen** statt anhand von Erweiterungen.
- Generieren Sie einen **zufälligen Dateinamen** (z. B. UUID oder Hash), um Directory Traversal und Konflikte bei der Ausführung zu verhindern.
- Speichern Sie die Datei **außerhalb des öffentlichen Web-Roots** unter Verwendung von isoliertem Cloud-Speicher (wie Amazon S3 oder privaten Ordnern).

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
## Häufige Fehler

1. **Vertrauen auf `getClientOriginalExtension`:** Die direkte Verwendung der ursprünglichen Benutzer-Erweiterungen, was Angreifern das Hochladen von Dateien namens `script.php.png` or `script.php` ermöglicht.
2. **Schwache DNS-Validierung:** Validierung des Hostnamens mit regulären Ausdrücken statt der Auflösung der IP-Adresse, wodurch die Anwendung anfällig für DNS-Rebinding wird.
3. **Öffentliche Upload-Verzeichnisse:** Speichern von hochgeladenen Dateien innerhalb von `public/uploads` und Zulassen der direkten HTTP-Ausführung von PHP-Skripten in diesen Ordnern.
4. **Zulassen von SVG-Uploads als Bilder:** Zulassen uneingeschränkter SVG-Uploads ohne Ausführung von Bereinigungsfiltern, was einen Vektor für Stored XSS erzeugt.

---

<a id="checklist"></a>
## Checkliste

1. **Host-Verifizierung:** Überprüft Ihre Klasse zum Abrufen von URLs die aufgelöste IP-Adresse oder validiert sie nur die String-Struktur?
2. **Speicherisolation:** Werden Ihre Uploads in einem ausführbaren Verzeichnis innerhalb des Web-Roots gespeichert oder außerhalb des öffentlichen Ordners bzw. auf S3 abgelegt?
3. **MIME-Typ-Analyse:** Überprüfen Sie den Dateityp mit PHPs `finfo` oder Laravels `getMimeType()` oder lesen Sie nur die ursprüngliche Erweiterung aus?
4. **Dateinamen-Bereinigung:** Behalten Sie die ursprünglichen Dateinamen bei oder generieren Sie zufällige UUID-/Hash-Strings?

---

<a id="summary"></a>
## Zusammenfassung

SSRF und unsichere Uploads drohen den Server auf Systemebene zu kompromittieren. Minimieren Sie **SSRF**, indem Sie Protokolle auf HTTP/HTTPS beschränken und überprüfen, ob aufgelöste IPs nicht auf private Netzwerke verweisen (RFC 1918). Sichern Sie **Datei-Uploads**, indem Sie MIME-Typen von Dateiinhalten analysieren, Dateien in zufällige Hashes umbenennen und sie außerhalb des öffentlichen Web-Roots speichern, um Remote Code Execution zu verhindern.

---

<a id="self-test-quiz"></a>
## Selbsttest-Quiz

### Frage 1: Warum ist die Auflösung eines Hostnamens in seine IP-Adresse vor der Validierung auf SSRF kritisch?
- A) Weil IP-Adressen schneller abzurufen sind als Domains.
- B) Um DNS-Rebinding-Angriffe zu verhindern, bei denen ein Angreifer den DNS-Eintrag der Domain so ändert, dass er auf eine interne IP verweist (wie `127.0.0.1`) nach dem die Validierung bestanden wurde.
- C) Weil PHPs Curl-Erweiterung keine Domains unterstützt.

<details>
<summary>Klicken Sie hier, um die Antwort anzuzeigen</summary>

**Antwort: B**
Bei einem DNS-Rebinding-Angriff löst die Domain anfangs in eine öffentliche IP auf, um die Host-Validierung zu bestehen. Während der Ausführung aktualisiert der Angreifer den DNS-Eintrag so, dass er in eine interne IP auflöst (wie Localhost oder Metadatendienste). Die einmalige Auflösung der IP und das direkte Abfragen dieser IP eliminiert dieses Sicherheitsfenster.
</details>

### Frage 2: Welches Risiko besteht darin, Benutzer-Uploads im `public`-Verzeichnis von Laravel zu speichern, ohne die PHP-Ausführung in der Nginx-/Apache-Konfiguration zu deaktivieren?
- A) Die Dateien werden automatisch beschädigt.
- B) Ein Angreifer kann ein schädliches `.php`-Skript hochladen und es direkt durch Aufrufen seiner URL im Browser ausführen, was zu Remote Code Execution (RCE) führt.
- C) Es löst Fehler bei der Datenbanksperrung aus.

<details>
<summary>Klicken Sie hier, um die Antwort anzuzeigen</summary>

**Antwort: B**
Webserver sind standardmäßig so konfiguriert, dass sie jede Datei analysieren und ausführen, die auf `.php` endet. Wenn eine hochgeladene Datei innerhalb des öffentlichen Web-Roots verbleibt, kann jeder direkt darauf zugreifen, was den Webserver veranlasst, den Code auszuführen.
</details>

### Frage 3: Welches Dateiattribut muss verwendet werden, um den Dateityp sicher zu validieren?
- A) Der durch Inhaltsanalyse (über `finfo` oder PHPs fileinfo-Erweiterung) ermittelte MIME-Typ der Datei.
- B) Die Dateiendung aus `getClientOriginalExtension()`.
- C) Die Dateigröße in Byte.

<details>
<summary>Klicken Sie hier, um die Antwort anzuzeigen</summary>

**Antwort: A**
Dateinamen und -endungen sind vom Client gesendete Header, die leicht gefälscht werden können. Die Analyse der tatsächlichen Bytes der Datei (Dateisignatur/Magic Bytes) ist der einzige sichere Weg, ihr tatsächliches Format zu identifizieren.
</details>
