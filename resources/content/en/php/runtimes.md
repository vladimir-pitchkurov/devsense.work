---
title: "PHP on the server: FPM, Swoole, workers & event-loop runtimes | DevSense"
description: "Master modern PHP execution environments: Compare PHP-FPM, long-lived application servers (Swoole, RoadRunner, FrankenPHP), and ReactPHP/AMPHP async event loops, while avoiding memory leaks."
faq:
  - question: "What is the difference between PHP-FPM and Swoole?"
    answer: "PHP-FPM spawns a new process or reuses a clean process state for every incoming request, reclaiming memory upon termination. Swoole is a long-lived application server that boots the application code once and handles subsequent requests in memory, greatly increasing performance but persisting global state."
  - question: "What is a memory leak in persistent PHP runtimes?"
    answer: "Since processes do not exit after each request, static properties, singletons, or global variables that accumulate data will grow in size indefinitely, eventually causing the worker process to exceed memory limits and crash."
  - question: "How does RoadRunner handle PHP workers?"
    answer: "RoadRunner is written in Go and acts as a load balancer and process manager. It communicates with persistent PHP worker processes using the Goridge protocol, handling HTTP, gRPC, and queues externally."
  - question: "Why do blocking functions stall ReactPHP or AMPHP event loops?"
    answer: "ReactPHP and AMPHP are single-threaded event loops. If you call a blocking function (like `sleep()` or a synchronous database query), the thread halts completely, freezing the server for all other concurrent connections."
---

# PHP on the Server: FPM, Swoole, Workers & Event Loops

Many developers still think PHP only executes in a single-threaded, short-lived \"share-nothing\" model where variables vanish after a page reload. But modern production PHP hosts high-concurrency WebSockets, handles thousands of requests per second on a single thread using Go-driven workers, and operates asynchronous event loops. If you deploy a modern PHP runtime assuming the classic request/response cycle, a single memory leak in a static property can crash your entire production cluster.

With the emergence of Swoole, RoadRunner, FrankenPHP, and ReactPHP, PHP has broken out of the boundaries of PHP-FPM. However, long-lived runtimes bring new hazards—specifically memory leaks, shared state contamination, and blocking I/O calls that stall the event loop.

> [!IMPORTANT]
> Modern PHP is no longer bound to a single execution model; choosing the right runtime requires understanding the contract between short-lived requests, persistent workers, and asynchronous event loops.

---

