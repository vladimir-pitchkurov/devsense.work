---
title: "Архітектурні патерни мікросервісів: Saga, CQRS, Event Sourcing та Circuit Breaker | DevSense"
description: "Опануйте архітектуру розподілених систем. Детальний аналіз патернів Database-per-service, Saga, CQRS, Event Sourcing, Circuit Breaker та Service Discovery у PHP."
published: 2026-06-18
faq:
  - question: "Яка різниця між хореографією (Saga Choreography) та оркестрацією (Saga Orchestration)?"
    answer: "Хореографія Saga покладається на те, що сервіси публікують події та підписуються на них без центрального координатора, змушуючи сервіси самостійно реагувати на зміни. Оркестрація Saga використовує центральний клас-оркестратор, який явно та послідовно координує всі кроки та компенсації."
  - question: "Як CQRS покращує продуктивність у мікросервісах?"
    answer: "CQRS розділяє читання та запис, дозволяючи масштабувати бази даних читання (наприклад, Elasticsearch або репліки Redis) незалежно від баз даних запису (наприклад, PostgreSQL). Запити на читання можуть використовувати прості та швидкі запити на денормалізованих представленнях, тоді як операції запису виконують нормалізовані транзакції валідації."
  - question: "Навіщо потрібен Circuit Breaker у розподілених системах?"
    answer: "У мережі мікросервісів один повільний або непрацюючий сервіс може вичерпати потоки або з'єднання у висхідних (upstream) сервісах, викликаючи каскадний збій. Circuit Breaker негайно припиняє виконання запитів до збійного сервісу (стан Open), захищаючи стабільність системи в цілому."
---

# Архітектурні патерни мікросервісів: Saga, CQRS, Event Sourcing та Circuit Breaker

Перехід від моноліту до мікросервісів вирішує проблеми масштабування та автономії команд, але створює нові виклики, характерні для розподілених систем. Ви втрачаєте транзакції ACID між різними базами даних, зростає затримка мережі, а сервіси можуть виходити з ладу незалежно один від одного.

Щоб створювати стійкі та продуктивні системи, необхідно використовувати патерни проєктування мікросервісів. У цьому посібнику ми проаналізуємо п'ять основних патернів мікросервісів, їхні компроміси та впровадимо практичні приклади на PHP 8.x, які демонструють їхню роботу.

**Пов'язані посібники:** [Перехід від монолітної до мікросервісної архітектури](monolith-to-microservices-architecture) · [Порівняння черг повідомлень](message-queues-compared) · [Продуктивність та масштабування баз даних](database-performance-and-scaling)

## Зміст

