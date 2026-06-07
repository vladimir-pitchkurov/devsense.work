---
title: "Основи ООП в PHP: від базових концепцій до асиметричної видимості | DevSense"
description: "Опануйте сучасне об'єктно-орієнтоване програмування в PHP. Вивчіть інкапсуляцію, успадкування, інтерфейси, абстрактні класи, трейти, анонімні класи (7.0+), readonly властивості (8.1+), readonly класи (8.2+) та асиметричну видимість (8.4+)."
faq:
  - question: "Яка різниця між інтерфейсом та абстрактним класом в PHP?"
    answer: "Інтерфейс визначає чистий публічний контракт, якому повинні відповідати класи, що його реалізують, дозволяючи кілька реалізацій. Абстрактний клас — це частково реалізований клас, який може містити стан, логіку конструктора та захищені методи, але оскільки PHP підтримує лише одинарне успадкування, клас може наслідувати тільки один абстрактний клас."
  - question: "Як працюють властивості тільки для читання (readonly) в PHP 8.1+?"
    answer: "Властивості тільки для читання можуть бути ініціалізовані лише один раз, як правило, в конструкторі класу. Будь-яка подальша спроба змінити або видалити (unset) таку властивість викличе виняток Error, що гарантує незмінність."
  - question: "Що таке асиметрична видимість в PHP 8.4+?"
    answer: "Асиметрична видимість дозволяє оголошувати різні рівні доступу для читання та запису властивості. Наприклад, 'public private(set) string $name' дозволяє публічний доступ для читання, але обмежує доступ для запису самим класом, що усуває потребу в шаблонних геттерах (getter boilerplate)."
  - question: "Як вирішувати конфлікти імен трейтів в PHP?"
    answer: "Коли два трейти визначають метод з однаковим ім'ям, PHP видає фатальну помилку, якщо ви явно не вирішите цей конфлікт за допомогою оператора 'insteadof' для вибору одного методу або оператора 'as' для створення псевдоніма методу під новим ім'ям."
---

# Основи ООП в PHP: Захист стану та визначення чистих інтерфейсів

Якщо ви коли-небудь витрачали години на налагодження таємничої мутації стану у великому веб-додатку, ви знаєте, наскільки крихкими можуть бути погано інкапсульовані об'єкти. Сервіс змінює публічну властивість спільного екземпляра, і раптом записи в базі даних по всій системі пошкоджуються. Основна проблема полягає не в самому об'єктно-орієнтованому програмуванні (ООП), а в нашій нездатності встановити чіткі межі між нашими об'єктами.

У процедурному коді дані передаються глобально, залишаючи їх вразливими для змін. У простому ООП класи розглядаються як звичайні контейнери властивостей, які відкривають усе через публічні змінні або стандартні геттери та сеттери. Сучасний PHP вирішує ці архітектурні проблеми, переходячи від відкритих, мутабельних об'єктів до суворих структур, що керуються самостійно.

> [!IMPORTANT]
> Сучасне ООП в PHP — це не просто організація функцій у класи; це забезпечення суворих меж стану, визначення чітких публічних контрактів та використання типізації для запобігання недійсним станам під час виконання.

**Цільовий рівень**: Junior / Middle

---

