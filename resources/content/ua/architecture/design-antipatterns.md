---
title: "Антипатерни проєктування ПЗ: Спагеті-код, Божественний об'єкт, Золотий молоток та Карго-культ | DevSense"
description: "Опануйте проєктування ПЗ, виявляючи та уникаючи поширених антипатернів у PHP. Дізнайтеся, як рефакторити спагеті-код, божественні об'єкти, золотий молоток, передчасну оптимізацію та карго-культ."
published: 2026-06-18
faq:
  - question: "Що таке антипатерн проєктування програмного забезпечення?"
    answer: "Антипатерн проєктування ПЗ — це поширена, повторювана реакція на проблему, яка на перший погляд здається корисною, але призводить до вкрай негативних наслідків, ускладнює підтримку коду та є контрпродуктивною."
  - question: "Як божественний об'єкт (God Object) порушує принципи SOLID?"
    answer: "Божественний об'єкт порушує принцип єдиної відповідальності (SRP), оскільки зосереджує занадто багато обов'язків, дій і даних в одному класі. Він також порушує принцип відкритості/закритості (OCP), оскільки зміна однієї функції вимагає редагування цього центрального монолітного класу."
  - question: "Коли патерн Репозиторій (Repository) вважається програмуванням карго-культу?"
    answer: "Він вважається програмуванням карго-культу, якщо ви створюєте загальний інтерфейс та клас репозиторію, які просто обгортають запити Eloquent (наприклад, find, create, delete) без додавання реальної бізнес-абстракції, переваг для тестування чи користі від послаблення зв'язності (decoupling)."
---

# Антипатерни проєктування ПЗ: Спагеті-код, Божественний об'єкт, Золотий молоток та Карго-культ

Написання чистого коду полягає як у знанні того, що робити, так і в знанні того, чого робити *не* слід. Антипатерни проєктування програмного забезпечення — це поширені, повторювані погані практики, які спочатку здаються хорошими рішеннями, але зрештою призводять до непідтримуваних, крихких та перевизначених (over-engineered) кодових баз.

У цьому посібнику ми проаналізуємо п'ять основних антипатернів проєктування у сучасній розробці на PHP, розглянемо конкретні приклади їх прояву та пройдемо шлях рефакторингу цих рішень у чисті, підтримувані структури.

**Пов'язані посібники:** [Породжувальні патерни проєктування GoF](creational-design-patterns) · [Структурні патерни проєктування GoF](structural-design-patterns) · [Поведінкові патерни проєктування GoF](behavioral-design-patterns)

## Зміст

