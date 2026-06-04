---
title: "Designing high-load event ingestion systems | DevSense"
description: "How to handle thousands of incoming HTTP events per second: edge validation, buffering layers, batch writing to storage, and avoiding database connection starvation under spike load."
published: 2026-04-11
faq:
  - question: "Why should you avoid synchronous database writes inside the ingestion endpoint?"
    answer: "Relational databases are designed for ACID-compliant transactional consistency and perform poorly under high-concurrency, short-lived write bursts. Synchronous database writes create table locks, connection exhaustion, and disk I/O bottlenecks. Offloading writes to a fast, sequential queue or log-based broker (like Kafka or Redis Streams) decouples the ingestion speed from database storage limits."
  - question: "What is the benefit of using an API Gateway with rate limiting at the edge of ingestion?"
    answer: "An API Gateway blocks invalid or abusive requests (DDoS, malformed JSON, auth failures) before they hit the application layer. This protects internal servers and downstream messaging brokers from resource starvation and maintains service availability during load spikes."
  - question: "How does client-side batching help event ingestion systems?"
    answer: "Client-side batching groups multiple small event records into a single HTTP request payload. This dramatically reduces network handshake overhead, HTTP serialization costs, and connection counts on the ingestion servers, allowing the system to ingest millions of events with a fraction of the raw requests."
  - question: "What is backpressure, and why is it important in ingestion architectures?"
    answer: "Backpressure is a mechanism where downstream consumers signal upstream ingestion services to slow down or buffer incoming traffic when the processing speed cannot keep up with the ingestion rate. Without backpressure, downstream services (like worker nodes or databases) get overloaded, run out of memory, or crash."
---

# Designing high-load event ingestion systems: buffers, batching, and bounds

Building an endpoint that accepts a web webhook or tracker event is simple. Building one that remains fast, cost-effective, and available when ten thousand mobile apps send analytic payloads **at the same second** is a different problem. Under load, a naive “receive, validate, write to SQL” path collapses: database connections exhaust, disk I/O spikes, and web workers queue up until the load balancer cuts traffic. Robust event ingestion is about **accepting bytes quickly**, **buffering them immediately**, and **writing them in batches** at a speed the storage layer actually enjoys.

**Related guides:** [Message queues compared](message-queues-compared) · [Databases under load](database-performance-and-scaling) · [Observability and monitoring](observability-monitoring-laravel)

## Contents