## Зміст
* [Інкапсуляція та модифікатори доступу](#encapsulation-modifiers)
* [Контракти та абстракція: Успадкування, абстрактні класи та інтерфейси](#contracts-abstraction)
* [Горизонтальне перевикористання та динамічна поведінка: Трейти та анонімні класи (PHP 7.0+)](#horizontal-reuse)
* [Незмінність за замовчуванням: Readonly властивості (PHP 8.1+) та readonly класи (PHP 8.2+)](#immutability)
* [Тонке налаштування меж: Асиметрична видимість (PHP 8.4+)](#asymmetric-visibility)
* [Архітектурні компроміси та обмеження](#trade-offs)
* [🧠 Запитання для самоперевірки](#self-check)

---

<a id="encapsulation-modifiers"></a>
## Інкапсуляція та модифікатори доступу

### 1. Теза
Інкапсуляція — це практика приховування внутрішнього стану об'єкта та вимога, щоб усі взаємодії відбувалися через публічний інтерфейс. PHP контролює цей доступ за допомогою трьох модифікаторів:
* `public`: Доступний звідусіль.
* `protected`: Доступний лише всередині самого класу та класами, що його успадковують.
* `private`: Доступний лише всередині класу, який його визначає.

### 2. Чому це важливо
Без інкапсуляції зовнішні сервіси можуть змінювати внутрішній стан об'єкта без його відома, обходячи правила валідації. Наприклад, якщо баланс `BankAccount` можна записати безпосередньо ззовні, мы не можемо гарантувати, що баланс ніколи не опуститься нижче нуля або що транзакції будуть належним чином записані в журнал.

### 3. Приклад
Давайте подивимося, як відкриття публічних властивостей призводить до пошкодження стану, і як приватні модифікатори забезпечують інваріанти:

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

### 4. Наслідок
Оголосивши `$balance` приватним, клас зберігає повний контроль над його валідацією. Зовнішній код не може виконати `$account->balance = -500.0;`. Зміна стану може відбуватися лише через валідні бізнес-операції (`deposit` та `withdraw`), гарантуючи, що реєстр транзакцій та баланс завжди синхронізовані.

---

<a id="contracts-abstraction"></a>
## Контракти та абстракція: Успадкування, абстрактні класи та інтерфейси

### 1. Теза
PHP дозволяє розробникам проектувати поведінку для повторного використання та встановлювати суворі архітектурні межі за допомогою успадкування та поліморфізму:
* **Успадкування (`extends`)**: Дозволяє дочірньому класу успадковувати властивості та методи батьківського класу. Дочірні класи можуть перевизначати батьківські методи, якщо тільки батьківський метод або клас не позначені як `final`.
* **Абстрактні класи (`abstract class`)**: Шаблони, які не можуть бути інстанційовані самостійно. Вони можуть містити повністю реалізовані методи, а також сигнатури `abstract` методів, які дочірні класи повинні реалізувати.
* **Інтерфейси (`interface`)**: Чисті контракти, які містять лише сигнатури методів без реалізації. Клас може реалізовувати кілька інтерфейсів, що вирішує обмеження PHP щодо одинарного успадкування.

### 2. Чому це важливо
Успадкування дозволяє ділитися спільною логікою, но воно створює сильний зв'язок (tight coupling) між батьківським та дочірнім класами. Абстрактні класи виступають проміжною ланкою, пропонуючи частковий план. Інтерфейси представляють найвищий рівень послаблення зв'язків (decoupling): вони визначають *що* об'єкт може робити, а не те, *як* він це робить. Це дозволяє вам замінювати адаптери баз даних, драйвери пошти або платіжні шлюзи без зміни логіки програми, яка від них залежить.

### 3. Приклад
Ось як ми будуємо контракт для обробки платежів, використовуючи інтерфейс, абстрактний базовий клас та конкретні реалізації:

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

### 4. Наслідок
Класи високого рівня (такі як `CheckoutController`) можуть залежати від `PaymentGatewayInterface` замість конкретних класів. Ми можемо замінити `StripeGateway` на `PaypalGateway` за допомогою впровадження залежностей (Dependency Injection) або створити імітацію шлюзу (mock) у тестах PHPUnit без зміни логіки оформлення замовлення.

---

<a id="horizontal-reuse"></a>
## Горизонтальне перевикористання та динамічна поведінка: Трейти та анонімні класи (PHP 7.0+)

### 1. Теза
PHP підтримує лише одинарне успадкування. Щоб запобігти дублюванню коду без створення складних ієрархій класів, PHP пропонує два механізми:
* **Трейти (Traits)**: Блоки коду, які можна вставити в класи для горизонтального спільного використання методів. Якщо між двома трейтами виникає конфлікт імен, його потрібно вирішити за допомогою операторів `insteadof` та `as`.
* **Анонімні класи (PHP 7.0+)**: Легкі безіменні класи, що створюються на льоту. Вони корисні для простих, короткочасних реалізацій інтерфейсів або абстрактних класів.

### 2. Чому це важливо
Створення ієрархій класів лише для спільного використання коду (наприклад, логування або м'якого видалення - soft deletes) призводить до вразливості батьківських класів та заплутаних дерев успадкування. Трейти вирішують цю проблему, дозволяючи імпортувати кілька незалежних поведінок в один клас. Анонімні класи запобігають написанню зайвого шаблонного коду, коли потрібно імітувати клас або надати одноразову реалізацію для тесту чи зворотного виклику (callback).

### 3. Приклад
Наступний код демонструє трейт із вирішенням конфліктів та анонімний клас, що використовується для логування в тестах:

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

### 4. Наслідок
Завдяки використанню трейтів `AuditService` отримує спільну поведінку з кількох джерел без необхідності розширювати спільний базовий клас. За допомогою анонімних класів ми можемо створювати вбудовані реалізації `MailerInterface` для швидкого тестування або невеликих скриптів, зберігаючи файлову систему чистою від одноразових класів.

---

<a id="immutability"></a>
## Незмінність за замовчуванням: Readonly властивості (PHP 8.1+) та readonly класи (PHP 8.2+)

### 1. Теза
PHP пропонує сучасні інструменти для забезпечення незмінності стану (immutability) на рівні рушія:
* **Readonly властивості (PHP 8.1+)**: Властивості, які можна записати лише один раз (зазвичай у конструкторі). Подальші модифікації або спроби видалити їх за допомогою `unset()` викличуть помилку `Error`. Вони обов'язково повинні бути типізованими; нетипізовані властивості не можуть бути позначені як readonly.
* **Readonly класи (PHP 8.2+)**: Синтаксичний цукор, який неявно позначає всі властивості класу як `readonly` і запобігає створенню динамічних властивостей.

### 2. Чому це важливо
Об'єкти перенесення даних (DTO) та налаштування конфігурації передаються через багато рівнів програми. Якщо ці структури є мутабельними, будь-який сервіс під час життєвого циклу запиту може випадково змінити їхні значення, що призведе до прихованих помилок. Забезпечення незмінності на рівні компілятора гарантує, що після створення DTO його дані залишатимуться незмінними протягом усього часу виконання.

### 3. Приклад
Давайте подивимося, як ми забезпечуємо незмінність DTO користувача:

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

### 4. Наслідок
Будь-який код, який використовує `$config`, гарантовано не змінить конфігурацію користувача в середині запиту. Це усуває потребу в написанні шаблонного коду з приватними властивостями та методами лише для читання (getters), значно спрощуючи код DTO.

---

<a id="asymmetric-visibility"></a>
## Тонке налаштування меж: Асиметрична видимість (PHP 8.4+)

### 1. Теза
В PHP 8.4+ з'явилася **Асиметрична видимість**, яка дозволяє визначати різні рівні доступу для читання та запису властивості в одному рядку.
* Синтаксис: `public private(set) Type $property`
* Спочатку вказується видимість для читання (наприклад, `public`).
* Одразу після цього вказується видимість для запису (наприклад, `private(set)` або `protected(set)`).

### 2. Чому це важливо
До версії PHP 8.4, якщо ви хотіли, щоб властивість була доступна для читання ззовні, але записувалася лише всередині класу, вам доводилося робити її `private` та створювати публічний геттер, або робити клас/властивість `readonly`. Однак `readonly` повністю забороняє будь-які внутрішні зміни після ініціалізації. Асиметрична видимість дозволяє класу змінювати власні властивості всередині, залишаючи їх доступними лише для читання для зовнішнього світу без написання зайвих геттерів.

### 3. Приклад
Ось сутність `Product`, ціна якої може змінюватися з часом, але лише за допомогою методів класу:

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

### 4. Наслідок
Ми отримуємо чистий синтаксис публічних властивостей для читання значень, зберігаючи при цьому сувору інкапсуляцію. Нам більше не потрібно писати шаблонний код на кшталт `public function getPriceInCents(): int { return $this->priceInCents; }`.

---

<a id="trade-offs"></a>
## Архітектурні компроміси та обмеження

Жоден патерн не є срібною кулею. Використання можливостей ООП без оцінки їхніх компромісів призводить до створення надто складного або важкого в обслуговуванні коду.

### 1. Сильний зв'язок через успадкування (Проблема крихкого базового класу)
Хоча розширення класів є простим, воно створює сильний зв'язок. Якщо батьківський клас (`AbstractPaymentGateway`) змінює сигнатуру свого конструктора, кожен дочірній клас має бути оновлений. Віддавайте перевагу **композиції над успадкуванням**, впроваджуючи залежності, а не розширюючи базові класи.

### 2. Прихована магія трейтів
Трейти можуть легко маскувати погану архітектуру. Вони виглядають як копіювання коду на рівні компілятора, що полегшує створення прихованих залежностей та конфліктів методів. Якщо кільком класам потрібна однакова логіка, краще створити окремий клас-помічник (helper) і впровадити його як залежність.

### 3. Обмеження readonly властивостей
Readonly властивості (PHP 8.1+) не можна скинути або змінити навіть усередині класу. Якщо вам потрібно змінювати стан внутрішньо (наприклад, для відстеження змін стану або інвалідації кешу), readonly властивості не підійдуть. Крім того, вони не можуть мати значень за замовчуванням і не можуть використовуватися з хуками властивостей (property hooks в PHP 8.4+).

### 4. Обмеження асиметричної видимості
Асиметрична видимість (PHP 8.4+) вимагає, щоб операція запису (`set`) була суворішою за операцію читання (`get`). Наприклад, оголошення `private public(set)` є недійсним і викличе синтаксичну помилку. Крім того, властивості з асиметричною видимістю не можна передавати за посиланням:

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

## Практична порада: Дерево рішень щодо видимості

Визначаючи властивості класу, керуйтеся цим простим правилом, щоб забезпечити надійну інкапсуляцію та зберегти код чистим:

1. **Чи змінюється властивість коли-небудь після ініціалізації в конструкторі?**
   * **Ні**: Використовуйте властивість `readonly` (PHP 8.1+) або клас `readonly` (PHP 8.2+).
   * **Так**: Перейдіть до кроку 2.
2. **Чи повинен зовнішній код мати можливість змінювати значення напряму?**
   * **Ні**: Використовуйте `public private(set)` (PHP 8.4+), щоб відкрити значення для читання без надання доступу на запис.
   * **Так**: Використовуйте стандартну властивість `public` (пам'ятайте, що це обходить будь-яку валідацію).

---

## 🧠 Запитання для самоперевірки

1. **Чому PHP видає фатальну помилку (Fatal Error), якщо ви намагаєтеся оголосити властивість як `private public(set)` у PHP 8.4+?**
2. **Правда чи брехня?** Дочірній клас може перевизначити метод батьківського класу, навіть якщо батьківський метод позначений ключовим словом `final`.
3. **Що станеться, якщо спробувати видалити (unset) або повторно ініціалізувати `readonly` властивість після того, як її вже було встановлено?**

<details>
<summary><b>Показати відповіді</b></summary>

1. Видимість запису (`set`) має бути такою ж або суворішою за видимість читання. Оскільки `private(set)` є суворішим за `public`, оголошення `public private(set)` є валідним. Однак `public(set)` є ширшим за приватну (`private`) видимість читання, що є нелогічним і відхиляється парсером.
2. **Брехня.** Позначення класу або методу як `final` заважає дочірнім класам перевизначати цей метод або успадковувати цей клас.
3. Виникає виняток `Error`: *Cannot modify readonly property...* або *Cannot unset readonly property...*.
</details>
