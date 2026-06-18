---
title: "GoF Erzeugungsmuster: Singleton, Factory, Builder und Prototype | DevSense"
description: "Ein tiefer Einblick in GoF Erzeugungsmuster in PHP: Singleton, Factory Method, Abstract Factory, Builder und Prototype mit strengen Typen und Praxisbeispielen."
published: 2026-06-18
faq:
  - question: "Warum wird das Singleton-Muster oft als Anti-Pattern angesehen?"
    answer: "Das Singleton-Muster gilt als Anti-Pattern, da es einen globalen Zustand in die Anwendung einführt, Klassen fest an eine statische Ressource koppelt, Komponententests erschwert (aufgrund des dauerhaften Zustands zwischen Tests) und das Prinzip der Einzelverantwortung verletzt."
  - question: "Wann sollte man das Builder-Muster anstelle einer Factory verwenden?"
    answer: "Verwenden Sie das Builder-Muster, wenn der Konstruktionsprozess eines Objekts eine große Anzahl von Parametern, eine komplexe Schritt-für-Schritt-Konfiguration oder optionale Schritte erfordert. Factories eignen sich besser für eine einstufige Instanziierung."
  - question: "Was ist der Unterschied zwischen Factory Method und Abstract Factory?"
    answer: "Die Factory Method definiert ein Interface zur Erstellung eines einzelnen Objekts und überlässt die Instanziierung den Unterklassen. Die Abstract Factory bietet ein Interface zur Erstellung von Familien zusammenhängender oder abhängiger Objekte ohne Angabe ihrer konkreten Klassen."
---

# GoF Erzeugungsmuster: Singleton, Factory, Builder und Prototype

Erzeugungsmuster (creational design patterns) abstrahieren den Instanziierungsprozess. Sie machen ein System unabhängig davon, wie seine Objekte erzeugt, zusammengesetzt und dargestellt werden. Durch Kapselung der Details über die konkreten Klassen verbergen sie, wie Instanzen dieser Klassen erzeugt und verbunden werden.

**Verwandte Leitfäden:** [Von der Monolithen- zur Mikrodienstarchitektur](monolith-to-microservices-architecture) · [Datenbank-Verbindungspooling in PHP](php-database-connection-pooling)

## Inhalt

