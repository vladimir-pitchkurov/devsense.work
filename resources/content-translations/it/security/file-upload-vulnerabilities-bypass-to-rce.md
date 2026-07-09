---
title: "Vulnerabilità di caricamento file: Aggirare i filtri per l'esecuzione di codice in remoto (RCE) | DevSense"
description: "Padroneggia i meccanismi di caricamento file sicuro. Scopri come gli aggressori aggirano i controlli client-side, sfruttano le blacklist di estensioni, sovrascrivono le configurazioni del server web e come scrivere codice Laravel sicuro."
published: 2026-07-09
faq:
  - question: "Perché una whitelist di estensioni è più sicura di una blacklist?"
    answer: "Una blacklist tenta di bloccare le estensioni pericolose note (es. .php, .exe) ma spesso ignora estensioni eseguibili alternative (es. .phtml, .phar, .php5) o trucchi di denominazione specifici del sistema operativo. Una whitelist definisce rigorosamente i formati consentiti e sicuri (es. .jpg, .pdf) e rifiuta qualsiasi altra cosa per impostazione predefinita."
  - question: "Come funziona l'aggiramento tramite NTFS Alternate Data Streams (ADS) in Windows?"
    answer: "Sui sistemi Windows, aggiungere '::$DATA' alla fine di un nome file (come 'shell.php::$DATA') indica a NTFS di scrivere i dati nel flusso predefinito di 'shell.php'. Durante il salvataggio, Windows elimina il suffisso '::$DATA', lasciando sul disco un file 'shell.php' completamente eseguibile, aggirando così i filtri blacklist elementari."
  - question: "In che modo la sovrascrittura di un file .user.ini porta all'esecuzione di codice?"
    answer: "Negli ambienti PHP CGI/FastCGI, caricare un file '.user.ini' personalizzato in una directory di upload consente a un utente malintenzionato di ridefinire le direttive di configurazione PHP per quella directory. Impostando 'auto_prepend_file=image.png', il motore PHP esegue automaticamente il codice PHP incorporato in 'image.png' ogni volta che si accede a qualsiasi script PHP in quella directory."
---

# Vulnerabilità di caricamento file: Aggirare i filtri per l'esecuzione di codice in remoto (RCE)

I moduli di caricamento file sono tra le funzionalità a più alto rischio nelle applicazioni web. Quando un'applicazione consente agli utenti di caricare file, apre una via di accesso diretto al file system del server. Se il backend non convalida correttamente i file caricati, un aggressore può caricare una web shell, aggirare le restrizioni di esecuzione e ottenere l'**esecuzione di codice in remoto (RCE)**.

In questa guida analizzeremo i meccanismi tecnici dei caricamenti di file, le tecniche di aggiramento dei filtri e come creare una pipeline di convalida dei file robusta in PHP e Laravel.

---

## Contenuti

