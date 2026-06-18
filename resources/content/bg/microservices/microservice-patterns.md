---
title: "Архитектурни шаблони за микроуслуги: Saga, CQRS, Event Sourcing & Circuit Breaker | DevSense"
description: "Овладейте архитектурата на разпределените системи. Подробен преглед на Database-per-service, шаблона Saga, CQRS, Event Sourcing, Circuit Breakers и Service Discovery в PHP."
published: 2026-06-18
faq:
  - question: "Каква е разликата между Saga Хореография (Choreography) и Saga Оркестрация (Orchestration)?"
    answer: "Saga Хореографията разчита на това услугите да публикуват и да се абонират за събития без централен координатор, което кара услугите да реагират на промените. Saga Оркестрацията използва централен клас оркестратор, който изрично координира всички стъпки и компенсации последователно."
  - question: "Как CQRS подобрява производителността при микроуслугите?"
    answer: "CQRS разделя операциите по четене от тези по запис, което ви позволява да мащабирате базите данни за четене (като Elasticsearch или Redis реплики) независимо от базите данни за запис (като PostgreSQL). Четенето може да използва прости и бързи заявки върху денормализирани изгледи, докато записите изпълняват нормализирани валидационни транзакции."
  - question: "Защо се нуждаем от Circuit Breaker в разпределените системи?"
    answer: "В мрежа от микроуслуги една бавна или неработеща услуга може да изчерпи нишките или връзките в услугите нагоре по веригата (upstream), причинявайки каскаден срив. Патърнът Circuit Breaker незабавно прекратява с грешка заявките към неизправна услуга (Open състояние), предпазвайки стабилността на цялата система."
---

# Архитектурни шаблони за микроуслуги: Saga, CQRS, Event Sourcing & Circuit Breaker

Преминаването от монолит към микроуслуги решава проблемите с мащабирането и автономията на екипите, но въвежда нови предизвикателства, характерни за разпределените системи. Губите ACID транзакциите между отделните бази данни, мрежовото забавяне (latency) се увеличава, а услугите могат да отказват независимо една от друга.

За да изградите устойчиви и производителни системи, трябва да се възползвате от шаблоните за дизайн на микроуслуги. В това ръководство ще анализираме пет основни шаблона (patterns) за микроуслуги, техните предимства и недостатъци, и ще имплементираме реални примери в PHP 8.x, показващи как работят.

**Свързани ръководства:** [Monolith to microservices architecture](monolith-to-microservices-architecture) · [Message queues compared](message-queues-compared) · [Database performance and scaling](database-performance-and-scaling)

## Съдържание

