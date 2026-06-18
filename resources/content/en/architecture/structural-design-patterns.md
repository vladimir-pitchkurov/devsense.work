---
title: "GoF Structural Design Patterns: Adapter, Decorator, Facade & Proxy | DevSense"
description: "An in-depth guide to GoF structural design patterns in PHP: Adapter, Bridge, Composite, Decorator, Facade, Flyweight, and Proxy with clean PHP code examples."
published: 2026-06-18
faq:
  - question: "What is the key difference between the Adapter and Decorator patterns?"
    answer: "An Adapter changes an object's interface to make it compatible with another interface, allowing otherwise incompatible classes to collaborate. A Decorator retains the original interface of the wrapped object while dynamically adding or extending its behaviors."
  - question: "How does the Facade pattern differ from the Mediator pattern?"
    answer: "A Facade provides a simplified, one-way interface to a complex subsystem without restricting direct subsystem access. A Mediator centralizes and coordinates complex, bidirectional communication among multiple colleague objects, hiding direct colleague relationships."
  - question: "When should you use a Proxy instead of a Decorator?"
    answer: "Use a Proxy when you need to control access to an object (e.g., lazy initialization, caching, access control) where the proxy manages the lifecycle of the real subject. Use a Decorator when you want to add responsibilities to an object dynamically at runtime without managing its instantiation lifecycle."
---

# GoF Structural Design Patterns: Adapter, Decorator, Facade & Proxy

Structural design patterns explain how to assemble objects and classes into larger structures, while keeping these structures flexible and efficient. They help build complex class hierarchies by using composition rather than tight inheritance.

**Related guides:** [Monolith to microservices architecture](monolith-to-microservices-architecture) · [Web attacks and prevention](web-attacks-and-prevention)

## Contents

* [Introduction to Structural Patterns](#intro)
* [The Adapter Pattern](#adapter)
* [The Decorator Pattern](#decorator)
* [The Facade Pattern](#facade)
* [The Proxy Pattern](#proxy)
* [Other Structural Patterns (Bridge, Composite, Flyweight)](#others)
* [Common Mistakes](#common-mistakes)
* [Checklist](#checklist)
* [Summary](#summary)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="intro"></a>
## Introduction to Structural Patterns

Inheritance is a powerful tool, but it is static and resolved at compile time. Structural patterns use **object composition** to combine behaviors dynamically at runtime. This provides greater flexibility and avoids deep, rigid class hierarchies that are difficult to maintain.

---

<a id="adapter"></a>
## The Adapter Pattern

The **Adapter** pattern allows objects with incompatible interfaces to collaborate. It acts as a wrapper that translates calls from client code into a format compatible with a third-party or legacy subsystem.

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
## The Decorator Pattern

The **Decorator** pattern attaches new behaviors to objects dynamically by placing them inside special wrapper objects that contain these behaviors.

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
## The Facade Pattern

The **Facade** pattern provides a simplified interface to a complex library, framework, or set of classes.

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
## The Proxy Pattern

The **Proxy** pattern provides a placeholder or surrogate object to control access to the real object. It can handle lazy initialization, access control, caching, or logging.

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
## Other Structural Patterns

- **Bridge**: Splits a large class or set of closely related classes into two separate hierarchies—abstraction and implementation—which can be developed independently.
- **Composite**: Lets you compose objects into tree structures and work with these structures as if they were individual objects (e.g. nested menu systems).
- **Flyweight**: Fits more objects into available RAM by sharing common parts of state between multiple objects instead of keeping all data in each object.

---

<a id="common-mistakes"></a>
## Common Mistakes

1. **Creating a God Facade**: Putting actual business logic inside the Facade class instead of delegating to subclasses. The Facade should only coordinate steps.
2. **Confusing Decorator with Adapter**: Trying to adapt an interface using a Decorator, or adding dynamic state changes using an Adapter.
3. **Deep Decorator Stacks**: Stacking too many Decorators on top of one object, which makes debugging stack traces difficult.

---

<a id="checklist"></a>
## Checklist

1. **Interfaces:** Do your Adapter and Decorator classes strictly implement standard interfaces to maintain polymorphism?
2. **Decoupling:** Is your Facade client decoupled from the internal classes of the subsystem?
3. **Lifecycle:** Does your Proxy correctly manage the lifecycle of the real subject (creation and cleanup)?
4. **Compatibility:** Does the Adapter convert the parameters accurately without changing core behaviors?

---

<a id="summary"></a>
## Summary

Structural design patterns use object composition to build robust, maintainable systems. Use **Adapter** to wrap incompatible interfaces. Use **Decorator** to add responsibilities to objects dynamically without subclassing. Use **Facade** to provide a simplified entrance to complex libraries. Use **Proxy** to manage lazy loading, security, or caching.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: What is the main design advantage of using the Decorator pattern instead of inheritance?
- A) It compiles code faster and runs faster.
- B) It allows you to add or modify behaviors dynamically at runtime, avoiding the combination explosion of compile-time subclasses.
- C) It guarantees thread safety in PHP.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Inheritance is static. If you want to add caching and logging to a repository, inheritance forces you to create separate classes (e.g. `CachedProductRepository`, `LoggedProductRepository`, `CachedAndLoggedProductRepository`). Decorators let you wrap them dynamically: `new Caching(new Logging(new SqlRepository()))`.
</details>

### Question 2: In a Proxy pattern, what is the relationship between the Proxy class and the Real Subject class?
- A) The Proxy must extend the Real Subject class.
- B) Both the Proxy and the Real Subject must implement the same interface, allowing the Proxy to act as a surrogate.
- C) The Proxy class cannot access the Real Subject class methods directly.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
To allow clients to use the Proxy interchangeably with the Real Subject, both must implement the same interface. This makes the proxy transparent to client code.
</details>
