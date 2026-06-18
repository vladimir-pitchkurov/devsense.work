---
title: "Antipatrones de microservicios: Distributed Monolith, Shared Database y Chatty Services | DevSense"
description: "Evita errores críticos en arquitecturas distribuidas. Aprende a identificar y refactorizar antipatrones de microservicios: Shared Database, Distributed Monolith y Chatty Services."
published: 2026-06-18
faq:
  - question: "¿Por qué se considera la base de datos compartida (Shared Database) como un antipatrón crítico de microservicios?"
    answer: "Una base de datos compartida acopla los microservicios a nivel de almacenamiento. Cualquier cambio de esquema realizado por un equipo puede romper inmediatamente otros servicios, y las consultas directas a la base de datos omiten las reglas de validación de negocio del servicio, lo que provoca la corrupción de datos."
  - question: "¿Qué es un monolito distribuido (Distributed Monolith)?"
    answer: "Un monolito distribuido es un sistema que se ha dividido en servicios de red separados, pero que aún requiere despliegues sincronizados, comparte conexiones de base de datos o utiliza llamadas RPC síncronas y bloqueantes para casi cada solicitud, combinando la complejidad de los microservicios con la rigidez de un monolito."
  - question: "¿Cómo se soluciona el antipatrón de servicios ruidosos o conversadores (Chatty Services)?"
    answer: "Se soluciona implementando endpoints de API por lotes o masivos (por ejemplo, recuperando una lista de IDs en una sola llamada), utilizando almacenamiento en caché local (con TTL) para datos que cambian lentamente, o adoptando desnormalización guiada por eventos para mantener copias de proyección de los datos necesarios localmente."
---

# Antipatrones de microservicios: Distributed Monolith, Shared Database y Chatty Services

Diseñar una arquitectura de microservicios es notoriamente difícil. Muchos equipos comienzan a dividir sus sistemas solo para terminar con un sistema que es más lento, más difícil de desplegar y más complejo que su monolito original. Estos fallos son causados por antipatrones comunes de microservicios.

En esta guía, analizaremos cinco antipatrones de microservicios, entenderemos por qué ocurren e implementaremos ejemplos reales en PHP 8.x para refactorizarlos en arquitecturas limpias y desacopladas.

**Guías relacionadas:** [Arquitectura de monolito a microservicios](monolith-to-microservices-architecture) · [Patrones de arquitectura de microservicios](microservice-patterns) · [Comparativa de colas de mensajes](message-queues-compared)

## Contenidos

