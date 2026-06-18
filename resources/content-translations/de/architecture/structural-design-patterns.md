---
title: "GoF Strukturmuster: Adapter, Decorator, Facade und Proxy | DevSense"
description: "Ein umfassender Leitfaden zu GoF-Strukturmustern in PHP: Adapter, Bridge, Composite, Decorator, Facade, Flyweight und Proxy mit sauberen PHP-Codebeispielen."
published: 2026-06-18
faq:
  - question: "Was ist der Hauptunterschied zwischen den Mustern Adapter und Decorator?"
    answer: "Ein Adapter ändert die Schnittstelle eines Objekts, um es mit einer anderen Schnittstelle kompatibel zu machen, sodass inkompatible Klassen zusammenarbeiten können. Ein Decorator behält die ursprüngliche Schnittstelle des eingepackten Objekts bei, während er dessen Verhalten dynamisch erweitert."
  - question: "Wie unterscheidet sich das Facade-Muster vom Mediator-Muster?"
    answer: "Eine Facade bietet eine vereinfachte, einseitige Schnittstelle zu einem komplexen Subsystem, ohne den direkten Zugriff auf das Subsystem einzuschränken. Ein Mediator zentralisiert und koordiniert die komplexe, zweiseitige Kommunikation zwischen mehreren Kollegen-Objekten und verbirgt deren direkte Beziehungen."
  - question: "Wann sollte man einen Proxy anstelle eines Decorators verwenden?"
    answer: "Verwenden Sie einen Proxy, wenn Sie den Zugriff auf ein Objekt kontrollieren müssen (z. B. träge Initialisierung, Caching, Zugriffskontrolle), wobei der Proxy den Lebenszyklus des realen Objekts verwaltet. Verwenden Sie einen Decorator, wenn Sie einem Objekt zur Laufzeit dynamisch Verantwortlichkeiten hinzufügen möchten, ohne dessen Lebenszyklus zu verwalten."
---

# GoF Strukturmuster: Adapter, Decorator, Facade und Proxy

Strukturmuster erklären, wie Objekte und Klassen zu größeren Strukturen zusammengefügt werden können, während diese Strukturen flexibel und effizient bleiben. Sie helfen beim Aufbau komplexer Klassenhierarchien durch den Einsatz von Komposition anstelle von starrer Vererbung.

**Verwandte Leitfäden:** [Von der Monolithen- zur Mikrodienstarchitektur](monolith-to-microservices-architecture) · [Web-Angriffe und deren Prävention](web-attacks-and-prevention)

## Inhalt

