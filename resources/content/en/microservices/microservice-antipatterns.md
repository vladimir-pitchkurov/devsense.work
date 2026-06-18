---
title: "Microservice Antipatterns: Distributed Monolith, Shared Database & Chatty Services | DevSense"
description: "Avoid critical mistakes in distributed architectures. Learn how to identify and refactor microservices antipatterns: Shared Database, Distributed Monolith, and Chatty Services."
published: 2026-06-18
faq:
  - question: "Why is a Shared Database considered a critical microservices antipattern?"
    answer: "A Shared Database couples microservices at the storage level. Any schema change made by one team can immediately break other services, and direct database queries bypass service business validation rules, leading to data corruption."
  - question: "What is a Distributed Monolith?"
    answer: "A Distributed Monolith is a system that has been split into separate network services, but still requires synchronized deployments, shares database connections, or uses synchronous blocking RPC calls for almost every request, combining the complexity of microservices with the rigidity of a monolith."
  - question: "How do you solve the Chatty Services antipattern?"
    answer: "Solve Chatty Services by implementing bulk API endpoints (e.g., retrieving a list of IDs in one call), using local caching (with TTL) of slow-moving data, or adopting event-driven denormalization to keep copy-projections of necessary data locally."
---

# Microservice Antipatterns: Distributed Monolith, Shared Database & Chatty Services

Designing a microservices architecture is notoriously difficult. Many teams begin splitting their systems only to end up with a system that is slower, harder to deploy, and more complex than their original monolith. These failures are caused by common microservice antipatterns.

In this guide, we will analyze five microservices antipatterns, understand why they occur, and implement real-world PHP 8.x examples to refactor them into clean, decoupled architectures.

**Related guides:** [Monolith to microservices architecture](monolith-to-microservices-architecture) · [Microservice Architectural Patterns](microservice-patterns) · [Message queues compared](message-queues-compared)

## Contents

