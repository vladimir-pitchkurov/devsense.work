---
title: "Основи на ООП в PHP: От основни концепции до асиметрична видимост | DevSense"
description: "Овладейте съвременното обектно-ориентирано програмиране в PHP. Научете капсулиране, наследяване, интерфейси, абстрактни класове, трейтове, анонимни класове (7.0+), readonly свойства (8.1+), readonly класове (8.2+) и асиметрична видимост (8.4+)."
faq:
  - question: "Каква е разликата между интерфейс и абстрактен клас в PHP?"
    answer: "Интерфейсът дефинира чист публичен договор, на който внедряващите класове трябва да съответстват, позволявайки множество имплементации. Абстрактният клас е частично внедрен клас, който може да съдържа състояние, логика на конструктор и защитени методи, но тъй като PHP поддържа само единично наследяване, даден клас може да наследява само един абстрактен клас."
  - question: "Как работят свойствата само за четене (readonly) в PHP 8.1+?"
    answer: "Свойствата само за четене могат да бъдат инициирани само веднъж, обикновено в конструктора на класа. Всеки последващ опит за промяна или премахване (unset) на свойството ще предизвика изключение Error, гарантирайки неговата непроменяемост."
  - question: "Какво е асиметрична видимост в PHP 8.4+?"
    answer: "Асиметричната видимост ви позволява да декларирате различни нива на достъп за четене и запис на дадено свойство. Например, 'public private(set) string $name' позволява публичен достъп за четене, но ограничава достъпа за запис само до самия клас, елиминирайки шаблонния код за гетъри."
  - question: "Как се разрешават конфликти на имена при трейтове в PHP?"
    answer: "Когато два трейта дефинират метод с едно и също име, PHP хвърля фатална грешка, освен ако изрично не разрешите конфликта с помощта на оператора 'insteadof', за да изберете единия метод, или оператора 'as', за да дадете псевдоним на метод под ново име."
---

# Основи на ООП в PHP: Защита на състоянието и дефиниране на чисти интерфейси

Ако някога сте прекарали часове в дебъгване на мистериозна мутация на състоянието в голямо уеб приложение, знаете колко крехки могат да бъдат лошо капсулираните обекти. Дадена услуга променя публично свойство на споделен инстанс и изведнъж записите в базата данни в цялата система се повреждат. Същинският проблем не е в самото Обектно-ориентирано програмиране (ООП), а по-скоро в неуспеха ни да наложим строги граници между нашите обекти.

В процедурния код данните се предават глобално, което ги прави уязвими за модификация. При наивното ООП класовете се разглеждат като обикновени контейнери за свойства, излагайки всичко чрез публични променливи или генерични гетъри и сетъри. Съвременният PHP решава тези архитектурни проблеми, като преминава от отворени, мутабилни обекти към строги, самоуправляващи се структури.

> [!IMPORTANT]
> Съвременното ООП в PHP не е просто организиране на функции в класове; става дума за налагане на строги граници на състоянието, дефиниране на строги публични договори и използване на безопасност на типовете за предотвратяване на невалидни състояния по време на изпълнение.

**Целево ниво**: Junior / Middle

---

