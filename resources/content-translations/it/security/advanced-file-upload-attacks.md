---
title: "Attacchi avanzati tramite caricamento file: tecniche di bypass e difese sicure"
description: "Una guida completa per sviluppatori sulle vulnerabilità di caricamento file, inclusi bypass MIME, immagini poliglotte, Zip Slip, SVG XSS, PDF SSRF e implementazioni Laravel sicure."
published: 2026-07-09
faq:
  - question: "Perché la verifica del tipo MIME lato client è insicura?"
    answer: "I controlli del tipo MIME lato client e l'intestazione HTTP Content-Type sono interamente controllati dal client. Gli attaccanti possono intercettare la richiesta con un proxy (come Burp Suite) e modificare l'intestazione (es. da 'application/x-php' a 'image/png') per eludere i filtri del backend."
  - question: "Cos'è un'immagine poliglotta e come esegue il codice?"
    answer: "Un'immagine poliglotta è un file valido in più formati contemporaneamente (ad esempio, un'immagine valida e uno script PHP funzionante). Il codice viene inserito nei metadati o nei commenti dell'immagine. Se il server lo memorizza nella root web con estensione .php, il codice viene eseguito."
  - question: "Come posso prevenire l'XSS tramite i file SVG caricati?"
    answer: "I file SVG sono documenti XML e possono contenere JavaScript integrato. Per prevenire l'XSS, è necessario sanificare il file SVG (rimuovendo i tag script e i gestori di eventi), convertire l'SVG in un formato raster (come PNG/JPEG) al momento del caricamento, o servirlo con l'intestazione 'Content-Disposition: attachment'."
---

# Attacchi avanzati tramite caricamento file: tecniche di bypass e difese sicure

La funzionalità di caricamento (upload) dei file è una caratteristica standard nelle moderne applicazioni web. Tuttavia, rappresenta anche uno dei vettori di attacco più critici. Se non adeguatamente protetta, può portare all'Esecuzione di Codice a Distanza (RCE), alla Divulgazione di File Locali (LFD), alla Server-Side Request Forgery (SSRF) e al Cross-Site Scripting (XSS).

Questa guida esplora le tecniche avanzate di bypass dei filtri di caricamento e spiega come implementare robuste difese lato server in PHP e Laravel.

---

## 1. Bypass della verifica del tipo MIME (MIME-Type Check Bypass)

### La vulnerabilità
Le applicazioni web spesso controllano l'intestazione `Content-Type` inviata dal browser del client per determinare se un file è sicuro. Ad esempio, quando si carica un'immagine, il browser invia automaticamente:

```http
Content-Type: image/png
```

Se il backend dell'applicazione si limita a convalidare questa intestazione, si crea una grave falla di sicurezza.

### L'attacco
Un attaccante intercetta la richiesta di caricamento utilizzando un proxy come Burp Suite. Carica una webshell PHP dannosa (`shell.php`) ma modifica l'intestazione `Content-Type`:

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="shell.php"
Content-Type: image/png

<?php system($_GET['cmd']); ?>
```

Poiché il backend legge l'intestazione modificata e ritiene che il file sia un'immagine PNG sicura, ne consente il caricamento. Una volta memorizzato in una cartella accessibile via web, l'attaccante accede a `http://vulnerable-app.com/uploads/shell.php?cmd=id` per eseguire comandi del sistema operativo.

---

## 2. Manipolazione dei Magic Bytes e delle firme dei file

### La vulnerabilità
Per contrastare i semplici bypass del tipo MIME, gli sviluppatori spesso implementano l'analisi delle firme dei file. Ogni formato di file inizia con una sequenza univoca di byte iniziali, noti come \"magic bytes\". Ad esempio:
- **GIF**: `GIF89a` (`47 49 46 38 39 61`)
- **PNG**: `\x89PNG\r\n\x1a\n` (`89 50 4E 47 0D 0A 1A 0A`)
- **JPEG**: `\xFF\xD8\xFF` (`FF D8 FF`)

Se il server verifica solo questi byte di firma all'inizio del file, rimane vulnerabile.

### L'attacco
L'attaccante aggiunge la firma del file consentito all'inizio del proprio codice dannoso. Ad esempio, crea un file di testo che inizia con `GIF89a;`, seguito da codice PHP:

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="shell.gif.php"
Content-Type: image/gif

