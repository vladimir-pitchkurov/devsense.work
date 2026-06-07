---
title: "Bases de PHP & Variables Globales : Portée & Superglobales | DevSense"
description: "Un guide complet sur la portée des variables en PHP (locale, globale, statique) et sur toutes les superglobales. Apprenez les meilleures pratiques, les pièges de sécurité et les restrictions de PHP 8.1+."
faq:
  - question: "Quelle est la différence entre les portées locale, globale et statique des variables en PHP ?"
    answer: "Les variables locales n'existent qu'au sein de la fonction où elles sont déclarées. Les variables globales existent en dehors des fonctions et doivent être accédées via le mot-clé 'global' ou le tableau $GLOBALS. Les variables statiques existent localement mais conservent leur valeur à travers plusieurs exécutions de la fonction."
  - question: "Pourquoi la réassignation de la variable $GLOBALS échoue-t-elle en PHP 8.1+ ?"
    answer: "Depuis PHP 8.1, le moteur Zend empêche la réassignation du tableau $GLOBALS (par exemple, $GLOBALS = [] est interdit) afin d'optimiser la vitesse de recherche des variables et de garantir la cohérence interne. Vous pouvez toujours modifier des clés individuelles (par exemple, $GLOBALS['maVar'] = 'valeur')."
  - question: "Comment gérer de manière sécurisée les téléchargements de fichiers à l'aide de $_FILES en PHP ?"
    answer: "Vérifiez toujours le statut du téléchargement avec $_FILES['field']['error'], validez la taille et le type MIME du fichier côté serveur, vérifiez le fichier avec is_uploaded_file(), et déplacez-le vers un répertoire sûr en utilisant move_uploaded_file()."
  - question: "Pourquoi l'accès direct à $_GET ou $_POST est-il déconseillé dans le développement PHP moderne ?"
    answer: "L'accès direct aux superglobales couple votre code à l'état global, ce qui rend les tests unitaires et la simulation (mocking) des requêtes extrêmement difficiles. De plus, cela contourne le filtrage des données, augmentant ainsi le risque d'attaques XSS et d'injections SQL. Utilisez plutôt un wrapper de requête."
---

# PHP Basics: Variable Scopes and Superglobals

**Difficulty Level:** Junior  
**Target PHP Versions:** PHP 7.0+ (with features marked up to PHP 8.3+)

Imaginez que vous déployiez une refactorisation mineure du code, pour découvrir que votre environnement de production plante avec une exception fatale d'exécution : `Fatal error: Cannot reassign $GLOBALS`. Ou peut-être avez-vous écrit un simple formulaire de contact, pour vous rendre compte qu'un utilisateur malveillant a injecté un script XSS directement via `$_POST` et a détourné des sessions administrateur.

En PHP, la portée des variables et les superglobales constituent le socle du flux de données. Pourtant, elles demeurent une source fréquente de pollution de portée (scope pollution), de fuites de mémoire et de vulnérabilités de sécurité critiques pour les développeurs débutant avec ce langage. Comprendre comment les variables transitent entre les portées et comment se comportent les superglobales de PHP — en particulier sous les règles strictes des moteurs PHP modernes — est essentiel pour écrire un code sécurisé, testable et robuste.

---

## Variable Scopes: Local, Global, and Static

La portée (scope) définit la visibilité et le cycle de vie des variables dans votre code. En PHP, les variables ne se propagent pas automatiquement dans les portées imbriquées, sauf indication explicite.

### Local Scope
* **Point**: Les variables déclarées à l'intérieur d'une fonction ou d'une méthode de classe sont isolées de l'environnement extérieur.
* **Why it matters**: La portée locale empêche les collisions de noms et les mutations inattendues. Une variable nommée `$user` à l'intérieur d'une fonction ne peut pas écraser accidentellement une variable `$user` définie ailleurs.
* **Example**:
  ```php
  // app/Services/CalculationService.php
  
  function calculateTotal(float $price, float $tax): float
  {
      $total = $price + ($price * $tax); // Local variable
      return $total;
  }
  
  // Accessing $total here will throw a Warning (Undefined variable $total)
  ```
* **Consequence**: Une fois l'exécution de la fonction terminée, ses variables locales sont détruites et leur mémoire est libérée. Toute tentative d'y accéder depuis la portée globale génère un avertissement de variable non définie.

### Global Scope
* **Point**: Les variables définies en dehors de toute fonction ou classe appartiennent à la portée globale. Cependant, elles ne sont pas automatiquement accessibles à l'intérieur des fonctions.
* **Why it matters**: Les variables globales permettent de partager des configurations ou des connexions de base de données entre différents fichiers, mais y accéder au sein des fonctions nécessite l'utilisation de mots-clés explicites.
* **Example**:
  ```php
  // config/database.php
  
  $dbDsn = "mysql:host=127.0.0.1;dbname=app_db";
  
  function connect(): PDO
  {
      // Accessing global scope requires the 'global' keyword
      global $dbDsn; 
      
      return new PDO($dbDsn, "root", "secret");
  }
  ```
