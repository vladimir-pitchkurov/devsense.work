---
title: "Структурні патерни GoF: Adapter, Decorator, Facade та Proxy | DevSense"
description: "Детальний посібник із структурних патернів проектування GoF на PHP: Adapter, Bridge, Composite, Decorator, Facade, Flyweight та Proxy з чистими прикладами коду."
published: 2026-06-18
faq:
  - question: "У чому ключова різниця між патернами Adapter (Адаптер) та Decorator (Декоратор)?"
    answer: "Адаптер змінює інтерфейс об'єкта, щоб зробити його сумісним з іншим інтерфейсом, дозволяючи несумісним класам працювати разом. Декоратор зберігає початковий інтерфейс об'єкта, що обгортається, динамічно розширюючи його поведінку."
  - question: "Чим патерн Facade (Фасад) відрізняється від патерну Mediator (Посередник)?"
    answer: "Фасад надає спрощений односторонній інтерфейс до складної підсистеми, не обмежуючи при цьому прямий доступ до її класів. Посередник централізує та координує складне двостороннє спілкування між багатьма об'єктами-колегами, приховуючи їхні прямі зв'язки один з одним."
  - question: "Коли слід використовувати Proxy (Замісник) замість Decorator (Декоратора)?"
    answer: "Використовуйте Замісник, коли вам потрібно контролювати доступ до об'єкта (наприклад, лінива ініціалізація, кешування, права доступу), при цьому замісник сам керує життєвим циклом реального об'єкта. Декоратор використовується для динамічного додавання обов'язків об'єкту під час виконання без керування його життєвим циклом."
---

# Структурні патерни GoF: Adapter, Decorator, Facade та Proxy

Структурні патерни проектування пояснюють, як збирати об'єкти та класи у більші структури, зберігаючи при цьому ці структури гнучкими та ефективними. Вони допомагають вибудовувати складні ієрархії класів, використовуючи композицію замість жорсткого успадкування.

**Пов'язані матеріали:** [Перехід від моноліту до мікросервісної архітектури](monolith-to-microservices-architecture) · [Атаки на веб-додатки та захист від них](web-attacks-and-prevention)

## Зміст

