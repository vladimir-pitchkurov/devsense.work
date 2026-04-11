---
title: "PHP on the server: FPM, Swoole, workers & event-loop runtimes | DevSense"
description: "How PHP executes behind nginx or Apache: PHP-FPM process model, long-lived servers (Swoole, RoadRunner, FrankenPHP), and ReactPHP/AMPHP-style async I/O—trade-offs, recipes, and memory-leak hygiene."
---

# PHP on the server: FPM, Swoole, workers, and event-loop runtimes

Most tutorials still show “drop `index.php` on a server,” but production PHP today is almost always **one process manager + one front web server**. This guide separates four ideas people often mix up:

1. **Classic request/response** — PHP-FPM (or `mod_php`): **one short-lived request** per worker, then teardown or reuse with a clean state contract.
2. **Long-lived application servers** — **Swoole/OpenSwoole**, **RoadRunner**, **FrankenPHP worker mode**: the same PHP worker handles **many** requests; shared memory and static caches become real.
3. **Async I/O libraries** — **ReactPHP**, **AMPHP**, **Revolt** event loop: cooperative multitasking inside PHP; great for I/O-bound glue, dangerous with blocking extensions.
4. **CLI / cron** — `php` binary for scripts, queues, and migrations—not a web model, but the same language with different constraints.

None of these is “the new PHP”; they are **different hosting contracts**. Pick the one that matches your traffic shape, team skills, and tolerance for operational complexity.

## Table of contents

