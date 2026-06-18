---
title: "Поведенчески шаблони GoF: Chain of Responsibility, Observer, Strategy и Command | DevSense"
description: "Подробно ръководство за поведенческите шаблони за проектиране GoF в PHP: Strategy, Observer, Chain of Responsibility, Command, Iterator, Mediator, Memento, State, Template Method, Visitor и Interpreter с чисти примери за код."
published: 2026-06-18
faq:
  - question: "Каква е основната разлика между шаблоните Strategy (Стратегия) и State (Състояние)?"
    answer: "Шаблонът Стратегия се използва, когато искате да изберете или замените конкретен алгоритъм или стратегия за изпълнение по време на работа на програмата, и клиентът обикновено знае за наличните стратегии. Шаблонът Състояние се използва, когато даден обект се държи различно в зависимост от вътрешното си състояние, а преходите между състоянията се управляват автоматично от самите обекти-състояния, обикновено скрити от клиента."
  - question: "Как шаблонът Observer (Наблюдател) се различава от Publish-Subscribe (Издател-Абонат)?"
    answer: "В шаблона Наблюдател субектът (издателят) директно поддържа списък със своите наблюдатели и ги уведомява директно. В Publish-Subscribe има междинен брокер на съобщения или канал за събития, който напълно разделя издателите от абонатите, така че те не знаят нищо един за друг."
  - question: "Кога трябва да използвате Chain of Responsibility (Верига от отговорности) вместо Decorator (Декоратор)?"
    answer: "Използвайте Верига от отговорности, когато всеки един от няколко обекта-обработчици може да обработи заявка (или да я предаде нататък), като изпълнението може да бъде спряно на всеки етап. Използвайте Декоратор, когато искате да изпълните всички обвивки в стека, за да обогатите поведението на основния обект, без възможност за преждевременно спиране или избор на един обработчик."
---

# Поведенчески шаблони GoF: Chain of Responsibility, Observer, Strategy и Command

Поведенческите шаблони за проектиране са свързани с алгоритмите и разпределението на отговорностите между обектите. Те описват не просто шаблони на обекти или класове, но и шаблони за комуникация между тях. Тези шаблони характеризират сложни потоци на управление, които са трудни за проследяване по време на работа на програмата.

**Свързани ръководства:** [Структурни шаблони GoF](structural-design-patterns) · [Преход от монолит към микросервизна архитектура](monolith-to-microservices-architecture)

## Съдържание

