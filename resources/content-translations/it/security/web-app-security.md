---
title: "Vulnerabilità delle Web Application & Mitigazioni: SQLi, Command Injection, XSS, CSRF & IDOR | DevSense"
description: "Proteggi le tue applicazioni PHP dalle vulnerabilità web più comuni. Scopri come prevenire SQL Injection, Command Injection, XSS, CSRF e IDOR con esempi di codice sicuro."
published: 2026-06-19
faq:
  - question: "Perché i prepared statement sono sicuri contro la SQL injection?"
    answer: "I prepared statement (istruzioni preparate) inviano separatamente al motore del database il modello della query SQL e i dati dei parametri. Il motore compila prima la query SQL, assicurando che i dati dei parametri non vengano mai interpretati o eseguiti come comandi SQL, indipendentemente dai caratteri che contengono."
  - question: "Quando è sicuro utilizzare la direttiva blade non formattata {!! $var !!} di Laravel?"
    answer: "È sicuro utilizzare `{!! $var !!}` solo quando la variabile contiene codice HTML grezzo generato da te o bonificato utilizzando una libreria di sanificazione HTML sicura come HTMLPurifier. Non dovresti mai stampare l'input grezzo dell'utente utilizzando questa direttiva."
  - question: "In che modo la ricerca basata sulle relazioni (relation-scoped lookup) previene l'IDOR?"
    answer: "La ricerca basata sulle relazioni (ad esempio, `$user->orders()->findOrFail($id)`) garantisce che la query del database filtri naturalmente i risultati in base all'ID dell'utente autenticato. Un utente malintenzionato che tenta di accedere all'ID di un altro utente riceverà un errore 404 Not Found, poiché il record non esiste all'interno della relazione definita."
---

# Vulnerabilità delle Web Application & Mitigazioni: SQLi, Command Injection, XSS, CSRF & IDOR

Sviluppare applicazioni web sicure non significa aggiungere la sicurezza al termine dello sviluppo. Richiede la comprensione di come le vulnerabilità si originano a livello di codice e la progettazione di limiti per prevenirle. 

In questa guida analizzeremo cinque vulnerabilità critiche delle applicazioni web (SQL Injection, Command Injection, Cross-Site Scripting, CSRF e IDOR) in PHP e Laravel, vedremo come vengono sfruttate e implementeremo mitigazioni sicure.

**Guide correlate:** [Architettura da monolito a microservizi](monolith-to-microservices-architecture) · [Osservabilità & monitoraggio](observability-monitoring-laravel)

## Indice

