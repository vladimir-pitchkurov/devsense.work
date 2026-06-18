---
title: "Antipattern di Progettazione Software: Spaghetti, God Object, Golden Hammer & Cargo Cult | DevSense"
description: "Padroneggia la progettazione software identificando ed evitando i comuni antipattern in PHP. Impara a rifattorizzare Spaghetti Code, God Object, Golden Hammer, Premature Optimization e Cargo Cult."
published: 2026-06-18
faq:
  - question: "Che cos'è un antipattern di progettazione software?"
    answer: "Un antipattern di progettazione software è una risposta comune e ricorrente a un problema che appare vantaggiosa in superficie, ma che si traduce in conseguenze altamente negative, difficili da mantenere e controproducenti."
  - question: "In che modo un God Object viola i principi SOLID?"
    answer: "Un God Object viola il Single Responsibility Principle (SRP) concentrando troppe responsabilità, azioni e dati in una singola classe. Viola anche l'Open/Closed Principle (OCP) perché la modifica di una singola funzionalità richiede la variazione di questa classe monolitica centrale."
  - question: "Quando il pattern Repository è considerato cargo cult programming?"
    answer: "È considerato cargo cult programming quando si scrive un'interfaccia e una classe repository generica che si limita a racchiudere le query Eloquent (ad esempio find, create, delete) senza aggiungere alcuna reale astrazione di business, vantaggi nei test o valore di disaccoppiamento."
---

# Antipattern di Progettazione Software: Spaghetti, God Object, Golden Hammer & Cargo Cult

Scrivere codice pulito richiede sia la conoscenza di ciò che *non* si deve fare, sia di ciò che si deve fare. Gli antipattern di progettazione software sono cattive pratiche comuni e ricorrenti che inizialmente sembrano buone soluzioni, ma che portano a codebase non mantenibili, fragili e sovra-ingegnerizzate.

In questa guida analizzeremo cinque importanti antipattern di progettazione nello sviluppo PHP moderno, vedremo esempi concreti di come si manifestano e vedremo come rifattorizzarli in strutture pulite e mantenibili.

**Guide correlate:** [Design Pattern Creazionali GoF](creational-design-patterns) · [Design Pattern Strutturali GoF](structural-design-patterns) · [Design Pattern Comportamentali GoF](behavioral-design-patterns)

## Indice

