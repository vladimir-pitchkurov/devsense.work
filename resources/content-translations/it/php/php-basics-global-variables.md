---
title: "Concetti base di PHP e variabili globali: Scope e superglobali | DevSense"
description: "Una guida completa agli scope delle variabili in PHP (locale, globale, statico) e a tutte le superglobali. Impara le best practice, le trappole di sicurezza e le restrizioni di PHP 8.1+."
faq:
  - question: "Qual è la differenza tra gli scope delle variabili locale, globale e statico in PHP?"
    answer: "Le variabili locali esistono solo all'interno della funzione in cui sono dichiarate. Le variabili globali esistono al di fuori delle funzioni e vi si deve accedere tramite la parola chiave 'global' o l'array $GLOBALS. Le variabili statiche esistono a livello locale ma mantengono il loro valore attraverso più esecuzioni della funzione."
  - question: "Perché la riassegnazione della variabile $GLOBALS fallisce in PHP 8.1+?"
    answer: "A partire da PHP 8.1, Zend Engine impedisce la riassegnazione dell'intero array $GLOBALS (ad esempio, $GLOBALS = [] è vietato) per ottimizzare la velocità di ricerca delle variabili e garantire la coerenza interna. È comunque possibile modificare singole chiavi (ad esempio, $GLOBALS['myVar'] = 'value')."
  - question: "Come dovrei gestire il caricamento sicuro dei file tramite $_FILES in PHP?"
    answer: "Verifica sempre lo stato del caricamento tramite $_FILES['field']['error'], valida la dimensione e il tipo MIME del file lato server, verifica il file con is_uploaded_file() e spostalo in una directory sicura utilizzando move_uploaded_file()."
  - question: "Perché l'accesso diretto a $_GET o $_POST è sconsigliato nello sviluppo PHP moderno?"
    answer: "L'accesso diretto alle superglobali accoppia il codice allo stato globale, rendendo i test unitari e il mocking delle richieste estremamente difficili. Inoltre, bypassa il filtraggio dei dati, aumentando il rischio di XSS e SQL injection. Utilizza invece un wrapper della richiesta (request wrapper)."
---

# PHP Basics: Variable Scopes and Superglobals

**Difficulty Level:** Junior  
**Target PHP Versions:** PHP 7.0+ (with features marked up to PHP 8.3+)

Immagina di distribuire un refactoring minore del codice, solo per scoprire che il tuo ambiente di produzione va in crash con un'eccezione fatale a runtime: `Fatal error: Cannot reassign $GLOBALS`. O forse hai scritto un semplice modulo di contatto, solo per scoprire che un utente malintenzionato ha iniettato un payload XSS direttamente tramite `$_POST` e ha violato le sessioni amministratore.

In PHP, gli scope delle variabili e le superglobali sono le fondamenta del flusso di dati. Tuttavia, rimangono una fonte frequente di contaminazione dello scope (scope pollution), perdite di memoria (memory leak) e vulnerabilità di sicurezza critiche per gli sviluppatori che si avvicinano a questo linguaggio. Capire come viaggiano le variabili tra gli scope e come si comportano le superglobali di PHP — specialmente sotto le rigide regole dei moderni motori PHP — è essenziale per scrivere codice sicuro, testabile e robusto.

---

## Variable Scopes: Local, Global, and Static

Lo scope (ambito di visibilità) definisce la visibilità e il ciclo di vita delle variabili nel codice. In PHP, le variabili non trapelano automaticamente negli scope annidati se non esplicitamente richiesto.

### Local Scope
* **Point**: Le variabili dichiarate all'interno di una funzione o di un metodo di classe sono isolate dall'ambiente esterno.
* **Why it matters**: Lo scope locale previene le collisioni di nomi e le modifiche impreviste dello stato. Una variabile chiamata `$user` all'interno di una funzione non può sovrascrivere accidentalmente una variabile `$user` definita altrove.
* **Example**:
  ```php
  // app/Services/CalculationService.php
  
  function calculateTotal(float $price, float $tax): float
  {
      $total = $price + ($price * $tax); // Local variable
      return $total;
  }
  
  // Accessing $total here will throw a Warning (Undefined variable $total)
  ```
* **Consequence**: Una volta terminata l'esecuzione della funzione, le sue variabili locali vengono distrutte e la loro memoria viene liberata. Qualsiasi tentativo di accedervi dallo scope globale genera un avviso di variabile non definita (Warning).

