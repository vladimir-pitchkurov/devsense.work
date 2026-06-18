---
title: "Patrons de comportement GoF : Chain of Responsibility, Observer, Strategy et Command | DevSense"
description: "Un guide approfondi sur les patrons de conception de comportement du GoF en PHP : Strategy, Observer, Chain of Responsibility, Command, Iterator, Mediator, Memento, State, Template Method, Visitor et Interpreter avec des exemples concrets en PHP."
published: 2026-06-18
faq:
  - question: "Quelle est la différence principale entre les patrons Strategy et State ?"
    answer: "Le patron Strategy est utilisé lorsque vous souhaitez choisir ou remplacer un algorithme ou une stratégie d'exécution spécifique au moment de l'exécution, et le client est généralement conscient des stratégies. Le patron State est utilisé lorsqu'un objet se comporte différemment selon son état interne, et les transitions d'état sont gérées automatiquement par les objets d'état eux-mêmes, généralement de manière masquée pour le client."
  - question: "En quoi le patron Observer diffère-t-il de l'architecture Publish-Subscribe ?"
    answer: "Dans le patron Observer, le sujet (publisher) maintient une liste directe de ses observateurs et les notifie directement. Dans le Publish-Subscribe, il existe un courtier de messages ou un canal d'événements intermédiaire qui découple complètement les éditeurs des abonnés, de sorte qu'ils ne se connaissent pas."
  - question: "Quand doit-on utiliser Chain of Responsibility plutôt qu'un Decorator ?"
    answer: "Utilisez la Chain of Responsibility lorsque l'un de plusieurs objets gestionnaires peut traiter une requête (ou la transmettre), et que la requête peut être arrêtée ou court-circuitée à n'importe quelle étape. Utilisez un Decorator lorsque vous souhaitez exécuter toutes les enveloppes d'une pile pour enrichir le comportement de l'objet de base, sans court-circuit ni choix d'un gestionnaire unique."
---

# Patrons de comportement GoF : Chain of Responsibility, Observer, Strategy et Command

Les patrons de comportement s'intéressent aux algorithmes et à la répartition des responsabilités entre les objets. Ils décrivent non seulement des modèles d'objets ou de classes, mais aussi les modèles de communication entre eux. Ces patrons caractérisent des flux de contrôle complexes qui sont difficiles à suivre au moment de l'exécution.

