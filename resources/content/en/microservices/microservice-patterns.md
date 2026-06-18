---
title: "Microservice Architectural Patterns: Saga, CQRS, Event Sourcing & Circuit Breaker | DevSense"
description: "Master distributed systems architecture. Deep dive into Database-per-service, Saga pattern, CQRS, Event Sourcing, Circuit Breakers, and Service Discovery in PHP."
published: 2026-06-18
faq:
  - question: "What is the difference between Saga Choreography and Saga Orchestration?"
    answer: "Saga Choreography relies on services publishing and subscribing to events without a central coordinator, causing services to react to changes. Saga Orchestration uses a central orchestrator class that explicitly coordinates all steps and compensations sequentially."
  - question: "How does CQRS improve performance in microservices?"
    answer: "CQRS decouples reads from writes, allowing you to scale read databases (like Elasticsearch or Redis read-replicas) independently from write databases (like PostgreSQL). Reads can use simple, fast queries on denormalized views, while writes run normalized validation transactions."
  - question: "Why do we need a Circuit Breaker in distributed systems?"
    answer: "In a microservices mesh, a single slow or down service can exhaust threads or connections in upstream services, causing a cascading failure. A Circuit Breaker immediately fails requests to a failing service (Open state), protecting the overall system stability."
---

# Microservice Architectural Patterns: Saga, CQRS, Event Sourcing & Circuit Breaker

Transitioning from a monolith to microservices solves scaling and team autonomy issues, but introduces new distributed systems problems. You lose ACID transactions across databases, network latency increases, and services can fail independently.

To build resilient and performant systems, you must leverage microservice design patterns. In this guide, we will analyze five core microservice patterns, their trade-offs, and implement real-world PHP 8.x examples showing how they work.

**Related guides:** [Monolith to microservices architecture](monolith-to-microservices-architecture) · [Message queues compared](message-queues-compared) · [Database performance and scaling](database-performance-and-scaling)

## Contents

