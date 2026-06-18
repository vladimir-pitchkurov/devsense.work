---
title: "Шаблони за създаване на обекти GoF: Singleton, Factory, Builder и Prototype | DevSense"
description: "Подробен анализ на GoF шаблоните за създаване на обекти в PHP: Singleton, Factory Method, Abstract Factory, Builder и Prototype със строга типизация и реални примери."
published: 2026-06-18
faq:
  - question: "Защо шаблонът Singleton (Единак) често се счита за антимодел?"
    answer: "Единакът се счита за антимодел, тъй като въвежда глобално състояние в приложението, свързва тясно класовете със статичен ресурс, затруднява модулното тестване (поради запазване на състоянието между тестовете) и нарушава принципа за единствена отговорност."
  - question: "Кога трябва да използвате шаблона Builder (Строител) вместо Factory (Фабрика)?"
    answer: "Използвайте Строител, когато създаването на обект изисква голям брой параметри, сложна конфигурация стъпка по стъпка или незадължителни стъпки. Фабриките са по-подходящи за едностъпково инстанциране, където клиентът трябва само да посочи тип."
  - question: "Каква е разликата между Фабричен метод (Factory Method) и Абстрактна фабрика (Abstract Factory)?"
    answer: "Фабричният метод дефинира интерфейс за създаване на един обект, като оставя инстанцирането на наследниците. Абстрактната фабрика предоставя интерфейс за създаване на цели фамилии от свързани или зависими обекти без указване на техните конкретни класове."
---

# Шаблони за създаване на обекти GoF: Singleton, Factory, Builder и Prototype

Шаблоните за създаване на обекти (creational design patterns) абстрахират процеса по инстанциране. Те правят системата независима от това как се създават, композират и представят нейните обекти. Чрез капсулиране на знанията за конкретните класове, те скриват детайлите за това как се създават и свързват екземплярите на тези класове.

**Свързани ръководства:** [Преход от монолит към микросервизна архитектура](monolith-to-microservices-architecture) · [Пул за връзки с база данни в PHP](php-database-connection-pooling)

## Съдържание