GIF89a;
<?php system($_GET['cmd']); ?>
```

Quando il backend legge i primi byte, rileva la firma `GIF89a` e convalida il file come immagine. Se il file viene salvato con estensione `.php`, il server web eseguirà il payload.

---

## 3. Iniezione di metadati EXIF e file poliglotti

### La vulnerabilità
Un file poliglotta è un file valido in più formati contemporaneamente (ad esempio, sia un'immagine valida che uno script eseguibile). I validatori di caricamento avanzati utilizzano analizzatori profondi (come la libreria GD o ImageMagick) per verificare la struttura dell'immagine. Tuttavia, i formati di immagine consentono commenti testuali o strutture di metadati (come i tag EXIF in JPEG, o blocchi di testo in PNG).

### L'attacco
Utilizzando strumenti come `exiftool`, gli attaccanti possono iniettare codice PHP all'interno del commento o dei campi dei metadati dell'immagine senza corromperne la struttura:

```bash
exiftool -Comment="<?php system($_GET['cmd']); ?>" exploit.jpg
```

Se il backend verifica solo se la struttura dell'immagine è valida (ad esempio, usando `getimagesize()` in PHP) e salva il file con estensione `.php` (o se funziona un bypass a doppia estensione o un null byte bypass), il parser PHP eseguirà il codice nascosto nei metadati.

> [!WARNING]
> **Sopravvivenza al ridimensionamento e alla compressione GD/ImageMagick**: Il semplice ridimensionamento dell'immagine sul server non garantisce la sicurezza. Gli attaccanti hanno sviluppato file poliglotti speciali \"GD-proof\" (in particolare per PNG e JPEG) in cui il payload è posizionato in sezioni di dati dell'immagine (come il blocco PLTE o le tabelle di quantizzazione) che sopravvivono inalterate alla compressione e al ridimensionamento.

---

## 4. Salto di directory tramite il nome del file (Path Traversal)

### La vulnerabilità
Durante il caricamento dei file, il browser trasmette un'intestazione `Content-Disposition` contenente il nome originale del file. Se il backend si fida di questo nome e lo concatena direttamente al percorso della cartella di destinazione, si verifica una vulnerabilità di Path Traversal.

### L'attacco
L'attaccante modifica il parametro `filename` per includere sequenze di salto di directory (`../`):

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="../../../../var/www/html/shell.php"
Content-Type: image/png

<?php system($_GET['cmd']); ?>
```

Se la cartella di destinazione è `/var/www/html/storage/uploads/` e l'applicazione risolve il percorso in modo dinamico senza filtri, il file verrà scritto in `/var/www/html/shell.php` anziché nella cartella protetta. Questo consente all'attaccante di eseguire la webshell direttamente dalla root web.

---

## 5. Zip Slip (Path Traversal all'interno di archivi compressi)

### La vulnerabilità
Quando le applicazioni web accettano archivi compressi (come `.zip` o `.tar`), il backend deve decomprimerli. Se la routine di estrazione non convalida il nome di ciascun file contenuto nell'archivio, il sistema è vulnerabile all'attacco \"Zip Slip\".

### L'attacco
L'attaccante crea un archivio compresso in cui il nome di un file contiene sequenze di salto di directory:

```text
Archivio dannoso:
└── ../../../../var/www/html/shell.php
```

Se il backend utilizza librerie standard di decompressione senza verificare che il percorso di destinazione di ciascun elemento rimanga all'interno della cartella stabilita, la webshell verrà estratta direttamente nella root web del server.

---

## 6. XSS lato client tramite caricamento di SVG

### La vulnerabilità
Il formato SVG (Scalable Vector Graphics) è basato su XML. Trattandosi di documenti XML, i file SVG possono contenere script incorporati (tag `<script>`) e gestori di eventi HTML.

### L'attacco
Se l'applicazione consente il caricamento di file SVG (ad esempio come foto profilo) e li distribuisce con l'intestazione `Content-Type: image/svg+xml`, il browser li elaborerà come documenti HTML attivi.

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

Quando un utente accede direttamente al link del file SVG, lo script JavaScript incorporato si eseguirà nel contesto del dominio dell'applicazione, facilitando il furto di sessioni o credenziali utente.

---

## 7. SSRF e LFD tramite l'elaborazione di file PDF

### La vulnerabilità
Molte piattaforme elaborano file PDF caricati per generare miniature (thumbnails) o analizzare il testo. Solitamente si utilizzano utility come Ghostscript, `pdftoppm` o convertitori da PDF a HTML. La maggior parte di questi strumenti ha una lunga storia di vulnerabilità critiche (come la remote command execution in Ghostscript).

### L'attacco
1. **Divulgazione di file locali (LFD)**: Un attaccante carica un PDF che fa riferimento a file del sistema locale (ad esempio `/etc/passwd` o `C:\Windows\win.ini`). Durante la generazione dell'anteprima, l'utility legge tali file e inserisce il loro contenuto direttamente nell'immagine renderizzata.
2. **Server-Side Request Forgery (SSRF)**: L'attaccante inserisce URL interni (come `http://169.254.169.254/` o pannelli interni di amministrazione) nella struttura del PDF. Nel tentativo di elaborare il file, il server invierà richieste a queste reti private isolate.

