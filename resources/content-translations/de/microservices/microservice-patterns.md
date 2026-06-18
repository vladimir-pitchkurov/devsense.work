---
title: "Microservice-Architekturmuster: Saga, CQRS, Event Sourcing & Circuit Breaker | DevSense"
description: "Meistern Sie die Architektur verteilter Systeme. Vertiefen Sie Ihr Wissen über Database-per-Service, Saga-Pattern, CQRS, Event Sourcing, Circuit Breaker und Service Discovery in PHP."
published: 2026-06-18
faq:
  - question: "Was ist der Unterschied zwischen Saga-Choreografie und Saga-Orchestrierung?"
    answer: "Saga-Choreografie basiert darauf, dass Services Events veröffentlichen und abonnieren, ohne dass ein zentraler Koordinator vorhanden ist, sodass die Services auf Änderungen reagieren. Die Saga-Orchestrierung verwendet eine zentrale Orchestrator-Klasse, die explizit alle Schritte und Kompensationen nacheinander koordiniert."
  - question: "Wie verbessert CQRS die Performance in Microservices?"
    answer: "CQRS entkoppelt Lese- von Schreibzugriffen, sodass Sie Lese-Datenbanken (wie Elasticsearch oder Redis-Read-Replicas) unabhängig von Schreib-Datenbanken (wie PostgreSQL) skalieren können. Lesezugriffe können einfache, schnelle Abfragen auf denormalisierten Ansichten nutzen, während Schreibzugriffe normalisierte Validierungs-Transaktionen ausführen."
  - question: "Warum benötigen wir einen Circuit Breaker in verteilten Systemen?"
    answer: "In einem Microservices-Netzwerk kann ein einzelner langsamer oder ausgefallener Service Threads oder Verbindungen in Upstream-Services erschöpfen, was zu kaskadierenden Ausfällen führt. Ein Circuit Breaker lässt Anfragen an einen fehlerhaften Service sofort fehlschlagen (Open-Zustand) und schützt so die Stabilität des Gesamtsystems."
---

# Microservice-Architekturmuster: Saga, CQRS, Event Sourcing & Circuit Breaker

Der Übergang von einem Monolithen zu Microservices löst Skalierungs- und Teamautonomie-Probleme, führt jedoch zu neuen Herausforderungen in verteilten Systemen. Sie verlieren ACID-Transaktionen über Datenbanken hinweg, die Netzwerklatenz steigt und Services können unabhängig voneinander ausfallen.

Um robuste und performante Systeme zu entwickeln, müssen Sie Microservice-Design-Patterns nutzen. In diesem Leitfaden analysieren wir fünf Kern-Microservice-Muster, ihre Vor- und Nachteile und implementieren praxisnahe PHP 8.x-Beispiele, die zeigen, wie sie funktionieren.

**Verwandte Leitfäden:** [Architektur vom Monolithen zu Microservices](monolith-to-microservices-architecture) · [Message Queues im Vergleich](message-queues-compared) · [Datenbankperformance und Skalierung](database-performance-and-scaling)

## Inhalt

