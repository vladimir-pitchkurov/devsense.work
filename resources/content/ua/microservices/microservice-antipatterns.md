---
title: "Антипатерни мікросервісів: розподілений моноліт, спільна база даних та «балакучі» сервіси | DevSense"
description: "Уникайте критичних помилок у розподілених архітектурах. Дізнайтеся, як виявити та рефакторити антипатерни мікросервісів: Shared Database, Distributed Monolith та Chatty Services."
published: 2026-06-18
faq:
  - question: "Чому спільна база даних (Shared Database) вважається критичним антипатерном мікросервісів?"
    answer: "Спільна база даних пов'язує мікросервіси на рівні зберігання даних. Будь-яка зміна схеми, здійснена однією командою, може миттєво зламати інші сервіси, а прямі запити до БД оминають правила бізнес-валідації сервісу, що призводить до пошкодження даних."
  - question: "Що таке розподілений моноліт (Distributed Monolith)?"
    answer: "Розподілений моноліт — це система, яку було розділено на окремі мережеві сервіси, але вона все ще вимагає синхронного розгортання, використовує спільні з'єднання з базою даних або здійснює синхронні блокувальні виклики RPC для майже кожного запиту, поєднуючи складність мікросервісів із жорсткістю моноліту."
  - question: "Як вирішити проблему «балакучих» сервісів (Chatty Services)?"
    answer: "Проблема «балакучих» сервісів вирішується шляхом впровадження пакетних (bulk) API-ендпоінтів (наприклад, для отримання списку ID за один виклик), використання локального кешування (з TTL) для даних, які рідко змінюються, або застосування денормалізації на основі подій для зберігання копій необхідних даних локально."
---

# Антипатерни мікросервісів: розподілений моноліт, спільна база даних та «балакучі» сервіси

Проєктування мікросервісної архітектури є відомо складною задачею. Багато команд починають розділяти свої системи лише для того, щоб отримати систему, яка є повільнішою, складнішою у розгортанні та заплутанішою, ніж їхній початковий моноліт. Ці невдачі спричинені загальними антипатернами мікросервісів.

У цьому посібнику ми проаналізуємо п'ять антипатернів мікросервісів, зрозуміємо причини їх виникнення та реалізуємо практичні приклади на PHP 8.x, щоб рефакторити їх у чисті, слабозв'язані архітектури.

**Пов'язані посібники:** [Перехід від моноліту до мікросервісної архітектури](monolith-to-microservices-architecture) · [Архітектурні патерни мікросервісів](microservice-patterns) · [Порівняння черг повідомлень](message-queues-compared)

## Зміст

