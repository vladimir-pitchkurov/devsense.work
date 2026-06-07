---
title: "PHP OOP Fundamentals: From Core Concepts to Asymmetric Visibility | DevSense"
description: "Master modern Object-Oriented Programming in PHP. Learn encapsulation, inheritance, interfaces, abstract classes, traits, anonymous classes (7.0+), readonly properties (8.1+), readonly classes (8.2+), and asymmetric visibility (8.4+)."
faq:
  - question: "What is the difference between an interface and an abstract class in PHP?"
    answer: "An interface defines a pure public contract that implementing classes must satisfy, allowing multiple implementations. An abstract class is a partially implemented class that can contain state, constructor logic, and protected methods, but PHP only supports single inheritance, so a class can only extend one abstract class."
  - question: "How do readonly properties work in PHP 8.1+?"
    answer: "Readonly properties can only be initialized once, typically within the class constructor. Any subsequent attempt to modify or unset the property will throw an Error exception, ensuring immutability."
  - question: "What is asymmetric visibility in PHP 8.4+?"
    answer: "Asymmetric visibility allows you to declare different access levels for reading and writing a property. For example, 'public private(set) string $name' allows public read access but restricts write access to the class itself, eliminating getter boilerplate."
  - question: "How do you resolve trait naming collisions in PHP?"
    answer: "When two traits define a method with the same name, PHP throws a fatal error unless you explicitly resolve the collision using the 'insteadof' operator to choose one method, or the 'as' operator to alias a method under a new name."
---

# PHP OOP Fundamentals: Protecting State and Defining Clean Interfaces

If you have ever spent hours debugging a mysterious state mutation in a large web application, you know how fragile poorly encapsulated objects can be. A service changes a public property on a shared instance, and suddenly, database entries across the system are corrupted. The core issue is not Object-Oriented Programming (OOP) itself, but rather our failure to enforce strong boundaries between our objects. 

In procedural code, data is passed around globally, leaving it vulnerable to modification. In naive OOP, classes are treated as mere bags of properties, exposing everything through public variables or generic getters and setters. Modern PHP solves these architecture problems by shifting from open, mutable objects to strict, self-governing structures. 

> [!IMPORTANT]
> Modern PHP OOP is not just about organizing functions into classes; it is about enforcing strong state boundaries, defining strict public contracts, and using type safety to prevent invalid runtime states.

**Target Level**: Junior / Middle  

---

