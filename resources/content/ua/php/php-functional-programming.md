---
title: "Функціональний PHP: Замикання, Callables та сучасні API масивів | DevSense"
description: "Опануйте функціональне програмування в PHP. Вивчіть замикання, стрілочні функції (7.4+), першокласний синтаксис callable (8.1+), сучасні методи масивів (8.4+) та чисті функції."
faq:
  - question: "Яка різниця між замиканнями та стрілочними функціями в PHP?"
    answer: "Замикання (анонімні функції) вимагають явного зв'язування змінних за допомогою ключового слова 'use' та підтримують декілька інструкцій. Стрілочні функції (PHP 7.4+) автоматично захоплюють зовнішні змінні за значенням, але обмежені однією інструкцією."
  - question: "Як синтаксис first-class callable покращує якість коду в PHP 8.1+?"
    answer: "Синтаксис first-class callable (наприклад, my_function(...)) забезпечує підтримку статичного аналізу, автодоповнення в IDE та підвищення продуктивності під час виконання порівняно зі застарілими рядковими або масивними callables."
  - question: "Які нові функції масивів з'явилися в PHP 8.4+ для функціонального програмування?"
    answer: "PHP 8.4+ представляє array_find(), array_find_key(), array_any() та array_all(), які спрощують типові операції, такі як пошук елементів або перевірка умов без написання громіздких циклів foreach."
  - question: "Чи підтримує PHP імутабельність нативно?"
    answer: "Масиви PHP використовують копіювання при записі (copy-on-write), що діє подібно до імутабельності, але об'єкти передаються за посиланням. Імутабельність може бути досягнута за допомогою readonly властивостей (PHP 8.1+), readonly класів (PHP 8.2+) або клонування."
---

**Рівень: Junior / Middle**

Уявіть, що ви шукаєте баг у продакшені, коли спільна сутність бази даних мутує у вкладеній допоміжній функції, через що інші частини життєвого циклу вашого запиту падають. Або пишете складний, глибоко вкладений цикл `foreach` лише для того, щоб перевірити, чи хоча б один користувач у списку заповнив свій профіль. В обох випадках першопричина одна й та сама: імперативна мутація стану та шаблонний потік керування.

PHP насамперед відомий об'єктно-орієнтованим програмуванням, але він володіє потужним і зрілим функціональним інструментарієм. Прийняття функціонального програмування в PHP дозволяє писати чистіший, більш протестований і передбачуваний код, розглядаючи обчислення як оцінку математичних функцій і уникаючи зміни стану.

> **Основна теза:**
> Перехід до функціонального мислення в PHP — використання замикань, стрілочних функцій, першокласних callables та сучасних API масивів — усуває баги, пов'язані з побічними ефектами, і замінює шаблонні цикли на чисті декларативні конвеєри.

---

