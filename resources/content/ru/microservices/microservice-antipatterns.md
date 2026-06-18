---
title: "Антипаттерны микросервисов: распределенный монолит, общая база данных и «болтливые» сервисы | DevSense"
description: "Избегайте критических ошибок в распределенных архитектурах. Узнайте, как выявлять и рефакторить антипаттерны микросервисов: общую базу данных, распределенный монолит и «болтливые» сервисы."
published: 2026-06-18
faq:
  - question: "Почему общая база данных (Shared Database) считается критическим антипаттерном микросервисов?"
    answer: "Общая база данных связывает микросервисы на уровне хранения данных. Любое изменение схемы, сделанное одной командой, может немедленно нарушить работу других сервисов, а прямые запросы к базе данных обходят правила бизнес-валидации сервиса, что может привести к повреждению данных."
  - question: "Что такое распределенный монолит (Distributed Monolith)?"
    answer: "Распределенный монолит — это система, разделенная на отдельные сетевые сервисы, которая при этом требует синхронного развертывания, использует общие подключения к БД или выполняет синхронные блокирующие RPC-вызовы почти при каждом запросе, объединяя в себе сложность микросервисов и жесткость монолита."
  - question: "Как решить проблему антипаттерна «болтливых» сервисов (Chatty Services)?"
    answer: "Решить проблему «болтливых» сервисов можно путем реализации групповых (bulk) эндпоинтов API (например, для получения списка ID за один вызов), локального кэширования (с TTL) редко меняющихся данных или внедрения событийной денормализации для локального хранения копий-проекций необходимых данных."
---

# Антипаттерны микросервисов: распределенный монолит, общая база данных и «болтливые» сервисы

Проектирование микросервисной архитектуры — общеизвестно сложная задача. Многие команды начинают разделять свои системы только для того, чтобы в итоге получить систему, которая работает медленнее, сложнее развертывается и более запутана, чем их первоначальный монолит. Причиной этих неудач являются распространенные антипаттерны микросервисов.

В этом руководстве мы разберем пять антипаттернов микросервисов, разберемся в причинах их возникновения и на практических примерах PHP 8.x рассмотрим, как отрефакторить их в чистые, слабосвязанные архитектуры.

**Сопутствующие руководства:** [Архитектура перехода от монолита к микросервисам](monolith-to-microservices-architecture) · [Архитектурные паттерны микросервисов](microservice-patterns) · [Сравнение очередей сообщений](message-queues-compared)

## Содержание

