---
title: "Microservice-Antipatterns: Distributed Monolith, Shared Database & Chatty Services | DevSense"
description: "Vermeiden Sie kritische Fehler in verteilten Architekturen. Erfahren Sie, wie Sie Microservice-Antipatterns wie Shared Database, Distributed Monolith und Chatty Services identifizieren und refaktoren."
published: 2026-06-18
faq:
  - question: "Warum gilt eine Shared Database als kritisches Microservice-Antipattern?"
    answer: "Eine Shared Database (gemeinsam genutzte Datenbank) koppelt Microservices auf Speicherebene. Jede Schemaänderung durch ein team kann sofort andere Services lahmlegen. Zudem umgehen direkte Datenbankabfragen die Validierungsregeln des jeweiligen Service, was zu Dateninkonsistenzen führen kann."
  - question: "Was ist ein Distributed Monolith (verteilter Monolith)?"
    answer: "Ein Distributed Monolith ist ein System, das zwar in separate Netzwerkservices aufgeteilt wurde, aber dennoch synchronisierte Deployments erfordert, Datenbankverbindungen teilt oder für fast jede Anfrage synchrone blockierende RPC-Aufrufe nutzt. Es kombiniert die Komplexität von Microservices mit der Starrheit eines Monolithen."
  - question: "Wie löst man das Chatty-Services-Antipattern?"
    answer: "Sie lösen Chatty Services (gesprächige Services), indem Sie Bulk-API-Endpunkte implementieren (z. B. Abrufen einer Liste von IDs in einem Aufruf), lokales Caching (mit TTL) für sich selten ändernde Daten nutzen oder eine eventgesteuerte Denormalisierung einführen, um Kopien/Projektionen der benötigten Daten lokal vorzuhalten."
---

# Microservice-Antipatterns: Distributed Monolith, Shared Database & Chatty Services

Der Entwurf einer Microservices-Architektur ist bekanntlich schwierig. Viele Teams beginnen mit der Aufteilung ihrer Systeme und enden schließlich mit einem System, das langsamer, schwieriger zu deployen und komplexer ist als ihr ursprünglicher Monolith. Diese Misserfolge werden durch häufige Microservice-Antipatterns verursacht.

In diesem Leitfaden analysieren wir fünf Microservice-Antipatterns, untersuchen, warum sie auftreten, und implementieren praxisnahe PHP 8.x-Beispiele, um sie in saubere, entkoppelte Architekturen zu refaktorisieren.

**Verwandte Leitfäden:** [Architektur vom Monolithen zu Microservices](monolith-to-microservices-architecture) · [Microservice-Architekturmuster](microservice-patterns) · [Message Queues im Vergleich](message-queues-compared)

## Inhalt

