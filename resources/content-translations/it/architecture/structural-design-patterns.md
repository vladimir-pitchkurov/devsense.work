---
title: "Pattern Strutturali GoF: Adapter, Decorator, Facade e Proxy | DevSense"
description: "Una guida dettagliata ai design pattern strutturali GoF in PHP: Adapter, Bridge, Composite, Decorator, Facade, Flyweight e Proxy con esempi chiari in PHP."
published: 2026-06-18
faq:
  - question: "Qual è la differenza fondamentale tra i pattern Adapter e Decorator?"
    answer: "Un Adapter modifica l'interfaccia di un oggetto esistente per renderla compatibile con un'altra interfaccia, consentendo a classi altrimenti incompatibili di collaborare. Un Decorator mantiene l'interfaccia originale dell'oggetto avvolto, estendendone o aggiungendone dinamicamente i comportamenti."
  - question: "In cosa si differenzia il pattern Facade da quello Mediator?"
    answer: "Una Facade fornisce un'interfaccia semplificata e unidirezionale a un sottosistema complesso, senza limitare l'accesso diretto alle sue classi. Un Mediator centralizza e coordina la comunicazione bidirezionale complessa tra molteplici oggetti colleghi, nascondendo le loro relazioni dirette."
  - question: "Quando si dovrebbe utilizzare un Proxy anziché un Decorator?"
    answer: "Utilizza un Proxy quando è necessario controllare l'accesso a un oggetto (ad esempio, inizializzazione pigra, memorizzazione nella cache, controllo degli accessi) e il proxy stesso gestisce il ciclo di vita dell'oggetto reale. Utilizza un Decorator per aggiungere responsabilità a un oggetto dinamicamente a runtime senza gestirne il ciclo di vita di istanziazione."
---

# Pattern Strutturali GoF: Adapter, Decorator, Facade e Proxy

I design pattern strutturali spiegano come assemblare oggetti e classi in strutture più grandi, mantenendo queste ultime flessibili ed efficienti. Aiutano a costruire gerarchie di classi complesse utilizzando la composizione anziché l'ereditarietà rigida.

