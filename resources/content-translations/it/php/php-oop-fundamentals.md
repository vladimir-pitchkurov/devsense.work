---
title: "Fondamenti di OOP in PHP: Dai concetti base alla visibilità asimmetrica | DevSense"
description: "Padroneggia la programmazione orientata agli oggetti moderna in PHP. Impara incapsulamento, ereditarietà, interfacce, classi astratte, trait, classi anonime (7.0+), proprietà readonly (8.1+), classi readonly (8.2+) e visibilità asimmetrica (8.4+)."
faq:
  - question: "Qual è la differenza tra un'interfaccia e una classe astratta in PHP?"
    answer: "Un'interfaccia definisce un contratto pubblico puro che le classi implementatrici devono soddisfare, consentendo implementazioni multiple. Una classe astratta è una classe parzialmente implementata che può contenere stato, logica del costruttore e metodi protetti. Tuttavia, poiché PHP supporta solo l'ereditarietà singola, una classe può estendere solo una classe astratta."
  - question: "Come funzionano le proprietà readonly in PHP 8.1+?"
    answer: "Le proprietà readonly possono essere inizializzate solo una volta, in genere all'interno del costruttore della classe. Qualsiasi tentativo successivo di modificare o rimuovere (unset) la proprietà genererà un'eccezione di tipo Error, garantendo l'immutabilità."
  - question: "Cos'è la visibilità asimmetrica in PHP 8.4+?"
    answer: "La visibilità asimmetrica consente di dichiarare diversi livelli di acesso per la lettura e la scrittura di una proprietà. Ad esempio, 'public private(set) string $name' consente l'accesso in lettura pubblico ma limita l'accesso in scrittura alla classe stessa, eliminando il codice ripetitivo dei getter."
  - question: "Come si risolvono le collisioni di nomi dei trait in PHP?"
    answer: "Quando due trait definiscono un metodo con lo stesso nome, PHP genera un errore fatale a meno che non si risolva esplicitamente la collisione utilizzando l'operatore 'insteadof' per scegliere un metodo, o l'operatore 'as' per creare un alias per il metodo sotto un nuovo nome."
---

# Fondamenti di OOP in PHP: Proteggere lo stato e definire interfacce pulite

Se hai mai passato ore a fare il debug di una misteriosa mutazione di stato in una grande applicazione web, sai quanto possono essere fragili gli oggetti mal incapsulati. Un servizio modifica una proprietà pubblica su un'istanza condivisa e, all'improvviso, i record del database in tutto il sistema vengono corrotti. Il problema di fondo non è la programmazione orientata agli oggetti (OOP) in sé, ma piuttosto la nostra incapacità di imporre confini rigidi tra i nostri oggetti.

Nel codice procedurale, i dati vengono passati a livello globale, lasciandoli vulnerabili alle modifiche. Nella OOP ingenua, le classi vengono trattate come semplici contenitori di proprietà, esponendo tutto tramite variabili pubbliche o getter e setter generici. Il PHP moderno risolve questi problemi di architettura passando da oggetti aperti e mutabili a strutture rigide e autonome.

> [!IMPORTANT]
> La OOP moderna in PHP non consiste solo nell'organizzare le funzioni in classi; si tratta di imporre confini di stato forti, definire contratti pubblici rigidi e utilizzare la sicurezza dei tipi per prevenire stati di runtime non validi.

**Livello target**: Junior / Middle

---