* **Consequence**: S'appuyer sur le mot-clé `global` introduit des dépendances cachées. Cela rend les tests unitaires extrêmement difficiles car le gestionnaire de tests ne peut pas facilement isoler ou simuler (mock) les valeurs globales.

### Static Scope
* **Point**: Une variable statique est déclarée à l'intérieur d'une fonction à l'aide du mot-clé `static`. Elle n'existe que dans la portée locale de cette fonction, mais elle conserve sa valeur entre les exécutions successives de la fonction.
* **Why it matters**: Les variables statiques sont idéales pour mettre en cache des calculs coûteux, suivre la profondeur de récursion ou générer des identifiants de séquence légers sans polluer l'espace de noms global.
* **Example**:
  ```php
  // app/Utils/SequenceGenerator.php
  
  function getNextSequenceId(): int
  {
      static $id = 0; // Initialized only on the first call
      $id++;
      return $id;
  }
  
  echo getNextSequenceId(); // 1
  echo getNextSequenceId(); // 2
  ```
  > [!NOTE]
  > **Note sur la version de PHP** : Depuis PHP 8.3+, les variables statiques peuvent être initialisées avec des expressions dynamiques (par exemple, en appelant d'autres fonctions). Avant PHP 8.3, elles ne pouvaient être initialisées qu'avec des valeurs constantes ou des littéraux.
* **Consequence**: La variable persiste en mémoire pendant toute la durée du processus PHP en cours. Si votre application s'exécute sous des environnements persistants (comme RoadRunner ou FrankenPHP), les variables statiques conserveront leur état d'une requête HTTP à l'autre, ce qui peut provoquer des fuites de mémoire ou mélanger des données utilisateur si elles ne sont pas réinitialisées.

---

## PHP Superglobals

Les superglobales sont des tableaux associatifs intégrés qui sont toujours disponibles dans toutes les portées tout au long du cycle de vie du script. Il n'est pas nécessaire d'utiliser le mot-clé `global` pour y accéder.

### 1. `$GLOBALS` (avec restrictions de réassignation PHP 8.1+)
* **Point**: Un tableau associatif contenant des références à toutes les variables actuellement définies dans la portée globale du script.
* **Why it matters**: Il fournit un accès programmatique et dynamique à la portée globale sans déclarer `global $varName`.
* **Example**:
  ```php
  // app/Utils/DebugHelper.php
  
  $debugMode = true;
  
  function isDebugEnabled(): bool
  {
      // Directly check the global variable through the superglobal array
      return $GLOBALS['debugMode'] ?? false;
  }
  ```
* **Restriction PHP 8.1+** :
  Avant PHP 8.1, le tableau `$GLOBALS` pouvait être réassigné ou passé par référence. Depuis PHP 8.1+, réassigner l'intégralité du tableau `$GLOBALS` (par exemple, `$GLOBALS = [];` ou `$GLOBALS =& $otherArray;`) est interdit et lève une erreur fatale à la compilation ou à l'exécution. Cette restriction a été introduite pour optimiser la vitesse de recherche des variables dans le moteur Zend. Vous pouvez toujours modifier des clés individuelles :
  ```php
  // app/Utils/LegacyRunner.php
  
  // This throws a Compile Error in PHP 8.1+:
  // $GLOBALS = ['app_env' => 'production']; 
  
  // This is fully supported and correct:
  $GLOBALS['app_env'] = 'production'; 
  ```
* **Consequence**: Les bases de code héritées qui réinitialisaient l'état global pendant le nettoyage des tests unitaires en utilisant `$GLOBALS = []` doivent être réécrites pour réinitialiser les variables clé par clé.

### 2. `$_SERVER`
* **Point**: Contient des en-têtes, des chemins, des emplacements de scripts et des variables d'environnement du serveur web.
* **Why it matters**: Essentiel pour le routage des requêtes, la vérification des verbes de requête (GET, POST) et la validation des en-têtes HTTP.
* **Example**:
  ```php
  // public/index.php
  
  $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
  $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  ```
* **Consequence**: Les valeurs préfixées par `HTTP_` (par exemple, `HTTP_USER_AGENT`, `HTTP_X_FORWARDED_FOR`) sont fournies directement par le navigateur du client. Elles ne doivent jamais être crues aveuglément car elles peuvent être facilement usurpées.

### 3. `$_GET`
* **Point**: Un tableau associatif de variables transmises au script via les paramètres de l'URL (query string).
* **Why it matters**: Utilisé pour transmettre des paramètres de navigation non sensibles tels que les décalages de pagination, les filtres et les termes de recherche.
* **Example**:
  ```php
  // app/Controllers/SearchController.php
  
  // URL: /search.php?query=php&page=2
  $query = $_GET['query'] ?? '';
  $page = (int)($_GET['page'] ?? 1); // Cast to int for safety
  ```
* **Consequence**: Les paramètres d'URL sont stockés dans l'historique du navigateur et dans les journaux du serveur web. Ne transmettez jamais d'identifiants sensibles (mots de passe, clés d'API ou jetons de réinitialisation) via `$_GET`.

