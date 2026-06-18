---
title: "Структурни шаблони GoF: Adapter, Decorator, Facade и Proxy | DevSense"
description: "Подробно ръководство за структурните шаблони за проектиране GoF в PHP: Adapter, Bridge, Composite, Decorator, Facade, Flyweight и Proxy с чисти примери за код."
published: 2026-06-18
faq:
  - question: "Каква е ключовата разлика между шаблоните Adapter (Адаптер) и Decorator (Декоратор)?"
    answer: "Адаптерът променя интерфейса на даден обект, за да го направи съвместим с друг интерфейс, позволявайки на иначе несъвместими класове да си сътрудничат. Декораторът запазва оригиналния интерфейс на обвития обект, като същевременно динамично добавя или разширява неговото поведение."
  - question: "Как шаблонът Facade (Фасад) се различава от шаблона Mediator (Посредник)?"
    answer: "Фасадът предоставя опростен, еднопосочен интерфейс към сложна подсистема, без да ограничава прекия достъп до нейните класове. Посредникът централизира и координира сложна, двупосочна комуникация между множество колеги-обекти, като скрива преките им връзки един с друг."
  - question: "Кога трябва да използвате Proxy (Прокси) вместо Decorator (Декоратор)?"
    answer: "Използвайте Прокси, когато трябва да контролирате достъпа до даден обект (например мързелива инициализация, кеширане, контрол на достъпа), при което самото прокси управлява жизнения цикъл на реалния обект. Използвайте Декоратор, когато искате динамично да добавите отговорности към обект по време на изпълнение, без да управлявате неговия жизнен цикъл."
---

# Структурни шаблони GoF: Adapter, Decorator, Facade и Proxy

Структурните шаблони за проектиране обясняват как да сглобяваме обекти и класове в по-големи структури, като същевременно запазваме тези структури гъвкави и ефективни. Те ни помагат да изграждаме сложни йерархии от класове, като използваме композиция вместо твърдо наследяване.

**Свързани ръководства:** [Преход от монолит към микросервизна архитектура](monolith-to-microservices-architecture) · [Атаки срещу уеб приложения и защита от тях](web-attacks-and-prevention)

## Съдържание

