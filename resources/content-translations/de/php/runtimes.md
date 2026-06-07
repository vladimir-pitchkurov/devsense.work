---
title: "PHP on the server: FPM, Swoole, workers & event-loop runtimes | DevSense"
description: "Meistern Sie moderne PHP-Ausführungsumgebungen: Vergleichen Sie PHP-FPM, langlebige Anwendungsserver (Swoole, RoadRunner, FrankenPHP) sowie asynchrone Event-Loops von ReactPHP/AMPHP und vermeiden Sie Memory Leaks."
faq:
  - question: "Was ist der Unterschied zwischen PHP-FPM und Swoole?"
    answer: "PHP-FPM startet für jede eingehende Anfrage einen neuen Prozess oder verwendet einen sauberen Prozesszustand wieder und gibt den Speicher nach Beendigung frei. Swoole ist ein langlebiger Anwendungsserver, der den Anwendungscode einmal lädt und nachfolgende Anfragen im Arbeitsspeicher verarbeitet, was die Performance erheblich steigert, aber den globalen Zustand beibehält."
  - question: "Was ist ein Memory Leak in persistenten PHP-Runtimes?"
    answer: "Da Prozesse nach einer Anfrage nicht beendet werden, wachsen statische Eigenschaften, Singletons oder globale Variablen, die Daten ansammeln, unbegrenzt an. Dies führt letztendlich dazu, dass der Worker-Prozess die Speichergrenzen überschreitet und abstürzt."
  - question: "Wie verwaltet RoadRunner PHP-Worker?"
    answer: "RoadRunner ist in Go geschrieben und fungiert als Load Balancer und Prozessmanager. Er kommuniziert mit persistenten PHP-Worker-Prozessen über das Goridge-Protokoll und wickelt HTTP, gRPC und Warteschlangen extern ab."
  - question: "Warum blockieren blockierende Funktionen die Event-Loops von ReactPHP oder AMPHP?"
    answer: "ReactPHP und AMPHP sind Single-Threaded-Event-Loops. Wenn Sie eine blockierende Funktion (wie sleep() oder eine synchrone Datenbankabfrage) aufrufen, hält der Thread vollständig an, was den Server für alle anderen gleichzeitigen Verbindungen blockiert."
---

# PHP auf dem Server: FPM, Swoole, Workers & Event-Loops

Viele Entwickler denken immer noch, dass PHP ausschließlich in einem Single-Threaded, kurzlebigen „Share-Nothing“-Modell ausgeführt wird, bei dem Variablen nach dem Neuladen der Seite verschwinden. Doch modernes PHP im Produktivbetrieb hostet hochperformante WebSockets, verarbeitet Tausende von Anfragen pro Sekunde in einem einzigen Thread über Go-gesteuerte Worker und betreibt asynchrone Event-Loops. Wenn Sie eine moderne PHP-Runtime im Glauben an den klassischen Request/Response-Zyklus bereitstellen, kann ein einziges Speicherleck (Memory Leak) in einer statischen Eigenschaft Ihr gesamtes Produktions-Cluster zum Absturz bringen.

Mit dem Aufkommen von Swoole, RoadRunner, FrankenPHP und ReactPHP hat PHP die Grenzen von PHP-FPM gesprengt. Langlebige Laufzeitumgebungen bringen jedoch neue Risiken mit sich – insbesondere Speicherlecks, Kontaminierung gemeinsamer Zustände (Shared State) und blockierende I/O-Aufrufe, die die Event-Loop lahmlegen.

> [!IMPORTANT]
> Modernes PHP ist nicht mehr an ein einziges Ausführungsmodell gebunden. Die Wahl der richtigen Runtime erfordert das Verständnis des Zusammenspiels zwischen kurzlebigen Requests, persistenten Workern und asynchronen Event-Loops.

---

