---
title: "Vulnérabilités des applications web et leurs solutions : SQLi, injection de commandes, XSS, CSRF et IDOR | DevSense"
description: "Protégez vos applications PHP contre les vulnérabilités web courantes. Apprenez à prévenir l'injection SQL, l'injection de commandes, XSS, CSRF et IDOR grâce à des exemples de code sécurisés."
published: 2026-06-19
faq:
  - question: "Pourquoi les requêtes préparées sont-elles sécurisées contre les injections SQL ?"
    answer: "Les requêtes préparées envoient séparément le modèle de requête SQL et les données des paramètres au moteur de base de données. Le moteur compile d'abord la requête SQL, garantissant que les données des paramètres ne sont jamais analysées ou exécutées comme des commandes SQL, quels que soient les caractères qu'elles contiennent."
  - question: "Dans quel cas est-il sûr d'utiliser la directive Blade non échappée {!! $var !!} de Laravel ?"
    answer: "Il n'est sûr d'utiliser `{!! $var !!}` que lorsque la variable contient du HTML brut que vous avez vous-même généré ou purifié à l'aide d'une bibliothèque de nettoyage HTML sécurisée comme HTMLPurifier. Vous ne devez jamais afficher d'entrées utilisateur brutes à l'aide de cette directive."
  - question: "Comment la recherche limitée aux relations prévient-elle les IDOR ?"
    answer: "La recherche limitée aux relations (par exemple, `$user->orders()->findOrFail($id)`) garantit que la requête de base de données filtre naturellement les résultats selon l'identifiant de l'utilisateur authentifié. Un attaquant tentant d'accéder à l'identifiant d'un autre utilisateur recevra une erreur 404 Not Found, car l'enregistrement n'existe pas dans le cadre de la relation restreinte."
---

# Vulnérabilités des applications web et leurs solutions : SQLi, injection de commandes, XSS, CSRF et IDOR

Développer des applications web sécurisées ne consiste pas à ajouter de la sécurité à la fin du développement. Cela nécessite de comprendre comment les vulnérabilités apparaissent au niveau du code et de concevoir des barrières pour les prévenir. 

Dans ce guide, nous analyserons cinq vulnérabilités critiques des applications web (injection SQL, injection de commandes, Cross-Site Scripting ou XSS, CSRF et IDOR) en PHP et Laravel, verrons comment elles sont exploitées et implémenterons des solutions sécurisées.

**Guides connexes :** [Monolith to microservices architecture](monolith-to-microservices-architecture) · [Observability & monitoring](observability-monitoring-laravel)

## Sommaire