### Global Scope
* **Point**: Le variabili definite al di fuori di qualsiasi funzione o classe appartengono allo scope globale. Tuttavia, non sono automaticamente accessibili all'interno delle funzioni.
* **Why it matters**: Le variabili globali consentono di condividere configurazioni o connessioni al database tra file diversi, ma l'accesso ad esse all'interno delle funzioni richiede parole chiave esplicite.
* **Example**:
  ```php
  // config/database.php
  
  $dbDsn = "mysql:host=127.0.0.1;dbname=app_db";
  
  function connect(): PDO
  {
      // Accessing global scope requires the 'global' keyword
      global $dbDsn; 
      
      return new PDO($dbDsn, "root", "secret");
  }
  ```
* **Consequence**: L'affidarsi alla parola chiave `global` introduce dipendenze nascoste. Rende i test unitari estremamente difficili perché il framework di test non può facilmente isolare o simulare (mock) i valori globali.

### Static Scope
* **Point**: Una variabile statica viene dichiarata all'interno di una funzione utilizzando la parola chiave `static`. Esiste solo all'interno dello scope locale di quella funzione, ma mantiene il suo valore tra le successive esecuzioni della funzione stessa.
* **Why it matters**: Le variabili statiche sono ideali per memorizzare in cache calcoli onerosi, tenere traccia della profondità di ricorsione o generare ID di sequenza leggeri senza inquinare lo spazio dei nomi globale.
* **Example**:
  ```php
  // app/Utils/SequenceGenerator.php
  
  function getNextSequenceId(): int
  {
      static $id = 0; // Initialized only on the first call
      $id++;
      return $id;
  }
  
  echo getNextSequenceId(); // 1
  echo getNextSequenceId(); // 2
  ```
  > [!NOTE]
  > **Nota sulla versione PHP**: A partire da PHP 8.3+, le variabili statiche possono essere inizializzate con espressioni dinamiche (ad esempio chiamando altre funzioni). Prima di PHP 8.3, potevano essere inizializzate solo con valori costanti o letterali.
* **Consequence**: La variabile persiste in memoria per tutta la durata del processo PHP corrente. Se l'applicazione viene eseguita in ambienti con processi persistenti (come RoadRunner o FrankenPHP), le variabili statiche manterranno lo stato tra le diverse richieste HTTP. Ciò può causare perdite di memoria o mescolare i dati degli utenti se non vengono azzerate.

---

## PHP Superglobals

Le superglobali sono array associativi integrati sempre disponibili in tutti gli scope durante il ciclo di vita dello script. Non è necessario utilizzare la parola chiave `global` per accedervi.

### 1. `$GLOBALS` (con restrizioni di riassegnazione in PHP 8.1+)
* **Point**: Un array associativo contenente i riferimenti a tutte le variabili attualmente definite nello scope globale dello script.
* **Why it matters**: Fornisce un accesso programmatico e dinamico allo scope globale senza dover dichiarare `global $varName`.
* **Example**:
  ```php
  // app/Utils/DebugHelper.php
  
  $debugMode = true;
  
  function isDebugEnabled(): bool
  {
      // Directly check the global variable through the superglobal array
      return $GLOBALS['debugMode'] ?? false;
  }
  ```
* **Restrizione PHP 8.1+**:
  Prima di PHP 8.1, l'array `$GLOBALS` poteva essere riassegnato o passato per riferimento. In PHP 8.1+, la riassegnazione dell'intero array `$GLOBALS` (ad esempio `$GLOBALS = [];` o `$GLOBALS =& $otherArray;`) è vietata e genera un errore fatale a runtime o in fase di compilazione. Questa restrizione è stata introdotta per ottimizzare la velocità di ricerca delle variabili all'interno del motore Zend. È comunque possibile modificare singole chiavi:
  ```php
  // app/Utils/LegacyRunner.php
  
  // This throws a Compile Error in PHP 8.1+:
  // $GLOBALS = ['app_env' => 'production']; 
  
  // This is fully supported and correct:
  $GLOBALS['app_env'] = 'production'; 
  ```
* **Consequence**: I progetti con codice legacy che azzeravano lo stato globale durante le fasi di teardown dei test unitari utilizzando `$GLOBALS = []` devono essere riscritti per reimpostare le variabili chiave per chiave.

### 2. `$_SERVER`
* **Point**: Contiene intestazioni (header), percorsi, posizioni degli script e variabili d'ambiente del server web.
* **Why it matters**: Essenziale per il routing delle richieste, il controllo del metodo di richiesta (GET, POST) e la convalida delle intestazioni HTTP.
* **Example**:
  ```php
  // public/index.php
  
  $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
  $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  ```
* **Consequence**: I valori con prefisso `HTTP_` (ad esempio `HTTP_USER_AGENT`, `HTTP_X_FORWARDED_FOR`) sono forniti direttamente dal browser del client. Non devono mai essere considerati attendibili in modo cieco, poiché possono essere facilmente contraffatti.

