---
title: "Структурные паттерны GoF: Adapter, Decorator, Facade и Proxy | DevSense"
description: "Подробное руководство по структурным паттернам проектирования GoF на PHP: Adapter, Bridge, Composite, Decorator, Facade, Flyweight и Proxy с чистыми примерами кода."
published: 2026-06-18
faq:
  - question: "В чем ключевая разница между паттернами Adapter (Адаптер) и Decorator (Декоратор)?"
    answer: "Адаптер изменяет интерфейс объекта, чтобы сделать его совместимым с другим интерфейсом, позволяя несовместимым классам работать вместе. Декоратор сохраняет исходный интерфейс оборачиваемого объекта, динамически расширяя его поведение."
  - question: "Чем паттерн Facade (Фасад) отличается от паттерна Mediator (Посредник)?"
    answer: "Фасад предоставляет упрощенный односторонний интерфейс к сложной подсистеме, не ограничивая при этом прямой доступ к ее классам. Посредник централизует и координирует сложное двустороннее общение между множеством объектов-коллег, скрывая их прямые связи друг с другом."
  - question: "Когда следует использовать Proxy (Заместитель) вместо Decorator (Декоратора)?"
    answer: "Используйте Заместитель, когда вам нужно контролировать доступ к объекту (например, ленивая инициализация, кэширование, права доступа), при этом заместитель сам управляет жизненным циклом реального объекта. Декоратор используется для динамического добавления обязанностей объекту во время выполнения без управления его жизненным циклом."
---

# Структурные паттерны GoF: Adapter, Decorator, Facade и Proxy

Структурные паттерны проектирования объясняют, как собирать объекты и классы в более крупные структуры, сохраняя при этом эти структуры гибкими и эффективными. Они помогают выстраивать сложные иерархии классов, используя композицию вместо жесткого наследования.

**Связанные материалы:** [Переход от монолита к микросервисной архитектуре](monolith-to-microservices-architecture) · [Атаки на веб-приложения и защита от них](web-attacks-and-prevention)

## Содержание