* [Въведение в поведенческите шаблони](#intro)
* [Шаблонът Strategy (Стратегия)](#strategy)
* [Шаблонът Observer (Наблюдател)](#observer)
* [Шаблонът Chain of Responsibility (Верига от отговорности)](#chain-of-responsibility)
* [Шаблонът Command (Команда)](#command)
* [Други поведенчески шаблони (Iterator, Mediator, Memento, State, Template Method, Visitor, Interpreter)](#others)
* [Типични грешки](#common-mistakes)
* [Чеклист за самопроверка](#checklist)
* [Резюме](#summary)
* [Тест за самопроверка](#self-test-quiz)

---

<a id="intro"></a>
## Въведение в поведенческите шаблони

Докато шаблоните за създаване се фокусират върху инстанцирането на обекти, а структурните шаблони — върху композицията на класове, **поведенческите шаблони** се концентрират върху тяхното взаимодействие. Те дефинират чисти начини за делегиране на действия, маршрутизиране на заявки, управление на промените в състоянието и динамична подмяна на алгоритми по време на изпълнение на приложението.

---

<a id="strategy"></a>
## Шаблонът Strategy (Стратегия)

Шаблонът **Strategy** дефинира фамилия от сходни алгоритми, капсулира всеки от тях и ги прави взаимозаменяеми. Стратегията позволява на алгоритъма да варира независимо от клиентите, които го използват.

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
## Шаблонът Observer (Наблюдател)

Шаблонът **Observer** създава зависимост „едно към много“ между обекти така, че при промяна на състоянието на един обект всички зависими обекти се уведомяват и обновяват автоматично.

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
## Шаблонът Chain of Responsibility (Верига от отговорности)

Шаблонът **Chain of Responsibility** позволява преминаването на заявки последователно по верига от обработчици. Всеки следващ обработчик решава дали може сам да обработи заявката или трябва да я предаде нататък по веригата.

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
## Шаблонът Command (Команда)

Шаблонът **Command** превръща заявката в самостоятелен обект, съдържащ цялата информация за нея. Тази трансформация позволява преминаването на заявки като аргументи на методи, отлагане или поставянето им в опашка, както и поддръжка за отмяна на операции.

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
## Други поведенчески шаблони

- **Iterator (Итератор)**: Предоставя последователен достъп до елементите на дадена колекция, без да разкрива нейната вътрешна структура (списък, стек, дърво и т.н.).
- **Mediator (Посредник)**: Ограничава пряката комуникация между обектите, като ги принуждава да си сътрудничат само чрез специален обект-посредник.
- **Memento (Спомен / Снимка)**: Позволява запазването и възстановяването на предишното състояние на даден обект, без да се нарушава капсулацията.
- **State (Състояние)**: Позволява на обекта да променя поведението си при промяна на вътрешното му състояние. Изглежда сякаш обектът е променил класа си.
- **Template Method (Шаблонен метод)**: Определя скелета на алгоритъм в суперкласа, но позволява на подкласовете да пренаписват специфични стъпки от алгоритъма, без да променят структурата му.
- **Visitor (Посетител)**: Позволява ви да разделите алгоритмите от обектите, върху които те оперират.
- **Interpreter (Интерпретатор)**: Позволява анализирането на граматика или изрази на даден език с използването на специализирана структура от класове.

---

<a id="common-mistakes"></a>
## Типични грешки

1. **Прекомерна употреба на шаблона Наблюдател**: Създаване на твърде много глобални абонати за събития. Това води до скрити странични ефекти и усложнява отстраняването на грешки.
2. **Тясна свързаност във Веригата от отговорности**: Програмиране на последователността директно вътре в обработчиците, вместо сглобяването ѝ на ниво конфигурация или оркестратор.
3. **Избор на Състояние вместо Стратегия**: Добавяне на свойства за управление на състоянието в обекти-Стратегии, които всъщност трябва да са независими от състоянието.

---

<a id="checklist"></a>
## Чеклист за самопроверка

1. **Слаба свързаност:** Наясно ли са вашите Наблюдатели за присъствието и поведението един на друг?
2. **Гъвкавост:** Могат ли Стратегиите да се сменят динамично по време на изпълнение, без да е необходимо рестартиране на контекстния обект?
3. **Спиране на веригата:** Прекъсват ли обработчиците на заявки изпълнението (short-circuit) веднага щом условията за валидация не са изпълнени?
4. **Команда:** Развързан ли е класът-изпращач (invoker) от реалния получател (receiver) на командата?

---

<a id="summary"></a>
## Резюме

Поведенческите шаблони за проектиране управляват комуникацията между класовете и разпределянето на отговорностите. Използвайте **Strategy** за подмяна на основни алгоритми в движение. Използвайте **Observer** за изграждане на push известия или системи със събития/слушатели. Използвайте **Chain of Responsibility** за изграждане на middleware слоеве. Използвайте **Command** за капсулиране на задачи и поставянето им в опашка.

---

<a id="self-test-quiz"></a>
## Тест за самопроверка

### Въпрос 1: Какъв е основният признак, че трябва да използвате шаблона Стратегия?
- А) Един метод съдържа огромен блок от вложени `if-else` или `switch-case` инструкции, избиращи различни начини за извършване на една и съща задача.
- B) Трябва да напишете метод за архивиране, който записва състоянията на базата данни.
- C) Искате да кеширате динамично резултатите от заявките към базата данни.

<details>
<summary><b>Покажи отговора</b></summary>

**Правилен отговор: А**
Когато забележите множество условия, избиращи различни алгоритми (например `if ($provider === 'Stripe')` или `else if ($provider === 'PayPal')`), това е ясен сигнал за необходимост от Стратегия. Замяната на тези условия с полиморфизъм води до чист код, съответстващ на принципа отвореност/затвореност.
</details>

### Въпрос 2: Как обработчиците във Веригата от отговорности решават дали да препратят заявката нататък?
- A) Всеки обработчик е длъжен винаги да предава заявката, преждевременно спиране не е разрешено.
- B) Даден обработчик обработва заявката и или връща резултат (прекъсвайки веригата), или предава управлението на следващия обработчик чрез извикване на неговия метод `handle()`.
- C) Последователността на веригата се рандомизира при всяка заявка.

<details>
<summary><b>Покажи отговора</b></summary>

**Правилен отговор: B**
Основният смисъл на Веригата от отговорности е, че обработчиците решават динамично дали да прекъснат изпълнението (например при неуспешна оторизация) или да делегират обработката на следващия обработчик по веригата.
</details>