* [La base de datos compartida (Shared Database)](#shared-database)
* [El monolito distribuido (Distributed Monolith)](#distributed-monolith)
* [Servicios conversadores (Chatty Services - llamadas de red N+1)](#chatty-services)
* [El Mega-Gateway](#mega-gateway)
* [Nano-servicios (sobreframentalización)](#nano-services)
* [Errores comunes](#common-mistakes)
* [Lista de verificación](#checklist)
* [Resumen](#summary)
* [Prueba de autoevaluación](#self-test-quiz)

---

<a id="shared-database"></a>
## La base de datos compartida (Shared Database)

El antipatrón **Shared Database** ocurre cuando múltiples microservicios leen o escriben directamente en las mismas tablas de la base de datos. Aunque esto facilita las uniones (joins), destruye por completo la autonomía del equipo y el aislamiento del servicio.

### La forma incorrecta: Uniones Eloquent entre bases de datos

En este ejemplo de PHP, el servicio de Pedidos (Orders) consulta la tabla de base de datos del servicio de Usuarios (Users) directamente, acoplando el código de Pedidos a la estructura de la tabla de Usuarios.

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

### La forma correcta: Desacoplamiento por API o desnormalización guiada por eventos

En su lugar, el servicio de Pedidos debe llamar a la API del servicio de Usuarios, o desnormalizar los metadatos de los usuarios localmente en su propia base de datos mediante la sincronización de eventos.

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
## El monolito distribuido (Distributed Monolith)

Un **Distributed Monolith** (monolito distribuido) es un sistema que se ha descompuesto en servicios, pero que sigue actuando como un monolito. Las características están estrechamente acopladas a través de los límites de la red, lo que significa que un cambio en el Servicio A requiere un despliegue coordinado del Servicio B.

Esto suele deberse a llamadas HTTP o gRPC síncronas y bloqueantes realizadas para cada solicitud de usuario, lo que hace que la disponibilidad del sistema sea igual al producto de la disponibilidad individual de todos los servicios.

> [!WARNING]
> **Advertencia matemática sobre disponibilidad**: Si tienes 5 servicios que se llaman entre sí de forma síncrona y cada uno tiene una disponibilidad del 99%, la disponibilidad total de tu sistema cae a:
> $$0.99 \times 0.99 \times 0.99 \times 0.99 \times 0.99 \approx 95\%$$
> ¡Esto se traduce en 18 días de inactividad al año! Refactoriza hacia la mensajería asíncrona para desacoplar la disponibilidad.

---

<a id="chatty-services"></a>
## Servicios conversadores (Chatty Services - llamadas de red N+1)

El antipatrón **Chatty Services** (servicios ruidosos o conversadores) es el equivalente distribuido del problema de la consulta N+1 en las bases de datos. Ocurre cuando los servicios se comunican con demasiada frecuencia para realizar una sola operación, lo que resulta en una alta sobrecarga de red y cargas de página lentas.

### La forma incorrecta: Llamadas a la API basadas en bucles (peticiones de red N+1)

Aquí, realizamos una solicitud HTTP para cada elemento individual en el bucle. Si hay 50 pedidos, realizamos 50 llamadas de red individuales.

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

### La forma correcta: APIs por lotes y almacenamiento en caché

Refactoriza consultando todos los IDs requeridos en una sola solicitud de API por lotes (bulk API), y almacenando en caché los resultados para evitar consultas de red duplicadas.

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
## El Mega-Gateway

Un **API Gateway** debe actuar como un proxy inverso ligero, encargándose del enrutamiento, la terminación TLS y la limitación de tasa (rate limiting).

Un **Mega-Gateway** es un API Gateway que se ha sobrecargado con lógica de negocio del dominio, validaciones de datos o consultas a bases de datos. Esto convierte al gateway en un punto único de fallo monolítico que acopla los calendarios de lanzamiento de todos los servicios descendentes (downstream).

### La forma incorrecta: API Gateway procesando lógica de negocio

Aquí, la clase Gateway consulta la base de datos y genera credenciales JWT personalizadas, omitiendo el servicio de autenticación.

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

### La forma correcta: Enrutamiento proxy puro

El Gateway solo debe delegar (proxy) la solicitud al microservicio correspondiente.

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
## Nano-servicios (sobreframentalización)

Un **Nano-service** es un servicio que es demasiado pequeño. La sobreframentalización ocurre cuando los desarrolladores dividen un servicio para cada operación individual (por ejemplo, `CreateOrderService`, `DeleteOrderService`, `UpdateOrderService` como artefactos de despliegue separados).

Esto da como resultado:
- Alta latencia de red (servicios llamando a otros servicios continuamente).
- Sobrecarga abrumadora de DevOps (gestión de flujos de despliegue, DNS y bases de datos para docenas de servicios diminutos).
- Duplicación extrema de código.

> [!TIP]
> **Tamaño correcto del servicio**: Alinea los límites de tus servicios con los **Contextos Acotados** (Bounded Contexts) de DDD (Diseño Guiado por el Dominio) en lugar del tamaño del código. Un único microservicio debe encapsular un dominio de negocio cohesivo, como `Ordering`, `Billing` o `Inventory`.

---

<a id="common-mistakes"></a>
## Errores comunes

1. **Omisión de base de datos aislada:** Crear microservicios pero mantener una única base de datos PostgreSQL donde los servicios leen las tablas de los demás directamente.
2. **Cascadas síncronas:** Realizar una cadena de llamadas HTTP síncronas entre múltiples servicios, lo que provoca que toda la solicitud falle si falla algún servicio de la cadena.
3. **Sobrecarga del Gateway:** Agregar controladores de bases de datos y comprobaciones de negocio en el API Gateway en lugar de delegar en los servicios internos descendentes.
4. **Bucles de red N+1:** Consultar APIs remotas en bucles en lugar de escribir endpoints de consulta por lotes o masivos (bulk).

---

<a id="checklist"></a>
## Lista de verificación

1. **Aislamiento de la base de datos:** ¿Puede un desarrollador cambiar el esquema de la tabla User sin romper la compilación o la ejecución de consultas del servicio Order?
2. **Soporte de endpoints por lotes:** ¿Tu servicio proporciona métodos de API para recuperar listas de recursos mediante arrays de IDs, o requiere bucles de consulta individuales?
3. **Simplicidad del Gateway:** ¿Tu API Gateway contiene consultas SQL o integraciones de APIs externas? Si es así, refactorízalas hacia los servicios descendentes.
4. **Límites de dominio:** ¿Son tus microservicios más pequeños que un único Contexto Acotado? Si comparten las mismas tablas de base de datos, considera fusionarlos.

---

<a id="summary"></a>
## Resumen

Los microservicios se crean para escalar equipos y sistemas, pero unos límites deficientes conducen a fallos. Evita las **Bases de datos compartidas** aislando el almacenamiento. Evita los **Monolitos distribuidos** aprovechando los eventos asíncronos en lugar de las llamadas bloqueantes. Elimina los **Servicios conversadores** utilizando APIs por lotes y almacenamiento en caché local. Mantén los API Gateways ligeros y evita los **Mega-Gateways**. Ajusta el tamaño de tus servicios utilizando Contextos Acotados para evitar la sobreframentalización en **Nano-servicios**.

---

<a id="self-test-quiz"></a>
## Prueba de autoevaluación

### Pregunta 1: ¿Cuál es el principal síntoma operativo de un monolito distribuido?
- A) Las bases de datos se replican en múltiples nubes.
- B) No puedes desplegar un cambio en un microservicio sin desplegar actualizaciones en otros servicios exactamente al mismo tiempo para evitar caídas en tiempo de ejecución.
- C) PHP se bloquea debido al agotamiento de las conexiones de Redis.

<details>
<summary>Haz clic para ver la respuesta</summary>

**Respuesta: B**
Si los servicios están estrechamente acoplados mediante dependencias síncronas o esquemas de base de datos compartidos, no se pueden desplegar de forma independiente. Esto anula la principal ventaja de los microservicios (despliegue independiente) y crea un monolito distribuido.
</details>

### Pregunta 2: ¿Cómo afecta el antipatrón de servicios ruidosos o conversadores (Chatty Services) al rendimiento de la aplicación?
- A) Agota el pool de conexiones de la base de datos en milisegundos.
- B) Introduce una alta latencia de red y sobrecarga de CPU al realizar numerosas solicitudes de red síncronas y pequeñas en lugar de una sola solicitud por lotes.
- C) Desencadena advertencias del compilador en PHP 8.x.

<details>
<summary>Haz clic para ver la respuesta</summary>

**Respuesta: B**
Cada llamada de red implica una sobrecarga de negociación TCP, enrutamiento, serialización y despolarización. Realizar numerosas solicitudes pequeñas en un bucle (peticiones de red N+1) ralentiza drásticamente el tiempo de respuesta en comparación con una sola carga útil por lotes.
</details>

### Pregunta 3: ¿Qué principio de diseño debe guiar el tamaño de los microservicios para evitar los Nano-servicios?
- A) Hacer de cada clase PHP un microservicio independiente.
- B) Los Contextos Acotados (Bounded Contexts) del Diseño Guiado por el Dominio (DDD).
- C) Asegurar que cada archivo de microservicio tenga menos de 100 líneas de código.

<details>
<summary>Haz clic para ver la respuesta</summary>

**Respuesta: B**
Los Contextos Acotados agrupan modelos y lógica de negocio relacionados que comparten un modelo de dominio común. Definir el tamaño de los microservicios en torno a los Contextos Acotados mantiene los servicios cohesivos y minimiza la necesidad de comunicaciones ruidosas entre servicios.
</details>