* [Meccanismi di Multipart Form-Data](#multipart-mechanics)
* [Aggiramento dei controlli lato client](#client-side-bypass)
* [Aggiramento del filtraggio delle estensioni](#extension-bypasses)
* [Sovrascrittura della configurazione del server Web](#config-overrides)
* [Normalizzazione dei nomi dei file in Windows](#windows-normalization)
* [Implementazione sicura in Laravel e PHP](#secure-implementation)
* [Pratiche comuni di hardening del server](#server-hardening)
* [Tabella riassuntiva di controllo](#checklist)

---

<a id="multipart-mechanics"></a>
## Meccanismi di Multipart Form-Data

Quando si carica un file tramite HTTP, i browser utilizzano lo schema di codifica `multipart/form-data`, definito nella specifica **RFC 7578**. Comprendere questa struttura è fondamentale per capire le discrepanze tra i diversi analizzatori (parser).

### Struttura di una richiesta Multipart
Una tipica richiesta di caricamento file si presenta così:

```http
POST /upload.php HTTP/1.1
Host: example.com
Content-Type: multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Length: 345

------WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Disposition: form-data; name="avatar"; filename="backdoor.php"
Content-Type: application/x-php

<?php system($_GET['cmd']); ?>
------WebKitFormBoundary7MA4YWxkTrZu0gW--
```

### Elementi critici di Multipart:
1. **Parametro Boundary:** L'attributo `boundary` nell'intestazione `Content-Type` definisce la stringa univoca utilizzata per separare i campi del modulo.
2. **Doppio trattino:** Nel corpo della richiesta, ogni boundary è preceduto da due trattini (`--`). Anche il boundary finale che segna la fine del payload deve terminare con due trattini (`--boundary--`).
3. **Discrepanze del parser:** Diversi linguaggi di programmazione e server applicativi analizzano i payload multipart in modo differente.
   - Alcuni parser gestiscono in modo permissivo le intestazioni mancanti o i trattini errati.
   - Ad esempio, è stata scoperta una vulnerabilità nella nota libreria multipart per Node.js **Multer**, in cui l'omissione dei trattini doppi finali (`--`) faceva sì che il parser rimanesse in attesa indefinita di ulteriori dati, causando una vulnerabilità di Denial of Service (DoS).

---

<a id="client-side-bypass"></a>
## Aggiramento dei controlli lato client

Molti sviluppatori implementano la convalida dei file esclusivamente in JavaScript sul lato client per fornire un feedback immediato all'utente. I controlli tipici includono la limitazione del tag input:

```html
<!-- Restrizione lato client -->
<input type="file" id="avatar" accept=".jpg, .png" onchange="validateFile()">
```

### Il meccanismo di aggiramento
I controlli lato client sono del tutto insicuri poiché il client è sotto il controllo diretto dell'aggressore. Un utente malintenzionato può aggirare questi controlli in diversi modi:
1. **Disattivazione di JavaScript:** Utilizzo degli strumenti di sviluppo del browser per disabilitare completamente l'esecuzione di JS, consentendo al modulo di inviare qualsiasi file.
2. **Proxy di intercettazione (es. Burp Suite):**
   - Seleziona un file legittimo (es. `profile.png`).
   - Invia il modulo.
   - Intercetta la richiesta in uscita in Burp Suite.
   - Modifica il parametro filename in `shell.php`, imposta il `Content-Type` su `application/x-php` e sostituisci il contenuto binario dell'immagine con il payload PHP: `<?php phpinfo(); ?>`.

> [!WARNING]
> Non fare mai affidamento sulla convalida lato client per la sicurezza. Tutti i controlli lato client servono solo alla comodità dell'utente. La sicurezza deve essere applicata sul server backend.

---

<a id="extension-bypasses"></a>
## Aggiramento del filtraggio delle estensioni

Quando gli sviluppatori configurano controlli sul server, spesso si basano sul filtraggio delle estensioni. Questo può essere fatto tramite una **blacklist** (blocco delle estensioni dannose) o una **whitelist** (consenso solo a estensioni specifiche).

### Aggiramento delle blacklist
Le blacklist sono intrinsecamente deboli. Gli aggressori possono aggirare le blacklist che bloccano il formato `.php` attraverso diverse strategie:

1. **Estensioni eseguibili alternative:**
   A seconda della configurazione del server web, potrebbero essere eseguite estensioni alternative compatibili con PHP:
   - `.phtml`, `.php3`, `.php4`, `.php5`, `.php7`, `.phps`
   - `.phar` (archivio PHP, che può attivare vettori di deserializzazione)
   - `.pht`

2. **Sfruttamento delle maiuscole/minuscole:**
   Se la logica di filtraggio controlla solo `.php` ma l'ambiente del server tratta i file senza fare distinzione tra maiuscole e minuscole, gli sviluppatori potrebbero tralasciare:
   - `.pHp`, `.Php`, `.PHp`, `.pHTML`

3. **Estensioni doppie e nidificate:**
   - **Estensioni doppie:** Se il server è configurato in modo errato per eseguire file contenenti `.php` in qualsiasi punto del nome (ad esempio, tramite Apache `AddHandler`), un aggressore può caricare `shell.php.jpg`.
   - **Aggiramento della rimozione:** Se il codice tenta di rimuovere `.php` in modo non ricorsivo:
     `shell.p.phphp.hp` -> Rimuovere `php` una sola volta lascerà `shell.php`.

---

<a id="config-overrides"></a>
## Sovrascrittura della configurazione del server Web

Se un'applicazione applica una blacklist rigida ma consente il caricamento di file di configurazione, un aggressore può sovrascrivere il comportamento di esecuzione del server per la directory di caricamento.

### 1. Sovrascrittura della configurazione di Apache (`.htaccess`)
Se Apache è configurato con `AllowOverride All` per la directory di caricamento, un aggressore può caricare un file `.htaccess` personalizzato:

```apache
# Forza la mappatura di PNG come PHP in .htaccess
AddType application/x-httpd-php .png
```
O:
```apache
<Files "logo.png">
    ForceType application/x-httpd-php
</Files>
```

Dopo aver caricato questo file `.htaccess`, l'aggressore carica un file denominato `logo.png` contenente codice PHP. Apache tratterà `logo.png` come uno script PHP e lo eseguirà quando vi si accede.

### 2. Sovrascrittura di PHP CGI/FastCGI (`.user.ini`)
Nelle configurazioni Nginx o IIS che utilizzano PHP-FPM o PHP CGI, i file `.htaccess` non vengono elaborati. Tuttavia, PHP supporta i file di configurazione a livello di directory chiamati `.user.ini`.

Un aggressore può caricare un file `.user.ini` contenente:

```ini
# Esegue il codice PHP incorporato in un file PNG
auto_prepend_file=avatar.png
```

Se l'aggressore carica quindi `avatar.png` (contenente il payload) e accede a qualsiasi file `.php` legittimo ed esistente in quella directory (anche uno script di indice predefinito vuoto), il motore PHP eseguirà prima il contenuto di `avatar.png`.

---

<a id="windows-normalization"></a>
## Normalizzazione dei nomi dei file in Windows

Quando le applicazioni vengono eseguite su un server web Windows (IIS o Apache su Windows), la creazione dei file è soggetta alle regole di normalizzazione delle API NTFS e Win32. Ciò crea particolari vettori di elusione.

### 1. Punti e spazi finali
Windows rimuove automaticamente i punti e gli spazi finali dai nomi dei file quando li crea sul disco.
- Se l'applicazione convalida le estensioni utilizzando una blacklist (ad esempio, bloccando `.php`), un aggressore può caricare un file denominato `shell.php.` o `shell.php `.
- La convalida regex controlla `shell.php.` (che non corrisponde a `.php`) e consente il caricamento.
- Il file system Win32 crea il file sul disco e normalizza il nome in `shell.php`, rendendolo eseguibile.

### 2. NTFS Alternate Data Streams (ADS)
NTFS utilizza gli Alternate Data Streams per memorizzare i metadati (come le informazioni sulla zona). Il flusso predefinito è indicato come `::$DATA`.
- Un aggressore carica un file denominato `shell.php::$DATA`.
- La logica della blacklist analizza l'estensione come `.php::$DATA` (o la accetta se cerca solo l'ultimo punto).
- Al momento del salvataggio del file, Windows estrae `shell.php` e scrive il contenuto del file in ingresso nel suo flusso di dati primario. Il risultato è un file `shell.php` perfettamente funzionante nella directory web.

---

<a id="secure-implementation"></a>
## Implementazione sicura in Laravel e PHP

Per proteggere i caricamenti dei file, è necessario seguire il principio della **difesa in profondità**. Non fare affidamento su un singolo passaggio di convalida.

### Controller di caricamento sicuro in Laravel

Ecco come implementare un controller di caricamento file sicuro in Laravel utilizzando whitelist rigide, controlli del tipo MIME e memorizzazione al di fuori della radice web pubblica:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SecureUploadController extends Controller
{
    // 1. Rigida whitelist delle estensioni consentite e dei corrispondenti tipi MIME
    private const ALLOWED_TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'pdf'  => 'application/pdf',
    ];

    public function store(Request $request): JsonResponse
    {
        // Verifica se il file esiste
        if (!$request->hasFile('document')) {
            return response()->json(['error' => 'No file uploaded.'], 400);
        }

        $file = $request->file('document');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return response()->json(['error' => 'Invalid or corrupted file.'], 400);
        }

        // 2. Convalida la dimensione del file (es. Max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json(['error' => 'File size exceeds 5MB limit.'], 400);
        }

        // 3. Ispeziona il contenuto fisico del file per il tipo MIME (evita di fidarsi delle intestazioni del client)
        $realMimeType = $file->getMimeType();
        $originalName = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // 4. Convalida che l'estensione e il tipo MIME corrispondano alla whitelist
        if (!array_key_exists($extension, self::ALLOWED_TYPES)) {
            return response()->json(['error' => 'Unsupported file extension.'], 400);
        }

        if (self::ALLOWED_TYPES[$extension] !== $realMimeType) {
            return response()->json(['error' => 'MIME type and file extension mismatch.'], 400);
        }

        // 5. Genera un nome file completamente casuale (UUID o hash crittografico)
        // Ciò neutralizza il Directory Traversal, i trucchi di normalizzazione di Windows e le collisioni di nomi.
        $safeName = Str::uuid()->toString() . '.' . $extension;

        // 6. Memorizza al di FUORI della radice web pubblica (ad esempio, utilizzando un bucket S3 privato o un disco locale privato)
        // Evita di utilizzare public_path(). Utilizza invece la memoria locale privata.
        $path = $file->storeAs('uploads/secure_docs', $safeName, 'local');

        if ($path === false) {
            return response()->json(['error' => 'Failed to store file.'], 500);
        }

        return response()->json([
            'message'   => 'File uploaded securely.',
            'safe_name' => $safeName
        ], 201);
    }
}
```

---

<a id="server-hardening"></a>
## Pratiche comuni di hardening del server

Anche se il codice dell'applicazione presenta dei punti deboli, l'irrigidimento dell'infrastruttura del server può impedire l'esecuzione di script dannosi.

### 1. Disabilitare l'esecuzione di PHP nelle directory di caricamento
Per Nginx, configura il blocco del server per rifiutare l'esecuzione degli script PHP nella cartella dei caricamenti:

```nginx
# Disabilita l'esecuzione di PHP nella directory degli upload
location ~* ^/uploads/.*\.php$ {
    deny all;
    return 403;
}
```

Per Apache, inserisci questo blocco nella configurazione della directory per bloccare l'esecuzione degli script:

```apache
<Directory "/var/www/html/uploads">
    # Disabilita l'esecuzione degli script
    RemoveHandler .php .phtml .php3
    RemoveType .php .phtml .php3
    
    # Oppure forza l'invio come testo normale
    ForceType text/plain
    
    # Disabilita le sovrascritture htaccess in questa cartella
    AllowOverride None
</Directory>
```

### 2. Eseguire il server Web con privilegi minimi
Assicurarsi che il server web (es. `www-data`, `nginx`) disponga dei permessi di lettura e scrittura *solo* per le directory di caricamento designate e non disponga di permessi di scrittura per altre cartelle di sistema o per i percorsi del codice sorgente.

---

<a id="checklist"></a>
## Tabella riassuntiva di controllo

| Livello di sicurezza | Controllo dell'implementazione |
| :--- | :--- |
| **Whitelist** | Convalida le estensioni rispetto a una whitelist rigida (non bloccare mai tramite blacklist). |
| **Convalida MIME** | Convalida le intestazioni effettive dei file utilizzando librerie sicure (es. php `fileinfo`). |
| **Ridenominazione** | Genera nomi casuali (UUID o hash casuale) al momento del caricamento. |
| **Posizione file** | Salva i file caricati all'esterno della directory principale del sito web pubblico. |
| **Config. del server** | Impedisci la sovrascrittura dei file di configurazione (`.htaccess`, `.user.ini`) nelle cartelle. |
| **Blocco esecuzione** | Disabilita esplicitamente il motore di esecuzione degli script nel percorso di caricamento. |