## Indice
* [Incapsulamento e modificatori di accesso](#encapsulation-modifiers)
* [Contratti e astrazione: Ereditarietà, classi astratte e interfacce](#contracts-abstraction)
* [Riuso orizzontale e comportamento dinamico: Trait e classi anonime (PHP 7.0+)](#horizontal-reuse)
* [Immutabilità per impostazione predefinita: Proprietà readonly (PHP 8.1+) e classi readonly (PHP 8.2+)](#immutability)
* [Confini granulari: Visibilità asimmetrica (PHP 8.4+)](#asymmetric-visibility)
* [Compromessi e limitazioni architetturali](#trade-offs)
* [🧠 Domande di autovalutazione](#self-check)

---

<a id="encapsulation-modifiers"></a>
## Incapsulamento e modificatori di accesso

### 1. Punto chiave
L'incapsulamento è la pratica di nascondere lo stato interno di un oggetto e richiedere che tutte le interazioni passino attraverso un'interfaccia pubblica. PHP controlla questo accesso utilizzando tre modificatori:
* `public`: Accessibile da qualsiasi luogo.
* `protected`: Accessibile solo all'interno della classe stessa e dalle classi ereditate.
* `private`: Accessibile solo all'interno della classe che lo definisce.

### 2. Perché è importante
Senza incapsulamento, i servizi esterni possono modificare lo stato interno di un oggetto a sua insuccesso, aggirando le regole di validazione. Ad esempio, se il saldo di un conto bancario (`BankAccount`) può essere modificato direttamente dall'esterno, non possiamo garantire che il saldo non scenda mai al di sotto dello zero o che le transazioni vengano registrate correttamente nel registro.

### 3. Esempio
Vediamo come l'esposizione di proprietà pubbliche porti alla corruzione dello stato e come i modificatori privati impongano invarianti:

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

### 4. Conseguenza
Dichiarando `$balance` come privato, la classe mantiene il pieno controllo sulla sua validazione. Il codice esterno non può eseguire `$account->balance = -500.0;`. Le transizioni di stato possono avvenire solo attraverso operazioni di business valide (`deposit` e `withdraw`), garantendo che il registro (ledger) e il saldo siano sempre sincronizzati.

---

<a id="contracts-abstraction"></a>
## Contratti e astrazione: Ereditarietà, classi astratte e interfacce

### 1. Punto chiave
PHP consente agli sviluppatori di progettare comportamenti riutilizzabili e confini architetturali rigorosi attraverso l'ereditarietà e il polimorfismo:
* **Ereditarietà (`extends`)**: Consente a una classe figlia di ereditare proprietà e metodi da una classe padre. Le classi figlie possono sovrascrivere i metodi del padre a meno che il metodo o la classe padre non siano contrassegnati come `final`.
* **Classi astratte (`abstract class`)**: Modelli che non possono essere istanziati da soli. Possono contenere metodi completamente implementati e firme di metodi `abstract` che le classi figlie devono implementare.
* **Interfacce (`interface`)**: Contratti puri contenenti solo firme di metodi senza implementazione. Una classe può implementare più interfacce, risolvendo il limite di ereditarietà singola di PHP.

### 2. Perché è importante
L'ereditarietà consente di condividere la logica comune, ma crea un forte accoppiamento (tight coupling) tra padre e figlio. Le classi astratte fungono da via di mezzo, offrendo un progetto parziale. Le interfacce rappresentano il massimo livello di disaccoppiamento (decoupling): definiscono *cosa* un oggetto può fare, non *come* lo fa. Ciò consente di sostituire gli adattatori di database, i driver di posta o i gateway di pagamento senza modificare la logica dell'applicazione che dipende da essi.

### 3. Esempio
Ecco come costruiamo un contratto di elaborazione dei pagamenti utilizzando un'interfaccia, una base astratta e implementazioni concrete:

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

### 4. Conseguenza
Le classi di alto livello (come un `CheckoutController`) possono dipendere da `PaymentGatewayInterface` anziché da classi concrete. Possiamo sostituire `StripeGateway` con `PaypalGateway` tramite Dependency Injection, o simulare (mock) completamente il gateway nei test PHPUnit, senza modificare la logica di checkout.

---

<a id="horizontal-reuse"></a>
## Riuso orizzontale e comportamento dinamico: Trait e classi anonime (PHP 7.0+)

### 1. Punto chiave
PHP è un linguaggio a ereditarietà singola. Per prevenire la duplicazione del codice senza imporre gerarchie di classi, PHP offre due meccanismi:
* **Trait**: Blocchi di codice che possono essere inseriti nelle classi per condividere metodi orizzontalmente. In caso di conflitti di nomi tra due trait, questi devono essere risolti utilizzando gli operatori `insteadof` e `as`.
* **Classi anonime (PHP 7.0+)**: Classi leggere e senza nome dichiarate al volo. Sono utili per implementazioni semplici e temporanee di interfacce o classi astratte.

### 2. Perché è importante
Imporre gerarchie di classi solo per condividere il codice (come la registrazione dei log o la cancellazione logica - soft deletes) porta a classi padre fragili e alberi di ereditarietà disordinati. I trait risolvono questo problema consentendo l'importazione di più comportamenti indipendenti in una singola classe. Le classi anonime evitano il codice ripetitivo (boilerplate) quando è necessario simulare una classe o fornire un'implementazione monouso per un test o una callback.

### 3. Esempio
Il codice seguente mostra un trait con risoluzione delle collisioni e una classe anonima utilizzata per il logging nei test:

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

### 4. Conseguenza
Utilizzando i trait, `AuditService` acquisisce comportamenti condivisi da più fonti senza la necessità di estendere una classe di base comune. Con le classi anonime, possiamo istanziare implementazioni in linea di `MailerInterface` per test rapidi o piccoli script, mantenendo il file system pulito da classi a uso singolo.

---

<a id="immutability"></a>
## Immutabilità per impostazione predefinita: Proprietà readonly (PHP 8.1+) e classi readonly (PHP 8.2+)

### 1. Punto chiave
PHP introduce strumenti moderni per imporre l'immutabilità dello stato a livello di motore (engine):
* **Proprietà readonly (PHP 8.1+)**: Proprietà che possono essere scritte solo una volta (di solito nel costruttore). Modifiche successive o tentativi di eliminarle tramite `unset()` genereranno un `Error`. Devono essere tipizzate; le proprietà non tipizzate non possono essere contrassegnate come readonly.
* **Classi readonly (PHP 8.2+)**: Zucchero sintattico che contrassegna implicitamente tutte le proprietà di una classe come `readonly` e impedisce la creazione di proprietà dinamiche.

### 2. Perché è importante
I Data Transfer Object (DTO) e le impostazioni di configurazione vengono passati attraverso molti livelli di un'applicazione. Se queste strutture sono mutabili, qualsiasi servizio lungo il ciclo di vita della richiesta potrebbe accidentalmente alterare i loro valori, portando a bug silenziosi. Imporre l'immutabilità a livello di compilatore garantisce che una volta costruito un DTO, i suoi dati rimangano identici durante tutto il runtime.

### 3. Esempio
Vediamo come imponiamo l'immutabilità su un DTO utente:

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

### 4. Conseguenza
Qualsiasi codice che consuma `$config` ha la guaranzia che la configurazione dell'utente non cambierà a metà della richiesta. Ciò elimina la necessità di proprietà private ripetitive e metodi di sola lettura (getters), semplificando drasticamente il codice del DTO.

---

<a id="asymmetric-visibility"></a>
## Confini granulari: Visibilità asimmetrica (PHP 8.4+)

### 1. Punto chiave
PHP 8.4+ introduce la **Visibilità asimmetrica**, che consente di definire diversi livelli di accesso per la lettura e la scrittura di una proprietà sulla stessa riga.
* Sintassi: `public private(set) Type $property`
* La visibilità di lettura (ad es. `public`) viene specificata per prima.
* La visibilità di scrittura (ad es. `private(set)` o `protected(set)`) viene specificata subito dopo.

### 2. Perché è importante
Prima di PHP 8.4, si desiderava che una proprietà fosse leggibile pubblicamente ma scrivibile solo all'interno della classe, era necessario rendere la proprietà `private` e scrivere un metodo getter pubblico, oppure rendere la classe/proprietà `readonly`. Tuttavia, `readonly` impedisce *qualsiasi* mutazione interna dopo l'inizializzazione. La visibilità asimmetrica consente a una classe di modificare le proprie proprietà internamente, esponendole al contempo come di sola lettura verso l'esterno, senza alcun codice ripetitivo per i getter.

### 3. Esempio
Ecco un'entità `Product` il cui prezzo può variare nel tempo, ma solo tramite i metodi della classe:

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

### 4. Conseguenza
Otteniamo la sintassi pulita delle proprietà pubbliche per la lettura dei valori, mantenendo al contempo un incapsulamento rigoroso. Non abbiamo più bisogno di scrivere codice ripetitivo come `public function getPriceInCents(): int { return $this->priceInCents; }`.

---

<a id="trade-offs"></a>
## Compromessi e limitazioni architetturali

Nessun pattern è una soluzione magica. L'applicazione delle funzionalità OOP senza valutarne i compromessi porta a codebase sovrastimate o difficili da mantenere.

### 1. Accoppiamento forte tramite ereditarietà (Il problema della classe base fragile)
Sebbene l'estensione delle classi sia semplice, essa introduce un accoppiamento forte. Se una classe padre (`AbstractPaymentGateway`) modifica la firma del suo costruttore, ogni singola classe figlia deve essere aggiornata. Favorisci la **composizione rispetto all'ereditarietà** iniettando le dipendenze anziché estendere le classi base.

### 2. La magia nascosta dei trait
I trait possono facilmente nascondere una cattiva architettura. Sembrano un copia-incolla assistito dal compilatore, rendendo facile la creazione di dipendenze nascoste e collisioni di metodi. Se più classi necessitano della stessa logica, considera la creazione di una classe helper dedicata e iniettala come dipendenza.

### 3. Limitazioni delle proprietà readonly
Le proprietà readonly (PHP 8.1+) non possono essere reimpostate o modificate, nemmeno all'interno della classe. Se è necessario modificare lo stato internamente (ad esempio, per tracciare le modifiche di stato o invalidare la cache), le proprietà readonly non funzioneranno. Inoltre, non possono avere valori predefiniti e non possono essere utilizzate con gli hook di proprietà (property hooks in PHP 8.4+).

### 4. Vincoli della visibilità asimmetrica
La visibilità asimmetrica (PHP 8.4+) richiede che l'operazione di scrittura (`set`) sia strettamente più limitata rispetto all'operazione di lettura (`get`). Ad esempio, `private public(set)` non è valido e genererà un errore di sintassi. Inoltre, le proprietà con visibilità asimmetrica non possono essere passate per riferimento:

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

## Consiglio pratico: L'albero decisionale della visibilità

Quando definisci le proprietà di una classe, segui questa regola empirica per mantenere sicuro l'incapsulamento e pulito il codice:

1. **La proprietà cambia mai dopo l'istanziazione nel costruttore?**
   * **No**: Usa una proprietà `readonly` (PHP 8.1+) o una classe `readonly` (PHP 8.2+).
   * **Sì**: Procedi al passaggio 2.
2. **Il codice esterno deve essere in grado di modificare il valore direttamente?**
   * **No**: Usa `public private(set)` (PHP 8.4+) per esporre il valore in lettura senza esporre l'accesso in scrittura.
   * **Sì**: Usa una proprietà `public` standard (tenendo presente che questo aggira la validazione).

---

## 🧠 Domande di autovalutazione

1. **Perché PHP genera un errore fatale (Fatal Error) se si tenta di dichiarare una proprietà come `private public(set)` in PHP 8.4+?**
2. **Vero o Falso?** Una classe figlia può sovrascrivere un metodo nella classe padre anche se il metodo padre è preceduto dalla parola chiave `final`.
3. **Cosa succede se si tenta di rimuovere (unset) o reinizializzare una proprietà `readonly` dopo che è già stata impostata?**

<details>
<summary><b>Mostra risposte</b></summary>

1. La visibilità di scrittura (`set`) deve essere uguale o più restrittiva della visibilità di lettura. Poiché `private(set)` è più restrittivo di `public`, `public private(set)` è valida. Tuttavia, `public(set)` è più ampia della visibilità di lettura `private`, il che è illogico e viene rifiutato dal parser.
2. **Falso.** Contrassegnare una classe o un metodo come `final` impedisce alle classi figlie di sovrascrivere quel metodo specifico o di ereditare dalla classe.
3. Genera un'eccezione di tipo `Error`: *Cannot modify readonly property...* o *Cannot unset readonly property...*.
</details>
