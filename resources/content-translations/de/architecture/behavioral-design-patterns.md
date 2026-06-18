---
title: "GoF Verhaltensmuster: Chain of Responsibility, Observer, Strategy und Command | DevSense"
description: "Ein umfassender Leitfaden zu GoF-Verhaltensmustern in PHP: Strategy, Observer, Chain of Responsibility, Command, Iterator, Mediator, Memento, State, Template Method, Visitor und Interpreter mit sauberen PHP-Codebeispielen."
published: 2026-06-18
faq:
  - question: "Was ist der Hauptunterschied zwischen den Mustern Strategy und State?"
    answer: "Das Strategy-Muster wird verwendet, um einen bestimmten Algorithmus oder eine Ausführungsstrategie zur Laufzeit auszuwählen oder auszutauschen, und der Client ist sich der Strategien normalerweise bewusst. Das State-Muster wird verwendet, wenn sich ein Objekt je nach seinem internen Zustand unterschiedlich verhält, und Zustandsübergänge werden automatisch von den Zustandsobjekten selbst verwaltet, meist für den Client verborgen."
  - question: "Wie unterscheidet sich das Observer-Muster von Publish-Subscribe?"
    answer: "Beim Observer-Muster verwaltet das Subjekt (Publisher) direkt eine Liste seiner Beobachter und benachrichtigt diese direkt. Beim Publish-Subscribe-Muster gibt es einen zwischengeschalteten Message Broker oder Ereigniskanal, der Publisher und Subscriber vollständig entkoppelt, sodass sie nichts voneinander wissen."
  - question: "Wann sollte man Chain of Responsibility anstelle eines Decorators verwenden?"
    answer: "Verwenden Sie Chain of Responsibility, wenn einer von mehreren Handlern eine Anfrage verarbeiten (oder weitergeben) kann und die Anfrage auf jeder Stufe abgebrochen werden kann. Verwenden Sie einen Decorator, wenn Sie alle Wrapper in einem Stapel ausführen möchten, um das Verhalten des Kernobjekts zu bereichern, ohne abzubrechen oder einen einzelnen Handler auszuwählen."
---

# GoF Verhaltensmuster: Chain of Responsibility, Observer, Strategy und Command

Verhaltensmuster befassen sich mit Algorithmen und der Zuweisung von Zuständigkeiten zwischen Objekten. Sie beschreiben nicht nur Muster von Objekten oder Klassen, sondern auch die Kommunikationsmuster zwischen ihnen. Diese Muster charakterisieren komplexe Kontrollflüsse, die zur Laufzeit schwer zu verfolgen sind.

**Verwandte Leitfäden:** [GoF Strukturmuster](structural-design-patterns) · [Von der Monolithen- zur Mikrodienstarchitektur](monolith-to-microservices-architecture)

## Inhalt

