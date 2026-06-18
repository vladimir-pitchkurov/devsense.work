---
title: "Anti-patterns de microservices : Monolithe distribué, Base de données partagée & Services bavards | DevSense"
description: "Évitez les erreurs critiques dans les architectures distribuées. Apprenez à identifier et à refactoriser les anti-patterns de microservices : Base de données partagée, Monolithe distribué et Services bavards."
published: 2026-06-18
faq:
  - question: "Pourquoi une base de données partagée (Shared Database) est-elle considérée comme un anti-pattern de microservices critique ?"
    answer: "Une base de données partagée couple les microservices au niveau du stockage. Tout changement de schéma effectué par une équipe peut immédiatement casser d'autres services, et les requêtes directes à la base de données contournent les règles de validation métier des services, entraînant une corruption potentielle des données."
  - question: "Qu'est-ce qu'un monolithe distribué (Distributed Monolith) ?"
    answer: "Un monolithe distribué est un système qui a été divisé en plusieurs services réseau distincts, mais qui nécessite toujours des déploiements synchronisés, partage des connexions de base de données ou utilise des appels RPC bloquants synchrones pour presque chaque requête, combinant la complexité des microservices avec la rigidité d'un monolithe."
  - question: "Comment résoudre l'anti-pattern des services bavards (Chatty Services) ?"
    answer: "Vous pouvez résoudre le problème des services bavards en implémentant des points de terminaison d'API de masse (bulk API endpoints, par exemple en récupérant une liste d'ID en un seul appel), en utilisant la mise en cache locale (avec TTL) pour les données peu changeantes, ou en adoptant une dénormalisation basée sur les événements pour conserver localement des projections des données nécessaires."
---

# Anti-patterns de microservices : Monolithe distribué, Base de données partagée & Services bavards

Concevoir une architecture de microservices est notoirement difficile. De nombreuses équipes commencent à diviser leur système pour finalement obtenir un résultat plus lent, plus difficile à déployer et plus complexe que leur monolithe d'origine. Ces échecs sont causés par des anti-patterns de microservices courants.

Dans ce guide, nous analyserons cinq anti-patterns de microservices, comprendrons pourquoi ils se produisent et implémenterons des exemples concrets en PHP 8.x pour les refactoriser en architectures propres et découplées.

**Guides connexes :** [Architecture du monolithe aux microservices](monolith-to-microservices-architecture) · [Patterns d'architecture de microservices](microservice-patterns) · [Comparatif des files d'attente de messages](message-queues-compared)

## Table des matières

