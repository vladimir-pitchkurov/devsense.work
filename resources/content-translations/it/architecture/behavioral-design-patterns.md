---
title: "Pattern Comportamentali GoF: Chain of Responsibility, Observer, Strategy e Command | DevSense"
description: "Una guida dettagliata ai design pattern comportamentali GoF in PHP: Strategy, Observer, Chain of Responsibility, Command, Iterator, Mediator, Memento, State, Template Method, Visitor e Interpreter con esempi chiari in PHP."
published: 2026-06-18
faq:
  - question: "Qual è la differenza fondamentale tra i pattern Strategy e State?"
    answer: "Il pattern Strategy viene utilizzato quando si desidera scegliere o sostituire un algoritmo o una strategia di esecuzione specifica a runtime, e il client è generalmente a conoscenza delle strategie. Il pattern State viene utilizzato quando un oggetto si comporta in modo diverso in base al suo stato interno, e le transizioni di stato vengono gestite automaticamente dagli oggetti di stato stessi, solitamente in modo nascosto al client."
  - question: "In cosa si differenzia il pattern Observer da Publish-Subscribe?"
    answer: "Nel pattern Observer, il soggetto (publisher) mantiene un elenco diretto dei suoi osservatori e li notifica direttamente. In Publish-Subscribe, c'è un broker di messaggi o un canale di eventi intermedio che disaccoppia completamente gli editori dagli abbonati, in modo che non si conoscano tra loro."
  - question: "Quando si dovrebbe utilizzare Chain of Responsibility anziché un Decorator?"
    answer: "Utilizza Chain of Responsibility quando uno qualsiasi di molteplici oggetti gestori può elaborare una richiesta (o passarla al successivo) e la richiesta può essere interrotta o cortocircuitata in qualsiasi fase. Utilizza un Decorator quando si desidera eseguire tutti gli involucri in una pila per arricchire il comportamento dell'oggetto centrale, senza cortocircuitare o selezionare un singolo gestore."
---

# Pattern Comportamentali GoF: Chain of Responsibility, Observer, Strategy e Command

I design pattern comportamentali si occupano degli algoritmi e dell'assegnazione delle responsabilità tra gli oggetti. Descrivono non solo i pattern di oggetti o classi, ma anche i pattern di comunicazione tra di essi. Questi pattern caratterizzano flussi di controllo complessi che sono difficili da seguire a runtime.