## Зміст
* [Замикання та анонімні функції](#closures-anonymous-functions)
* [Стрілочні функції (PHP 7.4+)](#arrow-functions)
* [Синтаксис First-Class Callable (PHP 8.1+)](#first-class-callables)
* [Операції вищого порядку з масивами](#higher-order-arrays)
* [Функціональний інструментарій для масивів у PHP 8.4+](#php84-arrays)
* [Чисті функції та імутабельність в PHP](#pure-functions)
* [Практична міні-демонстрація: Сучасний функціональний конвеєр](#practical-demo)
* [Обмеження та компроміси](#limitations-trade-offs)
* [🧠 Тест для самоперевірки](#self-check)

---

<a id="closures-anonymous-functions"></a>
## Замикання та анонімні функції

### Теза
Замикання (реалізовані через вбудований клас `Closure`) — це анонімні функції, які можуть захоплювати змінні з навколишньої області видимості за допомогою ключового слова `use`.

### Чому це важливо
У PHP функції не успадковують автоматично доступ до змінних у своїй батьківській області видимості. Замикання дозволяють упакувати фрагмент логіки разом із конкретним контекстом даних, необхідним для його виконання, що робить їх незамінними для зворотних викликів (callbacks), обробників подій та вбудованих операцій.

### Приклад
При визначенні замикання ви повинні явно зв'язати змінні батьківської області видимості за допомогою ключового слова `use`. Також можна вказувати типи повернення (починаючи з PHP 7.0+):

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

Якщо вам потрібно змінити батьківську змінну (що загалом не рекомендується у функціональному програмуванні), ви повинні зв'язати її за посиланням:

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

### Наслідок
Без замикань розробникам доводилося створювати одноразові класи або передавати великі масиви стану через кілька рівнів параметрів функцій. Використання замикань дозволяє зберігати логіку локальною, хоча ручне зв'язування через `use` може здаватися громіздким у великих проєктах.

---

<a id="arrow-functions"></a>
## Стрілочні функції (PHP 7.4+)

### Теза
Стрілочні функції (представлені в PHP 7.4+) надають скорочений синтаксис для анонімних функцій і автоматично захоплюють зовнішні змінні за значенням.

### Чому це важливо
Написання `function () use ($x) { return $x * 2; }` створює значний синтаксичний шум для простих однорядкових операцій. Стрілочні функції зменшують кількість шаблонів, роблячи вбудоване відображення, фільтрацію та сортування надзвичайно читабельними.

### Приклад
Стрілочні функції використовують ключове слово `fn`, за яким слідують параметри, подвійна стрілка `=>` та вираз, результат якого автоматично повертається:

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

### Наслідок
Хоча стрілочні функції роблять зворотні виклики лаконічними, вони мають критичне обмеження: **вони можуть містити лише один вираз**. Ви не можете писати кілька інструкцій або виконувати складні призначення всередині стрілочної функції. Крім того, зовнішні змінні захоплюються строго за значенням; зміна захопленої змінної всередині функції не впливає на зовнішню область видимості.

---

<a id="first-class-callables"></a>
## Синтаксис First-Class Callable (PHP 8.1+)

### Теза
Синтаксис first-class callable (представлений в PHP 8.1+) дозволяє посилатися на будь-яку функцію або метод як на об'єкт `Closure` за допомогою плейсхолдера з трьох крапок `...`.

### Чому це важливо
До PHP 8.1 посилання на існуючі методи або функції вимагало передачі їх у вигляді рядків (`'strlen'`) або масивів (`[$this, 'formatPrice']`). Це було джерелом помилок: IDE не могли розпізнати посилання (що ламало автодоповнення та рефакторинг), а інструменти статичного аналізу, такі як PHPStan або Psalm, не могли виявити друкарські помилки до виконання коду.

### Приклад
Порівняємо застарілий формат посилання на метод через масив із сучасним синтаксисом first-class callable:

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

Цей синтаксис працює для:
* Функцій: `strlen(...)`
* Статичних методів: `MathHelper::square(...)`
* Методів екземпляра: `$object->method(...)`
* Об'єктів, що викликаються (invokable): `$invokableObject(...)`

### Наслідок
Перехід на синтаксис first-class callable усуває помилки під час виконання, пов'язані з рядками, дозволяє IDE миттєво перейменовувати методи по всій базі коду та дає невеликий приріст продуктивності, оскільки PHP не потрібно виконувати динамічний пошук методу за рядком.

---

<a id="higher-order-arrays"></a>
## Операції вищого порядку з масивами

### Теза
Функції масивів вищого порядку — зокрема `array_map`, `array_filter` та `array_reduce` — приймають інші функції як аргументи для перетворення, фільтрації або агрегації масивів.

### Чому це важливо
Замість того, щоб вказувати PHP *як* обходити та мутувати масиви (імперативна парадигма), функції вищого порядку дозволяють декларувати, *яке* перетворення має відбутися (декларативна парадигма). Це ізолює зміну даних та запобігає багам, пов'язаним зі станом циклу.

### Приклад
Ось як можна використовувати три основні функції масивів у сучасному функціональному стилі:

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
> **Пастка неузгодженості параметрів у PHP**
> Зверніть увагу на сигнатури параметрів у вбудованих функціях масивів PHP:
> * `array_map(callable $callback, array $array)` -> Зворотний виклику йде **першим**.
> * `array_filter(array $array, callable $callback)` -> Зворотний виклик йде **другим**.
> * `array_reduce(array $array, callable $callback, $initial)` -> Зворотний виклик йде **другим**.
> 
> Плутанина з порядком цих параметрів є однією з найчастіших причин помилок у PHP.

### Наслідок
Хоча ці функції роблять конвеєри виразними, їх послідовне викликання призводить до створення копій проміжних масивів, що може збільшити використання пам'яті на великих наборах даних.

---

<a id="php84-arrays"></a>
## Функціональний інструментарій для масивів у PHP 8.4+

### Теза
PHP 8.4+ представляє вбудовані функції для пошуку елементів масиву за допомогою замикань без необхідності повного перебору: `array_find()`, `array_find_key()`, `array_any()` та `array_all()`.

### Чому це важливо
До PHP 8.4 перевірка існування елемента (`array_any`) або пошук першого відповідного елемента (`array_find`) вимагали написання циклу `foreach` із оператором `break`. Використання сторонніх бібліотек або написання власних ітерацій створювало додатковий шаблонний код.

### Приклад
Подивимося, як ці нові функції спрощують перевірку масивів у PHP 8.4+:

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

### Наслідок
Ці функції працюють ліниво, тобто вони зупиняють виконання замикання, як тільки результат стає визначеним (наприклад, `array_any` повертає `true` при першому ж збігу). Це забезпечує оптимальну продуктивність порівняно з повним фільтруванням масиву через `array_filter`.

---

<a id="pure-functions"></a>
## Чисті функції та імутабельність в PHP

### Теза
**Чиста функція** — це функція, яка завжди повертає однаковий результат для однакових вхідних даних і не створює побічних ефектів (таких як зміна глобального стану, модифікація параметрів за посиланням або операції введення-виведення).

### Чому це важливо
Коли функція змінює змінні поза своєю областю видимості, вона створює приховані залежності. Якщо кілька частин системи покладаються на цей спільний стан, тестування стає складним, а налагодження порядку виконання — заплутаним.

### Приклад
Масиви в PHP копіюються при записі (**copy-on-write**), що діє подібно до імутабельних значень при передачі у функції. Однак, **об'єкти в PHP передаються за посиланням**. Розглянемо приклад, коли функція здається чистою, але насправді змінює вхідний об'єкт:

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

Для забезпечення незмінності на рівні типів використовуйте **readonly властивості** (PHP 8.1+) або **readonly класи** (PHP 8.2+):

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

### Наслідок
Пишучи чисті функції та використовуючи readonly DTO, ви гарантуєте, що виклик функції ніколи не пошкодить стан в інших частинах вашого додатка, що робить код передбачуваним і легким для тестування.

---

<a id="practical-demo"></a>
## Практична міні-демонстрація: Сучасний функціональний конвеєр

Розглянемо реальний приклад: обробка необроблених даних продажів, фільтрація некоректних записів, перетворення на об'єкти цін та підрахунок середнього значення.

Ось як реалізувати це у функціональному стилі:

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

### Поганий приклад: Імперативні мутації та посилання

Для порівняння, подивіться на аналогічний код в імперативному стилі, де дані передаються за посиланням:

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
        $count = 0;Property Hooks

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

### Чому функціональний підхід кращий:
1. **Безпека посилань:** У застарілому коді змінна `$row` залишається зв'язаною за посиланням, якщо забути викликати `unset()`, що може призвести до неочікуваної мутації даних згодом.
2. **Незмінність:** Вхідний масив залишається недоторканим.
3. **Читабельність:** Чітка послідовність трансформацій замість вкладених блоків `if` всередині `foreach`.

---

<a id="limitations-trade-offs"></a>
## Обмеження та компроміси

Хоча функціональний підхід корисний, PHP не є Haskell. Слід пам'ятати про його компроміси:

1. **Відсутність оптимізації хвостової рекурсії (TCO):** PHP не підтримує TCO. Використання глибокої рекурсії швидко призведе до переповнення стеку викликів та фатальної помилки. Замість рекурсії використовуйте звичайні цикли.
2. **Використання пам'яті:** Послідовний виклик `array_map` та `array_filter` копіює масиви на кожному кроці. Для мільйонних масивів це створить велике навантаження на пам'ять. У таких критичних місцях краще застосовувати класичний `foreach`.
3. **Відсутність оператора конвеєра (Pipe Operator):** У PHP немає вбудованого оператора передачі результату (як `|>` в Elixir). Доводиться або вкладати функції одна в одну, або використовувати проміжні змінні.
4. **Неузгодженість сигнатур:** Як згадувалося вище, завжди перевіряйте порядок параметрів у функціях масивів.

---

## Практичний висновок

* **Використовуйте стрілочні функції** (PHP 7.4+) для простих однорядкових зворотних викликів.
* **Застосовуйте first-class callables** (PHP 8.1+) замість рядків для кращої підтримки IDE та статичного аналізу.
* **Замінюйте пошукові цикли вбудованими методами PHP 8.4+** (`array_find`, `array_any` тощо) для передчасного виходу з циклу.
* **Запобігайте мутації об'єктів** за допомогою `readonly` (PHP 8.2+) та клонування.
* **Надавайте перевагу foreach** при обробці гігантських масивів для збереження оперативної пам'яті.

---

<a id="self-check"></a>
## 🧠 Тест для самоперевірки

Спробуйте відповісти на наступні запитання:

1. Чому зміна змінної всередині стрілочної функції (PHP 7.4+) не змінює значення цієї змінної у батьківській області видимості?
2. Яка синтаксична різниця між застарілим викликом методу через масив та синтаксисом first-class callable в PHP 8.1+?
3. Які нові функції PHP 8.4+ зупиняють перебір масиву відразу після першої успішної перевірки умови?

<details>
<summary><b>Показати відповіді</b></summary>

1. Стрілочні функції захоплюють змінні з батьківської області видимості строго **за значенням** (by-value). Вони працюють із копією змінної, тому будь-які зміни всередині функції не впливають на оригінал.
2. Застарілий синтаксис використовує рядки або масиви (`'strlen'` або `[$this, 'methodName']`), які не розпізнаються IDE та статичними аналізаторами. Синтаксис first-class callable використовує назву функції/методу з трьома крапками (`strlen(...)` або `$this->methodName(...)`), створюючи повноцінний об'єкт `Closure`.
3. Функції `array_find()` та `array_find_key()` зупиняють ітерації відразу після знаходження першого елемента, який задовольняє умову (коли замикання повертає `true`). Також ліниво працює функція `array_any()`.
</details>
