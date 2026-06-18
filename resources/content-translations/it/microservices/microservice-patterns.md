---
title: "Pattern Architetturali dei Microservizi: Saga, CQRS, Event Sourcing & Circuit Breaker | DevSense"
description: "Padroneggia l'architettura dei sistemi distribuiti. Un'analisi approfondita di Database-per-service, pattern Saga, CQRS, Event Sourcing, Circuit Breaker e Service Discovery in PHP."
published: 2026-06-18
faq:
  - question: "Qual è la differenza tra Saga Choreography e Saga Orchestration?"
    answer: "La Saga Choreography (coreografia) si basa sulla pubblicazione e sottoscrizione di eventi da parte dei singoli servizi senza un coordinatore centrale, facendo sì che i servizi reagiscano autonomamente ai cambiamenti. La Saga Orchestration (orchestrazione) utilizza invece una classe orchestratrice centrale che coordina esplicitamente tutte le fasi e le relative compensazioni in modo sequenziale."
  - question: "In che modo il pattern CQRS migliora le prestazioni nei microservizi?"
    answer: "Il pattern CQRS disaccoppia le operazioni di lettura da quelle di scrittura, consentendo di scalare i database di lettura (come Elasticsearch o le repliche di lettura di Redis) in modo indipendente dai database di scrittura (come PostgreSQL). Le letture possono utilizzare query semplici e veloci su viste denormalizzate, mentre le scritture eseguono transazioni di validazione normalizzate."
  - question: "Perché abbiamo bisogno di un Circuit Breaker nei sistemi distribuiti?"
    answer: "In una rete di microservizi, un singolo servizio lento o non raggiungibile può esaurire i thread o le connessioni nei servizi a monte, causando un fallimento a catena. Un Circuit Breaker blocca immediatamente le richieste verso un servizio in avaria (stato Open), proteggendo la stabilità complessiva del sistema."
---

# Pattern Architetturali dei Microservizi: Saga, CQRS, Event Sourcing & Circuit Breaker

Il passaggio da un monolite ai microservizi risolve i problemi di scalabilità e di autonomia dei team, ma introduce nuove sfide tipiche dei sistemi distribuiti. Si perde il supporto alle transazioni ACID tra i database, aumenta la latenza di rete e i singoli servizi possono smettere di funzionare in modo indipendente.

Per creare sistemi resilienti e performanti, è necessario sfruttare i design pattern per microservizi. In questa guida analizzeremo cinque pattern fondamentali per i microservizi, i relativi compromessi e implementeremo esempi reali in PHP 8.x che ne mostrano il funzionamento.

**Guide correlate:** [Architettura da Monolite a Microservizi](monolith-to-microservices-architecture) · [Confronto tra Message Queue](message-queues-compared) · [Prestazioni e scaling dei database](database-performance-and-scaling)

## Indice

