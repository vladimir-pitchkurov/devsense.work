---
title: "PHP on the server: FPM, Swoole, workers & event-loop runtimes | DevSense"
description: "Padroneggia gli ambienti di esecuzione PHP moderni: confronta PHP-FPM, i server applicativi persistenti (Swoole, RoadRunner, FrankenPHP) e le event-loop asincrone di ReactPHP/AMPHP."
faq:
  - question: "Qual è la differenza tra PHP-FPM e Swoole?"
    answer: "PHP-FPM avvia un nuovo processo o riutilizza uno stato di processo pulito per ogni richiesta in arrivo, liberando memoria al termine. Swoole è un server applicativo persistente che carica il codice dell'applicazione una sola volta all'avvio e gestisce le richieste successive direttamente in memoria, aumentando notevolmente le prestazioni ma mantenendo persistente lo stato globale."
  - question: "Cos'è una fuga di memoria (memory leak) negli ambienti PHP persistenti?"
    answer: "Poiché i processi non terminano al termine di ogni richiesta, le proprietà statiche, i singleton o le variabili globali che accumulano dati cresceranno indefinitamente in memoria, portando infine il processo worker a superare i limiti di memoria consentiti ed a bloccarsi."
  - question: "In che modo RoadRunner gestisce i worker PHP?"
    answer: "RoadRunner è scritto in Go e funge da bilanciatore di carico e gestore di processi. Comunica con i processi worker PHP persistenti utilizzando il protocollo Goridge, delegando esternamente la gestione di HTTP, gRPC e code di messaggi."
  - question: "Perché le funzioni bloccanti rallentano le event-loop di ReactPHP o AMPHP?"
    answer: "ReactPHP e AMPHP sono event-loop a thread singolo (single-threaded). Se richiami una funzione bloccante (come sleep() o una query sincrona su database), il thread si blocca completamente, congelando il server per tutte le altre connessioni simultanee."
---

# PHP sul Server: FPM, Swoole, Workers & Event Loops

Molti sviluppatori pensano ancora che PHP si esegua solo in un modello a thread singolo a ciclo breve \"share-nothing\", in cui le variabili svaniscono dopo il caricamento della pagina. Tuttavia, gli ambienti di produzione moderni di PHP gestiscono connessioni WebSockets ad alta concorrenza, elaborano migliaia di richieste al secondo su un singolo thread utilizzando worker basati su Go ed eseguono event-loop asincrone. Se distribuisci un runtime PHP moderno supponendo il classico ciclo di richiesta/risposta, una singola fuga di memoria in una proprietà statica può causare il crash dell'intero cluster di produzione.

Con la comparsa di Swoole, RoadRunner, FrankenPHP e ReactPHP, il linguaggio PHP ha superato i limiti storici di PHP-FPM. Tuttavia, gli ambienti ad esecuzione persistente comportano nuovi rischi, in particolare le fughe di memoria (memory leak), la contaminazione dello stato condiviso (shared state) e le chiamate di I/O bloccanti che rallentano l'event-loop.

> [!IMPORTANT]
> Il PHP moderno non è più limitato a un singolo modello di esecuzione; la scelta del runtime corretto richiede la comprensione delle relazioni tra richieste brevi, worker persistenti ed event-loop asincrone.

---

