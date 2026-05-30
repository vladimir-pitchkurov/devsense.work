---
title: "Message queues compared: Redis, RabbitMQ, Kafka | DevSense"
description: "How to choose a broker for async work: comparing memory queues (Redis), AMQP brokers (RabbitMQ), and commit logs (Kafka) based on ordering, scale, durability, and operational cost."
published: 2026-04-12
faq:
  - question: "What is the primary difference between a message queue (like RabbitMQ) and a log-based broker (like Kafka)?"
    answer: "RabbitMQ uses a 'smart broker, dumb consumer' model, where the broker tracks message states (acks, reads, deletions) and deletes messages immediately after successful consumption. Kafka uses a 'dumb broker, smart consumer' model, where messages are appended to a write-ahead log on disk and persisted for a set retention period. Consumers track their own reading position (offset), allowing them to replay messages independently."
  - question: "Why are message queues preferred over databases for async task queues?"
    answer: "Databases are not designed for queuing patterns. Polling tables creates lock contention, high CPU usage, and table fragmentation (bloat) due to rapid inserts and deletes. Message queues store queue state in memory (or structured sequential disk segments) and support push-based consumer alerts, which provides much higher throughput and lower execution latency."
  - question: "How does Kafka achieve high throughput compared to traditional brokers?"
    answer: "Kafka writes messages sequentially to a disk log (leveraging OS page cache and zero-copy transfer to network sockets), bypassing memory serialization overhead. It partitions logs to parallelize write/read workloads across multiple brokers and batches messages together to reduce network and I/O overhead."
  - question: "When is Redis a good choice for messaging, and what are its limits?"
    answer: "Redis is an excellent, low-latency choice for simple queues (using Lists or Pub/Sub) or structured streams when you already use it for caching. However, it is limited by server RAM size since all active data is stored in memory, and it lacks advanced routing features like exchange routing, dead-lettering, or guaranteed long-term disk durability."
---

# Message queues compared: Redis, RabbitMQ, and Apache Kafka

Deciding how to move work out of the HTTP request path is a standard architectural milestone. But choosing **how** to route that work can lead to tool-driven paralysis. You do not need to run a three-node Kafka cluster to send fifty welcome emails a day, nor should you build a financial ledger on Redis Pub/Sub. The right broker is a balance of **ordering requirements**, **delivery guarantees**, **throughput**, and **how much operations overhead your team is willing to carry**.

**Related guides:** [High-load event ingestion](high-load-event-ingestion) · [Databases under load](database-performance-and-scaling) · [Observability and monitoring](observability-monitoring-laravel)

## Contents

