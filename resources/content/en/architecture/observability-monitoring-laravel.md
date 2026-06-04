---
title: "Observability: logs, metrics, and health for Laravel and microservices | DevSense"
description: "How to monitor application state across environments and load: structured logging, metrics, traces, correlation IDs across services, and a practical spectrum of tools from classic syslog to Prometheus, Loki, OpenTelemetry, and SaaS APM."
published: 2026-04-15
faq:
  - question: "What is the difference between log level and log context?"
    answer: "Log level (e.g., DEBUG, INFO, WARNING, ERROR) categorizes the severity of a message, allowing filters in production. Log context is structured metadata (arrays containing user IDs, request IDs, execution times) attached to the log entry, enabling automated indexing and correlation without parsing unstructured text."
  - question: "Why are high-cardinality labels bad for metrics systems like Prometheus?"
    answer: "Prometheus stores metrics as time-series database entries. Each unique combination of labels creates a new time series. Storing high-cardinality parameters (like user IDs or full dynamic URLs) as labels results in a 'series explosion', which exhausts Prometheus memory and crashes the metrics server."
  - question: "How does distributed tracing propagate correlation across microservices?"
    answer: "Distributed tracing uses HTTP headers (such as `X-Request-Id` or W3C `traceparent`) to pass a unique trace ID and span ID along the request chain. Every microservice in the request flow extracts this header, includes it in its own logs, and injects it into outbound HTTP client requests or queue message headers."
  - question: "Why should Laravel Telescope be disabled in production?"
    answer: "Laravel Telescope captures detailed request context, database queries, and log records, storing them in the database. In a high-traffic production environment, this query and storage overhead degrades application throughput, increases database load, and can consume excessive storage space."
---

# Observability: logs, metrics, and health in Laravel monoliths and microservices

“Works on my machine” is not a monitoring strategy. Production breaks in **specific ways**: disk fills, queue latency spikes, a dependency times out, or one replica serves stale reads. Good observability lets you answer **what changed**, **for whom**, and **which hop failed**—without SSH-ing into every box. The same ideas apply whether you run a **Laravel monolith** or a **fleet of services**; only the **plumbing** and **cardinality** get harder as you split boundaries.

**Related guides:** [PHP database connection pooling](php-database-connection-pooling) · [Databases under load](database-performance-and-scaling) · [API gateway & messaging](../microservices/api-gateway)

## Contents

