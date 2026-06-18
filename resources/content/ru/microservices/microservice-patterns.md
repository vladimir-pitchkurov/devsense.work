---
title: "Архитектурные паттерны микросервисов: Saga, CQRS, Event Sourcing и Circuit Breaker | DevSense"
description: "Освойте архитектуру распределенных систем. Подробный разбор Database-per-service, паттерна Saga, CQRS, Event Sourcing, Circuit Breakers и Service Discovery на PHP."
published: 2026-06-18
faq:
  - question: "В чем разница между Saga Choreography (хореографией) и Saga Orchestration (оркестрацией)?"
    answer: "Хореография Saga (Saga Choreography) полагается на публикацию событий сервисами и подписку на них без центрального координатора, из-за чего сервисы реагируют на изменения. Оркестрация Saga (Saga Orchestration) использует центральный класс оркестратора, который явно и последовательно координирует все шаги и компенсирующие действия."
  - question: "Как CQRS повышает производительность в микросервисах?"
    answer: "CQRS разделяет операции чтения и записи, позволяя независимо масштабировать базы данных для чтения (например, Elasticsearch или реплики чтения Redis) от баз данных для записи (например, PostgreSQL). Чтение может использовать простые, быстрые запросы к денормализованным представлениям, в то время как запись выполняет нормализованные транзакции с валидацией."
  - question: "Зачем нужен Circuit Breaker в распределенных системах?"
    answer: "В микросервисной сети один медленный или неработающий сервис может исчерпать потоки или соединения в вышестоящих сервисах, вызвав каскадный сбой. Паттерн Circuit Breaker («Предохранитель») немедленно возвращает ошибку при запросах к неисправному сервису (состояние Open), защищая стабильность системы в целом."
---

# Архитектурные паттерны микросервисов: Saga, CQRS, Event Sourcing и Circuit Breaker

Переход от монолита к микросервисам решает проблемы масштабирования и автономности команд, но привносит новые сложности распределенных систем. Вы теряете транзакции ACID между базами данных, увеличивается сетевая задержка, а сервисы могут выходить из строя независимо друг от друга.

Чтобы создавать отказоустойчивые и производительные системы, необходимо использовать паттерны проектирования микросервисов. В этом руководстве мы разберем пять основных паттернов микросервисной архитектуры, их компромиссы, а также реализуем практические примеры на PHP 8.x, демонстрирующие их работу.

**Сопутствующие руководства:** [Архитектура перехода от монолита к микросервисам](monolith-to-microservices-architecture) · [Сравнение очередей сообщений](message-queues-compared) · [Производительность и масштабирование баз данных](database-performance-and-scaling)

## Содержание