* [Einführung in Erzeugungsmuster](#intro)
* [Das Singleton-Muster](#singleton)
* [Factory Method & Abstract Factory](#factory)
* [Das Builder-Muster](#builder)
* [Das Prototype-Muster](#prototype)
* [Häufige Fehler](#common-mistakes)
* [Checkliste](#checklist)
* [Zusammenfassung](#summary)
* [Selbsttest-Quiz](#self-test-quiz)

---

<a id="intro"></a>
## Einführung in Erzeugungsmuster

Die Erstellung von Objekten direkt über den `new`-Operator kann die Geschäftslogik unnötig verkomplizieren und führt zu enger Kopplung (tight coupling). Erzeugungsmuster entkoppeln den Client-Code von den konkreten Details der Instanziierung.

In der modernen PHP-Entwicklung wird der Lebenszyklus von Objekten oft durch Dependency Injection (DI) Container verwaltet, aber das Verständnis von Erzeugungsmustern ist unerlässlich für das Schreiben von eigenen SDKs, Bibliotheken und komplexen Domänenobjekten.

---

<a id="singleton"></a>
## Das Singleton-Muster

Das **Singleton** garantiert, dass eine Klasse nur eine einzige Instanz hat, und stellt einen globalen Zugriffspunkt darauf bereit.

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

    // Verhindern von Klonen und Deserialisierung
    private function __clone() {}
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize a singleton class.");
    }

    public function query(string $sql): array
    {
        // Ausführung einer SQL-Abfrage...
        return ["status" => "success", "sql" => $sql];
    }
}
```

> [!WARNING]
> **Warnung für Komponententests**: Singletons behalten ihren Zustand zwischen Unit-Tests. Wenn Sie die statische Eigenschaft `$instance` zwischen den Tests nicht zurücksetzen, verletzen Sie die Testisolation.

---

<a id="factory"></a>
## Factory Method & Abstract Factory

### Factory Method
Die Factory Method definiert eine Schnittstelle zur Erstellung eines Objekts, überlässt es aber den Unterklassen, zu entscheiden, welche Klasse instanziiert wird.

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
        // Stripe-Integrationslogik...
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
Die Abstract Factory stellt eine Schnittstelle zur Erstellung von Familien zusammenhängender oder abhängiger Objekte bereit.

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
## Das Builder-Muster

Das **Builder-Muster** trennt die Konstruktion eines komplexen Objekts von seiner Darstellung, sodass derselbe Konstruktionsprozess unterschiedliche Darstellungen erzeugen kann.

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
## Das Prototype-Muster

Das **Prototype-Muster** bestimmt die Art der zu erstellenden Objekte mithilfe einer prototypischen Instanz und erstellt neue Objekte durch Kopieren dieses Prototyps.

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
        // Tiefes Klonen von verschachtelten Objekten erzwingen
        $this->scheduledAt = new DateTime();
        $this->title = "Kopie von " . $this->title;
    }
}
```

---

<a id="common-mistakes"></a>
## Häufige Fehler

1. **Übermäßiger Einsatz von Singleton**: Die Verwendung von Singletons als globale Variablen. Dies koppelt die Codebasis, zerstört die Testisolation und verschleiert echte Abhängigkeiten.
2. **Übermäßige Komplexität**: Erstellung von Fabriken für Objekte, die einfach direkt über einen Konstruktor instanziiert werden können, wenn keine alternativen Varianten existieren.
3. **Flaches Klonen im Prototyp**: Das Vergessen von tiefem Klonen bei Referenzeigenschaften (Objekte im Objekt), sodass die geklonte Instanz den Zustand mit dem Prototyp teilt.

---

<a id="checklist"></a>
## Checkliste

1. **Kapselung:** Haben Sie den Konstruktor privat, die Klonmethode privat gemacht und die Wakeup-Methode in Ihrem Singleton so eingerichtet, dass sie eine Ausnahme auslöst?
2. **Komplexität:** Erfordert die Objektkonfiguration eine Schritt-für-Schritt- oder optionale Einrichtung? Wenn ja, verwenden Sie einen Builder.
3. **Klonen:** Haben Sie sichergestellt, dass in Ihrer Prototyp-Klasse ein tiefes Klonen durchgeführt wird, wenn sie verschachtelte Objekte enthält?
4. **Entkopplung:** Verwenden Sie Schnittstellen (Interfaces) für Produkte, die von Ihren Factories erstellt werden?

---

<a id="summary"></a>
## Zusammenfassung

Erzeugungsmuster helfen Ihnen, die Objektinstanziierung von der Anwendungslogik zu trennen. Verwenden Sie **Singleton** nur, wenn Sie absolut garantieren müssen, dass genau eine Instanz existiert. Verwenden Sie **Factories**, um den Client-Code von der konkreten Implementierung der Unterklassen zu entkoppeln. Verwenden Sie **Builder** für die schrittweise Konstruktion komplexer Objekte. Verwenden Sie **Prototype**, wenn die Erstellung von Instanzen rechenintensiv ist und das Klonen einer vorhandenen Instanz effizienter ist.

---

<a id="self-test-quiz"></a>
## Selbsttest-Quiz

### Frage 1: Was ist das primäre architekurelle Risiko, wenn eine Singleton-Klasse direkt in Ihrer Geschäftslogik referenziert wird?
- A) PHP stürzt aufgrund einer Speicherüberschreitung ab.
- B) Es führt zu enger Kopplung und verstecktem globalen Zustand, wodurch der Code extrem schwer isoliert zu testen ist.
- C) Es löst ab PHP 8.0+ einen schwerwiegenden Kompilierungsfehler aus.

<details>
<summary><b>Antwort anzeigen</b></summary>

**Antwort: B**
Direkte Zugriffe auf Singletons koppeln Ihre Klasse an einen globalen Zustand. In Komponententests können Sie diese Verbindung nicht mocken, und der Zustand kann von einem Test zum anderen übertragen werden, was zu instabilen Testergebnissen führt.
</details>

### Frage 2: Was passiert in PHP beim Klonen eines Objekts (`$b = clone $a`), wenn die Klasse Referenzeigenschaften (wie andere Objekte) enthält und keine benutzerdefinierte `__clone()`-Methode definiert?
- A) PHP klont automatisch alle verschachtelten Objekte rekursiv.
- B) PHP führt eine flache Kopie aus, was bedeutet, dass die Referenzeigenschaften in `$b` auf genau dieselben Objektinstanzen verweisen wie in `$a`.
- C) Es wird sofort ein Laufzeitfehler ausgelöst.

<details>
<summary><b>Antwort anzeigen</b></summary>

**Antwort: B**
Das standardmäßige Klonen in PHP führt eine flache Kopie durch. Alle verschachtelten Objekte behalten ihre ursprüngliche Referenz. Daher betrifft das Ändern eines verschachtelten Objekts in der geklonten Instanz auch den Prototyp.
</details>
