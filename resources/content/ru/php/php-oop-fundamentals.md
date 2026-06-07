---
title: "Основы ООП в PHP: от базовых концепций до асимметричной видимости | DevSense"
description: "Освойте современное объектно-ориентированное программирование на PHP. Изучите инкапсуляцию, наследование, интерфейсы, абстрактные классы, трейты, анонимные классы (7.0+), readonly-свойства (8.1+), readonly-классы (8.2+) и асимметричную видимость (8.4+)."
faq:
  - question: "В чем разница между интерфейсом и абстрактным классом в PHP?"
    answer: "Интерфейс определяет чистый публичный контракт, который должны реализовывать классы, и допускает множественное наследование интерфейсов. Абстрактный класс — это частично реализованный класс, который может содержать состояние, логику конструктора и защищенные методы, но PHP поддерживает только одиночное наследование, поэтому класс может наследоваться только от одного абстрактного класса."
  - question: "Как работают readonly-свойства в PHP 8.1+?"
    answer: "Readonly-свойства могут быть инициализированы только один раз, обычно внутри конструктора класса. Любая последующая попытка изменить или удалить (unset) свойство вызовет исключение Error, гарантируя неизменяемость."
  - question: "Что такое асимметричная видимость в PHP 8.4+?"
    answer: "Асимметричная видимость позволяет объявлять разные уровни доступа для чтения и записи свойства. Например, 'public private(set) string $name' разрешает публичное чтение, но ограничивает запись рамками самого класса, избавляя от шаблонного кода геттеров."
  - question: "Как разрешать конфликты имен при использовании трейтов в PHP?"
    answer: "Если два трейта определяют метод с одинаковым именем, PHP выдаст фатальную ошибку, если только вы явно не разрешите этот конфликт с помощью оператора 'insteadof' для выбора одного из методов или оператора 'as' для создания псевдонима под другим именем."
---

# Основы ООП в PHP: защита состояния и проектирование чистых интерфейсов

Если вы когда-либо тратили часы на отладку загадочного изменения состояния в крупном веб-приложении, вы знаете, насколько хрупкими могут быть объекты с плохой инкапсуляцией. Сервис меняет публичное свойство у общего экземпляра, и внезапно данные в базе данных по всей системе оказываются поврежденными. Проблема кроется не в самом объектно-ориентированном программировании (ООП), а в том, как мы выстраиваем границы инкапсуляции.

В процедурном коде данные передаются глобально, что делает их уязвимыми для неконтролируемых изменений. В наивном ООП классы часто рассматриваются как простые контейнеры для свойств, открывающие доступ ко всему подряд через публичные переменные или стандартные геттеры и сеттеры. Современный PHP решает эти архитектурные проблемы, переходя от открытых, мутабельных объектов к строгим, саморегулирующимся структурам.

> [!IMPORTANT]
> Современное ООП в PHP — это не просто организация функций в классы; это создание надежных границ состояния, определение строгих публичных контрактов и использование типизации для предотвращения некорректного состояния во время работы приложения.

**Целевой уровень**: Junior / Middle  

---

