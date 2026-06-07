---
title: "PHP Funzionale: Closures, Callables e API per Array Moderne | DevSense"
description: "Padroneggia la programmazione funzionale in PHP. Impara closures, funzioni freccia (7.4+), sintassi first-class callable (8.1+), metodi per array moderni (8.4+) e funzioni pure."
faq:
  - question: "Qual è la differenza tra Closures e Funzioni Freccia in PHP?"
    answer: "Le closures (funzioni anonime) richiedono il binding esplicito delle variabili esterne tramite la parola chiave 'use' e supportano più istruzioni. Le funzioni freccia (PHP 7.4+) catturano automaticamente le variabili esterne per valore (by-value), ma sono limitate a una singola espressione."
  - question: "In che modo la sintassi first-class callable migliora la qualità del codice in PHP 8.1+?"
    answer: "La sintassi first-class callable (ad es. mia_funzione(...)) fornisce supporto per l'analisi statica, l'autocompletamento negli IDE e benefici di prestazioni in fase di esecuzione rispetto alle vecchie chiamate tramite stringhe o array."
  - question: "Quali sono le nuove funzioni per array di PHP 8.4+ per la programmazione funzionale?"
    answer: "PHP 8.4+ introduce array_find(), array_find_key(), array_any() e array_all(), che semplificano operazioni comuni come la ricerca di elementi o la verifica di condizioni senza dover scrivere cicli foreach dettagliati."
  - question: "PHP supporta l'immutabilità nativamente?"
    answer: "Gli array in PHP utilizzano il meccanismo copy-on-write, che agisce come immutabilità, ma gli oggetti sono passati per riferimento. L'immutabilità può essere ottenuta utilizzando proprietà readonly (PHP 8.1+), classi readonly (PHP 8.2+) o clonazione."
---

**Livello Destinatario: Junior / Middle**

Immagina di cercare un bug in produzione in cui un'entità di database condivisa viene mutata in modo imprevisto in una funzione ausiliaria nidificata, causando il fallimento di parti non correlate del ciclo di vita della richiesta. O di dover scrivere un ciclo `foreach` complesso e profondamente nidificato solo per verificare se almeno un utente in un elenco ha completato il proprio profilo. In entrambi i casi, la causa principale è la stessa: la mutazione di stato imperativa e codice di controllo del flusso ridondante.

PHP è noto principalmente per la programmazione orientata agli oggetti, ma possiede un set di strumenti funzionali robusto e sempre più maturo. Adottare la programmazione funzionale in PHP ti consente di scrivere codice più pulito, più testabile e altamente prevedibile trattando il calcolo come la valutazione di funzioni matematiche ed evitando la mutazione di stato.

> **Premessa principale:**
> Transizionare verso una mentalità funzionale in PHP —sfruttando closures, funzioni freccia, callables di prima classe e API per array moderne— elimina i bug legati agli effetti collaterali e sostituisce i cicli ripetitivi con pipeline dichiarative e pulite.

---