* [Введение в структурные паттерны](#intro)
* [Паттерн Adapter (Адаптер)](#adapter)
* [Паттерн Decorator (Декоратор)](#decorator)
* [Паттерн Facade (Фасад)](#facade)
* [Паттерн Proxy (Заместитель)](#proxy)
* [Другие структурные паттерны (Bridge, Composite, Flyweight)](#others)
* [Типичные ошибки](#common-mistakes)
* [Чеклист для самопроверки](#checklist)
* [Резюме](#summary)
* [Квиз для самопроверки](#self-test-quiz)

---

<a id="intro"></a>
## Введение в структурные паттерны

Наследование — это мощный инструмент, но оно является статическим и определяется на этапе компиляции. Структурные паттерны используют **композицию объектов**, позволяя объединять их поведение динамически во время выполнения. Это обеспечивает большую гибкость и избавляет от создания слишком глубоких и жестких иерархий классов.

---

<a id="adapter"></a>
## Паттерн Adapter (Адаптер)

Паттерн **Adapter** позволяет объектам с несовместимыми интерфейсами работать вместе. Он выступает в роли обертки (переводчика), которая преобразует вызовы от клиентского кода в формат, понятный стороннему или устаревшему (legacy) коду.

```php
// app/Logging/LoggerInterface.php
declare(strict_types=1);

namespace App\Logging;

interface LoggerInterface
{
    public function logMessage(string $message): void;
}

// Унаследованный сторонний сервис, который нельзя изменить напрямую
class LegacyLoggerService
{
    public function sendRawLog(string $rawMessage, int $level): void
    {
        // Логирование в устаревшей системе...
    }
}

// Класс-Адаптер, связывающий два интерфейса
class LoggerAdapter implements LoggerInterface
{
    private LegacyLoggerService $legacyLogger;

    public function __construct(LegacyLoggerService $legacyLogger)
    {
        $this->legacyLogger = $legacyLogger;
    }

    public function logMessage(string $message): void
    {
        // Перенаправление вызова в legacy-сервис с адаптацией параметров
        $this->legacyLogger->sendRawLog($message, 1);
    }
}
```

---

<a id="decorator"></a>
## Паттерн Decorator (Декоратор)

Паттерн **Decorator** позволяет динамически добавлять объектам новые обязанности, оборачивая их в специальные объекты-обертки.

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
        // Получение продукта из SQL базы данных...
        return ["id" => $id, "name" => "Книга по PHP"];
    }
}

// Декоратор, реализующий тот же интерфейс
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
## Паттерн Facade (Фасад)

Паттерн **Facade** предоставляет простой интерфейс к сложной системе классов, библиотеке или фреймворку.

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
## Паттерн Proxy (Заместитель)

Паттерн **Proxy** предоставляет объект-заменитель, контролирующий доступ к оригинальному объекту. Заместитель может брать на себя ленивую инициализацию, контроль доступа, кэширование или логирование.

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
        // Имитация тяжелого запроса к базе данных
        usleep(50000);
    }

    public function render(): string
    {
        return "<h1>Результат тяжелого отчета PDF</h1>";
    }
}

// Заместитель с кэшированием и отложенной инициализацией
class HeavyReportProxy implements HeavyReportInterface
{
    private ?RealHeavyReport $realReport = null;
    private ?string $cachedHtml = null;

    public function render(): string
    {
        if ($this->cachedHtml === null) {
            if ($this->realReport === null) {
                // Создается только при первом запросе
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
## Другие структурные паттерны

- **Bridge (Мост)**: Разделяет абстракцию и реализацию на две отдельные иерархии классов так, чтобы их можно было изменять независимо друг от друга.
- **Composite (Компоновщик)**: Объединяет объекты в древовидные структуры для представления иерархий «часть-целое» и позволяет клиентам единообразно трактовать как одиночные, так и составные объекты.
- **Flyweight (Приспособленец)**: Позволяет вместить большее количество объектов в отведенную память за счет совместного использования общих частей состояния вместо хранения всех данных в каждом объекте.

---

<a id="common-mistakes"></a>
## Типичные ошибки

1. **Создание «Божественного» Фасада**: Размещение реальной бизнес-логики внутри класса Фасада вместо делегирования ее подсистемам. Фасад должен только координировать шаги.
2. **Путаница между Декоратором и Адаптером**: Попытка адаптировать несовместимый интерфейс с помощью Декоратора или динамическое расширение поведения с помощью Адаптера.
3. **Слишком длинные цепочки декораторов**: Нанизывание слишком большого числа декораторов на один объект, что усложняет отладку и чтение стека вызовов.

---

<a id="checklist"></a>
## Чеклист для самопроверки

1. **Интерфейсы:** Реализуют ли ваши классы Адаптера и Декоратора стандартные интерфейсы для сохранения полиморфизма?
2. **Связанность:** Отвязан ли клиент вашего Фасада от внутренних классов сложной подсистемы?
3. **Жизненный цикл:** Корректно ли ваш Заместитель управляет жизненным циклом реального объекта (создание и удаление)?
4. **Совместимость:** Точно ли Адаптер конвертирует параметры без изменения оригинального поведения?

---

<a id="summary"></a>
## Резюме

Структурные паттерны проектирования используют композицию объектов для создания надежных и легко поддерживаемых систем. Используйте **Adapter** для оборачивания несовместимых интерфейсов. Используйте **Decorator** для динамического добавления новых возможностей объектам без наследования. Используйте **Facade** для предоставления простого входа в сложную библиотеку. Используйте **Proxy** для ленивой загрузки, безопасности или кэширования.

---

<a id="self-test-quiz"></a>
## Квиз для самопроверки

### Вопрос 1: В чем основное архитектурное преимущество использования паттерна Декоратор по сравнению с наследованием?
- А) Код компилируется быстрее и выполняется за меньшее время.
- B) Он позволяет добавлять или изменять поведение объекта динамически во время выполнения, избегая взрывного роста комбинаций классов при компиляции.
- C) Он гарантирует потокобезопасность в PHP.

<details>
<summary><b>Показать ответ</b></summary>

**Правильный ответ: B**
Наследование статично. Если вы хотите добавить к репозиторию кэширование и логирование, наследование заставит вас создать отдельные классы (например, `CachedProductRepository`, `LoggedProductRepository`, `CachedAndLoggedProductRepository`). Декораторы позволяют комбинировать их на лету: `new Caching(new Logging(new SqlRepository()))`.
</details>

### Вопрос 2: Какое отношение связывает класс Заместителя (Proxy) и класс Реального Субъекта?
- A) Заместитель должен обязательно наследоваться от класса Реального Субъекта.
- B) Заместитель и Реальный Субъект должны реализовывать один и тот же интерфейс, чтобы Заместитель мог выступать в роли прозрачного заменителя.
- C) Класс Заместителя не может напрямую вызывать методы класса Реального Субъекта.

<details>
<summary><b>Показать ответ</b></summary>

**Правильный ответ: B**
Чтобы клиенты могли использовать Заместитель вместо Реального Субъекта без изменения своего кода, оба класса должны реализовывать один и тот же интерфейс. Это делает замену прозрачной для клиента.
</details>