## Indice
* [PHP-FPM (FastCGI Process Manager)](#php-fpm)
* [Server applicativi persistenti (Swoole & Laravel Octane)](#swoole)
* [RoadRunner (Supervisore basato su Go)](#roadrunner)
* [FrankenPHP (Modalità Worker basata su Caddy)](#frankenphp)
* [Architetture Event-Loop (ReactPHP & AMPHP)](#event-loop)
* [Fughe di memoria: il killer silenzioso](#memory-leaks)
* [Errori comuni](#common-mistakes)
* [Configurazioni pratiche](#practical-recipes)
* [🧠 Domande di autovalutazione](#self-check)

---

<a id="php-fpm"></a>
## PHP-FPM (FastCGI Process Manager)

PHP-FPM è il gestore di processi classico e collaudato. Nginx gestisce il traffico HTTP in ingresso e inoltra le richieste `.php` ai worker FPM tramite un socket Unix o una connessione TCP.

```nginx
# /etc/nginx/sites-available/default
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

* **Il funzionamento**: ogni processo worker gestisce una singola richiesta, invia l'output al client, reimposta le variabili e torna nel pool.
* **Perché è importante**: nessun rischio di accumulo di perdite di memoria tra richieste diverse. In caso di errore in uno script, il processo viene riavviato in modo pulito.
* **Limiti**: elevato costo di inizializzazione (es. caricamento dell'intero framework Laravel/Symfony da zero per ogni singola richiesta).

---

<a id="swoole"></a>
## Server applicativi persistenti (Swoole & Laravel Octane)

Swoole è un'estensione scritta in C che trasforma PHP in un server di rete ad alta concorrenza basato sulle coroutine.

```php
// app/Servers/HttpServer.php
$server = new Swoole\Http\Server("127.0.0.1", 9501);
$server->on("request", function ($request, $response) {
    $response->header("Content-Type", "text/plain");
    $response->end("Hello World");
});
$server->start();
```

* **Il funzionamento**: il codice PHP viene caricato in memoria **una sola volta** all'avvio. Le richieste successive vengono elaborate dai worker residenti in memoria.
* **Perché è importanto**: aumenta le prestazioni fino a 10 volte eliminando il tempo di bootstrap del framework.
* **Limiti**: qualsiasi fuga di memoria all'interno di singleton o variabili statiche persisterà e crescerà fino al crash del processo.

---

<a id="roadrunner"></a>
## RoadRunner (Supervisore basato su Go)

RoadRunner è un application server PHP, process manager e bilanciatore di carico ad alte prestazioni scritto in Go.

```yaml
# .rr.yaml
server:
  command: "php worker.php"
http:
  address: "0.0.0.0:8080"
  pool:
    num_workers: 4
```

* **Il funzionamento**: Go gestisce le richieste HTTP in arrivo, le connessioni WebSocket ed i servizi gRPC, instradandoli verso processi PHP persistenti tramite il protocollo binario Goridge.
* **Perché è importante**: unisce la concorrenza e la gestione dei processi tipiche di Go alla semplicità di sviluppo di PHP.

---

<a id="frankenphp"></a>
## FrankenPHP (Modalità Worker basata su Caddy)

FrankenPHP è un application server PHP moderno basato sul server web Caddy. Introduce una modalità \"worker\" che mantiene l'applicazione precaricata in memoria.

* **Perché è importante**: distribuzione semplificata sotto forma di singolo file binario. Supporta nativamente i protocolli HTTP/1.1, HTTP/2, HTTP/3 e la gestione automatica dei certificati TLS.

---

<a id="event-loop"></a>
## Architetture Event-Loop (ReactPHP & AMPHP)

A differenza di Swoole, che richiede un'estensione C, ReactPHP e AMPHP implementano event-loop scritte in puro PHP tramite il multitasking cooperativo.

```php
// app/Servers/ReactServer.php
require __DIR__ . '/vendor/autoload.php';

$loop = React\EventLoop\Loop::get();
$loop->addPeriodicTimer(1.0, function () {
    echo "Tick\n";
});
$loop->run();
```

* **Perché è importante**: ideale per microservizi orientati all'I/O, server di WebSocket e proxy server personalizzati.
* **Limiti**: non è possibile eseguire codice bloccante (come le normali operazioni PDO o `file_get_contents`). Tutti i driver del database ed i client HTTP devono essere non bloccanti.

---

<a id="memory-leaks"></a>
## Fughe di memoria: il killer silenzioso

Nei runtime persistenti (Swoole, RoadRunner, modalità worker di FrankenPHP, demoni CLI), la memoria non viene rilasciata in automatico al termine della richiesta.

```php
// app/Services/DataLeak.php
class DataLeak
{
    private static array $cache = [];

    public function cacheRequest(array $data)
    {
        // ❌ Perdita di memoria! Questo array cresce indefinitamente ad ogni richiesta
        self::$cache[] = $data; 
    }
}
```

> [!NOTE]
> Per evitare fugas di memoria, è necessario limitare la cache in memoria (es. tramite criteri di rimozione LRU), evitare proprietà statiche per la memorizzazione di stati legati alla singola richiesta e monitorare la memoria con funzioni come `memory_get_usage(true)`.

---

<a id="common-mistakes"></a>
## ⚠️ Errori comuni

### 1. Blocco dell'event-loop in ReactPHP / AMPHP
Il richiamo di una funzione sincrona bloccante sospende l'event-loop, bloccando l'intero server per tutti gli altri utenti.

```php
// app/Http/Handler.php
// ❌ Pericoloso: arresta l'esecuzione per tutte le richieste concorrenti
$data = file_get_contents("http://external-api.com/data");

// ✅ Soluzione corretta
// Utilizza client HTTP asincroni:
$browser = new React\Http\Browser();
$browser->get("http://external-api.com/data")->then(function ($response) {
    // Elabora la risposta in modo asincrono
});
```

### 2. Modifica di proprietà statiche di classe per richieste utente
La memorizzazione dei dati di sessione o di autenticazione dell'utente in proprietà statiche provocherà perdite di dati a favore di altre richieste concorrenti.

```php
// app/Services/AuthService.php
// ❌ Pericoloso: l'utente 2 potrebbe vedere l'identità dell'utente 1!
class AuthService
{
    public static ?User $currentUser = null;
}
```

---

<a id="practical-recipes"></a>
## Configurazioni pratiche

### Riciclaggio sicuro dei worker
Per contrastare i problemi di memory leak in produzione, configura il process manager per riavviare i worker dopo che hanno servito un numero definito di richieste o al raggiungimento di una determinata soglia di memoria.

```ini
# /etc/php/8.3/fpm/pool.d/www.conf (PHP-FPM)
; Ricicla i worker dopo 500 richieste per ripulire le perdite minori
pm.max_requests = 500
```

```yaml
# .rr.yaml (RoadRunner)
http:
  pool:
    # Arresta i worker quando superano i 100MB di RAM o dopo 1000 esecuzioni
    max_jobs: 1000
    allocate_timeout: 60s
    destroy_timeout: 60s
    supervisor:
      watch_interval: 1s
      max_worker_memory: 100 # MB
```

---

## 🧠 Domande di autovalutazione

1. **Vero o Falso?** In PHP-FPM, le variabili statiche condividono memoria tra richieste HTTP separate.
2. Cosa succede se esegui `sleep(5)` all'interno di un gestore di richieste ReactPHP?
3. In che modo la modalità worker di FrankenPHP ottiene incrementi di prestazioni rispetto a PHP-FPM?
4. Qual è la funzione principale di `pm.max_requests` in PHP-FPM?

<details>
<summary><b>Mostra risposte</b></summary>

1. **Falso.** In PHP-FPM, la memoria allocata all'intero processo viene ripulita ed azzerata al termine di ogni richiesta (o il processo viene riciclato), quindi le variabili statiche non persistono tra richieste diverse.
2. Blocca l'event-loop a thread singolo per 5 secondi. Durante questo intervallo di tempo, il server non può accettare né rispondere ad altre connessioni.
3. Carica l'applicazione PHP e le librerie del framework in memoria una sola volta, saltando il caricamento dal file system ed i passaggi di compilazione per le richieste successive.
4. Riavvia il processo worker di PHP-FPM dopo che ha servito un numero configurato di richieste, prevenendo l'esaurimento della memoria del server dovuto a perdite di memoria in estensioni o codici di terze parti.
</details>
