---
title: "Ingesting millions of events without crushing your primary database | DevSense"
description: "Event spikes from ads, games, and panels: why buffering beats direct OLTP writes, how Redis Streams compares to lists, when RabbitMQ or Kafka fits, and how to keep analytics off the money path."
published: 2026-04-12
---

# Ingesting millions of events without crushing your primary database

Traffic spikes are quiet until they are not. A partner turns on a campaign, a promo goes viral, and suddenly your API is recording **impressions, clicks, spins, and bets** faster than a spreadsheet-minded design can absorb. The tempting shortcut is to **INSERT every signal straight into the same Postgres instance** that already guards wallets, ledgers, and sessions. That works until latency on the **money path** starts to wobble and nightly reports begin to **fight** checkout-sized transactions for the same buffers and WAL. The patterns below are what teams reach for when “just add an index” stops helping.

**Related:** [API gateway & messaging patterns](../microservices/api-gateway) · [Sail: queues & RabbitMQ](../tools/sail-queues)

## Contents

* [Mixing telemetry with transactional cores](#why-mixing-hurts)
* [An ingestion path that survives bursts](#ingestion-path)
* [Redis lists versus streams as shock absorbers](#redis-absorbers)
* [RabbitMQ and Kafka: queue brain versus log brain](#queue-vs-log)
* [Give analytics its own lane](#analytics-lane)
* [Questions to answer before you commit](#decision-questions)

---

<a id="why-mixing-hurts"></a>
## Mixing telemetry with transactional cores

**OLTP** systems shine at **small, consistent units of work**: debit an account, flip a state flag, enforce a uniqueness rule. Their indexes and autovacuum schedules assume that shape.

High-volume **event telemetry** behaves differently:

* volume is **high and bursty**, not smoothly spread;
* many rows are **append-only facts** (“this happened at T”) rather than updates to one canonical row;
* downstream teams want **range scans, funnels, and joins to dimensions**—workloads that look nothing like “fetch user by id in under two milliseconds.”

When both worlds share one hot database, you usually see **tail latency creep** on financial operations, **bloated indexes**, **replication lag**, and operators juggling **timeouts** that should never have been on the critical path. The reporting UI looks like the victim, but the real casualty is **anything that charges a card or locks a balance**.

---

<a id="ingestion-path"></a>
## An ingestion path that survives bursts

The recurring design is: **decouple the browser or device from the final analytical landing zone**. Accept the event, acknowledge quickly, then let asynchronous machinery finish the journey.

Useful building blocks:

1. **Edge validation** — schema checks, enrichment (tenant, campaign, device class), and, where duplicates are possible, an **idempotency key** so retries do not double-count money-adjacent metrics.
2. **A durable shock layer** — not “RAM in the PHP process unless you enjoy losing the burst on deploy.” Prefer **Redis with persistence you trust**, a **broker**, or another **append-friendly store** whose loss profile you have written down.
3. **Batch writers** — consumers that **flush in chunks** cut round-trips and reduce fsync pressure compared with single-row inserts.
4. **Back-pressure** — if consumers fall behind, **bounded queues** beat unbounded memory. Signal upstream (slow down, shed load, dead-letter with paging) instead of OOMing silently.

In **Laravel**, a common pattern is: lightweight controller work, then **`dispatch` a job** onto Redis/Rabbit/SQS. At very high scale you may split **ingestion** into its own service so your monolith’s workers are not the universal bottleneck. Whatever you pick, spell out what happens when **Redis evicts keys** or a node vanishes—**“we pushed to a list” is not the same as “we cannot lose this trail.”**

---

<a id="redis-absorbers"></a>
## Redis lists versus streams as shock absorbers

A **list** (`LPUSH` / `BRPOP` style) is the minimal pipe: producers push, workers pop. It is easy to reason about and you probably already run Redis. The catch is **fair fan-out across multiple workers** without duplicating or skipping work—you end up inventing **sharding rules** or living with **single-consumer** bottlenecks.

**Streams** (`XADD`, `XREADGROUP`, `XACK`) add **consumer groups**, **message IDs**, and **pending** entries for poison or stuck messages. That is closer to a **mini append log** when you need **ordering within a stream key** and **several independent readers** (for example, fraud scoring and warehouse loading) without immediately adopting Kafka.

Redis still means **RAM economics** and **eviction policies**. If memory pressure triggers `allkeys-lru` on the wrong keys, **events disappear**. Mitigate with **caps**, **alerts on stream length**, **no-eviction** classes for telemetry keys, or graduate to **disk-backed brokers** when the audit trail must survive broker restarts by design.

---

<a id="queue-vs-log"></a>
## RabbitMQ and Kafka: queue brain versus log brain

**RabbitMQ** thinks in **queues, bindings, and routing**. If you already run **Horizon** or similar, the operational vocabulary is familiar: TTL, DLX, retries, per-queue concurrency. It excels at **task-shaped** work—email, webhooks, recomputation—especially when throughput is **large but not planet-scale** and you want **flexible routing** without operating a distributed log cluster.

**Kafka** thinks in **topics, partitions, offsets, retention**. Producers append; consumers **replay** from a position; many teams can **read the same history** independently. That matches **firehose analytics**, **event sourcing backbones**, and **regulatory-style replay**. The trade-off is **cluster operations**, capacity planning, and a mindset shift from **“job finished”** to **“offset committed.”**

Neither badge wins by fashion. **Volume, fan-out, retention requirements, and team skill** pick the tool. A frequent middle ground: **Rabbit (or a cloud queue) for imperative tasks**, plus **Kafka or a managed streaming service** for **immutable event history** when product and compliance both care about the tape.

---

<a id="analytics-lane"></a>
## Give analytics its own lane

**OLAP** here means **any store and schema optimized for heavy reads over wide time windows**—columnar warehouses, lakehouses, or even a **second Postgres** with different indexes and **no** tight coupling to the wallet tables.

Sketch of a healthy split:

* **OLTP** remains the **system of record** for balances and state transitions.
* Events land in a **stream or queue**, then **workers or ELT** land them in **facts and dimensions** tuned for BI tools.
* Dashboards query **that** world. Near-real-time needs use **materialized views**, **scheduled refreshes**, or **streaming aggregates**, not **ad hoc mega-joins** against production row stores.

Parking everything in `public.events` on the primary instance saves weeks early on and can cost **quarters** later. A pragmatic compromise is **physically separate databases or schemas** on shared metal, with **resource groups** or **hard statement timeouts** so an analyst cannot accidentally **starve** payment retries.

---

<a id="decision-questions"></a>
## Questions to answer before you commit

1. **Durability budget** — zero loss implies **replicated, persistent** buffers and **lag dashboards**, not “best effort unless we notice.”
2. **Ordering guarantees** — per-user strict ordering suggests **partition keys** and careful stream design; “roughly time ordered” relaxes the problem.
3. **Reader multiplicity** — the more independent consumers need the **same** history, the more a **retained log** wins over a **single queue drain**.
4. **Dashboard placement** — if BI still points at OLTP, plan **isolation**: replicas, timeouts, or a hard move to analytical tables.
5. **Idempotency** — networks retry; APIs double-post. Without keys or dedupe, **metrics and billing drift**.

---

Architecture guides that follow will cover **resilient provider integrations**, **balance races**, and **Laravel-specific wiring**. One line to keep: **treating a raw event flood as just more rows in the money database is a risk you choose**, not a law of physics. Buffer, route, and **separate the read models** before the graphs look fine but the checkout does not.
