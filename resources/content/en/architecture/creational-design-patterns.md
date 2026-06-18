---
title: "GoF Creational Design Patterns: Singleton, Factory, Builder & Prototype | DevSense"
description: "A deep dive into GoF creational design patterns in PHP: Singleton, Factory Method, Abstract Factory, Builder, and Prototype with strict types and real-world examples."
published: 2026-06-18
faq:
  - question: "Why is the Singleton pattern often considered an anti-pattern?"
    answer: "Singleton is considered an anti-pattern because it introduces global state into the application, tightly couples classes to a static resource, makes unit testing difficult (due to state persistence across tests), and violates the Single Responsibility Principle by managing both its lifecycle and its primary business logic."
  - question: "When should you use the Builder pattern instead of a Factory?"
    answer: "Use the Builder pattern when object construction involves a large number of parameters, complex step-by-step configuration, or optional steps. Factories are better suited for single-step instantiation where the client only needs to specify a type and receive a completed object."
  - question: "What is the difference between Factory Method and Abstract Factory?"
    answer: "Factory Method defines an interface for creating a single object, deferring instantiation to subclasses. Abstract Factory provides an interface for creating families of related or dependent objects (e.g., matching UI elements or payment connectors) without specifying their concrete classes."
---

# GoF Creational Design Patterns: Singleton, Factory, Builder & Prototype

Creational design patterns abstract the instantiation process. They make a system independent of how its objects are created, composed, and represented. By encapsulating knowledge about which concrete classes the system uses, they hide how instances of these classes are created and put together.

**Related guides:** [Monolith to microservices architecture](monolith-to-microservices-architecture) · [PHP database connection pooling](php-database-connection-pooling)

## Contents