* [Database-per-Service Pattern](#database-per-service)
* [Saga Pattern (Distributed Transactions)](#saga-pattern)
* [CQRS & Event Sourcing](#cqrs-event-sourcing)
* [Circuit Breaker (Resilience)](#circuit-breaker)
* [Service Discovery](#service-discovery)
* [Common Mistakes](#common-mistakes)
* [Checklist](#checklist)
* [Summary](#summary)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="database-per-service"></a>
## Database-per-Service Pattern

In a monolith, all modules share a single database. In microservices, sharing a database breaks service boundaries, introduces tight schema coupling, and creates database connection exhaustion. 

The **Database-per-Service** pattern dictates that each service owns its data. No other service can access this database directly. Instead, data must be accessed exclusively through the service's public API.

| Aspect | Shared Database (Monolith) | Database-per-Service |
| --- | --- | --- |
| **Coupling** | High (any schema change breaks multiple modules) | Low (services encapsulate their schema completely) |
| **Technology** | Single database engine (e.g. relational only) | Polyglot persistence (e.g. Neo4j for graphs, MongoDB for documents) |
| **Transactions** | ACID transactions (easy rollback, foreign keys) | Distributed transactions (requires Sagas, eventual consistency) |

---

<a id="saga-pattern"></a>
## Saga Pattern (Distributed Transactions)

Since databases are isolated, you cannot run a standard `START TRANSACTION` across multiple services. The **Saga Pattern** coordinates a sequence of local transactions. Each transaction updates database state inside a single service and publishes an event.

If one transaction fails (e.g. insufficient payment), the Saga executes **compensating transactions** in reverse order to roll back the changes made by preceding steps.

There are two coordination strategies:
1. **Choreography:** Decentralized. Services listen to events and trigger their local actions.
2. **Orchestration:** Centralized. A controller orchestrator service instructs participants on what actions to execute.

### Saga Orchestrator Implementation in PHP

Here is a centralized Saga orchestrator coordinating an Order Creation Saga.

```php
// app/Sagas/OrderSagaOrchestrator.php
declare(strict_types=1);

namespace App\Sagas;

use App\Sagas\Steps\SagaStepInterface;
use Exception;
use Psr\Log\LoggerInterface;

class OrderSagaOrchestrator
{
    private array $steps = [];
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function addStep(SagaStepInterface $step): self
    {
        $this->steps[] = $step;
        return $this;
    }

    public function execute(array $payload): bool
    {
        $executedSteps = [];

        foreach ($this->steps as $step) {
            try {
                $this->logger->info("Executing step: " . get_class($step));
                $step->execute($payload);
                $executedSteps[] = $step;
            } catch (Exception $e) {
                $this->logger->error("Saga step failed: " . $e->getMessage() . ". Starting compensations.");
                $this->compensate($executedSteps, $payload);
                return false;
            }
        }

        return true;
    }

    private function compensate(array $executedSteps, array $payload): void
    {
        // Compensate executed steps in reverse order (LIFO)
        foreach (array_reverse($executedSteps) as $step) {
            try {
                $this->logger->warning("Compensating step: " . get_class($step));
                $step->compensate($payload);
            } catch (Exception $e) {
                $this->logger->critical("Compensation failed for " . get_class($step) . ": " . $e->getMessage());
                // In production, queue failed compensations for manual recovery/retries
            }
        }
    }
}
```

```php
// app/Sagas/Steps/SagaStepInterface.php
declare(strict_types=1);

namespace App\Sagas\Steps;

interface SagaStepInterface
{
    public function execute(array $payload): void;
    public function compensate(array $payload): void;
}
```

---

<a id="cqrs-event-sourcing"></a>
## CQRS & Event Sourcing

In a decoupled database architecture, querying data across services requires expensive HTTP joins. 

**CQRS (Command Query Responsibility Segregation)** separates operations that modify data (Commands) from operations that read data (Queries). 
**Event Sourcing** takes this further: instead of storing the current state of an entity, you store every state transition as a chronological event log.

The write side logs the events (e.g. `OrderCreated`, `PaymentCompleted`). A background projector listens to these events and constructs a denormalized read model (e.g., in Elasticsearch or a flat database table) optimized purely for fast read queries.

```php
// app/CQRS/OrderCommandHandler.php
declare(strict_types=1);

namespace App\CQRS;

use App\Events\OrderCreatedEvent;
use App\Models\EventStore;

class OrderCommandHandler
{
    private EventStore $eventStore;
    private EventDispatcherInterface $dispatcher;

    public function __construct(EventStore $eventStore, EventDispatcherInterface $dispatcher)
    {
        $this->eventStore = $eventStore;
        $this->dispatcher = $dispatcher;
    }

    public function handle(CreateOrderCommand $command): void
    {
        // 1. Validate domain logic
        if ($command->amount <= 0) {
            throw new \InvalidArgumentException("Order amount must be positive.");
        }

        // 2. Generate Domain Event
        $event = new OrderCreatedEvent(
            orderId: uniqid('ord_', true),
            userId: $command->userId,
            amount: $command->amount,
            createdAt: new \DateTimeImmutable()
        );

        // 3. Persist Event to Append-Only Event Store
        $this->eventStore->append(
            aggregateId: $event->orderId,
            aggregateType: 'Order',
            eventName: 'OrderCreated',
            payload: $event->toArray()
        );

        // 4. Dispatch event to update projections (Read Database) asynchronously
        $this->dispatcher->dispatch($event);
    }
}
```

---

<a id="circuit-breaker"></a>
## Circuit Breaker (Resilience)

Distributed networks fail. If Service A calls Service B, and Service B is experiencing latency or is down, Service A's threads will block, cascading the failure upward.

A **Circuit Breaker** acts as an electrical fuse wrapper around remote calls. It tracks failures and transitions through three states:
* **Closed:** Normal operations. Requests flow to the remote service.
* **Open:** Remote service is failing. Requests are blocked immediately, returning a local fallback value.
* **Half-Open:** Cooling period expired. A limited number of requests are sent to check if the remote service is healthy again.

```php
// app/CircuitBreaker/CircuitBreaker.php
declare(strict_types=1);

namespace App\CircuitBreaker;

use App\Services\CacheInterface;
use RuntimeException;

class CircuitBreaker
{
    private const STATE_CLOSED = 'CLOSED';
    private const STATE_OPEN = 'OPEN';
    private const STATE_HALF_OPEN = 'HALF_OPEN';

    private CacheInterface $cache;
    private string $serviceName;
    private int $failureThreshold;
    private int $cooldownPeriod; // In seconds

    public function __construct(
        CacheInterface $cache,
        string $serviceName,
        int $failureThreshold = 5,
        int $cooldownPeriod = 30
    ) {
        $this->cache = $cache;
        $this->serviceName = $serviceName;
        $this->failureThreshold = $failureThreshold;
        $this->cooldownPeriod = $cooldownPeriod;
    }

    public function call(callable $remoteCall, callable $fallback): mixed
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            if ($this->hasCooldownExpired()) {
                $this->transitionTo(self::STATE_HALF_OPEN);
            } else {
                return $fallback(); // Immediate fallback, no remote call
            }
        }

        try {
            $result = $remoteCall();
            $this->resetFailures();
            return $result;
        } catch (\Exception $e) {
            $this->handleFailure();
            return $fallback();
        }
    }

    private function getState(): string
    {
        return $this->cache->get("cb:{$this->serviceName}:state") ?: self::STATE_CLOSED;
    }

    private function transitionTo(string $state): void
    {
        $this->cache->set("cb:{$this->serviceName}:state", $state);
        if ($state === self::STATE_OPEN) {
            $this->cache->set("cb:{$this->serviceName}:open_time", time());
        }
    }

    private function handleFailure(): void
    {
        $failures = (int)$this->cache->get("cb:{$this->serviceName}:failures") + 1;
        $this->cache->set("cb:{$this->serviceName}:failures", $failures);

        if ($failures >= $this->failureThreshold) {
            $this->transitionTo(self::STATE_OPEN);
        }
    }

    private function resetFailures(): void
    {
        $this->cache->set("cb:{$this->serviceName}:failures", 0);
        $this->transitionTo(self::STATE_CLOSED);
    }

    private function hasCooldownExpired(): bool
    {
        $openTime = (int)$this->cache->get("cb:{$this->serviceName}:open_time");
        return (time() - $openTime) >= $this->cooldownPeriod;
    }
}
```

---

<a id="service-discovery"></a>
## Service Discovery

In microservice environments, instances scale up and down dynamically, meaning IP addresses change constantly. Hardcoding URLs inside configuration files is impossible.

**Service Discovery** provides a service registry (e.g. Consul, Eureka) acting as a distributed phonebook.
* **Service Registration:** When an instance starts, it calls the registry to record its name and current IP/port. It sends periodic health checks to remain in the registry.
* **Service Lookup:** When Service A wants to call Service B, it queries the registry to obtain a list of active IP addresses and then applies load-balancing.

---

<a id="common-mistakes"></a>
## Common Mistakes

1. **Shared Databases:** Running multiple microservices against the same database schema, resulting in hidden dependency coupling and database locking.
2. **Missing Compensation steps in Sagas:** Building Sagas without writing robust compensating actions, leaving the data in a corrupt state when transactions fail midway.
3. **Event Sourcing without Snapshots:** Re-reading thousands of historical events to build current state on every request, leading to massive CPU and DB overhead. Create state snapshots periodically (e.g., every 100 events).
4. **Ignoring Circuit Breaker Cooldowns:** Keeping the circuit breaker open forever or transitioning back to closed without testing with limited requests first (Half-Open state).

---

<a id="checklist"></a>
## Checklist

1. **Data Isolation:** Is your service accessing another service's database tables directly? If yes, refactor to HTTP/gRPC API calls.
2. **Distributed Transaction Safety:** If an order creation payment step fails, do you have a compensation trigger that cancels the order in your Database?
3. **CQRS Projection Isolation:** Is your CQRS read projection database running on a separate read replica or specialized datastore, or are you still running slow JOIN queries on the write database?
4. **Resilience wrapper:** Have you wrapped your external API calls in a Circuit Breaker pattern to prevent cascading network failures?

---

<a id="summary"></a>
## Summary

Microservice architectural patterns build high scalability and fault tolerance. Own your data with **Database-per-Service**. Avoid global locking with **Saga Patterns** to manage eventual transactions. Scale read traffic with **CQRS** and record history with **Event Sourcing**. Shield your services from cascading outages using **Circuit Breakers**. Map instances dynamically using **Service Discovery** registries.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: What is the main purpose of compensating transactions in the Saga pattern?
- A) To optimize SQL execution plans on PostgreSQL servers.
- B) To reverse the effects of previously completed local transactions in the sequence when a subsequent step in the Saga fails.
- C) To encrypt event payloads before sending them over Redis streams.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Because microservices do not share database transactions, Sagas achieve consistency by executing compensating actions in reverse order to cancel out earlier updates if the business process fails mid-execution.
</details>

### Question 2: In CQRS / Event Sourcing, what is the role of a "Projection"?
- A) To render charts and dashboard graphs in the admin panel.
- B) To calculate the future storage capacity required by databases.
- C) To compile the append-only event log chronologically into a denormalized read-optimized model for quick querying.

<details>
<summary>Click to view the answer</summary>

**Answer: C**
Projections listen to the aggregate event stream (like OrderCreated, OrderShipped) and update a flat, denormalized read database (like a document db or replica database) optimized specifically for fast, index-friendly select queries.
</details>

### Question 3: What happens when a Circuit Breaker transitions to the 'Open' state?
- A) All requests are allowed to pass through directly to check service health.
- B) Requests are immediately blocked and redirected to local fallbacks without calling the remote service, saving resources.
- C) The application automatically spawns new Docker instances via Docker Compose.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
When a service fails repeatedly, the circuit breaker opens to prevent blocking connection threads. Any subsequent calls fail immediately, bypassing the remote network call entirely and executing a fallback routine.
</details>