* [Въведение в структурните шаблони](#intro)
* [Шаблонът Adapter (Адаптер)](#adapter)
* [Шаблонът Decorator (Декоратор)](#decorator)
* [Шаблонът Facade (Фасад)](#facade)
* [Шаблонът Proxy (Прокси)](#proxy)
* [Други структурни шаблони (Bridge, Composite, Flyweight)](#others)
* [Типични грешки](#common-mistakes)
* [Чеклист за самопроверка](#checklist)
* [Резюме](#summary)
* [Тест за самопроверка](#self-test-quiz)

---

<a id="intro"></a>
## Въведение в структурните шаблони

Наследяването е мощен инструмент, но то е статично и се определя по време на компилация. Структурните шаблони използват **композиция на обекти**, позволявайки комбиниране на тяхното поведение динамично по време на изпълнение. Това осигурява по-голяма гъвкавост и избягва създаването на твърде дълбоки и твърди йерархии от класове.

---

<a id="adapter"></a>
## Шаблонът Adapter (Адаптер)

Шаблонът **Adapter** позволява на обекти с несъвместими интерфейси да работят заедно. Той действа като обвивка (преводач), която преобразува повикванията от клиентския код във формат, разбираем за външен или наследен (legacy) код.

```php
// app/Logging/LoggerInterface.php
declare(strict_types=1);

namespace App\Logging;

interface LoggerInterface
{
    public function logMessage(string $message): void;
}

// Legacy third-party service that cannot be changed directly
class LegacyLoggerService
{
    public function sendRawLog(string $rawMessage, int $level): void
    {
        // Legacy system logging...
    }
}

// The Adapter class that bridges the two interfaces
class LoggerAdapter implements LoggerInterface
{
    private LegacyLoggerService $legacyLogger;

    public function __construct(LegacyLoggerService $legacyLogger)
    {
        $this->legacyLogger = $legacyLogger;
    }

    public function logMessage(string $message): void
    {
        // Map the call to the legacy service method
        $this->legacyLogger->sendRawLog($message, 1);
    }
}
```

---

<a id="decorator"></a>
## Шаблонът Decorator (Декоратор)

Шаблонът **Decorator** позволява динамично да се добавят нови отговорности към обекти, като се поставят в специални обекти-обвивки.

```php
// app/Repository/ProductRepositoryInterface.php
declare(strict_types=1);

namespace App\Repository;

interface ProductRepositoryInterface
{
    public function findById(int $id): array;
}

class SqlProductRepository implements ProductRepositoryInterface
{
    public function findById(int $id): array
    {
        // Fetch product from SQL database...
        return ["id" => $id, "name" => "Core PHP Book"];
    }
}

// Decorator implementing the same interface
class CachingProductRepositoryDecorator implements ProductRepositoryInterface
{
    private ProductRepositoryInterface $wrapped;
    private array $cache = [];

    public function __construct(ProductRepositoryInterface $wrapped)
    {
        $this->wrapped = $wrapped;
    }

    public function findById(int $id): array
    {
        if (!isset($this->cache[$id])) {
            $this->cache[$id] = $this->wrapped->findById($id);
        }

        return $this->cache[$id];
    }
}
```

---

<a id="facade"></a>
## Шаблонът Facade (Фасад)

Шаблонът **Facade** предоставя прост интерфейс към сложна система от класове, библиотека или рамка (framework).

```php
// app/Shop/OrderFacade.php
declare(strict_types=1);

namespace App\Shop;

class InventoryService { public function check(int $itemId): bool { return true; } }
class PaymentService { public function charge(int $userId, float $amount): bool { return true; } }
class DeliveryService { public function schedule(int $itemId, int $userId): void {} }

class OrderFacade
{
    private InventoryService $inventory;
    private PaymentService $payment;
    private DeliveryService $delivery;

    public function __construct(
        InventoryService $inventory,
        PaymentService $payment,
        DeliveryService $delivery
    ) {
        $this->inventory = $inventory;
        $this->payment = $payment;
        $this->delivery = $delivery;
    }

    public function placeOrder(int $userId, int $itemId, float $price): bool
    {
        if (!$this->inventory->check($itemId)) {
            return false;
        }

        if (!$this->payment->charge($userId, $price)) {
            return false;
        }

        $this->delivery->schedule($itemId, $userId);
        return true;
    }
}
```

---

<a id="proxy"></a>
## Шаблонът Proxy (Прокси)

Шаблонът **Proxy** предоставя обект-заместник, който контролира достъпа до оригиналния обект. Проксито може да поеме мързелива инициализация, контрол на достъпа, кеширане или логване.

```php
// app/Http/HeavyReportInterface.php
declare(strict_types=1);

namespace App\Http;

interface HeavyReportInterface
{
    public function render(): string;
}

class RealHeavyReport implements HeavyReportInterface
{
    public function __construct()
    {
        // Simulating heavy database computation
        usleep(50000);
    }

    public function render(): string
    {
        return "<h1>Heavy PDF Report Output</h1>";
    }
}

// Caching & Lazy Initialization Proxy
class HeavyReportProxy implements HeavyReportInterface
{
    private ?RealHeavyReport $realReport = null;
    private ?string $cachedHtml = null;

    public function render(): string
    {
        if ($this->cachedHtml === null) {
            if ($this->realReport === null) {
                // Instantiated only when requested
                $this->realReport = new RealHeavyReport();
            }
            $this->cachedHtml = $this->realReport->render();
        }

        return $this->cachedHtml;
    }
}
```

---

<a id="others"></a>
## Други структурни шаблони

- **Bridge (Мост)**: Разделя абстракцията и нейната реализация в две отделни йерархии от класове, така че да могат да се променят независимо една от друга.
- **Composite (Композит / Дърво)**: Обединява обекти в дървовидни структури за представяне на йерархии от типа „част-цяло“, позволявайки на клиентите да третират единични и съставни обекти по еднакъв начин.
- **Flyweight (Приспособленец)**: Позволява съхраняването на по-голям брой обекти в ограничена памет чрез съвместно използване на общите части на състоянието, вместо съхраняването на всички данни във всеки обект.

---

<a id="common-mistakes"></a>
## Типични грешки

1. **Създаване на „Божествен“ Фасад**: Поставяне на реална бизнес логика в класа на Фасада, вместо делегирането ѝ на подсистемите. Фасадът трябва само да координира стъпките.
2. **Объркване между Декоратор и Адаптер**: Опит за адаптиране на несъвместим интерфейс с Декоратор или динамично разширяване на поведението с Адаптер.
3. **Прекалено дълги вериги от декоратори**: Натрупване на твърде много декоратори върху един обект, което усложнява отстраняването на грешки и четенето на стека от повиквания.

---

<a id="checklist"></a>
## Чеклист за самопроверка

1. **Интерфейси:** Реализират ли вашите класове за Адаптер и Декоратор стандартни интерфейси за поддържане на полиморфизъм?
2. **Свързаност:** Развързан ли е клиентът на вашия Фасад от вътрешните класове на сложната подсистема?
3. **Жизнен цикъл:** Управлява ли правилно вашето Прокси жизнения цикъл на реалния обект (създаване и изчистване)?
4. **Съвместимост:** Преобразува ли Адаптерът точно параметрите, без да променя оригиналното поведение?

---

<a id="summary"></a>
## Резюме

Структурните шаблони за проектиране използват композиция от обекти за изграждане на стабилни и лесни за поддръжка системи. Използвайте **Adapter** за обвиване на несъвместими интерфейси. Използвайте **Decorator** за динамично добавяне на отговорности към обекти без наследство. Използвайте **Facade** за предоставяне на опростен вход към сложни библиотеки. Използвайте **Proxy** за управление на мързеливо зареждане, сигурност или кеширане.

---

<a id="self-test-quiz"></a>
## Тест за самопроверка

### Въпрос 1: Какво е основното архитектурно предимство на използването на шаблона Декоратор в сравнение с наследяването?
- А) Кодът се компилира по-бързо и се изпълнява за по-малко време.
- B) Позволява ви да добавяте или променяте поведения динамично по време на изпълнение, като избягвате експлозивния брой комбинации от подкласове по време на компилация.
- C) Гарантира безопасност на нишките (thread safety) в PHP.

<details>
<summary><b>Покажи отговора</b></summary>

**Правилен отговор: B**
Наследяването е статично. Ако искате да добавите кеширане и логване към дадено хранилище, наследяването ви принуждава да създавате отделни класове (например `CachedProductRepository`, `LoggedProductRepository`, `CachedAndLoggedProductRepository`). Декораторите ви позволяват да ги комбинирате в движение: `new Caching(new Logging(new SqlRepository()))`.
</details>

### Въпрос 2: Каква е връзката между класа на Проксито (Proxy) и класа на Реалния обект в шаблона Прокси?
- A) Проксито трябва задължително да наследява класа на Реалния обект.
- B) Както Проксито, така и Реалният обект трябва да реализират един и същ интерфейс, което позволява на Проксито да действа като прозрачен заместник.
- C) Класът на Проксито не може да има директен достъп до методите на Реалния обект.

<details>
<summary><b>Покажи отговора</b></summary>

**Правилен отговор: B**
За да се позволи на клиентите да използват Проксито взаимозаменяемо с Реалния обект, и двата класа трябва да реализират един и същ интерфейс. Това прави проксито прозрачно за клиентския код.
</details>