* [Въведение в шаблоните за създаване на обекти](#intro)
* [Шаблонът Singleton (Единак)](#singleton)
* [Фабричен метод и Абстрактна фабрика](#factory)
* [Шаблонът Builder (Строител)](#builder)
* [Шаблонът Prototype (Прототип)](#prototype)
* [Чести грешки](#common-mistakes)
* [Чеклист за самопроверка](#checklist)
* [Резюме](#summary)
* [Тест за самопроверка](#self-test-quiz)

---

<a id="intro"></a>
## Въведение в шаблоните за създаване на обекти

Създаването на обекти директно чрез оператора `new` може лесно да задръсти бизнес логиката и да доведе до силна обвързаност (tight coupling). Шаблоните за създаване позволяват развързването на клиентския код от конкретните детайли по иницииране на класовете.

В съвременната разработка на PHP жизненият цикъл на обектите често се управлява от контейнери за инжектиране на зависимости (DI containers), но познаването на шаблоните за създаване е изключително важно за писане на собствени SDK, разширения и сложни домейни.

---

<a id="singleton"></a>
## Шаблонът Singleton (Единак)

Шаблонът **Singleton** гарантира, че даден клас има само един екземпляр, и осигурява глобална точка за достъп до него.

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

    // Забрана на клонирането и десериализацията
    private function __clone() {}
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize a singleton class.");
    }

    public function query(string $sql): array
    {
        // Изпълнение на SQL заявка...
        return ["status" => "success", "sql" => $sql];
    }
}
```

> [!WARNING]
> **Предупреждение при тестване**: Единаците запазват състоянието си между отделните тестове. Ако не нулирате статичното свойство `$instance` между тестовете, това ще наруши изолацията на тестовата среда.

---

<a id="factory"></a>
## Фабричен метод и Абстрактна фабрика

### Фабричен метод
Фабричният метод дефинира интерфейс за създаване на обект, но позволява на подкласовете да решат кой точно клас да инстанцират.

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
        // Логика за интеграция със Stripe...
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

### Абстрактна фабрика
Абстрактната фабрика осигурява интерфейс за създаване на фамилии от свързани или зависими обекти.

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
## Шаблонът Builder (Строител)

Шаблонът **Builder** разделя конструирането на сложен обект от неговото представяне, като позволява на един и същ процес на сглобяване да създава различни представяния.

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
## Шаблонът Prototype (Прототип)

Шаблонът **Prototype** специфицира видовете обекти, които да се създават, чрез прототипен екземпляр и създава нови обекти чрез копиране на този прототип.

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
        // Дълбоко клониране на вложените обекти
        $this->scheduledAt = new DateTime();
        $this->title = "Копие на " . $this->title;
    }
}
```

---

<a id="common-mistakes"></a>
## Чести грешки

1. **Прекомерна употреба на Singleton**: Използване на Единаци като глобални променливи. Това създава силна обвързаност, нарушава изолацията на тестовете и скрива зависимостите.
2. **Излишно усложняване**: Създаване на фабрики за обекти, които могат да бъдат инстанцирани директно през конструктор, без да има нужда от поддръжка на различни варианти.
3. **Плитко клониране в Прототипа**: Пропускане на дълбокото клониране за свойства от референтен тип (обекти в обекта), при което клонираният обект споделя памет с оригиналния прототип.

---

<a id="checklist"></a>
## Чеклист за самопроверка

1. **Капсулация:** Направихте ли конструктора и метода clone частни и хвърля ли изключение методът wakeup във вашия Singleton?
2. **Сложност:** Изисква ли обектът стъпка по стъпка или опционална конфигурация? Ако да, използвайте Строител.
3. **Клониране:** Уверихте ли се, че в класа на Прототипа е реализирано дълбоко копиране за вложените обекти?
4. **Гъвкавост:** Използвате ли интерфейси за продуктите, създавани от фабриките?

---

<a id="summary"></a>
## Резюме

Шаблоните за създаване на обекти помагат за разделянето на създаването на обекти от бизнес логиката. Използвайте **Singleton** само в крайни случаи, когато наистина ви трябва точно един екземпляр. Използвайте **Фабрики** за развързване на клиентския код от конкретните имплементации. Използвайте **Строител** за пошагово сглобяване на сложни обекти. Използвайте **Прототип**, когато инстанцирането е скъпо и клонирането е по-ефективно.

---

<a id="self-test-quiz"></a>
## Тест за самопроверка

### Въпрос 1: Какъв е основният архитектурен риск от директното рефериране на Singleton клас в бизнес логиката ви?
- А) PHP ще се срине поради препълване на паметта.
- B) Това създава силна обвързаност и скрито глобално състояние, което прави кода изключително труден за тестване в изолация.
- C) Това предизвиква критична грешка при компилиране в PHP 8.0+.

<details>
<summary><b>Виж отговора</b></summary>

**Правилен отговор: B**
Директното обръщение към статичен метод на Singleton обвързва вашия код с глобално състояние. В тестовете не можете да подмените тази връзка със заглушка, а състоянието ще се прехвърля между тестовете, правейки ги нестабилни.
</details>

### Въпрос 2: Какво се случва при клониране на обект (`$b = clone $a`) в PHP, ако класът съдържа референтни свойства (други обекти) и няма дефиниран метод `__clone()`?
- А) PHP автоматично клонира рекурсивно всички вложени обекти.
- B) PHP извършва плитко копиране: свойствата-референции в `$b` ще сочат към същите екземпляри на обекти, както и в `$a`.
- C) Веднага се хвърля грешка по време на изпълнение.

<details>
<summary><b>Виж отговора</b></summary>

**Правилен отговор: B**
Клонирането по подразбиране в PHP е плитко. Всички вложени обекти запазват оригиналните си референции, така че модифицирането на вложен обект в клонирания обект ще засегне и оригинала.
</details>
