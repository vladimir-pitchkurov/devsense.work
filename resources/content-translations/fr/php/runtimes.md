---
title: "PHP on the server: FPM, Swoole, workers & event-loop runtimes | DevSense"
description: "Maîtrisez les environnements d'exécution PHP modernes : comparez PHP-FPM, les serveurs d'applications persistants (Swoole, RoadRunner, FrankenPHP) et les boucles d'événements ReactPHP/AMPHP."
faq:
  - question: "Quelle est la différence entre PHP-FPM et Swoole ?"
    answer: "PHP-FPM démarre un nouveau processus ou réutilise un processus propre pour chaque requête, libérant la mémoire à la fin. Swoole est un serveur d'applications persistant qui charge le code une seule fois au démarrage et traite les requêtes suivantes directement en mémoire, ce qui améliore considérablement les performances mais conserve l'état global."
  - question: "Qu'est-ce qu'une fuite de mémoire (memory leak) dans les environnements PHP persistants ?"
    answer: "Puisque les processus ne s'arrêtent pas après chaque requête, les propriétés statiques, les singletons ou les variables globales accumulant des données vont grossir indéfiniment en mémoire, provoquant à terme le dépassement de la limite de mémoire et le crash du processus worker."
  - question: "Comment RoadRunner gère-t-il les workers PHP ?"
    answer: "RoadRunner est écrit en Go et agit comme un répartiteur de charge (load balancer) et un gestionnaire de processus. Il communique avec des processus workers PHP persistants via le protocole Goridge, déléguant la gestion HTTP, gRPC et des files d'attente à l'externe."
  - question: "Pourquoi les fonctions bloquantes paralysent-elles les boucles d'événements de ReactPHP ou d'AMPHP ?"
    answer: "ReactPHP et AMPHP utilisent des boucles d'événements mono-thread. Si vous appelez une fonction bloquante (comme sleep() ou une requête de base de données synchrone), le thread s'arrête complètement, figeant le serveur pour toutes les autres connexions simultanées."
---

# PHP sur le serveur : FPM, Swoole, Workers & Event Loops

Beaucoup de développeurs pensent encore que PHP s'exécute uniquement selon un modèle mono-thread et éphémère de type \"share-nothing\", où les variables disparaissent après chaque rechargement de page. Pourtant, en production, PHP gère aujourd'hui des connexions WebSockets à forte concurrence, traite des milliers de requêtes par seconde sur un seul thread grâce à des workers pilotés par Go, et fait tourner des boucles d'événements asynchrones. Si vous déployez un environnement d'exécution PHP moderne en présumant du cycle classique requête/réponse, une simple fuite de mémoire dans une propriété statique peut faire crasher l'ensemble de votre infrastructure de production.

Avec l'émergence de Swoole, RoadRunner, FrankenPHP et ReactPHP, PHP s'est affranchi des limites de PHP-FPM. Cependant, les environnements d'exécution persistants apportent de nouveaux risques—en particulier les fuites de mémoire, la contamination de l'état partagé et les appels d'E/S bloquants qui paralysent la boucle d'événements.

> [!IMPORTANT]
> Le PHP moderne n'est plus limité à un seul modèle d'exécution ; choisir le bon environnement implique de comprendre le fonctionnement des requêtes éphémères, des workers persistants et des boucles d'événements asynchrones.

---

