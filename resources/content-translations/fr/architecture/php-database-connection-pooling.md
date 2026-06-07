---
title: "Les applications PHP et le goulot d'étranglement du pool de connexions à la base de données | DevSense"
description: "Pourquoi PHP-FPM et les workers multiplient les sessions de base de données, comment les répartiteurs (poolers) et proxies de niveau intermédiaire partagent les connexions réelles du serveur, et ce que les équipes Laravel doivent savoir sur les modes PgBouncer, ProxySQL et les requêtes préparées."
published: 2026-04-14
faq:
  - question: "Quelle est la principale différence entre le regroupement de sessions (Session pooling) et le regroupement de transactions (Transaction pooling) dans PgBouncer ?"
    answer: "Le regroupement de sessions attribue une connexion serveur à un client pendant toute la durée de sa connexion, ne la libérant que lorsque le client se déconnecte. Le regroupement de transactions libère la connexion serveur vers le pool immédiatement après chaque transaction (`COMMIT` ou `ROLLBACK`). Le regroupement de transactions permet des ratios client/serveur beaucoup plus élevés, mais casse l'état au niveau de la session comme les tables temporaires, les verrous consultatifs (advisory locks) et les paramètres persistants."
  - question: "Pourquoi les requêtes préparées échouent-elles parfois lors de l'utilisation du regroupement de transactions ?"
    answer: "Dans le cas du regroupement de transactions, les requêtes séquentielles au cours d'une même session client peuvent être acheminées vers différents backends de serveur de base de données. Si la requête client A prépare une requête sur le backend 1, et que la requête B tente d'exécuter cette requête préparée sur le backend 2, l'exécution échouera car le backend 2 n'en a pas connaissance."
  - question: "Comment le modèle de processus de PHP-FPM affecte-t-il les connexions à la base de données par rapport à Node.js ou Go ?"
    answer: "PHP-FPM exécute un modèle de processus par requête, où chaque processus enfant gère une requête à la fois et ferme généralement les ressources à la fin de la requête. Dans les systèmes à fort trafic, cela conduit à une 'tempête de connexions' (handshakes TCP et authentifications répétés). À l'inverse, Node.js et Go utilisent des environnements d'exécution asynchrones à processus unique qui maintiennent un pool unique à longue durée de vie de connexions de base de données partagées entre des milliers de requêtes simultanées."
  - question: "Le regroupement de connexions résout-il les requêtes de base de données lentes ?"
    answer: "Non. Le regroupement de connexions résout uniquement la surcharge liée à l'établissement des connexions et évite de dépasser les limites de connexion sur le serveur de base de données. Il n'accélère pas l'exécution de requêtes SQL lentes, ne résout pas les index manquants et ne réduit pas la charge du processeur ou du disque causée par des requêtes non optimisées."
---

# Les applications PHP et le goulot d'étranglement de la base de données : poolers, proxies et réalité

Dans de nombreuses architectures, la base de données est suffisamment rapide et les requêtes sont raisonnables — pourtant, la production subit toujours des erreurs du type **`too many connections`**, **`remaining connection slots are reserved`** ou des **ralentissements mystérieux** juste après un déploiement. Le coupable n'est souvent pas des requêtes SQL lentes mais l'**arithmétique des connexions** : le modèle de requête de PHP crée des **pics de connexion + authentification + TLS**, et la base de données a un **plafond strict** sur les backends simultanés. Les **répartiteurs de connexions (poolers)** de niveau intermédiaire et les **proxies gérés** existent précisément pour placer un **ensemble restreint et stable de sessions côté serveur** derrière une **large flotte de clients PHP à courte durée de vie**.

**Guides associés :** [Databases under load: queries & scaling](database-performance-and-scaling) · [Observability and monitoring](observability-monitoring-laravel)

## Table des matières

