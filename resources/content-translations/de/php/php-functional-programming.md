---
title: "Funktionale Programmierung in PHP: Closures, Callables und moderne Array-APIs | DevSense"
description: "Meistern Sie die funktionale Programmierung in PHP. Lernen Sie Closures, Arrow Functions (7.4+), First-Class Callable Syntax (8.1+), moderne Array-Methoden (8.4+) und Pure Functions."
faq:
  - question: "Was ist der Unterschied zwischen Closures und Arrow Functions in PHP?"
    answer: "Closures (anonyme Funktionen) erfordern eine explizite Variablenbindung mit dem Schlüsselwort 'use' und unterstützen mehrere Anweisungen. Arrow Functions (PHP 7.4+) erfassen äußere Variablen automatisch per Wert (by-value), sind jedoch auf einen einzelnen Ausdruck beschränkt."
  - question: "Wie verbessert die First-Class Callable Syntax die Codequalität in PHP 8.1+?"
    answer: "Die First-Class Callable Syntax (z. B. my_function(...)) bietet statische Analyseunterstützung, Autovervollständigung in IDEs und Vorteile bei der Laufzeitperformance im Vergleich zu älteren String- oder Array-Callables."
  - question: "Was sind die neuen PHP 8.4+ Array-Funktionen für die funktionale Programmierung?"
    answer: "PHP 8.4+ führt array_find(), array_find_key(), array_any() und array_all() ein, die häufige Operationen wie das Suchen von Elementen oder das Überprüfen von Bedingungen vereinfachen, ohne dass wortreiche foreach-Schleifen geschrieben werden müssen."
  - question: "Unterstützt PHP die Unveränderlichkeit (Immutability) von Haus aus?"
    answer: "PHP-Arrays verwenden Copy-on-Write, was sich wie Unveränderlichkeit verhält, aber Objekte werden als Referenz übergeben. Unveränderlichkeit kann durch readonly-Eigenschaften (PHP 8.1+), readonly-Klassen (PHP 8.2+) oder Klonen erreicht werden."
---

**Zielgruppe: Junior / Middle**

Stellen Sie sich vor, Sie suchen nach einem Fehler im Produktivsystem, bei dem eine gemeinsam genutzte Datenbankentität in einer verschachtelten Hilfsfunktion mutiert wird, was dazu führt, dass nicht zusammenhängende Teile Ihres Anforderungslebenszyklus fehlschlagen. Oder Sie schreiben eine komplexe, tief verschachtelte `foreach`-Schleife, nur um zu überprüfen, ob mindestens ein Benutzer in einer Liste sein Profil ausgefüllt hat. In beiden Fällen ist die Ursache dieselbe: imperative Zustandsmutation und Boilerplate-Kontrollfluss.

PHP ist in erster Linie für die objektorientierte Programmierung bekannt, verfügt aber über ein robustes, zunehmend ausgereiftes funktionales Toolkit. Die funktionale Programmierung in PHP ermöglicht es Ihnen, saubereren, besser testbaren und hochgradig vorhersagbaren Code zu schreiben, indem Sie Berechnungen als Auswertung mathematischer Funktionen behandeln und Zustandsmutationen vermeiden.

> **Kernaussage:**
> Der Übergang zu einer funktionalen Denkweise in PHP – durch die Nutzung von Closures, Arrow Functions, First-Class Callables und modernen Array-APIs – eliminiert Nebenwirkungsfehler und ersetzt Boilerplate-Schleifen durch saubere, deklarative Pipelines.

---