* [Вступ до структурних патернів](#intro)
* [Патерн Adapter (Адаптер)](#adapter)
* [Патерн Decorator (Декоратор)](#decorator)
* [Патерн Facade (Фасад)](#facade)
* [Патерн Proxy (Замісник)](#proxy)
* [Інші структурні патерни (Bridge, Composite, Flyweight)](#others)
* [Типові помилки](#common-mistakes)
* [Чек-лист для самоперевірки](#checklist)
* [Резюме](#summary)
* [Квіз для самоперевірки](#self-test-quiz)

---

<a id="intro"></a>
## Вступ до структурних патернів

Успадкування — це потужний інструмент, але воно є статичним і визначається на етапі компіляції. Структурні патерни використовують **композицію об'єктів**, дозволяючи об'єднувати їхню поведінку динамічно під час виконання. Це забезпечує більшу гнучкість і позбавляє від створення занадто глибоких і жорстких ієрархій класів.

---

<a id="adapter"></a>
## Патерн Adapter (Адаптер)

Патерн **Adapter** дозволяє об'єктам із несумісними інтерфейсами працювати разом. Він виступає в ролі обгортки (перекладача), яка перетворює виклики від клієнтського коду у формат, зрозумілий сторонньому або застарілому (legacy) коду.

```php
// app/Logging/LoggerInterface.php
declare(strict_types=1);

namespace App\Logging;

interface LoggerInterface
{
    public function logMessage(string $message): void;
}

// Legacy third-party service that cannot be changed directly
class LegacyLoggerService
{
    public function sendRawLog(string $rawMessage, int $level): void
    {
        // Legacy system logging...
    }
}

// The Adapter class that bridges the two interfaces
class LoggerAdapter implements LoggerInterface
{
    private LegacyLoggerService $legacyLogger;

    public function __construct(LegacyLoggerService $legacyLogger)
    {
        $this->legacyLogger = $legacyLogger;
    }

    public function logMessage(string $message): void
    {
        // Map the call to the legacy service method
        $this->legacyLogger->sendRawLog($message, 1);
    }
}
```

---

<a id="decorator"></a>
## Патерн Decorator (Декоратор)

Патерн **Decorator** дозволяє динамічно додавати об'єктам нові обов'язки, обгортаючи їх у спеціальні об'єкти-обгортки.

```php
// app/Repository/ProductRepositoryInterface.php
declare(strict_types=1);

namespace App\Repository;

interface ProductRepositoryInterface
{
    public function findById(int $id): array;
}

class SqlProductRepository implements ProductRepositoryInterface
{
    public function findById(int $id): array
    {
        // Fetch product from SQL database...
        return ["id" => $id, "name" => "Core PHP Book"];
    }
}

// Decorator implementing the same interface
class CachingProductRepositoryDecorator implements ProductRepositoryInterface
{
    private ProductRepositoryInterface $wrapped;
    private array $cache = [];

    public function __construct(ProductRepositoryInterface $wrapped)
    {
        $this->wrapped = $wrapped;
    }

    public function findById(int $id): array
    {
        if (!isset($this->cache[$id])) {
            $this->cache[$id] = $this->wrapped->findById($id);
        }

        return $this->cache[$id];
    }
}
```

---

<a id="facade"></a>
## Патерн Facade (Фасад)

Патерн **Facade** надає простий інтерфейс до складної системи класів, бібліотеки або фреймворку.

```php
// app/Shop/OrderFacade.php
declare(strict_types=1);

namespace App\Shop;

class InventoryService { public function check(int $itemId): bool { return true; } }
class PaymentService { public function charge(int $userId, float $amount): bool { return true; } }
class DeliveryService { public function schedule(int $itemId, int $userId): void {} }

class OrderFacade
{
    private InventoryService $inventory;
    private PaymentService $payment;
    private DeliveryService $delivery;

    public function __construct(
        InventoryService $inventory,
        PaymentService $payment,
        DeliveryService $delivery
    ) {
        $this->inventory = $inventory;
        $this->payment = $payment;
        $this->delivery = $delivery;
    }

    public function placeOrder(int $userId, int $itemId, float $price): bool
    {
        if (!$this->inventory->check($itemId)) {
            return false;
        }

        if (!$this->payment->charge($userId, $price)) {
            return false;
        }

        $this->delivery->schedule($itemId, $userId);
        return true;
    }
}
```

---

<a id="proxy"></a>
## Патерн Proxy (Замісник)

Патерн **Proxy** надає об'єкт-замінник, який контролює доступ до оригінального об'єкта. Замісник може брати на себе ліниву ініціалізацію, контроль доступу, кешування або логування.

```php
// app/Http/HeavyReportInterface.php
declare(strict_types=1);

namespace App\Http;

interface HeavyReportInterface
{
    public function render(): string;
}

class RealHeavyReport implements HeavyReportInterface
{
    public function __construct()
    {
        // Simulating heavy database computation
        usleep(50000);
    }

    public function render(): string
    {
        return "<h1>Heavy PDF Report Output</h1>";
    }
}

// Caching & Lazy Initialization Proxy
class HeavyReportProxy implements HeavyReportInterface
{
    private ?RealHeavyReport $realReport = null;
    private ?string $cachedHtml = null;

    public function render(): string
    {
        if ($this->cachedHtml === null) {
            if ($this->realReport === null) {
                // Instantiated only when requested
                $this->realReport = new RealHeavyReport();
            }
            $this->cachedHtml = $this->realReport->render();
        }

        return $this->cachedHtml;
    }
}
```

---

<a id="others"></a>
## Інші структурні патерни

- **Bridge (Міст)**: Розділяє абстракцію та реалізацію на дві окремі ієрархії класів так, щоб їх можна було змінювати незалежно один від одного.
- **Composite (Компонувальник)**: Об'єднує об'єкти в деревоподібні структури для представлення ієрархій «частина-ціле» і дозволяє клієнтам однаково трактувати як поодинокі, так і складені об'єкти.
- **Flyweight (Легковаговик / Пристосуванець)**: Дозволяє вмістити більшу кількість об'єктів у виділену пам'ять за рахунок спільного використання спільних частин стану замість зберігання всіх даних у кожному об'єкті.

---

<a id="common-mistakes"></a>
## Типові помилки

1. **Створення «Божественного» Фасаду**: Розміщення реальної бізнес-логіки всередині класу Фасаду замість делегування її підсистемам. Фасад повинен лише координувати кроки.
2. **Плутанина між Декоратором та Адаптером**: Спроба адаптувати несумісний інтерфейс за допомогою Декоратора або динамічне розширення поведінки за допомогою Адаптера.
3. **Занадто довгі ланцюжки декораторів**: Нанизування занадто великої кількості декораторів на один об'єкт, що ускладнює відлагодження та читання стеку викликів.

---

<a id="checklist"></a>
## Чек-лист для самоперевірки

1. **Інтерфейси:** Чи реалізують ваші класи Адаптера та Декоратора стандартні інтерфейси для збереження поліморфізму?
2. **Зв'язність:** Чи відв'язаний клієнт вашого Фасаду від внутрішніх класів складної підсистеми?
3. **Життєвий цикл:** Чи коректно ваш Замісник керує життєвим циклом реального об'єкта (створення та видалення)?
4. **Сумісність:** Чи точно Адаптер конвертує параметри без зміни оригінальної поведінки?

---

<a id="summary"></a>
## Резюме

Структурні патерни проектування використовують композицію об'єктів для створення надійних систем, які легко підтримувати. Використовуйте **Adapter** для обгортання несумісних інтерфейсів. Використовуйте **Decorator** для динамічного додавання нових можливостей об'єктам без успадкування. Використовуйте **Facade** для надання спрощеного входу до складної бібліотеки. Використовуйте **Proxy** для лінивого завантаження, безпеки або кешування.

---

<a id="self-test-quiz"></a>
## Квиз для самоперевірки

### Питання 1: У чому основна архітектурна перевага використання патерну Декоратор порівняно з успадкуванням?
- А) Код компілюється швидше і виконується за менший час.
- B) Він дозволяє додавати або змінювати поведінку об'єкта динамічно під час виконання, уникаючи вибухового зростання комбінацій класів під час компіляції.
- C) Він гарантує потокобезпечність в PHP.

<details>
<summary><b>Показати відповідь</b></summary>

**Правильна відповідь: B**
Успадкування є статичним. Якщо ви хочете додати кешування та логування до репозиторію, успадкування змусить вас створити окремі класи (наприклад, `CachedProductRepository`, `LoggedProductRepository`, `CachedAndLoggedProductRepository`). Декоратори дозволяють комбінувати їх на льоту: `new Caching(new Logging(new SqlRepository()))`.
</details>

### Питання 2: Яке відношення пов'язує клас Замісника (Proxy) та клас Реального Суб'єкта?
- A) Замісник повинен обов'язково успадковувати клас Реального Суб'єкта.
- B) Замісник і Реальний Суб'єкт повинні реалізовувати один і той же інтерфейс, щоб Замісник міг виступати в ролі прозорого замінника.
- C) Клас Замісника не може безпосередньо викликати методи класу Реального Суб'єкта.

<details>
<summary><b>Показати відповідь</b></summary>

**Правильна відповідь: B**
Щоб клієнти могли використовувати Замісник замість Реального Суб'єкта без зміни свого коду, обидва класи повинні реалізовувати один і той же інтерфейс. Це робить заміну прозорою для клієнта.
</details>