### 4. `$_POST`
* **Point**: Un tableau associatif de variables transmises via HTTP POST (par exemple, des formulaires HTML ou des charges utiles `application/x-www-form-urlencoded`).
* **Why it matters**: Utilisé pour transmettre de grands ensembles de données, des métadonnées de fichiers et des données sensibles qui modifient l'état du serveur.
* **Example**:
  ```php
  // app/Controllers/RegistrationController.php
  
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      // PHP 8.0+ allows throw expressions with null coalescing:
      $email = $_POST['email'] ?? throw new InvalidArgumentException('Email is required');
      $password = $_POST['password'] ?? '';
  }
  ```
* **Consequence**: Si un utilisateur soumet un formulaire dont la taille dépasse le réglage `post_max_size` dans `php.ini`, PHP rejette silencieusement les données. Le tableau `$_POST` sera vide, et aucune erreur n'est levée automatiquement.

### 5. `$_SESSION`
* **Point**: Un tableau associatif contenant les variables de session disponibles pour le script actuel.
* **Why it matters**: Permet de conserver l'identité et l'état de l'utilisateur (comme les paniers d'achat ou le statut de connexion) à travers plusieurs requêtes HTTP sans état.
* **Example**:
  ```php
  // app/Services/AuthenticationService.php
  
  // PHP 7.0+ allows passing runtime options to session_start()
  session_start([
      'cookie_httponly' => true,
      'cookie_secure' => true,
      'cookie_samesite' => 'Lax',
  ]);
  
  $_SESSION['is_logged_in'] = true;
  $_SESSION['username'] = 'john_doe';
  ```
* **Consequence**: `session_start()` doit être appelé avant d'accéder à `$_SESSION`. Si les cookies de session ne sont pas configurés avec le drapeau `HttpOnly`, l'identifiant de session peut être volé via XSS, compromettant les comptes utilisateur.

### 6. `$_COOKIE`
* **Point**: Un tableau associatif contenant les cookies envoyés au serveur par le navigateur de l'utilisateur.
* **Why it matters**: Permet de lire des préférences côté client ou des jetons d'authentification de type "Se souvenir de moi".
* **Example**:
  ```php
  // app/Services/ThemeService.php
  
  $userTheme = $_COOKIE['preferred_theme'] ?? 'dark';
  ```
* **Consequence**: Les cookies sont stockés entièrement sur le poste client. Un utilisateur peut modifier manuellement les valeurs des cookies dans son navigateur. Ne faites jamais confiance aux valeurs de `$_COOKIE` pour des logiques critiques (comme `$_COOKIE['is_admin'] = 1`).