**Guide correlate:** [Dall'architettura monolitica a quella a microservizi](monolith-to-microservices-architecture) · [Attacchi web e prevenzione](web-attacks-and-prevention)

## Indice

* [Introduzione ai Pattern Strutturali](#intro)
* [Il Pattern Adapter (Adattatore)](#adapter)
* [Il Pattern Decorator (Decoratore)](#decorator)
* [Il Pattern Facade (Facciata)](#facade)
* [Il Pattern Proxy (Sostituto)](#proxy)
* [Altri Pattern Strutturali (Bridge, Composite, Flyweight)](#others)
* [Errori Comuni](#common-mistakes)
* [Lista di Controllo](#checklist)
* [Riepilogo](#summary)
* [Quiz di Autovalutazione](#self-test-quiz)

---

<a id="intro"></a>
## Introduzione ai Pattern Strutturali

L'ereditarietà è uno strumento potente, ma è statica e viene risolta a tempo di compilazione. I pattern strutturali utilizzano la **composizione degli oggetti** per combinare i comportamenti dinamicamente a runtime. Ciò offre una maggiore flessibilità ed evita gerarchie di classi profonde e rigide, difficili da mantenere.

---

<a id="adapter"></a>
## Il Pattern Adapter (Adattatore)

Il pattern **Adapter** consente a oggetti con interfacce incompatibili di collaborare. Funge da traduttore (involucro) che converte le chiamate del codice client in un formato compatibile con un sottosistema di terze parti o legacy.

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
## Il Pattern Decorator (Decoratore)

Il pattern **Decorator** aggiunge dinamicamente nuovi comportamenti agli oggetti posizionandoli all'interno di speciali oggetti wrapper (involucri) che contengono tali comportamenti.

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
## Il Pattern Facade (Facciata)

Il pattern **Facade** fornisce un'interfaccia semplificata a una libreria complessa, un framework o un insieme di classi.

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
## Il Pattern Proxy (Sostituto)

Il pattern **Proxy** fornisce un oggetto sostitutivo o surrogato per controllare l'accesso all'oggetto reale. Può gestire l'inizializzazione pigra (lazy initialization), il controllo degli accessi, la memorizzazione nella cache o la registrazione (logging).

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
## Altri Pattern Strutturali

- **Bridge**: Divide una classe grande o un insieme di classi strettamente correlate in due gerarchie separate (astrazione e implementazione) che possono essere sviluppate in modo indipendente.
- **Composite**: Consente di comporre oggetti in strutture ad albero per rappresentare gerarchie parte-tutto e lavorare con esse come se fossero singoli oggetti.
- **Flyweight**: Consente di inserire più oggetti nella memoria RAM disponibile condividendo parti comuni dello stato tra più oggetti anziché memorizzare tutti i dati in ciascun oggetto.

---

<a id="common-mistakes"></a>
## Errori Comuni

1. **Creare una Facciata Divina (God Facade)**: Inserire la logica di business reale all'interno della classe Facade anziché delegarla ai sottosistemi. La Facade dovrebbe solo coordinare i passaggi.
2. **Confondere Decorator con Adapter**: Tentare di adattare un'interfaccia utilizzando un Decorator, o aggiungere modifiche dinamiche al comportamento utilizzando un Adapter.
3. **Pile di Decorator Troppo Profonde**: Impilare troppi Decorator sopra un singolo oggetto, rendendo difficile il debug delle tracce dello stack.

---

<a id="checklist"></a>
## Lista di Controllo

1. **Interfacce:** Le classi Adapter e Decorator implementano rigorosamente interfacce standard per mantenere il polimorfismo?
2. **Disaccoppiamento:** Il client della Facciata è disaccoppiato dalle classi interne del sottosistema complesso?
3. **Ciclo di vita:** Il Proxy gestisce correttamente il ciclo di vita del soggetto reale (creazione e distruzione)?
4. **Compatibilità:** L'Adapter converte i parametri in modo accurato senza modificare i comportamenti fondamentali?

---

<a id="summary"></a>
## Riepilogo

I design pattern strutturali utilizzano la composizione degli oggetti per costruire sistemi robusti e manutenibili. Utilizza **Adapter** per avvolgere interfacce incompatibili. Utilizza **Decorator** per aggiungere responsabilità agli oggetti dinamicamente senza sottoclassare. Utilizza **Facade** per fornire un accesso semplificato a librerie complesse. Utilizza **Proxy** per gestire il caricamento pigro, la sicurezza o la memorizzazione nella cache.

---

<a id="self-test-quiz"></a>
## Quiz di Autovalutazione

### Domanda 1: Qual è il principale vantaggio di progettazione nell'utilizzare il pattern Decorator invece dell'ereditarietà?
- A) Compila il codice più velocemente e viene eseguito più rapidamente.
- B) Consente di aggiungere o modificare comportamenti dinamicamente a runtime, evitando l'esplosione combinatoria di sottoclassi a tempo di compilazione.
- C) Garantisce la thread safety in PHP.

<details>
<summary><b>Mostra la risposta</b></summary>

**Risposta: B**
L'ereditarietà è statica. Se desideri aggiungere cache e log a un repository, l'ereditarietà ti costringe a creare classi separate (ad esempio, `CachedProductRepository`, `LoggedProductRepository`, `CachedAndLoggedProductRepository`). I Decorator consentono di avvolgerli dinamicamente: `new Caching(new Logging(new SqlRepository()))`.
</details>

### Domanda 2: Nel pattern Proxy, qual è la relazione tra la classe Proxy e la classe del Soggetto Reale?
- A) Il Proxy deve estendere la classe del Soggetto Reale.
- B) Sia il Proxy che il Soggetto Reale devono implementare la stessa interfaccia, consentendo al Proxy di agire come sostituto.
- C) La classe Proxy non può accedere direttamente ai metodi della classe del Soggetto Reale.

<details>
<summary><b>Mostra la risposta</b></summary>

**Risposta: B**
Per consentire ai client di utilizzare il Proxy in modo intercambiabile con il Soggetto Reale, entrambi devono implementare la stessa interfaccia. Ciò rende il proxy trasparente per il codice client.
</details>
