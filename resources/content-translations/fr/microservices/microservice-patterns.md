---
title: "Patrons d'architecture de microservices : Saga, CQRS, Event Sourcing & Coupe-circuit | DevSense"
description: "Maîtrisez l'architecture des systèmes distribués. Plongez au cœur des concepts de base de données par service, du patron Saga, de CQRS, de l'Event Sourcing, des disjoncteurs (Circuit Breakers) et de la découverte de services en PHP."
published: 2026-06-18
faq:
  - question: "Quelle est la différence entre la chorégraphie et l'orchestration dans le patron Saga ?"
    answer: "La chorégraphie Saga (Saga Choreography) repose sur la publication et l'abonnement à des événements par les services sans coordinateur central, ce qui pousse les services à réagir aux changements. L'orchestration Saga (Saga Orchestration) utilise une classe d'orchestration centrale qui coordonne explicitement et séquentiellement toutes les étapes et compensations."
  - question: "Comment CQRS améliore-t-il les performances dans les microservices ?"
    answer: "CQRS découple les lectures des écritures, vous permettant de mettre à l'échelle les bases de données de lecture (comme Elasticsearch ou les réplicas de lecture Redis) indépendamment des bases de données d'écriture (comme PostgreSQL). Les lectures peuvent utiliser des requêtes simples et rapides sur des vues dénormalisées, tandis que les écritures exécutent des transactions de validation normalisées."
  - question: "Pourquoi avons-nous besoin d'un disjoncteur (Circuit Breaker) dans les systèmes distribués ?"
    answer: "Dans un réseau de microservices, un seul service lent ou en panne peut épuiser les threads ou les connexions des services en amont, provoquant une panne en cascade. Un disjoncteur échoue immédiatement les requêtes adressées à un service défaillant (état Ouvert / Open), protégeant ainsi la stabilité globale du système."
---

# Patterns d'architecture de microservices : Saga, CQRS, Event Sourcing & Disjoncteur (Circuit Breaker)

Passer d'un monolithe à des microservices résout les problèmes de mise à l'échelle et d'autonomie des équipes, mais introduit de nouveaux défis propres aux systèmes distribués. Vous perdez la possibilité d'effectuer des transactions ACID entre plusieurs bases de données, la latence réseau augmente et les services peuvent tomber en panne indépendamment.

Pour concevoir des systèmes résilients et performants, vous devez tirer parti des patrons de conception de microservices. Dans ce guide, nous analyserons cinq patterns clés de microservices, leurs compromis, et implémenterons des exemples concrets en PHP 8.x pour illustrer leur fonctionnement.

**Guides connexes :** [Architecture du monolithe aux microservices](monolith-to-microservices-architecture) · [Comparatif des files d'attente de messages](message-queues-compared) · [Performance et mise à l'échelle des bases de données](database-performance-and-scaling)

## Table des matières

