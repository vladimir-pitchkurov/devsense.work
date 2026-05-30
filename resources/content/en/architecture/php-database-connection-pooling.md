---
title: "PHP apps and the database connection pool bottleneck | DevSense"
description: "Why PHP-FPM and workers multiply database sessions, how middle-tier poolers and proxies share real server connections, and what Laravel teams should know about PgBouncer modes, ProxySQL, and prepared statements."
published: 2026-04-14
faq:
  - question: "What is the main difference between Session pooling and Transaction pooling in PgBouncer?"
    answer: "Session pooling assigns a server connection to a client for the entire duration of its connection, releasing it only when the client disconnects. Transaction pooling releases the server connection back to the pool immediately after each transaction (`COMMIT` or `ROLLBACK`). Transaction pooling allows much higher client-to-server ratios but breaks session-level state like temporary tables, advisory locks, and persistent settings."
  - question: "Why do prepared statements sometimes fail when using transaction pooling?"
    answer: "Under transaction pooling, sequential queries in the same client session might be routed to different database server backends. If client query A prepares a statement on backend 1, and query B attempts to execute that statement on backend 2, the statement execution will fail because backend 2 has no knowledge of it."
  - question: "How does PHP-FPM's process model impact database connections compared to Node.js or Go?"
    answer: "PHP-FPM runs a process-per-request model, where each child process handles one request at a time and typically closes resources at the end of the request. In high-traffic systems, this leads to a 'connection storm' (repeated TCP handshakes and authentication). Conversely, Node.js and Go use asynchronous single-process runtimes that keep a single long-lived pool of database connections shared across thousands of concurrent requests."
  - question: "Does connection pooling fix slow database queries?"
    answer: "No. Connection pooling only solves the overhead of establishing connections and prevents exceeding connection limits on the database server. It does not speed up slow SQL execution, resolve missing indexes, or reduce CPU/disk load caused by unoptimized queries."
---

# PHP apps and the database connection bottleneck: poolers, proxies, and reality

In many stacks the database is fast enough and the queries are reasonable—yet production still trips **`too many connections`**, **`remaining connection slots are reserved`**, or **mysterious stalls** right after a deploy. The culprit is often not slow SQL but **connection arithmetic**: PHP’s request model creates **bursts of connect + auth + TLS**, and the database has a **hard ceiling** on concurrent backends. Middle-tier **poolers** and **managed proxies** exist precisely to put a **small, stable set of server-side sessions** behind a **large flock of short-lived PHP clients**.

**Related guides:** [Databases under load: queries & scaling](database-performance-and-scaling) · [Observability and monitoring](observability-monitoring-laravel)

## Contents