* [Einführung in Verhaltensmuster](#intro)
* [Das Strategy-Muster](#strategy)
* [Das Observer-Muster](#observer)
* [Das Chain of Responsibility-Muster](#chain-of-responsibility)
* [Das Command-Muster](#command)
* [Andere Verhaltensmuster (Iterator, Mediator, Memento, State, Template Method, Visitor, Interpreter)](#others)
* [Häufige Fehler](#common-mistakes)
* [Checkliste](#checklist)
* [Zusammenfassung](#summary)
* [Selbsttest-Quiz](#self-test-quiz)

---

<a id="intro"></a>
## Einführung in Verhaltensmuster

Während Erzeugungsmuster sich auf die Objektinstanziierung und Strukturmuster auf die Klassenzusammensetzung konzentrieren, konzentrieren sich **Verhaltensmuster** auf die Kommunikation. Sie definieren saubere Wege, um Aktionen zu delegieren, Anfragen weiterzuleiten, Zustandsänderungen zu verwalten und Algorithmen zur Laufzeit dynamisch auszutauschen.

---

<a id="strategy"></a>
## Das Strategy-Muster

Das **Strategy**-Muster definiert eine Familie von Algorithmen, kapselt jeden einzelnen und macht sie austauschbar. Das Strategy-Muster ermöglicht es, den Algorithmus unabhängig von den Clients zu variieren, die ihn verwenden.

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
## Das Observer-Muster

Das **Observer**-Muster definiert eine Eins-zu-viele-Abhängigkeit zwischen Objekten, sodass alle abhängigen Objekte automatisch benachrichtigt und aktualisiert werden, wenn sich der Zustand eines Objekts ändert.

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
## Das Chain of Responsibility-Muster

Das **Chain of Responsibility**-Muster (Zuständigkeitskette) leitet Anfragen entlang einer Kette von Handlern weiter. Nach Erhalt einer Anfrage entscheidet jeder Handler, ob er die Anfrage verarbeitet oder an den nächsten Handler in der Kette weitergibt.

```php
// app/Http/Middleware/RequestHandlerInterface.php
declare(strict_types=1);

namespace App\Http/Middleware;

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
## Das Command-Muster

Das **Command**-Muster (Befehl) wandelt eine Anfrage in ein eigenständiges Objekt um, das alle Informationen über die Anfrage enthält. Diese Transformation ermöglicht es, Anfragen als Methodenargumente zu übergeben, die Ausführung einer Anfrage zu verzögern oder in eine Warteschlange zu stellen und rückgängig zu machende Operationen zu unterstützen.

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
## Andere Verhaltensmuster

- **Iterator**: Ermöglicht das sequenzielle Durchlaufen der Elemente einer Sammlung, ohne deren zugrunde liegende Struktur (Liste, Stack, Baum usw.) offenzulegen.
- **Mediator**: Schränkt die direkte Kommunikation zwischen Objekten ein und zwingt sie, nur über ein Vermittlerobjekt zusammenzuarbeiten.
- **Memento**: Ermöglicht das Speichern und Wiederherstellen des vorherigen Zustands eines Objekts, ohne die Details seiner Implementierung offenzulegen.
- **State**: Ermöglicht es einem Objekt, sein Verhalten zu ändern, wenn sich sein interner Zustand ändert. Es sieht so aus, als ob das Objekt seine Klasse gewechselt hätte.
- **Template Method**: Definiert das Skelett eines Algorithmus in der Superklasse, überlässt jedoch den Unterklassen das Überschreiben spezifischer Schritte, ohne die Struktur des Algorithmus zu ändern.
- **Visitor**: Ermöglicht es, Algorithmen von den Objekten zu trennen, auf denen sie operieren.
- **Interpreter**: Wertet Sprachgrammatiken oder Ausdrücke mithilfe einer spezialisierten Klassenstruktur aus.

---

<a id="common-mistakes"></a>
## Häufige Fehler

1. **Übermäßiger Einsatz des Observer-Musters**: Das Erstellen von zu vielen globalen Event-Abonnenten führt zu versteckten Seiteneffekten und erschwert das Nachverfolgen von Aktionen beim Debuggen.
2. **Enge Kopplung in der Zuständigkeitskette**: Das Festcodieren der Reihenfolge direkt in den Handlern, anstatt die Kette in einer Konfigurationsschicht oder einem Orchestrator aufzubauen.
3. **Wahl von State anstelle von Strategy**: Das Hinzufügen von Zustandsverwaltungseigenschaften in zustandslosen, unabhängigen Strategieobjekten.

---

<a id="checklist"></a>
## Checkliste

1. **Entkopplung:** Bleiben Ihre Beobachter unabhängig voneinander und wissen nichts vom Verhalten der anderen Beobachter?
2. **Flexibilität:** Können Strategien zur Laufzeit dynamisch gewechselt werden, ohne das Hauptkontextobjekt neu zu initialisieren?
3. **Kettenabbruch:** Können Anfrage-Handler die Verarbeitung sofort abbrechen (short-circuit), wenn Validierungsbedingungen fehlschlagen?
4. **Befehlskopplung:** Ist die Invoker-Klasse vollständig vom eigentlichen Empfänger (Receiver) des Befehls entkoppelt?

---

<a id="summary"></a>
## Zusammenfassung

Verhaltensmuster steuern, wie Klassen kommunizieren und Zuständigkeiten teilen. Verwenden Sie **Strategy**, um Kernalgorithmen im laufenden Betrieb auszutauschen. Verwenden Sie **Observer**, um Push-Benachrichtigungen oder Event-Listener-Systeme zu entwerfen. Verwenden Sie **Chain of Responsibility**, um Middleware-Flüsse zu erstellen. Verwenden Sie **Command**, um Aufgaben als Objekte in Befehlswarteschlangen darzustellen.

---

<a id="self-test-quiz"></a>
## Selbsttest-Quiz

### Frage 1: Was ist ein primäres Symptom dafür, dass Sie das Strategy-Muster verwenden sollten?
- A) Eine einzelne Methode enthält einen riesigen Block verschachtelter `if-else`- oder `switch-case`-Anweisungen, die verschiedene Wege zur Ausführung derselben Aufgabe auswählen.
- B) Sie müssen eine Backup-Methode schreiben, die Datenbankzustände speichert.
- C) Sie möchten Datenbankabfrageergebnisse dynamisch zwischenspeichern.

<details>
<summary><b>Antwort anzeigen</b></summary>

**Antwort: A**
Wenn Sie mehrere Bedingungen sehen, die verschiedene Algorithmen auswählen (z. B. `if ($provider === 'Stripe')` oder `else if ($provider === 'PayPal')`), deutet dies auf einen klaren Bedarf für Strategy hin. Das Ersetzen dieser Bedingungen durch Polymorphie führt zu sauberem Code, der dem Open-Closed-Prinzip entspricht.
</details>

### Frage 2: Wie entscheiden Handler in der Zuständigkeitskette, ob sie die Anfrage weiterleiten?
- A) Jeder Handler muss die Anfrage immer weitergeben, ein vorzeitiger Abbruch ist nicht zulässig.
- B) Ein Handler verarbeitet die Anfrage und gibt entweder ein Ergebnis zurück (was die Kette abbricht) oder übergibt die Kontrolle an den nächsten Handler, indem er dessen `handle()`-Methode aufruft.
- C) Die Reihenfolge der Kette wird bei jeder Anfrage zufällig bestimmt.

<details>
<summary><b>Antwort anzeigen</b></summary>

**Antwort: B**
Das Kernkonzept der Zuständigkeitskette besteht darin, dass Handler dynamisch entscheiden, ob sie die Ausführung abbrechen (z. B. wenn die Authentifizierung fehlschlägt) oder die Verarbeitung über die nächste Handler-Instanz an die Kette delegieren.
</details>