* [SQL Injection (SQLi)](#sql-injection)
* [Command Injection](#command-injection)
* [Cross-Site Scripting (XSS)](#xss)
* [Cross-Site Request Forgery (CSRF)](#csrf)
* [Insecure Direct Object References (IDOR)](#idor)
* [Errori Comuni](#common-mistakes)
* [Checklist](#checklist)
* [Riepilogo](#summary)
* [Quiz di Autovalutazione](#self-test-quiz)

---

<a id="sql-injection"></a>
## SQL Injection (SQLi)

La **SQL Injection** si verifica quando l'input non attendibile dell'utente viene concatenato direttamente all'interno delle stringhe di query SQL. Ciò consente agli aggressori di manipolare la struttura della query, aggirare l'autenticazione, leggere contenuti sensibili del database o eliminare record.

### La modalità errata: concatenazione di stringhe e ordinamento non sicuro

In questo caso, lo sviluppatore concatena le variabili di input direttamente nella query e utilizza un input non sanificato per definire la colonna di ordinamento.

```php
// app/Http/Controllers/ProductController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController
{
    public function search(Request $request): array
    {
        $search = $request->input('q');
        $sortBy = $request->input('sort', 'id');

        // VULNERABLE: SQL Injection via both search input and sorting column
        $sql = "SELECT * FROM products WHERE name LIKE '%{$search}%' ORDER BY {$sortBy} ASC";
        return DB::select($sql);
    }
}
```

### La modalità corretta: prepared statement e lista di elementi consentiti (allowlist) per le colonne

Per risolvere questo problema, dobbiamo utilizzare i **prepared statement (parameter binding)** per i valori dei dati delle query. Poiché le colonne di ordinamento non possono essere parametrizzate, dobbiamo validarle rispetto a una rigida **lista di elementi consentiti (allowlist)**.

```php
// app/Http/Controllers/ProductController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController
{
    private const ALLOWED_SORT_COLUMNS = ['id', 'name', 'price', 'created_at'];

    public function search(Request $request): array
    {
        $search = $request->input('q', '');
        
        // Strict sorting column allowlist fallback
        $sortBy = in_array($request->input('sort'), self::ALLOWED_SORT_COLUMNS, true) 
            ? $request->input('sort') 
            : 'id';

        // SECURE: Parameter binding for data, strict validation for identifiers
        return DB::select(
            "SELECT * FROM products WHERE name LIKE :search ORDER BY {$sortBy} ASC",
            ['search' => "%{$search}%"]
        );
    }
}
```

---

<a id="command-injection"></a>
## Command Injection

La **Command Injection** si verifica quando l'input dell'utente viene passato direttamente alle funzioni della shell di sistema come `exec()`, `shell_exec()` o `system()`. Ciò consente agli aggressori di eseguire comandi di shell arbitrari sul server con i permessi dell'utente del server web.

### La modalità errata: shell_exec con concatenazione di stringhe

In questo esempio, cerchiamo di convertire un file PDF in una miniatura PNG utilizzando uno strumento da riga di comando, ma passiamo il nome del file direttamente alla shell.

```php
// app/Services/ThumbnailGenerator.php
declare(strict_types=1);

namespace App\Services;

class ThumbnailGenerator
{
    public function generate(string $filename): string
    {
        $outputPath = "/tmp/" . uniqid('thumb_', true) . ".png";
        
        // VULNERABLE: Command injection if filename contains shell operators (e.g. "; rm -rf /;")
        $cmd = "pdftoppm -png -r 150 {$filename} {$outputPath}";
        shell_exec($cmd);

        return $outputPath;
    }
}
```

### La modalità corretta: componente Process con array di argomenti

Non invocare mai direttamente la shell né passare argomenti concatenati. Utilizza librerie wrapper di processi (come Symfony Process) che gestiscono in modo sicuro l'escaping degli argomenti e l'esecuzione senza utilizzare un ambiente shell.

```php
// app/Services/ThumbnailGenerator.php
declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ThumbnailGenerator
{
    public function generate(string $filePath): string
    {
        $outputPath = "/tmp/" . uniqid('thumb_', true) . ".png";
        
        // SECURE: Arguments are passed as an array, bypassing the shell shell interpreter
        $process = new Process(['pdftoppm', '-png', '-r', '150', $filePath, $outputPath]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $outputPath;
    }
}
```

---

<a id="xss"></a>
## Cross-Site Scripting (XSS)

Il **Cross-Site Scripting (XSS)** si verifica quando un'applicazione include dati utente non attendibili in una pagina web senza un escaping adeguato. Il browser dell'aggressore esegue JavaScript dannoso, in grado di rubare i cookie di sessione, acquisire gli input (keylogging) o reindirizzare gli utenti.

Esistono tre tipi di XSS:
- **Reflected XSS:** Lo script fa parte del payload della richiesta e viene riflesso immediatamente nella risposta.
- **Stored XSS:** Lo script viene salvato nel database (ad esempio, il testo di un commento) ed eseguito quando altri utenti visualizzano la pagina.
- **DOM-based XSS:** La vulnerabilità risiede esclusivamente nel codice JavaScript lato client che esegue variabili DOM non attendibili.

### La modalità errata: stampa di contenuti utente non formattati (senza escaping)

La direttiva `{!! $var !!}` di Laravel restituisce HTML grezzo, aggirando l'escaping predefinito di Blade per l'XSS.

```blade
<!-- resources/views/profile.blade.php -->
<div class="user-bio">
    <!-- VULNERABLE: Stored XSS if bio contains <script>alert('xss')</script> -->
    {!! $user->bio !!}
</div>
```

### La modalità corretta: eseguire l'escaping per impostazione predefinita e sanificare il Rich Text

Utilizza sempre `{{ $var }}`, che esegue automaticamente `e()` (`htmlspecialchars` di PHP) per l'escaping dell'output. Se devi stampare del testo formattato (rich text) fornito dall'utente, passalo attraverso una libreria di sanificazione HTML sicura (come HTMLPurifier).

```blade
<!-- resources/views/profile.blade.php -->
<div class="user-bio">
    <!-- SECURE: Automatically escaped by Blade -->
    {{ $user->bio }}
</div>

<div class="user-rich-content">
    <!-- SECURE: Outputting raw html only AFTER strict HTML purification -->
    {!! clean($user->rich_description) !!}
</div>
```

---

<a id="csrf"></a>
## Cross-Site Request Forgery (CSRF)

Il **Cross-Site Request Forgery (CSRF)** costringe il browser di un utente autenticato a eseguire azioni che modificano lo stato (come cambiare la password o avviare pagamenti) su un'applicazione alla quale ha attualmente effettuato l'accesso. Il browser allega automaticamente i cookie di sessione alle richieste cross-site, convalidando la richiesta.

### La modalità errata: azioni che modificano lo stato su richieste GET

Le richieste GET devono sempre essere **idempotenti** (sicure da eseguire più volte senza modificare lo stato). L'esecuzione di aggiornamenti sulle rotte GET rende il CSRF banale da sfruttare.

```php
// routes/web.php
// VULNERABLE: Anyone can link to /profile/delete in an img tag to delete an account
Route::get('/profile/delete', [ProfileController::class, 'destroy']);
```

### La modalità corretta: rotte POST/DELETE con token CSRF

Utilizza sempre metodi HTTP che modificano lo stato (POST, PUT, DELETE) e richiedi un token segreto e crittograficamente sicuro che non possa essere indovinato da siti web esterni.

```blade
<!-- resources/views/profile.blade.php -->
<!-- SECURE: Form triggers a POST request and generates a hidden CSRF token -->
<form action="{{ route('profile.destroy') }}" method="POST">
    @csrf
    @method('DELETE')
    <button type="submit">Delete Account</button>
</form>
```

---

<a id="idor"></a>
## Insecure Direct Object References (IDOR)

L'**IDOR (Insecure Direct Object Reference)** si verifica quando un'applicazione espone direttamente agli utenti una chiave di database o l'ID di una risorsa, omettendo di verificare se l'utente richiedente dispone dell'autorizzazione per accedere a quella specifica risorsa.

### La modalità errata: ricerca globale della query senza autorizzazione

In questo esempio, qualsiasi utente registrato può accedere ai dettagli della fattura di un altro utente semplicemente modificando `{id}` nel parametro dell'URL.

```php
// app/Http/Controllers/InvoiceController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController
{
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        // VULNERABLE: Retrieves invoice without checking user relationship or authorization
        $invoice = Invoice::findOrFail($id);
        
        return response()->json($invoice);
    }
}
```

### La modalità corretta: query basate sulle relazioni e autorizzazione tramite Gate

Disaccoppia il recupero delle risorse caricandole tramite lo scope del modello di relazione dell'utente o controlla i permessi utilizzando i Gate o le Policy di autorizzazione di Laravel.

```php
// app/Http/Controllers/InvoiceController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvoiceController
{
    public function show(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        // SECURE Method A: Query scoped directly to the authenticated user
        $invoice = $request->user()->invoices()->findOrFail($id);

        // SECURE Method B: Global lookup but validated with a Laravel Policy
        // $invoice = Invoice::findOrFail($id);
        // Gate::authorize('view', $invoice);
        
        return response()->json($invoice);
    }
}
```

---

<a id="common-mistakes"></a>
## Errori Comuni

1. **Eseguire l'escaping invece della parametrizzazione:** Pensare che l'esecuzione di `addslashes()` o di semplici espressioni regolari sulle variabili protegga le query. Parametrizza sempre.
2. **Alterazioni dello stato tramite GET:** Creare endpoint API o web che eseguono azioni di eliminazione o aggiornamento utilizzando il metodo HTTP GET.
3. **Mega-Gateway contenenti la validazione:** Affidare la validazione contro XSS/SQLi a WAF o Gateway invece di codificare una validazione robusta direttamente nel controller dell'applicazione.
4. **IDOR sulle richieste AJAX:** Proteggere le rotte principali delle pagine HTML ma esporre ID di risorse non autorizzati negli endpoint JSON di AJAX/API.

---

<a id="checklist"></a>
## Checklist

1. **Sicurezza delle query:** Stai concatenando variabili all'interno di istruzioni grezze `DB::select` o `whereRaw`?
2. **Esecuzione nella shell:** Puoi sostituire i comandi di shell con librerie PHP standard? In caso contrario, gli argomenti vengono passati in un array?
3. **Output di Blade:** Stai utilizzando `{!! !!}` in qualche parte dei template Blade senza la validazione di HTMLPurifier?
4. **Verifica dell'autorizzazione:** Ciascun metodo del controller che carica una risorsa verifica se la risorsa appartiene all'utente corrente?

---

<a id="summary"></a>
## Riepilogo

Le applicazioni web sicure convalidano rigorosamente gli input e applicano una difesa in profondità. Previeni la **SQL Injection** utilizzando prepared statement e liste di elementi consentiti (allowlist) per le colonne. Evita la **Command Injection** utilizzando array di argomenti all'interno di wrapper di processi. Blocca l'**XSS** eseguendo l'escaping dell'output delle variabili tramite i motori di template. Impedisci il **CSRF** isolando le modifiche dello stato alle richieste POST/DELETE protette da token. Sconfiggi l'**IDOR** limitando le ricerche alle relazioni dell'utente autenticato o eseguendo controlli sulle policy.

---

<a id="self-test-quiz"></a>
## Quiz di Autovalutazione

### Domanda 1: Qual è la differenza principale tra l'escaping e la parametrizzazione per le query SQL?
- A) L'escaping viene eseguito sul server del database, mentre la parametrizzazione viene gestita all'interno del motore PHP.
- B) L'escaping modifica i caratteri speciali all'interno delle stringhe di query per renderli sicuri, mentre la parametrizzazione invia la struttura della query e i parametri separatamente al motore del database.
- C) La parametrizzazione è compatibile solo con i database PostgreSQL.

<details>
<summary>Clicca per visualizzare la risposta</summary>

**Risposta: B**
L'escaping è un processo di manipolazione delle stringhe incline a bug del parser e ad aggiramenti. La parametrizzazione è una funzionalità del protocollo in cui la struttura SQL e i valori dei dati vengono inviati in modo indipendente, garantendo che i dati non possano mai alterare il modello della query.
</details>

### Domanda 2: Perché le richieste GET sono vulnerabili al CSRF anche se protette da token?
- A) I browser non supportano i moduli (form) GET.
- B) Le richieste GET espongono i token nella cronologia degli URL, nei log del browser e negli header Referer, e in ogni caso non dovrebbero mai essere utilizzate per operazioni che modificano lo stato.
- C) Le richieste GET aggirano automaticamente il middleware di verifica del token CSRF di Laravel.

<details>
<summary>Clicca per visualizzare la risposta</summary>

**Risposta: B**
Le richieste GET sono progettate per essere idempotenti (letture sicure). Se una richiesta GET modifica lo stato, gli aggressori possono incorporare l'URL di destinazione in collegamenti cross-site o tag immagine, attivando automaticamente la modifica dello stato senza il consenso dell'utente.
</details>

### Domanda 3: In che modo l'impostazione di una query di database basata sulla relazione dell'utente previene l'IDOR?
- A) Cripta le chiavi primarie del database.
- B) Blocca la richiesta a livello di firewall.
- C) Garantisce che la query SQL filtri naturalmente i record utilizzando l'ID dell'utente autenticato come vincolo di ricerca, rendendo irraggiungibili i record degli altri utenti.

<details>
<summary>Clicca per visualizzare la risposta</summary>

**Risposta: C**
Limitare lo scope delle query (ad esempio, `$user->invoices()->findOrFail($id)`) aggiunge una clausola `WHERE user_id = ?` alla query SQL. Se un utente malintenzionato tenta di recuperare l'ID di una risorsa appartenente a qualcun altro, la query restituirà zero record, producendo un errore 404 sicuro.
</details>