## Съдържание
* [Капсулиране и модификатори за достъп](#encapsulation-modifiers)
* [Договори и абстракция: Наследяване, абстрактни класове и интерфейси](#contracts-abstraction)
* [Хоризонтално повторно използване и динамично поведение: Трейтове и анонимни класове (PHP 7.0+)](#horizontal-reuse)
* [Непроменяемост по подразбиране: Readonly свойства (PHP 8.1+) и readonly класове (PHP 8.2+)](#immutability)
* [Фини граници: Асиметрична видимост (PHP 8.4+)](#asymmetric-visibility)
* [Архитектурни компромиси и ограничения](#trade-offs)
* [🧠 Въпроси за самопроверка](#self-check)

---

<a id="encapsulation-modifiers"></a>
## Капсулиране и модификатори за достъп

### 1. Теза
Капсулирането е практиката на скриване на вътрешното състояние на даден обект и изискването всички взаимодействия да преминават през публичен интерфейс. PHP контролира този достъп с помощта на три модификатора:
* `public`: Достъпен отвсякъде.
* `protected`: Достъпен само в самия клас и от наследяващите го класове.
* `private`: Достъпен само в класа, който го дефинира.

### 2. Защо е важно
Без капсулиране външните услуги могат да променят вътрешното състояние на обекта без негово знание, заобикаляйки правилата за валидация. Например, ако балансът на `BankAccount` може да се записва директно отвън, не можем да гарантираме, че балансът никога няма да падне под нулата или че транзакциите се записват правилно.

### 3. Пример
Нека да разгледаме как излагането на публични свойства води до повреждане на състоянието и как частните (private) модификатори налагат инварианти:

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

### 4. Последствие
Чрез деклариране на `$balance` като private, класът запазва пълен контрол върху своята валидация. Външният код не може да изпълни `$account->balance = -500.0;`. Промените на състоянието могат да се извършват само чрез валидни бизнес операции (`deposit` и `withdraw`), гарантирайки, че регистърът (ledger) и балансът са винаги синхронизирани.

---

<a id="contracts-abstraction"></a>
## Договори и абстракция: Наследяване, абстрактни класове и интерфейси

### 1. Теза
PHP позволява на разработчиците да проектират поведение за многократна употреба и строги архитектурни граници чрез наследяване и полиморфизъм:
* **Наследяване (`extends`)**: Позволява на дочерния клас да наследява свойства и методи от родителски клас. Дочерните класове могат да пренаписват родителските методи, освен ако родителският метод или клас не е маркиран като `final`.
* **Абстрактни класове (`abstract class`)**: Шаблони, които не могат да бъдат инстанцирани сами по себе си. Те могат да съдържат напълно внедрени методи, както и абстрактни (`abstract`) сигнатури на методи, които децата трябва да имплементират.
* **Интерфейси (`interface`)**: Чисти договори, съдържащи само сигнатури на методи без имплементация. Даден клас може да внедрява множество интерфейси, разрешавайки ограничението на PHP за единично наследяване.

### 2. Защо е важно
Наследяването ви позволява да споделяте обща логика, но създава тясно свързване (tight coupling) между родител и наследник. Абстрактните класове действат като средно положение, предлагайки частичен шаблон. Интерфейсите представляват най-високото ниво на развързване (decoupling): те дефинират *какво* може да прави даден обект, а не *как* го прави. Това ви позволява да сменяте адаптери за бази данни, имейл драйвери или платежни шлюзове, без да променяте приложната логика, която зависи от тях.

### 3. Пример
Ето как изграждаме договор за обработка на плащания, като използваме интерфейс, абстрактна база и конкретни имплементации:

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

### 4. Последствие
Класовете от високо ниво (като `CheckoutController`) могат да зависят от `PaymentGatewayInterface` вместо от конкретни класове. Можем да заменим `StripeGateway` с `PaypalGateway` чрез вграждане на зависимости (Dependency Injection) или да симулираме (mock) шлюза изцяло в тестове на PHPUnit, без да променяме логиката на поръчката.

---

<a id="horizontal-reuse"></a>
## Хоризонтално повторно използване и динамично поведение: Трейтове и анонимни класове (PHP 7.0+)

### 1. Теза
PHP поддържа само единично наследяване. За да се предотврати дублирането на код, без да се налагат йерархии от класове, PHP предлага два механизма:
* **Трейтове (Traits)**: Блокове от код, които могат да бъдат вмъкнати в класове за хоризонтално споделяне на методи. Ако възникнат конфликти на имена между два трейта, те трябва да бъдат разрешени с помощта на операторите `insteadof` и `as`.
* **Анонимни класове (PHP 7.0+)**: Леки, неназовани класове, декларирани в движение. Те са полезни за прости, кратковременни имплементации на интерфейси или абстрактни класове.

### 2. Защо е важно
Налагането на йерархии от класове само за споделяне на код (като логване или логическо изтриване - soft deletes) води до крехки родителски класове и разхвърляни дървета на наследяване. Трейтовете решават това, като позволяват импортирането на множество независими поведения в един клас. Анонимните класове спестяват шаблонния код (boilerplate), когато трябва да симулирате клас или да предоставите имплементация за еднократна употреба за тест или колбек.

### 3. Пример
Следният код демонстрира трейт с разрешаване на конфликти и анонимен клас, използван за логване в тестове:

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

### 4. Последствие
Чрез използването на трейтове `AuditService` придобива споделено поведение от множество източници, без да е необходимо да разширява общ базов клас. С анонимните класове можем да инстанцираме вградени имплементации на `MailerInterface` за бързо тестване или малки скриптове, като пазим файловата система чиста от еднократни класове.

---

<a id="immutability"></a>
## Непроменяемост по подразбиране: Readonly свойства (PHP 8.1+) и readonly класове (PHP 8.2+)

### 1. Теза
PHP въвежда модерни инструменти за налагане на непроменяемост на състоянието (immutability) на ниво двигател (engine):
* **Readonly свойства (PHP 8.1+)**: Свойства, които могат да бъдат записани само веднъж (обикновено в конструктора). Последващи модификации или опити за изтриването им с `unset()` ще предизвикат `Error`. Те трябва да бъдат типизирани; нетипизираните свойства не могат да бъдат маркирани като readonly.
* **Readonly класове (PHP 8.2+)**: Синтаксис, който неявно маркира всички свойства в даден клас като `readonly` и предотвратява създаването на динамични свойства.

### 2. Защо е важно
Обектите за трансфер на данни (DTOs) и конфигурационните настройки се предават през много нива на приложението. Ако тези структури са мутабилни, всяка услуга по време на жизнения цикъл на заявката може случайно да промени стойностите им, което води до скрити бъгове. Налагането на непроменяемост на ниво компилатор гарантира, че след като DTO е конструиран, неговите данни остават идентични по време на изпълнението.

### 3. Пример
Нека да разгледаме как налагаме непроменяемост върху потребителски DTO:

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

### 4. Последствие
Всеки код, който използва `$config`, има гаранция, че конфигурацията на потребителя няма да се промени в средата на заявката. Това елиминира нуждата от шаблонен код с частни свойства и методи само за четене, като драстично опростява кода на DTO.

---

<a id="asymmetric-visibility"></a>
## Фини граници: Асиметрична видимост (PHP 8.4+)

### 1. Теза
PHP 8.4+ въвежда **Асиметрична видимост**, която ви позволява да дефинирате различни нива на достъп за четене и запис на дадено свойство на един и същи ред.
* Синтаксис: `public private(set) Type $property`
* Първо се указва видимостта за четене (напр. `public`).
* Видимостта за запис (напр. `private(set)` или `protected(set)`) се указва веднага след това.

### 2. Защо е важно
Преди PHP 8.4, ако искахте свойството да бъде публично за четене, но да се записва само вътре в класа, трябваше да го направите `private` и да напишете публичен гетър метод, или да направите класа/свойството `readonly`. Въпреки това, `readonly` предотвратява *всякакви* вътрешни мутации след инициализация. Асиметричната видимост позволява на даден клас да променя собствените си свойства вътрешно, като ги излага като достъпни само за четене за външния свят, без да е необходим гетър код.

### 3. Пример
Ето обект `Product`, чиято цена може да се променя с течение на времето, но само чрез методи на класа:

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

### 4. Последствие
Получаваме чистия синтаксис на публичните свойства за четене на стойности, като същевременно поддържаме строга капсулация. Вече не е необходимо да пишем шаблонен код като `public function getPriceInCents(): int { return $this->priceInCents; }`.

---

<a id="trade-offs"></a>
## Архитектурни компромиси и ограничения

Нито един модел не е сребърен куршум. Прилагането на ООП функции без оценка на техните компромиси води до прекалено сложен или труден за поддръжка код.

### 1. Дълбоко наследяване и свързване (Проблемът за крехкия базов клас)
Въпреки че разширяването на класове е лесно, то въвежда тясно свързване. Ако родителски клас (`AbstractPaymentGateway`) промени сигнатурата на своя конструктор, всеки дочерен клас трябва да бъде актуализиран. Предпочитайте **композиция пред наследяване**, като внедрявате зависимости, вместо да разширявате бази.

### 2. Скритата магия на трейтовете
Трейтовете лесно могат да прикрият лоша архитектура. Те изглеждат като копиране и поставяне с помощта на компилатора, което улеснява създаването на скрити зависимости и конфликти на методи. Ако няколко класа се нуждаят от една и съща логика, помислете за създаване на специален помощен клас и вграждането му като зависимост.

### 3. Ограничения на Readonly свойствата
Readonly свойствата (PHP 8.1+) не могат да бъдат нулирани или променяни, дори вътре в класа. Ако трябва да променяте състоянието вътрешно (напр. проследяване на промени в състоянието или инвалидиране на кеша), свойствата само за четене няма да работят. Освен това те не могат да имат стойности по подразбиране и не могат да се използват с куки за свойства (property hooks в PHP 8.4+).

### 4. Ограничения за асиметрична видимост
Асиметричната видимост (PHP 8.4+) изисква операцията за запис (`set`) да бъде строго по-тясна от операцията за четене (`get`). Например, `private public(set)` е невалидно и ще хвърли синтактична грешка. Освен това свойства с асиметрична видимост не могат да се предават по референция:

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

## Практически извод: Дървото на решенията за видимост

Когато дефинирате свойства на клас, следвайте това ориентировъчно правило, за да запазите капсулирането защитено и кода чист:

1. **Променя ли се някога свойството след инстанциране на конструктора?**
   * **Не**: Използвайте `readonly` свойство (PHP 8.1+) или `readonly` клас (PHP 8.2+).
   * **Да**: Преминете към стъпка 2.
2. **Трябва ли външният код да може да променя стойността директно?**
   * **Не**: Използвайте `public private(set)` (PHP 8.4+), за да изложите стойността за четене, без да разрешавате достъп за запис.
   * **Да**: Използвайте стандартно `public` свойство (имайте предвид, че това заобикаля валидацията).

---

## 🧠 Въпроси за самопроверка

1. **Защо PHP хвърля фатална грешка (Fatal Error), ако се опитате да декларирате свойство като `private public(set)` в PHP 8.4+?**
2. **Вярно или невярно?** Дочерен клас може да пренапише метод в родителския клас, дори ако родителският метод е с префикс `final`.
3. **Какво се случва, ако се опитате да премахнете (unset) или да преинициализирате `readonly` свойство, след като то вече е било зададено?**

<details>
<summary><b>Виж отговорите</b></summary>

1. Видимостта на запис (`set`) трябва да бъде толкова строга или по-строга от видимостта за четене. Тъй като `private(set)` е по-строго от `public`, `public private(set)` е валидно. Въпреки това, `public(set)` е по-широко от `private` видимостта за четене, което е нелогично и се отхвърля от парсера.
2. **Невярно.** Маркирането на клас или метод като `final` предотвратява пренаписването на този метод от дочерни класове или наследяването на класа.
3. Хвърля изключение `Error`: *Cannot modify readonly property...* или *Cannot unset readonly property...*.
</details>
