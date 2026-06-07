---
title: "Bases de la POO en PHP : Des concepts fondamentaux à la visibilité asymétrique | DevSense"
description: "Maîtrisez la programmation orientée objet moderne en PHP. Apprenez l'encapsulation, l'héritage, les interfaces, les classes abstraites, les traits, les classes anonymes (7.0+), les propriétés readonly (8.1+), les classes readonly (8.2+) et la visibilité asymétrique (8.4+)."
faq:
  - question: "Quelle est la différence entre une interface et une classe abstraite en PHP ?"
    answer: "Une interface définit un contrat public pur que les classes implémentant doivent satisfaire, permettant des implémentations multiples. Une classe abstraite est une classe partiellement implémentée qui peut contenir un état, de la logique de constructeur et des méthodes protégées. Cependant, PHP ne prenant en charge que l'héritage simple, une classe ne peut étendre qu'une seule classe abstraite."
  - question: "Comment fonctionnent les propriétés readonly en PHP 8.1+ ?"
    answer: "Les propriétés readonly ne peuvent être initialisées qu'une seule fois, généralement dans le constructeur de la classe. Toute tentative ultérieure de modification ou de suppression (unset) de la propriété lèvera une exception Error, garantissant ainsi l'immutabilité."
  - question: "Qu'est-ce que la visibilité asymétrique en PHP 8.4+ ?"
    answer: "La visibilité asymétrique vous permet de déclarer différents niveaux d'accès pour la lecture et l'écriture d'une propriété. Exemple : 'public private(set) string $name' permet un accès public en lecture mais limite l'accès en écriture à la classe elle-même, éliminant le code répétitif des getters."
  - question: "Comment résout-on les collisions de noms de traits en PHP ?"
    answer: "Lorsque deux traits définissent une méthode avec le même nom, PHP lève une erreur fatale à moins que vous ne résolviez explicitement la collision en utilisant l'opérateur 'insteadof' pour choisir une méthode, ou l'opérateur 'as' pour créer un alias sous un nouveau nom."
---

# Bases de la POO en PHP : Protéger l'état et définir des interfaces propres

Si vous avez déjà passé des heures à déboguer une mystérieuse mutation d'état dans une grande application web, vous savez à quel point des objets mal encapsulés peuvent être fragiles. Un service modifie une propriété publique sur une instance partagée, et soudain, des entrées de base de données dans tout le système sont corrompues. Le problème fondamental n'est pas la programmation orientée objet (POO) elle-même, mais plutôt notre incapacité à imposer des limites strictes entre nos objets.

Dans le code procédural, les données circulent de manière globale, ce qui les rend vulnérables aux modifications. Dans une POO naïve, les classes sont traitées comme de simples sacs de propriétés, exposant tout via des variables publiques ou des getters et setters génériques. Le PHP moderne résout ces problèmes d'architecture en passant d'objets ouverts et mutables à des structures strictes et autonomes.

> [!IMPORTANT]
> La POO moderne en PHP ne consiste pas seulement à organiser des fonctions dans des classes ; il s'agit d'imposer des limites d'état strictes, de définir des contrats publics rigoureux et d'utiliser la sécurité des types pour empêcher les états d'exécution invalides.

**Niveau cible** : Junior / Middle

---

