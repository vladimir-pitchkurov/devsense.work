---
title: "PHP-OOP-Grundlagen: Von Kernkonzepten bis zu asymmetrischer Sichtbarkeit | DevSense"
description: "Meistern Sie moderne objektorientierte Programmierung in PHP. Lernen Sie Kapselung, Vererbung, interfaces, abstrakte Klassen, Traits, anonyme Klassen (7.0+), Readonly-Eigenschaften (8.1+), Readonly-Klassen (8.2+) und asymmetrische Sichtbarkeit (8.4+)."
faq:
  - question: "Was ist der Unterschied zwischen einem Interface und einer abstrakten Klasse in PHP?"
    answer: "Ein Interface definiert einen reinen öffentlichen Vertrag, den implementierende Klassen erfüllen müssen, und ermöglicht mehrere Implementierungen. Eine abstrakte Klasse ist eine teilweise implementierte Klasse, die Zustand, Konstruktorlogik und geschützte Methoden enthalten kann. Da PHP jedoch nur einfache Vererbung unterstützt, kann eine Klasse nur eine abstrakte Klasse erweitern."
  - question: "Wie funktionieren Readonly-Eigenschaften in PHP 8.1+?"
    answer: "Readonly-Eigenschaften können nur einmal initialisiert werden, in der Regel im Konstruktor der Klasse. Jeder spätere Versuch, die Eigenschaft zu ändern oder zu entfernen (unset), löst eine Error-Exception aus, was die Unveränderlichkeit garantiert."
  - question: "Was ist asymmetrische Sichtbarkeit in PHP 8.4+?"
    answer: "Die asymmetrische Sichtbarkeit ermöglicht es Ihnen, unterschiedliche Zugriffsebenen für das Lesen und Schreiben einer Eigenschaft zu deklarieren. Beispielsweise erlaubt 'public private(set) string $name' den öffentlichen Lesezugriff, schränkt den Schreibzugriff jedoch auf die Klasse selbst ein, wodurch Getter-Boilerplate-Code entfällt."
  - question: "Wie löst man Namenskollisionen bei Traits in PHP?"
    answer: "Wenn zwei Traits eine Methode mit demselben Namen definieren, löst PHP einen fatalen Fehler aus, es sei denn, Sie lösen die Kollision explizit auf, indem Sie den Operator 'insteadof' verwenden, um eine Methode auszuwählen, oder den Operator 'as', um eine Methode unter einem neuen Namen zu aliasen."
---

# PHP-OOP-Grundlagen: Zustand schützen und saubere Schnittstellen definieren

Wenn Sie jemals Stunden damit verbracht haben, eine mysteriöse Zustandsänderung in einer großen Webanwendung zu debuggen, wissen Sie, wie fragil schlecht gekapselte Objekte sein können. Ein Service ändert eine öffentliche Eigenschaft einer gemeinsamen Instanz, und plötzlich sind Datenbankeinträge im gesamten System beschädigt. Das eigentliche Problem ist nicht die objektorientierte Programmierung (OOP) selbst, sondern unsere Unfähigkeit, strenge Grenzen zwischen unseren Objekten durchzusetzen.

In prozeduralem Code werden Daten global weitergegeben, wodurch sie anfällig für Änderungen sind. In naiver OOP werden Klassen als bloße Eigenschaftsbehälter behandelt, die alles über öffentliche Variablen oder generische Getter und Setter offenlegen. Modernes PHP löst diese Architekturprobleme, indem es von offenen, veränderlichen (mutablen) Objekten zu strengen, selbstverwalteten Strukturen übergeht.

> [!IMPORTANT]
> Modernes PHP-OOP besteht nicht nur darin, Funktionen in Klassen zu organisieren. Es geht darum, strenge Zustandsgrenzen durchzusetzen, klare öffentliche Verträge zu definieren und Typsicherheit zu nutzen, um ungültige Laufzeitzustände zu verhindern.

