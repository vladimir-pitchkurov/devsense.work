---
title: "Patrons de création GoF : Singleton, Factory, Builder et Prototype | DevSense"
description: "Une exploration approfondie des patrons de création du GoF en PHP : Singleton, Factory Method, Abstract Factory, Builder et Prototype avec des types stricts et des exemples concrets."
published: 2026-06-18
faq:
  - question: "Pourquoi le patron Singleton est-il souvent considéré comme un anti-patron ?"
    answer: "Le Singleton est considéré comme un anti-patron car il introduit un état global dans l'application, couple fortement les classes à une ressource statique, complique les tests unitaires (en raison de la persistance de l'état d'un test à l'autre) et viole le principe de responsabilité unique."
  - question: "Quand devez-vous utiliser le patron Builder à la place d'une Factory ?"
    answer: "Utilisez le patron Builder lorsque la construction d'un objet implique un grand nombre de paramètres, une configuration complexe étape par étape, ou des étapes facultatives. Les Factories conviennent mieux pour des instanciations en une seule étape."
  - question: "Quelle est la différence entre Factory Method et Abstract Factory ?"
    answer: "Le Factory Method définit une interface pour créer un seul objet, déléguant l'instanciation aux sous-classes. L'Abstract Factory fournit une interface pour créer des familles d'objets apparentés ou dépendants sans spécifier leurs classes concrètes."
---

# Patrons de création GoF : Singleton, Factory, Builder et Prototype

Les patrons de création (creational design patterns) s'abstraient du processus d'instanciation. Ils rendent un système indépendant de la façon dont ses objets sont créés, composés et représentés. En encapsulant la connaissance des classes concrètes, ils masquent les détails de la création et de l'assemblage de ces instances.