* [Pattern Database-per-Service](#database-per-service)
* [Pattern Saga (Transazioni Distribuite)](#saga-pattern)
* [CQRS & Event Sourcing](#cqrs-event-sourcing)
* [Circuit Breaker (Resilienza)](#circuit-breaker)
* [Service Discovery](#service-discovery)
* [Errori Comuni](#common-mistakes)
* [Checklist](#checklist)
* [Riepilogo](#summary)
* [Quiz di Autovalutazione](#self-test-quiz)

---

<a id="database-per-service"></a>
## Pattern Database-per-Service

In un monolite, tutti i moduli condividono un unico database. Nei microservizi, la condivisione di un database rompe i confini dei servizi, introduce un forte accoppiamento degli schemi e crea il rischio di esaurimento delle connessioni al database.

Il pattern **Database-per-Service** impone che ogni servizio possieda i propri dati. Nessun altro servizio può accedere direttamente a questo database. I dati devono invece essere accessibili esclusivamente tramite le API pubbliche del servizio stesso.

| Aspetto | Database Condiviso (Monolite) | Database-per-Service |
| --- | --- | --- |
| **Accoppiamento** | Elevato (qualsiasi modifica allo schema rompe più moduli) | Basso (i servizi incapsulano completamente il proprio schema) |
| **Tecnologia** | Singolo motore di database (es. solo relazionale) | Persistenza poliglotta (es. Neo4j per i grafi, MongoDB per i documenti) |
| **Transazioni** | Transazioni ACID (rollback semplici, chiavi esterne) | Transazioni distribuite (richiede Saga, consistenza eventuale) |

---

<a id="saga-pattern"></a>
## Pattern Saga (Transazioni Distribuite)

Poiché i database sono isolati, non è possibile eseguire una transazione standard con `START TRANSACTION` su più servizi. Il **Pattern Saga** coordina una sequenza di transazioni locali. Ciascuna transazione aggiorna lo stato del database all'interno di un singolo servizio e pubblica un evento.

Se una transazione fallisce (ad esempio per pagamento insufficiente), la Saga esegue delle **transazioni di compensazione** in ordine inverso per annullare le modifiche apportate dalle fasi precedenti.

Esistono due strategie di coordinamento:
1. **Choreography (Coreografia):** Decentralizzata. I servizi ascoltano gli eventi e attivano le proprie azioni locali.
2. **Orchestration (Orchestrazione):** Centralizzata. Un servizio di controllo (orchestratore) indica ai partecipanti quali azioni eseguire.

### Implementazione di un Orchestratore Saga in PHP

Ecco un orchestratore Saga centralizzato che coordina una Saga di creazione dell'ordine.

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

In un'architettura con database disaccoppiati, l'esecuzione di query sui dati che coinvolgono più servizi richiede costose join tramite HTTP.

Il pattern **CQRS (Command Query Responsibility Segregation)** separa le operazioni che modificano i dati (Command) dalle operazioni che leggono i dati (Query).
L'**Event Sourcing** spinge questo concetto oltre: invece di memorizzare lo stato corrente di un'entità, viene memorizzata ogni transizione di stato come un log cronologico di eventi.

La parte di scrittura registra gli eventi (ad esempio `OrderCreated`, `PaymentCompleted`). Un proiettore in background (projector) ascolta questi eventi e costruisce un modello di lettura denormalizzato (ad esempio in Elasticsearch o in una tabella di database piatta) ottimizzato esclusivamente per query di lettura rapide.

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
## Circuit Breaker (Resilienza)

Le reti distribuite possono fallire. Se il Servizio A chiama il Servizio B, e il Servizio B presenta un'elevata latenza o è inattivo, i thread del Servizio A si bloccheranno, propagando il fallimento a cascata verso l'alto.

Un **Circuit Breaker** (interruttore di circuito) agisce come un fusibile elettrico che avvolge le chiamate remote. Tiene traccia dei fallimenti e passa attraverso tre stati:
* **Closed (Chiuso):** Operazioni normali. Le richieste fluiscono regolarmente verso il servizio remoto.
* **Open (Aperto):** Il servizio remoto è in errore. Le richieste vengono bloccate immediatamente, restituendo un valore di fallback locale.
* **Half-Open (Semiaperto):** Il periodo di raffreddamento (cooldown) è scaduto. Viene inviato un numero limitato di richieste per verificare se il servizio remoto è tornato in salute.

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

Negli ambienti a microservizi, le istanze scalano verticalmente e orizzontalmente in modo dinamico, il che significa che gli indirizzi IP cambiano costantemente. Scrivere gli URL direttamente nei file di configurazione è impossibile.

Il pattern **Service Discovery** fornisce un registro dei servizi (es. Consul, Eureka) che funge da elenco telefonico distribuito.
* **Registrazione del Servizio (Service Registration):** All'avvio di un'istanza, questa chiama il registro per registrare il proprio nome e la combinazione IP/porta corrente. Invia periodicamente controlli sullo stato di salute (health check) per rimanere nel registro.
* **Ricerca del Servizio (Service Lookup):** Quando il Servizio A vuole chiamare il Servizio B, interroga il registro per ottenere l'elenco degli indirizzi IP attivi e applica quindi un bilanciamento del carico (load balancing).

---

<a id="common-mistakes"></a>
## Errori Comuni

1. **Database Condivisi:** Eseguire più microservizi sullo stesso schema di database, con conseguente accoppiamento nascosto delle dipendenze e lock sul database.
2. **Fasi di Compensazione mancanti nelle Saga:** Progettare le Saga senza scrivere solide azioni compensative, lasciando i dati in uno stato corrotto quando le transazioni falliscono a metà percorso.
3. **Event Sourcing senza Snapshot:** Rileggere migliaia di eventi storici per ricostruire lo stato corrente a ogni richiesta, portando a un enorme sovraccarico di CPU e DB. Creare snapshot dello stato periodicamente (ad esempio ogni 100 eventi).
4. **Ignorare i tempi di cooldown del Circuit Breaker:** Mantenere il circuit breaker sempre aperto o farlo tornare a chiuso senza prima verificarlo tramite un numero limitato di richieste (stato Half-Open).

---

<a id="checklist"></a>
## Checklist

1. **Isolamento dei Dati:** Il tuo servizio accede direttamente alle tabelle del database di un altro servizio? Se sì, rifattorizza utilizzando chiamate API HTTP/gRPC.
2. **Sicurezza delle Transazioni Distribuite:** Se una fase di pagamento della creazione dell'ordine fallisce, disponi di un trigger di compensazione che annulla l'ordine nel database?
3. **Isolamento delle Proiezioni CQRS:** Il database delle proiezioni di lettura CQRS risiede su una replica di lettura separata o su un datastore specializzato, oppure esegui ancora lente query JOIN sul database di scrittura?
4. **Wrapper per la Resilienza:** Hai incapsulato le tue chiamate API esterne in un pattern Circuit Breaker per prevenire fallimenti di rete a cascata?

---

<a id="summary"></a>
## Riepilogo

I pattern architetturali per microservizi consentono di ottenere un'elevata scalabilità e tolleranza ai guasti. Mantieni la proprietà esclusiva dei dati con **Database-per-Service**. Evita lock globali utilizzando i **pattern Saga** per gestire le transazioni con consistenza eventuale. Scala il traffico di lettura con **CQRS** e traccia la cronologia con **Event Sourcing**. Proteggi i servizi da interruzioni a cascata utilizzando i **Circuit Breaker**. Mappa le istanze dinamicamente tramite i registri di **Service Discovery**.

---

<a id="self-test-quiz"></a>
## Quiz di Autovalutazione

### Domanda 1: Qual è lo scopo principale delle transazioni di compensazione nel pattern Saga?
- A) Ottimizzare i piani di esecuzione SQL sui server PostgreSQL.
- B) Annullare gli effetti delle transazioni locali precedentemente completate nella sequenza quando una fase successiva della Saga fallisce.
- C) Crittografare i payload degli eventi prima di inviarli tramite flussi Redis.

<details>
<summary>Clicca per vedere la risposta</summary>

**Risposta: B**
Poiché i microservizi non condividono le transazioni del database, le Saga raggiungono la consistenza eseguendo azioni compensative in ordine inverso per annullare gli aggiornamenti precedenti se il processo di business fallisce durante l'esecuzione.
</details>

### Domanda 2: In CQRS / Event Sourcing, qual è il ruolo di una "Proiezione" (Projection)?
- A) Rendering di grafici e cruscotti nel pannello di amministrazione.
- B) Calcolare la capacità di archiviazione futura richiesta dai database.
- C) Elaborare cronologicamente il registro degli eventi (append-only) in un modello denormalizzato ottimizzato per la lettura per consentire query rapide.

<details>
<summary>Clicca per vedere la risposta</summary>

**Risposta: C**
Le proiezioni ascoltano il flusso di eventi aggregati (come OrderCreated, OrderShipped) e aggiornano un database di lettura piatto e denormalizzato (como un database di documenti o un database di replica) ottimizzato specificamente per query select rapide e indicizzate.
</details>

### Domanda 3: Cosa succede quando un Circuit Breaker passa allo stato 'Open' (Aperto)?
- A) A tutte le richieste è consentito passare direttamente per verificare lo stato di salute del servizio.
- B) Le richieste vengono immediatamente bloccate e reindirizzate a fallback locali senza chiamare il servizio remoto, risparmiando risorse.
- C) L'applicazione avvia automaticamente nuove istanze Docker tramite Docker Compose.

<details>
<summary>Clicca per vedere la risposta</summary>

**Risposta: B**
Quando un servizio fallisce ripetutamente, il circuit breaker si apre per evitare di bloccare i thread di connessione. Qualsiasi chiamata successiva fallisce immediatamente, saltando completamente la chiamata di rete remota ed eseguendo una routine di fallback.
</details>