## Table of Contents
* [PHP-FPM (FastCGI Process Manager)](#php-fpm)
* [Long-Lived Application Servers (Swoole & Laravel Octane)](#swoole)
* [RoadRunner (Go-Powered Supervisor)](#roadrunner)
* [FrankenPHP (Caddy-Based Worker Mode)](#frankenphp)
* [Event-Loop Stacks (ReactPHP & AMPHP)](#event-loop)
* [Memory Leaks: The Silent Killer](#memory-leaks)
* [Common Mistakes](#common-mistakes)
* [Practical Recipes](#practical-recipes)
* [🧠 Self-Check Questions](#self-check)

---

<a id="php-fpm"></a>
## PHP-FPM (FastCGI Process Manager)

PHP-FPM is the classic, battle-tested process manager. Nginx terminates HTTP traffic and forwards `.php` requests to FPM workers via a Unix socket or TCP.

```nginx
# /etc/nginx/sites-available/default
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

* **The Contract**: Each worker process handles one request, flushes output, resets variables, and returns to the pool.
* **Why it matters**: Zero risk of memory leak accumulation between different requests. If a script fails, the process is recycled cleanly.
* **Limitations**: High bootstrap overhead (e.g. boot loading Laravel/Symfony from scratch on every single request).

---

<a id="swoole"></a>
## Long-Lived Application Servers (Swoole & Laravel Octane)

Swoole is a C-extension that turns PHP into a high-concurrency network server using coroutines.

```php
// app/Servers/HttpServer.php
$server = new Swoole\Http\Server("127.0.0.1", 9501);
$server->on("request", function ($request, $response) {
    $response->header("Content-Type", "text/plain");
    $response->end("Hello World");
});
$server->start();
```

* **The Contract**: The PHP code is loaded into memory **once** on startup. Subsequent requests are handled by memory-resident workers.
* **Why it matters**: Increases throughput by 10x by eliminating framework bootstrap overhead.
* **Limitations**: Any memory leak inside singletons or static variables will persist and grow until the process crashes.

---

<a id="roadrunner"></a>
## RoadRunner (Go-Powered Supervisor)

RoadRunner is a high-performance PHP application server, process manager, and load balancer written in Go.

```yaml
# .rr.yaml
server:
  command: "php worker.php"
http:
  address: "0.0.0.0:8080"
  pool:
    num_workers: 4
```

* **The Contract**: Go handles incoming HTTP requests, WebSocket handshakes, and gRPC endpoints, and marshals them into persistent PHP processes via the Goridge binary protocol.
* **Why it matters**: Combines Go's concurrency safety and process management with PHP's ease of development.

---

<a id="frankenphp"></a>
## FrankenPHP (Caddy-Based Worker Mode)

FrankenPHP is a modern PHP app server built on top of the Caddy web server. It brings a "worker mode" that keeps your application loaded in memory.

* **Why it matters**: Easy deployment as a single binary. It supports HTTP/1.1, HTTP/2, HTTP/3, and automatic TLS out of the box.

---

<a id="event-loop"></a>
## Event-Loop Stacks (ReactPHP & AMPHP)

Unlike Swoole, which relies on a C-extension, ReactPHP and AMPHP implement pure-PHP event loops using cooperative multitasking.

```php
// app/Servers/ReactServer.php
require __DIR__ . '/vendor/autoload.php';

$loop = React\EventLoop\Loop::get();
$loop->addPeriodicTimer(1.0, function () {
    echo "Tick\n";
});
$loop->run();
```

* **Why it matters**: Perfect for custom I/O-bound microservices, WebSocket handlers, and proxy servers.
* **Limitations**: You cannot run blocking code (like standard PDO database operations or `file_get_contents`). All database drivers and HTTP clients must be non-blocking.

---

<a id="memory-leaks"></a>
## Memory Leaks: The Silent Killer

In persistent runtimes (Swoole, RoadRunner, FrankenPHP worker mode, CLI daemons), memory is not freed automatically after a request. 

```php
// app/Services/DataLeak.php
class DataLeak
{
    private static array $cache = [];

    public function cacheRequest(array $data)
    {
        // ❌ Leaks memory! This array grows indefinitely with every request
        self::$cache[] = $data; 
    }
}
```

> [!NOTE]
> To prevent memory leaks, you must limit in-memory caches using LRU eviction, avoid static properties for request-specific state, and utilize memory monitoring checks like `memory_get_usage(true)`.

---

<a id="common-mistakes"></a>
## ⚠️ Common Mistakes

### 1. Blocking the Event Loop in ReactPHP / AMPHP
Calling a synchronous blocking function stalls the event loop, freezing the entire server for all other users.

```php
// app/Http/Handler.php
// ❌ Dangerous: halts execution for all concurrent requests
$data = file_get_contents("http://external-api.com/data");

// ✅ Correct Approach
// Use async HTTP clients:
$browser = new React\Http\Browser();
$browser->get("http://external-api.com/data")->then(function ($response) {
    // Process response asynchronously
});
```

### 2. Modifying Static Class Properties for User Requests
Storing user session or authentication details in static properties will result in user data leaking to other concurrent requests.

```php
// app/Services/AuthService.php
// ❌ Dangerous: User 2 might see User 1's identity!
class AuthService
{
    public static ?User $currentUser = null;
}
```

---

<a id="practical-recipes"></a>
## Practical Recipes

### Safely Recycling Workers
To combat memory leaks in production, configure your process manager to restart workers after they have handled a specific number of requests or reached a certain memory threshold.

```ini
# /etc/php/8.3/fpm/pool.d/www.conf (PHP-FPM)
; Recycle workers after 500 requests to clear minor leaks
pm.max_requests = 500
```

```yaml
# .rr.yaml (RoadRunner)
http:
  pool:
    # Destroy workers when they exceed 100MB of RAM
    max_jobs: 1000
    allocate_timeout: 60s
    destroy_timeout: 60s
    supervisor:
      watch_interval: 1s
      max_worker_memory: 100 # MB
```

---

## 🧠 Self-Check Questions

1. **True or False?** In PHP-FPM, static variables leak memory across separate HTTP requests.
2. What happens if you execute `sleep(5)` inside a ReactPHP request handler?
3. How does FrankenPHP's worker mode achieve performance gains compared to PHP-FPM?
4. What is the primary function of `pm.max_requests` in PHP-FPM?

<details>
<summary><b>Reveal Answers</b></summary>

1. **False.** In PHP-FPM, the entire process memory is cleared and reset between requests (or the process is recycled), meaning static variables do not persist across requests.
2. It blocks the single-threaded event loop for 5 seconds. During this time, the server cannot accept or respond to any other connections.
3. It boots the PHP application and framework libraries into memory once, bypassing the bootstrap file system loading and compiler phases on subsequent requests.
4. It restarts the PHP-FPM worker process after it has served a configured number of requests, preventing memory leaks in extensions or third-party code from consuming server memory.
</details>
