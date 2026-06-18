---
title: "Порождающие паттерны GoF: Singleton, Factory, Builder и Prototype | DevSense"
description: "Подробный разбор порождающих паттернов проектирования GoF на PHP: Singleton, Factory Method, Abstract Factory, Builder и Prototype со строгой типизацией и реальными примерами."
published: 2026-06-18
faq:
  - question: "Почему паттерн Singleton (Одиночка) часто считают антипаттерном?"
    answer: "Одиночка считается антипаттерном, так как он вводит глобальное состояние в приложение, создает жесткую зависимость классов от статического ресурса, затрудняет модульное тестирование (из-за сохранения состояния между тестами) и нарушает принцип единственной ответственности, управляя одновременно своим жизненным циклом и бизнес-логикой."
  - question: "Когда следует использовать паттерн Builder (Строитель) вместо Factory (Фабрики)?"
    answer: "Используйте Строитель, когда создание объекта требует большого количества параметров, сложной пошаговой настройки или опциональных шагов. Фабрики лучше подходят для одношагового создания, когда клиенту нужно лишь указать тип и получить готовый объект."
  - question: "В чем разница между Фабричным методом (Factory Method) и Абстрактной фабрикой (Abstract Factory)?"
    answer: "Фабричный метод определяет интерфейс для создания одного объекта, делегируя инициализацию наследникам. Абстрактная фабрика предоставляет интерфейс для создания целых семейств связанных или зависимых объектов (например, соответствующих элементов интерфейса или платежных провайдеров) без указания их конкретных классов."
---

# Порождающие паттерны GoF: Singleton, Factory, Builder и Prototype

Порождающие паттерны проектирования абстрагируют процесс создания объектов. Они делают систему независимой от того, как создаются, композируются и представляются ее объекты. Инкапсулируя знания о конкретных классах, которые использует система, они скрывают детали того, как экземпляры этих классов создаются и связываются друг с другом.

**Связанные материалы:** [Переход от монолита к микросервисной архитектуре](monolith-to-microservices-architecture) · [Пулинг соединений с базой данных в PHP](php-database-connection-pooling)

## Содержание