---

## 8. Esempio di implementazione sicura in PHP/Laravel (Difesa in profondità)

Per proteggere il caricamento dei file, è fondamentale adottare una strategia di difesa a più livelli. La semplice convalida dei tipi o delle estensioni non è sufficiente.

### Controller di caricamento sicuro in Laravel

Di seguito viene mostrata un'implementazione completa e pronta per la produzione di un controller sicuro in Laravel.

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
     * Gestisce il caricamento sicuro, la convalida e l'elaborazione delle immagini.
     */
    public function upload(SecureUploadRequest $request)
    {
        if (!$request->hasFile('uploaded_file')) {
            return response()->json(['error' => 'Nessun file caricato.'], 400);
        }

        $file = $request->file('uploaded_file');

        // 1. Applicare whitelist rigorosa delle estensioni
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        
        if (!in_array($extension, $allowedExtensions)) {
            return response()->json(['error' => 'Estensione file non valida.'], 400);
        }

        // 2. Determinare il tipo MIME reale sul server (utilizzando fileinfo)
        $realMime = $file->getMimeType();
        $mimeToExtensionMap = [
            'image/jpeg'    => ['jpg', 'jpeg'],
            'image/png'     => ['png'],
            'image/gif'     => ['gif'],
            'image/svg+xml' => ['svg'],
        ];

        if (!isset($mimeToExtensionMap[$realMime]) || !in_array($extension, $mimeToExtensionMap[$realMime])) {
            return response()->json(['error' => 'Il tipo MIME non corrisponde all\'estensione.'], 400);
        }

        // 3. Generare un nome file UUID casuale (protezione da Path Traversal e perdite di metadati)
        $uuid = Str::uuid()->toString();
        $secureFilename = "{$uuid}.{$extension}";

        // 4. Trattare separatamente i file SVG (Sanificazione contro XSS e XXE)
        if ($realMime === 'image/svg+xml') {
            try {
                $sanitizedSvg = $this->sanitizeSvg($file->getRealPath());
                
                // Salvare in un contenitore isolato (es. AWS S3) al di fuori della root web
                Storage::disk('s3')->put("uploads/{$secureFilename}", $sanitizedSvg, [
                    'visibility' => 'private',
                    'ContentType' => 'image/svg+xml',
                    'ContentDisposition' => 'attachment; filename="' . $secureFilename . '"' // Forza il download per evitare l'esecuzione di script nel browser
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Sanificazione del file SVG fallita.'], 400);
            }
        } else {
            // 5. Ricodificare le immagini raster (JPEG/PNG/GIF) per rimuovere EXIF e distruggere poliglotti
            try {
                // Inizializzare Intervention Image (utilizza GD o ImageMagick)
                $img = Image::make($file->getRealPath());

                // Ricostruire la struttura dei pixel scarta EXIF e script PHP incorporati
                $stream = $img->stream($extension, 85); // Ricodifica all'85% di qualità

                // Memorizzare in uno spazio privato protetto
                Storage::disk('s3')->put("uploads/{$secureFilename}", $stream->__toString(), [
                    'visibility' => 'private',
                    'ContentType' => $realMime,
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Elaborazione dell\'immagine fallita.'], 500);
            }
        }

        return response()->json([
            'message' => 'File caricato in modo sicuro.',
            'file_id' => $uuid,
            'filename' => $secureFilename
        ], 200);
    }

    /**
     * Sanifica il contenuto SVG rimuovendo tag script ed eventi inline.
     */
    private function sanitizeSvg(string $filePath): string
    {
        $xml = file_get_contents($filePath);
        if ($xml === false) {
            throw new \Exception('Impossibile leggere il file SVG.');
        }

        $dom = new \DOMDocument();
        
        // Disabilitare il caricamento di entità esterne (protezione XXE)
        libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);

        if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOENT | LIBXML_DTDLOAD)) {
            libxml_clear_errors();
            throw new \Exception('Struttura XML non valida.');
        }

        // 1. Rimuovere tutti i tag <script>
        $scripts = $dom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $script = $scripts->item(0);
            $script->parentNode->removeChild($script);
        }

        // 2. Rimuovere tutti i gestori di eventi inline (es. onload, onclick)
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

### Hacking della configurazione del server web
Oltre a utilizzare codice sicuro nell'applicazione, è necessario configurare il server web per impedire l'esecuzione di file nella cartella di upload.

#### Per Nginx
Disattivare l'esecuzione di PHP nella cartella di upload:
```nginx
location ~* ^/storage/uploads/.*\.php$ {
    deny all;
    return 404;
}
```

#### Per Apache
Disattivare l'elenco dei file e l'esecuzione di PHP nella cartella tramite `.htaccess`:
```apache
Options -Indexes
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8
php_flag engine off
```