* [Le patron Base de données par service (Database-per-Service)](#database-per-service)
* [Le patron Saga (Transactions distribuées)](#saga-pattern)
* [CQRS & Event Sourcing](#cqrs-event-sourcing)
* [Le disjoncteur (Circuit Breaker - Résilience)](#circuit-breaker)
* [La découverte de services (Service Discovery)](#service-discovery)
* [Erreurs courantes](#common-mistakes)
* [Aide-mémoire / Checklist](#checklist)
* [Résumé](#summary)
* [Quiz d'auto-évaluation](#self-test-quiz)

---

<a id="database-per-service"></a>
## Le patron Base de données par service (Database-per-Service)

Dans un monolithe, tous les modules partagent une unique base de données. Dans une architecture de microservices, le partage d'une base de données brise les frontières des services, introduit un couplage fort des schémas et peut entraîner l'épuisement des connexions à la base de données.

Le patron **Base de données par service** (Database-per-Service) stipule que chaque service possède ses propres données. Aucun autre service ne peut accéder directement à cette base de données. Les données doivent être accédées exclusivement via l'API publique du service concerné.

| Aspect | Base de données partagée (Monolithe) | Base de données par service |
| --- | --- | --- |
| **Couplage** | Fort (chaque modification de schéma brise plusieurs modules) | Faible (les services encapsulent complètement leur schéma) |
| **Technologie** | Moteur de base de données unique (ex. relationnel uniquement) | Persistance polyglotte (ex. Neo4j pour les graphes, MongoDB pour les documents) |
| **Transactions** | Transactions ACID (restauration/rollback facile, clés étrangères) | Transactions distribuées (nécessite des Sagas, cohérence à terme) |

---

<a id="saga-pattern"></a>
## Le patron Saga (Transactions distribuées)

Puisque les bases de données sont isolées, vous ne pouvez pas lancer un simple `START TRANSACTION` sur plusieurs services. Le **patron Saga** (Saga Pattern) coordonne une séquence de transactions locales. Chaque transaction met à jour l'état de la base de données au sein d'un seul service et publie un événement.

Si une transaction échoue (par exemple, en raison d'un paiement insuffisant), la Saga exécute des **transactions de compensation** (compensating transactions) dans l'ordre inverse pour annuler les modifications apportées par les étapes précédentes.

Il existe deux stratégies de coordination :
1. **La chorégraphie (Choreography) :** Décentralisée. Les services écoutent les événements et déclenchent leurs propres actions locales.
2. **L'orchestration (Orchestration) :** Centralisée. Un service contrôleur (orchestrateur) indique aux participants quelles actions exécuter.

### Implémentation d'un orchestrateur Saga en PHP

Voici un orchestrateur Saga centralisé coordonnant une Saga de création de commande (Order Creation Saga).

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

Dans une architecture à bases de données découplées, la requête de données entre différents services nécessite des liaisons (joins) HTTP coûteuses.

**CQRS (Command Query Responsibility Segregation)** sépare les opérations qui modifient les données (Commandes) des opérations qui lisent les données (Requêtes).
**L'Event Sourcing** va encore plus loin : au lieu de stocker l'état actuel d'une entité, vous stockez chaque transition d'état sous la forme d'un journal chronologique d'événements (event log).

Le côté écriture (write side) enregistre les événements (par exemple, `OrderCreated`, `PaymentCompleted`). Un projecteur en arrière-plan écoute ces événements et construit un modèle de lecture dénormalisé (par exemple, dans Elasticsearch ou une table de base de données plate) optimisé uniquement pour des requêtes de lecture rapides.

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
## Le disjoncteur (Circuit Breaker - Résilience)

Les réseaux distribués finissent par faillir. Si le Service A appelle le Service B et que le Service B subit de la latence ou est en panne, les threads du Service A vont se bloquer, propageant la panne vers l'amont.

Un **disjoncteur** (Circuit Breaker) agit comme un fusible électrique enveloppant les appels distants. Il suit les échecs et bascule à travers trois états :
* **Fermé (Closed) :** Fonctionnement normal. Les requêtes sont transmises au service distant.
* **Ouvert (Open) :** Le service distant est défaillant. Les requêtes sont bloquées immédiatement et renvoient une valeur de secours locale (fallback).
* **À moitié ouvert (Half-Open) :** La période de refroidissement a expiré. Un nombre limité de requêtes est envoyé pour vérifier si le service distant est à nouveau sain.

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
## La découverte de services (Service Discovery)

Dans les environnements de microservices, les instances se mettent à l'échelle dynamiquement (scale up/down), ce qui signifie que leurs adresses IP changent constamment. Il est impossible d'écrire des URL en dur (hardcoding) dans les fichiers de configuration.

La **découverte de services** (Service Discovery) fournit un registre de services (par exemple, Consul ou Eureka) qui agit comme un annuaire téléphonique distribué.
* **L'enregistrement du service (Service Registration) :** Lorsqu'une instance démarre, elle appelle le registre pour enregistrer son nom et son IP/port actuel. Elle envoie des bilans de santé périodiques (health checks) pour rester active dans le registre.
* **La recherche de service (Service Lookup) :** Lorsque le Service A souhaite appeler le Service B, il interroge le registre pour obtenir une liste des adresses IP actives, puis applique un équilibrage de charge (load-balancing).

---

<a id="common-mistakes"></a>
## Erreurs courantes

1. **Bases de données partagées (Shared Databases) :** Faire fonctionner plusieurs microservices sur le même schéma de base de données, ce qui crée un couplage de dépendance caché et des verrous de base de données.
2. **Absence d'étapes de compensation dans les Sagas :** Construire des Sagas sans écrire d'actions de compensation robustes, laissant ainsi les données dans un état corrompu lorsque les transactions échouent à mi-parcours.
3. **Event Sourcing sans instantanés (Snapshots) :** Relire des milliers d'événements historiques pour reconstruire l'état actuel à chaque requête, entraînant une surcharge massive du processeur et de la base de données. Créez périodiquement des instantanés d'état (par exemple, tous les 100 événements).
4. **Ignorer les périodes de refroidissement du disjoncteur :** Garder le disjoncteur ouvert indéfiniment ou le repasser à l'état fermé sans tester au préalable avec un nombre limité de requêtes (état À moitié ouvert / Half-Open).

---

<a id="checklist"></a>
## Aide-mémoire / Checklist

1. **Isolation des données :** Votre service accède-t-il directement aux tables de base de données d'un autre service ? Si oui, refactorisez vers des appels d'API HTTP/gRPC.
2. **Sécurité des transactions distribuées :** Si l'étape de paiement de la création d'une commande échoue, disposez-vous d'un déclencheur de compensation qui annule la commande dans votre base de données ?
3. **Isolation des projections CQRS :** Votre base de données de projection de lecture CQRS s'exécute-t-elle sur un réplica de lecture distinct ou sur un magasin de données spécialisé, ou exécutez-vous toujours des requêtes JOIN lentes sur la base de données d'écriture ?
4. **Enveloppe de résilience :** Avez-vous enveloppé vos appels d'API externes dans un patron Circuit Breaker pour éviter les pannes réseau en cascade ?

---

<a id="summary"></a>
## Résumé

Les patrons d'architecture de microservices apportent une grande évolutivité (scalability) et une forte tolérance aux pannes. Restez maître de vos données grâce à **Base de données par service** (Database-per-Service). Évitez les verrous globaux à l'aide des **patrons Saga** pour gérer les transactions cohérentes à terme. Mettez à l'échelle le trafic de lecture avec **CQRS** et enregistrez l'historique avec l'**Event Sourcing**. Protégez vos services contre les pannes en cascade à l'aide de **disjoncteurs** (Circuit Breakers). Cartographiez les instances de manière dynamique à l'aide de registres de **découverte de services** (Service Discovery).

---

<a id="self-test-quiz"></a>
## Quiz d'auto-évaluation

### Question 1 : Quel est l'objectif principal des transactions de compensation dans le patron Saga ?
- A) Optimiser les plans d'exécution SQL sur les serveurs PostgreSQL.
- B) Annuler les effets des transactions locales précédemment terminées dans la séquence lorsqu'une étape ultérieure de la Saga échoue.
- C) Chiffrer les charges utiles (payloads) d'événements avant de les envoyer sur des flux Redis (Redis streams).

<details>
<summary>Cliquez pour voir la réponse</summary>

**Réponse : B**
Étant donné que les microservices ne partagent pas les transactions de base de données, les Sagas assurent la cohérence en exécutant des actions de compensation dans l'ordre inverse pour annuler les mises à jour précédentes si le processus métier échoue en cours d'exécution.
</details>

### Question 2 : Dans le cadre de CQRS / Event Sourcing, quel est le rôle d'une « Projection » ?
- A) Afficher des graphiques et des tableaux de bord dans le panneau d'administration.
- B) Calculer la capacité de stockage future requise par les bases de données.
- C) Compiler le journal des événements en ajout uniquement (append-only event log) de manière chronologique dans un modèle dénormalisé et optimisé pour la lecture afin de permettre des requêtes rapides.

<details>
<summary>Cliquez pour voir la réponse</summary>

**Réponse : C**
Les projections écoutent le flux d'événements agrégés (comme OrderCreated, OrderShipped) et mettent à jour une base de données de lecture plate et dénormalisée (comme une base de données de documents ou une base de données réplica) spécialement optimisée pour des requêtes select rapides et adaptées aux index.
</details>

### Question 3 : Que se passe-t-il lorsqu'un disjoncteur (Circuit Breaker) bascule à l'état « Ouvert » (Open) ?
- A) Toutes les requêtes sont autorisées à passer directement pour vérifier l'état de santé du service.
- B) Les requêtes sont immédiatement bloquées et redirigées vers des solutions de secours locales (fallbacks) sans appeler le service distant, économisant ainsi des ressources.
- C) L'application lance automatiquement de nouvelles instances Docker via Docker Compose.

<details>
<summary>Cliquez pour voir la réponse</summary>

**Réponse : B**
Lorsqu'un service échoue de manière répétée, le disjoncteur s'ouvre pour éviter de bloquer les threads de connexion. Tous les appels suivants échouent immédiatement, contournant complètement l'appel réseau distant et exécutant une routine de secours (fallback).
</details>