**Guides connexes :** [Patrons de structure GoF](structural-design-patterns) · [De l'architecture monolithique aux microservices](monolith-to-microservices-architecture)

## Table des matières

* [Introduction aux patrons de comportement](#intro)
* [Le patron Strategy (Stratégie)](#strategy)
* [Le patron Observer (Observateur)](#observer)
* [Le patron Chain of Responsibility (Chaîne de responsabilité)](#chain-of-responsibility)
* [Le patron Command (Commande)](#command)
* [Autres patrons de comportement (Iterator, Mediator, Memento, State, Template Method, Visitor, Interpreter)](#others)
* [Erreurs courantes](#common-mistakes)
* [Liste de contrôle](#checklist)
* [Résumé](#summary)
* [Quiz d'auto-évaluation](#self-test-quiz)

---

<a id="intro"></a>
## Introduction aux patrons de comportement

Alors que les patrons de création se concentrent sur l'instanciation des objets et les patrons de structure sur la composition des classes, les **patrons de comportement** se concentrent sur la communication. Ils définissent des moyens propres pour déléguer des actions, acheminer des requêtes, gérer les changements d'état et remplacer dynamiquement des algorithmes au moment de l'exécution.

---

<a id="strategy"></a>
## Le patron Strategy (Stratégie)

Le patron **Strategy** définit une famille d'algorithmes, encapsule chacun d'eux et les rend interchangeables. Strategy permet à l'algorithme de varier indépendamment des clients qui l'utilisent.

```php
// app/Payment/PaymentStrategyInterface.php
declare(strict_types=1);

namespace App\Payment;

interface PaymentStrategyInterface
{
    public function pay(float $amount): bool;
}

class StripePaymentStrategy implements PaymentStrategyInterface
{
    public function pay(float $amount): bool
    {
        // Stripe payment API logic...
        return true;
    }
}

class PayPalPaymentStrategy implements PaymentStrategyInterface
{
    public function pay(float $amount): bool
    {
        // PayPal payment API logic...
        return true;
    }
}

// Client context class
class CheckoutProcessor
{
    private PaymentStrategyInterface $paymentStrategy;

    public function __construct(PaymentStrategyInterface $paymentStrategy)
    {
        $this->paymentStrategy = $paymentStrategy;
    }

    public function setPaymentStrategy(PaymentStrategyInterface $paymentStrategy): void
    {
        $this->paymentStrategy = $paymentStrategy;
    }

    public function executeCheckout(float $amount): bool
    {
        return $this->paymentStrategy->pay($amount);
    }
}
```

---

<a id="observer"></a>
## Le patron Observer (Observateur)

Le patron **Observer** définit une dépendance de type un-à-plusieurs entre objets de manière à ce que, lorsqu'un objet change d'état, tous ses dépendants en soient informés et mis à jour automatiquement.

```php
// app/Events/UserRegisterObserverInterface.php
declare(strict_types=1);

namespace App\Events;

interface UserRegisterObserverInterface
{
    public function update(string $email): void;
}

class SendWelcomeEmailObserver implements UserRegisterObserverInterface
{
    public function update(string $email): void
    {
        // Mailer logic to send welcome email...
    }
}

class CreatePromoCodeObserver implements UserRegisterObserverInterface
{
    public function update(string $email): void
    {
        // Database logic to generate a new user coupon...
    }
}

// Subject class holding references to observers
class UserRegistrationService
{
    private array $observers = [];

    public function attach(UserRegisterObserverInterface $observer): void
    {
        $this->observers[] = $observer;
    }

    public function registerUser(string $email): void
    {
        // Create user record in DB...
        
        // Notify observers
        foreach ($this->observers as $observer) {
            $observer->update($email);
        }
    }
}
```

---

<a id="chain-of-responsibility"></a>
## Le patron Chain of Responsibility (Chaîne de responsabilité)

Le patron **Chain of Responsibility** permet de transmettre des requêtes le long d'une chaîne de gestionnaires. À la réception d'une requête, chaque gestionnaire décide soit de la traiter, soit de la transmettre au gestionnaire suivant de la chaîne.

```php
// app/Http/Middleware/RequestHandlerInterface.php
declare(strict_types=1);

namespace App\Http/Middleware;

interface RequestHandlerInterface
{
    public function setNext(RequestHandlerInterface $handler): RequestHandlerInterface;
    public function handle(array $request): ?string;
}

abstract class AbstractRequestHandler implements RequestHandlerInterface
{
    private ?RequestHandlerInterface $nextHandler = null;

    public function setNext(RequestHandlerInterface $handler): RequestHandlerInterface
    {
        $this->nextHandler = $handler;
        return $handler;
    }

    public function handle(array $request): ?string
    {
        if ($this->nextHandler !== null) {
            return $this->nextHandler->handle($request);
        }
        return null;
    }
}

class AuthHandler extends AbstractRequestHandler
{
    public function handle(array $request): ?string
    {
        if (!isset($request['user_id'])) {
            return "401 Unauthorized";
        }
        return parent::handle($request);
    }
}

class RateLimitHandler extends AbstractRequestHandler
{
    public function handle(array $request): ?string
    {
        if (isset($request['request_count']) && $request['request_count'] > 100) {
            return "429 Too Many Requests";
        }
        return parent::handle($request);
    }
}
```

---

<a id="command"></a>
## Le patron Command (Commande)

Le patron **Command** permet de transformer une requête en un objet autonome qui contient toutes les informations sur cette requête. Cette transformation permet de passer des requêtes en tant qu'arguments de méthode, de retarder ou de planifier leur exécution, et de prendre en charge des opérations annulables.

```php
// app/Commands/CommandInterface.php
declare(strict_types=1);

namespace App\Commands;

interface CommandInterface
{
    public function execute(): void;
}

class BackupDatabaseCommand implements CommandInterface
{
    private string $databaseName;

    public function __construct(string $databaseName)
    {
        $this->databaseName = $databaseName;
    }

    public function execute(): void
    {
        // Logic to run backup shell scripts...
    }
}

// Invoker class
class CommandQueueExecutor
{
    private array $queue = [];

    public function addCommand(CommandInterface $command): void
    {
        $this->queue[] = $command;
    }

    public function runPendingCommands(): void
    {
        foreach ($this->queue as $command) {
            $command->execute();
        }
        $this->queue = [];
    }
}
```

---

<a id="others"></a>
## Autres patrons de comportement

- **Iterator (Itérateur)** : Permet de parcourir les éléments d'une collection sans exposer sa représentation sous-jacente (liste, pile, arbre, etc.).
- **Mediator (Médiateur)** : Limite les communications directes entre les objets et les force à collaborer uniquement via un objet médiateur.
- **Memento (Mémento)** : Permet de sauvegarder et de restaurer l'état précédent d'un objet sans violer le principe d'encapsulation.
- **State (État)** : Permet à un objet de modifier son comportement lorsque son état interne change. L'objet semblera changer de classe.
- **Template Method (Patron de méthode)** : Définit le squelette d'un algorithme dans une superclasse, mais laisse les sous-classes redéfinir certaines étapes sans modifier la structure globale.
- **Visitor (Visiteur)** : Permet de séparer les algorithmes des objets sur lesquels ils opèrent.
- **Interpreter (Interpréteur)** : Permet d'analyser et d'évaluer la grammaire ou les expressions d'un langage à l'aide d'une structure de classes dédiée.

---

<a id="common-mistakes"></a>
## Erreurs courantes

1. **Abuser du patron Observer** : Créer un trop grand nombre d'observateurs globaux d'événements. Cela engendre des effets de bord cachés et complique le suivi des actions lors du débogage.
2. **Couplage fort dans la Chaîne de responsabilité** : Coder en dur la séquence d'exécution directement au sein des gestionnaires au lieu de la configurer de manière externe.
3. **Choisir State au lieu de Strategy** : Ajouter des propriétés de gestion d'état à des objets de stratégie qui devraient normalement rester sans état et indépendants.

---

<a id="checklist"></a>
## Liste de contrôle

1. **Découplage :** Vos observateurs restent-ils totalement ignorants de la présence et des actions des autres observateurs ?
2. **Flexibilité :** Les stratégies peuvent-elles être interchangées dynamiquement au moment de l'exécution sans devoir réinitialiser l'objet de contexte ?
3. **Court-circuit :** Les gestionnaires de requêtes s'interrompent-ils immédiatement (short-circuit) lorsque les conditions de validation échouent ?
4. **Commande :** L'objet appelant (invoker) est-il complètement indépendant du récepteur réel (receiver) de la commande ?

---

<a id="summary"></a>
## Résumé

Les patrons de comportement régissent la façon dont les classes communiquent et partagent les responsabilités. Utilisez **Strategy** pour modifier à la volée les algorithmes centraux. Utilisez **Observer** pour concevoir des systèmes de notifications d'événements. Utilisez la **Chain of Responsibility** pour construire des flux de filtres (middlewares). Utilisez **Command** pour encapsuler les tâches sous forme d'objets dans des files d'attente.

---

<a id="self-test-quiz"></a>
## Quiz d'auto-évaluation

### Question 1 : Quel est le principal symptôme indiquant que vous devriez utiliser le patron Strategy ?
- A) Une seule méthode contient un bloc massif d'instructions conditionnelles `if-else` ou `switch-case` imbriquées sélectionnant différentes façons d'effectuer la même tâche.
- B) Vous devez écrire une méthode de sauvegarde qui enregistre les états de la base de données.
- C) Vous souhaitez mettre en cache dynamiquement les résultats des requêtes de base de données.

<details>
<summary><b>Afficher la réponse</b></summary>

**Réponse : A**
Lorsque vous observez plusieurs conditions sélectionnant des algorithmes différents (ex. `if ($provider === 'Stripe')` ou `else if ($provider === 'PayPal')`), cela indique un besoin évident du patron Strategy. Remplacer ces conditions par du polymorphisme produit un code propre respectant le principe ouvert/fermé.
</details>

### Question 2 : Dans la Chain of Responsibility, comment les gestionnaires décident-ils s'ils doivent transmettre la requête ?
- A) Chaque gestionnaire doit obligatoirement transmettre la requête, aucun court-circuit n'est autorisé.
- B) Un gestionnaire traite la requête et renvoie un résultat (ce qui rompt la chaîne) ou passe le contrôle au gestionnaire suivant en appelant sa méthode `handle()`.
- C) La séquence de la chaîne est tirée au sort à chaque requête.

<details>
<summary><b>Afficher la réponse</b></summary>

**Réponse : B**
Le concept clé de la Chaîne de responsabilité est que chaque gestionnaire décide dynamiquement s'il doit court-circuiter la requête (ex. échec de l'authentification) ou déléguer le traitement au gestionnaire suivant de la chaîne.
</details>
