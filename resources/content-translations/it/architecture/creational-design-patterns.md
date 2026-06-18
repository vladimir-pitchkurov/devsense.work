---
title: "Pattern Creazionali GoF: Singleton, Factory, Builder e Prototype | DevSense"
description: "Un'analisi approfondita dei pattern creazionali GoF in PHP: Singleton, Factory Method, Abstract Factory, Builder e Prototype con tipi stretti ed esempi reali."
published: 2026-06-18
faq:
  - question: "Perché il pattern Singleton è spesso considerato un anti-pattern?"
    answer: "Il Singleton è considerato un anti-pattern poiché introduce uno stato globale nell'applicazione, accoppia fortemente le classi a una risorsa statica, rende difficili i test unitari (a causa della persistenza dello stato tra i test) e viola il principio di singola responsabilità."
  - question: "Quando si dovrebbe utilizzare il pattern Builder anziché una Factory?"
    answer: "Utilizza il pattern Builder quando la creazione di un oggetto richiede un gran numero di parametri, una configurazione complessa passo dopo passo o passaggi opzionali. Le Factory sono più adatte per istanziazioni in un unico passaggio."
  - question: "Qual è la differenza tra Factory Method e Abstract Factory?"
    answer: "Factory Method definisce un'interfaccia per la creazione di un singolo oggetto, delegando l'istanziazione alle sottoclassi. Abstract Factory fornisce un'interfaccia per la creazione di famiglie di oggetti correlati o dipendenti senza specificare le loro classi concrete."
---

# Pattern Creazionali GoF: Singleton, Factory, Builder e Prototype

I pattern creazionali (creational design patterns) astraggono il processo di istanziazione. Rendono un sistema indipendente da come i suoi oggetti vengono creati, composti e rappresentati. Incapsulando la conoscenza delle classi concrete utilizzate dal sistema, nascondono i dettagli relativi a come le istanze di queste classi vengono create e assemblate.