## Inhaltsverzeichnis
* [Closures und anonyme Funktionen](#closures-anonymous-functions)
* [Arrow Functions (PHP 7.4+)](#arrow-functions)
* [First-Class Callable Syntax (PHP 8.1+)](#first-class-callables)
* [Array-Operationen höherer Ordnung](#higher-order-arrays)
* [Das funktionale Array-Toolkit ab PHP 8.4+](#php84-arrays)
* [Pure Functions & Unveränderlichkeit in PHP](#pure-functions)
* [Praktische Mini-Demonstration: Moderne funktionale Pipeline](#practical-demo)
* [Einschränkungen & Kompromisse](#limitations-trade-offs)
* [🧠 Selbsttest-Quiz](#self-check)

---

<a id="closures-anonymous-functions"></a>
## Closures und anonyme Funktionen

### Punkt
Closures (implementiert über die native Klasse `Closure`) sind anonyme Funktionen, die Variablen aus dem umgebenden Gültigkeitsbereich mit dem Schlüsselwort `use` erfassen können.

### Warum es wichtig ist
In PHP erben Funktionen den Zugriff auf Variablen im übergeordneten Bereich nicht automatisch. Closures ermöglichen es Ihnen, ein Stück Logik zusammen mit dem spezifischen Datenkontext zu verpacken, den es für die Ausführung benötigt, was sie für Callbacks, Event-Handler und Inline-Operationen unverzichtbar macht.

### Beispiel
Beim Definieren einer Closure müssen Sie Variablen des übergeordneten Bereichs explizit mit dem Schlüsselwort `use` binden. Sie können auch Rückgabetypen angeben (eingeführt in PHP 7.0+):

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

Wenn Sie die übergeordnete Variable mutieren müssen (wovon in der funktionalen Programmierung im Allgemeinen abgeraten wird), müssen Sie sie per Referenz binden:

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

### Konsequenz
Ohne Closures mussten Entwickler Einwegklassen schreiben oder umfangreiche Zustandsarrays durch mehrere Ebenen von Funktionsparametern übergeben. Durch die Verwendung von Closures halten Sie Logik lokal und kontextbezogen, obwohl die manuelle `use`-Bindung in größeren Codebasen mühsam sein kann.

---

<a id="arrow-functions"></a>
## Arrow Functions (PHP 7.4+)

### Punkt
Arrow Functions (eingeführt in PHP 7.4+) bieten eine verkürzte Syntax für anonyme Funktionen und erfassen äußere Variablen automatisch per Wert (by-value).

### Warum es wichtig ist
Das Schreiben von `function () use ($x) { return $x * 2; }` führt zu erheblichem Syntaxrauschen bei einfachen Operationen mit einem einzigen Ausdruck. Arrow Functions reduzieren Boilerplate-Code und machen Inline-Mapping, -Filtering und -Sorting hervorragend lesbar.

### Beispiel
Arrow Functions verwenden das Schlüsselwort `fn`, gefolgt von Parametern, einem Doppelpfeil `=>` und einem einzelnen Ausdruck, der automatisch zurückgegeben wird:

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

### Konsequenz
Obwohl Arrow Functions Callbacks prägnant machen, bringen sie eine kritische Einschränkung mit sich: **Sie können nur einen einzigen Ausdruck enthalten**. Sie können keine mehreren Anweisungen oder komplexe Zuweisungen innerhalb einer Arrow Function schreiben. Darüber hinaus werden äußere Variablen strikt per Wert (by-value) erfasst; das Ändern einer erfassten Variable innerhalb der Arrow Function hat keine Auswirkungen auf den äußeren Gültigkeitsbereich.

---

<a id="first-class-callables"></a>
## First-Class Callable Syntax (PHP 8.1+)

### Punkt
Die First-Class Callable Syntax (eingeführt in PHP 8.1+) ermöglicht es Ihnen, auf jede Funktion oder Methode als `Closure`-Objekt zu verweisen, indem Sie den Platzhalter mit drei Punkten `...` verwenden.

### Warum es wichtig ist
Vor PHP 8.1 erforderte der Verweis auf vorhandene Methoden oder Funktionen deren Übergabe als Strings (`'strlen'`) oder Arrays (`[$this, 'formatPrice']`). Dies war äußerst fehleranfällig: IDEs konnten die Verweise nicht auflösen (was Autovervollständigung und Refactoring verhinderte), und Tools zur statischen Analyse wie PHPStan oder Psalm konnten Tippfehler erst zur Laufzeit erkennen.

### Beispiel
Vergleichen wir das ältere Array-Callable-Format mit der modernen First-Class Callable Syntax:

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

Diese Syntax funktioniert für:
* Funktionen: `strlen(...)`
* Statische Methoden: `MathHelper::square(...)`
* Instanzmethoden: `$object->method(...)`
* Aufrufbare Objekte (invokable): `$invokableObject(...)`

### Konsequenz
Der Wechsel zur First-Class Callable Syntax eliminiert stringbasierte Laufzeitfehler, ermöglicht es IDEs, Methoden sofort im gesamten Code umzubenennen, und bietet einen geringfügigen Performance-Vorteil, da PHP keine Laufzeitauflösung von Strings zu Methoden durchführen muss.

---

<a id="higher-order-arrays"></a>
## Array-Operationen höherer Ordnung

### Punkt
Array-Funktionen höherer Ordnung – insbesondere `array_map`, `array_filter` und `array_reduce` – nehmen andere Funktionen als Argumente an, um Arrays zu transformieren, zu filtern oder zu aggregieren.

### Warum es wichtig ist
Anstatt PHP anzuweisen, *wie* es Arrays durchlaufen und mutieren soll (imperatives Paradigma), ermöglichen Ihnen Funktionen höherer Ordnung zu deklarieren, *welche* Transformation stattfinden soll (deklaratives Paradigma). Dies isoliert Datenmutationen und verhindert Fehler.

### Beispiel
So können Sie die drei primären Array-Funktionen in einem modernen funktionalen Stil verwenden:

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
> **PHPs Falle bei der Parameter-Reihenfolge**
> Achten Sie genau auf die Parameter-Signaturen in PHPs integrierten Array-Funktionen:
> * `array_map(callable $callback, array $array)` -> Der Callback steht an **erster** Stelle.
> * `array_filter(array $array, callable $callback)` -> Der Callback steht an **zweiter** Stelle.
> * `array_reduce(array $array, callable $callback, $initial)` -> Der Callback steht an **zweiter** Stelle.
> 
> Das Verwechseln dieser Parameter-Reihenfolge ist eine der häufigsten Ursachen für Laufzeitabstürze in PHP.

### Konsequenz
Obwohl diese Funktionen Pipelines sehr ausdrucksstark machen, führt das Aneinanderketten zu mehreren zwischenzeitlichen Array-Kopien, was den Speicherbedarf bei großen Datensätzen erhöhen kann.

---

<a id="php84-arrays"></a>
## Das funktionale Array-Toolkit ab PHP 8.4+

### Punkt
PHP 8.4+ führt native Funktionen ein, um Array-Elemente mithilfe von Closures abzufragen, ohne dass vollständige Iterationen durchgeführt werden müssen: `array_find()`, `array_find_key()`, `array_any()` und `array_all()`.

### Warum es wichtig ist
Vor PHP 8.4 erforderte das Überprüfen, ob ein Element existiert (`array_any`), oder das Finden des ersten Elements, das eine Bedingung erfüllt (`array_find`), das Schreiben einer benutzerdefinierten `foreach`-Schleife mit einer `break`-Anweisung. Die Verwendung von Hilfsbibliotheken oder das Schreiben eigener Iterationen führte zu zusätzlichem Boilerplate-Code.

### Beispiel
Sehen wir uns an, wie diese neuen nativen Funktionen ab PHP 8.4+ Array-Prüfungen vereinfachen:

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

### Konsequenz
Diese Funktionen werden verzögert (lazy) ausgeführt. Das bedeutet, dass sie die Ausführung des Callbacks stoppen, sobald das Ergebnis feststeht (z. B. gibt `array_any` beim ersten Treffer `true` zurück). Dies bietet eine optimale Performance im Vergleich zur Ausführung eines vollständigen `array_filter` und der Überprüfung der Ergebnismenge.

---

<a id="pure-functions"></a>
## Pure Functions & Unveränderlichkeit in PHP

### Punkt
Eine **Pure Function** (reine Funktion) ist eine Funktion, die für dieselben Eingabewerte immer denselben Ausgabewert zurückgibt und keine Nebenwirkungen (Side Effects) verursacht (wie das Ändern des globalen Zustands, das Ändern von Referenzparametern oder das Ausführen von E/A-Operationen).

### Warum es wichtig ist
Wenn eine Funktion Variablen außerhalb ihres Gültigkeitsbereichs ändert, entstehen versteckte Abhängigkeiten. Wenn sich mehrere Teile eines Systems auf diesen gemeinsamen Zustand verlassen, wird das Testen schwierig und das Debuggen der Ausführungsreihenfolge zu einer Herausforderung.

### Beispiel
PHP-Arrays verwenden **Copy-on-Write**, was bedeutet, dass sie sich bei der Übergabe an Funktionen wie unveränderliche Werte verhalten. **PHP-Objekte werden jedoch standardmäßig als Referenz übergeben**. Betrachten wir einen häufigen Fehler, bei dem eine Funktion rein erscheint, aber ein Objekt-Argument mutiert:

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

Um Unveränderlichkeit auf Typebene zu erzwingen, können Sie **readonly-Eigenschaften** (eingeführt in PHP 8.1+) oder **readonly-Klassen** (eingeführt in PHP 8.2+) nutzen:

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

### Konsequenz
Durch das Schreiben von Pure Functions und die Verwendung von Readonly-DTOs garantieren Sie, dass der Aufruf einer Funktion niemals den Zustand an anderer Stelle in Ihrer Anwendung beeinträchtigt. Dies führt zu hervorragend testbarem Code mit vorhersagbarem Verhalten.

---

<a id="practical-demo"></a>
## Praktische Mini-Demonstration: Moderne funktionale Pipeline

Erstellen wir ein praktisches Beispiel. Stellen Sie sich einen API-Endpunkt vor, der vom Benutzer übermittelte Daten analysiert, ungültige Zeilen herausfiltert, eine Währungsumrechnung anwendet und den Gesamtdurchschnitt berechnet.

So können wir dies im funktionalen Stil implementieren:

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

### Negativbeispiel: Imperative Zustandsmutation & gemeinsam genutzte Referenzen

Im Gegensatz dazu sehen Sie hier, wie dieselbe Logik typischerweise imperativ geschrieben wird, was sie anfällig für Fehler macht, wenn die Array-Elemente per Referenz geändert werden:

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

### Warum der funktionale Ansatz überlegen ist:
1. **Referenzsicherheit:** Der veraltete Generator lässt `$row` per Referenz gebunden, wenn `unset($row)` vergessen wird, was später zu unbeabsichtigten Mutationen führen kann.
2. **Unveränderlichkeit:** Das ursprüngliche Daten-Array bleibt unberührt.
3. **Lesbarkeit:** Klare, schrittweise Transformationen anstelle von verschachtelten `if`-Blöcken innerhalb einer `foreach`-Schleife.

---

<a id="limitations-trade-offs"></a>
## Einschränkungen & Kompromisse

Obwohl die funktionale Programmierung in PHP sehr nützlich ist, ist PHP kein Haskell. Sie müssen sich der folgenden Kompromisse bewusst sein:

1. **Keine Tail Call Optimization (TCO):** PHP unterstützt keine TCO. Das Schreiben stark rekursiver Funktionen führt schnell zum Überschreiten der maximalen Stacktiefe, was Speichererschöpfung oder fatale Stack-Overflow-Fehler zur Folge hat. Verwenden Sie Iteration statt starker Rekursion.
2. **Performance- und Speicher-Overhead:** Das Aneinanderketten von Funktionen wie `array_map` und `array_filter` erstellt bei jedem Schritt neue Arrays. Bei sehr großen Datensätzen kann dies zu Speicherengpässen führen. In leistungskritischen Szenarien ist eine einfache imperative `foreach`-Schleife schneller und speichereffizienter.
3. **Fehlen eines Pipe-Operators:** PHP hat keinen Pipe-Operator (wie `|>` in Elixir). Das Verketten erfordert entweder tief verschachtelte Funktionsaufrufe oder das Schreiben temporärer Variablen.
4. **Inkonsistente Signaturen:** Wie bereits gezeigt, müssen Sie die Callback-Positionen in PHPs Array-Funktionen genau prüfen, da diese inkonsistent sind.

---

## Praktische Erkenntnis

* **Nutzen Sie Arrow Functions** (PHP 7.4+) für einfache, einzeilige Transformationen, um Ihren Code lesbar zu halten.
* **Verwenden Sie First-Class Callables** (PHP 8.1+) anstelle von String- oder Array-Referenzen für volle Unterstützung durch IDEs und statische Analysen.
* **Ersetzen Sie Suchschleifen durch native PHP 8.4+-Methoden** (`array_find`, `array_any` usw.), um Iterationen frühzeitig abzubrechen.
* **Verhindern Sie Objektmutationen**, indem Sie Klassen als `readonly` (PHP 8.2+) deklarieren oder `clone` in Pure Functions nutzen.
* **Bevorzugen Sie foreach-Schleifen**, wenn Sie mit riesigen Datenmengen arbeiten, bei denen Speichereffizienz und Geschwindigkeit entscheidend sind.

---

<a id="self-check"></a>
## 🧠 Selbsttest-Quiz

Versuchen Sie, die folgenden Fragen zu beantworten, um Ihr Verständnis zu überprüfen:

1. Warum führt das Ändern einer Variable innerhalb einer Arrow Function (PHP 7.4+) nicht dazu, dass dieselbe Variable im übergeordneten Bereich aktualisiert wird?
2. Was ist der Syntaxunterschied zwischen einem veralteten String/Array-Callback und der First-Class Callable Syntax ab PHP 8.1+?
3. Welche der Hilfsfunktionen ab PHP 8.4+ stoppt die Ausführung sofort nach dem Auffinden des ersten Elements, das die Kriterien erfüllt?

<details>
<summary><b>Antworten anzeigen</b></summary>

1. Arrow Functions erfassen Variablen aus dem übergeordneten Bereich streng **per Wert (by-value)**. Sie erhalten eine Kopie, weshalb Änderungen innerhalb der Funktion das Original nicht beeinflussen.
2. Veraltete Callbacks verwenden Strings (`'strlen'`) oder Arrays (`[$this, 'methodName']`), die von statischen Analysetools nicht geprüft werden können. First-Class Callables verwenden den Namen der Funktion gefolgt von einem Platzhalter mit drei Punkten (`strlen(...)` oder `$this->methodName(...)`), wodurch ein echtes `Closure`-Objekt entsteht.
3. Sowohl `array_find()` als auch `array_find_key()` stoppen die Iteration, sobald sie auf das erste Element stoßen, für das der Callback `true` zurückgibt. Ebenso bricht `array_any()` vorzeitig ab und gibt beim ersten Treffer `true` zurück.
</details>