* [Die Shared Database (gemeinsam genutzte Datenbank)](#shared-database)
* [Der Distributed Monolith (verteilter Monolith)](#distributed-monolith)
* [Chatty Services (N+1-Netzwerkaufrufe)](#chatty-services)
* [Das Mega-Gateway](#mega-gateway)
* [Nano-Services (Überfragmentierung)](#nano-services)
* [Häufige Fehler](#common-mistakes)
* [Checkliste](#checklist)
* [Zusammenfassung](#summary)
* [Selbsttest-Quiz](#self-test-quiz)

---

<a id="shared-database"></a>
## Die Shared Database (gemeinsam genutzte Datenbank)

Das Antipattern **Shared Database** (gemeinsam genutzte Datenbank) tritt auf, wenn mehrere Microservices direkt aus denselben Datenbanktabellen lesen oder in diese schreiben. Dies macht Joins zwar einfach, zerstört jedoch die Teamautonomie und die Service-Isolation vollständig.

### Der schlechte Weg: Datenbankübergreifende Eloquent-Joins

In diesem PHP-Beispiel fragt der Order-Service (Bestellservice) direkt die Datenbanktabelle des User-Service (Benutzerservice) ab, wodurch der Bestellcode an die Struktur der Benutzertabelle gekoppelt wird.

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

### Der gute Weg: API-Entkopplung oder eventgesteuerte Denormalisierung

Stattdessen sollte der Order-Service die API des User-Service aufrufen oder Benutzer-Metadaten mithilfe von Event-Synchronisation lokal in seiner eigenen Datenbank denormalisieren.

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
## Der Distributed Monolith (verteilter Monolith)

Ein **Distributed Monolith** (verteilter Monolith) ist ein System, das zwar in Services zerlegt wurde, sich aber immer noch wie ein Monolith verhält. Funktionen sind über Netzwerkgrenzen hinweg eng gekoppelt, sodass eine Änderung an Service A ein koordiniertes Deployment von Service B erfordert.

Dies wird häufig durch synchrone, blockierende HTTP- oder gRPC-Aufrufe verursacht, die bei jeder Benutzeranfrage getätigt werden. Dadurch entspricht die Gesamtverfügbarkeit des Systems dem Produkt der Einzelverfügbarkeiten aller Services.

> [!WARNING]
> **Verfügbarkeitsrechnung-Warnung**: Wenn Sie 5 Services haben, die sich gegenseitig synchron aufrufen, und jeder eine Verfügbarkeit von 99 % aufweist, sinkt die Gesamtverfügbarkeit Ihres Systems auf:
> $$0.99 \times 0.99 \times 0.99 \times 0.99 \times 0.99 \approx 95\%$$
> Dies entspricht 18 Tagen Ausfallzeit pro Jahr! Refaktorisieren Sie auf asynchrones Messaging (Nachrichtenübermittlung), um die Verfügbarkeit zu entkoppeln.

---

<a id="chatty-services"></a>
## Chatty Services (N+1-Netzwerkaufrufe)

Das Antipattern **Chatty Services** (gesprächige Services) ist das verteilte Äquivalent des N+1-Datenbankabfrageproblems. Es tritt auf, wenn Services zu häufig kommunizieren, um eine einzelne Operation auszuführen, was zu hohem Netzwerk-Overhead und langsamen Ladezeiten führt.

### Der schlechte Weg: Schleifenbasierte API-Aufrufe (N+1-Netzwerkanfragen)

Hier führen wir für jedes einzelne Element in der Schleife eine HTTP-Anfrage aus. Wenn es 50 Bestellungen gibt, machen wir 50 einzelne Netzwerkaufrufe.

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

### Der gute Weg: Bulk-APIs und Caching

Refaktorisieren Sie, indem Sie alle erforderlichen IDs in einer einzigen Bulk-API-Anfrage abfragen und die Ergebnisse cachen, um doppelte Netzwerkabfragen zu vermeiden.

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
## Das Mega-Gateway

Ein **API-Gateway** sollte als leichtgewichtiger Reverse Proxy fungieren und Routing, TLS-Terminierung sowie Rate-Limiting (Ratenbegrenzung) übernehmen.

Ein **Mega-Gateway** ist ein API-Gateway, das mit geschäftlicher Domänenlogik, Datenvalidierungen oder Datenbankabfragen aufgebläht wurde. Dadurch wird das Gateway zu einem monolithischen Single Point of Failure (einzelner Ausfallpunkt), der die Release-Zyklen aller nachgeschalteten Services aneinanderkoppelt.

### Der schlechte Weg: API-Gateway verarbeitet Geschäftslogik

Hier fragt die Gateway-Klasse direkt die Datenbank ab und generiert benutzerdefinierte JWT-Anmeldeinformationen, wodurch der Authentifizierungs-Service umgangen wird.

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

### Der gute Weg: Reines Proxy-Routing

Das Gateway sollte die Anfrage lediglich an den nachgeschalteten Microservice weiterleiten (proxien).

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
## Nano-Services (Überfragmentierung)

Ein **Nano-Service** ist ein Service, der zu klein ist. Eine Überfragmentierung (Over-fragmentation) entsteht, wenn Entwickler einen Service für jede einzelne Operation aufteilen (z. B. `CreateOrderService`, `DeleteOrderService`, `UpdateOrderService` as separate deployment artifacts).

Dies führt zu:
- Hoher Netzwerklatenz (Services rufen sich ständig gegenseitig auf).
- Enormem DevOps-Overhead (Verwalten von Pipelines, DNS und Datenbanken für Dutzende von winzigen Services).
- Extremer Code-Duplizierung.

> [!TIP]
> **Richtige Service-Größe**: Richten Sie Ihre Servicegrenzen an den **Bounded Contexts** von DDD (Domain-Driven Design) aus, statt an der Codegröße. Ein einzelner Microservice sollte einen zusammenhängenden Geschäftsbereich kapseln, wie zum Beispiel `Ordering` (Bestellwesen), `Billing` (Abrechnung) oder `Inventory` (Lagerbestand).

---

<a id="common-mistakes"></a>
## Häufige Fehler

1. **Bypass der Shared Database:** Erstellen von Microservices, aber Beibehalten einer einzigen PostgreSQL-Datenbank, bei der Services die Tabellen der anderen direkt auslesen.
2. **Synchrone Kaskaden:** Erstellen einer Kette synchroner HTTP-Aufrufe über mehrere Services hinweg, was dazu führt, dass die gesamte Anfrage fehlschlägt, wenn ein Service in der Kette ausfällt.
3. **Gateway-Aufblähung (Gateway Bloat):** Hinzufügen von Datenbanktreibern und Geschäftsprüfungen im API-Gateway, anstatt diese an interne, nachgeschaltete Services zu delegieren.
4. **N+1-Netzwerscheifen:** Abfragen von Remote-APIs in Schleifen, anstatt Bulk-Abfrage-Endpunkte zu schreiben.

---

<a id="checklist"></a>
## Checkliste

1. **Datenisolation:** Kann ein Entwickler das Schema der User-Tabelle ändern, ohne die Kompilierung oder Abfrageausführung des Order-Service zu beeinträchtigen?
2. **Bulk-Endpunkt-Unterstützung:** Bietet Ihr Service API-Methoden zum Abrufen von Ressourcenlisten über Arrays von IDs an, oder erfordert er Schleifen für Einzelabfragen?
3. **Gateway-Einfachheit:** Enthält Ihr API-Gateway SQL-Abfragen oder externe API-Integrationen? Wenn ja, refaktorisieren Sie diese in nachgeschaltete Services.
4. **Domänengrenzen:** Sind Ihre Microservices kleiner als ein einzelner Bounded Context? Wenn sie dieselben Datenbanktabellen teilen, sollten Sie eine Zusammenlegung in Betracht ziehen.

---

<a id="summary"></a>
## Zusammenfassung

Microservices sind so konzipiert, dass sie Teams und Systeme skalieren, aber schlechte Grenzen führen zu Misserfolgen. Vermeiden Sie eine **Shared Database**, indem Sie den Speicher isolieren. Beugen Sie einem **Distributed Monolith** vor, indem Sie asynchrone Events anstelle von blockierenden Aufrufen nutzen. Eliminieren Sie **Chatty Services** durch den Einsatz von Bulk-APIs und lokalem Caching. Halten API-Gateways leichtgewichtig und vermeiden Sie **Mega-Gateways**. Dimensionieren Sie Ihre Services anhand von Bounded Contexts, um eine Überfragmentierung in **Nano-Services** zu verhindern.

---

<a id="self-test-quiz"></a>
## Selbsttest-Quiz

### Frage 1: Was ist das primäre betriebliche Symptom eines verteilten Monolithen (Distributed Monolith)?
- A) Die databases werden in mehrere Clouds repliziert.
- B) Sie können eine Änderung an einem Microservice nicht deployen, ohne gleichzeitig Updates für andere Services bereitzustellen, um Abstürze zur Laufzeit zu verhindern.
- C) Die PHP-Ausführung stürzt aufgrund einer Erschöpfung der Redis-Verbindungen ab.

<details>
<summary>Klicken Sie hier, um die Antwort anzuzeigen</summary>

**Antwort: B**
Wenn Services durch synchrone Abhängigkeiten oder gemeinsam genutzte Datenbankschemata eng gekoppelt sind, können sie nicht unabhängig voneinander bereitgestellt werden. Dies hebt den Hauptvorteil von Microservices (die unabhängige Deploybarkeit) auf und erzeugt einen verteilten Monolithen.
</details>

### Frage 2: Wie wirkt sich das Antipattern „Chatty Services“ auf die Anwendungsperformance aus?
- A) Es erschöpft den Datenbank-Verbindungspool in Millisekunden.
- B) Es verursacht hohe Netzwerklatenz und CPU-Overhead, indem es zahlreiche kleine, synchrone Netzwerkanfragen statt einer einzigen Bulk-Anfrage stellt.
- C) Es löst Compilerwarnungen in PHP 8.x.

<details>
<summary>Klicken Sie hier, um die Antwort anzuzeigen</summary>

**Antwort: B**
Jeder Netzwerkaufruf verursacht TCP-Handshake-, Routing-, Serialisierungs- und Deserialisierungs-Overhead. Die Ausführung zahlreicher kleiner Anfragen in einer Schleife (N+1-Netzwerkanfragen) verlangsamt die Antwortzeit im Vergleich zu einer einzigen Bulk-Payload erheblich.
</details>

### Frage 3: Welches Entwurfsprinzip sollte die Dimensionierung von Microservices leiten, um Nano-Services zu vermeiden?
- A) Machen Sie jede PHP-Klasse zu einem separaten Microservice.
- B) Bounded Contexts aus dem Domain-Driven Design (DDD).
- C) Sicherstellen, dass jede Microservice-Datei weniger als 100 Zeilen Code enthält.

<details>
<summary>Klicken Sie hier, um die Antwort anzuzeigen</summary>

**Antwort: B**
Bounded Contexts gruppieren verwandte Geschäftsmodelle und Geschäftslogiken, die sich ein gemeinsames Domänenmodell teilen. Die Dimensionierung von Microservices um Bounded Contexts hält Services kohäsiv und minimiert die Notwendigkeit für gesprächige (chatty) Kommunikation zwischen den Services.
</details>