* [Einführung in Strukturmuster](#intro)
* [Das Adapter-Muster](#adapter)
* [Das Decorator-Muster](#decorator)
* [Das Facade-Muster](#facade)
* [Das Proxy-Muster](#proxy)
* [Andere Strukturmuster (Bridge, Composite, Flyweight)](#others)
* [Häufige Fehler](#common-mistakes)
* [Checkliste](#checklist)
* [Zusammenfassung](#summary)
* [Selbsttest-Quiz](#self-test-quiz)

---

<a id="intro"></a>
## Einführung in Strukturmuster

Vererbung ist ein mächtiges Werkzeug, aber sie ist statisch und wird zur Kompilierzeit aufgelöst. Strukturmuster nutzen die **Objektkomposition**, um Verhaltensweisen dynamisch zur Laufzeit zu kombinieren. Dies bietet eine höhere Flexibilität und vermeidet tiefe, starre Klassenhierarchien, die schwer zu warten sind.

---

<a id="adapter"></a>
## Das Adapter-Muster

Das **Adapter**-Muster ermöglicht es Objekten mit inkompatiblen Schnittstellen, zusammenzuarbeiten. Es fungiert als Wrapper (Übersetzer), der Aufrufe vom Client-Code in ein Format übersetzt, das für ein Drittanbieter- oder Legacy-Subsystem verständlich ist.

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
## Das Decorator-Muster

Das **Decorator**-Muster fügt Objekten dynamisch neue Verhaltensweisen hinzu, indem es sie in spezielle Wrapper-Objekte einpackt, die diese Verhaltensweisen enthalten.

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
## Das Facade-Muster

Das **Facade**-Muster bietet eine vereinfachte Schnittstelle zu einer komplexen Bibliothek, einem Framework oder einer Gruppe von Klassen.

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
## Das Proxy-Muster

Das **Proxy**-Muster stellt ein Platzhalter- oder Stellvertreterobjekt bereit, um den Zugriff auf das reale Objekt zu kontrollieren. Es kann träge Initialisierung, Zugriffskontrolle, Caching oder Logging übernehmen.

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
## Andere Strukturmuster

- **Bridge**: Trennt eine Abstraktion von ihrer Implementierung, sodass beide unabhängig voneinander variiert werden können.
- **Composite**: Lässt Sie Objekte zu Baumstrukturen zusammenfügen, um Teil-Ganzes-Hierarchien zu repräsentieren, und ermöglicht es Clients, einzelne Objekte und Kompositionen einheitlich zu behandeln.
- **Flyweight**: Ermöglicht es, mehr Objekte im RAM unterzubringen, indem gemeinsame Zustandsteile von mehreren Objekten geteilt werden, anstatt alle Daten in jedem einzelnen Objekt zu speichern.

---

<a id="common-mistakes"></a>
## Häufige Fehler

1. **Erstellen einer Gott-Fassade**: Platzieren von tatsächlicher Geschäftslogik in der Fassadenklasse, anstatt an die Subsysteme zu delegieren. Die Fassade sollte nur Schritte koordinieren.
2. **Verwechseln von Decorator und Adapter**: Der Versuch, eine Schnittstelle mit einem Decorator anzupassen oder dynamische Verhaltensänderungen mit einem Adapter hinzuzufügen.
3. **Zu tiefe Decorator-Stapel**: Das Stapeln von zu vielen Decorators auf einem Objekt, was das Debuggen von Stacktraces erschwert.

---

<a id="checklist"></a>
## Checkliste

1. **Schnittstellen:** Implementieren Ihre Adapter- und Decorator-Klassen strikt Standard-Schnittstellen, um Polymorphie zu wahren?
2. **Entkopplung:** Ist Ihr Fassaden-Client von den internen Klassen des Subsystems entkoppelt?
3. **Lebenszyklus:** Verwaltet Ihr Proxy den Lebenszyklus des realen Objekts korrekt (Erstellung und Freigabe)?
4. **Kompatibilität:** Konvertiert der Adapter die Parameter korrekt, ohne das Kernverhalten zu verändern?

---

<a id="summary"></a>
## Zusammenfassung

Strukturmuster nutzen die Objektkomposition, um robuste, wartbare Systeme zu bauen. Verwenden Sie den **Adapter**, um inkompatible Schnittstellen einzupacken. Verwenden Sie den **Decorator**, um Objekten ohne Unterklassenbildung dynamisch neue Verantwortlichkeiten hinzuzufügen. Verwenden Sie eine **Facade**, um einen vereinfachten Zugang zu komplexen Bibliotheken bereitzustellen. Verwenden Sie einen **Proxy**, um Lazy Loading, Sicherheit oder Caching zu verwalten.

---

<a id="self-test-quiz"></a>
## Selbsttest-Quiz

### Frage 1: Was ist der Hauptvorteil des Decorator-Musters gegenüber der Vererbung?
- A) Es kompiliert Code schneller und läuft schneller.
- B) Es ermöglicht das dynamische Hinzufügen oder Ändern von Verhaltensweisen zur Laufzeit, wodurch eine explosionsartige Zunahme von Unterklassen zur Kompilierzeit vermieden wird.
- C) Es garantiert Thread-Sicherheit in PHP.

<details>
<summary><b>Antwort anzeigen</b></summary>

**Antwort: B**
Vererbung ist statisch. Wenn Sie einem Repository Caching und Logging hinzufügen möchten, zwingt Sie die Vererbung zur Erstellung separater Klassen (z. B. `CachedProductRepository`, `LoggedProductRepository`, `CachedAndLoggedProductRepository`). Decorators ermöglichen es, diese dynamisch zu verschachteln: `new Caching(new Logging(new SqlRepository()))`.
</details>

### Frage 2: Welche Beziehung besteht im Proxy-Muster zwischen der Proxy-Klasse und der Realen Subjekt-Klasse?
- A) Der Proxy muss die Reale Subjekt-Klasse erweitern.
- B) Sowohl der Proxy als auch das Reale Subjekt müssen dieselbe Schnittstelle implementieren, damit der Proxy als Stellvertreter fungieren kann.
- C) Die Proxy-Klasse kann nicht direkt auf die Methoden der Realen Subjekt-Klasse zugreifen.

<details>
<summary><b>Antwort anzeigen</b></summary>

**Antwort: B**
Damit Clients den Proxy austauschbar mit dem Realen Subjekt verwenden können, müssen beide dieselbe Schnittstelle implementieren. Dies macht den Proxy für den Client-Code transparent.
</details>
