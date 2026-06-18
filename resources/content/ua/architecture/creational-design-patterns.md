---
title: "Породжувальні патерни GoF: Singleton, Factory, Builder та Prototype | DevSense"
description: "Детальний розбір породжувальних патернів проектування GoF на PHP: Singleton, Factory Method, Abstract Factory, Builder та Prototype зі строгою типізацією та реальними прикладами."
published: 2026-06-18
faq:
  - question: "Чому патерн Singleton (Одинак) часто вважають антипатерном?"
    answer: "Одинак вважається антипатерном, оскільки він вводить глобальний стан у додаток, створює жорстку залежність класів від статичного ресурсу, ускладнює модульне тестування (через збереження стану між тестами) та порушує принцип єдиної відповідальності, керуючи одночасно своїм життєвим циклом та бізнес-логікою."
  - question: "Коли слід використовувати патерн Builder (Будівельник) замість Factory (Фабрики)?"
    answer: "Використовуйте Будівельник, коли створення об'єкта вимагає великої кількості параметрів, складного покрокового налаштування або опціональних кроків. Фабрики краще підходять для однокрокового створення, коли клієнту потрібно лише вказати тип і отримати готовий об'єкт."
  - question: "У чому різниця між Фабричним методом (Factory Method) та Абстрактною фабрикою (Abstract Factory)?"
    answer: "Фабричний метод визначає інтерфейс для створення одного об'єкта, делегуючи ініціалізацію спадкоємцям. Абстрактна фабрика надає інтерфейс для створення цілих сімейств пов'язаних або залежних об'єктів (наприклад, відповідних елементів інтерфейсу або платіжних провайдерів) без вказівки їх конкретних класів."
---

# Породжувальні патерни GoF: Singleton, Factory, Builder та Prototype

Породжувальні патерни проектування абстрагують процес створення об'єктів. Вони роблять систему незалежною від того, як створюються, компонуються та представляються її об'єкти. Інкапсулюючи знання про конкретні класи, які використовує система, вони приховують деталі того, як екземпляри цих класів створюються та зв'язуються один з одним.

**Пов'язані матеріали:** [Перехід від моноліту до мікросервісної архітектури](monolith-to-microservices-architecture) · [Пулінг з'єднань з базою даних в PHP](php-database-connection-pooling)

## Зміст

