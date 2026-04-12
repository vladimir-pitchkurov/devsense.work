---
title: "Message queues compared: Redis, RabbitMQ, Kafka — Laravel & beyond | DevSense"
description: "Job queues versus event logs: Redis as Laravel’s default, RabbitMQ for AMQP routing, Kafka as a distributed log—plus cloud options, other frameworks, overkill cases, and sharp edges."
published: 2026-04-12
---

# Message queues compared: Redis, RabbitMQ, Kafka, and the wider market

People say “queue” when they mean **three different things**: a **work backlog** for background jobs, a **broker** that routes messages between services, or an **append-only log** many teams read at their own pace. **Laravel** ships excellent ergonomics for the first (especially with **Redis** and **Horizon**). **RabbitMQ** shines when routing and dead-letter policies matter across runtimes. **Kafka** wins when history, replay, and fan-out dominate. This guide lines those roles up, sketches **other vendors**, and flags **over-engineering** and **operational footguns**.

**Related:** [High-load event streams](high-load-event-ingestion) · [Sail: queues & RabbitMQ](../tools/sail-queues)

## Contents

* [Jobs now, logs later: pick the metaphor](#two-metaphors)
* [Redis in Laravel-land](#redis-laravel)
* [RabbitMQ and AMQP routing](#rabbit-amqp)
* [Kafka as a log, not a mailbox](#kafka-log)
* [Beyond the big three](#wider-market)
* [Laravel drivers in practice](#laravel-drivers)
* [How other frameworks plug in](#frameworks)
* [When you are overbuying complexity](#overkill)
* [Delivery, ordering, poison pills](#sharp-edges)

---

<a id="two-metaphors"></a>
## Jobs now, logs later: pick the metaphor

**Background jobs** want **at-least-once execution**, retries, timeouts, and visibility into failures (`failed_jobs` in Laravel). One worker grabs a unit of work; others stay idle until more jobs arrive.

**Broker-style messaging** (classic Rabbit) focuses on **routing**: publishers emit to **exchanges**, **bindings** fan messages into queues, consumers acknowledge. Great for **service integration** where multiple apps care about different slices of traffic.

**Kafka-style logs** keep **ordered partitions**, **offsets**, and **retention**. Many **consumer groups** read the same topic independently—ideal for **telemetry**, **audit trails**, and **replaying** history when a new downstream appears. Awkward when all you needed was “email the user after signup.”

Mixing metaphors hurts: treating Kafka like a single Rabbit queue wastes its strengths; pretending Rabbit is a long-term immutable archive stretches it.

---

<a id="redis-laravel"></a>
## Redis in Laravel-land

`QUEUE_CONNECTION=redis` plus **Horizon** is the default serious setup for many Laravel teams: fast, colocated with cache/rate limiting, good dashboards for workers and throughput.

**Strengths:** low latency, simple ops for moderate scale, first-class Laravel tooling.

**Watchouts:**

* **Memory pressure** during spikes—monitor queue lengths, cap growth, and make sure **eviction policies** cannot silently drop queue keys.
* **Durability** is “Redis durability,” not magically the same as a multi-region log; design **replication** and **failure drills** if you promise stricter SLAs.
* **Redis Streams** help when you need **consumer groups** on a stream; Laravel’s job queue is **task-centric**, not a multi-day immutable journal.

Stick with Redis until **cross-language routing**, **complex DLQ policies**, or **massive retained history** push you elsewhere.

---

<a id="rabbit-amqp"></a>
## RabbitMQ and AMQP routing

Rabbit implements **AMQP** (and more): **exchanges**, **queues**, **bindings**, **TTL**, **dead-letter exchanges**, **prefetch**. Laravel integrates via community packages such as **`vladimir-yuldashev/laravel-queue-rabbitmq`**, and Rabbit is a natural peer for **Symfony Messenger** or polyglot microservices.

**Strengths:** expressive routing, mature operational patterns, fits both **task queues** and **event-style** messaging between services.

**Watchouts:** running a **resilient cluster** is real work—disk/memory watermarks, mirrored/HA queues depending on version, **consumer prefetch** tuning, and the risk that one slow consumer **blocks prefetch slots** if misconfigured.

If you only dispatch Laravel jobs on one app with no cross-service bus, Rabbit can be **more moving parts** than Redis without compensating benefits.

---

<a id="kafka-log"></a>
## Kafka as a log, not a mailbox

Kafka stores **topics** split into **partitions**; producers append; consumers track **offsets**; **retention** (time or compaction) defines how long data stays readable.

**Strengths:** huge throughput when partitioned well, **independent consumer groups**, **replay** for new services or forensic debugging, ecosystem for **stream processors**.

**Watchouts:** Laravel has **no first-party queue driver** identical to `redis` for Kafka—you often run **dedicated consumers** (sometimes not PHP) and treat PHP as a **producer** or thin consumer. Ops topics include **KRaft/ZK legacy**, **rebalances**, **partition sizing**, and **schema evolution** (Avro/Protobuf registries).

Using Kafka **solely** for low-volume cron-style jobs is a classic **complexity tax**.

---

<a id="wider-market"></a>
## Beyond the big three

* **Amazon SQS (+ SNS)** — serverless queues and pub/sub; Laravel’s **`sqs`** driver fits teams that outsource broker uptime. Mind **visibility timeouts** and per-call costs at scale.
* **Google Pub/Sub** and **Azure Service Bus** — cloud-native messaging with IAM integration and serverless hooks.
* **NATS / JetStream** — lightweight, popular in Go services; JetStream adds persistence; different trade-offs than AMQP.
* **Beanstalkd** — minimal job tube model; less fashionable but easy to reason about.
* **Managed Kafka** (Confluent, Aiven, MSK) — reduces hardware toil, **not** the need for solid **topic design** and **consumer discipline**.

“Buy vs build” also means **portability vs operational ownership**.

---

<a id="laravel-drivers"></a>
## Laravel drivers in practice

* **`sync`** — no queue; great for local debugging, dangerous if left on in production by mistake.
* **`database`** — jobs in SQL; simplest infra, but polling and write contention bite at scale.
* **`redis`** — sweet spot for many apps; pair with **Horizon** for supervision.
* **`sqs`** — when AWS already hosts everything.
* **RabbitMQ** — via packages; map exchanges/queues deliberately.

Regardless of transport: design jobs to be **idempotent** where retries are possible—**networks duplicate**, and **double emails** are only the tamest failure mode.

---

<a id="frameworks"></a>
## How other frameworks plug in

* **Symfony Messenger** — transport-agnostic messages; swap **AMQP**, Redis, Doctrine, etc.
* **Django + Celery** — Redis or Rabbit as broker; mature periodic task story.
* **Node** — **BullMQ** on Redis; **amqplib** for Rabbit; often shares Redis with sessions.
* **Spring (Java)** — first-class Rabbit/Kafka listeners.
* **.NET** — **MassTransit**, Azure Service Bus, Confluent clients.

Polyglot shops should standardize on **payload format** and **versioning** before standardizing on a broker brand.

---

<a id="overkill"></a>
## When you are overbuying complexity

| Profile | Usually enough | Often overkill |
|---------|----------------|----------------|
| Low traffic monolith | `database` or Redis | Multi-broker mesh |
| Laravel-only background work | Redis + Horizon | Kafka for “future scale” |
| Cross-service events with routing | Rabbit (or cloud equivalent) | Custom Kafka without consumers ready |
| Massive telemetry + many readers | Kafka / managed streaming | One Redis list without alarms |

Overkill includes **on-call runbooks** nobody has practiced: lag, DLQ depth, consumer offline alerts.

---

<a id="sharp-edges"></a>
## Delivery, ordering, poison pills

1. **Exactly-once end-to-end** is expensive; **at-least-once** plus **idempotent handlers** is the pragmatic default.
2. Kafka ordering is **per partition**, not global—choose keys wisely.
3. **Poison messages** need **max attempts**, **DLQs**, and human triage paths.
4. **Metrics:** queue depth, consumer lag, oldest message age, worker error rates—measure before users complain.
5. **Schema changes** without compatibility break older consumers silently.

---

**Bottom line:** name the problem—**defer work**, **route integration traffic**, or **retain a replayable stream**—then pick **Redis, Rabbit, or Kafka** (or a managed cousin) on purpose. For data-path context see [high-load event ingestion](high-load-event-ingestion); for local Docker queues see [Sail queues](../tools/sail-queues).
