---
title: "Поведенческие паттерны GoF: Chain of Responsibility, Observer, Strategy и Command | DevSense"
description: "Подробное руководство по поведенческим паттернам проектирования GoF на PHP: Strategy, Observer, Chain of Responsibility, Command, Iterator, Mediator, Memento, State, Template Method, Visitor и Interpreter с чистыми примерами кода."
published: 2026-06-18
faq:
  - question: "В чем основная разница между паттернами Strategy (Стратегия) и State (Состояние)?"
    answer: "Паттерн Стратегия используется, когда вы хотите выбрать или заменить конкретный алгоритм или стратегию выполнения во время работы программы, и клиент обычно знает о доступных стратегиях. Паттерн Состояние используется, когда объект ведет себя по-разному в зависимости от своего внутреннего состояния, а переходы между состояниями обрабатываются автоматически объектами-состояниями, обычно будучи скрытыми от клиента."
  - question: "Чем паттерн Observer (Наблюдатель) отличается от Publish-Subscribe (Издатель-Подписчик)?"
    answer: "В паттерне Наблюдатель субъект (издатель) напрямую хранит список своих наблюдателей и уведомляет их напрямую. В Publish-Subscribe есть промежуточный брокер сообщений или канал событий, который полностью разделяет издателей и подписчиков, так что они ничего не знают друг о друге."
  - question: "Когда следует использовать Chain of Responsibility (Цепочка обязанностей) вместо Decorator (Декоратор)?"
    answer: "Используйте Цепочку обязанностей, когда любой из нескольких объектов-обработчиков может обработать запрос (или передать его дальше), и выполнение запроса может быть прервано (short-circuited) на любом этапе. Используйте Декоратор, когда вы хотите выполнить все обертки в стопке для расширения поведения базового объекта, без возможности досрочной остановки или выбора единственного обработчика."
---

# Поведенческие паттерны GoF: Chain of Responsibility, Observer, Strategy и Command

Поведенческие паттерны проектирования связаны с алгоритмами и распределением обязанностей между объектами. Они описывают не просто паттерны объектов или классов, но и паттерны общения между ними. Эти паттерны характеризуют сложные потоки управления, которые трудно отследить во время работы программы.

**Связанные материалы:** [Структурные паттерны GoF](structural-design-patterns) · [Переход от монолита к микросервисной архитектуре](monolith-to-microservices-architecture)

## Содержание

