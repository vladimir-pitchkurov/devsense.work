---
title: "SSRF & Caricamento Sicuro dei File: Prevenire Server-Side Request Forgery e Remote Code Execution | DevSense"
description: "Metti in sicurezza la tua infrastruttura di backend. Scopri come prevenire il Server-Side Request Forgery (SSRF) e i caricamenti di file dannosi che portano alla Remote Code Execution (RCE)."
published: 2026-06-19
faq:
  - question: "Perché la risoluzione del dominio è fondamentale per prevenire l'SSRF?"
    answer: "Gli aggressori possono aggirare i filtri del nome host utilizando tecniche di DNS Rebinding o di reindirizzamento. Per prevenire questo scenario, è necessario risolvere il dominio nel suo indirizzo IP, verificare che l'IP non appartenga a reti di loopback o subnet private e quindi effettuare la richiesta HTTP direttamente a quell'indirizzo IP convalidato."
  - question: "In che modo un aggressore sfrutta i caricamenti di file non sicuri per ottenere una RCE?"
    answer: "Se l'applicazione memorizza i file caricati in una directory accessibile pubblicamente (web root) e conserva la loro estensione PHP originale (ad esempio, `shell.php`), l'aggressore può eseguire direttamente il file accedendo al suo URL, attivando l'esecuzione di codice arbitrario sul server."
  - question: "Cos'è un attacco XSS tramite SVG?"
    answer: "I file SVG sono documenti XML che possono incorporare codice HTML e JavaScript inline. Se un'applicazione consente agli utenti di caricare file SVG e li visualizza inline nel browser, qualsiasi codice JavaScript incorporato all'interno dell'SVG verrà eseguito nel dominio dell'applicazione, causando un attacco Stored XSS."
---

# SSRF & Caricamento Sicuro dei File: Prevenire Server-Side Request Forgery e Remote Code Execution

Quando le applicazioni di backend si connettono ad API esterne e consentono agli utenti di caricare contenuti multimediali, espongono percorsi di comunicazione diretta verso i server interni e il file system. La mancata protezione di questi confini porta a vulnerabilità di Server-Side Request Forgery (SSRF) e Remote Code Execution (RCE).

In questa guida analizzeremo come si verificano le vulnerabilità SSRF e di caricamento dei file in PHP e Laravel e creeremo filtri robusti per proteggerle.

**Guide correlate:** [Vulnerabilità delle Web Application & Mitigazioni](web-app-security) · [Osservabilità & monitoraggio](observability-monitoring-laravel)

## Indice

