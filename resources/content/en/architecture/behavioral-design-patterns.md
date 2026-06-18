---
title: "GoF Behavioral Design Patterns: Chain of Responsibility, Observer, Strategy & Command | DevSense"
description: "An in-depth guide to GoF behavioral design patterns in PHP: Strategy, Observer, Chain of Responsibility, Command, Iterator, Mediator, Memento, State, Template Method, Visitor, and Interpreter with clean PHP code examples."
published: 2026-06-18
faq:
  - question: "What is the main difference between the Strategy and State patterns?"
    answer: "The Strategy pattern is used when you want to choose or swap a specific algorithm or execution strategy at runtime, and the client is usually aware of the strategies. The State pattern is used when an object behaves differently based on its internal state, and state transitions are handled automatically by the state objects themselves, usually hidden from the client."
  - question: "How does the Observer pattern differ from Publish-Subscribe?"
    answer: "In the Observer pattern, the subject (publisher) maintains a direct list of its observers and notifies them directly. In Publish-Subscribe, there is an intermediate message broker or event channel that decouples publishers from subscribers entirely, so they don't know about each other."
  - question: "When should you use Chain of Responsibility instead of Decorator?"
    answer: "Use Chain of Responsibility when any one of multiple handler objects can handle a request (or pass it along), and the request can be stopped or short-circuited at any stage. Use a Decorator when you want to execute all the wrappers in a stack to enrich the behavior of the core object, without short-circuiting or selecting a single handler."
---

# GoF Behavioral Design Patterns: Chain of Responsibility, Observer, Strategy & Command

Behavioral design patterns are concerned with algorithms and the assignment of responsibilities between objects. They describe not just patterns of objects or classes but also the patterns of communication between them. These patterns characterize complex control flow that is difficult to follow at runtime.

**Related guides:** [GoF Structural Design Patterns](structural-design-patterns) · [Monolith to microservices architecture](monolith-to-microservices-architecture)

## Contents