**Guides connexes :** [De l'architecture monolithique aux microservices](monolith-to-microservices-architecture) · [Pooling de connexions de base de données en PHP](php-database-connection-pooling)

## Table des matières

* [Introduction aux patrons de création](#intro)
* [Le patron Singleton](#singleton)
* [Factory Method et Abstract Factory](#factory)
* [Le patron Builder](#builder)
* [Le patron Prototype](#prototype)
* [Erreurs courantes](#common-mistakes)
* [Aide-mémoire](#checklist)
* [Résumé](#summary)
* [Quiz d'auto-évaluation](#self-test-quiz)

---

<a id="intro"></a>
## Introduction aux patrons de création

La création directe d'objets à l'aide de l'opérateur `new` peut facilement encombrer la logique métier et introduit un couplage fort. Les patrons de création découplent le code client des détails d'instanciation concrets.

Dans le développement PHP moderne, le cycle de vie des objets est souvent géré par des conteneurs d'injection de dépendances (DI), mais la compréhension des patrons de création reste cruciale pour écrire des SDK personnalisés, des bibliothèques et des objets de domaine complexes.

---

<a id="singleton"></a>
## Le patron Singleton

Le **Singleton** garantit qu'une classe n'a qu'une seule instance et fournit un point d'accès global à celle-ci.

```php
// app/Database/DatabaseConnection.php
declare(strict_types=1);

namespace App\Database;

use RuntimeException;

class DatabaseConnection
{
    private static ?self $instance = null;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            if (empty($config)) {
                throw new RuntimeException("Database connection must be initialized with configuration.");
            }
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    // Empêcher le clonage et la désérialisation
    private function __clone() {}
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize a singleton class.");
    }

    public function query(string $sql): array
    {
        // Exécution de la requête SQL...
        return ["status" => "success", "sql" => $sql];
    }
}
```

> [!WARNING]
> **Attention pour les tests unitaires** : Les Singletons conservent leur état entre les tests unitaires. Si vous ne réinitialisez pas la propriété statique `$instance` entre les tests, vous risquez de casser l'isolation des tests.

---

<a id="factory"></a>
## Factory Method et Abstract Factory

### Factory Method
Le Factory Method définit une interface pour créer un objet, mais laisse les sous-classes décider de la classe à instancier.

```php
// app/Payments/PaymentGatewayFactory.php
declare(strict_types=1);

namespace App\Payments;

interface PaymentGateway
{
    public function charge(float $amount): bool;
}

class StripeGateway implements PaymentGateway
{
    public function charge(float $amount): bool
    {
        // Logique d'intégration Stripe...
        return true;
    }
}

abstract class PaymentProcessor
{
    abstract protected function createGateway(): PaymentGateway;

    public function process(float $amount): bool
    {
        $gateway = $this->createGateway();
        return $gateway->charge($amount);
    }
}

class StripeProcessor extends PaymentProcessor
{
    protected function createGateway(): PaymentGateway
    {
        return new StripeGateway();
    }
}
```

### Abstract Factory
L'Abstract Factory fournit une interface pour créer des familles d'objets apparentés ou dépendants.

```php
// app/UI/WidgetFactory.php
declare(strict_types=1);

namespace App\UI;

interface Button { public function render(): string; }
interface Input { public function render(): string; }

class DarkButton implements Button { public function render(): string { return "<button class='dark'>Submit</button>"; } }
class DarkInput implements Input { public function render(): string { return "<input class='dark' />"; } }

interface WidgetFactory
{
    public function createButton(): Button;
    public function createInput(): Input;
}

class DarkThemeFactory implements WidgetFactory
{
    public function createButton(): Button { return new DarkButton(); }
    public function createInput(): Input { return new DarkInput(); }
}
```

---

<a id="builder"></a>
## Le patron Builder

Le patron **Builder** sépare la construction d'un objet complexe de sa représentation, permettant au même processus de construction de créer des représentations différentes.

```php
// app/Http/RequestBuilder.php
declare(strict_types=1);

namespace App\Http;

class Request
{
    public string $url = '';
    public string $method = 'GET';
    public array $headers = [];
    public string $body = '';
}

class RequestBuilder
{
    private Request $request;

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): self
    {
        $this->request = new Request();
        return $this;
    }

    public function url(string $url): self
    {
        $this->request->url = $url;
        return $this;
    }

    public function method(string $method): self
    {
        $this->request->method = strtoupper($method);
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->request->headers[$name] = $value;
        return $this;
    }

    public function body(string $body): self
    {
        $this->request->body = $body;
        return $this;
    }

    public function build(): Request
    {
        $result = $this->request;
        $this->reset();
        return $result;
    }
}
```

---

<a id="prototype"></a>
## Le patron Prototype

Le patron **Prototype** spécifie les types d'objets à créer en utilisant une instance prototype, et crée de nouveaux objets en copiant ce prototype.

```php
// app/Marketing/CampaignTemplate.php
declare(strict_types=1);

namespace App\Marketing;

use DateTime;

class CampaignTemplate
{
    public string $title;
    public string $content;
    public DateTime $scheduledAt;

    public function __construct(string $title, string $content)
    {
        $this->title = $title;
        $this->content = $content;
        $this->scheduledAt = new DateTime();
    }

    public function __clone()
    {
        // Force le clonage de l'objet DateTime imbriqué
        $this->scheduledAt = new DateTime();
        $this->title = "Clone de " . $this->title;
    }
}
```

---

<a id="common-mistakes"></a>
## Erreurs courantes

1. **Surutilisation du Singleton** : Traiter les Singletons comme des variables globales. Cela couple la base de code, détruit l'isolation des tests et masque les dépendances.
2. **Sur-ingénierie des Factories** : Créer des Factories pour des objets qui peuvent être instanciés directement à l'aide d'un constructeur lorsqu'aucune variante de famille n'existe.
3. **Clonage superficiel dans le Prototype** : Oublier de cloner en profondeur les propriétés de type objet, ce qui fait que le clone partage son état avec le prototype d'origine.

---

<a id="checklist"></a>
## Aide-mémoire

1. **Encapsulation :** Avez-vous rendu le constructeur privé, la méthode clone privée et la méthode wakeup génératrice d'exception dans votre Singleton ?
2. **Complexité :** La configuration de votre objet nécessite-t-elle une configuration par étapes ou optionnelle ? Si oui, utilisez un Builder.
3. **Clonage :** Avez-vous veillé à ce qu'un clonage profond soit effectué dans votre classe Prototype si elle contient des objets imbriqués ?
4. **Découplage :** Utilisez-vous des interfaces pour les produits créés par vos Factories ?

---

<a id="summary"></a>
## Résumé

Les patrons de création vous aident à séparer l'instanciation d'un objet de la logique de l'application. Utilisez le **Singleton** uniquement lorsque vous devez garantir qu'il n'existe qu'une seule instance. Utilisez les **Factories** pour découpler le code client des implémentations de sous-classes concrètes. Utilisez le **Builder** pour l'assemblage par étapes d'objets complexes. Utilisez le **Prototype** lorsque la création d'instances est coûteuse en ressources et que le clonage d'une instance existante est plus efficace.

---

<a id="self-test-quiz"></a>
## Quiz d'auto-évaluation

### Question 1 : Quel est le principal risque structurel lié au fait de référencer une classe Singleton directement dans votre logique métier ?
- A) PHP plantera en raison d'un épuisement de la mémoire d'exécution.
- B) Cela introduit un couplage fort et un état global masqué, ce qui rend le code extrêmement difficile à tester de manière isolée.
- C) Cela déclenche une erreur de compilation fatale depuis PHP 8.0+.

<details>
<summary><b>Afficher la réponse</b></summary>

**Réponse : B**
Le fait de référencer des Singletons directement couple votre classe à un état global. Dans les tests unitaires, vous ne pouvez pas simuler cette connexion et l'état peut fuir d'un test à un autre, rendant vos tests instables.
</details>

### Question 2 : En PHP, que se passe-t-il lors du clonage d'un objet (`$b = clone $a`) si la classe contient des propriétés objets et ne définit pas de méthode `__clone()` personnalisée ?
- A) PHP clone automatiquement tous les objets imbriqués de manière récursive.
- B) PHP effectue une copie superficielle (shallow copy), ce qui signifie que les propriétés de type objet dans `$b` pointeront vers les mêmes instances que `$a`.
- C) Une erreur d'exécution est immédiatement levée.

<details>
<summary><b>Afficher la réponse</b></summary>

**Réponse : B**
Par défaut, le clonage en PHP effectue une copie superficielle. Toutes les propriétés de type objet imbriquées conserveront leur référence d'origine, donc la modification d'un objet imbriqué dans l'instance clonée affectera l'instance prototype.
</details>