* [The Shared Database](#shared-database)
* [The Distributed Monolith](#distributed-monolith)
* [Chatty Services (N+1 Network Calls)](#chatty-services)
* [The Mega-Gateway](#mega-gateway)
* [Nano-Services (Over-fragmentation)](#nano-services)
* [Common Mistakes](#common-mistakes)
* [Checklist](#checklist)
* [Summary](#summary)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="shared-database"></a>
## The Shared Database

The **Shared Database** antipattern occurs when multiple microservices read or write to the same database tables directly. While this makes joins easy, it completely destroys team autonomy and service isolation. 

### The Bad Way: Cross-Database Eloquent Joins

In this PHP example, the Order service queries the User service's database table directly, coupling the Order code to the User table structure.

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

### The Good Way: API Decoupling or Event-Driven Denormalization

Instead, the Order service should call the User service API, or denormalize user metadata locally in its own database using event synchronization.

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
## The Distributed Monolith

A **Distributed Monolith** is a system that has been decomposed into services, but still acts like a monolith. Features are tightly coupled across network boundaries, meaning a change to Service A requires a coordinated deployment of Service B. 

This is often caused by synchronous, blocking HTTP or gRPC calls made for every user request, making the system's availability equal to the product of all services' individual availability.

> [!WARNING]
> **Availability Math Warning**: If you have 5 services that call each other synchronously, and each has 99% availability, your total system availability drops to:
> $$0.99 \times 0.99 \times 0.99 \times 0.99 \times 0.99 \approx 95\%$$
> This translates to 18 days of downtime per year! Refactor to asynchronous messaging to decouple availability.

---

<a id="chatty-services"></a>
## Chatty Services (N+1 Network Calls)

The **Chatty Services** antipattern is the distributed equivalent of the database N+1 query problem. It happens when services communicate too frequently to perform a single operation, resulting in high network overhead and slow page loads.

### The Bad Way: Loop-Based API Calls (N+1 Network requests)

Here, we make an HTTP request for every single item in the loop. If there are 50 orders, we make 50 individual network calls.

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

### The Good Way: Bulk APIs and Caching

Refactor by querying all required IDs in a single bulk API request, and caching the results to avoid duplicate network queries.

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
## The Mega-Gateway

An **API Gateway** should act as a lightweight reverse proxy, handling routing, TLS termination, and rate limiting. 

A **Mega-Gateway** is an API Gateway that has bloated with domain business logic, data validations, or database queries. This turns the gateway into a monolithic single point of failure that couples the release schedules of all downstream services.

### The Bad Way: API Gateway Processing Business Logic

Here, the Gateway class queries the database and generates custom JWT credentials, bypassing the authentication service.

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

### The Good Way: Pure Proxy Routing

The Gateway should only proxy the request to the downstream microservice.

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
## Nano-Services (Over-fragmentation)

A **Nano-service** is a service that is too small. Over-fragmentation happens when developers split a service for every single operation (e.g. `CreateOrderService`, `DeleteOrderService`, `UpdateOrderService` as separate deployment artifacts).

This results in:
- High network latency (services calling other services continuously).
- Overwhelming DevOps overhead (managing pipelines, DNS, and databases for dozens of tiny services).
- Extreme code duplication.

> [!TIP]
> **Correct Service Sizing**: Align your service boundaries with DDD (Domain-Driven Design) **Bounded Contexts** rather than code size. A single microservice should encapsulate a cohesive business domain, such as `Ordering`, `Billing`, or `Inventory`.

---

<a id="common-mistakes"></a>
## Common Mistakes

1. **Shared Database bypass:** Creating microservices but keeping a single PostgreSQL database where services read each other's tables directly.
2. **Synchronous cascades:** Making a chain of synchronous HTTP calls across multiple services, causing the entire request to fail if any service in the chain goes down.
3. **Gateway Bloat:** Adding database drivers and business checks to the API Gateway instead of delegating to internal downstream services.
4. **N+1 Network Loops:** Querying remote APIs in loops instead of writing bulk query endpoints.

---

<a id="checklist"></a>
## Checklist

1. **Database Isolation:** Can a developer change the schema of the User table without breaking the compilation or query execution of the Order service?
2. **Bulk Endpoint Support:** Does your service provide API methods to fetch resource lists by arrays of IDs, or does it require single query loops?
3. **Gateway Simplicity:** Does your API Gateway contain SQL queries or external API integrations? If so, refactor them downstream.
4. **Domain Boundaries:** Are your microservices smaller than a single Bounded Context? If they share the same database tables, consider merging them.

---

<a id="summary"></a>
## Summary

Microservices are built to scale teams and systems, but bad boundaries lead to failures. Avoid **Shared Databases** by isolating storage. Prevent **Distributed Monoliths** by leveraging asynchronous events over blocking calls. Eliminate **Chatty Services** by using Bulk APIs and local caching. Keep API Gateways lightweight and avoid **Mega-Gateways**. Size your services using Bounded Contexts to avoid **Nano-services** over-fragmentation.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: What is the main operational symptom of a Distributed Monolith?
- A) The databases are replicated to multiple clouds.
- B) You cannot deploy a change to one microservice without deploying updates to other services at the exact same time to prevent runtime crashes.
- C) PHP execution crashes due to Redis connection exhaustion.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
If services are tightly coupled through synchronous dependencies or shared database schemas, they cannot be deployed independently. This negates the primary advantage of microservices (independent deployability) and creates a distributed monolith.
</details>

### Question 2: How does the "Chatty Services" antipattern affect application performance?
- A) It exhausts the database connection pool in milliseconds.
- B) It introduces high network latency and CPU overhead by making numerous small, synchronous network requests instead of one bulk request.
- C) It triggers compiler warnings in PHP 8.x.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Every network call involves TCP handshake, routing, serialization, and deserialization overhead. Making numerous small requests in a loop (N+1 network requests) dramatically slows down the response time compared to a single bulk payload.
</details>

### Question 3: Which design principle should guide the sizing of microservices to avoid Nano-services?
- A) Make every PHP class a separate microservice.
- B) Bounded Contexts from Domain-Driven Design (DDD).
- C) Ensuring each microservice file has less than 100 lines of code.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Bounded Contexts group related business models and logic that share a common domain model. Sizing microservices around Bounded Contexts keeps services cohesive and minimizes the need for chatty cross-service communication.
</details>