## Table of Contents
* [Encapsulation & Access Modifiers](#encapsulation-modifiers)
* [Contracts & Abstraction: Inheritance, Abstract Classes & Interfaces](#contracts-abstraction)
* [Horizontal Reuse & Dynamic Behavior: Traits & Anonymous Classes (PHP 7.0+)](#horizontal-reuse)
* [Immutability by Default: Readonly Properties (PHP 8.1+) & Readonly Classes (PHP 8.2+)](#immutability)
* [Fine-Grained Boundaries: Asymmetric Visibility (PHP 8.4+)](#asymmetric-visibility)
* [Architectural Trade-offs & Limitations](#trade-offs)
* [🧠 Self-Check Questions](#self-check)

---

<a id="encapsulation-modifiers"></a>
## Encapsulation & Access Modifiers

### 1. Point
Encapsulation is the practice of hiding an object’s internal state and requiring all interactions to go through a public interface. PHP controls this access using three modifiers:
* `public`: Accessible from anywhere.
* `protected`: Accessible only within the class itself and by inheriting classes.
* `private`: Accessible only within the class that defines it.

### 2. Why It Matters
Without encapsulation, external services can modify the internal state of an object without its knowledge, bypassing validation rules. For example, if a `BankAccount` balance can be written directly from the outside, we cannot guarantee that the balance never drops below zero or that transactions are properly logged.

### 3. Example
Let's look at how exposing public properties leads to state corruption, and how private modifiers enforce invariants:

```php
// app/Finance/BankAccount.php
namespace App\Finance;

use InvalidArgumentException;

class BankAccount
{
    // Private properties prevent direct external modification
    private float $balance = 0.0;
    private array $ledger = [];

    public function __construct(float $initialDeposit)
    {
        if ($initialDeposit < 0) {
            throw new InvalidArgumentException("Initial deposit cannot be negative.");
        }
        $this->balance = $initialDeposit;
        $this->ledger[] = "Account opened with: {$initialDeposit}";
    }

    // Public method provides controlled access to mutate state
    public function deposit(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Deposit amount must be positive.");
        }
        $this->balance += $amount;
        $this->ledger[] = "Deposited: {$amount}";
    }

    public function withdraw(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Withdrawal amount must be positive.");
        }
        if ($amount > $this->balance) {
            throw new InvalidArgumentException("Insufficient funds.");
        }
        $this->balance -= $amount;
        $this->ledger[] = "Withdrew: {$amount}";
    }

    // Getter provides read-only access without exposing mutable references
    public function getBalance(): float
    {
        return $this->balance;
    }
}
```

### 4. Consequence
By declaring the `$balance` private, the class retains full control over its validation. External code cannot execute `$account->balance = -500.0;`. State transitions can only occur through valid business operations (`deposit` and `withdraw`), ensuring the ledger and the balance are always synchronized.

---

<a id="contracts-abstraction"></a>
## Contracts & Abstraction: Inheritance, Abstract Classes & Interfaces

### 1. Point
PHP allows developers to design reusable behavior and strict architectural boundaries through inheritance and polymorphism:
* **Inheritance (`extends`)**: Allows a child class to inherit properties and methods from a parent class. Child classes can override parent methods unless the parent method or class is marked `final`.
* **Abstract Classes (`abstract class`)**: Templates that cannot be instantiated on their own. They can contain fully implemented methods as well as `abstract` method signatures that children must implement.
* **Interfaces (`interface`)**: Pure contracts containing only method signatures with no implementation. A class can implement multiple interfaces, resolving PHP's single-inheritance limitation.

### 2. Why It Matters
Inheritance allows you to share common logic, but it creates a tight coupling between parent and child. Abstract classes act as a middle ground, offering a partial blueprint. Interfaces represent the highest level of decoupling: they define *what* an object can do, not *how* it does it. This allows you to swap database adapters, mail drivers, or payment gateways without changing the application logic that depends on them.

### 3. Example
Here is how we construct a payment processing contract using an interface, an abstract base, and concrete implementations:

```php
// app/Payments/PaymentGatewayInterface.php
namespace App\Payments;

use App\Payments\PaymentGatewayInterface;

interface PaymentGatewayInterface
{
    public function charge(int $amountInCents): bool;
}
```

```php
// app/Payments/AbstractPaymentGateway.php
namespace App\Payments;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    protected string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    // Shared utility method available to child classes
    protected function logTransaction(string $gateway, int $amount, bool $success): void
    {
        // Imagine writing this to a system log
        echo sprintf("[%s] Charged %d cents. Success: %s\n", $gateway, $amount, $success ? 'Yes' : 'No');
    }
}
```

```php
// app/Payments/StripeGateway.php
namespace App\Payments;

class StripeGateway extends AbstractPaymentGateway
{
    public function charge(int $amountInCents): bool
    {
        // Stripe-specific API implementation
        $success = true; // Call Stripe API using $this->apiKey
        
        $this->logTransaction('Stripe', $amountInCents, $success);
        return $success;
    }
}
```

### 4. Consequence
High-level classes (like a `CheckoutController`) can depend on `PaymentGatewayInterface` instead of concrete classes. We can swap `StripeGateway` for `PaypalGateway` via Dependency Injection, or mock the gateway entirely in PHPUnit tests, without altering the checkout logic.

---

<a id="horizontal-reuse"></a>
## Horizontal Reuse & Dynamic Behavior: Traits & Anonymous Classes (PHP 7.0+)

### 1. Point
PHP is a single-inheritance language. To prevent code duplication without forcing class hierarchies, PHP offers two mechanisms:
* **Traits**: Blocks of code that can be inserted into classes to share methods horizontally. If naming conflicts occur between two traits, they must be resolved using `insteadof` and `as` operators.
* **Anonymous Classes (PHP 7.0+)**: Lightweight, unnamed classes declared on the fly. They are useful for simple, short-lived implementations of interfaces or abstract classes.

### 2. Why It Matters
Forcing class hierarchies just to share code (like logging or soft deletes) leads to fragile parent classes and messy inheritance trees. Traits solve this by allowing multiple independent behaviors to be imported into a single class. Anonymous classes prevent boilerplate when you need to mock a class or provide a single-use implementation for a test or callback.

### 3. Example
The following code demonstrates a trait with collision resolution and an anonymous class used for logging in tests:

```php
// app/Traits/LoggerTraits.php
namespace App\Traits;

trait LoggerA
{
    public function log(string $message): void
    {
        echo "LoggerA: {$message}\n";
    }
}

trait LoggerB
{
    public function log(string $message): void
    {
        echo "LoggerB: {$message}\n";
    }
}
```

```php
// app/Services/AuditService.php
namespace App\Services;

use App\Traits\LoggerA;
use App\Traits\LoggerB;

class AuditService
{
    use LoggerA, LoggerB {
        // Resolve conflict: Use LoggerA's log method instead of LoggerB's
        LoggerA::log insteadof LoggerB;
        // Keep LoggerB's log method accessible under an alias
        LoggerB::log as logFromB;
    }

    public function performAudit(): void
    {
        $this->log("Auditing system event..."); // Calls LoggerA::log
        $this->logFromB("Backup check...");     // Calls LoggerB::log
    }
}
```

```php
// app/Contracts/MailerInterface.php
namespace App\Contracts;

interface MailerInterface {
    public function send(string $recipient, string $body): void;
}
```

```php
// app/Services/MailerDemo.php
namespace App\Services;

use App\Contracts\MailerInterface;

// Creating a one-time class instance on the fly (PHP 7.0+)
$mockMailer = new class implements MailerInterface {
    public function send(string $recipient, string $body): void
    {
        echo "Mock sent to {$recipient} with body: {$body}\n";
    }
};
```

### 4. Consequence
By using traits, `AuditService` gains shared behaviors from multiple sources without needing to extend a common base class. With anonymous classes, we can instantiate inline implementations of `MailerInterface` for quick testing or small scripts, keeping the file system clean of boilerplate single-use classes.

---

<a id="immutability"></a>
## Immutability by Default: Readonly Properties (PHP 8.1+) & Readonly Classes (PHP 8.2+)

### 1. Point
PHP introduces modern tools to enforce state immutability at the engine level:
* **Readonly Properties (PHP 8.1+)**: Properties that can only be written to once (usually in the constructor). Subsequent modifications or attempts to `unset()` them will trigger an `Error`. They must be typed; untyped properties cannot be marked readonly.
* **Readonly Classes (PHP 8.2+)**: Syntactic sugar that implicitly marks all properties in a class as `readonly` and prevents the creation of dynamic properties.

### 2. Why It Matters
Data Transfer Objects (DTOs) and configuration settings are passed across many layers of an application. If these structures are mutable, any service along the request lifecycle could accidentally alter their values, leading to silent bugs. Enforcing immutability at the compiler level guarantees that once a DTO is constructed, its data remains identical throughout the runtime.

### 3. Example
Let's look at how we enforce immutability on a User DTO:

```php
// app/DTO/UserConfiguration.php
namespace App\DTO;

// PHP 8.2+ allows marking the entire class as readonly
readonly class UserConfiguration
{
    // All properties in a readonly class are implicitly readonly and must be typed
    public string $theme;
    public int $itemsPerPage;

    public function __construct(string $theme, int $itemsPerPage)
    {
        $this->theme = $theme;
        $this->itemsPerPage = $itemsPerPage;
    }
}
```

```php
// app/Services/ConfigConsumer.php
namespace App\Services;

use App\DTO\UserConfiguration;

$config = new UserConfiguration('dark', 25);

// Attempting to modify values:
// $config->theme = 'light'; 
// ❌ Fatal Error: Cannot modify readonly property App\DTO\UserConfiguration::$theme
```

### 4. Consequence
Any code consuming `$config` is guaranteed that the user's configuration will not change mid-request. This eliminates the need for boilerplate private properties and getter-only methods, drastically simplifying DTO code.

---

<a id="asymmetric-visibility"></a>
## Fine-Grained Boundaries: Asymmetric Visibility (PHP 8.4+)

### 1. Point
PHP 8.4+ introduces **Asymmetric Visibility**, which allows you to define different access levels for reading and writing a property on the same line.
* Syntax: `public private(set) Type $property`
* The read visibility (e.g. `public`) is specified first.
* The write visibility (e.g. `private(set)` or `protected(set)`) is specified immediately after.

### 2. Why It Matters
Before PHP 8.4, if you wanted a property to be publicly readable but only writable inside the class, you had to make the property `private` and write a public getter method, or make the class/property `readonly`. However, `readonly` prevents *any* internal mutations after initialization. Asymmetric visibility allows a class to mutate its own properties internally while exposing them as read-only to the outside world, without any getter boilerplate.

### 3. Example
Here is a `Product` entity whose price can change over time, but only via class methods:

```php
// app/Catalog/Product.php
namespace App\Catalog;

use InvalidArgumentException;

class Product
{
    // Publicly readable, but can only be set privately from within this class (PHP 8.4+)
    public private(set) int $priceInCents;
    
    // Publicly readable, but can be set by this class and child classes (protected)
    public protected(set) string $name;

    public function __construct(string $name, int $priceInCents)
    {
        $this->name = $name;
        $this->setPrice($priceInCents);
    }

    public function setPrice(int $newPriceInCents): void
    {
        if ($newPriceInCents < 0) {
            throw new InvalidArgumentException("Price cannot be negative.");
        }
        $this->priceInCents = $newPriceInCents;
    }
}
```

```php
// app/Services/CatalogService.php
namespace App\Services;

use App\Catalog\Product;

$product = new Product('Mechanical Keyboard', 9900);

// Reading the property directly works without a getter
echo $product->priceInCents; // Output: 9900

// Writing directly fails:
// $product->priceInCents = 8500; 
// ❌ Fatal Error: Cannot modify property App\Catalog\Product::$priceInCents from global scope
```

### 4. Consequence
We get the clean syntax of public properties for reading values, while maintaining strict encapsulation. We no longer need to write boilerplate code like `public function getPriceInCents(): int { return $this->priceInCents; }`.

---

<a id="trade-offs"></a>
## Architectural Trade-offs & Limitations

No pattern is a silver bullet. Applying OOP features without evaluating their trade-offs leads to over-engineered or hard-to-maintain codebases.

### 1. Deep Inheritance Coupling (The Fragile Base Class Problem)
While extending classes is easy, it introduces tight coupling. If a parent class (`AbstractPaymentGateway`) changes its constructor signature, every single child class must be updated. Favor **composition over inheritance** by injecting dependencies rather than extending bases.

### 2. The Hidden Magic of Traits
Traits can easily mask poor architecture. They look like compiler-assisted copy-pasting, making it easy to create hidden dependencies and method collisions. If multiple classes need the same logic, consider creating a dedicated helper class and injecting it as a dependency.

### 3. Readonly Property Limitations
Readonly properties (PHP 8.1+) cannot be reset or modified, even inside the class. If you need to mutate state internally (e.g. tracking state changes or cache invalidation), readonly properties will not work. Additionally, they cannot have default values and cannot be used with property hooks (PHP 8.4+).

### 4. Asymmetric Visibility Constraints
Asymmetric visibility (PHP 8.4+) requires the set operation to be strictly narrower than the get operation. For example, `private public(set)` is invalid and will throw a syntax error. Additionally, properties with asymmetric visibility cannot be passed by reference:
```php
// app/Services/ReferenceDemo.php
namespace App\Services;

use App\Catalog\Product;

$product = new Product('Mouse', 2500);

// If we try to pass by reference:
// sort($product->priceInCents); 
// ❌ Fatal Error: Cannot pass property App\Catalog\Product::$priceInCents by reference
```

---

## Practical Takeaway: The Visibility Decision Tree

When defining class properties, follow this rule of thumb to keep your encapsulation secure and code clean:

1. **Does the property ever change after constructor instantiation?**
   * **No**: Use a `readonly` property (PHP 8.1+) or a `readonly` class (PHP 8.2+).
   * **Yes**: Proceed to step 2.
2. **Should external code be able to modify the value directly?**
   * **No**: Use `public private(set)` (PHP 8.4+) to expose the value for reading without exposing write access.
   * **Yes**: Use a standard `public` property (keep in mind this bypasses validation validation).

---

## 🧠 Self-Check Questions

1. **Why does PHP throw a Fatal Error if you try to declare a property as `private public(set)` in PHP 8.4+?**
2. **True or False?** A child class can override a method in the parent class even if the parent method is prefixed with the `final` keyword.
3. **What happens if you try to unset or reinitialize a `readonly` property after it has already been set?**

<details>
<summary><b>Reveal Answers</b></summary>

1. Write visibility (`set`) must be as strict or stricter than read visibility. Because `private(set)` is stricter than `public`, `public private(set)` is valid. However, `public(set)` is wider than `private` read visibility, which is illogical and rejected by the parser.
2. **False.** Marking a class or method as `final` prevents child classes from overriding that specific method or inheriting from the class.
3. It throws an `Error` exception: *Cannot modify readonly property...* or *Cannot unset readonly property...*.
</details>