* [Three pillars: logs, metrics, traces](#pillars)
* [What differs by environment](#environments)
* [Laravel monolith: practical layers](#monolith)
* [Microservices: correlation and tracing](#microservices)
* [Tool spectrum: classic to modern](#tools)
* [Under load: sampling, cardinality, cost](#load)
* [Alerting that humans will respect](#alerting)
* [Common Mistakes](#common-mistakes)
* [Checklist](#checklist)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="pillars"></a>
## Three pillars: logs, metrics, traces

| Pillar | Answers | Typical mistakes |
|--------|---------|------------------|
| **Logs** | *What narrative happened?* (errors, audit, debug context) | Unstructured one-liners you cannot query; logging secrets; **INFO** floods in prod |
| **Metrics** | *How much, how fast, compared to yesterday?* (rates, histograms, saturation) | **High-cardinality** labels (full URL, user id on every series); charts nobody looks at |
| **Traces** | *Which span in the chain was slow?* | Missing **context propagation** so microservice B has no idea it belongs to request A |

They reinforce each other: a **spike in error rate** (metric) sends you to **representative logs** and a **trace** of a slow checkout. No single pillar replaces the others.

---

<a id="environments"></a>
## What differs by environment

* **Local / dev** — maximize **developer speed**: `tail`, Telescope-style debug UIs, verbose logs, breakpoints. **Do not** ship that verbosity to prod unchanged.
* **Staging / pre-prod** — mirror **prod-like** logging sinks and dashboards where affordable; catch “works until we enable JSON to Loki” class mistakes.
* **Production** — optimize for **signal**, **retention cost**, and **safe defaults**: structured logs, redaction, sampling on debug paths, **health checks** that reflect real dependencies.

> [!NOTE]
> **Compatibility Principle**
> The goal is not identical tooling everywhere—it is **compatible contracts** (same correlation field names, same metric names) so incidents do not require relearning the system.

---

<a id="monolith"></a>
## Laravel monolith: practical layers

### Application logging

* Use **`config/logging.php`** channels: `stack`, `daily`, `syslog`, or a **JSON** formatter to stdout for container hosts to scrape.
* Prefer **structured fields** (`context` arrays) over parsing English sentences later.
* Attach **request id**, **user id** (if policy allows), **queue job id**—whatever helps you pivot from one log line to the rest of the story.

Example of structured context:
```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily'],
        'ignore_exceptions' => false,
    ],
    // ...
],
```

And code utilization:
```php
// app/Http/Controllers/OrderController.php
Log::info('Order processed successfully', [
    'order_id' => $order->id,
    'user_id' => auth()->id(),
    'execution_time_ms' => $timeMs,
]);
```

### Request lifecycle

* Middleware for **correlation id**: accept incoming `X-Request-Id` or generate one; return it in responses; pass it to jobs and HTTP clients.
* Laravel’s **`Log::withContext()`** helps keep context on the request without threading parameters everywhere.

### Queues and schedules

* **Horizon** (Redis) gives **queue depth, throughput, failed jobs**—treat it as **first-class monitoring**, not an optional UI.
* Scheduled tasks: log **start/end/duration**; alert on **missed runs** (cron monitoring or external heartbeat).

### Deep introspection (non-production)

* **Telescope** is invaluable for **local/staging**; keep it **off** in production unless you have hard gates (IP, auth, sampling) and accept the overhead.
* **Laravel Pulse** surfaces **slow queries, exceptions, queues** in a dashboard—still mind **sampling and retention** on busy apps.

### Health and readiness

* **`/up` (Health)** in Laravel 11+: distinguish **liveness** (“process runs”) from **readiness** (“can talk to DB and cache”). Load balancers and Kubernetes probes care about the difference.

### Errors as a product

* **Sentry**, **Flare**, **Bugsnag**—grouped stack traces, release tracking, breadcrumbs. They complement logs; they do not replace **metrics for saturation**.

---

<a id="microservices"></a>
## Microservices: correlation and tracing

When one HTTP call becomes **gateway → service A → service B → broker → worker**, a plain access log per service is **insufficient**.

### Correlation ID

* Propagate a stable id on every outbound call (`X-Request-Id` or **W3C `traceparent`** alongside your internal id).
* Log it in **every** service at entry; include it in **async** payloads (job `payload`, message headers).

### Distributed tracing

* **OpenTelemetry** is the emerging **vendor-neutral** way to emit traces; collectors forward to **Jaeger**, **Tempo**, **Zipkin**, or SaaS backends.
* PHP ecosystems vary in maturity—verify **instrumentation** for your HTTP client, DB driver, and queue library. Partial tracing still beats none.

### Service boundaries

* Standardize **timeout, retry, and idempotency** policies; observability will show **cascading retries** if each layer blindly retries.

---

<a id="tools"></a>
## Tool spectrum: classic to modern

### Host and network era

* **syslog**, **rsyslog**, **logrotate** — centralize plain files; still valid as a **transport** stage.
* **Nagios**, **Icinga**, **Zabbix** — host checks, ping, disk, simple service probes. Less about **app traces**, still common for **infra baselines**.

### Log aggregation

* **ELK / Elastic Stack** (Elasticsearch, Logstash/Beats, Kibana) — powerful search; **operate** or **buy** capacity consciously.
* **Graylog**, **Splunk** (enterprise) — similar problem space.

### Metrics and dashboards

* **Prometheus** scrape model + **Grafana** dashboards — de facto for **Kubernetes** and many bare-metal shops; **Alertmanager** for routing.
* **VictoriaMetrics**, **Mimir**, **Thanos** — long-term or HA variants around Prometheus protocols.

### Logs “like metrics”

* **Grafana Loki** — label-based log storage that pairs naturally with Grafana; often cheaper than indexing every field like search engines.

### Cloud-native

* **AWS CloudWatch**, **Google Cloud Logging/Monitoring**, **Azure Monitor** — tight integration if you already live on those bills.

### SaaS all-in-one

* **Datadog**, **New Relic**, **Honeycomb** — logs, metrics, APM, RUM; **fast to value**, **priced by volume**—watch cardinality.

### Errors and APM for PHP

* **Sentry** (errors + performance), **Scout**, **Tideways** (PHP-focused profiling) — strong Laravel community usage.

### Standardization wave

* **OpenTelemetry (OTel)** — unified SDKs/exporters; **collector** can fan out to many backends. **Adoption is growing** precisely to avoid vendor lock-in per signal.

---

<a id="load"></a>
## Under load: sampling, cardinality, cost

* **Log volume** grows linearly with traffic; **JSON per request** at `debug` can dwarf app CPU. Use **levels** and **sampled debug** for hot paths.
* **Prometheus labels**: never use **unbounded** values (raw URLs with ids, emails) as label names or high-cardinality values—**metrics explode**.
* **Trace sampling**: keep **100%** for errors or slow requests; sample the rest—backends and wallets will thank you.
* **Retention**: define **hot** (days) vs **cold** (object storage) vs **delete**; compliance may mandate longer **audit** retention separately from **debug** logs.

---

<a id="alerting"></a>
## Alerting that humans will respect

Alert on **user-visible** or **imminent** failure: **SLO burn**, **error rate** jump, **queue wait** p95, **disk** threshold, **certificate** expiry.

Avoid paging for **known noisy** conditions unless you attach **runbooks**. “CPU > 80%” for five minutes is often **not** an incident; “**payment success rate** dropped 10x” is.

---

<a id="common-mistakes"></a>
## Common Mistakes

1. **High-Cardinality Metric Labels**: Adding unique identifiers like `user_id` or dynamically generated `url` parameters as Prometheus labels, which leads to memory exhaustion.
2. **Leaking Credentials in Logs**: Logging `$request->all()` on login or password reset endpoints, exposing passwords, tokens, and PII.
3. **Leaving Telescope Enabled in Production**: Unrestricted database storage of application spans, slowing down queries and overloading primary storage.
4. **Missing Timeout Settings on External Requests**: Calling third-party APIs without a timeout, causing HTTP processes to hang and deplete FPM child processes.

---

<a id="checklist"></a>
## Checklist

1. **Structured logs** to stdout or a shipper; **one** correlation id across sync and async work.
2. **Golden signals** per service: latency, traffic, errors, saturation (plus **queue depth** for Laravel workers).
3. **Health** endpoints that test **real** dependencies; separate **liveness** vs **readiness** where orchestrators need it.
4. **Error tracker** in prod; **Telescope-like** tools gated to non-prod.
5. **Cost and cardinality** review before enabling “log everything” or “label everything.”

---

## Summary

Observability is **part of the product**: the same Laravel codebase that serves users should tell you—**with evidence**—when it is about to fail and **where** to look first.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: What is the main issue with adding user emails as labels in a Prometheus metrics database?
- A) Prometheus does not support string values.
- B) It causes a metrics cardinality explosion, crashing the metric database.
- C) It violates standard URL formatting policies.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Each unique email label creates a new time series. If you have thousands of users, Prometheus will quickly run out of memory due to cardinality explosion.
</details>

### Question 2: In a Kubernetes or load-balanced environment, what is the purpose of the readiness check?
- A) To verify if the server process has started.
- B) To verify if the application is ready to accept traffic (e.g., database connection is live).
- C) To trace slow queries.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
A readiness probe checks if the container is ready to handle incoming web traffic. If it fails, the balancer stops forwarding requests to that node.
</details>