## Indice
* [Closures e funzioni anonime](#closures-anonymous-functions)
* [Funzioni freccia (PHP 7.4+)](#arrow-functions)
* [Sintassi First-Class Callable (PHP 8.1+)](#first-class-callables)
* [Operazioni su array di ordine superiore](#higher-order-arrays)
* [La cassetta degli attrezzi funzionale per array in PHP 8.4+](#php84-arrays)
* [Funzioni pure e immutabilità in PHP](#pure-functions)
* [Dimostrazione pratica: Pipeline funzionale moderna](#practical-demo)
* [Limitazioni e compromessi](#limitations-trade-offs)
* [🧠 Quiz di autoverifica](#self-check)

---

<a id="closures-anonymous-functions"></a>
## Closures e funzioni anonime

### Punto
Le Closures (implementate tramite la classe nativa `Closure`) sono funzioni anonime in grado di catturare variabili dall'ambito circostante utilizzando la parola chiave `use`.

### Perché è importante
In PHP, le funzioni non ereditano automaticamente l'accesso alle variabili del loro ambito principale. Le closures ti consentono di impacchettare un blocco di logica insieme al contesto di dati specifico di cui ha bisogno per essere eseguito, rendendole indispensabili per callback, gestori di eventi e operazioni inline.

### Esempio
Quando definisci una closure, devi associare esplicitamente le variabili dell'ambito principale tramite la parola chiave `use`. È inoltre possibile specificare tipi di ritorno (introdotti a partire da PHP 7.0+):

```php
// app/Utils/SearchFilter.php
<?php

declare(strict_types=1);

namespace App\Utils;

class SearchFilter
{
    public function getFilterCallback(string $searchTerm): \Closure
    {
        // Explicitly bind $searchTerm by-value using the 'use' keyword
        return function (array $item) use ($searchTerm): bool {
            return str_contains(strtolower($item['name']), strtolower($searchTerm));
        };
    }
}
```

Se hai bisogno di mutare la variabile principale (cosa generalmente sconsigliata nella programmazione funzionale), devi associarla per riferimento:

```php
// app/Utils/Counter.php
<?php

declare(strict_types=1);

$count = 0;

// Capture by reference using '&'
$increment = function () use (&$count): void {
    $count++;
};

$increment();
echo $count; // Output: 1
```

### Conseguenza
Senza le closures, i sviluppatori dovevano scrivere classi monouso o passare grandi array di stato attraverso molteplici livelli di parametri di funzione. Utilizzando le closures, mantieni la logica locale e sensibile al contesto, sebbene l'associazione manuale tramite `use` possa risultare verbosa in basi di codice di grandi dimensioni.

---

<a id="arrow-functions"></a>
## Funzioni freccia (PHP 7.4+)

### Punto
Le funzioni freccia (introdotte in PHP 7.4+) forniscono una sintassi abbreviata per le funzioni anonime e catturano automaticamente le variabili esterne per valore (by-value).

### Perché è importante
Scrivere `function () use ($x) { return $x * 2; }` introduce un rumore sintattico significativo per operazioni semplici a singola espressione. Le funzioni freccia riducono questo codice ridondante, rendendo la mappatura, il filtraggio e l'ordinamento inline estremamente leggibili.

### Esempio
Le funzioni freccia utilizzano la parola chiave `fn` seguita dai parametri, una doppia freccia `=>` e una singola espressione che viene restituita automaticamente:

```php
// app/Services/TaxCalculator.php
<?php

declare(strict_types=1);

namespace App\Services;

class TaxCalculator
{
    public function applyTaxes(array $prices, float $taxRate): array
    {
        // Automatically captures $taxRate by-value; no 'use' keyword required
        return array_map(fn(float $price): float => $price * (1 + $taxRate), $prices);
    }
}
```

### Conseguenza
Sebbene le funzioni freccia rendano i callback concisi, comportano un limite critico: **possono contenere solo una singola espressione**. Non puoi scrivere più istruzioni o eseguire assegnazioni complesse all'interno di una funzione freccia. Inoltre, le variabili esterne vengono catturate rigorosamente per valore; la modifica di una variabile catturata all'interno della funzione freccia non influisce sull'ambito esterno.

---

<a id="first-class-callables"></a>
## Sintassi First-Class Callable (PHP 8.1+)

### Punto
La sintassi first-class callable (introdotta in PHP 8.1+) ti consente di fare riferimento a qualsiasi funzione o metodo come un oggetto `Closure` utilizzando il segnaposto con tre punti `...`.

### Perché è importante
Prima di PHP 8.1, fare riferimento a metodi o funzioni esistenti richiedeva di passarli come stringhe (`'strlen'`) o array (`[$this, 'formatPrice']`). Questo era incline a errori: gli IDE не potevano risolvere i riferimenti (interrompendo l'autocompletamento e il refactoring), e gli strumenti di analisi statica come PHPStan o Psalm не potevano rilevare errori di battitura fino al tempo di esecuzione.

### Esempio
Confrontiamo il vecchio formato di callback basato su array con la sintassi moderna first-class callable:

```php
// app/Services/StringFormatter.php
<?php

declare(strict_types=1);

namespace App\Services;

class StringFormatter
{
    public function trimAndLower(string $value): string
    {
        return strtolower(trim($value));
    }

    public function processLegacy(array $strings): array
    {
        // ❌ Legacy array-callable: Hard for IDEs to track, open to typos
        return array_map([$this, 'trimAndLower'], $strings);
    }

    public function processModern(array $strings): array
    {
        // ✅ First-class callable syntax (PHP 8.1+): Full IDE support and type-safety
        return array_map($this->trimAndLower(...), $strings);
    }
}
```

Questa sintassi funziona per:
* Funzioni: `strlen(...)`
* Metodi statici: `MathHelper::square(...)`
* Metodi di istanza: `$object->method(...)`
* Oggetti invocabili: `$invokableObject(...)`

### Conseguenza
Passare alla sintassi first-class callable elimina gli errori di runtime basati sulle stringhe, consente agli IDE di rinominare istantaneamente i metodi in tutto il codice e offre un piccolo miglioramento delle prestazioni poiché PHP не deve risolvere dinamicamente il nome del metodo da una stringa.

---

<a id="higher-order-arrays"></a>
## Operazioni su array di ordine superiore

### Punto
Le funzioni per array di ordine superiore —specialmente `array_map`, `array_filter` e `array_reduce`— accettano altre funzioni come argomenti per trasformare, filtrare o aggregare array.

### Perché è importante
Invece di indicare a PHP *come* scorrere e modificare gli array (paradigma imperativo), le funzioni di ordine superiore ti consentono di dichiarare *quale* trasformazione deve essere eseguita (paradigma dichiarativo). Questo isola le mutazioni dei dati ed evita errori.

### Esempio
Ecco come puoi utilizzare le tre funzioni per array principali in uno stile funzionale moderno:

```php
// app/Services/OrderProcessor.php
<?php

declare(strict_types=1);

namespace App\Services;

class OrderProcessor
{
    public function getActiveOrderTotal(array $orders): float
    {
        // 1. Filter: Keep only completed orders
        $completedOrders = array_filter(
            $orders,
            fn(array $order): bool => $order['status'] === 'completed'
        );

        // 2. Map: Extract total prices
        $totals = array_map(
            fn(array $order): float => $order['total'],
            $completedOrders
        );

        // 3. Reduce: Sum all totals starting at 0.0
        return array_reduce(
            $totals,
            fn(float $carry, float $total): float => $carry + $total,
            0.0
        );
    }
}
```

> [!WARNING]
> **La trappola dell'incoerenza dei parametri in PHP**
> Presta molta attenzione all'ordine dei parametri nelle funzioni per array native di PHP:
> * `array_map(callable $callback, array $array)` -> Il callback è **primo**.
> * `array_filter(array $array, callable $callback)` -> Il callback è **secondo**.
> * `array_reduce(array $array, callable $callback, $initial)` -> Il callback è **secondo**.
> 
> Confondere l'ordine di questi parametri è una delle cause più comuni di crash in PHP.

### Conseguenza
Sebbene queste funzioni rendano le pipeline molto espressive, incatenarle genera molteplici copie di array intermedi, il che può aumentare l'uso della memoria su grandi set di dati.

---

<a id="php84-arrays"></a>
## La cassetta degli attrezzi funzionale per array in PHP 8.4+

### Punto
PHP 8.4+ introduce funzioni native per verificare elementi di un array tramite closures senza eseguire iterazioni complete: `array_find()`, `array_find_key()`, `array_any()` e `array_all()`.

### Perché è importante
Prima di PHP 8.4, verificare se un elemento esisteva (`array_any`) o trovare il primo elemento che soddisfaceva una condizione (`array_find`) richiedeva la scrittura di un ciclo `foreach` personalizzato con un'istruzione `break`. L'uso di librerie esterne o la scrittura di cicli personalizzati introduceva codice superfluo.

### Esempio
Vediamo come queste nuove funzioni native di PHP 8.4+ semplificano le verifiche sugli array:

```php
// app/Services/UserVerification.php
<?php

declare(strict_types=1);

namespace App\Services;

class UserVerification
{
    private array $users = [
        ['id' => 1, 'username' => 'alice', 'role' => 'user', 'active' => true],
        ['id' => 2, 'username' => 'bob', 'role' => 'admin', 'active' => false],
        ['id' => 3, 'username' => 'charlie', 'role' => 'user', 'active' => true],
    ];

    public function auditUsers(): void
    {
        // 1. Find the first inactive user
        $inactiveUser = array_find($this->users, fn(array $u) => !$u['active']);
        // Returns: ['id' => 2, 'username' => 'bob', 'role' => 'admin', 'active' => false]

        // 2. Find the key of the first inactive user
        $inactiveKey = array_find_key($this->users, fn(array $u) => !$u['active']);
        // Returns: 1

        // 3. Check if ANY user is an admin
        $hasAdmin = array_any($this->users, fn(array $u) => $u['role'] === 'admin');
        // Returns: true

        // 4. Check if ALL users are active
        $allActive = array_all($this->users, fn(array $u) => $u['active']);
        // Returns: false
    }
}
```

### Conseguenza
Queste funzioni vengono eseguite in modo pigro (lazy execution), il che significa che interrompono l'esecuzione del callback non appena viene determinato il risultato (ad esempio, `array_any` restituisce `true` alla prima corrispondenza). Questo offre prestazioni ottimali rispetto all'esecuzione di un `array_filter` completo e alla verifica del numero di elementi.

---

<a id="pure-functions"></a>
## Funzioni pure e immutabilità in PHP

### Punto
Una **funzione pura** è una funzione che restituisce sempre lo stesso risultato per gli stessi argomenti di input e non produce effetti collaterali (come modificare lo stato globale, alterare parametri passati per riferimento o eseguire operazioni di I/O).

### Perché è importante
Quando una função modifica variabili al di fuori del proprio ambito, crea dipendenze nascoste. Se molteplici parti di un sistema dipendono da questo stato condiviso, i test diventano difficili e il processo di debug dell'ordine di esecuzione diventa complesso.

### Esempio
Gli array in PHP vengono copiati alla scrittura (**copy-on-write**), il che li fa comportare come valori immutabili quando vengono passati alle funzioni. Tuttavia, **gli oggetti in PHP sono passati per riferimento**. Esaminiamo un errore comune in cui una funzione sembra pura ma in realtà modifica un oggetto passato come argomento:

```php
// app/Models/Price.php
<?php

declare(strict_types=1);

namespace App\Models;

class Price
{
    public function __construct(public float $amount) {}
}
```

```php
// app/Services/Billing.php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Price;

class Billing
{
    // ❌ Impure Function: Mutates the incoming $price object!
    public function applyDiscountImpure(Price $price, float $discount): Price
    {
        $price->amount -= $discount; // Side effect: mutates original object!
        return $price;
    }

    // ✅ Pure Function: Uses cloning to guarantee immutability
    public function applyDiscountPure(Price $price, float $discount): Price
    {
        $newPrice = clone $price; // Keeps the original object intact
        $newPrice->amount -= $discount;
        return $newPrice;
    }
}
```

Per applicare l'immutabilità a livello di tipi, utilizza **proprietà di sola lettura** (introdotte in PHP 8.1+) o **classi di sola lettura** (introdotte in PHP 8.2+):

```php
// app/DTO/ImmutablePrice.php
<?php

declare(strict_types=1);

namespace App\DTO;

// Available since PHP 8.2+
readonly class ImmutablePrice
{
    public function __construct(public float $amount) {}

    public function withDiscount(float $discount): self
    {
        // Return a brand-new instance instead of mutating the current one
        return new self($this->amount - $discount);
    }
}
```

### Conseguenza
Scrivendo funzioni pure e utilizzando DTO readonly, garantisci che la chiamata a una funzione non corromperà mai lo stato in altre parti dell'applicazione. Ciò consente di ottenere codice altamente testabile e dal comportamento prevedibile.

---

<a id="practical-demo"></a>
## Dimostrazione pratica: Pipeline funzionale moderna

Creiamo uno scenario pratico. Immaginiamo un endpoint API che analizza i dati inviati dagli utenti, filtra le righe non valide, applica una conversione di valuta e calcola la media totale.

Ecco come possiamo implementarlo in uno stile funzionale con PHP moderno:

```php
// app/Services/ReportGenerator.php
<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\ImmutablePrice;

class ReportGenerator
{
    /**
     * Processes raw sales data and calculates average price.
     * 
     * @param array<array{amount: float, valid: bool}> $rawData
     * @return float
     */
    public function calculateAverageValidPrice(array $rawData): float
    {
        // 1. Filter: Keep only valid records
        $validItems = array_filter(
            $rawData,
            fn(array $row): bool => $row['valid'] === true
        );

        // 2. Map: Convert to ImmutablePrice DTO objects (using PHP 8.1+ readonly properties)
        $prices = array_map(
            fn(array $row): ImmutablePrice => new ImmutablePrice($row['amount']),
            $validItems
        );

        // 3. Check (PHP 8.4+): Ensure no price is negative
        $hasNegativePrice = array_any($prices, fn(ImmutablePrice $p) => $p->amount < 0);
        if ($hasNegativePrice) {
            throw new \InvalidArgumentException("Reports cannot contain negative prices.");
        }

        if (count($prices) === 0) {
            return 0.0;
        }

        // 4. Reduce: Sum the values of the immutable DTOs
        $totalSum = array_reduce(
            $prices,
            fn(float $carry, ImmutablePrice $price): float => $carry + $price->amount,
            0.0
        );

        return $totalSum / count($prices);
    }
}
```

### Caso di errore: Mutazione di stato imperativa e riferimenti condivisi

In confronto, guarda come questa stessa logica viene tipicamente scritta in modo imperativo, il che la espone a bug se gli elementi dell'array vengono modificati per riferimento:

```php
// app/Services/LegacyReportGenerator.php
<?php

declare(strict_types=1);

namespace App\Services;

class LegacyReportGenerator
{
    // ❌ Error-Prone Imperative Approach
    public function calculateAverage(array &$rawData): float // Passed by reference!
    {
        $sum = 0.0;
        $count = 0;

        foreach ($rawData as &$row) { // Reference iteration
            if ($row['amount'] < 0) {
                throw new \InvalidArgumentException("Negative price found.");
            }
            if ($row['valid']) {
                // Modifying the original data array (Side effect!)
                $row['amount'] = $row['amount'] * 1.0; 
                $sum += $row['amount'];
                $count++;
            }
        }
        unset($row); // If forgotten, the last item remains bound by reference!

        return $count > 0 ? $sum / $count : 0.0;
    }
}
```

### Perché l'approccio funzionale è superiore:
1. **Sicurezza dei riferimenti:** Il generatore legacy lascia `$row` legato per riferimento se `unset($row)` viene omesso, il che può causare mutazioni accidentali se quella variabile viene riutilizzata in seguito.
2. **Immutabilità:** L'array di dati originale rimane intatto.
3. **Leggibilità:** Passaggi di trasformazione chiari invece di blocchi `if` nidificati all'interno di un ciclo `foreach`.

---

<a id="limitations-trade-offs"></a>
## Limitazioni e compromessi

Sebbene il PHP funzionale sia potente, PHP non è Haskell o Scala. Devi essere consapevole dei suoi compromessi:

1. **Nessuna ottimizzazione della ricorsione terminale (TCO):** PHP non supporta la TCO. Scrivere funzioni altamente ricorsive causerà rapidamente il superamento della profondità massima dello stack di chiamate, provocando una saturazione della memoria o errori fatali di overflow dello stack. Utilizza l'iterazione invece di una ricorsione pesante.
2. **Prestazioni e consumo di memoria:** L'incatenamento di funzioni come `array_map` und `array_filter` crea nuovi array ad ogni passaggio. Per set di dati con milioni di elementi, ciò può causare significativi picchi di memoria. In scenari ad alte prestazioni, un ciclo `foreach` imperativo semplice che elabora elementi inline è più veloce ed efficiente in termini di memoria.
3. **Nessun operatore pipeline nativo:** PHP è privo di un operatore pipeline nativo (come `|>` in Elixir). L'incatenamento di operazioni richiede l'annidamento di funzioni (`array_reduce(array_map(...))`) o la dichiarazione di variabili temporanee.
4. **Incoerenza dei parametri:** Come dimostrato in precedenza, devi verificare costantemente le posizioni dei callback poiché le funzioni native di PHP hanno firme opposte.

---

## Conclusione pratica

* **Utilizza le funzioni freccia** (PHP 7.4+) per semplici trasformazioni a singola espressione per mantenere il tuo codice pulito e conciso.
* **Adotta i callables di prima classe** (PHP 8.1+) al tempo di riferimenti di stringhe o array per garantire una copertura completa dall'IDE e dall'analisi statica.
* **Sostituisci i cicli di ricerca con metodi nativi di PHP 8.4+** (`array_find`, `array_any`, ecc.) per interrompere le iterazioni il prima possibile.
* **Previeni la mutazione degli oggetti** dichiarando classi come `readonly` (PHP 8.2+) o utilizzando `clone` in funzioni pure.
* **Privilegia i cicli foreach** durante l'elaborazione di set di dati molto grandi in cui il consumo di memoria e la velocità sono critici.

---

<a id="self-check"></a>
## 🧠 Quiz di autoverifica

Prova a rispondere alle seguenti domande per verificare la tua comprensione:

1. Perché modificare una variabile all'interno di una funzione freccia (PHP 7.4+) non aggiorna quella stessa variabile nell'ambito principale?
2. Qual è la differenza di sintassi tra un callback legacy (stringa/array) e la sintassi first-class callable di PHP 8.1+?
3. Quale delle funzioni di aiuto di PHP 8.4+ si arresterà immediatamente dopo aver trovato il primo elemento che soddisfa i suoi criteri?

<details>
<summary><b>Mostra Risposte</b></summary>

1. Le funzioni freccia catturano le variabili dell'ambito principale strettamente **per valore** (by-value). Ciò significa che ricevono una copia della variabile, per cui le modifiche interne non influiscono sulla variabile principale originale.
2. I vecchi callback utilizzano stringhe (`'strlen'`) o array (`[$this, 'methodName']`), che non possono essere verificati dagli analizzatori statici o dagli IDE. I callables di prima classe utilizzano il nome della funzione o del metodo seguito da tre punti (`strlen(...)` o `$this->methodName(...)`), creando un vero oggetto `Closure`.
3. Sia `array_find()` che `array_find_key()` si arrestano non appena incontrano il primo elemento per il quale il callback restituisce `true`. Allo stesso modo, `array_any()` si arresta prematuramente e restituisce `true` alla prima corrispondenza.
</details>