* [The anatomy of the bottleneck](#bottleneck)
* [Architecture: Decouple receive from write](#architecture)
* [The ingestion endpoint: lightweight and stateless](#endpoint)
* [Edge routing and API gateway validation](#edge)
* [Buffering tier: Redis Streams, Kafka, or disk logs](#buffering)
* [Batch processing and workers](#batching)
* [Handling spikes: backpressure and shed](#spikes)
* [Common Mistakes](#common-mistakes)
* [Checklist](#checklist)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="bottleneck"></a>
## The anatomy of the bottleneck

When high-frequency events hit a standard stack:

* **HTTP Overhead** — Negotiating TLS, parsing headers, and boots/framework initialization per request consumes CPU.
* **Synchronous Storage Calls** — If the endpoint waits for `INSERT INTO ...` to finish, the client connection stays open. This pins memory and FPM child processes.
* **Disk I/O and Lock contention** — Each individual write forces the database to write to its write-ahead log (WAL) and sync to disk. A hundred parallel writes trigger a hundred disk writes; a batch of a thousand triggers one.

---

<a id="architecture"></a>
## Architecture: Decouple receive from write

The core design principle is **asynchrony**:

```
[ Client ] ──(HTTP POST)──> [ Ingestion Gateway ] 
                                   │
                           (Push to Buffer)
                                   ▼
                            [ Buffer Tier ] (Redis/Kafka)
                                   ▲
                             (Batch Read)
                                   │
                            [ Worker Pool ]
                                   │
                             (Bulk Write)
                                   ▼
                           [ Storage Layer ] (ClickHouse/DB)
```

1. **Ingestion Gateway** receives the request, performs schema validation, pushes it to the buffer, and returns a `202 Accepted` immediately.
2. **Buffer Tier** (durable memory queue or commit log) holds the raw events.
3. **Worker Pool** reads events from the buffer in batches and writes them to storage.

---

<a id="endpoint"></a>
## The ingestion endpoint: lightweight and stateless

The code handling the incoming request must do the bare minimum:

```php
// app/Http/Controllers/IngestController.php
public function __invoke(IngestRequest $request)
{
    // 1. Lightweight validation (schema match only)
    $payload = $request->validated();

    // 2. Push to buffer (e.g. Redis Stream or Kafka)
    $this->buffer->push('events', [
        'event_id' => Str::uuid()->toString(),
        'received_at' => now()->getTimestamp(),
        'data' => json_encode($payload),
    ]);

    // 3. Return immediate acknowledgment
    return response()->json(['status' => 'accepted'], 202);
}
```

Keep FPM footprints small: avoid calling external APIs, executing complex database queries, or doing CPU-heavy image processing on this request path.

---

<a id="edge"></a>
## Edge routing and API gateway validation

Filter bad requests before they touch your application workers:
* **API Gateway (Nginx, Kong, AWS API Gateway)** — Validate API keys, enforce rate limits, and block malformed payloads.
* **Payload validation** — Use JSON Schema validation at the gateway level if possible, reducing application parsing overhead.
* **Redirection and CDN** — For static tracking pixels (GET routes), return the pixel image from the CDN edge, sending log dumps asynchronously to storage.

---

<a id="buffering"></a>
## Buffering tier: Redis Streams, Kafka, or disk logs

Choose your buffer based on data guarantees and volume:

| Buffer | Max Throughput | Operational Complexity | Note |
|--------|----------------|------------------------|------|
| **Redis Streams** | Very High | Low | Excellent for memory-bound queues. Keep track of memory size. |
| **Apache Kafka** | Extreme | High | Standard for distributed event streams. Durable on disk. |
| **AWS Kinesis / GCP PubSub** | High | Low (Managed) | Pay-per-use, scales automatically, vendor lock-in. |

> [!NOTE]
> **Memory Allocation**
> If your buffer runs in memory (like Redis), monitor memory usage closely. If downstream consumers slow down, the queue will eat up RAM and crash the server.

---

<a id="batching"></a>
## Batch processing and workers

Writing events one-by-one is the most common database performance killer. Workers should read in batches:

```php
// app/Console/Commands/ProcessBufferBatch.php
public function handle()
{
    // Read up to 1000 events from the stream
    $events = $this->buffer->readBatch('events', 1000);

    if (empty($events)) {
        return;
    }

    // Transform and write in a single bulk query
    $this->storage->bulkInsert(
        $this->transform($events)
    );

    // Acknowledge processed offsets
    $this->buffer->acknowledge('events', collect($events)->pluck('id'));
}
```

For large analytics workloads, consider column-oriented databases like **ClickHouse**, **Snowflake**, or **AWS Redshift**, which are designed for high-speed bulk inserts.

---

<a id="spikes"></a>
## Handling spikes: backpressure and shed

When load spikes past system capacity:
* **Backpressure** — Downstream workers signal the ingestion gateway to throttle incoming requests or queue them at the edge.
* **Load Shedding** — Block low-priority traffic at the gateway, returning `429 Too Many Requests` to preserve core service availability.
* **Circuit Breakers** — If the database or queue broker fails, open the circuit breaker to fail fast rather than hanging connection threads.

---

<a id="common-mistakes"></a>
## Common Mistakes

1. **Synchronous Database Writes**: Writing events directly to the application database inside the HTTP request lifecycle.
2. **Missing Ingestion Rate Limits**: Allowing a single client or buggy mobile app loop to saturate the ingestion endpoint.
3. **Heavy Authentication Checks**: Querying the database to check client status on every fast-path event request. Use cache-backed API tokens instead.
4. **Failing to Monitor Buffer Lag**: Tracking only application health while the event buffer queue grows unchecked behind the scenes.

---

<a id="checklist"></a>
## Checklist

1. **Stateless endpoint:** Does the handler return a response without touching persistent storage?
2. **Buffering:** Is there a queuing layer between ingestion and storage?
3. **Edge validation:** Are invalid schemas and unauthorized calls blocked at the gateway level?
4. **Batch writing:** Do worker threads combine records into bulk inserts?
5. **Backpressure plan:** Does the system shed load or throttle traffic when queues fill up?

---

## Summary

Ingestion at scale is about separating **accepting the event** from **storing the event**. Focus on keeping the front door fast and simple, while using a strong buffer to feed the backend at a steady, manageable pace.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: What is the primary benefit of returning a `202 Accepted` response code in an ingestion API?
- A) It formats the response payload into compressed JSON.
- B) It informs the client the payload was received and queued, letting the HTTP thread close without waiting for database storage.
- C) It guarantees that the event is free from schema errors.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
A `202 Accepted` response indicates that the request has been accepted for processing, but the processing has not been completed. This allows the connection to terminate immediately, keeping request duration and resource usage minimal.
</details>

### Question 2: Why do analytics databases like ClickHouse require batch inserts (e.g., 10k rows at once) instead of individual row inserts?
- A) Individual inserts bypass security checks.
- B) Columnar engines write data in large, compressed physical parts on disk; writing row-by-row creates too many small files, exhausting disk I/O.
- C) Batching prevents memory leaks in PHP workers.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Columnar storage models are optimized for sequential block writes. Writing single rows forces the engine to repeatedly merge small parts on disk, leading to write amplification and disk saturation.
</details>