* [Спагеті-код](#spaghetti-code)
* [Божественний об'єкт](#god-object)
* [Золотий молоток](#golden-hammer)
* [Передчасна оптимізація](#premature-optimization)
* [Карго-культ та програмування копіюванням-вставленням](#cargo-cult)
* [Типові помилки](#common-mistakes)
* [Контрольний список (Checklist)](#checklist)
* [Резюме](#summary)
* [Тест для самоперевірки](#self-test-quiz)

---

<a id="spaghetti-code"></a>
## Спагеті-код

**Спагеті-код (Spaghetti Code)** — це код із заплутаним, неструктурованим потоком керування, переповнений вкладеними умовними блоками, змішаними обов'язками та без чітких архітектурних меж. Його називають «спагеті», оскільки потік виконання заплутаний, немов миска спагеті, що робить його практично неможливим для відстеження.

### Поганий підхід: змішування бази даних, валідації та HTML

У цьому прикладі на PHP маршрутизація, робота з базою даних, валідація вхідних даних та рендеринг змішані в одному файлі.

```php
// index.php
<?php
$conn = new mysqli("localhost", "root", "", "app");
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["email"]) && filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
        $email = $_conn->real_escape_string($_POST["email"]);
        $res = $conn->query("SELECT * FROM users WHERE email = '$email'");
        if ($res->num_rows === 0) {
            $conn->query("INSERT INTO users (email) VALUES ('$email')");
            echo "<p>User registered!</p>";
        } else {
            echo "<p>Email already exists.</p>";
        }
    } else {
        echo "<p>Invalid email.</p>";
    }
}
?>
<form method="POST">
    <input type="text" name="email" />
    <button type="submit">Register</button>
</form>
```

### Правильний підхід: розділення обов'язків (Separation of Concerns)

Ми рефакторимо це, розділяючи взаємодію з базою даних (Репозиторій), бізнес-правила та правила валідації (Контролер) та візуальне представлення (Blade/Шаблон).

```php
// app/Repositories/UserRepository.php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $email): bool
    {
        $stmt = $this->pdo->prepare("INSERT INTO users (email) VALUES (:email)");
        return $stmt->execute(['email' => $email]);
    }
}
```

```php
// app/Http/Controllers/RegisterController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use InvalidArgumentException;

class RegisterController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function register(array $data): string
    {
        $email = $data['email'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format.");
        }

        if ($this->userRepository->findByEmail($email) !== null) {
            return "Email already exists.";
        }

        $this->userRepository->create($email);
        return "User registered!";
    }
}
```

---

<a id="god-object"></a>
## Божественний об'єкт

**Божественний об'єкт (God Object)** — це монолітний клас, який знає або робить занадто багато. Він зосереджує в собі весь інтелект додатка, порушуючи **принцип єдиної відповідальності (Single Responsibility Principle — SRP)**. Інші класи в системі стають просто пасивними тримачами даних (анемічними моделями), якими керує цей гігантський клас.

### Поганий підхід: монолітний OrderManager

Тут один клас відповідає за підключення до платіжного шлюзу, збереження в базі даних, надсилання електронних листів, розрахунок знижок та генерацію PDF.

```php
// app/Services/OrderManager.php
declare(strict_types=1);

namespace App\Services;

class OrderManager
{
    public function processOrder(array $orderData): void
    {
        // 1. Calculate discount
        $total = $orderData['price'];
        if ($orderData['coupon'] === 'SUMMER') {
            $total *= 0.9;
        }

        // 2. Save order to database
        $db = new \PDO("mysql:host=localhost;dbname=shop", "root", "");
        $stmt = $db->prepare("INSERT INTO orders (total, user_id) VALUES (?, ?)");
        $stmt->execute([$total, $orderData['user_id']]);

        // 3. Process payment via Stripe
        $stripe = new \Stripe\StripeClient('sk_test_key');
        $stripe->charges->create([
            'amount' => (int)($total * 100),
            'currency' => 'usd',
            'source' => $orderData['token'],
        ]);

        // 4. Generate PDF invoice
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->Write(10, "Invoice total: " . $total);
        $pdf->Output('F', "/invoices/order.pdf");

        // 5. Send email notification
        mail($orderData['email'], "Order Success", "Your order total is " . $total);
    }
}
```

### Правильний підхід: слабозв'язані сервіси

Ми робимо рефакторинг, делегуючи кожне доменне завдання спеціалізованому компоненту, залишаючи клас-менеджер як легковагий координатор.

```php
// app/Services/OrderService.php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Notification\NotificationInterface;
use App\Services\Invoice\InvoiceGeneratorInterface;

class OrderService
{
    private OrderRepository $repository;
    private DiscountCalculator $discountCalculator;
    private PaymentGatewayInterface $paymentGateway;
    private InvoiceGeneratorInterface $invoiceGenerator;
    private NotificationInterface $notifier;

    public function __construct(
        OrderRepository $repository,
        DiscountCalculator $discountCalculator,
        PaymentGatewayInterface $paymentGateway,
        InvoiceGeneratorInterface $invoiceGenerator,
        NotificationInterface $notifier
    ) {
        $this->repository = $repository;
        $this->discountCalculator = $discountCalculator;
        $this->paymentGateway = $paymentGateway;
        $this->invoiceGenerator = $invoiceGenerator;
        $this->notifier = $notifier;
    }

    public function checkout(array $orderData): void
    {
        $total = $this->discountCalculator->calculate($orderData['price'], $orderData['coupon']);
        
        $order = $this->repository->save($total, $orderData['user_id']);
        
        $this->paymentGateway->charge($total, $orderData['token']);
        
        $invoicePath = $this->invoiceGenerator->generate($order);
        
        $this->notifier->sendSuccessNotification($orderData['email'], $total, $invoicePath);
    }
}
```

---

<a id="golden-hammer"></a>
## Золотий молоток

**Золотий молоток (Golden Hammer)** — це припущення, що улюблена технологія або патерн проєктування є універсально застосовними: *«Якщо у вас є лише молоток, усе схоже на цвях».*

У PHP це часто трапляється, коли розробники намагаються використовувати складні структурні чи поведінкові патерни (наприклад, State або Visitor) для простих завдань, що призводить до непотрібного розмноження класів, або використовують спеціалізовані бази даних (наприклад, Elasticsearch чи Redis) для завдань, які можна легко вирішити безпосередньо в SQL.

### Поганий підхід: нав'язування патерну Стан (State) для простої перевірки віку

Розробник хоче перевірити, чи дозволено користувачеві купувати алкоголь. Замість простої умови він вибудовує цілу структуру патерну Стан (State) з кількома інтерфейсами та конкретними класами.

```php
// app/Validation/AgeValidator.php
declare(strict_types=1);

namespace App\Validation;

interface AgeStateInterface {
    public function canPurchaseAlcohol(): bool;
}

class UnderageState implements AgeStateInterface {
    public function canPurchaseAlcohol(): bool { return false; }
}

class AdultState implements AgeStateInterface {
    public function canPurchaseAlcohol(): bool { return true; }
}

class AgeValidator
{
    private AgeStateInterface $state;

    public function __construct(int $age)
    {
        $this->state = $age >= 18 ? new AdultState() : new UnderageState();
    }

    public function check(): bool
    {
        return $this->state->canPurchaseAlcohol();
    }
}
```

### Правильний підхід: будьте простішими (принцип KISS)

Патерни проєктування додають складності та когнітивного навантаження. Якщо бізнес-логіка проста, пишіть її просто.

```php
// app/Validation/AgeValidator.php
declare(strict_types=1);

namespace App\Validation;

class AgeValidator
{
    private const ALCOHOL_MINIMUM_AGE = 18;

    public static function canPurchaseAlcohol(int $age): bool
    {
        return $age >= self::ALCOHOL_MINIMUM_AGE;
    }
}
```

> [!NOTE]
> **Використовуйте патерни лише тоді, коли цього вимагає складність**: Патерни проєктування створені для обробки мінливих вимог та високої складності. Якщо ваша логіка станів містить лише одну просту умову, яка ніколи не зміниться, використання патерну є формою перевизначення (over-engineering).

---

<a id="premature-optimization"></a>
## Передчасна оптимізація

**Передчасна оптимізація (Premature Optimization)** — це оптимізація продуктивності коду до того, як ви отримаєте конкретні докази (за допомогою інструментів профілювання, як-от Xdebug або Blackfire), що ця ділянка дійсно є вузьким місцем.

Це призводить до нечитабельного, складного коду, написаного заради економії мікросекунд, тоді як ігноруються запити до бази даних або повільні зовнішні API-запити, які тривають сотні мілісекунд.

### Поганий підхід: заплутані мікрооптимізації

Розробник уникає зрозумілих вбудованих функцій PHP і використовує вкладені пошуки в рядках та побітові операції для аналізу рядків конфігурації, вважаючи, що це швидше.

```php
// app/Config/Parser.php
declare(strict_types=1);

namespace App\Config;

class Parser
{
    // Cryptic string manipulation to save CPU cycles
    public function parse(string $data): array
    {
        $pos = strpos($data, ':');
        if ($pos === false) return [];
        
        $key = substr($data, 0, $pos);
        $val = substr($data, $pos + 1);
        
        // Bitwise flag check representing boolean configuration
        $isFlagged = (int)$val & 1; 
        
        return [$key => (bool)$isFlagged];
    }
}
```

### Правильний підхід: пріоритет читабельності коду

Пишіть код, який легко читати, тестувати та підтримувати. Якщо виникають проблеми з продуктивністю, спочатку виконайте профілювання, а потім оптимізуйте саме там, де виявлено вузькі місця.

```php
// app/Config/Parser.php
declare(strict_types=1);

namespace App\Config;

class Parser
{
    public function parse(string $jsonString): array
    {
        $decoded = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        
        return $decoded;
    }
}
```

---

<a id="cargo-cult"></a>
## Карго-культ та програмування копіюванням-вставленням

**Програмування карго-культу (Cargo Cult Programming)** — це практика копіювання кодових патернів, структур або методологій без розуміння того, *кому* вони використовуються або яку проблему вони вирішують.

Класичний приклад у PHP — обгортання моделей Laravel Eloquent у порожній шар репозиторіїв (Repository Layer), оскільки «хороша архітектура вимагає репозиторіїв», хоча Eloquent вже реалізує патерни Active Record та Query Builder.

### Поганий підхід: порожня обгортка абстракції репозиторію

Цей репозиторій-обгортка просто копіює методи Eloquent, не додаючи жодної абстракції чи користі, але подвоюючи кількість класів, які потрібно підтримувати.

```php
// app/Repositories/PostRepositoryInterface.php
declare(strict_types=1);

namespace App\Repositories;

interface PostRepositoryInterface {
    public function find(int $id);
    public function create(array $data);
}

// app/Repositories/EloquentPostRepository.php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Post;

class EloquentPostRepository implements PostRepositoryInterface
{
    public function find(int $id)
    {
        return Post::find($id);
    }

    public function create(array $data)
    {
        return Post::create($data);
    }
}
```

### Правильний підхід: використання Active Record напряму або створення реальних абстракцій

Якщо ваш репозиторій не ізолює клієнта від механізму збереження даних (наприклад, все одно повертає сирі запити чи моделі Eloquent), відмовтеся від нього та використовуйте Eloquent безпосередньо.

```php
// app/Http/Controllers/PostController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;

class PostController
{
    public function show(int $id): JsonResponse
    {
        // Use active record pattern directly
        $post = Post::findOrFail($id);
        return response()->json($post);
    }
}
```

> [!TIP]
> **Коли використовувати репозиторії**: Використовуйте їх, коли ви впроваджуєте складну доменну логіку, яка повинна залишатися незалежною від ORM (наприклад, у доменно-орієнтованому проєктуванні — DDD), або коли ви створюєте власні запити багаторазового використання, які хочете протестувати ізольовано.

---

<a id="common-mistakes"></a>
## Типові помилки

1. **Нав'язування патернів усюди**: впровадження складних патернів проєктування у простих CRUD-проєктах.
2. **Створення божественних класів (God Classes)**: роздування допоміжних класів чи менеджерів до нескінченності замість розділення їх на маленькі, згуртовані сервіси.
3. **Оптимізація без профілювання**: переписування чистих PHP-циклів у складні алгоритми лише тому, що ви «підозрюєте», що вони повільні, тоді як база даних виконує запити без індексів.
4. **Сліпе копіювання**: перенесення складних архітектурних рішень (таких як CQRS, Event Sourcing чи шари репозиторіїв) у прості MVC-проєкти лише тому, що вони популярні в Інтернеті.

---

<a id="checklist"></a>
## Контрольний список (Checklist)

1. **Перевірка SRP:** Чи має ваш клас більше ніж одну причину для зміни? Якщо так, розділіть його.
2. **Принцип KISS:** Чи може проста умова `if` або допоміжна функція замінити складну ієрархію ООП?
3. **Профілювання продуктивності:** Чи виконали ви профілювання вашого додатка за допомогою Xdebug або Blackfire перед застосуванням мікрооптимізацій?
4. **Корисність абстракції:** Чи дійсно ваш репозиторій або шар абстракції приховує деталі реалізації, чи це просто зайва обгортка?

---

<a id="summary"></a>
## Резюме

Антипатерни проєктування — це типові пастки, які призводять до жорсткого та крихкого програмного забезпечення. **Спагеті-код** виникає через відсутність розділення обов'язків. **Божественні об'єкти** зосереджують занадто багато відповідальності. **Золотий молоток** нав'язує невідповідні рішення для будь-яких проблем. **Передчасна оптимізація** ставить мікропродуктивність вище за читабельність коду. **Карго-культ** дублює структури без розуміння їхньої архітектурної цінності. Прагніть до того, щоб код залишався простим та зрозумілим, і додавайте патерни проєктування лише тоді, коли цього вимагає реальна складність.

---

<a id="self-test-quiz"></a>
## Тест для самоперевірки

### Запитання 1: Який принцип SOLID безпосередньо порушується, коли клас діє як «Божественний об'єкт» (God Object)?
- A) Принцип відкритості/закритості (Open/Closed Principle — OCP)
- B) Принцип підстановки Лісков (Liskov Substitution Principle — LSP)
- C) Принцип єдиної відповідальності (Single Responsibility Principle — SRP)

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: C**
Божественний об'єкт характеризується наявністю багатьох обов'язків (запити до бази даних, інтеграція платежів, розсилка, шаблони, форматування), що порушує принцип єдиної відповідальності, який стверджує, що клас повинен мати лише одну причину для зміни.
</details>

### Запитання 2: Чому передчасна оптимізація вважається антипатерном?
- A) Тому що двигуни PHP не підтримують оптимізований код.
- B) Тому що вона збільшує складність коду та знижує його читабельність ще до того, як вузькі місця продуктивності були доведені та проаналізовані.
- C) Тому що оптимізація компілятора в PHP 8.x автоматично вимикається, якщо ви виконуєте оптимізацію вручну.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: B**
Передчасна оптимізація призводить до складних для підтримки блоків коду в тих частинах програми, які рідко є вузькими місцями. Справжніми вузькими місцями майже завжди є запити до бази даних, операції введення-виведення файлової системи або мережеві виклики, а не конкатенація рядків.
</details>

### Запитання 3: Яка основна характеристика програмування карго-культу?
- A) Сліпе копіювання структур коду, патернів проєктування або шарів без розуміння їхньої архітектурної мети чи реальної користі в поточному проєкті.
- B) Міграція застарілого коду в хмарні сервіси, такі як AWS або Docker.
- C) Написання тестів після розгортання робочого коду.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: A**
Програмування карго-культу виникає тоді, коли розробники копіюють патерни чи архітектурні шари (такі як порожні репозиторії, інтерфейси сервісів або абстрактні фабрики) просто тому, що «це стандартна практика» або «так було написано в туторіалі», без оцінки того, чи вирішує це реальну проблему в їхньому контексті.
</details>