* [PHP-FPM + nginx (or Apache proxy)](#php-fpm)
* [Apache `mod_php` (embedded)](#mod-php)
* [CLI PHP (cron, workers, Artisan)](#cli-php)
* [Swoole / OpenSwoole / Laravel Octane](#swoole)
* [RoadRunner](#roadrunner)
* [FrankenPHP](#frankenphp)
* [Event-loop stacks: ReactPHP, AMPHP, Revolt](#event-loop)
* [Comparison: when to use what](#comparison)
* [Memory leaks: shared checklist](#memory-leaks)

---

<a id="php-fpm"></a>
## PHP-FPM + nginx (or Apache as reverse proxy)

### What happens

1. nginx terminates TLS and serves static files.
2. For `*.php`, nginx forwards the request to **PHP-FPM** over FastCGI (Unix socket or TCP).
3. FPM **picks a worker** from the pool. That worker runs your bootstrap (`public/index.php` in Laravel), sends the response, then returns to the pool (or exits after N requests—see `pm.max_requests`).

Each request **starts from a fresh-ish global state** in the sense that you should not rely on globals surviving across requests (even if Opcache keeps bytecode warm).

### Pros

* **Battle-tested** with Laravel, Symfony, WordPress, etc.
* **Simple mental model**: request in, response out; memory is reclaimed when the worker recycles.
* **Easy horizontal scaling**: more FPM workers + more app servers behind a load balancer.
* **Few surprises** from third-party packages (most assume FPM).

### Cons

* **Per-request bootstrap cost** (mitigated by Opcache, realpath cache, preloading in tuned setups).
* **Concurrency = worker count**, not “infinite”; under load, queuing happens in FPM backlog—tune `pm.*` carefully.
* Not ideal for **millions of long-lived WebSocket connections** on a single box without another layer.

### Recipe (Ubuntu-style)

Install FPM (match your PHP version):

```bash
sudo apt update
sudo apt install php8.5-fpm
sudo systemctl enable --now php8.5-fpm
```

nginx location (minimal pattern):

```nginx
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.5-fpm.sock;
}
```

Tune pool `/etc/php/8.5/fpm/pool.d/www.conf` (adjust for RAM):

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

Reload:

```bash
sudo systemctl reload php8.5-fpm
```

### Memory notes (FPM)

* **`pm.max_requests`**: restarts a worker after N requests—cheap leak insurance for extensions or rare circular references.
* If workers RSS grows without bound, profile **your code** and extensions; FPM only masks small leaks.

---

<a id="mod-php"></a>
## Apache `mod_php` (embedded)

Apache runs PHP **inside its own processes/threads** (`mod_php`). Still mostly **request-scoped**, but process architecture differs from FPM.

### Pros

* Simple on **single-server** setups; shared hosting heritage.

### Cons

* **Ties PHP lifecycle to Apache workers**—tuning and isolation differ from FPM + nginx stacks.
* Less common in modern Laravel deployments (nginx + FPM dominates).

**When to use:** legacy stacks or team preference; otherwise prefer **FPM + nginx** for clearer separation.

---

<a id="cli-php"></a>
## CLI PHP (cron, queues, Artisan)

`php artisan ...`, `php bin/console ...`, queue consumers, schedulers—**no HTTP front door**.

### Pros

* Perfect for **batch**, **queues**, **reindex**, **imports**.

### Cons

* Not a replacement for a web SAPI; different timeouts, no per-request nginx buffer semantics.

### Recipe

```bash
cd /var/www/app
php artisan schedule:work   # dev-style; prod often uses cron -> artisan schedule:run
php artisan queue:work redis --sleep=1 --tries=3
```

**Memory:** long-running queue workers behave like **mini servers**—apply the [long-lived checklist](#memory-leaks).

---

<a id="swoole"></a>
## Swoole / OpenSwoole / Laravel Octane

**Swoole** (and the community fork **OpenSwoole**) embed a **long-running** server: workers stay alive, handle many requests, and can use **coroutines** for concurrent I/O inside PHP.

**Laravel Octane** can drive Swoole/RoadRunner/FrankenPHP—same idea: **boot the framework once**, serve many requests.

### Pros

* **High throughput** for I/O-bound apps when code cooperates.
* **WebSockets**, timers, and async I/O primitives (when using coroutine-friendly APIs).

### Cons

* **Global state persists** across requests—`static`, singletons, and caches can leak between users.
* **Not all Composer packages are safe** (hidden I/O, globals, `$_SESSION` assumptions).
* **Debugging** and **deploy** story is harder: you must **reload workers** after deploy.

### Recipe (illustrative HTTP server)

> Production usually uses Octane or a framework integration; this shows the *shape* of Swoole:

```bash
pecl install swoole   # or distro package php8.5-swoole where available
```

```php
<?php
$http = new Swoole\Http\Server('127.0.0.1', 9501);
$http->on('request', function ($request, $response) {
    $response->header('Content-Type', 'text/plain; charset=utf-8');
    $response->end('ok');
});
$http->start();
```

### Octane (Laravel)

```bash
composer require laravel/octane
php artisan octane:install   # choose swoole/roadrunner/frankenphp
php artisan octane:start
```

### Memory / leaks

* Configure **worker recycle** (Octane/Swoole options—consult current docs for your version).
* Never stash **per-request user data** in static properties.
* After deploy: **graceful restart** workers (systemd, `octane:reload`, etc.).

---

<a id="roadrunner"></a>
## RoadRunner

**RoadRunner** is a **Go** binary that keeps **PHP worker processes** alive; communication uses **goridge** (often paired with **spiral/roadrunner-laravel** or Octane).

### Pros

* Very good **worker supervision** story; Go layer handles HTTP, gRPC, queues, etc.
* Clean separation between **application workers** and edge protocols.

### Cons

* Extra moving part (RR binary + config) in your deploy.
* Same **persistent state** caveats as Swoole.

### Recipe

```bash
curl -sL https://github.com/roadrunner-server/roadrunner/releases | # pick asset for your arch
./rr serve -c .rr.yaml
```

Typical `.rr.yaml` includes `server.command` pointing at `php worker.php` or your Octane worker—follow the scaffold your installer generates.

---

<a id="frankenphp"></a>
## FrankenPHP

**FrankenPHP** is a **Caddy-based** PHP app server with features like **worker mode** (long-lived PHP for many requests) and modern HTTP/3-friendly deployment paths.

### Pros

* **Single binary** ergonomics with Caddy; interesting for **edge** deployments and worker mode.

### Cons

* Newer ecosystem; verify extension compatibility and Laravel/Octane support matrix for your version.

### Recipe (high level)

Use official docs / Octane installer selection. Pattern is **Caddy + frankenphp module + worker script**.

---

<a id="event-loop"></a>
## Event-loop stacks: ReactPHP, AMPHP, Revolt

Libraries like **ReactPHP** or **AMPHP** (built on **Revolt** / `amphp/amp`) implement a **single-threaded event loop** with **non-blocking I/O** when you use their APIs.

### Pros

* Excellent for **I/O-bound** agents: many concurrent sockets, HTTP clients, DNS, timers.
* Useful for **custom protocols**, proxies, chat bridges, integration glue.

### Cons

* **Any blocking call** (`PDO::query` to remote DB with default driver, `sleep()`, `file_get_contents('http://...')`) **stalls the loop** for everyone.
* You must use **async-capable clients** (`amphp/http-client`, ReactPHP adapters) or run blocking work in a **thread pool / child process** (adds complexity).

### Recipe (AMPHP HTTP client sketch)

```bash
composer require amphp/http-client revolt/event-loop
```

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use function Amp\async;

$client = HttpClientBuilder::buildDefault();

$futures = [];
foreach (['https://example.com', 'https://php.net'] as $url) {
    $futures[] = async(fn () => $client->request(new Request($url)));
}

foreach ($futures as $future) {
    $response = $future->await();
    echo $response->getStatus(), "\n";
}
```

### Recipe (ReactPHP loop sketch)

```bash
composer require react/event-loop react/http
```

```php
<?php
require __DIR__ . '/vendor/autoload.php';

$loop = React\EventLoop\Loop::get();
$loop->addPeriodicTimer(1.0, fn () => print "tick\n");
$loop->run();
```

---

<a id="comparison"></a>
## Comparison: when to use what

| Runtime | Best for | Usually avoid when |
|--------|----------|--------------------|
| **PHP-FPM** | Typical Laravel/Symfony HTTP APIs & sites | You need millions of cheap duplex connections on one node without extra layers |
| **Swoole / Octane** | High QPS, websockets, coroutine-friendly code | Team unfamiliar with persistent state; heavy use of blocking libs |
| **RoadRunner** | Supervised workers + multi-protocol edges | You cannot operate another binary in deploy |
| **FrankenPHP** | Caddy-centric deploys, worker mode experiments | You need the most conservative, oldest stack |
| **ReactPHP / AMPHP** | Custom network services, async I/O glue | Classic CRUD app with lots of blocking Symfony/Laravel internals |

---

<a id="memory-leaks"></a>
## Memory leaks: shared checklist (especially long-lived PHP)

1. **Static properties and singletons** — store only **configuration**, never **per-request** data.
2. **Global caches without bounds** — use LRU caps or Redis/Memcached instead of unbounded PHP arrays.
3. **Closures capturing large graphs** — `$use` by reference keeps objects alive until the closure dies.
4. **Timers / event listeners** — always **cancel** periodic timers; remove listeners on teardown.
5. **Database result sets** — fetch in chunks; don’t accumulate huge arrays in memory.
6. **Opcache** is not a leak fix—**recycle workers** (`pm.max_requests`, Octane reload) to mitigate extension-level drift.

**Inspect:**

```bash
# FPM: watch worker RSS while load-testing
ps aux | grep php-fpm
```

**PHP built-in helper (CLI debugging):**

```php
<?php
echo memory_get_usage(true), " bytes\n";
```

---

### Further reading

* [PHP-FPM configuration](https://www.php.net/manual/en/install.fpm.configuration.php)
* [Laravel Octane](https://laravel.com/docs/octane)
* [RoadRunner](https://roadrunner.dev/)
* [FrankenPHP](https://frankenphp.dev/)
* [AMPHP documentation](https://amphp.org/)
* [ReactPHP](https://reactphp.org/)