* [Why not use a database table?](#why-not-db)
* [The three models of messaging](#three-models)
* [Redis: simple, in-memory, low-latency](#redis)
* [RabbitMQ: smart broker, flexible routing](#rabbitmq)
* [Apache Kafka: the distributed commit log](#kafka)
* [Feature comparison matrix](#matrix)
* [Delivery guarantees: At-least-once, At-most-once, Exactly-once](#delivery)
* [Ordering, consumer groups, and scale](#ordering)
* [Operational cost: Managed vs Self-hosted](#ops-cost)
* [Common Mistakes](#common-mistakes)
* [Checklist](#checklist)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="why-not-db"></a>
## Why not use a database table?

It is tempting to write `jobs` to PostgreSQL or MySQL, index `status`, and poll it every second.
For low-volume setups, this **can work**. However, as volume grows, the database breaks under queuing workloads:
* **Table bloat** — database engines do not like constant write-then-delete patterns. Dead tuples pile up, vacuuming lags, and index performance degrades.
* **Polling lock contention** — multiple workers running `SELECT ... FOR UPDATE LIMIT 1` write-lock the same index pages, bottlenecking throughput.
* **Push vs. Pull** — databases force you to poll; brokers push messages to waiting TCP connections instantly.

---

<a id="three-models"></a>
## The three models of messaging

1. **Transient Memory Queue (Redis Lists / Pub/Sub)** — Fast, lightweight, data fits in RAM, simple patterns.
2. **Classic Message Queue (RabbitMQ / ActiveMQ)** — Complex routing, queues track consumer state, messages are deleted once acknowledged.
3. **Log-based Event Stream (Kafka / Redpanda)** — Append-only disk log, messages persist after read, consumers track their own positions (offsets).

---

<a id="redis"></a>
## Redis: simple, in-memory, low-latency

Redis is an in-memory data store that supports queuing primitives.

### Mechanisms
* **Lists (`LPUSH` / `BRPOP`)** — A basic FIFO queue. Simple, low latency (microseconds), but lacks advanced routing.
* **Streams (Redis 5.0+)** — Appends entries to a log, supports consumer groups and acknowledgement (`XACK`).
* **Pub/Sub** — Fire-and-forget broadcasting. If no consumers are connected when a message is published, the message is **lost**.

### Strengths
* Zero extra infrastructure if you already use Redis for cache or session storage.
* Extremely low latency.

### Weaknesses
* **RAM limits** — If workers slow down and the queue grows, you can exhaust server memory.
* **Durability trade-offs** — AOF/RDB persistence writes asynchronously; a sudden crash can lose recent messages.

---

<a id="rabbitmq"></a>
## RabbitMQ: smart broker, flexible routing

RabbitMQ is an AMQP (Advanced Message Queuing Protocol) broker built on Erlang.

### Core concepts
* **Producers** publish messages to **Exchanges**.
* **Exchanges** route messages to **Queues** using **Bindings** (rules based on routing keys, headers, or fanout).
* **Consumers** pull from **Queues**.

```
Producer ──> [ Exchange ] ──(Binding Rules)──> [ Queue ] ──> Consumer
```

### Strengths
* Rich routing patterns (e.g., topic match, header routing).
* Acknowledge/Negative-Acknowledge semantics per message.
* Dead Letter Exchanges (DLX) for failed retries out of the box.

### Weaknesses
* **Erlang runtime** adds operational overhead for configuration and cluster management.
* Queue performance degrades if queues grow to millions of messages and spill to disk.

---

<a id="kafka"></a>
## Apache Kafka: the distributed commit log

Kafka is not a traditional message queue. It is a distributed, partitioned, append-only transaction log.

### Core concepts
* **Topics** are split into **Partitions**.
* Messages are appended sequentially on disk.
* A message is identified by its **Offset** (index position).
* Consumers join **Consumer Groups**; Kafka assigns partitions to group members.

```
Topic: Orders
Partition 0: [Msg 0][Msg 1][Msg 2][Msg 3]  <-- Consumer A (Offset 3)
Partition 1: [Msg 0][Msg 1]                <-- Consumer B (Offset 1)
```

### Strengths
* **High throughput** — Writes are sequential to disk; reads leverage OS page cache and zero-copy network transfer.
* **Message Replay** — Because messages persist on disk for a set retention window, you can rewind offsets and replay history.
* **Scalability** — Partitioning allows horizontal scaling across multiple servers (brokers).

### Weaknesses
* High complexity. Requires Apache ZooKeeper or KRaft for coordination.
* High latency compared to Redis (milliseconds vs microseconds).
* Overkill for simple task processing.

---

<a id="matrix"></a>
## Feature comparison matrix

| Feature | Redis (Lists) | RabbitMQ | Apache Kafka |
|---------|---------------|----------|--------------|
| **Primary Model** | In-memory List / Stream | Smart Broker (AMQP) | Distributed Commit Log |
| **Persistence** | Volatile / Optional Disk | Disk/Memory (configurable) | Always Disk (Commit Log) |
| **Max Throughput**| High (limited by single-core CPU/RAM) | Moderate (tens of thousands/sec) | Extreme (millions/sec via partitioning) |
| **Message Lifetime** | Deleted on pop | Deleted on Ack | Retained by time/size policy |
| **Routing Flexibility** | None (FIFO) | High (Exchanges & Bindings) | Key-to-partition mapping |
| **Order Guarantees** | Strict FIFO | Strict per-queue (single consumer) | Strict **within a partition** |

---

<a id="delivery"></a>
## Delivery guarantees

No messaging system can guarantee "Exactly-once" delivery across network boundaries without distributed transaction coordination (which degrades performance).

* **At-most-once** — Messages are sent without confirmation. If a network blip or crash occurs, the message is lost.
* **At-least-once** — Consumers must acknowledge processing. If a crash happens mid-process, the broker delivers the message again. **Your application logic must be idempotent** to handle duplicates.
* **Exactly-once** — Requires end-to-end coordination (like Kafka transactions). Often simulated by combining "At-least-once" delivery with deduplication at the destination.

> [!NOTE]
> **Idempotency Rule**
> Always design your consumers to handle duplicates. Use unique transaction IDs or business keys to guard against processing the same event twice.

---

<a id="ordering"></a>
## Ordering, consumer groups, and scale

* **RabbitMQ** guarantees order within a single queue. If you scale to multiple parallel consumers, they process messages at different speeds, which can result in out-of-order execution at the application level.
* **Kafka** guarantees order **only within a partition**. To maintain order for a resource (e.g., updates to Order #105), you must route all its events to the same partition using a partition key (e.g., `order_id`).

---

<a id="ops-cost"></a>
## Operational cost: Managed vs Self-hosted

* **Redis** is easy to run and manage. Almost every cloud provider offers a managed Redis service.
* **RabbitMQ** requires active monitoring of queue memory usage, disc space, and Erlang cluster sync. Managed options (like CloudAMQP or AWS Amazon MQ) reduce this burden.
* **Kafka** is the most complex to operate. Managing partition rebalances, broker configuration, disk retention, and KRaft consensus is a full-time operations role. Use managed platforms (like Confluent Cloud, AWS MSK, or Aiven) unless you have a dedicated platform team.

---

<a id="common-mistakes"></a>
## Common Mistakes

1. **Publishing Events Inside DB Transactions**: Enqueuing a message before committing the database transaction. If the consumer runs faster than the DB commit, it will look for records that do not exist yet.
2. **Missing Prefetch Limits in RabbitMQ**: Leaving the default prefetch limit unset. RabbitMQ will push all queue messages to the first available consumer, overloading it while other workers sit idle.
3. **Using Kafka without Partition Keys**: Publishing events to Kafka without specifying a routing key, which routes messages randomly and breaks ordering guarantees for related records.
4. **Treating Pub/Sub as a Persistent Queue**: Using Redis Pub/Sub for background tasks, assuming messages are buffered when consumers are offline.

---

<a id="checklist"></a>
## Checklist

1. **Verify your volume:** Under 10k messages/sec? Skip Kafka; start with Redis or RabbitMQ.
2. **Define durability:** Can you afford to lose a message on server crash? If not, avoid pure in-memory Redis Lists.
3. **Check routing requirements:** Do you need complex routing patterns (e.g., topic routing)? Choose RabbitMQ.
4. **Establish ordering boundaries:** Do you need strict order per entity across parallel workers? Choose Kafka with partition keys.
5. **Acknowledge the ops cost:** Do you have the team bandwidth to maintain KRaft/Zookeeper? If not, choose a managed service.

---

## Summary

The right tool matches the shape of your data. Use **Redis** for quick task queues, **RabbitMQ** when routing logic is complex, and **Kafka** when you need a high-throughput commit log.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: What happens to a message in Redis Pub/Sub if no active subscribers are connected at the moment it is published?
- A) It is queued in memory until a subscriber connects.
- B) It is dropped and lost permanently.
- C) It is written to the RDB snapshot file.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Redis Pub/Sub is a fire-and-forget broadcasting mechanism. It does not buffer messages for disconnected clients; they are lost immediately if no subscribers are active.
</details>


### Question 2: In Apache Kafka, how do you ensure that all status updates for a specific user are processed in the exact order they occurred?
- A) Run only a single broker.
- B) Use the `user_id` as the partition key so all events for that user land in the same partition.
- C) Set log retention to infinity.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Kafka guarantees message order only within a single partition. By using the `user_id` as the partition key, Kafka routes all events for that user to the same partition, preserving execution order.
</details>