* [Pourquoi PHP amplifie le problème](#why-php)
* [Par quoi vous êtes réellement limité](#limits)
* [Poolers et proxies de niveau intermédiaire](#middle-tier)
* [PostgreSQL : PgBouncer en pratique](#pgbouncer)
* [MySQL et MariaDB : ProxySQL et assimilés](#proxysql)
* [Proxies gérés (RDS Proxy, autres)](#managed)
* [Autres poolers : PgCat, Odyssey, pgpool-II](#other-tools)
* [Notes spécifiques à Laravel](#laravel)
* [Ce que les poolers ne résolvent *pas*](#not-a-cure)
* [Erreurs courantes](#common-mistakes)
* [Liste de contrôle](#checklist)
* [Quiz d'auto-évaluation](#self-test-quiz)

---

<a id="why-php"></a>
## Pourquoi PHP amplifie le problème

Le traditionnel **PHP-FPM** exécute une requête, communique avec les services, renvoie une réponse et libère les ressources associées à la requête. À moins d'utiliser des **connexions persistantes** (et d'en accepter les compromis), chaque requête qui touche à la base de données **ouvre ou récupère** généralement une session TCP, **s'authentifie**, négocie éventuellement le **TLS**, puis exécute les requêtes.

Sous charge :

* **`pm.max_children`** sur FPM définit le nombre de processus PHP qui peuvent s'exécuter **en même temps** sur cette machine. Si la plupart des requêtes touchent la base de données, vous pouvez avoir besoin de **jusqu'à ce nombre** de sessions de base de données simultanées **par machine de worker**.
* **Les workers de file d'attente** (`queue:work`, Horizon) sont des **processus à longue durée de vie** — chaque worker simultané détient souvent **une ou plusieurs** connexions ouvertes pendant l'exécution des tâches.
* **La mise à l'échelle horizontale** multiplie le tout : trois nœuds d'application avec quatre-vingts enfants chacun représentent **deux cent quarante** sessions potentielles avant même de compter les workers, les planificateurs et les tâches CLI ponctuelles.

La base de données fait face à des **tempêtes de connexion (connection storms)** lors des déploiements et des pics de trafic : des centaines de négociations (handshakes) en quelques secondes. Même lorsque `max_connections` est suffisamment élevé, la **mémoire par backend** (particulièrement avec Postgres) et le **processeur requis pour l'authentification** deviennent les véritables limites.

---

<a id="limits"></a>
## Par quoi vous êtes réellement limité

* **`max_connections` (Postgres)** / **`max_connections` (MySQL)** — un plafond global. Les emplacements réservés pour les superutilisateurs et la réplication peuvent réduire ce dont disposent les applications.
* **Mémoire** — chaque backend de serveur transporte des tampons et des états ; « simplement augmenter la limite » peut provoquer un crash par manque de mémoire (**OOM**) de l'instance.
* **Latence de connexion** — TLS + vérification du mot de passe + LDAP optionnel ajoutent des **millisecondes à des dizaines de millisecondes** par requête si vous vous connectez à chaque fois.
* **Effet de troupeau (Thundering herd)** — après un redémarrage, chaque processus PHP peut essayer de se connecter **en même temps**, saturant la file d'attente d'acceptation ou le chemin d'authentification.

> [!NOTE]
> **Arithmétique de la charge globale**
> Règle générale : comptez **tous** les programmes qui parlent SQL (web, workers, cron, outils d'administration, BI), et pas seulement le protocole HTTP. Chaque environnement s'ajoute à l'empreinte globale de la base de données.

---

<a id="middle-tier"></a>
## Poolers et proxies de niveau intermédiaire

Un **répartiteur (pooler)** se situe **entre** PHP et la base de données. PHP ouvre une connexion légère **vers le pooler** ; le pooler maintient un **pool plus restreint** de connexions réelles vers Postgres/MySQL et les **réutilise** à travers de nombreux clients.

### Avantages
* Moins de **backends de serveur** et moins de **RAM** sur l'hôte de la base de données.
* **Multiplexage** : de nombreux clients PHP inactifs ne bloquent pas chacun une session de serveur inactive.
* Comportement plus fluide lors des pics de trafic **soudains**.

### Coûts et pièges
* Un **saut (hop)** supplémentaire (latence, domaine de défaillance supplémentaire, configuration à sécuriser et à surveiller).
* **La sémantique des sessions** change en fonction du **mode** de répartition — voir PgBouncer ci-dessous.
* Vous devez toujours dimensionner le pooler afin qu'il ne devienne pas le **nouveau** goulot d'étranglement (processeur, descripteurs de fichiers, épuisement du pool).

---

<a id="pgbouncer"></a>
## PostgreSQL : PgBouncer en pratique

**PgBouncer** est le standard de facto pour le regroupement de connexions Postgres dans les architectures PHP.

**Modes de pool** courants :

| Mode | Comportement | Intégration PHP / Laravel |
|------|--------------|---------------------------|
| **Session** | Une connexion serveur pour toute la session client jusqu'à la déconnexion. | Compatibilité la plus sûre : `SET`, `LISTEN`, verrous consultatifs (advisory locks), tables temporaires, requêtes préparées fonctionnent. **Gain de multiplexage minimal** si les clients restent connectés longtemps (workers) ou si vous ouvrez par requête de toute façon. |
| **Transaction** | Connexion serveur renvoyée au pool **après chaque transaction** (COMMIT/ROLLBACK). | **Multiplexage fort** pour les requêtes web courtes. Casse les fonctionnalités **liées à la session** : `SET LOCAL` sur plusieurs allers-retours sans transaction, `LISTEN`, tables temporaires à longue durée de vie, certains modèles de **requêtes préparées** à moins d'être configurés avec soin. |
| **Statement** | Connexion serveur libérée après **chaque instruction**. | Rare pour les ORM ; casse les transactions à plusieurs instructions. Pas une cible classique de Laravel. |

### Requêtes préparées et regroupement de transactions

De nombreux pilotes préparent les requêtes **par nom** sur la session. Lorsque la connexion physique au serveur change, les **requêtes préparées nommées** peuvent échouer. Atténuations utilisées en production :

* Préférez les requêtes préparées **non nommées** / le protocole de **requête simple** pour cette liaison, ou
* **Désactivez** les requêtes préparées côté serveur pour la connexion au pooler (spécifique au pilote ; souvent `PDO::ATTR_EMULATE_PREPARES` ou des options de framework).

```php
// config/database.php
'connections' => [
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
        // ...
        'options' => [
            PDO::ATTR_EMULATE_PREPARES => true, // Émule les requêtes préparées localement en PHP
        ],
    ],
],
```

---

<a id="proxysql"></a>
## MySQL et MariaDB : ProxySQL et assimilés

**ProxySQL** est un intermédiaire populaire pour le **protocole MySQL** : routage, règles de requête, répartition lecture/écriture et **regroupement de connexions** avec des règles de multiplexage adaptées par utilisateur/schéma.

Les équipes l'utilisent pour :
* Plafonner les **connexions backend** pendant que de nombreux processus enfants PHP-FPM se connectent à ProxySQL.
* Acheminer les **lectures** vers les réplicas avec des règles explicites (surveillez tout de même le **retard de réplication**).
* Rejeter ou réécrire certains modèles de requêtes (avec prudence — la logique dans le proxy reste une **complexité opérationnelle**).

**MySQL Router** (InnoDB Cluster) et certains **répartiteurs de charge cloud** exposent un comportement similaire au regroupement, mais **vérifiez la documentation** : tous les niveaux ne multiplexent pas de la même manière que ProxySQL.

**MariaDB MaxScale** peut agir comme un routeur avec des fonctionnalités de gestion de connexion en fonction de l'édition et des modules — vérifiez les **licences** et les capacités pour votre déploiement.

---

<a id="managed"></a>
## Proxies gérés (RDS Proxy, autres)

Les fournisseurs de cloud proposent des **proxies de connexion gérés** devant RDS, Aurora, Cloud SQL, etc. Ils gèrent généralement :
* Le **regroupement** et l'intégration de l'**authentification par IAM ou jeton**.
* La simplicité en cas de **basculement (failover)** (reconnexion des backends sans reconnecter tous les processus PHP en même temps).

Ils obéissent toujours à la **sémantique de la base de données** : si le produit multiplexe de manière agressive, vous faites face aux mêmes contraintes de **requêtes préparées** et d'**état de session** qu'avec un PgBouncer auto-hébergé — lisez la **matrice de service** pour votre moteur et votre pilote.

---

<a id="other-tools"></a>
## Autres poolers : PgCat, Odyssey, pgpool-II

* **PgCat** et **Odyssey** — Poolers Postgres avec une popularité croissante ; comparez les **modes de pool**, les métriques et les particularités du pilote avec PgBouncer avant de changer.
* **pgpool-II** — souvent déployé pour la **réplication** et le routage autant que pour le regroupement ; **plus lourd sur le plan opérationnel** que PgBouncer si vous n'avez besoin que du multiplexage.

---

<a id="laravel"></a>
## Notes spécifiques à Laravel

* **`config/database.php`** — `connections.*.options` et les drapeaux de pilotes permettent d'aligner le comportement de **PDO** avec votre pooler (par exemple, émuler les requêtes préparées si nécessaire).
* **Séparation lecture/écriture** — Laravel peut envoyer les sélections vers des hôtes de `lecture` ; combiné avec un répartiteur, assurez-vous que les sémantiques **sticky** correspondent à vos attentes (retard de réplication vs configuration `sticky`).
* **Octane / Swoole / FrankenPHP** — les workers **à longue durée de vie** changent la donne : les connexions persistantes peuvent **bien fonctionner** mais vous devez éviter la **fuite** de l'état de connexion entre les requêtes et surveiller le **délai d'inactivité (idle timeout)** sur le serveur et le pooler.
* **Horizon / `queue:work`** — la concurrence × les workers ajoute des connexions **soutenues** ; regroupez **par worker** ou utilisez le **mode transaction** avec des paramètres compatibles.
* **Telescope, Nightwatch, barres de débogage** en prod peuvent maintenir les transactions ouvertes plus longtemps que vous ne le pensez — à restreindre aux environnements de **développement uniquement**.

Exemple de configuration d'environnement utilisant un répartiteur :
```env
# .env
# PHP se connecte à PgBouncer sur le port 6432 ; PgBouncer se connecte à Postgres sur le port 5432
DB_HOST=pgbouncer.internal
DB_PORT=6432
DB_DATABASE=app
DB_USERNAME=app_rw
```

---

<a id="not-a-cure"></a>
## Ce que les poolers ne résolvent *pas*

* Les **requêtes N+1** et les index manquants consomment toujours du **processeur et des E/S** sur le serveur — le regroupement ne fait que limiter **le nombre de sessions** qui expriment cette charge.
* Les **transactions longues** monopolisent les backends de serveur du pool — vos gains en **mode transaction** disparaissent si le code maintient les transactions ouvertes pendant des appels HTTP externes.
* Les **verrous globaux** et les **migrations** — exécuter `migrate` via un pooler saturé peut mal interagir avec les **verrous** ; certaines équipes utilisent un chemin d'accès administrateur **direct** pour le DDL.

---

<a id="common-mistakes"></a>
## Erreurs courantes

1. **Regroupement de transactions avec des variables de session** : Définir des configurations spécifiques à la session (comme `SET TIMEZONE` ou utiliser des tables temporaires) dans un environnement PgBouncer avec regroupement de transactions, ce qui entraîne la fuite de ces paramètres vers d'autres sessions clients.
2. **Oubli d'émuler les requêtes préparées** : Ne pas définir `PDO::ATTR_EMULATE_PREPARES => true` lors de l'utilisation du regroupement de transactions, ce qui lève des exceptions du type \"prepared statement already exists\" ou \"prepared statement not found\".
3. **Mise à l'échelle des limites du pooler au-delà des frontières du serveur** : Configurer la valeur `max_client_conn` de PgBouncer et la taille du pool backend de manière à ce qu'elles soient supérieures à la valeur physique `max_connections` de PostgreSQL.
4. **Mauvaise utilisation des connexions persistantes avec FPM** : Activer `PDO::ATTR_PERSISTENT` sur les serveurs web sans gérer la durée de vie des enfants FPM, laissant les connexions inactives ouvertes indéfiniment.

---

<a id="checklist"></a>
## Liste de contrôle

1. **Inventoriez** chaque type de processus qui ouvre du SQL (processus enfants FPM max × nœuds, workers Horizon, cron, CLI).
2. Comparez les totaux à **`max_connections`** et à la **RAM par connexion** sur la base de données — décidez d'abord de la stratégie : **pooler vs plus de discipline applicative**.
3. Choisissez le **mode de pool** (Postgres) ou les **règles de multiplexage** (MySQL) qui correspondent aux capacités du couple **ORM + pilote**.
4. Validez les **requêtes préparées** et les **fonctionnalités de session** (`SET`, tables temporaires, verrous consultatifs) sous des tests de charge.
5. Surveillez le **temps d'attente du pooler** et les **connexions actives du serveur** — si la file d'attente du pooler grandit, la base de données ou le mélange de requêtes reste la limite.

---

## Résumé

Les répartiteurs de niveau intermédiaire sont des **infrastructures que vous gérez** (ou achetez). Bien utilisés, ils transforment « PHP a ouvert huit cents connexions » en « Postgres voit soixante backends occupés » — ce qui est précisément la forme pour laquelle la plupart des bases de données OLTP ont été conçues.

---

<a id="self-test-quiz"></a>
## Quiz d'auto-évaluation

### Question 1 : Que se passe-t-il si vous essayez d'utiliser des verrous consultatifs (advisory locks) PostgreSQL via PgBouncer exécuté en mode de regroupement de transactions ?
- A) Les verrous fonctionnent correctement car PgBouncer les intercepte.
- B) Les verrous peuvent verrouiller la mauvaise session ou être perdus silencieusement lorsque la connexion change de backend.
- C) Une exception SQL immédiate est levée par le parseur de PgBouncer.

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse : B**
Les verrous consultatifs sont liés à la session physique du backend. En mode transaction, votre requête suivante peut être acheminée vers une connexion physique différente, ce qui signifie que le verrou est perdu de votre côté tout en restant verrouillé sur le backend d'origine.
</details>

---

### Question 2 : Pourquoi PHP-FPM crée-t-il des tempêtes de connexions par rapport à des environnements d'exécution à workers persistants comme Go ou Node.js ?
- A) Les processus PHP-FPM ne prennent pas en charge TCP.
- B) PHP-FPM met fin à l'état de la requête à la fin de l'exécution, ce qui ferme et rouvre les descripteurs de base de données à plusieurs reprises.
- C) Node.js et Go utilisent des moteurs de base de données personnalisés.

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse : B**
Parce que le cycle de vie de PHP-FPM est limité à la requête, les connexions sont négociées et fermées à chaque requête, à moins que des connexions persistantes ne soient soigneusement configurées.
</details>