* [Database-per-Service-Pattern](#database-per-service)
* [Saga-Pattern (Verteilte Transaktionen)](#saga-pattern)
* [CQRS & Event Sourcing](#cqrs-event-sourcing)
* [Circuit Breaker (Resilienz)](#circuit-breaker)
* [Service Discovery](#service-discovery)
* [Häufige Fehler](#common-mistakes)
* [Checkliste](#checklist)
* [Zusammenfassung](#summary)
* [Selbsttest-Quiz](#self-test-quiz)

---

<a id="database-per-service"></a>
## Database-per-Service-Pattern

In einem Monolithen teilen sich alle Module eine einzige Datenbank. In Microservices bricht das Teilen einer Datenbank die Servicegrenzen auf, führt zu einer engen Schema-Kopplung und verursacht eine Erschöpfung der Datenbankverbindungen.

Das **Database-per-Service**-Pattern schreibt vor, dass jeder Service Eigentümer seiner Daten ist. Kein anderer Service darf direkt auf diese Datenbank zugreifen. Stattdessen darf auf Daten ausschließlich über die öffentliche API des jeweiligen Service zugegriffen werden.

| Aspekt | Gemeinsame Datenbank (Monolith) | Database-per-Service |
| --- | --- | --- |
| **Kopplung** | Hoch (jede Schemaänderung bricht mehrere Module) | Gering (Services kapseln ihr Schema vollständig) |
| **Technologie** | Einzelne Datenbank-Engine (z. B. nur relational) | Polyglotte Persistenz (z. B. Neo4j für Graphen, MongoDB für Dokumente) |
| **Transaktionen** | ACID-Transaktionen (einfacher Rollback, Fremdschlüssel) | Verteilte Transaktionen (erfordert Sagas, Eventual Consistency / schlussendliche Konsistenz) |

---

<a id="saga-pattern"></a>
## Saga-Pattern (Verteilte Transaktionen)

Da die Datenbanken isoliert sind, können Sie kein standardmäßiges `START TRANSACTION` über mehrere Services hinweg ausführen. Das **Saga-Pattern** koordiniert eine Abfolge lokaler Transaktionen. Jede Transaktionsphase aktualisiert den Datenbankzustand innerhalb eines einzelnen Service und veröffentlicht ein Event.

Wenn eine Transaktion fehlschlägt (z. B. unzureichende Zahlung), führt die Saga **Kompensationstransaktionen** (compensating transactions) in umgekehrter Reihenfolge aus, um die durch die vorherigen Schritte vorgenommenen Änderungen rückgängig zu machen.

Es gibt zwei Koordinationsstrategien:
1. **Choreografie:** Dezentral. Services hören auf Events und stoßen ihre lokalen Aktionen an.
2. **Orchestrierung:** Zentralisiert. Ein zentraler Controller- bzw. Orchestrator-Service weist die Teilnehmer an, welche Aktionen auszuführen sind.

### Saga-Orchestrator-Implementierung in PHP

Hier ist ein zentralisierter Saga-Orchestrator, der eine Saga zur Bestellerstellung (Order Creation Saga) koordiniert.

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

In einer entkoppelten Datenbankarchitektur erfordert das Abfragen von Daten über Services hinweg teure HTTP-Joins.

**CQRS (Command Query Responsibility Segregation)** trennt Operationen, die Daten ändern (Commands), von Operationen, die Daten lesen (Queries).

**Event Sourcing** geht noch einen Schritt weiter: Anstatt den aktuellen Zustand einer Entität zu speichern, speichern Sie jede Zustandsänderung als ein chronologisches Event-Log.

Die Schreibseite protokolliert die Events (z. B. `OrderCreated`, `PaymentCompleted`). Ein Hintergrund-Projektor (Projector) hört auf diese Events und baut ein denormalisiertes Lesemodell auf (z. B. in Elasticsearch oder einer flachen Datenbanktabelle), das rein für schnelle Leseabfragen optimiert ist.

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
## Circuit Breaker (Resilienz)

Verteilte Netzwerke können ausfallen. Wenn Service A Service B aufruft und Service B Latenzen aufweist oder ausgefallen ist, blockieren die Threads von Service A, was zu einem kaskadierenden Ausfall nach oben führt.

Ein **Circuit Breaker** (Sicherung) fungiert als elektrische Sicherung um Remote-Aufrufe. Er verfolgt Fehler und wechselt zwischen drei Zuständen:

* **Closed (Geschlossen):** Normalbetrieb. Anfragen fließen an den Remote-Service.
* **Open (Offen):** Der Remote-Service schlägt fehl. Anfragen werden sofort blockiert und ein lokaler Fallback-Wert wird zurückgegeben.
* **Half-Open (Halboffen):** Die Abkühlphase ist abgelaufen. Eine begrenzte Anzahl von Anfragen wird gesendet, um zu prüfen, ob der Remote-Service wieder gesund ist.

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

In Microservice-Umgebungen skalierten Instanzen dynamisch nach oben und unten, was bedeutet, dass sich IP-Adressen ständig ändern. Das Hardcodieren von URLs in Konfigurationsdateien ist unmöglich.

**Service Discovery** (Dienstaufdeckung) stellt ein Service-Registry (z. B. Consul, Eureka) bereit, das als verteiltes Telefonbuch fungiert.

* **Service-Registrierung (Service Registration):** Wenn eine Instanz startet, ruft sie das Registry auf, um ihren Namen sowie die aktuelle IP und den Port zu registrieren. Sie sendet periodische Health-Checks (Gesundheitsprüfungen), um im Registry registriert zu bleiben.
* **Service-Abfrage (Service Lookup):** Wenn Service A Service B aufrufen möchte, fragt er das Registry ab, um eine Liste der aktiven IP-Adressen zu erhalten, und wendet anschließend Load-Balancing an.

---

<a id="common-mistakes"></a>
## Häufige Fehler

1. **Gemeinsam genutzte Datenbanken (Shared Databases):** Ausführen mehrerer Microservices auf demselben Datenbank-Schema, was zu versteckter Abhängigkeitskopplung und Datenbanksperren führt.
2. **Fehlende Kompensationsschritte in Sagas:** Aufbau von Sagas ohne das Schreiben robuster Kompensationsmaßnahmen, was bei einem Fehlschlagen von Transaktionen auf halbem Weg zu inkonsistenten Daten führt.
3. **Event Sourcing ohne Snapshots:** Erneutes Auslesen Tausender historischer Events bei jeder Anfrage, um den aktuellen Zustand zu ermitteln, was zu erheblichem CPU- und Datenbank-Overhead führt. Erstellen Sie regelmäßig Zustands-Snapshots (z. B. alle 100 Events).
4. **Ignorieren der Circuit-Breaker-Abkühlzeiten:** Das Offenlassen des Circuit Breakers auf unbestimmte Zeit oder der direkte Wechsel zurück in den geschlossenen Zustand (Closed), ohne vorherige Tests mit einer begrenzten Anzahl von Anfragen (Half-Open-Zustand).

---

<a id="checklist"></a>
## Checkliste

1. **Datenisolation:** Greift Ihr Service direkt auf die Datenbanktabellen eines anderen Service zu? Wenn ja, refaktorisieren Sie dies in HTTP- bzw. gRPC-API-Aufrufe.
2. **Sicherheit bei verteilten Transaktionen:** Wenn der Zahlungsschritt bei der Bestellerstellung fehlschlägt, haben Sie einen Kompensations-Trigger, der die Bestellung in Ihrer Datenbank storniert?
3. **CQRS-Projektions-Isolation:** Läuft Ihre CQRS-Leseprojektionsdatenbank auf einem separaten Read-Replica oder einem spezialisierten Datenspeicher, oder führen Sie immer noch langsame JOIN-Abfragen auf der Schreibdatenbank aus?
4. **Resilienz-Wrapper:** Haben Sie Ihre externen API-Aufrufe mit einem Circuit-Breaker-Pattern versehen, um kaskadierende Netzwerkausfälle zu verhindern?

---

<a id="summary"></a>
## Zusammenfassung

Microservice-Architekturmuster sorgen für hohe Skalierbarkeit und Fehlertoleranz. Werden Sie zum alleinigen Dateninhaber mit **Database-per-Service**. Vermeiden Sie globales Locking, indem Sie das **Saga-Pattern** verwenden, um verteilte Transaktionen zu verwalten. Skalieren Sie den Lese-Traffic mit **CQRS** und zeichnen Sie den Verlauf mit **Event Sourcing** auf. Schützen Sie Ihre Services vor kaskadierenden Ausfällen mithilfe von **Circuit Breakern**. Bilden Sie Instanzen dynamisch über **Service-Discovery**-Registries ab.

---

<a id="self-test-quiz"></a>
## Selbsttest-Quiz

### Frage 1: Was ist der Hauptzweck von Kompensationstransaktionen im Saga-Pattern?
- A) Zur Optimierung von SQL-Ausführungsplänen auf PostgreSQL-Servern.
- B) Um die Auswirkungen zuvor abgeschlossener lokaler Transaktionen in der Sequenz rückgängig zu machen, wenn ein nachfolgender Schritt in der Saga fehlschlägt.
- C) Um Event-Payloads vor dem Senden über Redis-Streams zu verschlüsseln.

<details>
<summary>Klicken Sie hier, um die Antwort anzuzeigen</summary>

**Antwort: B**
Da Microservices keine gemeinsamen Datenbanktransaktionen nutzen, erreichen Sagas Konsistenz, indem sie Kompensationsaktionen in umgekehrter Reihenfolge ausführen, um frühere Aktualisierungen rückgängig zu machen, falls der Geschäftsprozess mitten in der Ausführung fehlschlägt.
</details>

### Frage 2: Welche Rolle spielt eine „Projektion“ (Projection) in CQRS / Event Sourcing?
- A) Zur Darstellung von Diagrammen und Dashboard-Grafiken im Admin-Panel.
- B) Zur Berechnung der zukünftig von Datenbanken benötigten Speicherkapazität.
- C) Um das Append-Only-Event-Log chronologisch in ein denormalisiertes, leseoptimiertes Modell für schnelle Abfragen zusammenzustellen.

<details>
<summary>Klicken Sie hier, um die Antwort anzuzeigen</summary>

**Antwort: C**
Projektionen hören auf den aggregierten Event-Stream (wie OrderCreated, OrderShipped) und aktualisieren eine flache, denormalisierte Lese-Datenbank (wie eine Dokumentendatenbank oder Replica-Datenbank), die speziell für schnelle, indexfreundliche Select-Abfragen optimiert ist.
</details>

### Frage 3: Was passiert, wenn ein Circuit Breaker in den Zustand „Open“ (Offen) wechselt?
- A) Alle Anfragen dürfen direkt passieren, um den Systemzustand des Service zu überprüfen.
- B) Anfragen werden sofort blockiert und an lokale Fallbacks umgeleitet, ohne den Remote-Service aufzurufen, was Ressourcen spart.
- C) Die Anwendung startet automatisch neue Docker-Instanzen über Docker Compose.

<details>
<summary>Klicken Sie hier, um die Antwort anzuzeigen</summary>

**Antwort: B**
Wenn ein Service wiederholt fehlschlägt, öffnet sich der Circuit Breaker, um das Blockieren von Verbindungs-Threads zu verhindern. Alle nachfolgenden Aufrufe schlagen sofort fehl, wodurch der Remote-Netzwerkaufruf vollständig umgangen und eine Fallback-Routine ausgeführt wird.
</details>