* [Injection SQL (SQLi)](#sql-injection)
* [Injection de commandes](#command-injection)
* [Cross-Site Scripting (XSS)](#xss)
* [Cross-Site Request Forgery (CSRF)](#csrf)
* [Références directes non sécurisées à des objets (IDOR)](#idor)
* [Erreurs courantes](#common-mistakes)
* [Checklist](#checklist)
* [Résumé](#summary)
* [Quiz d'auto-évaluation](#self-test-quiz)

---

<a id="sql-injection"></a>
## Injection SQL (SQLi)

L'**injection SQL** se produit lorsque des entrées utilisateur non fiables sont directement concaténées dans des chaînes de requête SQL. Cela permet aux attaquants de manipuler la structure de la requête, de contourner l'authentification, de lire des contenus sensibles de la base de données ou de supprimer des enregistrements.

### La mauvaise méthode : Concaténation de chaînes et tri non sécurisé

Ici, le développeur concatène les variables d'entrée directement dans la requête et utilise une entrée non nettoyée pour définir la colonne de tri.

```php
// app/Http/Controllers/ProductController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController
{
    public function search(Request $request): array
    {
        $search = $request->input('q');
        $sortBy = $request->input('sort', 'id');

        // VULNERABLE: SQL Injection via both search input and sorting column
        $sql = "SELECT * FROM products WHERE name LIKE '%{$search}%' ORDER BY {$sortBy} ASC";
        return DB::select($sql);
    }
}
```

### La bonne méthode : Requêtes préparées et liste d'autorisation de colonnes

Pour corriger cela, nous devons utiliser des **requêtes préparées (liaison de paramètres / parameter binding)** pour les valeurs des données de requête. Étant donné que les colonnes de tri ne peuvent pas être paramétrées, nous devons les valider par rapport à une **liste d'autorisation (allowlist)** stricte.

```php
// app/Http/Controllers/ProductController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController
{
    private const ALLOWED_SORT_COLUMNS = ['id', 'name', 'price', 'created_at'];

    public function search(Request $request): array
    {
        $search = $request->input('q', '');
        
        // Strict sorting column allowlist fallback
        $sortBy = in_array($request->input('sort'), self::ALLOWED_SORT_COLUMNS, true) 
            ? $request->input('sort') 
            : 'id';

        // SECURE: Parameter binding for data, strict validation for identifiers
        return DB::select(
            "SELECT * FROM products WHERE name LIKE :search ORDER BY {$sortBy} ASC",
            ['search' => "%{$search}%"]
        );
    }
}
```

---

<a id="command-injection"></a>
## Injection de commandes

L'**injection de commandes** se produit lorsque les entrées de l'utilisateur sont transmises directement à des fonctions système du shell telles que `exec()`, `shell_exec()` ou `system()`. Cela permet aux attaquants d'exécuter des commandes shell arbitraires sur le serveur avec les privilèges de l'utilisateur du serveur web.

### La mauvaise méthode : Exécution du shell avec concaténation de chaînes

Dans cet exemple, nous tentons de convertir un fichier PDF en une miniature PNG à l'aide d'un outil en ligne de commande, mais nous transmettons le nom de fichier directement au shell.

```php
// app/Services/ThumbnailGenerator.php
declare(strict_types=1);

namespace App\Services;

class ThumbnailGenerator
{
    public function generate(string $filename): string
    {
        $outputPath = "/tmp/" . uniqid('thumb_', true) . ".png";
        
        // VULNERABLE: Command injection if filename contains shell operators (e.g. "; rm -rf /;")
        $cmd = "pdftoppm -png -r 150 {$filename} {$outputPath}";
        shell_exec($cmd);

        return $outputPath;
    }
}
```

### La bonne méthode : Composant Process avec des tableaux d'arguments

N'invoquez jamais le shell directement et ne transmettez jamais d'arguments concaténés. Utilisez des bibliothèques d'encapsulation de processus (comme Symfony Process) qui gèrent l'échappement des arguments et leur exécution de manière sécurisée sans recourir à un environnement shell.

```php
// app/Services/ThumbnailGenerator.php
declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ThumbnailGenerator
{
    public function generate(string $filePath): string
    {
        $outputPath = "/tmp/" . uniqid('thumb_', true) . ".png";
        
        // SECURE: Arguments are passed as an array, bypassing the shell shell interpreter
        $process = new Process(['pdftoppm', '-png', '-r', '150', $filePath, $outputPath]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $outputPath;
    }
}
```

---

<a id="xss"></a>
## Cross-Site Scripting (XSS)

Le **Cross-Site Scripting (XSS)** se produit lorsqu'une application inclut des données utilisateur non fiables dans une page web sans échappement approprié. Le navigateur de l'attaquant exécute alors du code JavaScript malveillant, qui peut voler des cookies de session, enregistrer des saisies (keylogging) ou rediriger les utilisateurs.

Il existe trois types de XSS :
- **XSS réfléchi (Reflected XSS) :** Le script fait partie de la charge utile (payload) de la requête et est immédiatement renvoyé dans la réponse.
- **XSS stocké (Stored XSS) :** Le script est enregistré dans la base de données (par exemple, le texte d'un commentaire) et exécuté lorsque d'autres utilisateurs consultent la page.
- **XSS basé sur le DOM (DOM-based XSS) :** La vulnérabilité réside uniquement dans le JavaScript côté client qui utilise des variables du DOM non fiables.

### La mauvaise méthode : Affichage de contenu utilisateur non échappé

La syntaxe `{!! $var !!}` de Laravel affiche du HTML brut, contournant l'échappement XSS par défaut de Blade.

```blade
<!-- resources/views/profile.blade.php -->
<div class="user-bio">
    <!-- VULNERABLE: Stored XSS if bio contains <script>alert('xss')</script> -->
    {!! $user->bio !!}
</div>
```

### La bonne méthode : Échappement par défaut et purification du texte enrichi

Utilisez toujours `{{ $var }}` qui exécute automatiquement `e()` (`htmlspecialchars` de PHP) pour échapper la sortie. Si vous devez afficher du texte enrichi fourni par l'utilisateur, passez-le par une bibliothèque de nettoyage HTML sécurisée (comme HTMLPurifier).

```blade
<!-- resources/views/profile.blade.php -->
<div class="user-bio">
    <!-- SECURE: Automatically escaped by Blade -->
    {{ $user->bio }}
</div>

<div class="user-rich-content">
    <!-- SECURE: Outputting raw html only AFTER strict HTML purification -->
    {!! clean($user->rich_description) !!}
</div>
```

---

<a id="csrf"></a>
## Cross-Site Request Forgery (CSRF)

La **falsification de requête intersite (CSRF - Cross-Site Request Forgery)** force le navigateur d'un utilisateur authentifié à exécuter des actions qui modifient l'état (comme la modification d'un mot de passe ou l'initiation de paiements) sur une application dans laquelle il est actuellement connecté. Le navigateur associe automatiquement les cookies de session aux requêtes intersites, ce qui valide la requête.

### La mauvaise méthode : Actions modifiant l'état via des requêtes GET

Les requêtes GET doivent toujours être **idempotentes** (sûres à exécuter plusieurs fois sans modifier l'état). Effectuer des mises à jour sur des routes GET rend les attaques CSRF triviales.

```php
// routes/web.php
// VULNERABLE: Anyone can link to /profile/delete in an img tag to delete an account
Route::get('/profile/delete', [ProfileController::class, 'destroy']);
```

### La bonne méthode : Routes POST/DELETE avec jetons (tokens) CSRF

Utilisez toujours des méthodes HTTP modifiant l'état (POST, PUT, DELETE) et exigez un jeton secret et cryptographiquement sécurisé qui ne peut pas être deviné par des sites web externes.

```blade
<!-- resources/views/profile.blade.php -->
<!-- SECURE: Form triggers a POST request and generates a hidden CSRF token -->
<form action="{{ route('profile.destroy') }}" method="POST">
    @csrf
    @method('DELETE')
    <button type="submit">Delete Account</button>
</form>
```

---

<a id="idor"></a>
## Références directes non sécurisées à des objets (IDOR)

Une **IDOR (Insecure Direct Object Reference)** se produit lorsqu'une application expose une clé de base de données ou un identifiant de ressource directement aux utilisateurs, et ne vérifie pas si l'utilisateur demandeur a l'autorisation d'accéder à cette ressource spécifique.

### La mauvaise méthode : Recherche globale de requête sans autorisation

Dans cet exemple, n'importe quel utilisateur connecté peut accéder aux détails de la facture d'un autre utilisateur simplement en modifiant le `{id}` dans le paramètre de l'URL.

```php
// app/Http/Controllers/InvoiceController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController
{
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        // VULNERABLE: Retrieves invoice without checking user relationship or authorization
        $invoice = Invoice::findOrFail($id);
        
        return response()->json($invoice);
    }
}
```

### La bonne méthode : Requêtes limitées aux relations et autorisation par Gate

Découplez la récupération des ressources en les chargeant via le scope de la relation du modèle utilisateur, ou vérifiez les permissions à l'aide de Gates ou de Policies d'autorisation.

```php
// app/Http/Controllers/InvoiceController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvoiceController
{
    public function show(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        // SECURE Method A: Query scoped directly to the authenticated user
        $invoice = $request->user()->invoices()->findOrFail($id);

        // SECURE Method B: Global lookup but validated with a Laravel Policy
        // $invoice = Invoice::findOrFail($id);
        // Gate::authorize('view', $invoice);
        
        return response()->json($invoice);
    }
}
```

---

## Erreurs courantes

1. **Échapper au lieu de paramétrer :** Penser que l'utilisation de `addslashes()` ou de simples expressions régulières sur des variables protège les requêtes. Paramétrez toujours.
2. **Altérations d'état via GET :** Construire des points de terminaison API ou web qui effectuent des actions de suppression ou de mise à jour à l'aide de la méthode HTTP GET.
3. **Méga-passerelles (Mega-Gateways) contenant la validation :** Laisser la validation XSS/SQLi à des WAF (Web Application Firewalls) ou à des passerelles au lieu d'intégrer une validation robuste directement dans le contrôleur de l'application.
4. **IDOR sur les requêtes AJAX :** Sécuriser les routes principales des pages HTML mais exposer des identifiants de ressources non autorisés dans les points de terminaison JSON AJAX/API.

---

## Checklist

1. **Sécurité des requêtes :** Concaténez-vous des variables à l'intérieur d'instructions brutes `DB::select` ou `whereRaw` ?
2. **Exécution du shell :** Pouvez-vous remplacer les commandes shell par des bibliothèques PHP standard ? Si ce n'est pas le cas, les arguments sont-ils transmis dans un tableau ?
3. **Sortie Blade :** Utilisez-vous `{!! !!}` dans vos templates Blade sans validation par HTMLPurifier ?
4. **Contrôle d'autorisation :** Est-ce que chaque méthode de contrôleur chargeant une ressource vérifie si celle-ci appartient bien à l'utilisateur actuel ?

---

## Résumé

Les applications web sécurisées valident rigoureusement les entrées et appliquent une stratégie de défense en profondeur. Prévenez l'**injection SQL** en utilisant des requêtes préparées et des listes d'autorisation de colonnes. Évitez l'**injection de commandes** en transmettant des arguments sous forme de tableau à l'intérieur d'encapsulateurs de processus. Bloquez le **XSS** en échappant les sorties de variables à l'aide de moteurs de templates. Arrêtez le **CSRF** en limitant les modifications d'état aux requêtes POST/DELETE protégées par des jetons. Contrez les **IDOR** en limitant les recherches aux relations de l'utilisateur authentifié ou en exécutant des vérifications de politiques d'accès (policies).

---

## Quiz d'auto-évaluation

### Question 1 : Quelle est la principale différence entre l'échappement (escaping) et le paramétrage (parameterization) pour les requêtes SQL ?
- A) L'échappement s'exécute sur le serveur de base de données, tandis que le paramétrage est géré au sein du moteur PHP.
- B) L'échappement modifie les caractères spéciaux dans les chaînes de requête pour les rendre sûres, tandis que le paramétrage envoie la structure de la requête et les paramètres séparément au moteur de base de données.
- C) Le paramétrage n'est compatible qu'avec les bases de données PostgreSQL.

<details>
<summary>Cliquez pour voir la réponse</summary>

**Réponse : B**
L'échappement est un processus de manipulation de chaînes qui est sujet à des bugs d'analyse (parser bugs) et à des contournements. Le paramétrage est une fonctionnalité de protocole dans laquelle la structure SQL et les valeurs de données sont envoyées indépendamment, garantissant que les données ne peuvent jamais altérer le modèle de requête.
</details>

### Question 2 : Pourquoi les requêtes GET sont-elles vulnérables au CSRF même si elles sont protégées par des jetons (tokens) ?
- A) Les navigateurs ne prennent pas en charge les formulaires GET.
- B) Les requêtes GET exposent les jetons dans l'historique des URL, les journaux de navigation (browser logs) et les en-têtes Referer, et ne devraient de toute façon jamais être utilisées pour des opérations modifiant l'état.
- C) Les requêtes GET contournent automatiquement le middleware de vérification du jeton CSRF de Laravel.

<details>
<summary>Cliquez pour voir la réponse</summary>

**Réponse : B**
Les requêtes GET sont conçues pour être idempotentes (lectures sécurisées). Si une requête GET modifie l'état, des attaquants peuvent intégrer l'URL cible dans des liens intersites ou des balises d'image, déclenchant ainsi le changement d'état automatiquement à l'insu de l'utilisateur.
</details>

### Question 3 : Comment la limitation d'une requête de base de données à une relation utilisateur prévient-elle les IDOR ?
- A) Elle chiffre les clés primaires de la base de données.
- B) Elle bloque la requête au niveau du pare-feu (firewall).
- C) Elle garantit que la requête SQL filtre naturellement les enregistrements en utilisant l'identifiant de l'utilisateur authentifié comme contrainte de recherche, rendant les enregistrements des autres utilisateurs inaccessibles.

<details>
<summary>Cliquez pour voir la réponse</summary>

**Réponse : C**
Limiter les requêtes (par exemple, `$user->invoices()->findOrFail($id)`) ajoute une clause `WHERE user_id = ?` à la requête SQL. Si un attaquant tente de récupérer l'identifiant d'une ressource appartenant à quelqu'un d'autre, la requête retournera zéro enregistrement, générant ainsi une erreur 404 sécurisée.
</details>
