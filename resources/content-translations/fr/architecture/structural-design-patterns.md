---
title: "Patrons de structure GoF : Adapter, Decorator, Facade et Proxy | DevSense"
description: "Un guide approfondi sur les patrons de conception structurels du GoF en PHP : Adapter, Bridge, Composite, Decorator, Facade, Flyweight et Proxy avec des exemples concrets en PHP."
published: 2026-06-18
faq:
  - question: "Quelle est la différence clé entre les patrons Adapter et Decorator ?"
    answer: "Un Adapter modifie l'interface d'un objet existant pour la rendre compatible avec une autre interface, permettant à des classes normalement incompatibles de collaborer. Un Decorator conserve l'interface d'origine de l'objet enveloppé tout en ajoutant ou étendant dynamiquement ses comportements."
  - question: "En quoi le patron Facade diffère-t-il du patron Mediator ?"
    answer: "Une Facade fournit une interface simplifiée et unidirectionnelle vers un sous-système complexe sans restreindre l'accès direct à ses classes. Un Mediator centralise et coordonne la communication bidirectionnelle complexe entre plusieurs objets collègues, en masquant leurs relations directes."
  - question: "Quand doit-on utiliser un Proxy plutôt qu'un Decorator ?"
    answer: "Utilisez un Proxy lorsque vous devez contrôler l'accès à un objet (ex. initialisation tardive, mise en cache, contrôle d'accès) et que le proxy gère lui-même le cycle de vie de l'objet réel. Utilisez un Decorator lorsque vous souhaitez ajouter dynamiquement des responsabilités à un objet au moment de l'exécution sans gérer son cycle de vie d'instanciation."
---

# Patrons de structure GoF : Adapter, Decorator, Facade et Proxy

Les patrons de structure expliquent comment assembler des objets et des classes pour former des structures plus grandes, tout en garantissant que ces structures restent flexibles et efficaces. Ils permettent de construire des hiérarchies de classes complexes en privilégiant la composition à l'héritage direct.