**Guide correlate:** [Pattern Strutturali GoF](structural-design-patterns) · [Dall'architettura monolitica a quella a microservizi](monolith-to-microservices-architecture)

## Indice

* [Introduzione ai Pattern Comportamentali](#intro)
* [Il Pattern Strategy (Strategia)](#strategy)
* [Il Pattern Observer (Osservatore)](#observer)
* [Il Pattern Chain of Responsibility (Catena di responsabilità)](#chain-of-responsibility)
* [Il Pattern Command (Comando)](#command)
* [Altri Pattern Comportamentali (Iterator, Mediator, Memento, State, Template Method, Visitor, Interpreter)](#others)
* [Errori Comuni](#common-mistakes)
* [Lista di Controllo](#checklist)
* [Riepilogo](#summary)
* [Quiz di Autovalutazione](#self-test-quiz)

---

<a id="intro"></a>
## Introduzione ai Pattern Comportamentali

Mentre i pattern creazionali si concentrano sull'istanziazione degli oggetti e i strutturali sulla composizione delle classi, i **pattern comportamentali** si concentrano sulla comunicazione. Definiscono modi puliti per delegare azioni, instradare richieste, gestire i cambiamenti di stato e scambiare algoritmi a runtime in modo dinamico.

---

<a id="strategy"></a>
## Il Pattern Strategy (Strategia)

Il pattern **Strategy** definisce una famiglia di algoritmi, ne incapsula ciascuno e li rende intercambiabili. Strategy consente all'algoritmo di variare indipendentemente dai client che lo utilizzano.

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
## Il Pattern Observer (Osservatore)

Il pattern **Observer** definisce una dipendenza uno-a-molti tra oggetti in modo che, quando un oggetto cambia stato, tutti i suoi dipendenti vengono notificati e aggiornati automaticamente.

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
## Il Pattern Chain of Responsibility (Catena di responsabilità)

Il pattern **Chain of Responsibility** passa le richieste lungo una catena di gestori. Alla ricezione di una richiesta, ciascun gestore decide se elaborare la richiesta o passarla al gestore successivo nella catena.

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
## Il Pattern Command (Comando)

Il pattern **Command** trasforma una richiesta in un oggetto autonomo che contiene tutte le informazioni sulla richiesta stessa. Questa trasformazione consente di passare le richieste come argomenti di metodi, ritardare o accodare l'esecuzione di una richiesta e supportare operazioni annullabili.

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
## Altri Pattern Comportamentali

- **Iterator**: Consente di scorrere gli elementi di una collezione senza esporre la sua rappresentazione interna (lista, pila, albero, ecc.).
- **Mediator**: Limita le comunicazioni dirette tra gli oggetti e li costringe a collaborare solo tramite un oggetto mediatore.
- **Memento**: Consente di salvare e ripristinare lo stato precedente di un oggetto senza rivelare i dettagli della sua implementazione.
- **State**: Consente a un oggetto di alterare il proprio comportamento quando cambia il suo stato interno. L'oggetto sembrerà cambiare classe.
- **Template Method**: Definisce lo scheletro di un algoritmo nella superclasse, ma consente alle sottoclassi di sovrascrivere passaggi specifici senza modificarne la struttura generale.
- **Visitor**: Consente di separare gli algoritmi dagli oggetti su cui operano.
- **Interpreter**: Valuta la grammatica o le espressioni di un linguaggio utilizzando una struttura di classi specializzata.

---

<a id="common-mistakes"></a>
## Errori Comuni

1. **Abuso del pattern Observer**: Creare troppi sottoscrittori di eventi globali. Ciò porta a effetti collaterali nascosti e rende difficile il tracciamento delle azioni durante il debug.
2. **Accoppiamento rigido nella Catena di responsabilità**: Codificare la sequenza direttamente all'interno dei gestori anziché assemblare la catena in un livello di configurazione o in un orchestratore.
3. **Scegliere State anziché Strategy**: Aggiungere proprietà di gestione dello stato a oggetti Strategy che dovrebbero normalmente rimanere privi di stato e indipendenti.

---

<a id="checklist"></a>
## Lista di Controllo

1. **Disaccoppiamento:** I vostri osservatori continuano a ignorare la presenza e i comportamenti reciproci?
2. **Flessibilità:** Le strategie possono essere sostituite dinamicamente a runtime senza dover riavviare l'oggetto di contesto principale?
3. **Interruzione della catena:** I gestori di richieste interrompono l'esecuzione (short-circuit) immediatamente quando le condizioni di convalida falliscono?
4. **Disaccoppiamento del comando:** La classe invitatrice (invoker) rimane disaccoppiata dall'effettivo ricevitore (receiver) del comando?

---

<a id="riepilogo"></a>
## Riepilogo

I design pattern comportamentali regolano il modo in care le classi comunicano e condividono le responsabilità. Utilizza **Strategy** per scambiare gli algoritmi principali al volo. Utilizza **Observer** per progettare notifiche push o sistemi basati su eventi/ascoltatori. Utilizza **Chain of Responsibility** per creare flussi di middleware. Utilizza **Command** per rappresentare attività come oggetti in code di comandi.

---

<a id="self-test-quiz"></a>
## Quiz di Autovalutazione

### Domanda 1: Qual è il sintomo principale che indica la necessità di utilizzare il pattern Strategy?
- A) Un singolo metodo contiene un blocco massiccio di istruzioni `if-else` o `switch-case` annidate che selezionano modi diversi di eseguire lo stesso compito.
- B) È necessario scrivere un metodo di backup che salvi gli stati del database.
- C) Si desidera memorizzare nella cache dinamicamente i risultati delle query del database.

<details>
<summary><b>Mostra la risposta</b></summary>

**Risposta: A**
Quando si vedono più condizioni che selezionano diversi algoritmi (ad esempio, `if ($provider === 'Stripe')` o `else if ($provider === 'PayPal')`), ciò indica una chiara necessità del pattern Strategy. Sostituire queste condizioni con il polimorfismo produce un codice pulito conforme al principio aperto/chiuso.
</details>

### Domanda 2: Nella Catena di responsabilità (Chain of Responsibility), in che modo i gestori decidono se passare la richiesta?
- A) Ciascun gestore deve sempre passare la richiesta; il cortocircuito non è consentito.
- B) Un gestore elabora la richiesta e restituisce un risultato (interrompendo la catena) o passa il controllo al gestore successivo chiamando il suo metodo `handle()`.
- C) La sequenza della catena viene determinata in modo casuale ad ogni richiesta.

<details>
<summary><b>Mostra la risposta</b></summary>

**Risposta: B**
Il design fondamentale della Catena di responsabilità prevede che i gestori decidano dinamicamente se interrompere l'esecuzione (ad esempio, quando fallisce la convalida dell'autenticazione) o se delegare l'elaborazione lungo la catena chiamando il gestore successivo.
</details>
