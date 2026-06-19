---
title: "Server & Infrastructure Hardening: Security Header, TLS, Rate Limiting e Gestione dei Secret | DevSense"
description: "Hardening del web server e dell'infrastruttura dell'applicazione. Scopri come configurare gli header di sicurezza HTTP, i cifrari TLS, i rate limiter, i secret sicuri e l'isolamento del database."
published: 2026-06-19
faq:
  - question: "Perché dovresti evitare di usare env() al di fuori dei file di configurazione in Laravel?"
    answer: "In produzione, Laravel memorizza i file di configurazione in un unico file ottimizzato (cache). Una volta memorizzato nella cache, Laravel smette completamente di leggere il file .env e qualsiasi chiamata diretta a env() nel codice dell'applicazione restituirà null, causando errori critici di configurazione a runtime."
  - question: "Qual è il ruolo principale dell'header Content-Security-Policy (CSP)?"
    answer: "Il CSP funge da potente livello di difesa contro gli attacchi di Cross-Site Scripting (XSS) e di injection dei dati consentendo agli amministratori di limitare le risorse (come JavaScript, CSS, Immagini) che il browser è autorizzato a caricare ed eseguire per una determinata pagina."
  - question: "In che modo Nginx protegge dagli attacchi Bruteforce e Denial of Service (DoS)?"
    answer: "Il rate limiting di Nginx utilizza l'algoritmo Leaky Bucket per limitare la frequenza delle richieste in entrata da un singolo indirizzo IP, ritardando o bloccando le richieste che superano le zone definite."
---

# Server & Infrastructure Hardening: Security Header, TLS, Rate Limiting e Gestione dei Secret

Sebbene la protezione del codice dell'applicazione sia fondamentale, anche l'infrastruttura che la ospita deve essere sottoposta a hardening (messa in sicurezza). Un ambiente di backend sicuro richiede una solida sicurezza del trasporto, limitazione delle richieste (request throttling), isolamento della rete e secret ambientali protetti.

In questa guida implementeremo gli header di sicurezza tramite il middleware di Laravel, configureremo i limiti di frequenza (rate limit) in Nginx e Laravel, proteggeremo le variabili d'ambiente e discuteremo la messa in sicurezza della rete.

**Guide correlate:** [Vulnerabilità delle Web Application & Mitigazioni](web-app-security) · [SSRF & Caricamento Sicuro dei File](ssrf-and-file-upload-security)

## Indice