## Table des matières
* [Encapsulation & modificateurs d'accès](#encapsulation-modifiers)
* [Contrats & abstraction : Héritage, classes abstraites & interfaces](#contracts-abstraction)
* [Réutilisation horizontale & comportement dynamique : Traits & classes anonymes (PHP 7.0+)](#horizontal-reuse)
* [Immutabilité par défaut : Propriétés readonly (PHP 8.1+) & classes readonly (PHP 8.2+)](#immutability)
* [Limites fines : Visibilité asymétrique (PHP 8.4+)](#asymmetric-visibility)
* [Compromis architecturaux & limitations](#trade-offs)
* [🧠 Questions d'auto-évaluation](#self-check)

---

<a id="encapsulation-modifiers"></a>
## Encapsulation & modificateurs d'accès

### 1. Point
L'encapsulation est la pratique consistant à masquer l'état interne d'un objet et à exiger que toutes les interactions passent par une interface publique. PHP contrôle cet accès à l'aide de trois modificateurs :
* `public` : Accessible de n'importe où.
* `protected` : Accessible uniquement au sein de la classe elle-même et par les classes qui en héritent.
* `private` : Accessible uniquement au sein de la classe qui le définit.

### 2. Pourquoi c'est important
Sans encapsulation, des services externes peuvent modifier l'état interne d'un objet à son insu, contournant ainsi les règles de validation. Par exemple, si le solde d'un `BankAccount` peut être modifié directement depuis l'extérieur, nous ne pouvons pas garantir que le solde ne descendra jamais en dessous de zéro ou que les transactions seront correctement enregistrées dans le journal.

### 3. Exemple
Voyons comment l'exposition de propriétés publiques entraîne la corruption de l'état, et comment les modificateurs privés imposent des invariants :

```php
// app/Finance/BankAccount.php
namespace App\Finance;

use InvalidArgumentException;

class BankAccount
{
    // Private properties prevent direct external modification
    private float $balance = 0.0;
    private array $ledger = [];

    public function __construct(float $initialDeposit)
    {
        if ($initialDeposit < 0) {
            throw new InvalidArgumentException("Initial deposit cannot be negative.");
        }
        $this->balance = $initialDeposit;
        $this->ledger[] = "Account opened with: {$initialDeposit}";
    }

    // Public method provides controlled access to mutate state
    public function deposit(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Deposit amount must be positive.");
        }
        $this->balance += $amount;
        $this->ledger[] = "Deposited: {$amount}";
    }

    public function withdraw(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Withdrawal amount must be positive.");
        }
        if ($amount > $this->balance) {
            throw new InvalidArgumentException("Insufficient funds.");
        }
        $this->balance -= $amount;
        $this->ledger[] = "Withdrew: {$amount}";
    }

    // Getter provides read-only access without exposing mutable references
    public function getBalance(): float
    {
        return $this->balance;
    }
}
```

### 4. Conséquence
En déclarant `$balance` privé, la classe conserve le contrôle total sur sa validation. Le code externe ne peut pas exécuter `$account->balance = -500.0;`. Les transitions d'état ne peuvent se produire que par le biais d'opérations commerciales valides (`deposit` et `withdraw`), garantissant que le grand livre (ledger) et le solde sont toujours synchronisés.

---

<a id="contracts-abstraction"></a>
## Contrats & abstraction : Héritage, classes abstraites & interfaces

### 1. Point
PHP permet aux développeurs de concevoir des comportements réutilisables et des limites architecturales strictes grâce à l'héritage et au polymorphisme :
* **Héritage (`extends`)** : Permet à une classe enfant d'hériter des propriétés et des méthodes d'une classe parent. Les classes enfants peuvent surcharger les méthodes parentes, sauf si la méthode ou la classe parente est marquée `final`.
* **Classes abstraites (`abstract class`)** : Modèles qui ne peuvent pas être instanciés directement. Elles peuvent contenir des méthodes entièrement implémentées ainsi que des signatures de méthodes `abstract` que les enfants doivent implémenter.
* **Interfaces (`interface`)** : Contrats purs ne contenant que des signatures de méthodes sans implémentation. Une classe peut implémenter plusieurs interfaces, résolvant ainsi la limitation de l'héritage simple de PHP.

### 2. Pourquoi c'est important
L'héritage permet de partager une logique commune, mais il crée un couplage fort (tight coupling) entre le parent et l'enfant. Les classes abstraites agissent comme un juste milieu, offrant un plan partiel. Les interfaces représentent le niveau de découplage le plus élevé : elles définissent *ce que* un objet peut faire, et non *comment* il le fait. Cela vous permet d'échanger des adaptateurs de base de données, des pilotes de messagerie ou des passerelles de paiement sans modifier la logique applicative qui en dépend.

### 3. Exemple
Voici comment construire un contrat de traitement des paiements en utilisant une interface, une base abstraite et des implémentations concrètes :

```php
// app/Payments/PaymentGatewayInterface.php
namespace App\Payments;

use App\Payments\PaymentGatewayInterface;

interface PaymentGatewayInterface
{
    public function charge(int $amountInCents): bool;
}
```

```php
// app/Payments/AbstractPaymentGateway.php
namespace App\Payments;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    protected string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    // Shared utility method available to child classes
    protected function logTransaction(string $gateway, int $amount, bool $success): void
    {
        // Imagine writing this to a system log
        echo sprintf("[%s] Charged %d cents. Success: %s\n", $gateway, $amount, $success ? 'Yes' : 'No');
    }
}
```

```php
// app/Payments/StripeGateway.php
namespace App\Payments;

class StripeGateway extends AbstractPaymentGateway
{
    public function charge(int $amountInCents): bool
    {
        // Stripe-specific API implementation
        $success = true; // Call Stripe API using $this->apiKey
        
        $this->logTransaction('Stripe', $amountInCents, $success);
        return $success;
    }
}
```

### 4. Conséquence
Les classes de haut niveau (comme un `CheckoutController`) peuvent dépendre de `PaymentGatewayInterface` au lieu de classes concrètes. Nous pouvons remplacer `StripeGateway` par `PaypalGateway` via l'injection de dépendances (Dependency Injection), ou mocker entièrement la passerelle dans les tests PHPUnit, sans modifier la logique de commande.

---

<a id="horizontal-reuse"></a>
## Réutilisation horizontale & comportement dynamique : Traits & classes anonymes (PHP 7.0+)

### 1. Point
PHP est un langage à héritage simple. Pour éviter la duplication de code sans imposer de hiérarchies de classes complexes, PHP propose deux mécanismes :
* **Traits** : Blocs de code qui peuvent être insérés dans des classes pour partager des méthodes horizontalement. Si des conflits de noms surviennent entre deux traits, ils doivent être résolus à l'aide des opérateurs `insteadof` et `as`.
* **Classes anonymes (PHP 7.0+)** : Classes légères et sans nom déclarées à la volée. Elles sont utiles pour des implémentations simples et éphémères d'interfaces ou de classes abstraites.

### 2. Pourquoi c'est important
Imposer des hiérarchies de classes uniquement pour partager du code (comme la journalisation ou les suppressions logiques - soft deletes) conduit à des classes parentes fragiles et à des arbres d'héritage désordonnés. Les traits résolvent ce problème en permettant d'importer plusieurs comportements indépendants dans une seule classe. Les classes anonymes évitent le code répétitif (boilerplate) lorsque vous devez mocker une classe ou fournir une implémentation à usage unique pour un test ou un callback.

### 3. Exemple
Le code suivant démontre un trait avec résolution de collision et une classe anonyme utilisée pour la journalisation dans les tests :

```php
// app/Traits/LoggerTraits.php
namespace App\Traits;

trait LoggerA
{
    public function log(string $message): void
    {
        echo "LoggerA: {$message}\n";
    }
}

trait LoggerB
{
    public function log(string $message): void
    {
        echo "LoggerB: {$message}\n";
    }
}
```

```php
// app/Services/AuditService.php
namespace App\Services;

use App\Traits\LoggerA;
use App\Traits\LoggerB;

class AuditService
{
    use LoggerA, LoggerB {
        // Resolve conflict: Use LoggerA's log method instead of LoggerB's
        LoggerA::log insteadof LoggerB;
        // Keep LoggerB's log method accessible under an alias
        LoggerB::log as logFromB;
    }

    public function performAudit(): void
    {
        $this->log("Auditing system event..."); // Calls LoggerA::log
        $this->logFromB("Backup check...");     // Calls LoggerB::log
    }
}
```

```php
// app/Contracts/MailerInterface.php
namespace App\Contracts;

interface MailerInterface {
    public function send(string $recipient, string $body): void;
}
```

```php
// app/Services/MailerDemo.php
namespace App\Services;

use App\Contracts\MailerInterface;

// Creating a one-time class instance on the fly (PHP 7.0+)
$mockMailer = new class implements MailerInterface {
    public function send(string $recipient, string $body): void
    {
        echo "Mock sent to {$recipient} with body: {$body}\n";
    }
};
```

### 4. Conséquence
En utilisant des traits, `AuditService` acquiert des comportements partagés provenant de plusieurs sources sans avoir besoin d'étendre une classe de base commune. Avec les classes anonymes, nous pouvons instancier des implémentations en ligne de `MailerInterface` pour des tests rapides ou de petits scripts, évitant ainsi d'encombrer le système de fichiers avec des classes à usage unique.

---

<a id="immutability"></a>
## Immutabilité par défaut : Propriétés readonly (PHP 8.1+) & classes readonly (PHP 8.2+)

### 1. Point
PHP introduit des outils modernes pour imposer l'immutabilité des états au niveau du moteur (engine) :
* **Propriétés readonly (PHP 8.1+)** : Propriétés qui ne peuvent être écrites qu'une seule fois (généralement dans le constructeur). Les modifications ultérieures ou les tentatives de les supprimer avec `unset()` déclencheront une `Error`. Elles doivent être typées ; les propriétés non typées ne peuvent pas être marquées readonly.
* **Classes readonly (PHP 8.2+)** : Sucre syntaxique qui marque implicitement toutes les propriétés d'une classe comme `readonly` et empêche la création de propriétés dynamiques.

### 2. Pourquoi c'est important
Les objets de transfert de données (DTOs) et les paramètres de configuration traversent de nombreuses couches d'une application. Si ces structures sont mutables, n'importe quel service tout au long du cycle de vie de la requête pourrait accidentellement modifier leurs valeurs, entraînant des bugs silencieux. Imposer l'immutabilité au niveau du compilateur garantit qu'une fois un DTO construit, ses données restent identiques tout au long de l'exécution.

```php
// app/DTO/UserConfiguration.php
namespace App\DTO;

// PHP 8.2+ allows marking the entire class as readonly
readonly class UserConfiguration
{
    // All properties in a readonly class are implicitly readonly and must be typed
    public string $theme;
    public int $itemsPerPage;

    public function __construct(string $theme, int $itemsPerPage)
    {
        $this->theme = $theme;
        $this->itemsPerPage = $itemsPerPage;
    }
}
```

```php
// app/Services/ConfigConsumer.php
namespace App\Services;

use App\DTO\UserConfiguration;

$config = new UserConfiguration('dark', 25);

// Attempting to modify values:
// $config->theme = 'light'; 
// ❌ Fatal Error: Cannot modify readonly property App\DTO\UserConfiguration::$theme
```

### 4. Conséquence
Tout code consommant `$config` a la garantie que la configuration de l'utilisateur ne changera pas en cours de requête. Cela élimine le besoin de propriétés privées répétitives et de méthodes uniquement getters, simplifiant considérablement le code des DTO.

---

<a id="asymmetric-visibility"></a>
## Limites fines : Visibilité asymétrique (PHP 8.4+)

### 1. Point
PHP 8.4+ introduit la **Visibilité asymétrique**, qui vous permet de définir différents niveaux d'accès pour la lecture et l'écriture d'une propriété sur la même ligne.
* Syntaxe : `public private(set) Type $property`
* La visibilité en lecture (ex: `public`) est spécifiée en premier.
* La visibilité en écriture (ex: `private(set)` ou `protected(set)`) est spécifiée immédiatement après.

### 2. Pourquoi c'est important
Avant PHP 8.4, si vous vouliez qu'une propriété soit lisible publiquement mais modifiable uniquement à l'intérieur de la classe, vous deviez rendre la propriété `private` et écrire une méthode getter publique, ou rendre la classe/propriété `readonly`. Cependant, `readonly` empêche *toute* mutation interne après l'initialisation. La visibilité asymétrique permet à une classe de modifier ses propres propriétés en interne tout en les exposant en lecture seule à l'extérieur, sans aucun code répétitif de getter.

### 3. Exemple
Voici une entité `Product` dont le prix peut changer au fil du temps, mais uniquement via des méthodes de classe :

```php
// app/Catalog/Product.php
namespace App\Catalog;

use InvalidArgumentException;

class Product
{
    // Publicly readable, but can only be set privately from within this class (PHP 8.4+)
    public private(set) int $priceInCents;
    
    // Publicly readable, but can be set by this class and child classes (protected)
    public protected(set) string $name;

    public function __construct(string $name, int $priceInCents)
    {
        $this->name = $name;
        $this->setPrice($priceInCents);
    }

    public function setPrice(int $newPriceInCents): void
    {
        if ($newPriceInCents < 0) {
            throw new InvalidArgumentException("Price cannot be negative.");
        }
        $this->priceInCents = $newPriceInCents;
    }
}
```

```php
// app/Services/CatalogService.php
namespace App\Services;

use App\Catalog\Product;

$product = new Product('Mechanical Keyboard', 9900);

// Reading the property directly works without a getter
echo $product->priceInCents; // Output: 9900

// Writing directly fails:
// $product->priceInCents = 8500; 
// ❌ Fatal Error: Cannot modify property App\Catalog\Product::$priceInCents from global scope
```

### 4. Conséquence
Nous obtenons la syntaxe propre des propriétés publiques pour la lecture des valeurs, tout en maintenant une encapsulation stricte. Nous n'avons plus besoin d'écrire de code répétitif comme `public function getPriceInCents(): int { return $this->priceInCents; }`.

---

<a id="trade-offs"></a>
## Compromis architecturaux & limitations

Aucun modèle n'est une solution miracle. Appliquer des fonctionnalités POO sans évaluer leurs compromis conduit à des bases de code sur-conçues ou difficiles à maintenir.

### 1. Couplage fort par héritage (Le problème de la classe de base fragile)
Bien qu'étendre des classes soit facile, cela introduit un couplage fort. Si une classe parent (`AbstractPaymentGateway`) modifie la signature de son constructeur, chaque classe enfant doit être mise à jour. Préférez la **composition à l'héritage** en injectant des dépendances plutôt qu'en étendant des bases.

### 2. La magie cachée des traits
Les traits peuvent facilement masquer une mauvaise architecture. Ils s'apparentent à du copier-coller assisté par le compilateur, ce qui facilite la création de dépendances cachées et de collisions de méthodes. Si plusieurs classes ont besoin de la même logique, envisagez de créer une classe d'aide (helper) dédiée et de l'injecter comme dépendance.

### 3. Limitations des propriétés readonly
Les propriétés readonly (PHP 8.1+) ne peuvent pas être réinitialisées ou modifiées, même à l'intérieur de la classe. Si vous devez modifier l'état en interne (par exemple, pour suivre des changements d'état ou invalider du cache), les propriétés readonly ne fonctionneront pas. De plus, elles ne peuvent pas avoir de valeurs par défaut et ne peuvent pas être utilisées avec des hooks de propriété (property hooks en PHP 8.4+).

### 4. Contraintes de la visibilité asymétrique
La visibilité asymétrique (PHP 8.4+) exige que l'opération d'écriture (`set`) soit strictement plus restreinte que l'opération de lecture (`get`). Par exemple, `private public(set)` est invalide et lèvera une erreur de syntaxe. De plus, les propriétés avec visibilité asymétrique ne peuvent pas être passées par référence :

```php
// app/Services/ReferenceDemo.php
namespace App\Services;

use App\Catalog\Product;

$product = new Product('Mouse', 2500);

// If we try to pass by reference:
// sort($product->priceInCents); 
// ❌ Fatal Error: Cannot pass property App\Catalog\Product::$priceInCents by reference
```

---

## Conseils pratiques : L'arbre de décision de la visibilité

Lors de la définition des propriétés d'une classe, suivez cette règle générale pour maintenir votre encapsulation sécurisée et votre code propre :

1. **La propriété change-t-elle après l'instanciation du constructeur ?**
   * **Non** : Utilisez une propriété `readonly` (PHP 8.1+) ou une classe `readonly` (PHP 8.2+).
   * **Oui** : Passez à l'étape 2.
2. **Le code externe doit-il pouvoir modifier la valeur directement ?**
   * **Non** : Utilisez `public private(set)` (PHP 8.4+) pour exposer la valeur en lecture sans exposer l'accès en écriture.
   * **Oui** : Utilisez une propriété `public` standard (gardez à l'esprit que cela contourne toute validation).

---

## 🧠 Questions d'auto-évaluation

1. **Pourquoi PHP lève-t-il une erreur fatale (Fatal Error) si vous tentez de déclarer une propriété comme `private public(set)` en PHP 8.4+ ?**
2. **Vrai ou Faux ?** Une classe enfant peut surcharger une méthode dans la classe parent même si la méthode parent est préfixée par le mot-clé `final`.
3. **Que se passe-t-il si vous tentez de supprimer (unset) ou de réinitialiser une propriété `readonly` après qu'elle a déjà été définie ?**

<details>
<summary><b>Afficher les réponses</b></summary>

1. La visibilité en écriture (`set`) doit être aussi stricte ou plus stricte que la visibilité en lecture. Étant donné que `private(set)` est plus strict que `public`, `public private(set)` est valide. Cependant, `public(set)` est plus large que la visibilité en lecture `private`, ce qui est illogique et rejeté par l'analyseur syntaxique (parser).
2. **Faux.** Marquer une classe ou une méthode comme `final` empêche les classes enfants de surcharger cette méthode spécifique ou d'hériter de cette classe.
3. Cela lève une exception `Error` : *Cannot modify readonly property...* ou *Cannot unset readonly property...*.
</details>
