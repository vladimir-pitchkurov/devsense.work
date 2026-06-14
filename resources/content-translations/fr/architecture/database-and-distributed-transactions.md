---
title: "Bases de données et transactions distribuées : isolation, verrous et modèles de consensus"
description: "Un guide complet sur les transactions de base de données, les conditions de concurrence, le verrouillage optimiste vs pessimiste, les verrous distribués basés sur Redis, le protocole 2PC, l'orchestration/chorégraphie de Saga et le modèle Transactional Outbox avec courtiers de messages."
published: 2026-06-14
faq:
  - question: "Quelle est la différence entre le verrouillage optimiste et pessimiste ?"
    answer: "Le verrouillage pessimiste prévient les problèmes de concurrence en verrouillant les lignes de la base de données (via SELECT FOR UPDATE), bloquant les autres transactions jusqu'à ce que le verrou soit libéré. Le verrouillage optimiste suppose que les conflits sont rares ; il autorise les opérations simultanées mais vérifie une colonne de version ou de timestamp lors des mises à jour. Si une autre transaction a modifié la ligne entre-temps, la mise à jour échoue et l'application doit réessayer."
  - question: "Pourquoi la validation en deux phases (2PC) est-elle rarement utilisée dans les microservices modernes ?"
    answer: "La validation en deux phases est un protocole synchrone et bloquant. Elle exige que tous les services participants verrouillent leurs ressources pendant les deux phases. Si un service ralentit ou se déconnecte, tous les autres restent bloqués, créant un point de défaillance unique et limitant fortement la scalabilité et la disponibilité du système."
  - question: "Comment le modèle Transactional Outbox garantit-il la livraison 'au moins une fois' (at-least-once) ?"
    answer: "Au lieu de publier un message directement sur un courtier de messages (broker) pendant une transaction (ce qui pourrait échouer si le broker est hors ligne), le service écrit les données métier et le message dans une table 'outbox' au sein de la même transaction locale. Un processus d'arrière-plan distinct lit cette table de manière asynchrone, envoie les messages au broker et les marque comme traités, garantissant qu'aucun message n'est perdu."
---

# Bases de données et transactions distribuées : isolation, verrous et modèles de consensus

Imaginez un utilisateur essayant de réserver le dernier siège disponible sur un vol. Deux requêtes arrivent sur les serveurs d'applications exactement à la même milliseconde. Si le système n'est pas conçu pour gérer la concurrence, les deux requêtes liront le statut du siège comme \"disponible\", débiteront le paiement et émettront deux billets pour le même siège. Ce problème classique de double réservation est un cauchemar pour les développeurs et les entreprises. Dans un système monolithique avec une seule base de données, les transactions SQL peuvent facilement l'éviter. Mais dans un système distribué, où le service de réservation, la passerelle de paiement et le système de notification sont des microservices isolés, une simple transaction de base de données ne suffit plus.

La conception de systèmes transactionnels robustes exige d'adapter la granularité du verrouillage à l'échelle de l'architecture, en passant de simples verrous SQL locaux à des modèles de consensus distribués pour la cohérence entre services.

