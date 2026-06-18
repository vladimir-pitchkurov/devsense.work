---
title: "Поведінкові патерни GoF: Chain of Responsibility, Observer, Strategy та Command | DevSense"
description: "Детальний посібник із поведінкових патернів проектування GoF на PHP: Strategy, Observer, Chain of Responsibility, Command, Iterator, Mediator, Memento, State, Template Method, Visitor та Interpreter з чистими прикладами коду."
published: 2026-06-18
faq:
  - question: "У чому ключова різниця між патернами Strategy (Стратегія) та State (Стан)?"
    answer: "Патерн Стратегія використовується, коли ви хочете вибрати або замінити конкретний алгоритм або стратегію виконання під час роботи програми, і клієнт зазвичай знає про доступні стратегії. Патерн Стан використовується, коли об'єкт поводиться по-різному залежно від свого внутрішнього стану, а переходи між станами обробляються автоматично об'єктами-станами, зазвичай приховано від клієнта."
  - question: "Чим патерн Observer (Спостерігач) відрізняється від Publish-Subscribe (Видавець-Підписник)?"
    answer: "У патерні Спостерігач суб'єкт (видавець) безпосередньо зберігає список своїх спостерігачів і повідомляє їх напряму. У Publish-Subscribe є проміжний брокер повідомлень або канал подій, який повністю розділяє видавців та підписників, так що они нічого не знають один про одного."
  - question: "Коли слід використовувати Chain of Responsibility (Ланцюжок обов'язків) замість Decorator (Декоратор)?"
    answer: "Використовуйте Ланцюжок обов'язків, коли будь-який із кількох об'єктів-обробників може обробити запит (або передати його далі), і виконання запиту може бути перервано (short-circuited) на будь-якому етапі. Використовуйте Декоратор, коли ви хочете виконати всі обгортки в стопці для розширення поведінки базового об'єкта, без можливості дострокової зупинки або вибору єдиного обробника."
---

# Поведінкові патерни GoF: Chain of Responsibility, Observer, Strategy та Command

Поведінкові патерни проектування пов'язані з алгоритмами та розподілом обов'язків між об'єктами. Вони описують не просто патерни об'єктів або класів, але й патерни спілкування між ними. Ці патерни характеризують складні потоки керування, які важко відстежити під час роботи програми.

**Пов'язані матеріали:** [Структурні патерни GoF](structural-design-patterns) · [Перехід від моноліту до мікросервісної архітектури](monolith-to-microservices-architecture)

## Зміст