### 7. `$_ENV`
* **Point**: Un tableau associatif contenant les variables d'environnement transmises au processus PHP.
* **Why it matters**: Garde les identifiants sensibles (mots de passe de base de données, clés d'API de messagerie) sécurisés et séparés du code source.
* **Example**:
  ```php
  // app/Config/Database.php
  
  $dbPassword = $_ENV['DB_PASSWORD'] ?? 'default_password';
  ```
* **Consequence**: `$_ENV` peut être complètement vide si la directive `variables_order` dans `php.ini` ne contient pas le caractère `"E"` (par exemple si elle est configurée sur `"GPCS"`). Si `$_ENV` est vide, utilisez `getenv()` ou mettez à jour votre configuration.

### 8. `$_FILES`
* **Point**: Un tableau associatif contenant des métadonnées sur les fichiers téléchargés sur le serveur via HTTP POST.
* **Why it matters**: Permet un accès sécurisé aux propriétés du fichier téléchargé par le client (nom, type, taille, nom temporaire, code d'erreur) pour les valider et les déplacer.
* **Example**:
  ```php
  // app/Services/UploadService.php
  
  if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
      $tempPath = $_FILES['profile_pic']['tmp_name'];
      
      // Strict security validation
      if (is_uploaded_file($tempPath)) {
          $filename = uniqid('avatar_', true) . '.png';
          move_uploaded_file($tempPath, '/var/www/uploads/' . $filename);
      }
  }
  ```
* **Consequence**: Si les fichiers ne sont pas déplacés avec `move_uploaded_file()` pendant l'exécution de la requête, PHP supprime automatiquement le fichier temporaire du répertoire temporaire à la fin du script.

---

## Limitations, Trade-offs, and Clean Architecture

Bien que les variables globales et les superglobales soient intégrées à PHP pour en faciliter l'utilisation, les pratiques modernes de génie logiciel découragent leur utilisation directe dans la logique métier.

* **Lack of Testability**: L'accès direct à `$_GET`, `$_POST` ou `$_SERVER` couple votre classe de service directement à l'état global. Cela rend les tests unitaires impossibles car vous ne pouvez pas isoler le contexte d'exécution sans modifier les variables globales avant chaque test.
* **Security Exposure**: Les superglobales représentent des entrées client brutes et non fiables. Accéder directement à `$_POST['username']` au lieu d'utiliser un objet de transfert de données (DTO) validé augmente le risque d'injections SQL et de Cross-Site Scripting (XSS).
* **The Framework Alternative**: Les frameworks PHP modernes (tels que Laravel et Symfony) enveloppent les superglobales dans des objets Request et Response. Au lieu de lire `$_GET['id']`, vous injectez une instance de `Request` :
  ```php
  // app/Http/Controllers/UserController.php
  
  // Modern framework approach using Dependency Injection
  public function show(Request $request): Response
  {
      $userId = $request->query('id'); // Mockable and testable
      // ...
  }
  ```

---

## Practical Takeaways

1. **Minimize Scope**: Gardez les variables aussi locales que possible. Passez les variables aux fonctions sous forme d'arguments au lieu d'y accéder à l'aide du mot-clé `global`.
2. **Handle RoadRunner/FrankenPHP Lifecycles**: Si vous utilisez des variables statiques, nettoyez-les ou réinitialisez-les si votre environnement utilise des processus de travail (workers) persistants. Sinon, des fuites de mémoire ou des fuites de données entre utilisateurs se produiront.
3. **Use Request Wrappers**: Si vous écrivez du PHP natif (sans framework), créez une classe wrapper ou utilisez `filter_input()` au lieu d'accéder directement aux superglobales.
4. **Prepare for PHP 8.1+**: Assurez-vous de ne pas avoir de code de bibliothèque hérité qui réassigne ou transmet `$GLOBALS` par référence.

---

## Self-Check Quiz

Testez votre compréhension des portées PHP et des superglobales.

### Question 1: Quel est le résultat du code PHP suivant ?
```php
// app/Http/Controllers/TestController.php

$name = "Alice";

function greet(): string
{
    return "Hello, " . $name;
}

echo greet();
```
- A) `Hello, Alice`
- B) `Hello, ` accompagné d'un avertissement de variable non définie (Undefined Variable Warning)
- C) `Hello, $name`

<details>
<summary>Cliquez pour voir la réponse et les explications</summary>

**Réponse : B**  
Les variables définies dans la portée globale ne sont pas automatiquement visibles à l'intérieur des fonctions. Comme `$name` n'est pas déclarée à l'aide du mot-clé `global` ou accédée via `$GLOBALS['name']` à l'intérieur de `greet()`, PHP lève un avertissement de variable non définie et traite la variable comme `null`.
</details>

### Question 2: Quelle affirmation est vraie concernant la réassignation de `$GLOBALS` ?
- A) `$GLOBALS` peut être réassigné dans toutes les versions de PHP.
- B) Réassigner `$GLOBALS` (par exemple `$GLOBALS = [];`) déclenchera une erreur fatale à partir de PHP 8.1+.
- C) La modification de clés individuelles comme `$GLOBALS['user'] = 'Bob';` est obsolète et désactivée en PHP 8.1+.

<details>
<summary>Cliquez pour voir la réponse et les explications</summary>

**Réponse : B**  
À partir de PHP 8.1, vous ne pouvez plus réassigner le tableau `$GLOBALS` lui-même en raison d'optimisations dans la table des symboles du moteur Zend. Cependant, la modification de clés individuelles (comme `$GLOBALS['user'] = 'Bob'`) reste entièrement prise en charge et fonctionnelle.
</details>

### Question 3: Pourquoi devriez-vous éviter de faire confiance à `$_FILES['input_name']['type']` ?
- A) Il est toujours vide.
- B) Il est déterminé par PHP du côté serveur et est fréquemment incorrect.
- C) Il est envoyé par le navigateur (client) et peut être usurpé par un attaquant.

<details>
<summary>Cliquez pour voir la réponse et les explications</summary>

**Réponse : C**  
Le type MIME dans `$_FILES['...']['type']` est fourni directement par les en-têtes de requête du navigateur client. Un attaquant peut télécharger un script `.php` malveillant mais usurper l'en-tête pour indiquer `image/png`. Vérifiez toujours le type MIME côté serveur à l'aide de `finfo_file()` ou de l'extension `fileinfo`.
</details>