**Zielgruppe**: Junior / Middle

---

## Inhalt
* [Kapselung & Zugriffsmodifizierer](#encapsulation-modifiers)
* [Verträge & Abstraktion: Vererbung, abstrakte Klassen & Interfaces](#contracts-abstraction)
* [Horizontale Wiederverwendung & dynamisches Verhalten: Traits & anonyme Klassen (PHP 7.0+)](#horizontal-reuse)
* [Unveränderlichkeit standardmäßig: Readonly-Eigenschaften (PHP 8.1+) & Readonly-Klassen (PHP 8.2+)](#immutability)
* [Feingranulare Grenzen: Asymmetrische Sichtbarkeit (PHP 8.4+)](#asymmetric-visibility)
* [Architektonische Kompromisse & Einschränkungen](#trade-offs)
* [🧠 Fragen zur Selbstkontrolle](#self-check)

---

<a id="encapsulation-modifiers"></a>
## Kapselung & Zugriffsmodifizierer

### 1. Kernaussage
Kapselung (Encapsulation) ist die Praxis, den internen Zustand eines Objekts zu verbergen und zu verlangen, dass alle Interaktionen über eine öffentliche Schnittstelle erfolgen. PHP steuert diesen Zugriff über drei Modifizierer:
* `public`: Von überall aus zugänglich.
* `protected`: Nur innerhalb der Klasse selbst und von erbenden Klassen aus zugänglich.
* `private`: Nur innerhalb der Klasse zugänglich, die es definiert.

### 2. Warum es wichtig ist
Ohne Kapselung können externe Dienste den internen Zustand eines Objekts ohne dessen Wissen ändern und Validierungsregeln umgehen. Wenn beispielsweise der Kontostand eines `BankAccount` direkt von außen geschrieben werden kann, können wir nicht garantieren, dass der Kontostand niemals unter Null fällt oder dass Transaktionen ordnungsgemäß protokolliert werden.

### 3. Beispiel
Sehen wir uns an, wie das Offenlegen öffentlicher Eigenschaften zur Zustandsbeschädigung führt und wie private Modifizierer Invarianten durchsetzen:

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

### 4. Konsequenz
Durch die Deklaration von `$balance` als privat behält die Klasse die volle Kontrolle über ihre Validierung. Externer Code kann `$account->balance = -500.0;` nicht ausführen. Zustandsübergänge können nur über gültige Geschäftstransaktionen (`deposit` und `withdraw`) erfolgen, wodurch sichergestellt wird, dass das Hauptbuch (ledger) und der Kontostand immer synchronisiert sind.

---

<a id="contracts-abstraction"></a>
## Verträge & Abstraktion: Vererbung, abstrakte Klassen & Interfaces

### 1. Kernaussage
PHP ermöglicht es Entwicklern, wiederverwendbares Verhalten und strenge architektonische Grenzen durch Vererbung und Polymorphismus zu entwerfen:
* **Vererbung (`extends`)**: Ermöglicht es einer Kindklasse, Eigenschaften und Methoden von einer Elternklasse zu erben. Kindklassen können Elternmethoden überschreiben, es sei denn, die Elternmethode oder -klasse ist als `final` markiert.
* **Abstrakte Klassen (`abstract class`)**: Vorlagen, die nicht selbst instanziiert werden können. Sie können vollständig implementierte Methoden sowie abstrakte (`abstract`) Methodensignaturen enthalten, die Kinder implementieren müssen.
* **Interfaces (`interface`)**: Reine Verträge, die nur Methodensignaturen ohne Implementierung enthalten. Eine Klasse kann mehrere Interfaces implementieren, wodurch die Einschränkung der einfachen Vererbung in PHP umgangen wird.

### 2. Warum es wichtig ist
Vererbung ermöglicht es Ihnen, gemeinsame Logik zu teilen, führt jedoch zu einer engen Kopplung (tight coupling) zwischen Eltern- und Kindklasse. Abstrakte Klassen dienen als Mittelweg und bieten einen Teilentwurf. Interfaces repräsentieren die höchste Stufe der Entkopplung: Sie definieren, *was* ein Objekt tun kann, nicht *wie* es dies tut. Dies ermöglicht es Ihnen, Datenbankadapter, Mail-Treiber oder Payment-Gateways auszutauschen, ohne die Anwendungslogik zu ändern, die von ihnen abhängt.

### 3. Beispiel
Hier ist, wie wir einen Vertrag zur Zahlungsabwicklung mithilfe eines Interfaces, einer abstrakten Basisklasse und konkreten Implementierungen erstellen:

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

### 4. Konsequenz
Klassen auf höherer Ebene (wie ein `CheckoutController`) können von `PaymentGatewayInterface` anstelle von konkreten Klassen abhängen. Wir können `StripeGateway` per Dependency Injection gegen `PaypalGateway` austauschen oder das Gateway in PHPUnit-Tests vollständig mocken, ohne die Checkout-Logik zu ändern.

---

<a id="horizontal-reuse"></a>
## Horizontale Wiederverwendung & dynamisches Verhalten: Traits & anonyme Klassen (PHP 7.0+)

### 1. Kernaussage
PHP ist eine Sprache mit einfacher Vererbung. Um Code-Duplizierung zu verhindern, ohne Klassenhierarchien zu erzwingwen, bietet PHP zwei Mechanismen:
* **Traits**: Codeblöcke, die in Klassen eingefügt werden können, um Methoden horizontal zu teilen. Wenn Namenskonflikte zwischen zwei Traits auftreten, müssen diese mit den Operatoren `insteadof` und `as` aufgelöst werden.
* **Anonyme Klassen (PHP 7.0+)**: Leichtgewichtige, namenlose Klassen, die direkt deklariert werden. Sie sind nützlich für einfache, kurzlebige Implementierungen von Interfaces oder abstrakten Klassen.

### 2. Warum es wichtig ist
Das Erzwingen von Klassenhierarchien nur zur gemeinsamen Nutzung von Code (wie Logging oder Soft Deletes) führt zu fragilen Elternklassen und unübersichtlichen Vererbungsbäumen. Traits lösen dies, indem sie es ermöglichen, mehrere unabhängige Verhaltensweisen in eine einzige Klasse zu importieren. Anonyme Klassen verhindern Boilerplate-Code, wenn Sie eine Klasse mocken oder eine Einmal-Implementierung für einen Test oder Callback bereitstellen müssen.

### 3. Beispiel
Der folgende Code demonstriert einen Trait mit Kollisionsauflösung und eine anonyme Klasse, die für das Logging in Tests verwendet wird:

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

### 4. Konsequenz
Durch die Verwendung von Traits erhält der `AuditService` gemeinsam genutzte Verhaltensweisen aus mehreren Quellen, ohne eine gemeinsame Basisklasse erweitern zu müssen. Mit anonymen Klassen können wir Inline-Implementierungen von `MailerInterface` für schnelle Tests oder kleine Skripte instanziieren und so das Dateisystem frei von Boilerplate-Klassen für den Einmalgebrauch halten.

---

<a id="immutability"></a>
## Unveränderlichkeit standardmäßig: Readonly-Eigenschaften (PHP 8.1+) & Readonly-Klassen (PHP 8.2+)

### 1. Kernaussage
PHP führt moderne Werkzeuge ein, um die Unveränderlichkeit von Zuständen (Immutability) auf Engine-Ebene durchzusetzen:
* **Readonly-Eigenschaften (PHP 8.1+)**: Eigenschaften, die nur einmal beschrieben werden können (normalerweise im Konstruktor). Nachträgliche Änderungen oder Versuche, sie mit `unset()` zu entfernen, lösen einen `Error` aus. Sie müssen typisiert sein; untypisierte Eigenschaften können nicht als readonly markiert werden.
* **Readonly-Klassen (PHP 8.2+)**: Syntaktischer Zucker, der implizit alle Eigenschaften einer Klasse als `readonly` markiert und das Erstellen dynamischer Eigenschaften verhindert.

### 2. Warum es wichtig ist
Data Transfer Objects (DTOs) und Konfigurationseinstellungen werden über viele Schichten einer Anwendung hinweg übergeben. Wenn diese Strukturen veränderlich sind, könnte jeder Service entlang des Request-Lebenszyklus versehentlich ihre Werte ändern, was zu schwer auffindbaren Bugs führt. Die Durchsetzung der Unveränderlichkeit auf Compiler-Ebene garantiert, dass die Daten eines DTO nach dessen Erstellung über die gesamte Laufzeit hinweg identisch bleiben.

### 3. Beispiel
Sehen wir uns an, wie wir Unveränderlichkeit für ein Benutzer-DTO durchsetzen:

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

### 4. Konsequenz
Jeglicher Code, der `$config` konsumiert, hat die Garantie, dass sich die Konfiguration des Benutzers während des Requests nicht ändert. Dies macht privaten Boilerplate-Code und reine Getter-Methoden überflüssig, was den DTO-Code drastisch vereinfacht.

---

<a id="asymmetric-visibility"></a>
## Feingranulare Grenzen: Asymmetrische Sichtbarkeit (PHP 8.4+)

### 1. Kernaussage
PHP 8.4+ führt die **Asymmetrische Sichtbarkeit** ein, die es Ihnen ermöglicht, unterschiedliche Zugriffsebenen für das Lesen und Schreiben einer Eigenschaft in derselben Zeile zu definieren.
* Syntax: `public private(set) Type $property`
* Die Lese-Sichtbarkeit (z. B. `public`) wird zuerst angegeben.
* Die Schreib-Sichtbarkeit (z. B. `private(set)` oder `protected(set)`) wird unmittelbar danach angegeben.

### 2. Warum es wichtig ist
Vor PHP 8.4 mussten Sie eine Eigenschaft als `private` deklarieren und eine öffentliche Getter-Methode schreiben oder die Klasse/Eigenschaft als `readonly` deklarieren, wenn Sie wollten, dass sie öffentlich lesbar, aber nur innerhalb der Klasse beschreibbar ist. Allerdings verhindert `readonly` *jede* interne Änderung nach der Initialisierung. Die asymmetrische Sichtbarkeit ermöglicht es einer Klasse, ihre eigenen Eigenschaften intern zu verändern, während sie nach außen hin als schreibgeschützt offengelegt werden – ganz ohne Getter-Boilerplate.

### 3. Beispiel
Hier ist eine `Product`-Entität, deren Preis sich im Laufe der Zeit ändern kann, aber nur über Klassenmethoden:

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

### 4. Konsequenz
Wir erhalten die saubere Syntax öffentlicher Eigenschaften für das Lesen von Werten, während wir gleichzeitig eine strenge Kapselung beibehalten. Wir müssen keinen Boilerplate-Code wie `public function getPriceInCents(): int { return $this->priceInCents; }` mehr schreiben.

---

<a id="trade-offs"></a>
## Architektonische Kompromisse & Einschränkungen

Kein Muster ist eine Universallösung. Die Anwendung von OOP-Features ohne Abwägung ihrer Kompromisse führt zu überentwickelten oder schwer zu wartenden Codebasen.

### 1. Enge Vererbungskopplung (Das Problem der fragilen Basisklasse)
Das Erweitern von Klassen ist zwar einfach, führt jedoch zu einer engen Kopplung. Wenn eine Elternklasse (`AbstractPaymentGateway`) ihre Konstruktorsignatur ändert, muss jede einzelne Kindklasse aktualisiert werden. Bevorzugen Sie **Komposition gegenüber Vererbung**, indem Sie Abhängigkeiten injizieren, anstatt Basisklassen zu erweitern.

### 2. Die versteckte Magie von Traits
Traits können eine schlechte Architektur leicht verschleiern. Sie wirken wie vom Compiler unterstütztes Copy-Paste, was es leicht macht, versteckte Abhängigkeiten und Methodenkollisionen zu erzeugen. Wenn mehrere Klassen dieselbe Logik benötigen, sollten Sie in Erwägung ziehen, eine dedizierte Helper-Klasse zu erstellen und diese als Abhängigkeit zu injizieren.

### 3. Einschränkungen bei Readonly-Eigenschaften
Readonly-Eigenschaften (PHP 8.1+) können selbst innerhalb der Klasse nicht zurückgesetzt oder geändert werden. Wenn Sie den Zustand intern ändern müssen (z. B. zur Verfolgung von Statusänderungen oder Cache-Invalidierung), funktionieren Readonly-Eigenschaften nicht. Darüber hinaus können sie keine Standardwerte haben und nicht mit Property-Hooks (PHP 8.4+) verwendet werden.

### 4. Einschränkungen bei asymmetrischer Sichtbarkeit
Die asymmetrische Sichtbarkeit (PHP 8.4+) erfordert, dass die Schreiboperation (`set`) strikt eingeschränkter ist als die Leseoperation (`get`). Beispielsweise ist `private public(set)` ungültig und löst einen Syntaxfehler aus. Darüber hinaus können Eigenschaften mit asymmetrischer Sichtbarkeit nicht als Referenz übergeben werden:

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

## Praktische Empfehlung: Der Entscheidungsbaum für Sichtbarkeit

Folgen Sie bei der Definition von Klasseeigenschaften dieser Faustregel, um Ihre Kapselung sicher und Ihren Code sauber zu halten:

1. **Ändert sich die Eigenschaft nach der Instanziierung im Konstruktor jemals?**
   * **Nein**: Verwenden Sie eine `readonly`-Eigenschaft (PHP 8.1+) oder eine `readonly`-Klasse (PHP 8.2+).
   * **Ja**: Gehen Sie zu Schritt 2 über.
2. **Sollte externer Code den Wert direkt ändern können?**
   * **Nein**: Verwenden Sie `public private(set)` (PHP 8.4+), um den Wert zum Lesen freizugeben, ohne Schreibzugriff zu gewähren.
   * **Ja**: Verwenden Sie eine Standard-`public`-Eigenschaft (denken Sie daran, dass dies die Validierung umgeht).

---

## 🧠 Fragen zur Selbstkontrolle

1. **Warum löst PHP in PHP 8.4+ einen Fatal Error aus, wenn Sie versuchen, eine Eigenschaft als `private public(set)` zu deklarieren?**
2. **Richtig oder Falsch?** Eine Kindklasse kann eine Methode der Elternklasse überschreiben, selbst wenn der Elternmethode das Schlüsselwort `final` vorangestellt ist.
3. **Was passiert, wenn Sie versuchen, eine `readonly`-Eigenschaft zu entfernen (unset) oder neu zu initialisieren, nachdem sie bereits gesetzt wurde?**

<details>
<summary><b>Antworten anzeigen</b></summary>

1. Die Schreib-Sichtbarkeit (`set`) muss genauso streng oder strenger sein als die Lese-Sichtbarkeit. Da `private(set)` strenger als `public` is, ist `public private(set)` gültig. Allerdings ist `public(set)` offener als die Lese-Sichtbarkeit `private`, was unlogisch ist und vom Parser abgelehnt wird.
2. **Falsch.** Das Markieren einer Klasse oder Methode als `final` verhindert, dass Kindklassen diese spezifische Methode überschreiben oder von der Klasse erben.
3. Es wird eine `Error`-Exception ausgelöst: *Cannot modify readonly property...* oder *Cannot unset readonly property...*.
</details>