* [La base de données partagée (Shared Database)](#shared-database)
* [Le monolithe distribué (Distributed Monolith)](#distributed-monolith)
* [Les services bavards (Chatty Services - Appels réseau N+1)](#chatty-services)
* [La méga-passerelle (Mega-Gateway)](#mega-gateway)
* [Les nano-services (Sur-fragmentation)](#nano-services)
* [Erreurs courantes](#common-mistakes)
* [Aide-mémoire / Checklist](#checklist)
* [Résumé](#summary)
* [Quiz d'auto-évaluation](#self-test-quiz)

---

<a id="shared-database"></a>
## La base de données partagée (Shared Database)

L'anti-pattern de la **Base de données partagée** (Shared Database) se produit lorsque plusieurs microservices lisent ou écrivent directement dans les mêmes tables de base de données. Bien que cela facilite les jointures (joins), cela détruit complètement l'autonomie des équipes et l'isolation des services.

### La mauvaise approche : jointures Eloquent inter-bases de données

Dans cet exemple PHP, le service Commande (Order) interroge directement la table de base de données du service Utilisateur (User), couplant ainsi le code de la commande à la structure de la table utilisateur.

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

### La bonne approche : découplage par API ou dénormalisation basée sur les événements

À la place, le service Commande devrait appeler l'API du service Utilisateur, ou dénormaliser localement les métadonnées de l'utilisateur dans sa propre base de données à l'aide d'une synchronisation par événements.

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
## Le monolithe distribué (Distributed Monolith)

Un **Monolithe distribué** (Distributed Monolith) est un système qui a été décomposé en services, mais qui se comporte toujours comme un monolithe. Les fonctionnalités sont étroitement couplées à travers les frontières réseau, ce qui signifie qu'une modification du Service A nécessite un déploiement coordonné du Service B.

Cela est souvent causé par des appels HTTP ou gRPC synchrones et bloquants effectués pour chaque requête utilisateur, rendant la disponibilité globale du système égale au produit de la disponibilité individuelle de chaque service.

> [!WARNING]
> **Avertissement sur le calcul de la disponibilité** : Si vous disposez de 5 services qui s'appellent mutuellement de manière synchrone, et que chacun a une disponibilité de 99 %, la disponibilité totale de votre système chute à :
> $$0.99 \times 0.99 \times 0.99 \times 0.99 \times 0.99 \approx 95\%$$
> Cela représente environ 18 jours d'indisponibilité par an ! Refactorisez vers une messagerie asynchrone pour découpler la disponibilité.

---

<a id="chatty-services"></a>
## Les services bavards (Chatty Services - Appels réseau N+1)

L'anti-pattern des **Services bavards** (Chatty Services) est l'équivalent distribué du problème de requête N+1 dans les bases de données. Il se produit lorsque les services communiquent trop fréquemment pour effectuer une seule opération, entraînant une surcharge réseau élevée et des temps de chargement de page lents.

### La mauvaise approche : appels d'API dans une boucle (requêtes réseau N+1)

Ici, nous effectuons une requête HTTP pour chaque élément de la boucle. S'il y a 50 commandes, nous effectuons 50 appels réseau individuels.

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

### La bonne approche : API de masse (Bulk APIs) et mise en cache

Refactorisez en interrogeant tous les identifiants requis dans une seule requête d'API de masse, et en mettant en cache les résultats pour éviter des requêtes réseau redondantes.

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
## La méga-passerelle (Mega-Gateway)

Une **passerelle d'API** (API Gateway) doit agir comme un proxy inverse (reverse proxy) léger, gérant le routage, la terminaison TLS et la limitation du débit (rate limiting).

Une **Méga-passerelle** (Mega-Gateway) est une passerelle d'API qui a été surchargée de logique métier, de validations de données ou de requêtes de base de données. Cela transforme la passerelle en un point de défaillance unique monolithique qui couple les calendriers de publication (release schedules) de tous les services en aval.

### La mauvaise approche : la passerelle d'API traitant la logique métier

Ici, la classe Gateway interroge la base de données et génère des identifiants JWT personnalisés, contournant le service d'authentification.

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

### La bonne approche : simple routage par proxy

La passerelle doit uniquement relayer la requête vers le microservice en aval.

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
## Les nano-services (Sur-fragmentation)

Un **Nano-service** est un service de taille trop réduite. La sur-fragmentation se produit lorsque les développeurs divisent un service pour chaque opération individuelle (par exemple, en créant `CreateOrderService`, `DeleteOrderService` et `UpdateOrderService` sous forme d'artefacts de déploiement distincts).

Cela entraîne :
- Une latence réseau élevée (les services s'appelant mutuellement en continu).
- Une surcharge DevOps écrasante (gestion des pipelines, des DNS et des bases de données pour des dizaines de micro-services).
- Une duplication extrême de code.

> [!TIP]
> **Dimensionnement correct du service** : Alignez les frontières de vos services avec les contextes délimités (**Bounded Contexts**) du Domain-Driven Design (DDD) plutôt qu'avec la taille du code. Un seul microservice doit encapsuler un domaine métier cohérent, tel que `Ordering` (Commande), `Billing` (Facturation) ou `Inventory` (Inventaire).

---

<a id="common-mistakes"></a>
## Erreurs courantes

1. **Contournement par base de données partagée :** Créer des microservices mais conserver une unique base de données PostgreSQL dans laquelle les services lisent directement les tables les uns des autres.
2. **Cascades synchrones :** Effectuer une chaîne d'appels HTTP synchrones à travers plusieurs services, entraînant l'échec de l'ensemble de la requête si l'un des services de la chaîne tombe en panne.
3. **Surcharge de la passerelle (Gateway Bloat) :** Ajouter des pilotes de base de données et des validations métier dans la passerelle d'API au lieu de déléguer aux services internes en aval.
4. **Boucles réseau N+1 :** Interroger des API distantes dans des boucles au lieu d'écrire des points de terminaison de requêtes de masse (bulk query endpoints).

---

<a id="checklist"></a>
## Aide-mémoire / Checklist

1. **Isolation des bases de données :** Un développeur peut-il modifier le schéma de la table User sans casser la compilation ou l'exécution des requêtes du service Order ?
2. **Prise en charge des bulk endpoints :** Votre service fournit-il des méthodes d'API pour récupérer des listes de ressources à l'aide de tableaux d'identifiants, ou nécessite-t-il des boucles de requête individuelle ?
3. **Simplicité de la passerelle :** Votre passerelle d'API contient-elle des requêtes SQL ou des intégrations d'API externes ? Si oui, refactorisez-les en aval.
4. **Frontières de domaine :** Vos microservices sont-ils plus petits qu'un seul Bounded Context ? S'ils partagent les mêmes tables de base de données, envisagez de les fusionner.

---

<a id="summary"></a>
## Résumé

Les microservices sont conçus pour mettre à l'échelle les équipes et les systèmes, mais de mauvaises frontières mènent à des échecs. Évitez les **bases de données partagées** (Shared Databases) en isolant le stockage. Prévenez l'apparition de **monolithes distribués** (Distributed Monoliths) en privilégiant les événements asynchrones aux appels bloquants. Éliminez les **services bavards** (Chatty Services) en utilisant des API de masse (Bulk APIs) et la mise en cache locale. Gardez les passerelles d'API légères et évitez les **méga-passerelles**. Dimensionnez vos services en utilisant les Bounded Contexts pour éviter la sur-fragmentation en **nano-services**.

---

<a id="self-test-quiz"></a>
## Quiz d'auto-évaluation

### Question 1 : Quel est le principal symptôme opérationnel d'un monolithe distribué (Distributed Monolith) ?
- A) Les bases de données sont répliquées sur plusieurs clouds.
- B) Vous ne pouvez pas déployer de modification sur un microservice sans déployer simultanément des mises à jour sur d'autres services pour éviter des plantages au moment de l'exécution.
- C) L'exécution de PHP plante en raison de l'épuisement des connexions Redis.

<details>
<summary>Cliquez pour voir la réponse</summary>

**Réponse : B**
Si les services sont étroitement couplés par des dépendances synchrones ou des schémas de base de données partagés, ils ne peuvent pas être déployés indépendamment. Cela annule le principal avantage des microservices (le déploiement indépendant) et crée un monolithe distribué.
</details>

### Question 2 : Comment l'anti-pattern des services bavards (Chatty Services) affecte-t-il les performances de l'application ?
- A) Il épuise le pool de connexions à la base de données en quelques millisecondes.
- B) Il introduit une latence réseau élevée et une surcharge du processeur en effectuant de nombreuses petites requêtes réseau synchrones au lieu d'une seule requête groupée (bulk).
- C) Il déclenche des avertissements du compilateur dans PHP 8.x.

<details>
<summary>Cliquez pour voir la réponse</summary>

**Réponse : B**
Chaque appel réseau implique une surcharge liée à la négociation TCP (handshake), au routage, à la sérialisation et à la désérialisation. Effectuer de nombreuses petites requêtes dans une boucle (requêtes réseau N+1) ralentit considérablement le temps de réponse par rapport à un chargement groupé en une seule fois.
</details>

### Question 3 : Quel principe de conception doit guider le dimensionnement des microservices afin d'éviter les nano-services ?
- A) Faire de chaque classe PHP un microservice distinct.
- B) Les contextes délimités (Bounded Contexts) du Domain-Driven Design (DDD).
- C) S'assurer que chaque fichier de microservice contient moins de 100 lignes de code.

<details>
<summary>Cliquez pour voir la réponse</summary>

**Réponse : B**
Les contextes délimités regroupent les modèles et la logique métier associés qui partagent un modèle de domaine commun. Le dimensionnement des microservices autour des Bounded Contexts maintient la cohésion des services et minimise le besoin de communications inter-services bavardes.
</details>