* [Header di Sicurezza HTTP](#security-headers)
* [Hardening SSL/TLS](#ssl-tls-hardening)
* [Rate Limiting (Nginx & Laravel)](#rate-limiting)
* [Gestione Sicura dei Secret](#secrets-management)
* [Isolamento del Database](#database-isolation)
* [Errori Comuni](#common-mistakes)
* [Checklist](#checklist)
* [Riepilogo](#summary)
* [Quiz di Autovalutazione](#self-test-quiz)

---

<a id="security-headers"></a>
## Header di Sicurezza HTTP

Gli header di sicurezza HTTP indicano al browser come comportarsi durante l'interazione con il tuo sito, neutralizzando i vettori di attacco comuni come Clickjacking, Cross-Site Scripting (XSS) e MIME-sniffing.

Possiamo applicare questi header a livello globale in Laravel utilizzando un middleware personalizzato:

```php
// app/Http/Middleware/SecureHeadersMiddleware.php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 1. Prevent Clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // 2. Prevent MIME-type Sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 3. Control referrer information sent in HTTP headers
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 4. Force HTTPS (HTTP Strict Transport Security - HSTS)
        // 31536000 seconds = 1 year. Include subdomains and preloading.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // 5. Restrict permissions (Permissions-Policy)
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');

        // 6. Content Security Policy (CSP)
        // Allow scripts and styles only from self and specific secure CDNs
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' https://trusted-cdn.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; frame-ancestors 'none';"
        );

        return $response;
    }
}
```

---

<a id="ssl-tls-hardening"></a>
## Hardening SSL/TLS

Configurare correttamente SSL/TLS garantisce che i dati in transito non possano essere intercettati o modificati. È necessario disabilitare le versioni TLS obsolete e limitare il server a cifrari sicuri.

Di seguito è riportato un blocco di configurazione TLS sicuro per Nginx:

```nginx
# nginx.conf
server {
    listen 443 ssl http2;
    server_name example.com;

    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    # Restrict to secure TLS versions (TLS v1.2 and TLS v1.3 only)
    ssl_protocols TLSv1.2 TLSv1.3;

    # Enforce secure cipher suites
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers on;

    # Enable Session Tickets & Caching for performance
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    # Enable OCSP Stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    resolver 8.8.8.8 8.8.4.4 valid=300s;
    resolver_timeout 5s;
}
```

---

<a id="rate-limiting"></a>
## Rate Limiting (Nginx & Laravel)

Il rate limiting previene gli abusi derivanti da attacchi DDoS, tentativi di brute-force per l'accesso e scraper bot. Questa misura di hardening dovrebbe essere applicata sia a livello di web server (Nginx) che di applicazione (Laravel).

### 1. Rate Limiting in Nginx (basato su IP)

Nginx gestisce il rate limiting prima che le richieste raggiungano PHP-FPM, conservando le risorse del server:

```nginx
# nginx.conf (global context)
limit_req_zone $binary_remote_addr zone=login_limit:10m rate=5r/m;

# server context
location /login {
    # Apply limit with a burst margin of 5 requests
    limit_req zone=login_limit burst=5 nodelay;
    
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 2. Rate Limiting in Laravel

Laravel consente un rate limiting dinamico e specifico per l'utente all'interno del codice dell'applicazione. Configura i limitatori in `app/Providers/AppServiceProvider.php` (o `RouteServiceProvider.php`):

```php
// app/Providers/AppServiceProvider.php
declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Global API limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Dedicated sensitive action limiter (e.g., login/register)
        RateLimiter::for('auth', function (Request $request) {
            $email = (string) $request->input('email');
            return Limit::perMinute(5)->by($email ?: $request->ip());
        });
    }
}
```

Applica questo limitatore nel file delle rotte:

```php
// routes/api.php
Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});
```

---

<a id="secrets-management"></a>
## Gestione Sicura dei Secret

L'esposizione di chiavi API segrete o di password del database può portare a violazioni dei dati catastrofiche.

### 1. Evitare l'uso di `env()` nel codice dell'applicazione

Non chiamare mai `env()` al di fuori dei file di configurazione (situati nella directory `config/`). Quando esegui la cache della configurazione in produzione (`php artisan config:cache`), Laravel carica i valori di `.env` una sola volta, li memorizza nella cache e disabilita la lettura del file `.env` a runtime. Qualsiasi chiamata diretta a `env()` dopo la memorizzazione nella cache restituirà `null`.

**Pattern corretto:**

```php
// config/services.php
return [
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
    ],
];

// app/Services/PaymentService.php
// Access using config() helper, not env()
$secretKey = config('services.stripe.secret');
```

### 2. Proteggere i permessi di `.env`

Assicurati che gli utenti non autorizzati sul sistema host non possano leggere il file `.env` contenente le chiavi di configurazione:

```bash
chmod 600 .env
```

---

<a id="database-isolation"></a>
## Isolamento del Database

Il database non dovrebbe mai essere esposto a internet.

1. **Associazione di rete (Network Binding):** Costringi il server del database a restare in ascolto solo su interfacce interne o sul loopback locale. In PostgreSQL (`postgresql.conf`) o MySQL (`my.cnf`), associa la configurazione agli indirizzi locali:
   ```ini
   # mysql.cnf
   bind-address = 127.0.0.1
   ```
2. **Regole del Firewall:** Blocca le porte esterne in entrata (come la porta 3306 di MySQL o la porta 5432 di PostgreSQL) utilizzando le regole del firewall (UFW o Cloud Security Groups). Consenti l'accesso solo dall'IP privato del web server.
3. **SSL/TLS per il Database:** Se il database e l'applicazione web risiedono su server diversi all'interno di una subnet privata, imponi connessioni SSL tra Laravel e il database:

```php
// config/database.php
'mysql' => [
    // ...
    'options' => [
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    ],
],
```

---

<a id="common-mistakes"></a>
## Errori Comuni

1. **Chiamare `env()` a runtime:** Chiamare `env('API_KEY')` all'interno di controller o job, con conseguenti errori di configurazione quando viene abilitata la cache in produzione.
2. **Mancanza di `Strict-Transport-Security` (HSTS):** Dimenticare di inviare gli header HSTS, lasciando gli utenti vulnerabili ad attacchi di SSL-stripping.
3. **Configurazione TLS debole:** Mantenere il supporto a TLS 1.0 o 1.1 sui server per preservare la retrocompatibilità, compromettendo la crittografia complessiva del sistema.
4. **Esposizione pubblica della porta 3306/5432:** Lasciare la porta del database aperta a internet, esponendola a scansioni, attacchi a dizionario e inondazioni di connessioni (connection flooding).

---

<a id="checklist"></a>
## Checklist

1. **Nessuna chiamata a `env()`:** Hai analizzato il codice per assicurarti che tutte le ricerche delle variabili d'ambiente avvengano all'interno dei file di configurazione?
2. **Header di Sicurezza HTTP:** La tua applicazione web invia gli header `X-Frame-Options`, `X-Content-Type-Options` e una `Content-Security-Policy` definita?
3. **Restrizione della versione TLS:** La tua configurazione Nginx limita i protocolli esclusivamente a TLS v1.2 e v1.3?
4. **Limitazione di frequenza (Throttling):** Tutti gli endpoint pubblici di autenticazione e le API sensibili sono protetti da rate limiting?
5. **Firewall di rete:** L'accesso alla rete pubblica è disabilitato per database, motori di cache (Redis/Memcached) e microservizi interni?

---

<a id="summary"></a>
## Riepilogo

La protezione dei server delle applicazioni richiede la salvaguardia dei dati a tutti i livelli. Abilita i meccanismi di sicurezza del browser utilizando header di sicurezza come HSTS e CSP. Metti in sicurezza le configurazioni di Nginx e Laravel applicando il rate limiting per bloccare gli attacchi brute force. Proteggi sempre i secret di configurazione richiamandoli solo all'interno dei file di configurazione e memorizzandoli correttamente nella cache, e isola completamente il livello del database dalla rete pubblica.

---

<a id="self-test-quiz"></a>
## Quiz di Autovalutazione

### Domanda 1: Cosa succede se `env('STRIPE_KEY')` viene chiamato all'interno di un controller dopo aver eseguito `php artisan config:cache`?
- A) Laravel legge la chiave direttamente dal file `.env`.
- B) La chiamata restituisce `null`, perché la lettura a runtime di `.env` viene disabilitata dopo la memorizzazione nella cache della configurazione.
- C) Viene generata un'eccezione di sicurezza.

<details>
<summary>Clicca per visualizzare la risposta</summary>

**Risposta: B**
La memorizzazione nella cache analizza tutti i file di configurazione in un unico file memorizzato. Una sola volta in cache, il caricamento del file `.env` viene saltato completamente, il che significa che le chiamate a `env()` restituiranno `null`. Tutte le credenziali devono essere lette dalla directory config tramite `config()`.
</details>

### Domanda 2: Perché l'header `X-Content-Type-Options: nosniff` è importante?
- A) Impedisce al browser di eseguire file il cui MIME type non corrisponde all'estensione del file o al tipo HTML, mitigando gli attacchi di esecuzione dei file.
- B) Impedisce al sito web di essere caricato all'interno di un iframe (Clickjacking).
- C) Comprime le risposte per caricarle più velocemente.

<details>
<summary>Clicca per visualizzare la risposta</summary>

**Risposta: A**
Senza `nosniff`, i browser eseguono il "MIME-sniffing" ed eseguono i file caricati come HTML/JavaScript se contengono payload corrispondenti, indipendentemente dall'header Content-Type inviato. `nosniff` previene questo comportamento.
</details>

### Domanda 3: Quali versioni del protocollo dovrebbero essere disabilitate nella configurazione TLS del server web?
- A) TLS 1.2 e TLS 1.3
- B) TLS 1.0 e TLS 1.1
- C) Solo SSLv2 e SSLv3

<details>
<summary>Clicca per visualizzare la risposta</summary>

**Risposta: B**
Le versioni TLS 1.0 e 1.1 contengono vulnerabilità crittografiche e non supportano le moderne suite di cifratura con forward-secrecy. Solo TLS 1.2 e TLS 1.3 dovrebbero essere abilitati per i servizi di produzione.
</details>