* [Шаблон Database-per-Service](#database-per-service)
* [Шаблон Saga (Разпределени транзакции)](#saga-pattern)
* [CQRS и Event Sourcing](#cqrs-event-sourcing)
* [Circuit Breaker (Устойчивост)](#circuit-breaker)
* [Service Discovery](#service-discovery)
* [Често срещани грешки (Common Mistakes)](#common-mistakes)
* [Контролен списък (Checklist)](#checklist)
* [Резюме (Summary)](#summary)
* [Тест за самоподготовка (Self-Test Quiz)](#self-test-quiz)

---

<a id="database-per-service"></a>
## Шаблон Database-per-Service (Database-per-Service Pattern)

При монолита всички модули споделят една база данни. При микроуслугите споделянето на база данни нарушава границите на услугите, въвежда тясно обвързване на схемите (schema coupling) и създава риск от изчерпване на връзките към базата данни.

Шаблонът **Database-per-Service** гласи, че всяка услуга притежава свои собствени данни. Никое друга услуга няма директен достъп до тази база данни. Вместо това достъпът до данните трябва да става единствено чрез публичния API на услугата.

| Аспект | Споделена база данни (Монолит) | Database-per-Service |
| --- | --- | --- |
| **Свързаност (Coupling)** | Висока (всяка промяна на схемата може да счупи множество модули) | Ниска (услугите капсулират напълно своята схема) |
| **Технология** | Единичен енджин за база данни (напр. само релационна) | Многообразно съхранение / Polyglot persistence (напр. Neo4j за графи, MongoDB за документи) |
| **Транзакции** | ACID транзакции (лесен rollback, външни ключове) | Разпределени транзакции (изисква Sagas, крайна съгласуваност / eventual consistency) |

---

<a id="saga-pattern"></a>
## Шаблон Saga (Разпределени транзакции)

Тъй като базите данни са изолирани, не можете да изпълните стандартна команда `START TRANSACTION` на ниво множество услуги. **Шаблонът Saga** координира поредица от локални транзакции. Всяка локална транзакция актуализира състоянието в базата данни на една услуга и публикува събитие (event).

Ако някоя транзакция се провали (например поради недостатъчно плащане), Saga изпълнява **компенсиращи транзакции** в обратен ред, за да върне обратно (roll back) промените, направени от предходните стъпки.

Има две стратегии за координация:
1. **Хореография (Choreography):** Децентрализирана. Услугите слушат за събития и задействат своите локални действия.
2. **Оркестрация (Orchestration):** Централизирана. Контролер на ниво оркестрираща услуга инструктира участниците какви действия да изпълнят.

### Имплементация на Saga Orchestrator в PHP

Ето централизиран Saga оркестратор, който координира процеса по създаване на поръчка (Order Creation Saga).

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
## CQRS и Event Sourcing

При архитектура с разделени бази данни, заявките за данни от различни услуги изискват скъпи съединения (joins) през HTTP.

**CQRS (Command Query Responsibility Segregation)** разделя операциите, които променят данни (Commands), от операциите, които четат данни (Queries).

**Event Sourcing** отива още по-далеч: вместо да съхранява текущото състояние на даден обект (entity), вие съхранявате всяка промяна на състоянието като хронологичен лог от събития (event log).

Страната за запис (write side) логва събитията (например `OrderCreated`, `PaymentCompleted`). Фонов проектор (projector) слуша за тези събития и изгражда денормализиран модел за четене (напр. в Elasticsearch или плоска таблица в база данни), оптимизиран изцяло за бързи заявки за четене.

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
## Circuit Breaker (Устойчивост)

Разпределените мрежи понякога отказват. Ако Услуга А извиква Услуга Б, а Услуга Б има голямо забавяне или не работи, нишките на Услуга А ще блокират, пренасяйки срива каскадно нагоре по веригата.

Предпазителят **Circuit Breaker** действа като електрически предпазител около отдалечените повиквания. Той проследява грешките и преминава през три състояния:
* **Затворено (Closed):** Нормална работа. Заявките преминават свободно към отдалечената услуга.
* **Отворено (Open):** Отдалечената услуга има проблеми. Заявките се блокират незабавно, връщайки локална резервна стойност (fallback).
* **Полуотворено (Half-Open):** Времето за охлаждане (cooldown period) е изтекло. Изпраща се ограничен брой заявки, за да се провери дали отдалечената услуга е възстановила нормалната си работа.

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
## Откриване на услуги (Service Discovery)

В среди с микроуслуги инстанциите се мащабират динамично нагоре и надолу, което означава, че IP адресите им се променят постоянно. Твърдото кодиране (hardcoding) на URL адреси в конфигурационни файлове е невъзможно.

Патърнът **Service Discovery** предоставя регистър на услугите (service registry, като Consul или Eureka), действащ като разпределен телефонен указател.

* **Регистрация на услуги (Service Registration):** Когато дадена инстанция стартира, тя се свързва с регистъра, за да запише своето име и текущия си IP адрес/порт. Тя изпраща периодични здравни проверки (health checks), за да остане активна в регистъра.
* **Търсене на услуги (Service Lookup):** Когато Услуга А иска да извика Услуга Б, тя прави заявка към регистъра, за да получи списък с активните IP адреси, след което прилага балансиране на натоварването (load-balancing).

---

<a id="common-mistakes"></a>
## Често срещани грешки

1. **Споделени бази данни (Shared Databases):** Стартиране на множество микроуслуги срещу една и съща схема на база данни, което води до скрито свързване на зависимостите и заключвания (locking) в базата данни.
2. **Липса на компенсационни стъпки в Sagas:** Изграждане на Sagas без написване на надеждни компенсиращи действия, което оставя данните в несъгласувано (corrupt) състояние, когато транзакциите се провалят по средата.
3. **Event Sourcing без моментни снимки (Snapshots):** Повторно четене на хиляди исторически събития за изграждане на текущото състояние при всяка заявка, което води до огромен разход на CPU ресурси и натоварване на базата данни. Създавайте периодично моментни снимки (snapshots) на състоянието (напр. на всеки 100 събития).
4. **Игнориране на времената за охлаждане (cooldowns) на Circuit Breaker:** Държане на предпазителя отворен (Open) завинаги или преминаване обратно към затворен (Closed), без първо да се тества с ограничен брой заявки (Half-Open състояние).

---

<a id="checklist"></a>
## Контролен списък (Checklist)

1. **Изолация на данните (Data Isolation):** Има ли ваша услуга достъп директно до таблиците в базата данни на друга услуга? Ако да, рефакторирайте към HTTP/gRPC API повиквания.
2. **Безопасност на разпределените транзакции:** Ако стъпката за плащане при създаване на поръчка се провали, имате ли компенсиращ тригер, който отменя поръчката във вашата база данни?
3. **Изолация на CQRS проекцията:** Работи ли вашата база данни за CQRS проекции за четене върху отделна реплика за четене (read replica) или специализирано хранилище за данни, или все още изпълнявате бавни JOIN заявки към базата данни за запис?
4. **Обвивка за устойчивост (Resilience wrapper):** Обвили ли сте вашите външни API повиквания в шаблона Circuit Breaker, за да предотвратите каскадни мрежови сривове?

---

<a id="summary"></a>
## Резюме

Архитектурните шаблони за микроуслуги изграждат висока мащабируемост и толерантност към грешки. Управлявайте собствените си данни с **Database-per-Service**. Избягвайте глобалното заключване, като използвате **Saga Patterns** за управление на евентуалните транзакции. Мащабирайте трафика за четене с **CQRS** и записвайте историята на промените с **Event Sourcing**. Защитете услугите си от каскадни сривове с **Circuit Breakers**. Свързвайте инстанциите динамично чрез **Service Discovery** регистри.

---

<a id="self-test-quiz"></a>
## Тест за самоподготовка

### Въпрос 1: Каква е основната цел на компенсиращите транзакции в шаблона Saga?
- A) Да се оптимизират плановете за изпълнение на SQL заявки в PostgreSQL сървъри.
- B) Да се анулират ефектите от предварително завършени локални транзакции в поредицата, когато следваща стъпка в Saga се провали.
- C) Да се криптират съдържанията на събитията, преди да бъдат изпратени по Redis потоци.

<details>
<summary>Кликнете, за да видите отговора</summary>

**Отговор: B**
Тъй като микроуслугите не споделят транзакции на ниво база данни, Sagas постигат съгласуваност чрез изпълнение на компенсиращи действия в обратен ред, за да се отменят по-ранни актуализации, ако бизнес процесът се провали по време на изпълнението си.
</details>

### Въпрос 2: В CQRS / Event Sourcing, каква е ролята на „Проекцията“ (Projection)?
- A) Да рендира диаграми и графики на таблото за управление в администраторския панел.
- B) Да изчислява бъдещия капацитет за съхранение, необходим на базите данни.
- C) Да компилира хронологично append-only лога със събития в денормализиран, оптимизиран за четене модел за бързи заявки.

<details>
<summary>Кликнете, за да видите отговора</summary>

**Отговор: C**
Проекциите (projections) слушат потока от събития (като OrderCreated, OrderShipped) и актуализират плоска, денормализирана база данни за четене (като документална база данни или реплика база данни), оптимизирана специално за бързи и лесни за индексиране селектиращи заявки.
</details>

### Въпрос 3: Какво се случва, когато Circuit Breaker премине в състояние „Отворено“ (Open)?
- A) Всички заявки се пропускат директно, за да се провери здравето на услугата.
- B) Заявките се блокират незабавно и се пренасочват към локални резервни варианти (fallbacks), без да се извиква отдалечената услуга, спестявайки ресурси.
- C) Приложението автоматично стартира нови Docker инстанции чрез Docker Compose.

<details>
<summary>Кликнете, за да видите отговора</summary>

**Отговор: B**
Когато дадена услуга се проваля многократно, предпазителят се отваря (opens), за да се предотврати блокиране на нишките за връзка. Всяко следващо повикване се проваля незабавно, заобикаляйки изцяло отдалеченото мрежово повикване и изпълнявайки резервна рутина (fallback).
</details>