## Содержание
* [Инкапсуляция и модификаторы доступа](#encapsulation-modifiers)
* [Контракты и абстракция: наследование, абстрактные классы и интерфейсы](#contracts-abstraction)
* [Горизонтальное повторное использование: трейты и анонимные классы (PHP 7.0+)](#horizontal-reuse)
* [Неизменяемость по умолчанию: readonly-свойства (PHP 8.1+) и readonly-классы (PHP 8.2+)](#immutability)
* [Тонкая настройка границ видимости: асимметричная видимость (PHP 8.4+)](#asymmetric-visibility)
* [Архитектурные компромиссы и ограничения](#trade-offs)
* [🧠 Вопросы для самопроверки](#self-check)

---

<a id="encapsulation-modifiers"></a>
## Инкапсуляция и модификаторы доступа

### 1. Тезис (Point)
Инкапсуляция — это скрытие внутренней реализации и состояния объекта и предоставление контролируемого интерфейса для работы с ним. В PHP доступ к свойствам и методам регулируется тремя модификаторами:
* `public`: Доступ отовсюду.
* `protected`: Доступ только внутри самого класса и в наследующих классах.
* `private`: Доступ только внутри того класса, где свойство или метод были объявлены.

### 2. Почему это важно (Why It Matters)
Без инкапсуляции внешние сервисы могут изменять внутреннее состояние объекта напрямую, в обход бизнес-логики и проверок. Например, если баланс счета в классе `BankAccount` доступен для записи извне, мы не сможем гарантировать, что баланс не станет отрицательным или что операция пополнения или списания будет корректно зафиксирована в истории операций (логе).

### 3. Пример (Example)
Рассмотрим, как открытые свойства приводят к повреждению состояния, и как приватные свойства с публичными методами гарантируют целостность данных:

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

### 4. Последствия (Consequence)
Объявление свойства `$balance` приватным дает классу полный контроль над его валидацией. Внешний код больше не может выполнить команду вида `$account->balance = -500.0;`. Изменения состояния происходят только через контролируемые операции (`deposit` и `withdraw`), гарантируя, что лог операций всегда синхронизирован с итоговым балансом.

---

<a id="contracts-abstraction"></a>
## Контракты и абстракция: наследование, абстрактные классы и интерфейсы

### 1. Тезис (Point)
PHP предоставляет возможности для создания переиспользуемой логики и жестких архитектурных границ с помощью наследования и полиморфизма:
* **Наследование (`extends`)**: Позволяет дочернему классу перенимать свойства и методы родительского класса. Дочерние классы могут переопределять методы родителя, если только родительский метод или класс не помечен ключевым словом `final`.
* **Абстрактные классы (`abstract class`)**: Шаблоны, которые нельзя инстанцировать напрямую. Они могут содержать как полностью реализованные методы, так и объявления сигнатур абстрактных методов (`abstract`), реализация которых обязательна в дочерних классах.
* **Интерфейсы (`interface`)**: Чистые контракты, содержащие только сигнатуры публичных методов без какой-либо реализации. Класс может реализовывать несколько интерфейсов, что позволяет обойти ограничение PHP на одиночное наследование.

### 2. Почему это важно (Why It Matters)
Наследование позволяет делить общий код, однако оно создает сильное зацепление (coupling) между родителем и наследником. Абстрактные классы выступают промежуточным звеном, предлагая частично заполненный шаблон. Интерфейсы же предоставляют максимальную степень независимости: они описывают *что* умеет делать объект, а не *как* он это делает. Это позволяет легко менять адаптеры баз данных, драйверы отправки почты или платежные шлюзы, не меняя код бизнес-логики, который от них зависит.

### 3. Пример (Example)
Создадим архитектурную схему для платежных шлюзов с использованием интерфейса, абстрактного класса и конкретных реализаций:

```php
// app/Payments/PaymentGatewayInterface.php
namespace App\Payments;

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

### 4. Последствия (Consequence)
Высокоуровневые классы (например, контроллер оформления заказа `CheckoutController`) могут зависеть от интерфейса `PaymentGatewayInterface`, а не от конкретных классов. Мы можем в любой момент заменить `StripeGateway` на `PaypalGateway` через внедрение зависимостей (Dependency Injection) или подменить платежную систему заглушкой (mock) в тестах PHPUnit без изменений в логике оформления заказа.

---

<a id="horizontal-reuse"></a>
## Горизонтальное повторное использование: трейты и анонимные классы (PHP 7.0+)

### 1. Тезис (Point)
В PHP поддерживается только одиночное наследование классов. Для повторного использования кода без создания глубоких иерархий классов и для динамического расширения поведения PHP предлагает два инструмента:
* **Трейты (Traits)**: Наборы методов, которые можно внедрять в классы для горизонтального шеринга логики. В случае совпадения имен методов из разных трейтов конфликт разрешается вручную с помощью операторов `insteadof` и `as`.
* **Анонимные классы (PHP 7.0+)**: Одноразовые безымянные классы, создаваемые на лету. Они полезны для быстрой реализации интерфейсов или абстрактных классов без выделения под них отдельного файла.

### 2. Почему это важно (Why It Matters)
Создание иерархий классов только ради переиспользования вспомогательного кода (например, логирования или механизма Soft Delete) приводит к раздуванию родительских классов. Трейты решают эту проблему, позволяя независимо подключать нужную функциональность в любые классы. Анонимные классы избавляют от необходимости писать лишний код (boilerplate), когда вам нужно передать простую реализацию интерфейса в тест или callback-функцию.

### 3. Пример (Example)
Ниже показано использование трейтов с разрешением конфликтов имен, а также создание анонимного класса на лету:

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

### 4. Последствия (Consequence)
Благодаря трейтам класс `AuditService` получает функциональность логирования из разных источников без необходимости наследоваться от общего базового класса. Анонимные классы позволяют создавать компактные реализации `MailerInterface` прямо в коде тестов или консольных команд, не засоряя файловую систему.

---

<a id="immutability"></a>
## Неизменяемость по умолчанию: readonly-свойства (PHP 8.1+) и readonly-классы (PHP 8.2+)

### 1. Тезис (Point)
Современный PHP предоставляет мощные инструменты для контроля неизменяемости (immutability) состояния объектов на уровне компилятора:
* **Readonly-свойства (PHP 8.1+)**: Свойства, которые могут быть записаны только один раз (обычно в конструкторе). Попытка изменить их значение позже или удалить через `unset()` приведет к ошибке `Error`. Такие свойства обязательно должны быть типизированы.
* **Readonly-классы (PHP 8.2+)**: Синтаксический сахар, который неявно делает все свойства класса readonly, а также блокирует возможность динамического создания свойств на лету.

### 2. Почему это важно (Why It Matters)
Объекты передачи данных (DTO) и настройки конфигурации проходят через множество слоев приложения. Если эти структуры мутабельны, любой сервис по пути может случайно перезаписать их значения, что приведет к трудноотслеживаемым ошибкам. Гарантия неизменяемости на уровне движка PHP гарантирует, что переданные в DTO данные останутся ровно в том виде, в каком они были созданы.

### 3. Пример (Example)
Посмотрим, как объявить неизменяемый класс для конфигурации:

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

### 4. Последствия (Consequence)
Любой код, принимающий `$config`, уверен, что настройки пользователя не изменятся в середине выполнения запроса. Это устраняет необходимость писать шаблонные приватные свойства и геттеры для них, делая код DTO максимально лаконичным.

---

<a id="asymmetric-visibility"></a>
## Тонкая настройка границ видимости: асимметричная видимость (PHP 8.4+)

### 1. Тезис (Point)
PHP 8.4+ представляет **асимметричную видимость свойств** (Asymmetric Visibility), позволяющую задавать разные уровни доступа для чтения и для записи свойства в рамках одной строки.
* Синтаксис: `public private(set) Тип $свойство`
* Сначала указывается область видимости для чтения (например, `public`).
* Сразу после этого в скобках пишется область видимости для записи (например, `private(set)` или `protected(set)`).

### 2. Почему это важно (Why It Matters)
До PHP 8.4, если требовалось сделать свойство доступным для чтения извне, но запретить внешнюю запись, приходилось либо делать свойство приватным и писать публичный геттер, либо делать весь класс `readonly`. Однако `readonly` полностью запрещает внутренние изменения свойства после инициализации. Асимметричная видимость позволяет самому классу спокойно изменять свои свойства изнутри, но оставляет их видимыми только для чтения снаружи без написания лишних геттеров.

### 3. Пример (Example)
Рассмотрим модель товара `Product`, цена которого может изменяться во времени, но только через методы самого класса:

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

### 4. Последствия (Consequence)
Мы получаем чистый и лаконичный синтаксис прямого обращения к свойствам для чтения, сохраняя при этом строгую инкапсуляцию. Больше нет необходимости плодить функции-обертки вроде `public function getPriceInCents(): int { return $this->priceInCents; }`.

---

<a id="trade-offs"></a>
## Архитектурные компромиссы и ограничения

Ни один паттерн не является серебряной пулей. Применение возможностей ООП без оценки компромиссов может переусложнить систему.

### 1. Сильное зацепление при наследовании (проблема хрупкого базового класса)
Хотя наследование классов кажется самым простым решением, оно связывает классы слишком тесно. Если изменить сигнатуру конструктора в родительском классе (`AbstractPaymentGateway`), придется обновить конструкторы во всех дочерних классах. Отдавайте предпочтение **композиции перед наследованием**, внедряя зависимости вместо расширения классов.

### 2. Скрытая «магия» трейтов
Трейты могут легко маскировать плохой дизайн. По сути, это встроенный в компилятор механизм copy-paste, который часто скрывает зависимости классов и приводит к коллизиям имен. Если нескольким классам требуется одинаковая логика, лучше выделить ее в отдельный сервис и внедрять как зависимость.

### 3. Ограничения readonly-свойств
Значения readonly-свойств (PHP 8.1+) невозможно сбросить или изменить даже внутри самого класса после их инициализации. Если вам нужно изменять состояние объекта во время его работы (например, для сброса кэша или переключения флагов состояния), readonly-свойства не подойдут. Также они не могут иметь значений по умолчанию и несовместимы с хуками свойств (property hooks) в PHP 8.4+.

### 4. Ограничения асимметричной видимости
Асимметричная видимость (PHP 8.4+) требует, чтобы видимость на запись (`set`) была строго уже или равна видимости на чтение (`get`). Объявление вида `private public(set)` приведет к фатальной ошибке парсера. Кроме того, свойства с асимметричной видимостью нельзя передавать по ссылке:
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

## Практический вывод: Дерево принятия решений по видимости свойств

При определении свойств в классе используйте следующее простое правило для выбора модификаторов:

1. **Будет ли свойство когда-либо изменяться после создания объекта в конструкторе?**
   * **Нет**: Используйте `readonly`-свойство (PHP 8.1+) или объявите весь класс как `readonly` (PHP 8.2+).
   * **Да**: Переходите к шагу 2.
2. **Должен ли внешний код иметь возможность изменять это свойство напрямую?**
   * **Нет**: Объявите его как `public private(set)` (PHP 8.4+), чтобы разрешить прямое чтение, но закрыть запись на уровне класса.
   * **Да**: Используйте стандартное `public`-свойство (помня о том, что это обходит любые проверки валидности данных).

---

## 🧠 Вопросы для самопроверки

1. **Почему PHP выдаст ошибку компиляции при попытке объявить свойство как `private public(set)` в PHP 8.4+?**
2. **Верно ли утверждение?** Дочерний класс может переопределить метод родительского класса, даже если родительский метод помечен ключевым словом `final`.
3. **Что произойдет, если попытаться вызвать `unset()` для `readonly`-свойства после его инициализации?**

<details>
<summary><b>Показать ответы</b></summary>

1. Уровень видимости записи (`set`) должен быть таким же или более строгим, чем уровень чтения (`get`). Поскольку `private(set)` строже, чем `public`, объявление `public private(set)` корректно. А вот `public(set)` шире, чем `private` на чтение, что противоречит логике инкапсуляции, и парсер блокирует такое объявление.
2. **Неверно.** Использование ключевого слова `final` для метода запрещает его переопределение в дочерних классах (а для класса — полностью запрещает наследование от него).
3. PHP выбросит исключение `Error` с сообщением о невозможности удаления или модификации readonly-свойства (*Cannot modify readonly property...* или *Cannot unset readonly property...*).
</details>