### 3. `$_GET`
* **Point**: Un array associativo di variabili della query string passate allo script tramite l'URL.
* **Why it matters**: Utilizzato per passare parametri di navigazione non sensibili, come offset di paginazione, filtri e termini di ricerca.
* **Example**:
  ```php
  // app/Controllers/SearchController.php
  
  // URL: /search.php?query=php&page=2
  $query = $_GET['query'] ?? '';
  $page = (int)($_GET['page'] ?? 1); // Cast to int for safety
  ```
* **Consequence**: I parametri di query vengono memorizzati nella cronologia del browser e nei log del server web. Non passare mai credenziali sensibili (password, chiavi API o token di ripristino) tramite `$_GET`.

### 4. `$_POST`
* **Point**: Un array associativo di variabili passate tramite HTTP POST (ad esempio, moduli HTML o payload `application/x-www-form-urlencoded`).
* **Why it matters**: Utilizzato per trasmettere set di dati di grandi dimensioni, metadati dei file e payload sensibili che modificano i dati sul server.
* **Example**:
  ```php
  // app/Controllers/RegistrationController.php
  
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      // PHP 8.0+ allows throw expressions with null coalescing:
      $email = $_POST['email'] ?? throw new InvalidArgumentException('Email is required');
      $password = $_POST['password'] ?? '';
  }
  ```
* **Consequence**: Se un utente invia un modulo con un payload che supera l'impostazione `post_max_size` in `php.ini`, PHP scarta silenziosamente il payload. L'array `$_POST` risulterà vuoto e non verrà generato alcun errore automatico.

### 5. `$_SESSION`
* **Point**: Un array associativo contenente le variabili di sessione disponibili per lo script corrente.
* **Why it matters**: Consente di mantenere l'identità e lo stato dell'utente (come i carrelli degli acquisti o lo stato di login) attraverso più richieste HTTP senza stato (stateless).
* **Example**:
  ```php
  // app/Services/AuthenticationService.php
  
  // PHP 7.0+ allows passing runtime options to session_start()
  session_start([
      'cookie_httponly' => true,
      'cookie_secure' => true,
      'cookie_samesite' => 'Lax',
  ]);
  
  $_SESSION['is_logged_in'] = true;
  $_SESSION['username'] = 'john_doe';
  ```
* **Consequence**: `session_start()` deve essere richiamato prima di accedere a `$_SESSION`. Se i cookie di sessione non sono configurati con il flag `HttpOnly`, l'ID di sessione può essere rubato tramite XSS, compromettendo gli account degli utenti.

### 6. `$_COOKIE`
* **Point**: Un array associativo contenente i cookie inviati al server dal browser dell'utente.
* **Why it matters**: Consente di leggere le preferenze lato client o i token di autenticazione di tipo "Ricordami".
* **Example**:
  ```php
  // app/Services/ThemeService.php
  
  $userTheme = $_COOKIE['preferred_theme'] ?? 'dark';
  ```
* **Consequence**: I cookie sono memorizzati interamente lato client. Un utente può modificare manualmente i valori dei cookie all'interno del proprio browser. Non fidarsi mai dei valori di `$_COOKIE` per logiche di business critiche (come `$_COOKIE['is_admin'] = 1`).

### 7. `$_ENV`
* **Point**: Un array associativo contenente le variabili d'ambiente passate al processo PHP.
* **Why it matters**: Mantiene le credenziali sensibili (password del database, chiavi API del mailer) protette e separate dal codice sorgente.
* **Example**:
  ```php
  // app/Config/Database.php
  
  $dbPassword = $_ENV['DB_PASSWORD'] ?? 'default_password';
  ```
* **Consequence**: `$_ENV` può risultare completamente vuoto se la direttiva `variables_order` nel file `php.ini` non contiene la lettera `"E"` (ad esempio, se è configurata come `"GPCS"`). Se `$_ENV` è vuoto, usa `getenv()` o aggiorna la tua configurazione.

### 8. `$_FILES`
* **Point**: Un array associativo contenente metadati sui file caricati sul server tramite HTTP POST.
* **Why it matters**: Consente l'accesso sicuro alle proprietà del file caricato dal client (nome, tipo, dimensione, percorso temporaneo, codice di errore) per convalidarlo e spostarlo.
* **Example**:
  ```php
  // app/Services/UploadService.php
  
  if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
      $tempPath = $_FILES['profile_pic']['tmp_name'];
      
      // Strict security validation
      if (is_uploaded_file($tempPath)) {
          $filename = uniqid('avatar_', true) . '.png';
          move_uploaded_file($tempPath, '/var/www/uploads/' . $filename);
      }
  }
  ```
* **Consequence**: Se i file non vengono spostati utilizzando `move_uploaded_file()` durante l'esecuzione della richiesta, PHP cancella automaticamente il file temporaneo dalla directory temporanea al termine dello script.

---