* [Введение в порождающие паттерны](#intro)
* [Паттерн Singleton (Одиночка)](#singleton)
* [Фабричный метод и Абстрактная фабрика](#factory)
* [Паттерн Builder (Строитель)](#builder)
* [Паттерн Prototype (Прототип)](#prototype)
* [Типичные ошибки](#common-mistakes)
* [Чеклист для самопроверки](#checklist)
* [Резюме](#summary)
* [Квиз для самопроверки](#self-test-quiz)

---

<a id="intro"></a>
## Введение в порождающие паттерны

Создание объектов «вручную» через оператор `new` может легко засорить бизнес-логику и привести к жесткой связанности (tight coupling). Порождающие паттерны позволяют отвязать клиентский код от конкретных деталей инициализации классов.

В современной PHP-разработке жизненным циклом объектов часто управляют Dependency Injection (DI) контейнеры, но знание порождающих паттернов критически важно для написания собственных библиотек расширения, SDK и сложных доменных объектов.

---

<a id="singleton"></a>
## Паттерн Singleton (Одиночка)

Паттерн **Singleton** гарантирует, что у класса есть только один экземпляр, и предоставляет к нему глобальную точку доступа.

```php
// app/Database/DatabaseConnection.php
declare(strict_types=1);

namespace App\Database;

use RuntimeException;

class DatabaseConnection
{
    private static ?self $instance = null;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            if (empty($config)) {
                throw new RuntimeException("Database connection must be initialized with configuration.");
            }
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    // Запрет клонирования и десериализации
    private function __clone() {}
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize a singleton class.");
    }

    public function query(string $sql): array
    {
        // Выполнение SQL-запроса...
        return ["status" => "success", "sql" => $sql];
    }
}
```

> [!WARNING]
> **Предупреждение для юнит-тестов**: Одиночки сохраняют свое состояние между тестами. Если не сбрасывать статическое свойство `$instance` между запусками тестов, это нарушит изоляцию тестирования.

---

<a id="factory"></a>
## Фабричный метод и Абстрактная фабрика

### Фабричный метод
Фабричный метод определяет интерфейс для создания объекта, но позволяет подклассам решать, какой класс инкапсулировать.

```php
// app/Payments/PaymentGatewayFactory.php
declare(strict_types=1);

namespace App\Payments;

interface PaymentGateway
{
    public function charge(float $amount): bool;
}

class StripeGateway implements PaymentGateway
{
    public function charge(float $amount): bool
    {
        // Логика интеграции со Stripe...
        return true;
    }
}

abstract class PaymentProcessor
{
    abstract protected function createGateway(): PaymentGateway;

    public function process(float $amount): bool
    {
        $gateway = $this->createGateway();
        return $gateway->charge($amount);
    }
}

class StripeProcessor extends PaymentProcessor
{
    protected function createGateway(): PaymentGateway
    {
        return new StripeGateway();
    }
}
```

### Абстрактная фабрика
Абстрактная фабрика предоставляет интерфейс для создания семейств взаимосвязанных или взаимозависимых объектов.

```php
// app/UI/WidgetFactory.php
declare(strict_types=1);

namespace App\UI;

interface Button { public function render(): string; }
interface Input { public function render(): string; }

class DarkButton implements Button { public function render(): string { return "<button class='dark'>Submit</button>"; } }
class DarkInput implements Input { public function render(): string { return "<input class='dark' />"; } }

interface WidgetFactory
{
    public function createButton(): Button;
    public function createInput(): Input;
}

class DarkThemeFactory implements WidgetFactory
{
    public function createButton(): Button { return new DarkButton(); }
    public function createInput(): Input { return new DarkInput(); }
}
```

---

<a id="builder"></a>
## Паттерн Builder (Строитель)

Паттерн **Builder** отделяет конструирование сложного объекта от его представления, позволяя одному и тому же процессу пошаговой сборки создавать различные представления.

```php
// app/Http/RequestBuilder.php
declare(strict_types=1);

namespace App\Http;

class Request
{
    public string $url = '';
    public string $method = 'GET';
    public array $headers = [];
    public string $body = '';
}

class RequestBuilder
{
    private Request $request;

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): self
    {
        $this->request = new Request();
        return $this;
    }

    public function url(string $url): self
    {
        $this->request->url = $url;
        return $this;
    }

    public function method(string $method): self
    {
        $this->request->method = strtoupper($method);
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->request->headers[$name] = $value;
        return $this;
    }

    public function body(string $body): self
    {
        $this->request->body = $body;
        return $this;
    }

    public function build(): Request
    {
        $result = $this->request;
        $this->reset();
        return $result;
    }
}
```

---

<a id="prototype"></a>
## Паттерн Prototype (Прототип)

Паттерн **Prototype** задает виды создаваемых объектов с помощью экземпляра-прототипа и создает новые объекты путем копирования этого прототипа.

```php
// app/Marketing/CampaignTemplate.php
declare(strict_types=1);

namespace App\Marketing;

use DateTime;

class CampaignTemplate
{
    public string $title;
    public string $content;
    public DateTime $scheduledAt;

    public function __construct(string $title, string $content)
    {
        $this->title = $title;
        $this->content = $content;
        $this->scheduledAt = new DateTime();
    }

    public function __clone()
    {
        // Глубокое клонирование связанных объектов
        $this->scheduledAt = new DateTime();
        $this->title = "Копия " . $this->title;
    }
}
```

---

<a id="common-mistakes"></a>
## Типичные ошибки

1. **Злоупотребление Одиночкой**: Использование Одиночек как глобальных переменных. Это создает сильную связность, рушит изоляцию юнит-тестов и прячет реальные зависимости классов.
2. **Излишняя фабрикация**: Создание фабрик для объектов, которые можно инициализировать напрямую через конструктор, когда нет необходимости в поддержке разных вариантов семейств.
3. **Поверхностное клонирование в Прототипе**: Пренебрежение глубоким клонированием для ссылочных типов (объектов внутри объекта), из-за чего клонированный объект делит внутреннее состояние с прототипом.

---

<a id="checklist"></a>
## Чеклист для самопроверки

1. **Инкапсуляция:** Сделали ли вы конструктор приватным, метод клонирования приватным и метод wakeup генерирующим исключение в вашем Одиночке?
2. **Сложность:** Требует ли объект пошаговой или опциональной настройки? Если да, используйте Строитель.
3. **Клонирование:** Убедились ли вы, что в классе Прототипа реализовано глубокое копирование для вложенных объектов?
4. **Гибкость:** Используете ли вы интерфейсы для продуктов, создаваемых фабриками?

---

<a id="summary"></a>
## Резюме

Порождающие паттерны проектирования помогают отделить создание объектов от прикладной логики. Используйте **Singleton** только в крайних случаях, когда действительно нужен ровно один экземпляр. Используйте **Фабрики** для отделения клиентского кода от конкретных реализаций. Используйте **Строитель** для сборки сложных объектов по шагам. Используйте **Прототип**, когда инстанцирование ресурсоемко и клонирование уже готового объекта эффективнее.

---

<a id="self-test-quiz"></a>
## Квиз для самопроверки

### Вопрос 1: В чем заключается основная архитектурная угроза прямого использования Одиночки в бизнес-логике?
- А) PHP аварийно завершит работу из-за нехватки оперативной памяти.
- B) Это создает жесткую связанность и скрытое глобальное состояние, делая код практически нетестируемым в изоляции.
- C) Это вызывает критическую ошибку компиляции в PHP 8.0+.

<details>
<summary><b>Показать ответ</b></summary>

**Правильный ответ: B**
Прямое обращение к статическому методу Одиночки связывает ваш код с глобальным состоянием. В тестах вы не сможете подменить это соединение заглушкой, а состояние будет перетекать из теста в тест, делая результаты нестабильными.
</details>

### Вопрос 2: Что произойдет при клонировании объекта (`$b = clone $a`) в PHP, если класс содержит ссылочные свойства (другие объекты) и не имеет метода `__clone()`?
- А) PHP автоматически рекурсивно скопирует все вложенные объекты.
- B) PHP выполнит поверхностное копирование: свойства-ссылки в `$b` будут указывать на те же самые экземпляры объектов, что и в `$a`.
- C) Будет немедленно выброшена ошибка выполнения.

<details>
<summary><b>Показать ответ</b></summary>

**Правильный ответ: B**
Клонирование по умолчанию в PHP является поверхностным. Любые вложенные объекты сохранят свои оригинальные ссылки, поэтому изменение вложенного объекта в клоне `$b` затронет и оригинал `$a`.
</details>