## Inhalt
* [PHP-FPM (FastCGI Process Manager)](#php-fpm)
* [Langlebige Anwendungsserver (Swoole & Laravel Octane)](#swoole)
* [RoadRunner (Go-gesteuerter Supervisor)](#roadrunner)
* [FrankenPHP (Caddy-basierter Worker-Modus)](#frankenphp)
* [Event-Loop-Stacks (ReactPHP & AMPHP)](#event-loop)
* [Speicherlecks (Memory Leaks): Der stille Killer](#memory-leaks)
* [Häufige Fehler](#common-mistakes)
* [Praktische Rezepte](#practical-recipes)
* [🧠 Selbsttest-Fragen](#self-check)

---

<a id="php-fpm"></a>
## PHP-FPM (FastCGI Process Manager)

PHP-FPM ist der klassische, praxiserprobte Prozessmanager. Nginx nimmt den HTTP-Verkehr entgegen und leitet `.php`-Anfragen über einen Unix-Socket oder TCP an FPM-Worker weiter.

```nginx
# /etc/nginx/sites-available/default
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

* **Das Funktionsprinzip**: Jeder Worker-Prozess verarbeitet genau eine Anfrage, gibt die Ausgabe aus, setzt Variablen zurück und kehrt in den Pool zurück.
* **Der Vorteil**: Kein Risiko der Anhäufung von Speicherlecks zwischen verschiedenen Anfragen. Wenn ein Skript fehlschlägt, wird der Prozess sauber recycelt.
* **Einschränkung**: Hoher Bootstrap-Overhead (z. B. das Laden des Frameworks wie Laravel/Symfony von Grund auf bei jeder einzelnen Anfrage).

---

<a id="swoole"></a>
## Langlebige Anwendungsserver (Swoole & Laravel Octane)

Swoole ist eine C-Erweiterung, die PHP mithilfe von Coroutinen in einen hochperformanten Netzwerkserver verwandelt.

```php
// app/Servers/HttpServer.php
$server = new Swoole\Http\Server("127.0.0.1", 9501);
$server->on("request", function ($request, $response) {
    $response->header("Content-Type", "text/plain");
    $response->end("Hello World");
});
$server->start();
```

* **Das Funktionsprinzip**: Der PHP-Code wird beim Start **einmal** in den Speicher geladen. Nachfolgende Anfragen werden von im Speicher verbleibenden Workern verarbeitet.
* **Der Vorteil**: Steigert den Durchsatz um das Zehnfache, da der Bootstrap-Overhead des Frameworks entfällt.
* **Einschränkung**: Jedes Speicherleck in Singletons oder statischen Variablen bleibt bestehen und wächst, bis der Prozess abstürzt.

---

<a id="roadrunner"></a>
## RoadRunner (Go-gesteuerter Supervisor)

RoadRunner ist ein in Go geschriebener, hochperformanter PHP-Anwendungsserver, Prozessmanager und Load Balancer.

```yaml
# .rr.yaml
server:
  command: "php worker.php"
http:
  address: "0.0.0.0:8080"
  pool:
    num_workers: 4
```

* **Das Funktionsprinzip**: Go verarbeitet eingehende HTTP-Anfragen, WebSocket-Handshakes und gRPC-Endpunkte und leitet sie über das binäre Goridge-Protokoll an persistente PHP-Prozesse weiter.
* **Der Vorteil**: Verbindet die Nebenläufigkeit und Prozessverwaltung von Go mit der einfachen Entwicklung in PHP.

---

<a id="frankenphp"></a>
## FrankenPHP (Caddy-basierter Worker-Modus)

FrankenPHP ist ein moderner PHP-App-Server, der auf dem Caddy-Webserver aufbaut. Er bringt einen „Worker-Modus“ mit, der Ihre Anwendung dauerhaft im Speicher hält.

* **Der Vorteil**: Einfache Bereitstellung als einzelnes Binary. Unterstützt HTTP/1.1, HTTP/2, HTTP/3 und automatische TLS-Zertifikate direkt out-of-the-box.

---

<a id="event-loop"></a>
## Event-Loop-Stacks (ReactPHP & AMPHP)

Im Gegensatz zu Swoole, das auf einer C-Erweiterung basiert, implementieren ReactPHP und AMPHP reine PHP-Event-Loops für kooperatives Multitasking.

```php
// app/Servers/ReactServer.php
require __DIR__ . '/vendor/autoload.php';

$loop = React\EventLoop\Loop::get();
$loop->addPeriodicTimer(1.0, function () {
    echo "Tick\n";
});
$loop->run();
```

* **Der Vorteil**: Perfekt für I/O-intensive Microservices, WebSocket-Server und Proxy-Server.
* **Einschränkung**: Sie dürfen keinen blockierenden Code ausführen (wie Standard-PDO-Datenbankoperationen oder `file_get_contents`). Alle Datenbanktreiber und HTTP-Clients müssen nicht-blockierend sein.

---

<a id="memory-leaks"></a>
## Speicherlecks (Memory Leaks): Der stille Killer

In persistenten Laufzeitumgebungen (Swoole, Roadrunner, FrankenPHP-Worker-Modus, CLI-Daemons) wird der Speicher nach einer Anfrage nicht automatisch freigegeben.

```php
// app/Services/DataLeak.php
class DataLeak
{
    private static array $cache = [];

    public function cacheRequest(array $data)
    {
        // ❌ Verursacht ein Speicherleck! Dieses Array wächst mit jeder Anfrage unendlich weiter.
        self::$cache[] = $data; 
    }
}
```

> [!NOTE]
> Um Speicherlecks zu verhindern, müssen Sie In-Memory-Caches (z. B. durch LRU-Evictors) begrenzen, statische Eigenschaften für anfragespezifische Zustände vermeiden und den Speicher über Funktionen wie `memory_get_usage(true)` überwachen.

---

<a id="common-mistakes"></a>
## ⚠️ Häufige Fehler

### 1. Blockieren der Event-Loop in ReactPHP / AMPHP
Der Aufruf einer synchronen, blockierenden Funktion hält die gesamte Event-Loop an und blockiert den Server für alle anderen Benutzer.

```php
// app/Http/Handler.php
// ❌ Gefährlich: Blockiert die Ausführung für alle gleichzeitigen Anfragen
$data = file_get_contents("http://external-api.com/data");

// ✅ Korrekter Ansatz
// Verwenden Sie asynchrone HTTP-Clients:
$browser = new React\Http\Browser();
$browser->get("http://external-api.com/data")->then(function ($response) {
    // Antwort asynchron verarbeiten
});
```

### 2. Ändern statischer Klasseneigenschaften bei Benutzeranfragen
Das Speichern von Benutzersitzungen oder Authentifizierungsdaten in statischen Eigenschaften führt dazu, dass Benutzerdaten in andere Anfragen übergehen.

```php
// app/Services/AuthService.php
// ❌ Gefährlich: Benutzer 2 könnte die Identität von Benutzer 1 sehen!
class AuthService
{
    public static ?User $currentUser = null;
}
```

---

<a id="practical-recipes"></a>
## Praktische Rezepte

### Sicheres Recyceln von Workern
Um Speicherlecks in der Produktion entgegenzuwirken, konfigurieren Sie Ihren Prozessmanager so, dass er Worker nach einer bestimmten Anzahl von Anfragen oder beim Erreichen eines Speicherlimits neu startet.

```ini
# /etc/php/8.3/fpm/pool.d/www.conf (PHP-FPM)
; Recycelt Worker nach 500 Anfragen, um kleine Speicherlecks zu bereinigen
pm.max_requests = 500
```

```yaml
# .rr.yaml (RoadRunner)
http:
  pool:
    # Beendet Worker, wenn sie 100 MB RAM überschreiten oder 1000 Jobs abgearbeitet haben
    max_jobs: 1000
    allocate_timeout: 60s
    destroy_timeout: 60s
    supervisor:
      watch_interval: 1s
      max_worker_memory: 100 # MB
```

---

## 🧠 Selbsttest-Fragen

1. **Richtig oder Falsch?** In PHP-FPM verursachen statische Variablen Speicherlecks über verschiedene HTTP-Anfragen hinweg.
2. Was passiert, wenn Sie `sleep(5)` innerhalb eines ReactPHP-Request-Handlers ausführen?
3. Wie erzielt der Worker-Modus von FrankenPHP Performance-Gewinne im Vergleich zu PHP-FPM?
4. Was ist die Hauptaufgabe von `pm.max_requests` in PHP-FPM?

<details>
<summary><b>Antworten anzeigen</b></summary>

1. **Falsch.** In PHP-FPM wird der gesamte Prozessspeicher zwischen den Anfragen gelöscht und zurückgesetzt (oder der Prozess wird recycelt), was bedeutet, dass statische Variablen nicht über Anfragen hinweg bestehen bleiben.
2. Es blockiert die Single-Threaded-Event-Loop für 5 Sekunden. In dieser Zeit kann der Server keine anderen Verbindungen annehmen oder beantworten.
3. Er lädt die PHP-Anwendung und die Framework-Bibliotheken einmalig in den Arbeitsspeicher und umgeht so das erneute Laden des Dateisystems und die Kompilierungsphasen bei nachfolgenden Anfragen.
4. Es startet den PHP-FPM-Worker-Prozess neu, nachdem er eine konfigurierte Anzahl von Anfragen verarbeitet hat. Dadurch wird verhindert, dass Speicherlecks in Erweiterungen oder Drittanbieter-Code den Server-Speicher erschöpfen.
</details>