## Limitations, Trade-offs, and Clean Architecture

Sebbene le variabili globali e le superglobali siano integrate in PHP per facilitarne l'uso, le moderne pratiche di ingegneria del software scoraggiano il loro utilizzo diretto all'interno della logica di business.

* **Lack of Testability**: L'accesso diretto a `$_GET`, `$_POST` o `$_SERVER` accoppia direttamente la classe di servizio allo stato globale. Questo rende impossibile effettuare test unitari, poiché non è possibile isolare il contesto di esecuzione senza modificare le variabili globali prima di ogni test.
* **Security Exposure**: Le superglobali rappresentano input grezzi e non attendibili del client. Accedere direttamente a `$_POST['username']` anziché utilizzare un oggetto di trasferimento dati (DTO) convalidato aumenta la superficie di attacco per SQL Injection e Cross-Site Scripting (XSS).
* **The Framework Alternative**: I moderni framework PHP (come Laravel e Symfony) incapsulano le superglobali in oggetti Request e Response. Invece di leggere `$_GET['id']`, si inietta un'istanza di `Request`:
  ```php
  // app/Http/Controllers/UserController.php
  
  // Modern framework approach using Dependency Injection
  public function show(Request $request): Response
  {
      $userId = $request->query('id'); // Mockable and testable
      // ...
  }
  ```

---

## Practical Takeaways

1. **Ridurre lo scope**: Mantieni le variabili il più locali possibile. Passa le variabili alle funzioni come argomenti anziché accedervi utilizzando la parola chiave `global`.
2. **Gestire i cicli di vita di RoadRunner/FrankenPHP**: Se utilizzi variabili statiche, puliscile o ripristinale se l'ambiente utilizza processi worker persistenti. In caso contrario, si verificheranno perdite di memoria o di dati tra gli utenti.
3. **Utilizzare request wrapper**: Se stai scrivendo codice in PHP nativo (信号framework), crea una classe wrapper o utilizza `filter_input()` invece di accedere direttamente alle superglobali.
4. **Prepararsi a PHP 8.1+**: Assicurati di non avere codice di librerie legacy che riassegna o passa `$GLOBALS` per riferimento.

---

## Self-Check Quiz

Metti alla prova la tua comprensione degli scope e delle superglobali in PHP.

### Question 1: Qual è l'output del seguente codice PHP?
```php
// app/Http/Controllers/TestController.php

$name = "Alice";

function greet(): string
{
    return "Hello, " . $name;
}

echo greet();
```
- A) `Hello, Alice`
- B) `Hello, ` insieme a un avviso di variabile non definita (Undefined Variable Warning)
- C) `Hello, $name`

<details>
<summary>Clicca per visualizzare la risposta e la spiegazione</summary>

**Risposta: B**  
Le variabili definite nello scope globale non sono automaticamente visibili all'interno delle funzioni. Poiché `$name` non è dichiarata utilizzando la parola chiave `global` o vi si accede tramite `$GLOBALS['name']` all'interno di `greet()`, PHP genera un avviso di variabile non definita e tratta la variabile come `null`.
</details>

### Question 2: Quale affermazione è vera riguardo alla riassegnazione di `$GLOBALS`?
- A) `$GLOBALS` può essere riassegnato in tutte le versioni di PHP.
- B) La riassegnazione di `$GLOBALS` (ad esempio `$GLOBALS = [];`) genererà un errore fatale a partire da PHP 8.1+.
- C) La modifica di singole chiavi come `$GLOBALS['user'] = 'Bob';` è deprecata e disabilitata in PHP 8.1+.

<details>
<summary>Clicca per visualizzare la risposta e la spiegazione</summary>

**Risposta: B**  
A partire da PHP 8.1, non è più possibile riassegnare l'array `$GLOBALS` stesso a causa di ottimizzazioni nella tabella dei simboli del motore Zend. Tuttavia, la modifica di singole chiavi (come `$GLOBALS['user'] = 'Bob'`) rimane pienamente supportata e funzionante.
</details>

### Question 3: Perché dovresti evitare di fidarti di `$_FILES['input_name']['type']`?
- A) È sempre vuoto.
- B) È determinato da PHP lato server ed è spesso errato.
- C) Viene inviato dal browser (client) e può essere facilmente falsificato da un utente malintenzionato.

<details>
<summary>Clicca per visualizzare la risposta e la spiegazione</summary>

**Risposta: C**  
Il tipo MIME in `$_FILES['...']['type']` viene fornito direttamente dalle intestazioni di richiesta del browser client. Un utente malintenzionato può caricare uno script `.php` dannoso ma contraffare l'intestazione per farlo apparire come `image/png`. Convalida sempre il tipo MIME sul server utilizzando `finfo_file()` o l'estensione `fileinfo`.
</details>