* [Патерн «База даних на сервіс» (Database-per-Service)](#database-per-service)
* [Патерн Saga (Розподілені транзакції)](#saga-pattern)
* [CQRS та Event Sourcing](#cqrs-event-sourcing)
* [Circuit Breaker (Стійкість до збоїв)](#circuit-breaker)
* [Service Discovery](#service-discovery)
* [Типові помилки](#common-mistakes)
* [Контрольний список (Checklist)](#checklist)
* [Резюме](#summary)
* [Тест для самоперевірки](#self-test-quiz)

---

<a id="database-per-service"></a>
## Патерн «База даних на сервіс» (Database-per-Service)

У моноліті всі модулі використовують одну спільну базу даних. У мікросервісах спільна база даних порушує межі сервісів, створює жорстке зв'язування схем даних та призводить до вичерпання пулу з'єднань із базою даних. 

Патерн **«База даних на сервіс» (Database-per-Service)** вимагає, щоб кожен сервіс володів власними даними. Жоден інший сервіс не може отримати доступ до цієї бази даних напряму. Натомість доступ до даних має здійснюватися виключно через публічний API сервісу.

| Аспект | Спільна база даних (Моноліт) | База даних на сервіс |
| --- | --- | --- |
| **Зв'язність (Coupling)** | Висока (будь-яка зміна схеми ламає кілька модулів) | Низька (сервіси повністю інкапсулюють свою схему) |
| **Технологія** | Один двигун БД (наприклад, лише реляційний) | Багатомовне зберігання / Polyglot persistence (наприклад, Neo4j для графів, MongoDB для документів) |
| **Транзакції** | Транзакції ACID (простий відкат, зовнішні ключі) | Розподілені транзакції (вимагає патерну Saga, узгодженість у кінцевому підсумку / eventual consistency) |

---

<a id="saga-pattern"></a>
## Патерн Saga (Розподілені транзакції)

Оскільки бази даних ізольовані, ви не можете запустити стандартну команду `START TRANSACTION` для кількох сервісів одночасно. **Патерн Saga** координує послідовність локальних транзакцій. Кожна транзакція оновлює стан бази даних у межах одного сервісу та публікує подію.

Якщо одна з транзакцій зазнає невдачі (наприклад, недостатньо коштів), Saga виконує **компенсувальні транзакції** у зворотному порядку для відкату змін, зроблених попередніми кроками.

Існує дві стратегії координації:
1. **Хореографія (Choreography):** Децентралізована. Сервіси слухають події та запускають власні локальні дії.
2. **Оркестрація (Orchestration):** Централізована. Сервіс-контролер (оркестратор) вказує учасникам, які дії слід виконувати.

### Реалізація оркестратора Saga на PHP

Ось централізований оркестратор Saga, який координує Saga створення замовлення.

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
## CQRS та Event Sourcing

У децентралізованій архітектурі баз даних запити даних між сервісами вимагають високовитратних об'єднань (joins) через HTTP. 

**CQRS (Command Query Responsibility Segregation — розділення відповідальності за команди та запити)** розділяє операції, що змінюють дані (команди), та операції, які читають дані (запити). 
**Event Sourcing (джерело подій)** йде далі: замість збереження поточного стану сутності ви зберігаєте кожен перехід стану у вигляді хронологічного журналу подій (event log).

Сторона запису реєструє події (наприклад, `OrderCreated`, `PaymentCompleted`). Фоновий проектор (projector) слухає ці події та створює денормалізовану модель читання (наприклад, в Elasticsearch або пласкій таблиці бази даних), оптимізовану виключно для швидких запитів на читання.

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
## Circuit Breaker (Стійкість до збоїв)

Розподілені мережі схильні до збоїв. Якщо сервіс А викликає сервіс Б, а сервіс Б працює із затримками або взагалі недоступний, потоки сервісу А будуть заблоковані, викликаючи лавиноподібний каскадний збій системи.

**Circuit Breaker (запобіжник)** діє як електричний запобіжник-обгортка навколо віддалених викликів. Він відстежує помилки та проходить через три стани:
* **Closed (Закритий):** Нормальний режим роботи. Запити проходять до віддаленого сервісу.
* **Open (Відкритий):** Віддалений сервіс не відповідає. Запити блокуються негайно, повертаючи локальне резервне значення (fallback).
* **Half-Open (Напіввідкритий):** Період очікування (охолодження) минув. Надсилається обмежена кількість запитів для перевірки, чи відновив віддалений сервіс свою роботу.

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
## Service Discovery (Виявлення сервісів)

У мікросервісних середовищах інстанси динамічно масштабуються вгору та вниз, а це означає, що їхні IP-адреси постійно змінюються. Жорстке прописування (hardcoding) URL-адрес у конфігураційних файлах стає неможливим.

**Service Discovery (Виявлення сервісів)** використовує реєстр сервісів (наприклад, Consul, Eureka), який виконує роль розподіленої телефонної книги.
* **Реєстрація сервісів (Service Registration):** Під час запуску екземпляра (інстансу) сервісу він викликає реєстр, щоб записати свою назву та поточний IP-адрес/порт. Він надсилає періодичні перевірки працездатності (health checks), щоб залишатися в реєстрі.
* **Пошук сервісів (Service Lookup):** Коли сервіс А хоче викликати сервіс Б, він робить запит до реєстру для отримання списку активних IP-адрес, після чого застосовує балансування навантаження.

---

<a id="common-mistakes"></a>
## Типові помилки

1. **Спільні бази даних:** використання кількома мікросервісами однієї схеми бази даних, що призводить до прихованого зв'язування залежностей та блокування БД.
2. **Відсутність кроків компенсації в Sagas:** розробка Sagas без написання надійних компенсувальних дій, що залишає дані в суперечливому стані в разі збою транзакцій на півшляху.
3. **Event Sourcing без знімків (Snapshots):** повторне зчитування тисяч історичних подій для побудови поточного стану при кожному запиті, що веде до величезного навантаження на CPU та БД. Створюйте знімки стану періодично (наприклад, кожні 100 подій).
4. **Ігнорування періодів охолодження Circuit Breaker:** утримання запобіжника у відкритому стані (Open) назавжди або повернення до закритого стану без попередньої перевірки обмеженою кількістю запитів (стан Half-Open).

---

<a id="checklist"></a>
## Контрольний список (Checklist)

1. **Ізоляція даних:** Чи звертається ваш сервіс безпосередньо до таблиць бази даних іншого сервісу? Якщо так, виконайте рефакторинг на виклики через HTTP/gRPC API.
2. **Безпека розподілених транзакцій:** Якщо крок оплати при створенні замовлення зазнає невдачі, чи є у вас компенсувальний тригер, який скасовує замовлення у вашій базі даних?
3. **Ізоляція проекцій CQRS:** Чи працює ваша база даних проекції читання CQRS на окремій репліці читання чи спеціалізованому сховищі даних, чи ви все ще виконуєте повільні JOIN-запити до бази даних запису?
4. **Обгортка для стійкості:** Чи загорнули ви свої зовнішні виклики API у патерн Circuit Breaker, щоб запобігти каскадним мережевим збоям?

---

<a id="summary"></a>
## Резюме

Архітектурні патерни мікросервісів забезпечують високу масштабованість та відмовостійкість додатка. Володійте своїми даними завдяки патерну **«База даних на сервіс» (Database-per-Service)**. Уникайте глобальних блокувань за допомогою **патерну Saga** для керування розподіленими транзакціями. Масштабуйте трафік читання за допомогою **CQRS** та записуйте історію змін з **Event Sourcing**. Захищайте свої сервіси від каскадних збоїв за допомогою **Circuit Breaker**. Динамічно знаходьте екземпляри сервісів через реєстри **Service Discovery**.

---

<a id="self-test-quiz"></a>
## Тест для самоперевірки

### Запитання 1: Яке основне призначення компенсувальних транзакцій у патерні Saga?
- A) Оптимізувати плани виконання SQL на серверах PostgreSQL.
- B) Скасувати ефекти раніше завершених локальних транзакцій у послідовності, якщо наступний крок у Saga зазнає невдачі.
- C) Шифрувати корисне навантаження подій перед відправленням у потоки Redis.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: B**
Оскільки мікросервіси не мають спільних транзакцій бази даних, консистентність у Sagas забезпечується шляхом виконання компенсувальних дій у зворотному порядку для скасування попередніх оновлень, якщо бізнес-процес завершився збоєм під час виконання.
</details>

### Запитання 2: Яка роль «Проекції» (Projection) у CQRS / Event Sourcing?
- A) Рендерити діаграми та графіки панелі інструментів в адмін-панелі.
- B) Розраховувати майбутній обсяг сховища, необхідний для баз даних.
- C) Хронологічно компілювати журнал подій типу append-only у денормалізовану, оптимізовану для читання модель для швидкого виконання запитів.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: C**
Проекції слухають потік агрегованих подій (таких як OrderCreated, OrderShipped) і оновлюють пласку, денормалізовану базу даних читання (наприклад, документну БД чи базу-репліку), яка оновлена спеціально для швидких і дружніх до індексів SELECT-запитів.
</details>

### Запитання 3: Що відбувається, коли Circuit Breaker переходить у стан «Open» (Відкритий)?
- A) Усі запити пропускаються безпосередньо для перевірки працездатності сервісу.
- B) Запити блокуються негайно і перенаправляються на локальні резервні обробники (fallbacks) без виклику віддаленого сервісу, що заощаджує ресурси.
- C) Додаток автоматично запускає нові Docker-контейнери за допомогою Docker Compose.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: B**
Коли сервіс постійно дає збій, запобіжник відкриється, щоб запобігти блокуванню потоків з'єднання. Усі наступні виклики завершуються помилкою негайно, повністю оминаючи виклик віддаленої мережі та виконуючи резервну процедуру (fallback).
</details>