* [Общая база данных](#shared-database)
* [Распределенный монолит](#distributed-monolith)
* [«Болтливые» сервисы (N+1 сетевых вызовов)](#chatty-services)
* [Мега-шлюз (Mega-Gateway)](#mega-gateway)
* [Наносервисы (избыточная фрагментация)](#nano-services)
* [Частые ошибки](#common-mistakes)
* [Чек-лист](#checklist)
* [Заключение](#summary)
* [Тест для самопроверки](#self-test-quiz)

---

<a id="shared-database"></a>
## Общая база данных

Антипаттерн **Общая база данных (Shared Database)** возникает, когда несколько микросервисов напрямую читают или записывают данные в одни и те же таблицы БД. Хотя это упрощает объединение таблиц (joins), это полностью разрушает автономность команд и изоляцию сервисов.

### Плохой подход: Eloquent-объединения между базами данных

В этом PHP-примере сервис заказов (Order) напрямую опрашивает таблицу базы данных сервиса пользователей (User), связывая код заказов со структурой таблицы пользователей.

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

### Хороший подход: разделение через API или событийная денормализация

Вместо этого сервис заказов должен обращаться к API сервиса пользователей или денормализовать метаданные пользователей локально в собственной базе данных с помощью синхронизации на основе событий.

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
        ]);

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
## Распределенный монолит

**Распределенный монолит (Distributed Monolith)** — это система, которая была разделена на сервисы, но по-прежнему ведет себя как монолит. Функционал жестко связан через сетевые границы, из-за чего изменение в Сервисе A требует скоординированного развертывания Сервиса B.

Часто причиной этого являются синхронные блокирующие вызовы HTTP или gRPC, выполняемые при каждом пользовательском запросе, из-за чего доступность системы становится равной произведению доступности каждого отдельного сервиса.

> [!WARNING]
> **Предупреждение о расчете доступности**: Если у вас есть 5 сервисов, которые вызывают друг друга синхронно, и доступность каждого составляет 99%, общая доступность вашей системы падает до:
> $$0.99 \times 0.99 \times 0.99 \times 0.99 \times 0.99 \approx 95\%$$
> Это означает около 18 дней простоя в год! Перейдите на асинхронный обмен сообщениями, чтобы сделать доступность независимой.

---

<a id="chatty-services"></a>
## «Болтливые» сервисы (N+1 сетевых вызовов)

Антипаттерн **«Болтливые» сервисы (Chatty Services)** — это распределенный эквивалент проблемы N+1 запросов к базе данных. Он возникает, когда сервисы слишком часто взаимодействуют друг с другом для выполнения одной операции, что приводит к высоким накладным расходам сети и медленной загрузке страниц.

### Плохой подход: API-вызовы в цикле (N+1 сетевых запросов)

В этом случае мы делаем HTTP-запрос для каждого элемента в цикле. Если у нас 50 заказов, мы выполним 50 отдельных сетевых вызовов.

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

### Хороший подход: групповые API и кэширование

Отрефакторите код, запрашивая все необходимые ID в одном групповом (bulk) API-запросе и кэшируя результаты во избежание дублирующих сетевых запросов.

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

API-шлюз (API Gateway) должен работать как легковесный обратный прокси-сервер (reverse proxy), отвечая за маршрутизацию, терминацию TLS и ограничение частоты запросов (rate limiting).

Мега-шлюз (Mega-Gateway) — это API-шлюз, разросшийся бизнес-логикой предметной области, валидацией данных или SQL-запросами к базе данных. Это превращает шлюз в монолитную единую точку отказа (SPOF) и связывает графики релизов всех нижестоящих сервисов.

### Плохой подход: обработка бизнес-логики на уровне API-шлюза

В этом примере класс шлюза обращается к базе данных и генерирует JWT-токены в обход сервиса аутентификации.

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

### Хороший подход: чистая прокси-маршрутизация

Шлюз должен только проксировать запрос соответствующему микросервису.

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
## Наносервисы (избыточная фрагментация)

Наносервис (Nano-service) — это слишком мелкий сервис. Избыточная фрагментация происходит, когда разработчики выделяют в отдельный сервис каждую единичную операцию (например, когда `CreateOrderService`, `DeleteOrderService` и `UpdateOrderService` разворачиваются как отдельные независимые артефакты).

Это приводит к следующим последствиям:
- Высокая задержка сети (сервисы непрерывно вызывают друг друга).
- Огромные накладные расходы на DevOps (управление конвейерами сборки, DNS и базами данных для десятков крошечных сервисов).
- Сильное дублирование кода.

> [!TIP]
> **Правильный выбор размера сервиса**: Согласуйте границы ваших сервисов с **ограниченными контекстами (Bounded Contexts)** из предметно-ориентированного проектирования (DDD), а не с объемом кода. Один микросервис должен инкапсулировать целостную бизнес-область, такую как `Ordering` (Заказы), `Billing` (Оплата) или `Inventory` (Склад).

---

<a id="common-mistakes"></a>
## Частые ошибки

1. **Обход изоляции через общую БД:** Создание микросервисов с сохранением единой базы данных PostgreSQL, в которой сервисы напрямую читают таблицы друг друга.
2. **Синхронные каскады:** Выстраивание цепочки синхронных вызовов HTTP между несколькими сервисами, что приводит к отказу всего исходного запроса при сбое любого сервиса в цепочке.
3. **Разрастание шлюза:** Добавление драйверов баз данных и бизнес-проверок в API-шлюз вместо делегирования запросов внутренним сервисам.
4. **Сетевые циклы N+1:** Запросы к удаленным API внутри циклов вместо использования групповых эндпоинтов для выборки данных.

---

<a id="checklist"></a>
## Чек-лист

1. **Изоляция базы данных:** Может ли разработчик изменить схему таблицы User без нарушения компиляции или выполнения запросов в сервисе Order?
2. **Поддержка групповых эндпоинтов:** Предоставляет ли ваш сервис методы API для получения списков ресурсов по массиву ID, или для этого требуются циклы одиночных запросов?
3. **Простота шлюза:** Содержит ли ваш API-шлюз SQL-запросы или интеграцию с внешними API? Если да, перенесите эту логику на уровень нижестоящих сервисов.`
4. **Границы домена:** Не меньше ли ваши микросервисы, чем один ограниченный контекст (Bounded Context)? Если они используют одни и те же таблицы БД, рассмотрите возможность их объединения.

---

<a id="summary"></a>
## Заключение

Микросервисы создаются для масштабирования команд и систем, но неверно определенные границы приводят к провалам. Избегайте антипаттерна **Общая база данных**, изолируя хранилище каждого сервиса. Предотвращайте появление **распределенных монолитов**, используя асинхронные события вместо блокирующих вызовов. Устраняйте **«болтливые» сервисы**, используя групповые API и локальное кэширование. Сохраняйте API-шлюзы легковесными и избегайте **мега-шлюзов**. Определяйте размер сервисов на основе ограниченных контекстов (Bounded Contexts), чтобы не допустить избыточной фрагментации в виде **наносервисов**.

---

<a id="self-test-quiz"></a>
## Тест для самопроверки

### Вопрос 1: Каков основной операционный симптом распределенного монолита?
- A) Базы данных реплицируются в несколько облаков.
- B) Вы не можете развернуть изменения в одном микросервисе без одновременного обновления других сервисов во избежание сбоев в работе.
- C) Выполнение PHP завершается сбоем из-за исчерпания подключений к Redis.

<details>
<summary>Нажмите, чтобы увидеть ответ</summary>

**Ответ: B**
Если сервисы жестко связаны через синхронные зависимости или общие схемы баз данных, их невозможно развертывать независимо. Это сводит на нет главное преимущество микросервисов (независимое развертывание) и создает распределенный монолит.
</details>

### Вопрос 2: Как антипаттерн «болтливых» сервисов влияет на производительность приложения?
- A) Он за миллисекунды исчерпывает пул подключений к базе данных.
- B) Он приводит к высокой задержке сети и нагрузке на процессор, выполняя множество мелких синхронных сетевых запросов вместо одного группового.
- C) Он вызывает предупреждения компилятора в PHP 8.x.

<details>
<summary>Нажмите, чтобы увидеть ответ</summary>

**Ответ: B**
Каждый сетевой вызов сопровождается накладными расходами на установление TCP-соединения, маршрутизацию, сериализацию и десериализацию данных. Выполнение множества мелких запросов в цикле (проблема N+1 сетевых запросов) резко увеличивает время ответа по сравнению с передачей одного группового пакета данных.
</details>

### Вопрос 3: Какой принцип проектирования должен определять размер микросервисов, чтобы избежать наносервисов?
- A) Создание отдельного микросервиса для каждого PHP-класса.
- B) Ограниченные контексты (Bounded Contexts) из предметно-ориентированного проектирования (DDD).
- C) Требование, чтобы файлы каждого микросервиса содержали менее 100 строк кода.

<details>
<summary>Нажмите, чтобы увидеть ответ</summary>

**Ответ: B**
Ограниченные контексты группируют связанные бизнес-модели и логику, разделяющие общую модель предметной области. Определение границ микросервисов вокруг ограниченных контекстов сохраняет их зацепление (cohesion) и минимизирует необходимость в интенсивном межсервисном взаимодействии.
</details>