## Table des matières
* [PHP-FPM (FastCGI Process Manager)](#php-fpm)
* [Serveurs d'applications persistants (Swoole & Laravel Octane)](#swoole)
* [RoadRunner (Superviseur écrit en Go)](#roadrunner)
* [FrankenPHP (Mode worker basé sur Caddy)](#frankenphp)
* [Boucles d'événements (ReactPHP & AMPHP)](#event-loop)
* [Fuites de mémoire : le tueur silencieux](#memory-leaks)
* [Erreurs courantes](#common-mistakes)
* [Recettes pratiques](#practical-recipes)
* [🧠 Questions d'auto-évaluation](#self-check)

---

<a id="php-fpm"></a>
## PHP-FPM (FastCGI Process Manager)

PHP-FPM est le gestionnaire de processus classique et éprouvé. Nginx gère le trafic HTTP et transmet les requêtes `.php` aux workers FPM via un socket Unix ou TCP.

```nginx
# /etc/nginx/sites-available/default
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

* **Le fonctionnement** : chaque processus worker traite une seule requête, vide le tampon de sortie (flush), réinitialise ses variables et retourne dans le pool.
* **Pourquoi c'est important** : aucun risque d'accumulation de fuites de mémoire d'une requête à l'autre. Si un script échoue, le processus est recyclé proprement.
* **Limites** : coût d'initialisation élevé (ex : charger Laravel/Symfony à partir du disque à chaque requête).

---

<a id="swoole"></a>
## Serveurs d'applications persistants (Swoole & Laravel Octane)

Swoole est une extension C qui transforme PHP en un serveur réseau haute performance grâce aux coroutines.

```php
// app/Servers/HttpServer.php
$server = new Swoole\Http\Server("127.0.0.1", 9501);
$server->on("request", function ($request, $response) {
    $response->header("Content-Type", "text/plain");
    $response->end("Hello World");
});
$server->start();
```

* **Le fonctionnement** : le code PHP est chargé en mémoire **une seule fois** au démarrage. Les requêtes suivantes sont gérées par des workers résidant en mémoire.
* **Pourquoi c'est important** : multiplie le débit par 10 en éliminant le coût d'initialisation du framework.
* **Limites** : toute fuite de mémoire au sein des singletons ou des variables statiques persistera et grossira jusqu'à ce que le processus crashe.

---

<a id="roadrunner"></a>
## RoadRunner (Superviseur écrit en Go)

RoadRunner est un serveur d'applications, gestionnaire de processus et répartiteur de charge haute performance pour PHP, écrit en Go.

```yaml
# .rr.yaml
server:
  command: "php worker.php"
http:
  address: "0.0.0.0:8080"
  pool:
    num_workers: 4
```

* **Le fonctionnement** : Go prend en charge les requêtes HTTP entrantes, les connexions WebSockets et les points d'accès gRPC, puis les transmet aux processus PHP persistants via le protocole binaire Goridge.
* **Pourquoi c'est important** : allie la gestion des processus et la concurrence de Go à la simplicité de développement de PHP.

---

<a id="frankenphp"></a>
## FrankenPHP (Mode worker basé sur Caddy)

FrankenPHP est un serveur d'applications moderne pour PHP built au-dessus du serveur web Caddy. Il intègre un \"mode worker\" qui maintient votre application chargée en mémoire.

* **Pourquoi c'est important** : déploiement simple sous forme de binaire unique. Il prend en charge HTTP/1.1, HTTP/2, HTTP/3 et la gestion automatique du TLS en natif.

---

<a id="event-loop"></a>
## Boucles d'événements (ReactPHP & AMPHP)

Contrairement à Swoole, qui repose sur une extension en C, ReactPHP et AMPHP implémentent des boucles d'événements en pur PHP grâce au multitâche coopératif.

```php
// app/Servers/ReactServer.php
require __DIR__ . '/vendor/autoload.php';

$loop = React\EventLoop\Loop::get();
$loop->addPeriodicTimer(1.0, function () {
    echo "Tick\n";
});
$loop->run();
```

* **Pourquoi c'est important** : idéal pour les microservices gourmands en E/S, les gestionnaires de WebSockets et les serveurs proxy.
* **Limites** : vous ne pouvez pas exécuter de code bloquant (comme les opérations PDO classiques ou `file_get_contents`). Tous les pilotes de base de données et clients HTTP doivent être non bloquants.

---

<a id="memory-leaks"></a>
## Fuites de mémoire : le tueur silencieux

Dans les environnements d'exécution persistants (Swoole, RoadRunner, mode worker FrankenPHP, scripts CLI d'arrière-plan), la mémoire n'est pas libérée automatiquement après une requête.

```php
// app/Services/DataLeak.php
class DataLeak
{
    private static array $cache = [];

    public function cacheRequest(array $data)
    {
        // ❌ Fuite de mémoire ! Ce tableau grossit indéfiniment à chaque requête
        self::$cache[] = $data; 
    }
}
```

> [!NOTE]
> Pour prévenir les fuites de mémoire, vous devez limiter les caches en mémoire (par exemple à l'aide d'algorithmes d'éviction LRU), éviter d'utiliser des propriétés statiques pour stocker des états propres aux requêtes, et surveiller la mémoire avec `memory_get_usage(true)`.

---

<a id="common-mistakes"></a>
## ⚠️ Erreurs courantes

### 1. Bloquer la boucle d'événements dans ReactPHP / AMPHP
Appeler une fonction bloquante synchrone suspend la boucle d'événements, figeant tout le serveur pour tous les autres utilisateurs.

```php
// app/Http/Handler.php
// ❌ Dangereux : interrompt l'exécution de toutes les requêtes simultanées
$data = file_get_contents("http://external-api.com/data");

// ✅ Approche correcte
// Utilisez des clients HTTP asynchrones :
$browser = new React\Http\Browser();
$browser->get("http://external-api.com/data")->then(function ($response) {
    // Traitement asynchrone de la réponse
});
```

### 2. Modifier des propriétés statiques de classe pour des requêtes utilisateur
Stocker les informations de session ou d'authentification d'un utilisateur dans des propriétés statiques entraînera la fuite de ces données vers d'autres requêtes concurrentes.

```php
// app/Services/AuthService.php
// ❌ Dangereux : l'utilisateur 2 pourrait voir l'identité de l'utilisateur 1 !
class AuthService
{
    public static ?User $currentUser = null;
}
```

---

<a id="practical-recipes"></a>
## Recettes pratiques

### Recycler les workers en toute sécurité
Pour limiter l'impact des fuites de mémoire en production, configurez votre gestionnaire de processus pour redémarrer les workers après qu'ils ont traité un nombre défini de requêtes ou atteint un certain seuil de mémoire.

```ini
# /etc/php/8.3/fpm/pool.d/www.conf (PHP-FPM)
; Recycle les workers après 500 requêtes pour nettoyer les fuites mineures
pm.max_requests = 500
```

```yaml
# .rr.yaml (RoadRunner)
http:
  pool:
    # Détruit les workers lorsqu'ils dépassent 100 Mo de RAM ou 1000 tâches traitées
    max_jobs: 1000
    allocate_timeout: 60s
    destroy_timeout: 60s
    supervisor:
      watch_interval: 1s
      max_worker_memory: 100 # Mo
```

---

## 🧠 Questions d'auto-évaluation

1. **Vrai ou Faux ?** En PHP-FPM, les variables statiques provoquent des fuites de mémoire d'une requête HTTP à l'autre.
2. Que se passe-t-il si vous exécutez `sleep(5)` dans un gestionnaire de requêtes ReactPHP ?
3. Comment le mode worker de FrankenPHP parvient-il à améliorer les performances par rapport à PHP-FPM ?
4. Quel est le rôle principal de la directive `pm.max_requests` dans PHP-FPM ?

<details>
<summary><b>Afficher les réponses</b></summary>

1. **Faux.** Dans PHP-FPM, toute la mémoire du processus est libérée et réinitialisée entre les requêtes (ou le processus est recyclé), ce qui signifie que les variables statiques ne persistent pas d'une requête à l'autre.
2. Cela bloque la boucle d'événements mono-thread pendant 5 secondes. Durant ce laps de temps, le serveur ne peut accepter ni répondre à aucune autre connexion.
3. Il charge l'application PHP et les bibliothèques du framework en mémoire une seule fois, évitant ainsi le chargement des fichiers depuis le disque et les phases de compilation lors des requêtes suivantes.
4. Redémarrer le processus worker PHP-FPM après qu'il a traité un nombre configuré de requêtes, évitant ainsi que les fuites de mémoire dans les extensions ou le code tiers ne saturent la mémoire du serveur.
</details>