* [Спільна база даних (Shared Database)](#shared-database)
* [Розподілений моноліт (Distributed Monolith)](#distributed-monolith)
* [«Балакучі» сервіси / Chatty Services (N+1 мережевих викликів)](#chatty-services)
* [Мега-шлюз (Mega-Gateway)](#mega-gateway)
* [Наносервіси / Nano-Services (Надмірна фрагментація)](#nano-services)
* [Типові помилки](#common-mistakes)
* [Контрольний список (Checklist)](#checklist)
* [Резюме](#summary)
* [Тест для самоперевірки](#self-test-quiz)

---

<a id="shared-database"></a>
## Спільна база даних (Shared Database)

Антипатерн **«Спільна база даних» (Shared Database)** виникає, коли кілька мікросервісів читають або записують дані безпосередньо в одні й ті самі таблиці бази даних. Хоча це спрощує операції об'єднання (joins), такий підхід повністю руйнує автономію команд та ізоляцію сервісів. 

### Поганий підхід: об'єднання через різні бази даних в Eloquent

У цьому прикладі на PHP сервіс замовлень (Order service) робить запит безпосередньо до таблиці бази даних сервісу користувачів (User service), прив'язуючи код замовлень до структури таблиці користувачів.

```php
// app/Services/OrderReport.php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrderReport
{
    public function getDetailedOrders(): array
    {
        // Direct JOIN across database tables violating domain boundaries
        return DB::table('orders')
            ->join('users_db.users', 'orders.user_id', '=', 'users.id')
            ->select('orders.id', 'orders.total', 'users.email', 'users.name')
            ->get()
            ->toArray();
    }
}
```

### Правильний підхід: розділення через API або денормалізація на основі подій

Натомість сервіс замовлень має викликати API сервісу користувачів або денормалізувати метадані користувачів локально у власній базі даних за допомогою синхронізації подій.

```php
// app/Clients/UserServiceClient.php
declare(strict_types=1);

namespace App\Clients;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class UserServiceClient
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function getUsersByIds(array $userIds): array
    {
        $response = Http::post("{$this->baseUrl}/users/bulk", [
            'ids' => $userIds
        ]);BaseUrl

        if ($response->failed()) {
            throw new RuntimeException("Failed to fetch user profiles.");
        }

        return $response->json();
    }
}
```

```php
// app/Services/OrderReport.php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Clients\UserServiceClient;

class OrderReport
{
    private OrderRepository $orderRepository;
    private UserServiceClient $userClient;

    public function __construct(OrderRepository $orderRepository, UserServiceClient $userClient)
    {
        $this->orderRepository = $orderRepository;
        $this->userClient = $userClient;
    }

    public function getDetailedOrders(): array
    {
        $orders = $this->orderRepository->getRecentOrders();
        $userIds = array_unique(array_column($orders, 'user_id'));

        // Fetch user profiles in one HTTP bulk call rather than direct database joins
        $users = $this->userClient->getUsersByIds($userIds);
        $userMap = array_column($users, null, 'id');

        foreach ($orders as &$order) {
            $order['user'] = $userMap[$order['user_id']] ?? null;
        }

        return $orders;
    }
}
```

---

<a id="distributed-monolith"></a>
## Розподілений моноліт (Distributed Monolith)

**Розподілений моноліт (Distributed Monolith)** — це система, яка була розділена на окремі сервіси, але все ще поводиться як моноліт. Функціонал залишається жорстко зв'язаним через мережеві межі, що означає, що зміна в сервісі А вимагає узгодженого розгортання сервісу Б. 

Це часто викликано синхронними, блокувальними викликами HTTP або gRPC, які виконуються для кожного користувацького запиту, через що доступність системи дорівнює добутку доступності кожного окремого сервісу.

> [!WARNING]
> **Попередження щодо доступності**: Якщо у вас є 5 сервісів, які синхронно викликають один другого, і кожен має доступність 99%, загальна доступність вашої системи знижується до:
> $$0.99 \times 0.99 \times 0.99 \times 0.99 \times 0.99 \approx 95\%$$
> Це означає приблизно 18 днів простою на рік! Виконайте рефакторинг на асинхронний обмін повідомленнями, щоб розділити доступність.

---

<a id="chatty-services"></a>
## «Балакучі» сервіси / Chatty Services (N+1 мережевих викликів)

Антипатерн **«Балакучі сервіси» (Chatty Services)** є розподіленим еквівалентом проблеми N+1 запитів до бази даних. Це відбувається, коли сервіси занадто часто спілкуються між собою для виконання однієї операції, що призводить до високих мережевих витрат і повільного завантаження сторінок.

### Поганий підхід: виклики API у циклі (N+1 мережевих запитів)

Тут ми робимо HTTP-запит для кожного окремого елемента в циклі. Якщо є 50 замовлень, ми робимо 50 окремих викликів по мережі.

```php
// app/Services/OrderReport.php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\Http;

class OrderReport
{
    private OrderRepository $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function getDetailedOrders(): array
    {
        $orders = $this->orderRepository->getRecentOrders();

        // High frequency of blocking network requests (N+1 antipattern)
        foreach ($orders as &$order) {
            $response = Http::get("http://user-service/users/" . $order['user_id']);
            $order['user'] = $response->json();
        }

        return $orders;
    }
}
```

### Правильний підхід: пакетні API та кешування

Виконайте рефакторинг, запитуючи всі необхідні ID в одному пакетному (bulk) запиті до API та кешуючи результати, щоб уникнути повторних мережевих запитів.

```php
// app/Services/OrderReport.php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Clients\UserServiceClient;
use Illuminate\Support\Facades\Cache;

class OrderReport
{
    private OrderRepository $orderRepository;
    private UserServiceClient $userClient;

    public function __construct(OrderRepository $orderRepository, UserServiceClient $userClient)
    {
        $this->orderRepository = $orderRepository;
        $this->userClient = $userClient;
    }

    public function getDetailedOrders(): array
    {
        $orders = $this->orderRepository->getRecentOrders();
        
        $userIds = array_unique(array_column($orders, 'user_id'));
        $missingUserIds = [];
        $userMap = [];

        // Check local cache first to save network calls
        foreach ($userIds as $id) {
            if (Cache::has("user:{$id}")) {
                $userMap[$id] = Cache::get("user:{$id}");
            } else {
                $missingUserIds[] = $id;
            }
        }

        // Only call API for cache misses in a single bulk call
        if (!empty($missingUserIds)) {
            $fetchedUsers = $this->userClient->getUsersByIds($missingUserIds);
            foreach ($fetchedUsers as $user) {
                Cache::put("user:{$user['id']}", $user, 300); // Cache for 5 minutes
                $userMap[$user['id']] = $user;
            }
        }

        foreach ($orders as &$order) {
            $order['user'] = $userMap[$order['user_id']] ?? null;
        }

        return $orders;
    }
}
```

---

<a id="mega-gateway"></a>
## Мега-шлюз (Mega-Gateway)

API-шлюз (API Gateway) має діяти як легковагий зворотний проксі (reverse proxy), виконуючи маршрутизацію, термінацію TLS та обмеження частоти запитів (rate limiting). 

**Мега-шлюз (Mega-Gateway)** — це API-шлюз, який розрісся через додавання доменної бізнес-логіки, валідації даних або запитів до БД. Це перетворює шлюз на монолітну єдину точку збою (single point of failure), що зв'язує графіки релізів усіх внутрішніх сервісів.

### Поганий підхід: обробка бізнес-логіки на API-шлюзі

Тут клас Gateway робить запити до бази даних та генерує власні облікові дані JWT, обходячи сервіс автентифікації.

```php
// app/Http/Gateway/ApiGatewayController.php
declare(strict_types=1);

namespace App\Http\Gateway;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Firebase\JWT\JWT;

class ApiGatewayController
{
    // The Gateway shouldn't manage business logic or SQL queries directly
    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = DB::table('users')->where('email', $request->input('email'))->first();
        
        if (!$user || !password_verify($request->input('password'), $user->password)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = JWT::encode(['sub' => $user->id], 'secret-key', 'HS256');
        return response()->json(['token' => $token]);
    }
}
```

### Правильний підхід: чиста проксі-маршрутизація

Шлюз повинен лише проксіювати запит до відповідного внутрішнього мікросервісу.

```php
// app/Http/Gateway/ApiGatewayController.php
declare(strict_types=1);

namespace App\Http\Gateway;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ApiGatewayController
{
    // Lightweight reverse proxy routing
    public function routeToAuthService(Request $request): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders($request->headers->all())
            ->post("http://auth-service/login", $request->all());
    }
}
```

---

<a id="nano-services"></a>
## Наносервіси / Nano-Services (Надмірна фрагментація)

**Наносервіс (Nano-service)** — це занадто малий сервіс. Надмірна фрагментація виникає, коли розробники виділяють окремий сервіс для кожної поодинокої операції (наприклад, `CreateOrderService`, `DeleteOrderService`, `UpdateOrderService` як окремі артефакти розгортання).

Це призводить до:
- високої затримки мережі (сервіси безперервно викликають інші сервіси);
- колосального навантаження на DevOps (керування пайплайнами, DNS та базами даних для десятків крихітних сервісів);
- надмірного дублювання коду.

> [!TIP]
> **Правильний розмір сервісу**: Узгоджуйте межі своїх сервісів з обмеженими контекстами (**Bounded Contexts**) з доменно-орієнтованого проєктування (DDD), а не з розміром коду. Один мікросервіс повинен інкапсулювати цілісний бізнес-домен, наприклад `Ordering` (Замовлення), `Billing` (Виставлення рахунків) або `Inventory` (Склад).

---

<a id="common-mistakes"></a>
## Типові помилки

1. **Обхід ізоляції спільної БД:** створення мікросервісів зі збереженням єдиної бази даних PostgreSQL, де сервіси читають таблиці один одного напряму.
2. **Синхронні каскади:** створення ланцюжка синхронних HTTP-викликів між кількома сервісами, через що весь запит завершується збоєм, якщо хоча б один сервіс у ланцюжку не працює.
3. **Роздування шлюзу (Gateway Bloat):** додавання драйверів баз даних та бізнес-перевірок на рівні API-шлюзу замість делегування їх внутрішнім сервісам.
4. **Мережеві цикли N+1:** запити до віддалених API у циклах замість написання пакетних ендпоінтів для запитів.

---

<a id="checklist"></a>
## Контрольний список (Checklist)

1. **Ізоляція баз даних:** Чи може розробник змінити схему таблиці User без порушення компіляції або виконання запитів сервісу Order?
2. **Підтримка пакетних ендпоінтів:** Чи надає ваш сервіс методи API для отримання списків ресурсів за масивами ID, чи він вимагає поодиноких викликів у циклі?
3. **Простота шлюзу:** Чи містить ваш API-шлюз SQL-запити або інтеграції із зовнішніми API? Якщо так, перенесіть їх на рівень внутрішніх сервісів.
4. **Межі доменів:** Чи є ваші мікросервіси меншими за один обмежений контекст (Bounded Context)? Якщо вони використовують спільні таблиці бази даних, розгляньте можливість їх об'єднання.

---

<a id="summary"></a>
## Резюме

Мікросервіси створюються для масштабування команд та систем, але неправильно визначені межі призводять до невдач. Уникайте **спільних баз даних (Shared Databases)** шляхом ізоляції сховищ. Запобігайте виникненню **розподілених монолітів (Distributed Monoliths)**, віддаючи перевагу асинхронним подіям перед блокувальними викликами. Усувайте **«балакучі» сервіси (Chatty Services)** за допомогою пакетних API та локального кешування. Зберігайте API-шлюзи легковагими та уникайте **мега-шлюзів (Mega-Gateways)**. Визначайте розмір сервісів відповідно до обмежених контекстів (Bounded Contexts), щоб уникнути надмірної фрагментації на **наносервіси (Nano-services)**.

---

<a id="self-test-quiz"></a>
## Тест для самоперевірки

### Запитання 1: Який основний операційний симптом розподіленого моноліту (Distributed Monolith)?
- A) Бази даних реплікуються у кілька хмарних середовищ.
- B) Ви не можете розгорнути зміни в одному мікросервісі без одночасного розгортання оновлень для інших сервісів, щоб запобігти збоям під час виконання.
- C) Виконання PHP завершується збоєм через вичерпання з'єднань із Redis.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: B**
Якщо сервіси жорстко зв'язані через синхронні залежності або спільні схеми бази даних, їх не можна розгортати незалежно. Це зводить нанівець основну перевагу мікросервісів (незалежність розгортання) та створює розподілений моноліт.
</details>

### Запитання 2: Як антипатерн «балакучих» сервісів (Chatty Services) впливає на продуктивність додатка?
- A) Він вичерпує пул з'єднань із базою даних за мілісекунди.
- B) Він створює високу затримку мережі та навантаження на CPU шляхом виконання численних дрібних синхронних мережевих запитів замість одного пакетного запиту.
- C) Він викликає попередження компілятора в PHP 8.x.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: B**
Кожен мережевий виклик передбачає накладні витрати на встановлення TCP-з'єднання, маршрутизацію, серіалізацію та десеріалізацію. Виконання численних дрібних запитів у циклі (N+1 мережевих запитів) суттєво уповільнює час відповіді порівняно з одним пакетним запитом.
</details>

### Запитання 3: Який принцип проєктування має визначати розмір мікросервісів, щоб уникнути наносервісів (Nano-services)?
- A) Зробити кожен клас PHP окремим мікросервісом.
- B) Обмежені контексти (Bounded Contexts) з доменно-орієнтованого проєктування (DDD).
- C) Забезпечення того, щоб кожен файл мікросервісу містив менше 100 рядків коду.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: B**
Обмежені контексти (Bounded Contexts) групують пов'язані бізнес-моделі та логіку, які мають спільну модель домену. Визначення розміру мікросервісів навколо обмежених контекстів забезпечує їхню згуртованість та мінімізує потребу в «балакучому» спілкуванні між сервісами.
</details>