* [Server-Side Request Forgery (SSRF)](#ssrf)
* [Mitigazione dell'SSRF in PHP](#ssrf-mitigation)
* [Vulnerabilità nel Caricamento dei File](#file-upload-vulnerabilities)
* [Implementazione del Caricamento Sicuro dei File](#secure-file-upload)
* [Errori Comuni](#common-mistakes)
* [Checklist](#checklist)
* [Riepilogo](#summary)
* [Quiz di Autovalutazione](#self-test-quiz)

---

<a id="ssrf"></a>
## Server-Side Request Forgery (SSRF)

Il **Server-Side Request Forgery (SSRF)** si verifica quando un'applicazione recupera un URL remoto fornito dall'utente senza convalidare la destinazione di arrivo.

Poiché la richiesta ha origine dal server di backend, l'aggressore può utilizzare il server come proxy per:
- Scansionare le reti interne (ad esempio, `http://10.0.0.5:80`).
- Accedere a microservizi interni privi di autenticazione (ad esempio, Redis su `http://127.0.0.1:6379`).
- Accedere agli endpoint dei metadati del cloud (ad esempio, `http://169.254.169.254/latest/meta-data/` su AWS/OpenStack) per recuperare credenziali di sicurezza IAM temporanee.

---

<a id="ssrf-mitigation"></a>
## Mitigazione dell'SSRF in PHP

Per prevenire l'SSRF, dobbiamo convalidare sia lo schema del protocollo sia l'indirizzo IP risolto per assicurarci che non facciano riferimento a reti di loopback o private.

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
## Vulnerabilità nel Caricamento dei File

Consentire agli utenti di caricare file comporta rischi significativi per la sicurezza se il processo di caricamento non è protetto:
1. **Remote Code Execution (RCE):** L'aggressore carica uno script PHP (ad esempio, `backdoor.php`), accede al percorso del file direttamente tramite il browser ed esegue comandi da terminale.
2. **Directory Traversal:** Utilizzo di un nome di file come `../../index.php` per sovrascrivere i file di codice dell'applicazione.
3. **MIME Spoofing:** Modifica dell'estensione del file di uno script in `.jpg` o `.png`, mantenendo il payload interno in formato PHP.
4. **XSS tramite SVG:** Caricamento di un file `.svg` contenente codice JavaScript inline dannoso. Quando viene visualizzato inline, il browser esegue lo script nel contesto di origine dell'applicazione.

---

<a id="secure-file-upload"></a>
## Implementazione del Caricamento Sicuro dei File

Per proteggere i caricamenti in Laravel, dobbiamo applicare regole di validazione rigide:
- Convalidare i file utilizzando i **MIME type** anziché le estensioni.
- Generare un **nome file casuale** (ad esempio, UUID o Hash) per prevenire attacchi di directory traversal e conflitti di esecuzione.
- Memorizzare il file **al di fuori della web root pubblica** utilizzando uno storage cloud isolato (come Amazon S3 o cartelle private).

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
## Errori Comuni

1. **Fidarsi di `getClientOriginalExtension`:** Utilizzare direttamente le estensioni originali fornite dall'utente, il che consente agli aggressori di caricare file denominati `script.php.png` o `script.php`.
2. **Validazione DNS debole:** Validare il nome host con espressioni regolari anziché risolvere il relativo indirizzo IP, rendendo l'applicazione vulnerabile al DNS rebinding.
3. **Directory di caricamento pubbliche:** Memorizzare i file caricati all'interno di `public/uploads` e consentire l'esecuzione HTTP diretta di script PHP in tali cartelle.
4. **Consentire il caricamento di file SVG come immagini:** Consentire caricamenti di SVG senza restrizioni e senza eseguire filtri di sanificazione completi, creando un vettore per attacchi Stored XSS.

---

<a id="checklist"></a>
## Checklist

1. **Verifica dell'host:** La tua classe di recupero dell'URL verifica l'indirizzo IP risolto o convalida solo la struttura della stringa?
2. **Isolamento dello storage:** I caricamenti vengono salvati in una directory eseguibile all'interno della web root o vengono salvati all'esterno della cartella pubblica o su S3?
3. **Analisi del MIME type:** Stai verificando il tipo di file utilizzando `finfo` di PHP o `getMimeType()` di Laravel, o stai solo leggendo l'estensione originale?
4. **Sanificazione del nome del file:** Stai conservando i nomi originali dei file o stai generando stringhe casuali di tipo UUID/hash?

---

<a id="summary"></a>
## Riepilogo

Le vulnerabilità SSRF e di caricamento non sicuro dei file minacciano la sicurezza a livello di server. Mitiga l'**SSRF** limitando i protocolli a HTTP/HTTPS e verificando che gli IP risolti non corrispondano a reti private (RFC 1918). Proteggi il **caricamento dei file** analizzando i MIME type del contenuto, rinominando i file con hash casuali e memorizzandoli al di fuori della web root pubblica per prevenire la Remote Code Execution.

---

<a id="self-test-quiz"></a>
## Quiz di Autovalutazione

### Domanda 1: Perché è fondamentale risolvere un nome host nel suo indirizzo IP prima di validarlo contro l'SSRF?
- A) Perché il recupero degli indirizzi IP è più rapido rispetto a quello dei domini.
- B) Per prevenire attacchi di DNS Rebinding, in cui un aggressore modifica il record di risoluzione DNS del dominio per farlo puntare a un IP interno (come `127.0.0.1`) dopo che la convalida è stata superata.
- C) Perché l'estensione Curl di PHP non supporta i domini.

<details>
<summary>Clicca per visualizzare la risposta</summary>

**Risposta: B**
In un attacco di DNS Rebinding, il dominio si risolve inizialmente in un IP pubblico per superare la convalida dell'host. Durante l'esecuzione, l'aggressore aggiorna il record DNS per farlo risolvere in un IP interno (come localhost o servizi di metadati). Risolvere l'IP una sola volta ed effettuare la query direttamente a quell'IP elimina questa finestra di vulnerabilità.
</details>

### Domanda 2: Qual è il rischio di salvare i file caricati dagli utenti all'interno della directory `public` di Laravel senza disabilitare l'esecuzione di PHP nella configurazione di Nginx/Apache?
- A) I file diventeranno automaticamente corrotti.
- B) Un aggressore può caricare uno script `.php` dannoso ed eseguirlo direttamente caricando il suo URL nel browser, con conseguente Remote Code Execution (RCE).
- C) Causa errori di blocco del database.

<details>
<summary>Clicca per visualizzare la risposta</summary>

**Risposta: B**
I server web sono configurati per impostazione predefinita per analizzare ed eseguire qualsiasi file che termini con `.php`. Se un file caricato rimane all'interno della web root pubblica, chiunque può accedervi direttamente, costringendo il server web a eseguire il codice.
</details>

### Domanda 3: Quale attributo del file deve essere utilizzato per convalidare il tipo di file in modo sicuro?
- A) Il MIME type del file determinato tramite l'analisi del contenuto (tramite `finfo` o l'estensione fileinfo di PHP).
- B) L'estensione del file derivata da `getClientOriginalExtension()`.
- C) La dimensione del file in byte.

<details>
<summary>Clicca per visualizzare la risposta</summary>

**Risposta: A**
I nomi dei file e le estensioni sono intestazioni inviate dal client che possono essere facilmente contraffatte. L'analisi dei byte effettivi del file (firma del file/magic byte) è l'unico modo sicuro per identificarne il formato reale.
</details>