**Guides connexes :** [De l'architecture monolithique aux microservices](monolith-to-microservices-architecture) · [Attaques web et prévention](web-attacks-and-prevention)

## Table des matières

* [Introduction aux patrons de structure](#intro)
* [Le patron Adapter (Adaptateur)](#adapter)
* [Le patron Decorator (Décorateur)](#decorator)
* [Le patron Facade (Façade)](#facade)
* [Le patron Proxy (Intermédiaire)](#proxy)
* [Autres patrons structurels (Bridge, Composite, Flyweight)](#others)
* [Erreurs courantes](#common-mistakes)
* [Liste de contrôle](#checklist)
* [Résumé](#summary)
* [Quiz d'auto-évaluation](#self-test-quiz)

---

<a id="intro"></a>
## Introduction aux patrons de structure

L'héritage est un outil puissant, mais il est statique et résolu au moment de la compilation. Les patrons de structure utilisent la **composition d'objets** pour combiner des comportements de manière dynamique à l'exécution. Cela offre une plus grande flexibilité et évite de créer des hiérarchies de classes trop profondes et rigides, difficiles à maintenir.

---

<a id="adapter"></a>
## Le patron Adapter (Adaptateur)

Le patron **Adapter** permet à des objets ayant des interfaces incompatibles de collaborer. Il agit comme un traducteur qui convertit les appels du code client dans un format compatible avec un sous-système tiers ou existant (legacy).

```php
// app/Logging/LoggerInterface.php
declare(strict_types=1);

namespace App\Logging;

interface LoggerInterface
{
    public function logMessage(string $message): void;
}

// Legacy third-party service that cannot be changed directly
class LegacyLoggerService
{
    public function sendRawLog(string $rawMessage, int $level): void
    {
        // Legacy system logging...
    }
}

// The Adapter class that bridges the two interfaces
class LoggerAdapter implements LoggerInterface
{
    private LegacyLoggerService $legacyLogger;

    public function __construct(LegacyLoggerService $legacyLogger)
    {
        $this->legacyLogger = $legacyLogger;
    }

    public function logMessage(string $message): void
    {
        // Map the call to the legacy service method
        $this->legacyLogger->sendRawLog($message, 1);
    }
}
```

---

<a id="decorator"></a>
## Le patron Decorator (Décorateur)

Le patron **Decorator** permet d'attacher dynamiquement de nouveaux comportements à un objet en le plaçant dans un objet enveloppe (wrapper) spécial contenant ces comportements.

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
        // Fetch product from SQL database...
        return ["id" => $id, "name" => "Core PHP Book"];
    }
}

// Decorator implementing the same interface
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
## Le patron Facade (Façade)

Le patron **Facade** propose une interface simplifiée vers une bibliothèque, un framework ou un ensemble complexe de classes.

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
## Le patron Proxy (Intermédiaire)

Le patron **Proxy** fournit un objet de substitution ou de contrôle d'accès pour l'objet réel. Il peut gérer l'initialisation paresseuse (lazy initialization), le contrôle d'accès, la mise en cache ou la journalisation.

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
        // Simulating heavy database computation
        usleep(50000);
    }

    public function render(): string
    {
        return "<h1>Heavy PDF Report Output</h1>";
    }
}

// Caching & Lazy Initialization Proxy
class HeavyReportProxy implements HeavyReportInterface
{
    private ?RealHeavyReport $realReport = null;
    private ?string $cachedHtml = null;

    public function render(): string
    {
        if ($this->cachedHtml === null) {
            if ($this->realReport === null) {
                // Instantiated only when requested
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
## Autres patrons structurels

- **Bridge (Pont)** : Sépare une abstraction de son implémentation pour qu'elles puissent varier indépendamment l'une de l'autre.
- **Composite** : Permet d'organiser les objets en structures arborescentes pour représenter des hiérarchies composants/composés, permettant aux clients de traiter uniformément les objets individuels et les compositions.
- **Flyweight (Poids mouche)** : Permet de stocker plus d'objets dans la mémoire vive en partageant l'état intrinsèque commun entre plusieurs objets au lieu de stocker toutes les données dans chaque instance.

---

<a id="common-mistakes"></a>
## Erreurs courantes

1. **Création d'une « Façade Dieu »** : Ajouter de la logique métier directement dans la classe Façade au lieu de la déléguer aux sous-systèmes. La Façade doit uniquement coordonner les étapes.
2. **Confondre Decorator et Adapter** : Tenter d'adapter une interface à l'aide d'un Décorateur, ou utiliser un Adaptateur pour ajouter un comportement dynamique.
3. **Empilement excessif de Décorateurs** : Empiler un trop grand nombre de Décorateurs sur un même objet, ce qui rend la trace d'appels difficile à déboguer.

---

<a id="checklist"></a>
## Liste de contrôle

1. **Interfaces :** Vos classes Adapter et Decorator implémentent-elles strictement des interfaces standard pour préserver le polymorphisme ?
2. **Découplage :** Le client utilisant votre Façade est-il bien découplé des classes internes du sous-système complexe ?
3. **Cycle de vie :** Votre Proxy gère-t-il correctement le cycle de vie de l'objet réel (création et libération) ?
4. **Compatibilité :** L'adaptateur convertit-il fidèlement les paramètres sans modifier le comportement métier d'origine ?

---

<a id="summary"></a>
## Résumé

Les patrons de structure s'appuient sur la composition d'objets pour concevoir des systèmes robustes et faciles à maintenir. Utilisez **Adapter** pour envelopper des interfaces incompatibles. Utilisez **Decorator** pour ajouter dynamiquement des responsabilités à des objets sans recourir à l'héritage. Utilisez **Facade** pour simplifier l'accès à une bibliothèque complexe. Utilisez **Proxy** pour gérer l'initialisation paresseuse, la sécurité ou la mise en cache.

---

<a id="self-test-quiz"></a>
## Quiz d'auto-évaluation

### Question 1 : Quel est le principal avantage de conception du patron Decorator par rapport à l'héritage ?
- A) Il permet de compiler le code plus rapidement et d'accélérer l'exécution.
- B) Il permet d'ajouter ou de modifier des comportements de manière dynamique à l'exécution, évitant ainsi l'explosion combinatoire de sous-classes à la compilation.
- C) Il garantit la sécurité des threads (thread safety) en PHP.

<details>
<summary><b>Afficher la réponse</b></summary>

**Réponse : B**
L'héritage est statique. Si vous souhaitez ajouter la mise en cache et la journalisation à un dépôt de données, l'héritage vous obligerait à créer des classes distinctes (ex. `CachedProductRepository`, `LoggedProductRepository`, `CachedAndLoggedProductRepository`). Les Décorateurs permettent de les combiner à la volée : `new Caching(new Logging(new SqlRepository()))`.
</details>

### Question 2 : Dans le patron Proxy, quelle est la relation entre la classe Proxy et la classe du Sujet Réel ?
- A) Le Proxy doit obligatoirement hériter de la classe du Sujet Réel.
- B) Le Proxy et le Sujet Réel doivent implémenter la même interface, permettant au Proxy de se substituer de manière transparente au Sujet Réel.
- C) La classe Proxy ne peut pas accéder directement aux méthodes de la classe du Sujet Réel.

<details>
<summary><b>Afficher la réponse</b></summary>

**Réponse : B**
Pour que les clients puissent utiliser le Proxy de manière interchangeable avec le Sujet Réel, les deux classes doivent implémenter la même interface. Cela rend le proxy totalement transparent pour le code client.
</details>