* [Паттерн Database-per-Service](#database-per-service)
* [Паттерн Saga (распределенные транзакции)](#saga-pattern)
* [CQRS и Event Sourcing](#cqrs-event-sourcing)
* [Circuit Breaker (устойчивость к сбоям)](#circuit-breaker)
* [Service Discovery](#service-discovery)
* [Частые ошибки](#common-mistakes)
* [Чек-лист](#checklist)
* [Заключение](#summary)
* [Тест для самопроверки](#self-test-quiz)

---

<a id="database-per-service"></a>
## Паттерн Database-per-Service

В монолите все модули используют одну общую базу данных. В микросервисах совместное использование базы данных нарушает границы сервисов, приводит к сильной связанности схем данных и вызывает исчерпание пула подключений к БД.

Паттерн **Database-per-Service (База данных на сервис)** предписывает, что каждый сервис владеет собственными данными. Ни один другой сервис не может обращаться к этой базе данных напрямую. Вместо этого доступ к данным должен осуществляться исключительно через публичный API соответствующего сервиса.

| Аспект | Общая база данных (монолит) | Database-per-Service (микросервисы) |
| --- | --- | --- |
| **Связанность** | Высокая (любое изменение схемы ломает несколько модулей) | Низкая (сервисы полностью инкапсулируют свою схему) |
| **Технологии** | Единая СУБД (например, только реляционная) | Многовариантное хранение / Polyglot persistence (например, Neo4j для графов, MongoDB для документов) |
| **Транзакции** | ACID-транзакции (простой откат, внешние ключи) | Распределенные транзакции (требуется паттерн Saga, согласованность в конечном счете) |

---

<a id="saga-pattern"></a>
## Паттерн Saga (распределенные транзакции)

Поскольку базы данных изолированы, вы не можете выполнить стандартный запрос `START TRANSACTION` сразу для нескольких сервисов. **Паттерн Saga** координирует последовательность локальных транзакций. Каждая транзакция обновляет состояние базы данных внутри одного сервиса и публикует событие.

Если одна из транзакций завершается сбоем (например, из-за недостатка средств при оплате), Saga выполняет **компенсирующие транзакции** в обратном порядке для отката изменений, сделанных на предыдущих шагах.

Существует две стратегии координации:
1. **Хореография (Choreography):** Децентрализованная. Сервисы слушают события и запускают свои локальные действия.
2. **Оркестрация (Orchestration):** Централизованная. Сервис-контроллер (оркестратор) дает указания участникам, какие действия выполнять.

### Реализация оркестратора Saga на PHP

Ниже представлена реализация централизованного оркестратора Saga, координирующего процесс создания заказа.

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

В архитектуре с изолированными базами данных выборка данных из нескольких сервисов требует дорогостоящих HTTP-запросов (HTTP joins).

**CQRS (Command Query Responsibility Segregation — разделение ответственности на команды и запросы)** разделяет операции изменения данных (Команды) от операций чтения данных (Запросы).

**Event Sourcing (Внедрение событий)** идет еще дальше: вместо хранения текущего состояния сущности вы сохраняете каждое изменение состояния в виде хронологического журнала событий.

Сторона записи логирует события (например, `OrderCreated`, `PaymentCompleted`). Фоновый проектор (projector) слушает эти события и выстраивает денормализованную модель для чтения (например, в Elasticsearch или плоской таблице базы данных), которая оптимизирована исключительно под быстрые запросы на чтение.

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
## Circuit Breaker (устойчивость к сбоям)

Распределенные сети подвержены сбоям. Если Сервис A вызывает Сервис B, а Сервис B работает с задержками или недоступен, потоки выполнения Сервиса A заблокируются, что приведет к каскадному сбою всей системы.

Паттерн **Circuit Breaker (Предохранитель)** работает как электрический предохранитель вокруг удаленных вызовов. Он отслеживает сбои и переключается между тремя состояниями:
* **Closed (Закрыт):** Обычный режим работы. Запросы направляются к удаленному сервису.
* **Open (Открыт):** Удаленный сервис недоступен. Запросы блокируются сразу же, возвращая локальное резервное значение (fallback).
* **Half-Open (Полуоткрыт):** Период охлаждения истек. Отправляется ограниченное количество запросов, чтобы проверить, восстановилась ли работоспособность удаленного сервиса.

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

В микросервисной среде инстансы динамически масштабируются вверх и вниз, что означает постоянную смену IP-адресов. Прописывать URL-адреса вручную (hardcoding) в конфигурационных файлах невозможно.

Технология **Service Discovery (Обнаружение сервисов)** предоставляет реестр сервисов (например, Consul, Eureka), действующий как распределенная телефонная книга.
* **Регистрация сервиса (Service Registration):** При запуске инстанса он отправляет запрос в реестр, чтобы записать свое имя и текущий IP/порт. Он периодически отправляет проверки работоспособности (health checks), чтобы оставаться в реестре.
* **Поиск сервиса (Service Lookup):** Когда Сервис A хочет вызвать Сервис B, он опрашивает реестр для получения списка активных IP-адресов, а затем применяет балансировку нагрузки.

---

<a id="common-mistakes"></a>
## Частые ошибки

1. **Общие базы данных:** Работа нескольких микросервисов с одной и той же схемой базы данных, что ведет к скрытой связанности зависимостей и блокировкам БД.
2. **Отсутствие компенсирующих шагов в Sagas:** Проектирование Saga без написания надежных компенсирующих действий, что оставляет данные в некорректном состоянии при сбоях транзакций на полпути.
3. **Event Sourcing без снимков состояния (Snapshots):** Чтение тысяч исторических событий для построения текущего состояния при каждом запросе, что создает колоссальную нагрузку на процессор и базу данных. Периодически создавайте снимки состояния (например, каждые 100 событий).
4. **Игнорирование времени охлаждения Circuit Breaker:** Удержание предохранителя в открытом состоянии навсегда или возвращение в закрытое состояние без предварительной проверки ограниченным количеством запросов (состояние Half-Open).

---

<a id="checklist"></a>
## Чек-лист

1. **Изоляция данных:** Обращается ли ваш сервис к таблицам БД другого сервиса напрямую? Если да, отрефакторите код на вызовы HTTP/gRPC API.
2. **Безопасность распределенных транзакций:** Если этап оплаты при создании заказа завершился сбоем, есть ли у вас триггер компенсации, который отменяет заказ в вашей базе данных?
3. **Изоляция проекции CQRS:** Работает ли ваша база данных проекций чтения CQRS на отдельной реплике чтения или специализированном хранилище данных, или вы все еще выполняете медленные запросы JOIN к основной базе данных записи?
4. **Обертка отказоустойчивости:** Обернули ли вы внешние вызовы API с использованием паттерна Circuit Breaker для предотвращения каскадных сетевых сбоев?

---

<a id="summary"></a>
## Заключение

Архитектурные паттерны микросервисов обеспечивают высокую масштабируемость и отказоустойчивость. Владейте своими данными с помощью **Database-per-Service**. Избегайте глобальных блокировок, используя **Saga** для управления согласованностью распределенных транзакций. Масштабируйте трафик чтения с помощью **CQRS** и сохраняйте историю изменений через **Event Sourcing**. Защищайте свои сервисы от каскадных сбоев с помощью **Circuit Breaker**. Составляйте динамическую карту запущенных инстансов с помощью реестров **Service Discovery**.

---

<a id="self-test-quiz"></a>
## Тест для самопроверки

### Вопрос 1: Какова основная цель компенсирующих транзакций в паттерне Saga?
- A) Оптимизация планов выполнения SQL на серверах PostgreSQL.
- B) Отмена результатов ранее успешно завершенных локальных транзакций в последовательности, если последующий шаг в Saga завершился сбоем.
- C) Шифрование полезной нагрузки событий перед отправкой в потоки Redis.

<details>
<summary>Нажмите, чтобы увидеть ответ</summary>

**Ответ: B**
Поскольку микросервисы не используют общие транзакции баз данных, Saga обеспечивает согласованность за счет выполнения компенсирующих действий в обратном порядке для отмены предыдущих обновлений, если бизнес-процесс завершается сбоем на полпути.
</details>

### Вопрос 2: Какова роль «проекции» (Projection) в CQRS / Event Sourcing?
- A) Рендеринг графиков и диаграмм на панели администратора.
- B) Расчет необходимого объема хранилища базы данных в будущем.
- C) Составление из хронологического журнала событий (доступного только для добавления) денормализованной модели, оптимизированной для быстрого выполнения запросов чтения.

<details>
<summary>Нажмите, чтобы увидеть ответ</summary>

**Ответ: C**
Проекции слушают поток событий агрегата (таких как OrderCreated, OrderShipped) и обновляют плоскую денормализованную базу данных для чтения (например, документную БД или реплику БД), оптимизированную специально для быстрых поисковых SELECT-запросов с использованием индексов.
</details>

### Вопрос 3: Что происходит, когда Circuit Breaker переходит в состояние «Open» (Открыт)?
- A) Разрешается прямое прохождение всех запросов для проверки работоспособности сервиса.
- B) Запросы немедленно блокируются и перенаправляются на локальные резервные сценарии (fallbacks) без вызова удаленного сервиса, сохраняя ресурсы.
- C) Приложение автоматически запускает новые контейнеры Docker с помощью Docker Compose.

<details>
<summary>Нажмите, чтобы увидеть ответ</summary>

**Ответ: B**
Когда сервис многократно выходит из строя, предохранитель открывается, чтобы предотвратить блокировку потоков соединений. Все последующие вызовы немедленно завершаются ошибкой в обход удаленного сетевого вызова, запуская резервный сценарий.
</details>