* [Spaghetti Code](#spaghetti-code)
* [The God Object](#god-object)
* [The Golden Hammer](#golden-hammer)
* [Premature Optimization](#premature-optimization)
* [Cargo Cult & Copy-Paste Programming](#cargo-cult)
* [Errori Comuni](#common-mistakes)
* [Checklist](#checklist)
* [Riepilogo](#summary)
* [Quiz di Autovalutazione](#self-test-quiz)

---

<a id="spaghetti-code"></a>
## Spaghetti Code

Lo **Spaghetti Code** è codice con un flusso di controllo aggrovigliato e non strutturato, ricco di blocchi condizionali annidati, responsabilità miste e privo di chiari confini architetturali. Viene chiamato "spaghetti" proprio perché il flusso di esecuzione è intrecciato come un piatto di pasta, rendendolo impossibile da tracciare.

### La via da evitare: Database, Validazione e HTML mescolati

In questo esempio PHP, il routing, l'esecuzione del database, la validazione dell'input e il rendering sono tutti compressi all'interno di un unico file.

```php
// index.php
<?php
$conn = new mysqli("localhost", "root", "", "app");
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["email"]) && filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
        $email = $_conn->real_escape_string($_POST["email"]);
        $res = $conn->query("SELECT * FROM users WHERE email = '$email'");
        if ($res->num_rows === 0) {
            $conn->query("INSERT INTO users (email) VALUES ('$email')");
            echo "<p>User registered!</p>";
        } else {
            echo "<p>Email already exists.</p>";
        }
    } else {
        echo "<p>Invalid email.</p>";
    }
}
?>
<form method="POST">
    <input type="text" name="email" />
    <button type="submit">Register</button>
</form>
```

### La via corretta: Separazione delle Responsabilità (Separation of Concerns)

Rifattorizziamo questo codice separando l'interazione con il database (Repository), le regole di business e di validazione (Controller) e la presentazione visiva (Blade/Template).

```php
// app/Repositories/UserRepository.php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $email): bool
    {
        $stmt = $this->pdo->prepare("INSERT INTO users (email) VALUES (:email)");
        return $stmt->execute(['email' => $email]);
    }
}
```

```php
// app/Http/Controllers/RegisterController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use InvalidArgumentException;

class RegisterController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function register(array $data): string
    {
        $email = $data['email'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format.");
        }

        if ($this->userRepository->findByEmail($email) !== null) {
            return "Email already exists.";
        }

        $this->userRepository->create($email);
        return "User registered!";
    }
}
```

---

<a id="god-object"></a>
## The God Object

Un **God Object** è una classe monolitica che sa troppo o fa troppo. Concentra tutta l'intelligenza dell'applicazione, violando il **Single Responsibility Principle (SRP)**. Le altre classi del sistema diventano semplici contenitori di dati (anemic models) controllati da questa gigantesca classe.

### La via da evitare: Un OrderManager monolitico

Qui, una singola classe gestisce la connessione al gateway di pagamento, il salvataggio nel database, l'invio di e-mail, il calcolo degli sconti e la generazione di PDF.

```php
// app/Services/OrderManager.php
declare(strict_types=1);

namespace App\Services;

class OrderManager
{
    public function processOrder(array $orderData): void
    {
        // 1. Calculate discount
        $total = $orderData['price'];
        if ($orderData['coupon'] === 'SUMMER') {
            $total *= 0.9;
        }

        // 2. Save order to database
        $db = new \PDO("mysql:host=localhost;dbname=shop", "root", "");
        $stmt = $db->prepare("INSERT INTO orders (total, user_id) VALUES (?, ?)");
        $stmt->execute([$total, $orderData['user_id']]);

        // 3. Process payment via Stripe
        $stripe = new \Stripe\StripeClient('sk_test_key');
        $stripe->charges->create([
            'amount' => (int)($total * 100),
            'currency' => 'usd',
            'source' => $orderData['token'],
        ]);

        // 4. Generate PDF invoice
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->Write(10, "Invoice total: " . $total);
        $pdf->Output('F', "/invoices/order.pdf");

        // 5. Send email notification
        mail($orderData['email'], "Order Success", "Your order total is " . $total);
    }
}
```

### La via corretta: Servizi Disaccoppiati

Rifattorizziamo delegando ciascuna attività di dominio a un componente dedicato, mantenendo la classe manager come coordinatore leggero.

```php
// app/Services/OrderService.php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Notification\NotificationInterface;
use App\Services\Invoice\InvoiceGeneratorInterface;

class OrderService
{
    private OrderRepository $repository;
    private DiscountCalculator $discountCalculator;
    private PaymentGatewayInterface $paymentGateway;
    private InvoiceGeneratorInterface $invoiceGenerator;
    private NotificationInterface $notifier;

    public function __construct(
        OrderRepository $repository,
        DiscountCalculator $discountCalculator,
        PaymentGatewayInterface $paymentGateway,
        InvoiceGeneratorInterface $invoiceGenerator,
        NotificationInterface $notifier
    ) {
        $this->repository = $repository;
        $this->discountCalculator = $discountCalculator;
        $this->paymentGateway = $paymentGateway;
        $this->invoiceGenerator = $invoiceGenerator;
        $this->notifier = $notifier;
    }

    public function checkout(array $orderData): void
    {
        $total = $this->discountCalculator->calculate($orderData['price'], $orderData['coupon']);
        
        $order = $this->repository->save($total, $orderData['user_id']);
        
        $this->paymentGateway->charge($total, $orderData['token']);
        
        $invoicePath = $this->invoiceGenerator->generate($order);
        
        $this->notifier->sendSuccessNotification($orderData['email'], $total, $invoicePath);
    }
}
```

---

<a id="golden-hammer"></a>
## The Golden Hammer

Il **Golden Hammer** (il martello d'oro) è il presupposto che una tecnologia o un design pattern preferito sia universalmente applicabile: *"Se tutto ciò che hai è un martello, tutto ti sembrerà un chiodo."*

In PHP, questo accade spesso quando gli sviluppatori cercano di usare pattern strutturali o comportamentali complessi (come State o Visitor) per compiti semplici, portando a un'esplosione non necessaria di classi, oppure quando usano un motore di database (like Elasticsearch o Redis) per attività che potrebbero essere eseguite facilmente direttamente in SQL.

### La via da evitare: Forzare il pattern State per un semplice controllo dell'età

Uno sviluppatore vuole verificare se un utente è autorizzato ad acquistare alcolici. Invece di una semplice condizione, costruisce l'intera infrastruttura del pattern State con molteplici interfacce e classi concrete.

```php
// app/Validation/AgeValidator.php
declare(strict_types=1);

namespace App\Validation;

interface AgeStateInterface {
    public function canPurchaseAlcohol(): bool;
}

class UnderageState implements AgeStateInterface {
    public function canPurchaseAlcohol(): bool { return false; }
}

class AdultState implements AgeStateInterface {
    public function canPurchaseAlcohol(): bool { return true; }
}

class AgeValidator
{
    private AgeStateInterface $state;

    public function __construct(int $age)
    {
        $this->state = $age >= 18 ? new AdultState() : new UnderageState();
    }

    public function check(): bool
    {
        return $this->state->canPurchaseAlcohol();
    }
}
```

### La via corretta: Keep It Simple (KISS)

I design pattern aggiungono complessità e carico cognitivo. Se la logica di business è semplice, scrivila in modo semplice.

```php
// app/Validation/AgeValidator.php
declare(strict_types=1);

namespace App\Validation;

class AgeValidator
{
    private const ALCOHOL_MINIMUM_AGE = 18;

    public static function canPurchaseAlcohol(int $age): bool
    {
        return $age >= self::ALCOHOL_MINIMUM_AGE;
    }
}
```

> [!NOTE]
> **Usa i pattern solo quando la complessità lo richiede**: I design pattern sono progettati per gestire requisiti mutevoli e alta complessità. Se la tua logica di stato ha solo una semplice condizione che non cambierà mai, un design pattern rappresenta una forma di sovra-ingegnerizzazione.

---

<a id="premature-optimization"></a>
## Premature Optimization

La **Premature Optimization** (ottimizzazione prematura) consiste nell'ottimizzare le prestazioni del codice prima di avere prove concrete (tramite strumenti di profilazione come Xdebug o Blackfire) che ci sia effettivamente un collo di bottiglia (bottleneck).

Questo porta a scrivere codice complesso e illeggibile solo per risparmiare microsecondi, ignorando al contempo query del database o richieste API esterne lente che richiedono centinaia di millisecondi.

### La via da evitare: Micro-ottimizzazioni oscure

Uno sviluppatore evita funzioni PHP chiare e utilizza ricerche di stringhe annidate e operazioni bit a bit per analizzare le stringhe di configurazione, pensando che sia più veloce.

```php
// app/Config/Parser.php
declare(strict_types=1);

namespace App\Config;

class Parser
{
    // Cryptic string manipulation to save CPU cycles
    public function parse(string $data): array
    {
        $pos = strpos($data, ':');
        if ($pos === false) return [];
        
        $key = substr($data, 0, $pos);
        $val = substr($data, $pos + 1);
        
        // Bitwise flag check representing boolean configuration
        $isFlagged = (int)$val & 1; 
        
        return [$key => (bool)$isFlagged];
    }
}
```

### La via corretta: Prima la leggibilità del codice

Scrivi codice che sia facile da leggere, testare e mantenere. Se le prestazioni diventano un problema, esegui prima una profilazione, quindi ottimizza laddove si trovano i colli di bottiglia.

```php
// app/Config/Parser.php
declare(strict_types=1);

namespace App\Config;

class Parser
{
    public function parse(string $jsonString): array
    {
        $decoded = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        
        return $decoded;
    }
}
```

---

<a id="cargo-cult"></a>
## Cargo Cult & Copy-Paste Programming

Il **Cargo Cult Programming** è la pratica di copiare pattern di codice, strutture o metodologie senza capire *perché* vengano utilizzati o quale problema risolvano.

Un classico esempio in PHP consiste nel racchiudere i modelli Eloquent di Laravel in un livello Repository vuoto perché "una buona architettura richiede i repository", anche se Eloquent implementa già i pattern Active Record e Query Builder.

### La via da evitare: Wrapper vuoto dell'astrazione Repository

Questo repository wrapper si limita a copiare i metodi di Eloquent, aggiungendo zero astrazione o valore e introducendo al contempo il doppio delle classi da mantenere.

```php
// app/Repositories/PostRepositoryInterface.php
declare(strict_types=1);

namespace App\Repositories;

interface PostRepositoryInterface {
    public function find(int $id);
    public function create(array $data);
}

// app/Repositories/EloquentPostRepository.php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Post;

class EloquentPostRepository implements PostRepositoryInterface
{
    public function find(int $id)
    {
        return Post::find($id);
    }

    public function create(array $data)
    {
        return Post::create($data);
    }
}
```

### La via corretta: Usare direttamente Active Record o creare reali astrazioni

Se il tuo repository non isola il client dal meccanismo di persistenza (ad esempio, restituendo comunque query o modelli Eloquent grezzi), eliminalo e usa direttamente Eloquent.

```php
// app/Http/Controllers/PostController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;

class PostController
{
    public function show(int $id): JsonResponse
    {
        // Use active record pattern directly
        $post = Post::findOrFail($id);
        return response()->json($post);
    }
}
```

> [!TIP]
> **Quando usare i repository**: Usali quando stai implementando una logica di dominio complessa che deve rimanere indipendente dall'ORM (ad esempio, nel Domain-Driven Design), o quando stai creando query personalizzate riutilizzabili che desideri testare in isolamento.

---

<a id="common-mistakes"></a>
## Errori Comuni

1. **Forzare i pattern ovunque**: Implementare design pattern complessi in semplici progetti CRUD.
2. **Scrivere God Class**: Permettere a una classe helper o manager di crescere all'infinito invece di suddividerla in piccoli servizi coesi.
3. **Ottimizzare senza profilare**: Riscrivere cicli PHP puliti in algoritmi complessi perché si "sospetta" che siano lenti, mentre il database esegue query non indicizzate.
4. **Copiare ciecamente**: Copiare pattern enterprise avanzati (como CQRS, Event Sourcing o repository layer) in semplici progetti MVC solo perché sono popolari online.

---

<a id="checklist"></a>
## Checklist

1. **Controllo SRP:** La tua classe ha più di un motivo per cambiare? Se sì, scomponila.
2. **Principio KISS:** Una semplice condizione `if` o una funzione helper possono sostituire una complessa gerarchia OOP?
3. **Profilazione delle prestazioni:** Hai profilato la tua applicazione usando Xdebug o Blackfire prima di applicare micro-ottimizzazioni?
4. **Astrazione a valore aggiunto:** Il tuo repository o livello di astrazione nasconde effettivamente i dettagli di implementazione o è solo un wrapper ridondante?

---

<a id="summary"></a>
## Riepilogo

Gli antipattern di progettazione sono trappole comuni che portano a software rigido e fragile. Lo **Spaghetti Code** deriva dalla mancanza di separazione delle responsabilità. I **God Object** consolidano troppe responsabilità. Il **Golden Hammer** forza soluzioni errate sui problemi. La **Premature Optimization** dà priorità alle micro-prestazioni rispetto alla leggibilità. Il **Cargo Culting** duplica le strutture senza comprenderne il valore architetturale. Mantieni il codice semplice, chiaro e aggiungi design pattern solo quando la complessità lo richiede.

---

<a id="self-test-quiz"></a>
## Quiz di Autovalutazione

### Domanda 1: Quale principio SOLID viene violato direttamente quando una classe funge da "God Object"?
- A) Open/Closed Principle (OCP)
- B) Liskov Substitution Principle (LSP)
- C) Single Responsibility Principle (SRP)

<details>
<summary>Clicca per vedere la risposta</summary>

**Risposta: C**
Un God Object è definito dall'avere molteplici responsabilità (query sul database, integrazioni di pagamento, invio mail, template, formattazione), violando il principio di singola responsabilità (Single Responsibility Principle), il quale stabilisce che una classe dovrebbe avere una sola ragione per cambiare.
</details>

### Domanda 2: Perché la Premature Optimization è considerata un antipattern?
- A) Perché i motori PHP non supportano il codice ottimizzato.
- B) Perché aumenta la complessità del codice e riduce la leggibilità prima che i colli di bottiglia delle prestazioni siano stati dimostrati e analizzati.
- C) Perché le ottimizzazioni del compilatore in PHP 8.x vengono disabilitate automaticamente quando si ottimizza manualmente.

<details>
<summary>Clicca per vedere la risposta</summary>

**Risposta: B**
L'ottimizzazione prematura porta a blocchi di codice complessi e difficili da mantenere in aree dell'applicazione che raramente costituiscono colli di bottiglia. I veri colli di bottiglia sono quasi sempre query di database, I/O del file system o chiamate di rete, non le concatenazioni di stringhe.
</details>

### Domanda 3: Qual è la caratteristica principale della programmazione Cargo Cult?
- A) Copiare ciecamente strutture di codice, design pattern o livelli senza comprenderne lo scopo architetturale o l'effettiva utilità nel progetto corrente.
- B) Migrare il codice legacy su servizi cloud come AWS o Docker.
- C) Scrivere test dopo che il codice di produzione è stato distribuito.

<details>
<summary>Clicca per vedere la risposta</summary>

**Risposta: A**
La programmazione cargo cult si verifica quando gli sviluppatori copiano pattern o livelli architetturali (come repository vuoti, interfacce di servizio o factory astratte) semplicemente perché "è una pratica standard" o "così diceva il tutorial", senza valutare se ciò risolva un problema reale nel loro contesto.
</details>