## Table des matières
* [Transactions locales : ACID et niveaux d'isolation](#acid-isolation)
* [Conditions de concurrence : verrouillage pessimiste vs optimiste](#locking-strategies)
* [Verrous distribués à grande échelle : solutions basées sur Redis](#redis-locks)
* [Transactions distribuées : la chute de la validation en deux phases (2PC)](#distributed-2pc)
* [Cohérence à terme : Saga et Transactional Outbox](#saga-outbox)
* [Démonstration pratique de code en PHP & Laravel](#code-demo)
* [Limites et compromis](#limitations)
* [Conseils pratiques](#takeaways)

---

<a id="acid-isolation"></a>
## Transactions locales : ACID et niveaux d'isolation

Au cœur de l'intégrité des données se trouve le modèle ACID : atomicité (Atomicity), cohérence (Consistency), isolation (Isolation) et durabilité (Durability). Alors que l'atomicité (\"tout ou rien\") et la durabilité (stockage permanent) sont simples, l'isolation est le point où la performance et la correction s'affrontent.

Les bases de données offrent quatre niveaux d'isolation standard pour gérer les accès concurrents, chacun évitant différentes anomalies :

1. **Read Uncommitted (Lecture non validée)** : Le niveau le plus bas. Une transaction peut lire des modifications non validées d'une autre transaction (Dirty Reads ou lectures sales).
2. **Read Committed (Lecture validée)** : Évite les lectures sales. Une requête ne lit que les données validées avant le début de la requête. Cependant, si elle réexécute la requête, elle peut voir des modifications validées par d'autres transactions entre-temps (Non-repeatable Reads ou lectures non reproductibles).
3. **Repeatable Read (Lecture répétable)** : Évite les lectures non reproductibles. Les données lues pendant la transaction restent cohérentes. Dans certaines bases de données, cela n'empêche pas l'insertion de nouvelles lignes par d'autres transactions (Phantom Reads ou lectures fantômes).
4. **Serializable (Sérialisable)** : Le niveau le plus élevé. Les transactions sont exécutées de manière à produire le même résultat que si elles s'exécutaient séquentiellement, éliminant toutes les anomalies de concurrence au prix de lourdes pertes de performance.

La plupart des bases de données relationnelles (comme PostgreSQL et MySQL) utilisent par défaut **Read Committed** ou **Repeatable Read**. Pour obtenir une sécurité totale sans sacrifier la performance, les développeurs doivent contrôler explicitement les verrous plutôt que de s'en remettre uniquement au niveau d'isolation global de la base de données.

---

<a id="locking-strategies"></a>
## Conditions de concurrence : verrouillage pessimiste vs optimiste

Lorsque plusieurs clients tentent de modifier simultanément la même ligne de base de données, nous devons choisir une stratégie de verrouillage.

### Verrouillage pessimiste (Pessimistic Locking)
* **Point** : Supposer que les conflits sont très probables et bloquer l'accès de manière proactive.
* **Pourquoi c'est important** : Prévenir les conditions de concurrence directement au niveau de la base de données en utilisant ses mécanismes de verrouillage internes.
* **Exemple** : L'exécution de `SELECT ... FOR UPDATE` verrouille les lignes sélectionnées. Toute autre transaction tentant de lire ces lignes avec `FOR UPDATE` ou de les modifier sera bloquée jusqu'à ce que la première transaction soit validée ou annulée.
* **Conséquence** : Sécurité maximale mais faible parallélisme. Si les transactions prennent du temps (par exemple, en attendant des requêtes réseau externes), les autres threads de la base de données épuisent rapidement le pool de connexions, provoquant le crash de l'application.

### Verrouillage optimiste (Optimistic Locking)
* **Point** : Supposer que les conflits sont rares, autoriser les lectures simultanées mais valider lors des écritures.
* **Pourquoi c'est important** : Éviter complètement les verrous de base de données, maximisant le débit de lecture et la scalabilité.
* **Exemple** : Chaque ligne dispose d'une colonne `version` (entier) ou `updated_at` (timestamp). Lors de la mise à jour, la requête SQL se structure ainsi :
  ```sql
  UPDATE inventory SET quantity = quantity - 1, version = version + 1 
  WHERE id = 1 AND version = 3;
  ```
* **Conséquence** : Si une autre transaction a mis à jour la ligne en premier, la version serait 4, et cette requête mettrait à jour 0 ligne. L'application détecte cela et décide de réessayer ou d'échouer. Cependant, en cas de forte concurrence d'écriture, les tentatives fréquentes peuvent dégrader les performances.

---

<a id="redis-locks"></a>
## Verrous distribués à grande échelle : solutions basées sur Redis

Dans les applications à forte concurrence, le verrouillage au niveau de la base de données peut surcharger son processeur. De plus, si vous devez verrouiller un processus métier qui n'est pas mappé sur une seule ligne de table, ou verrouiller des opérations sur différents systèmes, les verrous de base de données sont insuffisants.

* **Point** : Utiliser un stockage en mémoire rapide comme Redis pour gérer les verrous en dehors de la base de données relationnelle.
* **Pourquoi c'est important** : Les opérations de Redis sont monothreads et exécutées de façon atomique, ce qui les rend extrêmement rapides (moins d'une milliseconde) et libère les connexions de base de données.
* **Exemple** : Un processus acquiert un verrou à l'aide de la commande atomique `SET lock_key unique_token NX PX 5000` (définir si n'existe pas, expire après 5000 ms). Lors de la libération, un script Lua vérifie que le processus actuel détient le jeton unique avant de supprimer la clé.
* **Conséquence** : Hautement scalable. Cependant, si Redis tombe en panne, ou dans un environnement clusterisé où un master échoue avant de répliquer le verrou sur un réplica (split-brain), des verrous doublons peuvent être accordés. L'algorithme Redlock résout ce problème en acquérant des verrous sur plusieurs nœuds Redis indépendants.

---

<a id="distributed-2pc"></a>
## Transactions distribuées : la chute de la validation en deux phases

Lors du passage à une architecture microservices, les données sont réparties entre les services. Une transaction métier peut engager un Service Client, un Service Inventaire et un Service Paiement.

Historiquement, la solution standard pour les transactions distribuées était la **validation en deux phases (2PC)** :
1. **Phase de préparation (Prepare)** : Un coordinateur demande à tous les services participants s'ils sont prêts à valider. Les services verrouillent leurs ressources locales et répondent \"oui\".
2. **Phase de validation (Commit)** : Si tous les services ont répondu \"oui\", le coordinateur leur ordonne de valider. Si l'un d'eux a répondu \"non\", il leur ordonne d'annuler (rollback).

Bien que conceptuellement simple, le 2PC est un **protocole synchrone et bloquant**. Si le réseau échoue ou si un service se déconnecte pendant la phase de validation, les ressources restent verrouillées indéfiniment. Cela réduit considérablement le débit et viole le principe de disponibilité du théorème CAP. Les systèmes distribués modernes préfèrent échanger la cohérence atomique contre une cohérence à terme.

---

<a id="saga-outbox"></a>
## Cohérence à terme : Saga et Transactional Outbox

Pour assurer la fiabilité entre plusieurs microservices sans bloquer les ressources, nous utilisons des modèles de conception asynchrones.

### Modèle Saga
Une Saga est une séquence de transactions locales. Chaque service met à jour sa base de données dans une transaction locale et publie un événement. D'autres services écoutent cet événement et exécutent leurs transactions locales. Si une transaction échoue, la Saga exécute des **transactions compensatoires** en ordre inverse pour annuler les changements.

* **Chorégraphie** : Les services publient et s'abonnent aux événements sans coordinateur central. Rapide et découplé, mais difficile à suivre dans des flux complexes.
* **Orchestration** : Un service central orchestre le flux, appelant les services et gérant la logique de compensation. Plus facile à modéliser, mais introduit un point de défaillance unique pour l'orchestration.

### Modèle Transactional Outbox
Un anti-pattern courant consiste à publier un événement sur un courtier de messages (comme RabbitMQ) directement à l'intérieur d'une transaction de base de données. Si le broker est hors ligne, la transaction s'annule. Si le broker réussit mais que la base de données échoue à valider, vous créez des événements fantômes.

Le modèle **Transactional Outbox** résout ce problème :
1. Écrire à la fois les modifications du modèle de domaine et les données de l'événement (dans une table `outbox`) au sein de la même transaction de base de données.
2. Un processus indépendant en arrière-plan (Message Relay) scrute la table `outbox`, publie les messages sur le broker et les marque comme envoyés.
3. Cela garantit la **livraison au moins une fois**. Le récepteur doit implémenter l'**idempotence** (le traitement répété du même message produit le même résultat) pour gérer les doublons en toute sécurité.

---

<a id="code-demo"></a>
## Démonstration pratique de code en PHP & Laravel

Voici comment vous pouvez implémenter ces modèles dans une application Laravel.

### 1. Transaction de base de données avec verrouillage pessimiste
```php
// app/Services/BookingService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\FlightSeat;

class BookingService
{
    public function bookSeat(int $seatId, int $userId): bool
    {
        return DB::transaction(function () use ($seatId, $userId) {
            // Verrouiller la ligne pour mise à jour. Les autres threads bloquent ici.
            $seat = FlightSeat::where('id', $seatId)
                ->lockForUpdate()
                ->first();

            if (!$seat || $seat->is_booked) {
                return false;
            }

            $seat->update([
                'is_booked' => true,
                'user_id' => $userId,
            ]);

            return true;
        });
    }
}
```

### 2. Verrou distribué avec Redis
```php
// app/Services/InventoryService.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class InventoryService
{
    public function decreaseStock(int $productId, int $quantity): bool
    {
        $lockKey = "lock:product:{$productId}";
        
        // Tente d'acquérir le verrou pour 10 secondes, attend jusqu'à 3 secondes.
        $lock = Cache::lock($lockKey, 10);

        if ($lock->get()) {
            try {
                // Mise à jour rapide et non bloquante
                // ... logique de mise à jour des stocks
                return true;
            } finally {
                $lock->release();
            }
        }

        return false;
    }
}
```

### 3. Insertion dans la Transactional Outbox
```php
// app/Services/OrderService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OutboxMessage;

class OrderService
{
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create($data);

            // Enregistrer l'événement dans la même transaction
            OutboxMessage::create([
                'event_type' => 'order.created',
                'payload' => json_encode([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'total' => $order->total_amount,
                ]),
                'processed' => false,
            ]);

            return $order;
        });
    }
}
```

---

<a id="limitations"></a>
## Limites et compromis

* **Verrouillage optimiste** : Sous des charges d'écriture simultanées extrêmement élevées, les mises à jour optimistes échoueront fréquemment, entraînant des erreurs pour les requêtes clients ou forçant une utilisation intensive du processeur lors des tentatives.
* **Verrous Redis** : Un verrou n'est sûr que pendant sa durée d'expiration (TTL). Si un processus prend plus de temps que la durée du verrou (par exemple, en raison d'une longue pause du garbage collector), le verrou expire, un autre processus l'acquiert et vous perdez l'exclusion mutuelle.
* **Sagas** : Les transactions compensatoires ne peuvent pas annuler physiquement les effets secondaires externes (comme l'envoi d'un e-mail ou un débit bancaire). Par conséquent, le flux métier doit être conçu avec des états intermédiaires (comme la réservation de fonds ou des brouillons d'e-mails).

---

<a id="takeaways"></a>
## Conseils pratiques

1. Utilisez le **verrouillage optimiste** pour les opérations avec un volume élevé de lecture et des conflits peu fréquents (par exemple, édition de contenu ou mises à jour de profils).
2. Utilisez le **verrouillage pessimiste** (`SELECT ... FOR UPDATE`) pour les transactions financières critiques avec une concurrence modérée, où les conflits doivent être résolus au sein de la base de données.
3. Utilisez les **verrous Redis** pour protéger les flux de travail applicatifs et éviter que plusieurs workers n'exécutent des tâches identiques simultanément.
4. Pour les microservices, évitez les transactions distribuées synchrones (comme 2PC). Implémentez le **modèle Saga** pour l'orchestration et le **modèle Transactional Outbox** pour garantir une livraison asynchrone fiable des événements.