* [Introduction to Creational Patterns](#intro)
* [The Singleton Pattern](#singleton)
* [Factory Method & Abstract Factory](#factory)
* [The Builder Pattern](#builder)
* [The Prototype Pattern](#prototype)
* [Common Mistakes](#common-mistakes)
* [Checklist](#checklist)
* [Summary](#summary)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="intro"></a>
## Introduction to Creational Patterns

Object creation can easily clutter business logic. Hardcoding concrete class instantiations using `new` introduces tight coupling. Creational patterns decouple the client code from the concrete instantiation details. 

In modern PHP development, Dependency Injection (DI) containers often manage object lifecycles, but understanding creational patterns is crucial for writing custom extension layers, SDKs, and complex domain objects.

---

<a id="singleton"></a>
## The Singleton Pattern

The **Singleton** pattern ensures that a class has only one instance and provides a global point of access to it.

```php
// app/Database/DatabaseConnection.php
declare(strict_types=1);

namespace App\Database;

use RuntimeException;

class DatabaseConnection
{
    private static ?self $instance = null;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            if (empty($config)) {
                throw new RuntimeException("Database connection must be initialized with configuration.");
            }
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    // Prevent cloning and deserialization
    private function __clone() {}
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize a singleton class.");
    }

    public function query(string $sql): array
    {
        // Execute SQL query...
        return ["status" => "success", "sql" => $sql];
    }
}
```

> [!WARNING]
> **Unit Testing Warning**: Singletons carry state between unit tests. If you don't reset the static `$instance` property between tests, test isolation will be broken.

---

<a id="factory"></a>
## Factory Method & Abstract Factory

### Factory Method
Factory Method defines an interface for creating an object but lets subclasses decide which class to instantiate.

```php
// app/Payments/PaymentGatewayFactory.php
declare(strict_types=1);

namespace App\Payments;

interface PaymentGateway
{
    public function charge(float $amount): bool;
}

class StripeGateway implements PaymentGateway
{
    public function charge(float $amount): bool
    {
        // Stripe integration logic...
        return true;
    }
}

abstract class PaymentProcessor
{
    abstract protected function createGateway(): PaymentGateway;

    public function process(float $amount): bool
    {
        $gateway = $this->createGateway();
        return $gateway->charge($amount);
    }
}

class StripeProcessor extends PaymentProcessor
{
    protected function createGateway(): PaymentGateway
    {
        return new StripeGateway();
    }
}
```

### Abstract Factory
Abstract Factory provides an interface for creating families of related or dependent objects.

```php
// app/UI/WidgetFactory.php
declare(strict_types=1);

namespace App\UI;

interface Button { public function render(): string; }
interface Input { public function render(): string; }

class DarkButton implements Button { public function render(): string { return "<button class='dark'>Submit</button>"; } }
class DarkInput implements Input { public function render(): string { return "<input class='dark' />"; } }

interface WidgetFactory
{
    public function createButton(): Button;
    public function createInput(): Input;
}

class DarkThemeFactory implements WidgetFactory
{
    public function createButton(): Button { return new DarkButton(); }
    public function createInput(): Input { return new DarkInput(); }
}
```

---

<a id="builder"></a>
## The Builder Pattern

The **Builder** pattern separates the construction of a complex object from its representation, allowing the same construction process to create different representations.

```php
// app/Http/RequestBuilder.php
declare(strict_types=1);

namespace App\Http;

class Request
{
    public string $url = '';
    public string $method = 'GET';
    public array $headers = [];
    public string $body = '';
}

class RequestBuilder
{
    private Request $request;

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): self
    {
        $this->request = new Request();
        return $this;
    }

    public function url(string $url): self
    {
        $this->request->url = $url;
        return $this;
    }

    public function method(string $method): self
    {
        $this->request->method = strtoupper($method);
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->request->headers[$name] = $value;
        return $this;
    }

    public function body(string $body): self
    {
        $this->request->body = $body;
        return $this;
    }

    public function build(): Request
    {
        $result = $this->request;
        $this->reset();
        return $result;
    }
}
```

---

<a id="prototype"></a>
## The Prototype Pattern

The **Prototype** pattern specifies the kinds of objects to create using a prototypical instance, and creates new objects by copying this prototype.

```php
// app/Marketing/CampaignTemplate.php
declare(strict_types=1);

namespace App\Marketing;

use DateTime;

class CampaignTemplate
{
    public string $title;
    public string $content;
    public DateTime $scheduledAt;

    public function __construct(string $title, string $content)
    {
        $this->title = $title;
        $this->content = $content;
        $this->scheduledAt = new DateTime();
    }

    public function __clone()
    {
        // Force cloning of nested object dependencies
        $this->scheduledAt = new DateTime();
        $this->title = "Clone of " . $this->title;
    }
}
```

---

<a id="common-mistakes"></a>
## Common Mistakes

1. **Overusing Singleton**: Treating Singletons as global variables. This couples the codebase, ruins test isolation, and hides dependencies.
2. **Abstract Factory Over-Engineering**: Creating factories for objects that can be instantiated directly using a constructor when no family variants exist.
3. **Shallow Cloning in Prototype**: Forgetting to implement `__clone` for reference type attributes (e.g., objects inside objects), causing the cloned object to share memory state with the prototype.

---

<a id="checklist"></a>
## Checklist

1. **Encapsulation:** Did you make the constructor private, clone method private, and wakeup method throw an exception in your Singleton?
2. **Complexity:** Does your object configuration require optional or step-by-step setup? If so, use a Builder.
3. **Cloning:** Did you ensure deep cloning is performed in your Prototype class if it contains nested objects?
4. **Decoupling:** Are you using interfaces for products created by your Factories?

---

<a id="summary"></a>
## Summary

Creational design patterns help you separate object instantiation from application logic. Use **Singleton** only when you must guarantee exactly one instance exists. Use **Factories** to decouple client code from concrete subclass implementation. Use **Builder** for step-by-step assembly of complex objects. Use **Prototype** when creating instances is computationally expensive and cloning an existing one is more efficient.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: What is the main structural risk of referencing a Singleton class directly inside your business logic?
- A) PHP will crash due to execution memory exhaustion.
- B) It introduces tight coupling and hidden global state, making the code extremely hard to unit test and mock.
- C) It triggers a fatal runtime compile error in PHP 8.0+.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Referencing Singletons directly couples your class to a global state. In unit tests, you cannot mock the connection, and state can leak from one test to another, resulting in flaky tests.
</details>

### Question 2: In PHP, what happens during object cloning (`$b = clone $a`) if a class contains reference properties (like other objects) and does not define a custom `__clone()` method?
- A) PHP automatically clones all nested objects recursively.
- B) PHP performs a shallow copy, meaning the reference properties in `$b` will point to the exact same object instances as `$a`.
- C) A runtime error is thrown immediately.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
PHP's default cloning performs a shallow copy. Any nested object properties will retain their original reference, so modifying a nested object in the cloned instance will affect the prototype instance.
</details>