* [Вступ до породжувальних патернів](#intro)
* [Патерн Singleton (Одинак)](#singleton)
* [Фабричний метод та Абстрактна фабрика](#factory)
* [Патерн Builder (Будівельник)](#builder)
* [Патерн Prototype (Прототип)](#prototype)
* [Типові помилки](#common-mistakes)
* [Чекліст для самоперевірки](#checklist)
* [Резюме](#summary)
* [Квіз для самоперевірки](#self-test-quiz)

---

<a id="intro"></a>
## Вступ до породжувальних патернів

Створення об'єктів «вручну» через оператор `new` може легко засмітити бізнес-логику та призвести до жорсткої пов'язаності (tight coupling). Породжувальні патерни дозволяють відв'язати клієнтський код від конкретних деталей ініціалізації класів.

У сучасній PHP-розробці життєвим циклом об'єктів часто керують Dependency Injection (DI) контейнери, але знання породжувальних патернів критично важливе для написання власних бібліотек розширення, SDK та складних доменних об'єктів.

---

<a id="singleton"></a>
## Паттерн Singleton (Одинак)

Патерн **Singleton** гарантує, що клас має тільки один екземпляр, і надає до нього глобальну точку доступу.

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

    // Заборона клонування та десеріалізації
    private function __clone() {}
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize a singleton class.");
    }

    public function query(string $sql): array
    {
        // Виконання SQL-запиту...
        return ["status" => "success", "sql" => $sql];
    }
}
```

> [!WARNING]
> **Попередження для юніт-тестів**: Одинаки зберігають свій стан між тестами. Якщо не скидати статичну властивість `$instance` між запусками тестів, це порушить ізоляцію тестування.

---

<a id="factory"></a>
## Фабричний метод та Абстрактна фабрика

### Фабричний метод
Фабричний метод визначає інтерфейс для створення об'єкта, але дозволяє підкласам вирішувати, який клас інкапсулювати.

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
        // Логіка інтеграції зі Stripe...
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
Абстрактная фабрика надає інтерфейс для створення сімейств взаємопов'язаних або взаємозалежних об'єктів.

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
## Паттерн Builder (Будівельник)

Патерн **Builder** відокремлює конструювання складного об'єкта від його представлення, дозволяючи одному й тому самому процесу покрокового збирання створювати різні представлення.

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

Патерн **Prototype** задає види створюваних об'єктів за допомогою екземпляра-прототипу і створює нові об'єкти шляхом копіювання цього прототипу.

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
        // Глибоке клонування пов'язаних об'єктів
        $this->scheduledAt = new DateTime();
        $this->title = "Копія " . $this->title;
    }
}
```

---

<a id="common-mistakes"></a>
## Типові помилки

1. **Зловживання Одинаком**: Використання Одинаків як глобальних змінних. Це створює сильну пов'язаність, руйнує ізоляцію юніт-тестів та ховає реальні залежності класів.
2. **Зайва фабрикація**: Створення фабрик для об'єктів, які можна ініціалізувати безпосередньо через конструктор, коли немає потреби в підтримці різних варіантів сімейств.
3. **Поверхневе клонування у Прототипі**: Нехтування глибоким клонуванням для посилальних типів (об'єктів всередині об'єкта), через що клонований об'єкт ділить внутрішній стан із прототипом.

---

<a id="checklist"></a>
## Чекліст для самоперевірки

1. **Інкапсуляція:** Чи зробили ви конструктор приватним, метод клонування приватним та метод wakeup генеруючим виключення у вашому Одинаку?
2. **Складність:** Чи вимагає об'єкт покрокового або опціонального налаштування? Якщо так, використовуйте Будівельник.
3. **Клонування:** Чи переконалися ви, що в класі Прототипу реалізовано глибоке копіювання для вкладених об'єктів?
4. **Гнучкість:** Чи використовуєте ви інтерфейси для продуктів, що створюються фабриками?

---

<a id="summary"></a>
## Резюме

Породжувальні патерни проектування допомагають відокремити створення об'єктів від прикладної логіки. Використовуйте **Singleton** тільки в крайніх випадках, коли дійсно потрібен рівно один екземпляр. Використовуйте **Фабрики** для відокремлення клієнтського коду від конкретних реалізацій. Використовуйте **Будівельник** для складання складних об'єктів за кроками. Використовуйте **Прототип**, коли інстанціювання є ресурсомістким і клонування вже готового об'єкта ефективніше.

---

<a id="self-test-quiz"></a>
## Квіз для самоперевірки

### Вопрос 1: У чому полягає основна архітектурна загроза прямого використання Одинака в бізнес-логіці?
- А) PHP аварійно завершить роботу через брак оперативної пам'яті.
- B) Це створює жорстку пов'язаність і прихований глобальний стан, роблячи код практично нетестируемим в ізоляції.
- C) Це викликає критичну помилку компіляції в PHP 8.0+.

<details>
<summary><b>Показати відповідь</b></summary>

**Правильна відповідь: B**
Пряме звернення до статичного методу Одинака пов'язує ваш код із глобальним станом. У тестах ви не зможете підмінити це з'єднання заглушкою, а стан буде перетікати з тесту в тест, роблячи результати нестабільними.
</details>

### Вопрос 2: Що станеться при клонуванні об'єкта (`$b = clone $a`) в PHP, якщо клас містить посилальні властивості (інші об'єкти) і не має методу `__clone()`?
- А) PHP автоматично рекурсивно скопіює всі вкладені об'єкти.
- B) PHP виконає поверхневе копіювання: властивості-посилання в `$b` будуть вказувати на ті самі екземпляри об'єктів, що й в `$a`.
- C) Буде негайно викинута помилка виконання.

<details>
<summary><b>Показати відповідь</b></summary>

**Правильна відповідь: B**
Клонування за замовчуванням у PHP є поверхневим. Будь-які вкладені об'єкти збережуть свої оригінальні посилання, тому зміна вкладеного об'єкта в клоні `$b` зачепить і оригінал `$a`.
</details>