* [Вступ до поведінкових патернів](#intro)
* [Патерн Strategy (Стратегія)](#strategy)
* [Патерн Observer (Спостерігач)](#observer)
* [Патерн Chain of Responsibility (Ланцюжок обов'язків)](#chain-of-responsibility)
* [Патерн Command (Команда)](#command)
* [Інші поведінкові патерни (Iterator, Mediator, Memento, State, Template Method, Visitor, Interpreter)](#others)
* [Типові помилки](#common-mistakes)
* [Чек-лист для самоперевірки](#checklist)
* [Резюме](#summary)
* [Квіз для самоперевірки](#self-test-quiz)

---

<a id="intro"></a>
## Вступ до поведінкових патернів

У той час як порождаючі патерни фокусуються на інстанціюванні об'єктів, а структурні патерни — на компонуванні класів, **поведінкові патерни** концентруються на їхній взаємодії. Вони визначають чисті способи делегування дій, маршрутизації запитів, керування змінами станів та динамічної підміни алгоритмів під час роботи додатка.

---

<a id="strategy"></a>
## Патерн Strategy (Стратегія)

Патерн **Strategy** визначає сімейство схожих алгоритмів, інкапсулює кожен із них і робить їх взаємозамінними. Стратегія дозволяє змінювати алгоритми незалежно від клієнтів, які їх використовують.

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
## Патерн Observer (Спостерігач)

Патерн **Observer** створює залежність «один до багатьох» між об'єктами так, що при зміні стану одного об'єкта всі залежні об'єкти сповіщаються та оновлюються автоматично.

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
## Патерн Chain of Responsibility (Ланцюжок обов'язків)

Патерн **Chain of Responsibility** дозволяє передавати запити послідовно по ланцюжку обробників. Кожен наступний обробник вирішує, чи може він сам обробити запит, чи його потрібно передати далі по ланцюжку.

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
## Патерн Command (Команда)

Патерн **Command** перетворює запит на самостійний об'єкт, який містить всю інформацію про запит. Ця трансформація дозволяє передавати запити як аргументи методів, відкладати або ставити їх у чергу, а також підтримувати скасування операцій.

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
## Інші поведінкові патерни

- **Iterator (Ітератор)**: Надає послідовний доступ до елементів колекції, не розкриваючи її внутрішню структуру (список, стек, дерево тощо).
- **Mediator (Посередник)**: Обмежує пряму взаємодію між об'єктами, змушуючи їх спілкуватися тільки через спеціальний об'єкт-посередник.
- **Memento (Знімок)**: Дозволяє зберігати та відновлювати внутрішній стан об'єкта, не порушуючи інкапсуляцію.
- **State (Стан)**: Дозволяє об'єкту змінювати свою поведінку залежно від внутрішнього стану. Здається, що об'єкт змінює свій клас.
- **Template Method (Шаблонний метод)**: Визначає основу алгоритму в суперкласі, дозволяючи підкласам перевизначати окремі кроки без зміни загальної структури алгоритму.
- **Visitor (Відвідувач)**: Дозволяє додавати нові операції до класів об'єктів без зміни самих класів.
- **Interpreter (Інтерпретатор)**: Дозволяє аналізувати граматику або вирази мови з використанням спеціалізованої структури класів.

---

<a id="common-mistakes"></a>
## Типові помилки

1. **Надмірне використання патерну Спостерігач**: Створення занадто великої кількості глобальних підписників на події. Це призводить до прихованих побічних ефектів і ускладнює відлагодження.
2. **Жорсткий зв'язок у Ланцюжку обов'язків**: Програмування послідовності прямо всередині обробників замість збирання ланцюжка на рівні конфігурації або диспетчера.
3. **Вибір Стану замість Стратегії**: Додавання властивостей керування станом у той час як потрібні були незалежні від стану, чисті об'єкти-Стратегії.

---

<a id="checklist"></a>
## Чек-лист для самоперевірки

1. **Слабка пов'язаність:** Чи знають ваші Спостерігачі про існування та поведінку один одного?
2. **Гнучкість:** Чи можуть Стратегії підмінятися динамічно під час роботи програми без потреби перезапуску контекстного об'єкта?
3. **Зупинка ланцюжка:** Чи переривають обробники запитів виконання (short-circuit) відразу ж, як тільки умови перевірки не виконуються?
4. **Команда:** Чи відв'язаний клас-відправник (invoker) від реального отримувача (receiver) команди?

---

<a id="summary"></a>
## Резюме

Поведінкові патерни проектування визначають способи взаємодії класів та розподілу їхніх обов'язків. Використовуйте **Strategy** для зміни ключових алгоритмів «на льоту». Використовуйте **Observer** для реалізації розсилок та систем подій/слухачів. Використовуйте **Chain of Responsibility** для створення шарів middleware. Використовуйте **Command** для інкапсуляції завдань та встановлення їх у чергу.

---

<a id="self-test-quiz"></a>
## Квіз для самоперевірки

### Питання 1: Що є основною ознакою того, що вам слід застосувати патерн Стратегія?
- А) Один метод містить величезний блок вкладених операторів `if-else` або `switch-case`, які вибирають різні способи виконання одного й того самого завдання.
- B) Вам необхідно написати метод резервного копіювання, що зберігає стан бази даних.
- C) Ви хочете динамічно кешувати результати запитів до бази даних.

<details>
<summary><b>Показати відповідь</b></summary>

**Правильна відповідь: А**
Коли ви бачите множинні умови, що вибирають різні алгоритми (наприклад, `if ($provider === 'Stripe')` або `else if ($provider === 'PayPal')`), це явний сигнал про потребу патерну Стратегія. Заміна цих умов поліморфізмом дає чистий код, що відповідає принципу відкритості/закритості.
</details>

### Питання 2: Як обробники в Ланцюжку обов'язків приймають рішення про передачу запиту далі?
- A) Кожен обробник зобов'язаний передати запит далі, дострокова зупинка не допускається.
- B) Обробник обробляє запит і або повертає результат (перериваючи ланцюжок), або передає керування наступному обробнику, викликаючи його метод `handle()`.
- C) Послідовність ланцюжка рандомізується при кожному запиті.

<details>
<summary><b>Показати відповідь</b></summary>

**Правильна відповідь: B**
Основний сенс Ланцюжка обов'язків полягає в тому, що обробники динамічно вирішують, чи перервати виконання (наприклад, якщо перевірка авторизації не вдалася), чи делегувати виконання далі за допомогою екземпляра наступного обробника.
</details>