* [Introduction to Behavioral Patterns](#intro)
* [The Strategy Pattern](#strategy)
* [The Observer Pattern](#observer)
* [The Chain of Responsibility Pattern](#chain-of-responsibility)
* [The Command Pattern](#command)
* [Other Behavioral Patterns (Iterator, Mediator, Memento, State, Template Method, Visitor, Interpreter)](#others)
* [Common Mistakes](#common-mistakes)
* [Checklist](#checklist)
* [Summary](#summary)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="intro"></a>
## Introduction to Behavioral Patterns

While creational patterns focus on object instantiation and structural patterns focus on class composition, **behavioral patterns** concentrate on communication. They define clean ways to delegate actions, route requests, manage state changes, and swap algorithms dynamically during runtime.

---

<a id="strategy"></a>
## The Strategy Pattern

The **Strategy** pattern defines a family of algorithms, encapsulates each one, and makes them interchangeable. Strategy lets the algorithm vary independently from clients that use it.

```php
// app/Payment/PaymentStrategyInterface.php
declare(strict_types=1);

namespace App\Payment;

interface PaymentStrategyInterface
{
    public function pay(float $amount): bool;
}

class StripePaymentStrategy implements PaymentStrategyInterface
{
    public function pay(float $amount): bool
    {
        // Stripe payment API logic...
        return true;
    }
}

class PayPalPaymentStrategy implements PaymentStrategyInterface
{
    public function pay(float $amount): bool
    {
        // PayPal payment API logic...
        return true;
    }
}

// Client context class
class CheckoutProcessor
{
    private PaymentStrategyInterface $paymentStrategy;

    public function __construct(PaymentStrategyInterface $paymentStrategy)
    {
        $this->paymentStrategy = $paymentStrategy;
    }

    public function setPaymentStrategy(PaymentStrategyInterface $paymentStrategy): void
    {
        $this->paymentStrategy = $paymentStrategy;
    }

    public function executeCheckout(float $amount): bool
    {
        return $this->paymentStrategy->pay($amount);
    }
}
```

---

<a id="observer"></a>
## The Observer Pattern

The **Observer** pattern defines a one-to-many dependency between objects so that when one object changes state, all its dependents are notified and updated automatically.

```php
// app/Events/UserRegisterObserverInterface.php
declare(strict_types=1);

namespace App\Events;

interface UserRegisterObserverInterface
{
    public function update(string $email): void;
}

class SendWelcomeEmailObserver implements UserRegisterObserverInterface
{
    public function update(string $email): void
    {
        // Mailer logic to send welcome email...
    }
}

class CreatePromoCodeObserver implements UserRegisterObserverInterface
{
    public function update(string $email): void
    {
        // Database logic to generate a new user coupon...
    }
}

// Subject class holding references to observers
class UserRegistrationService
{
    private array $observers = [];

    public function attach(UserRegisterObserverInterface $observer): void
    {
        $this->observers[] = $observer;
    }

    public function registerUser(string $email): void
    {
        // Create user record in DB...
        
        // Notify observers
        foreach ($this->observers as $observer) {
            $observer->update($email);
        }
    }
}
```

---

<a id="chain-of-responsibility"></a>
## The Chain of Responsibility Pattern

The **Chain of Responsibility** pattern passes requests along a chain of handlers. Upon receiving a request, each handler decides either to process the request or to pass it to the next handler in the chain.

```php
// app/Http/Middleware/RequestHandlerInterface.php
declare(strict_types=1);

namespace App\Http\Middleware;

interface RequestHandlerInterface
{
    public function setNext(RequestHandlerInterface $handler): RequestHandlerInterface;
    public function handle(array $request): ?string;
}

abstract class AbstractRequestHandler implements RequestHandlerInterface
{
    private ?RequestHandlerInterface $nextHandler = null;

    public function setNext(RequestHandlerInterface $handler): RequestHandlerInterface
    {
        $this->nextHandler = $handler;
        return $handler;
    }

    public function handle(array $request): ?string
    {
        if ($this->nextHandler !== null) {
            return $this->nextHandler->handle($request);
        }
        return null;
    }
}

class AuthHandler extends AbstractRequestHandler
{
    public function handle(array $request): ?string
    {
        if (!isset($request['user_id'])) {
            return "401 Unauthorized";
        }
        return parent::handle($request);
    }
}

class RateLimitHandler extends AbstractRequestHandler
{
    public function handle(array $request): ?string
    {
        if (isset($request['request_count']) && $request['request_count'] > 100) {
            return "429 Too Many Requests";
        }
        return parent::handle($request);
    }
}
```

---

<a id="command"></a>
## The Command Pattern

The **Command** pattern turns a request into a stand-alone object that contains all information about the request. This transformation lets you pass requests as a method arguments, delay or queue a request's execution, and support undoable operations.

```php
// app/Commands/CommandInterface.php
declare(strict_types=1);

namespace App\Commands;

interface CommandInterface
{
    public function execute(): void;
}

class BackupDatabaseCommand implements CommandInterface
{
    private string $databaseName;

    public function __construct(string $databaseName)
    {
        $this->databaseName = $databaseName;
    }

    public function execute(): void
    {
        // Logic to run backup shell scripts...
    }
}

// Invoker class
class CommandQueueExecutor
{
    private array $queue = [];

    public function addCommand(CommandInterface $command): void
    {
        $this->queue[] = $command;
    }

    public function runPendingCommands(): void
    {
        foreach ($this->queue as $command) {
            $command->execute();
        }
        $this->queue = [];
    }
}
```

---

<a id="others"></a>
## Other Behavioral Patterns

- **Iterator**: Lets you traverse elements of a collection without exposing its underlying representation (List, Stack, Tree, etc.).
- **Mediator**: Restricts direct communications between the objects and forces them to collaborate only via a mediator object.
- **Memento**: Lets you save and restore the previous state of an object without revealing the details of its implementation.
- **State**: Lets an object alter its behavior when its internal state changes. The object will appear to change its class.
- **Template Method**: Defines the skeleton of an algorithm in the superclass but lets subclasses override specific steps of the algorithm without changing its structure.
- **Visitor**: Lets you separate algorithms from the objects on which they operate.
- **Interpreter**: Evaluates language grammar or expressions using a specialized class structure.

---

<a id="common-mistakes"></a>
## Common Mistakes

1. **Overusing the Observer Pattern**: Spawning too many global event subscribers. This leads to hidden side-effects and makes tracing user actions difficult during debugging.
2. **Tight Coupling in Chain of Responsibility**: Hardcoding the sequence inside the handlers instead of building the chain in a configuration layer or orchestrator.
3. **Choosing State instead of Strategy**: Adding state management properties to what should be stateless, independent Strategy objects.

---

<a id="checklist"></a>
## Checklist

1. **Decoupling:** Do your observers remain unaware of each other's presence and behaviors?
2. **Flexibility:** Can strategies be switched dynamically at runtime without restarting the main context object?
3. **Chain Setup:** Can request handlers return immediately (short-circuit) when validation conditions fail?
4. **Command Decoupling:** Does the invoker class remain decoupled from the receiver of the command?

---

<a id="summary"></a>
## Summary

Behavioral design patterns govern how classes communicate and share responsibilities. Use **Strategy** to swap core algorithms on the fly. Use **Observer** to design push notification or event-listener systems. Use **Chain of Responsibility** to build middleware flows. Use **Command** to represent tasks as objects in command queues.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: What is a primary symptom that you should use the Strategy pattern?
- A) A single method contains a massive block of nested `if-else` or `switch-case` statements selecting different ways to perform the same task.
- B) You need to write a backup method that saves database states.
- C) You want to cache database query results dynamically.

<details>
<summary>Click to view the answer</summary>

**Answer: A**
When you see multiple conditions selecting different algorithms (e.g. `if ($provider === 'Stripe')` or `else if ($provider === 'PayPal')`), this indicates a clear need for Strategy. Swapping these cases out for polymorphism yields clean, open-closed compliant code.
</details>

### Question 2: In the Chain of Responsibility, how do handlers decide whether to pass the request along?
- A) Each handler must always pass the request, short-circuiting is not allowed.
- B) A handler processes the request and either returns a result (breaking the chain) or passes control to the next handler by calling its `handle()` method.
- C) The chain sequence is randomized on every request.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
The core design of the Chain of Responsibility is that handlers decide dynamically whether to short-circuit (e.g. when authentication validation fails) or to delegate processing down the chain using the next handler instance.
</details>