* [Why PHP amplifies the problem](#why-php)
* [What you are actually limited by](#limits)
* [Middle-tier poolers and proxies](#middle-tier)
* [PostgreSQL: PgBouncer in practice](#pgbouncer)
* [MySQL and MariaDB: ProxySQL and friends](#proxysql)
* [Managed proxies (RDS Proxy, others)](#managed)
* [Other poolers: PgCat, Odyssey, pgpool-II](#other-tools)
* [Laravel-specific notes](#laravel)
* [What poolers do *not* fix](#not-a-cure)
* [Common Mistakes](#common-mistakes)
* [Checklist](#checklist)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="why-php"></a>
## Why PHP amplifies the problem

Classic **PHP-FPM** runs a request, talks to services, returns a response, and tears request-scoped resources down. Unless you use **persistent connections** (and accept their trade-offs), each request that touches the database typically **opens or checks out** a TCP session, **authenticates**, optionally negotiates **TLS**, then runs queries.

Under load:

* **`pm.max_children`** on FPM defines how many PHP processes can run **at the same time** on that box. If most requests hit the DB, you can need **up to that many** concurrent DB sessions **per worker machine**.
* **Queue workers** (`queue:work`, Horizon) are **long-lived processes**—each concurrent worker often holds **one or more** open connections while jobs run.
* **Horizontal scaling** multiplies everything: three app nodes with eighty children each is **two hundred forty** potential sessions before you count workers, schedulers, and one-off CLI tasks.

The database sees **connection storms** on deploys and traffic spikes: hundreds of handshakes in seconds. Even when `max_connections` is high enough, **memory per backend** (especially Postgres) and **CPU for auth** become the real limit.

---

<a id="limits"></a>
## What you are actually limited by

* **`max_connections` (Postgres)** / **`max_connections` (MySQL)** — a global cap. Reserved slots for superusers and replication can shrink what applications get.
* **Memory** — each server backend carries buffers and state; “just raise the limit” can **OOM** the instance.
* **Latency of connect** — TLS + password verification + optional LDAP adds **milliseconds to tens of milliseconds** per request if you connect every time.
* **Thundering herd** — after restart, every PHP process may try to connect **at once**, saturating the accept queue or auth path.

> [!NOTE]
> **Total Load Arithmetic**
> Rule of thumb: count **all** programs that speak SQL (web, workers, cron, admin tools, BI), not only HTTP. Each environment adds to the total database footprint.

---

<a id="middle-tier"></a>
## Middle-tier poolers and proxies

A **pooler** sits **between** PHP and the database. PHP opens a cheap connection **to the pooler**; the pooler keeps a **smaller pool** of real connections to Postgres/MySQL and **reuses** them across many clients.

### Benefits
* Fewer **server backends** and less **RAM** on the database host.
* **Multiplexing**: many idle PHP clients do not each pin an idle server session.
* Smoother behavior under **spiky** traffic.

### Costs and caveats
* Another **hop** (latency, failure domain, configuration to secure and monitor).
* **Session semantics** change depending on pooling **mode**—see PgBouncer below.
* You must still size the pooler so it does not become the **new** bottleneck (CPU, file descriptors, pool starvation).

---

<a id="pgbouncer"></a>
## PostgreSQL: PgBouncer in practice

**PgBouncer** is the de facto standard for Postgres pooling in PHP stacks.

Common **pool modes**:

| Mode | Behavior | PHP / Laravel fit |
|------|----------|-------------------|
| **Session** | One server connection for the whole client session until disconnect | Safest compatibility: `SET`, `LISTEN`, advisory locks, temp tables, prepared statements work. **Least multiplexing gain** if clients stay connected long (workers) or you open per request anyway. |
| **Transaction** | Server connection returned to pool **after each transaction** (COMMIT/ROLLBACK) | **Strong multiplexing** for short web requests. Breaks **session-scoped** features: `SET LOCAL` across multiple round-trips without a transaction, `LISTEN`, long-lived temp tables, some **prepared statement** patterns unless configured carefully. |
| **Statement** | Server connection released after **each statement** | Rare for ORMs; breaks multi-statement transactions. Not a typical Laravel target. |

### Prepared statements and transaction pooling

Many drivers prepare statements **by name** on the session. When the physical server connection changes under you, **named prepares** can break. Mitigations used in production:

* Prefer **unnamed** prepares / **simple query** protocol for that hop, or
* **Disable** server-side prepares for the pooler connection (driver-specific; often `PDO::ATTR_EMULATE_PREPARES` or framework options).

```php
// config/database.php
'connections' => [
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
        // ...
        'options' => [
            PDO::ATTR_EMULATE_PREPARES => true, // Emulates prepared statements locally in PHP
        ],
    ],
],
```

---

<a id="proxysql"></a>
## MySQL and MariaDB: ProxySQL and friends

**ProxySQL** is a popular **MySQL protocol** middle tier: routing, query rules, read/write split, and **connection pooling** with multiplexing rules tuned per user/schema.

Teams use it to:
* Cap **backend connections** while many PHP-FPM children connect to ProxySQL.
* Route **reads** to replicas with explicit rules (still watch **replication lag**).
* Shed or rewrite certain query patterns (with care—logic in the proxy is still **ops complexity**).

**MySQL Router** (InnoDB Cluster) and some **cloud load balancers** expose pooling-like behavior but **check docs**: not every layer multiplexes the same way ProxySQL does.

**MariaDB MaxScale** can act as a router with connection management features depending on edition and modules—verify **licensing** and capabilities for your deployment.

---

<a id="managed"></a>
## Managed proxies (RDS Proxy, others)

Cloud vendors offer **managed connection proxies** in front of RDS, Aurora, Cloud SQL, etc. They typically handle:
* **Pooling** and **IAM or token auth** integration.
* **Failover** friendliness (reconnecting backends without reconnecting every PHP process at once).

They still obey database **semantics**: if the product multiplexes aggressively, you face the same **prepared statement** and **session state** constraints as self-hosted PgBouncer—read the **service matrix** for your engine and driver.

---

<a id="other-tools"></a>
## Other poolers: PgCat, Odyssey, pgpool-II

* **PgCat** and **Odyssey** — Postgres poolers with a growing following; compare **pool modes**, metrics, and driver quirks against PgBouncer before you switch.
* **pgpool-II** — often deployed for **replication** and routing as much as pooling; **operationally heavier** than PgBouncer if you only need multiplexing.

---

<a id="laravel"></a>
## Laravel-specific notes

* **`config/database.php`** — `connections.*.options` and driver flags are where you align **PDO** behavior with your pooler (e.g. emulate prepares when required).
* **Read/write splitting** — Laravel can send selects to `read` hosts; combined with a pooler, ensure **sticky** semantics match your expectations (replica lag vs `sticky` config).
* **Octane / Swoole / FrankenPHP** — **long-lived** workers change the calculus: persistent connections can **work well** but you must avoid **leaking** connection state between requests and watch **idle timeout** on the server and pooler.
* **Horizon / `queue:work`** — concurrency × workers adds **sustained** connections; pool **per worker** or use **transaction mode** with compatible settings.
* **Telescope, Nightwatch, debug bars** in prod can hold transactions open longer than you think—tighten **in non-prod only**.

Example environment configuration using a pooler:
```env
# .env
# PHP connects to PgBouncer on 6432; PgBouncer connects to Postgres on 5432
DB_HOST=pgbouncer.internal
DB_PORT=6432
DB_DATABASE=app
DB_USERNAME=app_rw
```

---

<a id="not-a-cure"></a>
## What poolers do *not* fix

* **N+1 queries** and missing indexes still burn **CPU and I/O** on the server—pooling only caps **how many sessions** express that load.
* **Long transactions** hold server backends from the pool—your **transaction mode** gains vanish if code keeps transactions open across external HTTP calls.
* **Global locks** and **migrations**—running `migrate` through a saturated pooler can interact badly with **locks**; some teams use a **direct** admin path for DDL.

---

<a id="common-mistakes"></a>
## Common Mistakes

1. **Transaction Pooling with Session Variables**: Setting session-specific configurations (like `SET TIMEZONE` or using temporary tables) inside a PgBouncer transaction-pooled environment, resulting in leaked settings across different client sessions.
2. **Forgetting to Emulate Prepares**: Failing to set `PDO::ATTR_EMULATE_PREPARES => true` when using transaction pooling, which throws "prepared statement already exists" or "prepared statement not found" exceptions.
3. **Scaling pooler limits past Database boundaries**: Setting PgBouncer's `max_client_conn` and backend pool size larger than the PostgreSQL physical `max_connections` value.
4. **Incorrect Persistent Connections with FPM**: Enabling `PDO::ATTR_PERSISTENT` on web servers without managing FPM child lifespan, leaving idle connections open forever.

---

<a id="checklist"></a>
## Checklist

1. **Inventory** every process type that opens SQL (FPM max children × nodes, Horizon workers, cron, CLI).
2. Compare totals to **`max_connections`** and **RAM per connection** on the DB—decide **pooler vs more app discipline** first.
3. Pick **pool mode** (Postgres) or **multiplexing rules** (MySQL) that match **ORM + driver** capabilities.
4. Validate **prepared statements** and **session features** (`SET`, temp tables, advisory locks) under load tests.
5. Monitor **pooler wait time** and **server active connections**—if the pooler queue grows, the database or query mix is still the limit.

---

## Summary

Middle-tier poolers are **infrastructure you operate** (or buy). Used well, they turn “PHP opened eight hundred connections” into “Postgres sees sixty busy backends”—which is exactly the shape most OLTP databases were designed to reason about.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: What happens if you try to use PostgreSQL advisory locks through PgBouncer running in transaction pooling mode?
- A) The locks work correctly because PgBouncer intercepts them.
- B) The locks might lock the wrong session or be silently lost when the connection changes backends.
- C) An immediate SQL exception is thrown by the PgBouncer parser.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Advisory locks are tied to the physical backend session. In transaction mode, your next query may be routed to a different physical connection, meaning the lock is lost on your side while remaining locked on the original backend.
</details>

### Question 2: Why does PHP-FPM create connection storms compared to persistent worker runtimes like Go or Node.js?
- A) PHP-FPM processes do not support TCP.
- B) PHP-FPM terminates request state at the end of execution, which closes and reopens database handles repeatedly.
- C) Node.js and Go use custom database engines.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Because PHP-FPM is request-scoped, connections are negotiated and torn down on each request unless persistent handles are carefully configured.
</details>