* [Введение в поведенческие паттерны](#intro)
* [Паттерн Strategy (Стратегия)](#strategy)
* [Паттерн Observer (Наблюдатель)](#observer)
* [Паттерн Chain of Responsibility (Цепочка обязанностей)](#chain-of-responsibility)
* [Паттерн Command (Команда)](#command)
* [Другие поведенческие паттерны (Iterator, Mediator, Memento, State, Template Method, Visitor, Interpreter)](#others)
* [Типичные ошибки](#common-mistakes)
* [Чеклист для самопроверки](#checklist)
* [Резюме](#summary)
* [Квиз для самопроверки](#self-test-quiz)

---

<a id="intro"></a>
## Введение в поведенческие паттерны

В то время как порождающие паттерны фокусируются на инстанцировании объектов, а структурные паттерны — на компоновке классов, **поведенческие паттерны** концентрируются на их взаимодействии. Они определяют чистые способы делегирования действий, маршрутизации запросов, управления изменениями состояний и динамической подмены алгоритмов во время работы приложения.

---

<a id="strategy"></a>
## Паттерн Strategy (Стратегия)

Паттерн **Strategy** определяет семейство схожих алгоритмов, инкапсулирует каждый из них и делает их взаимозаменяемыми. Стратегия позволяет изменять алгоритмы независимо от клиентов, которые их используют.

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
## Паттерн Observer (Наблюдатель)

Паттерн **Observer** создает зависимость «один ко многим» между объектами так, что при изменении состояния одного объекта все зависимые объекты оповещаются и обновляются автоматически.

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
## Паттерн Chain of Responsibility (Цепочка обязанностей)

Паттерн **Chain of Responsibility** позволяет передавать запросы последовательно по цепочке обработчиков. Каждый последующий обработчик решает, может ли он сам обработать запрос или его нужно передать дальше по цепочке.

```php
// app/Http/Middleware/RequestHandlerInterface.php
declare(strict_types=1);

namespace App\Http\Middleware;

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
## Паттерн Command (Команда)

Паттерн **Command** превращает запрос в самостоятельный объект, содержащий всю информацию о запросе. Эта трансформация позволяет передавать запросы как аргументы методов, откладывать или ставить их в очередь, а также поддерживать отмену операций.

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
## Другие поведенческие паттерны

- **Iterator (Итератор)**: Предоставляет последовательный доступ к элементам коллекции, не раскрывая её внутреннюю структуру (список, стек, дерево и т.д.).
- **Mediator (Посредник)**: Ограничивает прямое взаимодействие между объектами, заставляя их общаться только через специальный объект-посредник.
- **Memento (Снимок)**: Позволяет сохранять и восстанавливать внутреннее состояние объекта, не нарушая инкапсуляцию.
- **State (Состояние)**: Позволяет объекту изменять свое поведение в зависимости от внутреннего состояния. Кажется, что объект меняет свой класс.
- **Template Method (Шаблонный метод)**: Определяет основу алгоритма в суперклассе, позволяя подклассам переопределять отдельные шаги без изменения общей структуры алгоритма.
- **Visitor (Посетитель)**: Позволяет добавлять новые операции к классам объектов без изменения самих классов.
- **Interpreter (Интерпретатор)**: Позволяет анализировать грамматику или выражения языка с использованием специализированной структуры классов.

---

<a id="common-mistakes"></a>
## Типичные ошибки

1. **Избыточное использование паттерна Наблюдатель**: Создание слишком большого количества глобальных подписчиков на события. Это приводит к скрытым побочным эффектам и усложняет отладку.
2. **Жесткая связь в Цепочке обязанностей**: Программирование последовательности прямо внутри обработчиков вместо сборки цепочки на уровне конфигурации или диспетчера.
3. **Выбор Состояния вместо Стратегии**: Добавление свойств управления состоянием в то то время как нужны были независимые от состояния, чистые объекты-Стратегии.

---

<a id="checklist"></a>
## Чеклист для самопроверки

1. **Слабая связанность:** Знают ли ваши Наблюдатели о существовании и поведении друг друга?
2. **Гибкость:** Могут ли Стратегии подменяться динамически во время работы программы без необходимости перезапуска контекстного объекта?
3. **Остановка цепочки:** Прерывают ли обработчики запросов выполнение (short-circuit) сразу же, как только условия проверки не соблюдаются?
4. **Команда:** Отвязан ли класс-отправитель (invoker) от реального получателя (receiver) команды?

---

<a id="summary"></a>
## Резюме

Поведенческие паттерны проектирования определяют способы взаимодействия классов и разделения их обязанностей. Используйте **Strategy** для смены ключевых алгоритмов «на лету». Используйте **Observer** для реализации рассылок и систем событий/слушателей. Используйте **Chain of Responsibility** для создания слоев middleware. Используйте **Command** для инкапсуляции задач и постановки их в очередь.

---

<a id="self-test-quiz"></a>
## Квиз для самопроверки

### Вопрос 1: Что является основным признаком того, что вам следует применить паттерн Стратегия?
- А) Один метод содержит огромный блок вложенных операторов `if-else` или `switch-case`, выбирающих разные способы выполнения одной и той же задачи.
- B) Вам необходимо написать метод резервного копирования, сохраняющий состояние базы данных.
- C) Вы хотите динамически кэшировать результаты запросов к базе данных.

<details>
<summary><b>Показать ответ</b></summary>

**Правильный ответ: А**
Когда вы видите множественные условия, выбирающие разные алгоритмы (например, `if ($provider === 'Stripe')` или `else if ($provider === 'PayPal')`), это явный сигнал о необходимости паттерна Стратегия. Замена этих условий полиморфизмом дает чистый код, соответствующий принципу открытости/закрытости.
</details>

### Вопрос 2: Как обработчики в Цепочке обязанностей принимают решение о передаче запроса дальше?
- A) Каждый обработчик обязан передать запрос дальше, досрочная остановка не допускается.
- B) Обработчик обрабатывает запрос и либо возвращает результат (прерывая цепочку), либо передает управление следующему обработчику, вызывая его метод `handle()`.
- C) Последовательность цепочки рандомизируется при каждом запросе.

<details>
<summary><b>Показать ответ</b></summary>

**Правильный ответ: B**
Основной смысл Цепочки обязанностей заключается в том, что обработчики динамически решают, прервать ли выполнение (например, если проверка авторизации не удалась) или делегировать выполнение дальше с помощью экземпляра следующего обработчика.
</details>