**Guide correlate:** [Dall'architettura monolitica a quella a microservizi](monolith-to-microservices-architecture) · [Pooling delle connessioni al database in PHP](php-database-connection-pooling)

## Indice

* [Introduzione ai pattern creazionali](#intro)
* [Il pattern Singleton](#singleton)
* [Factory Method e Abstract Factory](#factory)
* [Il pattern Builder](#builder)
* [Il pattern Prototype](#prototype)
* [Errori comuni](#common-mistakes)
* [Lista di controllo](#checklist)
* [Riepilogo](#summary)
* [Quiz di autovalutazione](#self-test-quiz)

---

<a id="intro"></a>
## Introduzione ai pattern creazionali

La creazione diretta di oggetti tramite l'operatore `new` può facilmente appesantire la logica di business e introduce un accoppiamento forte. I pattern creazionali disaccoppiano il codice client dai dettagli concreti di istanziazione.

Nello sviluppo PHP moderno, il ciclo di vita degli oggetti è spesso gestito da contenitori di Dependency Injection (DI), ma comprendere i pattern creazionali è fondamentale per scrivere SDK personalizzati, librerie e oggetti di dominio complessi.

---

<a id="singleton"></a>
## Il pattern Singleton

Il **Singleton** garantisce che una classe abbia una sola istanza e fornisce un punto di accesso globale ad essa.

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

    // Prevenire la clonazione e la deserializzazione
    private function __clone() {}
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize a singleton class.");
    }

    public function query(string $sql): array
    {
        // Esecuzione query SQL...
        return ["status" => "success", "sql" => $sql];
    }
}
```

> [!WARNING]
> **Avviso per i test unitari**: I Singleton mantengono lo stato tra i test unitari. Se non si ripristina la proprietà statica `$instance` tra i test, si interrompe l'isolamento dei test.

---

<a id="factory"></a>
## Factory Method e Abstract Factory

### Factory Method
Factory Method definisce un'interfaccia per la creazione di un oggetto, ma lascia che le sottoclassi decidano quale classe istanziare.

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
        // Logica di integrazione con Stripe...
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
Abstract Factory fornisce un'interfaccia per la creazione di famiglie di oggetti correlati o dipendenti.

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
## Il pattern Builder

Il pattern **Builder** separa la costruzione di un oggetto complesso dalla sua rappresentazione, consentendo allo stesso processo di costruzione di creare rappresentazioni diverse.

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
## Il pattern Prototype

Il pattern **Prototype** specifica i tipi di oggetti da creare utilizzando un'istanza prototipale, e crea nuovi oggetti copiando questo prototipo.

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
        // Forza la clonazione dell'oggetto DateTime annidato
        $this->scheduledAt = new DateTime();
        $this->title = "Copia di " . $this->title;
    }
}
```

---

<a id="common-mistakes"></a>
## Errori comuni

1. **Uso eccessivo del Singleton**: Trattare i Singleton come variabili globali. Questo accoppia il codice, distrugge l'isolamento dei test e nasconde le dipendenze.
2. **Sovraingegnerizzazione delle Factory**: Creare Factory per oggetti che possono essere istanziati direttamente tramite costruttore quando non esistono varianti di famiglia.
3. **Clonazione superficiale in Prototype**: Dimenticare di eseguire una clonazione profonda delle proprietà oggetto, facendo sì che il clone condivida lo stato in memoria con il prototipo originale.

---

<a id="checklist"></a>
## Lista di controllo

1. **Incapsulamento:** Nel tuo Singleton, hai reso privato il costruttore, privato il metodo clone e forzato il metodo wakeup a lanciare un'eccezione?
2. **Complessità:** La configurazione dell'oggetto richiede passaggi sequenziali o opzionali? Se sì, utilizza un Builder.
3. **Clonazione:** Se la classe Prototype contiene oggetti annidati, ti sei assicurato di eseguire una clonazione profonda?
4. **Disaccoppiamento:** Stai utilizzando interfacce per i prodotti creati dalle tue Factory?

---

<a id="summary"></a>
## Riepilogo

I pattern creazionali aiutano a separare l'istanza di un oggetto dalla logica applicativa. Utilizza **Singleton** solo quando è strettamente necessario garantire l'esistenza di una sola istanza. Utilizza le **Factory** per disaccoppiare il codice client dalle implementazioni delle sottoclassi concrete. Utilizza **Builder** per la costruzione dettagliata di oggetti complessi. Utilizza **Prototype** quando la creazione di istanze richiede molte risorse e la clonazione di un'istanza esistente è più efficiente.

---

<a id="self-test-quiz"></a>
## Quiz di autovalutazione

### Domanda 1: Qual è il principale rischio strutturale nel fare riferimento a una classe Singleton direttamente nella logica di business?
- A) PHP andrà in crash per esaurimento della memoria di esecuzione.
- B) Introduce accoppiamento forte e stato globale nascosto, rendendo il codice estremamente difficile da testare unitariamente in isolamento.
- C) Genera un errore fatale di compilazione a partire da PHP 8.0+.

<details>
<summary><b>Mostra la risposta</b></summary>

**Risposta: B**
Il riferimento diretto ai Singleton accoppia la classe a uno stato globale. Nei test unitari non sarà possibile mockare tale connessione e lo stato potrebbe passare da un test all'altro, rendendo instabili i risultati dei test.
</details>

### Domanda 2: In PHP, cosa succede durante la clonazione di un oggetto (`$b = clone $a`) se una classe contiene proprietà di riferimento (come altri oggetti) e non definisce un metodo `__clone()` personalizzato?
- A) PHP clona automaticamente tutti gli oggetti annidati in modo ricorsivo.
- B) PHP esegue una copia superficiale (shallow copy), il che significa che le proprietà di riferimento in `$b` punteranno esattamente alle stesse istanze oggetto di `$a`.
- C) Viene generato immediatamente un errore di runtime.

<details>
<summary><b>Mostra la risposta</b></summary>

**Risposta: B**
La clonazione predefinita in PHP esegue una copia superficiale. Qualsiasi proprietà oggetto annidata manterrà il proprio riferimento originale, pertanto la modifica di un oggetto annidato nell'istanza clonata influenzerà anche il prototipo originale.
</details>
