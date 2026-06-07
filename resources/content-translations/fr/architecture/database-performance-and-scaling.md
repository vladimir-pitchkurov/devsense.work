---
title: "Bases de données sous charge : requêtes, index, MySQL vs Postgres, mise à l'échelle | DevSense"
description: "Comment optimiser le SQL et le schéma, choisir les types d'index, quand la logique côté base de données devient un handicap, comment MySQL et PostgreSQL diffèrent en production, et ce que coûtent réellement la mise à l'échelle verticale, les réplicas, la décomposition et le sharding."
published: 2026-04-13
faq:
  - question: "Pourquoi la pagination par recherche (keyset) est-elle plus rapide que la pagination OFFSET sur de grands ensembles de données ?"
    answer: "La pagination OFFSET oblige le moteur de base de données à analyser et rejeter toutes les lignes sautées (par exemple, scanner 100 000 lignes pour en renvoyer 20). La pagination keyset utilise une condition de filtrage (par exemple, `WHERE (created_at, id) < (?, ?)`) basée sur la dernière ligne vue, permettant au moteur de sauter directement aux lignes cibles en utilisant un index B-tree sans scanner les lignes ignorées."
  - question: "Quand devez-vous utiliser un index couvrant au lieu d'un index classique ?"
    answer: "Vous devez utiliser un index couvrant (souvent en utilisant la clause `INCLUDE` ou un index composite contenant tous les champs sélectionnés) lorsqu'une requête fréquente ne lit que quelques colonnes. Cela permet au moteur de base de données de renvoyer le résultat directement depuis la structure de l'index (Index Only Scan) sans effectuer de recherche secondaire sur le disque ou le Heap pour la ligne complète."
  - question: "Quels sont les principaux compromis liés à l'utilisation de réplicas de lecture ?"
    answer: "Les réplicas de lecture déchargent les opérations de lecture de la base de données principale, augmentant ainsi le débit de lecture. Cependant, le délai de réplication (replication lag) signifie que les réplicas peuvent servir des données obsolètes (cohérence éventuelle). Les développeurs doivent concevoir le routage de l'application pour diriger les lectures dépendantes des écritures vers la base de données principale afin d'éviter les bugs de cohérence de type 'read-your-writes'."
  - question: "Pourquoi le sharding est-il considéré comme un dernier recours pour la mise à l'échelle d'une base de données ?"
    answer: "Le sharding divise les données entre des instances de base de données physiquement distinctes, ce qui augmente la complexité opérationnelle. Il empêche l'utilisation de jointures inter-shards, l'intégrité des transactions globales (sans validations en deux phases lentes), et les contraintes simples d'unicité globale, tout en introduisant la difficulté de rééquilibrer les shards lorsque la distribution des données change."
---

# Bases de données sous charge : optimisation des requêtes, index, moteurs et compromis de mise à l'échelle

La plupart des histoires de performances sont ennuyeuses jusqu'à ce qu'elles ne le soient plus. Le tableau de bord affiche fièrement quatre-vingt-quinze millisecondes, puis une campagne est lancée, un rapport joint six tables, et soudain **le passage en caisse** et **la réinitialisation du mot de passe** partagent une file d'attente derrière le même moteur de stockage. Les solutions sont rarement un simple bouton magique : elles résident dans **moins d'allers-retours**, **des index qui correspondent aux prédicats réels**, **des calculs honnêtes de capacité**, et parfois **le fait d'admettre qu'une base de données logique ne peut pas être infinie**.

**Guides associés :** [High-load event ingestion](high-load-event-ingestion) · [Message queues compared](message-queues-compared) · [Observability and monitoring](observability-monitoring-laravel)

## Table des matières

* [Mesurer avant d’« optimiser »](#measure)
* [Optimisations au niveau des requêtes avec exemples](#queries)
* [Des choix de schéma qui vieillissent bien](#schema)
* [Les types d’index et quand ils aident](#indexes)
* [Pourquoi une logique lourde dans la base de données nuit aux équipes](#db-logic)
* [MySQL vs PostgreSQL en pratique](#mysql-vs-postgres)
* [Pistes de mise à l'échelle et les problèmes qu'elles apportent](#scaling)
* [Décomposition sans contes de fées](#decomposition)
* [Sharding : clés, douleur inter-shards, rééquilibrage](#sharding)
* [Erreurs courantes](#common-mistakes)
* [Liste de contrôle avant de sharder ou d'acheter du matériel](#checklist)
* [Quiz d'auto-évaluation](#self-test-quiz)

---

<a id="measure"></a>
## Mesurer avant d’« optimiser »

Les **percentiles de latence** l'emportent sur les moyennes. Une moyenne de 40 ms peut masquer une traîne où **un pour cent** des requêtes dépasse deux secondes en raison d'attentes de verrous ou de caches froids.

Signaux pratiques :
* **Journaux de requêtes lentes** avec un seuil à réévaluer à mesure que les données augmentent — pas « tout », sinon vous apprendrez aux équipes à ignorer le bruit.
* **`EXPLAIN` (Postgres)** / **`EXPLAIN` (MySQL 8+)** sur les formes de requêtes réellement exécutées en production avec leurs liaisons de paramètres, et pas seulement des valeurs littérales écrites à la main.
* **Nombre de connexions** et **saturation du pool** ; de nombreux incidents de « base de données lente » sont en fait des **attentes de connexion**, et non un problème de disque.

> [!NOTE]
> **Partage d'état (Shared State)**
> La base de données est un état partagé. Tout ce qui augmente le **CPU, les verrous ou les E/S** sur le nœud principal finit par entrer en concurrence avec tout le reste sur ce même chemin.

---

<a id="queries"></a>
## Optimisations au niveau des requêtes avec exemples

### Ne récupérez que ce dont vous avez besoin

Un `SELECT *` large sur des lignes volumineuses force le moteur à déplacer des octets que vous rejetterez ensuite en PHP ou Node. Privilégiez les colonnes explicites, en particulier sur les tables contenant de gros volumes de texte ou de JSON.

```sql
-- database/queries/fetch_users.sql
-- À éviter (transmet toutes les colonnes, y compris les blobs inutiles)
SELECT * FROM users WHERE country = 'DE' LIMIT 50;

-- À préférer
SELECT id, email, display_name, created_at
FROM users
WHERE country = 'DE'
ORDER BY created_at DESC
LIMIT 50;
```

### Forme des jointures et cardinalité

Les boucles imbriquées (nested loops) sont peu coûteuses lorsque le côté interne est **appuyé sur un index** et de petite taille ; les jointures par hachage (hash joins) excellent sur les ensembles plus grands — **la stratégie obtenue** dépend du moteur et des statistiques. Si une jointure fait exploser le nombre de lignes parce qu'une relation est **plusieurs-à-plusieurs sans contrainte**, corrigez le modèle — pas le timeout.

### Pagination sans scanner toute la table

La pagination `OFFSET` est trompeusement simple. Pour les grands décalages, le moteur **parcourt souvent tout de même** les lignes ignorées.

```sql
-- database/queries/offset_pagination.sql
-- Devient plus lent à mesure que :offset augmente
SELECT id, title, created_at
FROM posts
ORDER BY created_at DESC, id DESC
LIMIT 20 OFFSET 100000;
```

La **pagination Keyset (recherche)** utilise la dernière clé de tri vue :

```sql
-- database/queries/seek_pagination.sql
SELECT id, title, created_at
FROM posts
WHERE (created_at, id) < (:last_created_at, :last_id)
ORDER BY created_at DESC, id DESC
LIMIT 20;
```

Vous devez disposer d'un **index de support** correspondant à l'ordre de tri, par exemple `(created_at DESC, id DESC)`.

### `EXISTS` vs `IN` pour les vérifications d'existence

Pour les schémas du type « existe-t-il une ligne correspondante ? », les plans de type semi-jointure se comportent souvent très bien :

```sql
-- database/queries/exists_lookup.sql
SELECT o.id, o.total_cents
FROM orders o
WHERE EXISTS (
  SELECT 1 FROM order_items i
  WHERE i.order_id = o.id AND i.sku = 'GOLD-001'
);
```

### Agrégations et rapports

Les `GROUP BY` lourds sur des tables OLTP brutes sont un moyen classique de **dégrader la latence de queue**. Précalculez dans des **tables de résumé**, des **vues matérialisées** (Postgres), ou un stockage **OLAP** lorsque l'entreprise a réellement besoin d'analyses interactives.

---

<a id="schema"></a>
## Des choix de schéma qui vieillissent bien

* **Des types qui correspondent à la réalité** — stockez l'argent sous forme d'**entiers (plus petite unité)** (par exemple, des centimes) ou en **décimal avec une précision explicite**, pas de flottants binaires.
* **Nullabilité** — les colonnes nullables compliquent l'indexation et les statistiques ; utilisez-les lorsque la valeur inconnue a un sens, pas comme valeur par défaut par paresse.
* **Clés étrangères** — elles coûtent un peu à l'écriture et garantissent la **cohérence référentielle**. Les équipes qui les désactivent pour « aller plus vite » le paient souvent en **lignes orphelines** et en **bugs applicatifs non déterministes**.
* **Sur-normalisation vs chemins de lecture** — la théorie pure ignore la fréquence à laquelle vous lisez des données jointes. Un **champ dénormalisé** mesuré et documenté peut l'emporter sur des jointures interminables — au prix d'une **complexité d'écriture** et d'une **discipline stricte sur les invariants**.

---

<a id="indexes"></a>
## Les types d’index et quand ils aident

Tous les index ne sont pas des arbres B, et tous les arbres B n'ont pas la bonne forme.

| Concept | Utilisation typique | Remarques |
|---------|---------------------|-----------|
| **B-tree (par défaut)** | Égalité et plage sur les types triables | La plupart des index « normaux » ; pour les composites, **l'ordre compte** — les premières colonnes doivent correspondre aux filtres courants `WHERE`/`ORDER BY`. |
| **Hash** | Égalité exacte (si pris en charge) | Les index **hash** Postgres ont historiquement connu des limites de réplication. MySQL expose le hachage principalement via **MEMORY** et ses mécanismes internes d'**adaptive hash**. |
| **Full-text** | Recherche de tokens dans du texte | MySQL **InnoDB FTS** vs Postgres **`tsvector` + GIN** — analyseurs et profils de maintenance différents. |
| **GIN / GiST (Postgres)** | Contenance JSONB, tableaux, recherche textuelle, certains types géométriques | Puissant ; la **construction de l'index et le bloat** doivent être surveillés. |
| **Spatial** | Requêtes géographiques | Spécifique au moteur (**PostGIS** sur Postgres ; types spatiaux MySQL). |

### Index composites : ordre des colonnes

Si les requêtes filtrent presque toujours sur `tenant_id`, le placer **en premier** est généralement correct :

```sql
-- database/migrations/create_composite_index.sql
-- Idéal quand les requêtes ressemblent à : WHERE tenant_id = ? AND status = 'open'
CREATE INDEX orders_tenant_status_idx ON orders (tenant_id, status);
```

### Index couvrants (Covering Indexes)

Si l'index **contient toutes les colonnes** lues par la requête, le moteur peut y répondre uniquement à partir de l'index (**Index Only Scan** sous Postgres). Exemple de structure :

```sql
-- database/migrations/create_covering_index.sql
CREATE INDEX sessions_lookup_idx ON sessions (user_id) INCLUDE (last_seen_at);
```

### Index partiels / filtrés

Lorsqu'un prédicat est **stable et sélectif**, n'indexez que la tranche active :

```sql
-- database/migrations/create_partial_index.sql
-- Index partiel spécifique à Postgres
CREATE INDEX invoices_open_due_idx
ON invoices (due_at)
WHERE status = 'open' AND due_at IS NOT NULL;
```

---

<a id="db-logic"></a>
## Pourquoi une logique lourde dans la base de données nuit aux équipes

Les procédures stockées, les déclencheurs (triggers) et les vues complexes ne sont **pas de la mauvaise physique**. Ce sont des choix de **déploiement et de responsabilité**.

Ce qui pose souvent problème :
* **Gestion des versions** — le code applicatif est déployé via Git, CI et des déploiements progressifs ; les routines de base de données vivent **ailleurs**. Les dérives entre branches et environnements deviennent douloureuses.
* **Tests** — les règles métier en PHP/Java sont testées unitairement dans des environnements familiers ; les triggers qui mutent des lignes lors de l'insertion sont **plus difficiles à tester** isolément.
* **Portabilité** — la logique applicative peut migrer entre MySQL, Postgres ou d'autres éditeurs ; la logique en PL/pgSQL ou dans les procédures MySQL vous **enferme chez un fournisseur**.
* **Observabilité** — les traces de pile (stack traces) et les spans APM se concentrent sur la couche applicative ; les chaînes de déclencheurs complexes se traduisent par une **latence mystérieuse** à moins d'instrumenter méticuleusement.

Quand la logique côté base de données reste **néanmoins** pertinente :
* **Contraintes** — `CHECK`, clés étrangères et **unicité bien choisie** expriment les invariants de manière plus économique qu'en espérant que chaque service applicatif s'en souvienne.
* **Gardes d'idempotence** — un **index unique** sur une clé métier est plus efficace que des vérifications préalables de type « select puis insert ».

---

<a id="mysql-vs-postgres"></a>
## MySQL vs PostgreSQL en pratique

Les deux sont de qualité production. Les différences deviennent problématiques si vous supposez qu'ils sont interchangeables.

| Sujet | MySQL (InnoDB classique) | PostgreSQL |
|-------|--------------------------|------------|
| **Modèle de stockage** | La **clé primaire** (clustered) organise les lignes ; les index secondaires pointent vers la PK | Tables **Heap** ; les index sont séparés ; **CLUSTER** est une opération de maintenance |
| **MVCC / nettoyage** | Historique **Undo** ; les délais de purge (**purge lag**) comptent pour les transactions longues | **Tuples morts** ; le nettoyage via **VACUUM** (autovacuum) est le quotidien opérationnel |
| **Fonctionnalités SQL** | SQL de base solide ; historiquement plus strict sur certains aspects | **CTEs** plus riches, **fonctions de fenêtrage**, **LATERAL**, opérateurs **JSONB**, **types de plages** (ranges) |
| **Extensions** | Peu dans le noyau ; écosystème via plugins | **PostGIS**, **pgvector**, **Citext**, et bien d'autres |
| **Réplication** | Streaming de **binlog** mature ; nombreuses topologies hébergées | Réplication **physique** et **logique** ; modèle **publication/abonnement** |

Exemples de requêtes qui divergent :

Postgres — **Contenance JSONB** :
```sql
-- database/queries/postgres_jsonb_containment.sql
SELECT id FROM events WHERE payload @> '{"kind":"purchase"}'::jsonb;
```

MySQL — Extraction **JSON** (8.x) :
```sql
-- database/queries/mysql_json_extract.sql
SELECT id FROM events WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.kind')) = 'purchase';
```

---

<a id="scaling"></a>
## Pistes de mise à l'échelle et les problèmes qu'elles apportent

### Mise à l'échelle verticale (« machine plus puissante »)
Simple jusqu'à ce que la physique dise non. La bande passante du disque et les effets CPU NUMA apparaissent. De plus, vous concentrez les risques de panne.

### Réplicas de lecture (Read Replicas)
* **Avantages :** déchargent le trafic **essentiellement en lecture**, facilitent les sauvegardes et les clones de rapports.
* **Inconvénients :** le **délai de réplication** (replication lag) signifie que les lectures peuvent être obsolètes.

### Pool de connexions (Connection Pooling)
Ouvrir une session TCP+auth par requête HTTP ne passe pas à l'échelle. Des outils comme **PgBouncer** (Postgres) ou ProxySQL (MySQL) s'insèrent entre l'application et la base de données.

### Caching (Redis et consorts)
Les caches masquent les clés très sollicitées (hot keys) ; ils ne résolvent pas l'**amplification d'écriture** sur la base de données principale.

---

<a id="decomposition"></a>
## Décomposition sans contes de fées

Le modèle **un schéma par service** n'est pas magique — il offre simplement **moins de jointures accidentelles** et un **rayon d'action de panne plus clair**. Il implique des **transactions distribuées** ou des **sagas** lorsqu'une action utilisateur s'étend réellement sur plusieurs bases.

Anti-pattern : **deux services écrivant dans la même table** via une « base de données partagée » — vous venez de construire un **monolithe distribué** avec des sauts réseau supplémentaires.

---

<a id="sharding"></a>
## Sharding : clés, douleur inter-shards, rééquilibrage

Le **sharding** répartit les lignes sur **plusieurs bases de données principales** en utilisant une **clé de shard** (souvent l'ID utilisateur ou l'ID locataire).

Ce qui s'améliore :
* Les plafonds de **débit d'écriture** par machine diminuent puisque chaque shard possède sa propre tranche.
* Le **rayon d'action d'une panne** (blast radius) peut rétrécir si une défaillance n'isole que certains shards.

Ce qui fait mal :
* **Jointures inter-shards** et **unicité globale** — vous avez besoin de coordination ou de règles applicatives spécifiques.
* **Rééquilibrage** lorsqu'un client très actif domine un shard — le resharding est une opération complexe.

---

<a id="common-mistakes"></a>
## Erreurs courantes

1. **S'appuyer sur OFFSET pour de grands ensembles de données** : Utiliser la pagination classique `LIMIT ... OFFSET`, ce qui oblige le moteur à scanner des millions de lignes pour n'en renvoyer que quelques-unes.
2. **Ignorer l'ordre des colonnes des index composites** : Placer une colonne avec des filtres de plage (par exemple, `created_at`) avant des colonnes avec des filtres d'égalité dans les définitions d'index composites.
3. **Modèle de base de données partagée dans les microservices** : Permettre à plusieurs services d'écrire dans la même table, ce qui crée un couplage fort et des goulots d'étranglement d'intégration.
4. **Écrire la logique métier dans des déclencheurs (triggers)** : Placer des validations et des mutations complexes dans des triggers, contournant ainsi les tests, la CI et le traçage.

---

<a id="checklist"></a>
## Liste de contrôle avant de sharder ou d'acheter du matériel

1. **Est-ce qu'un `EXPLAIN` montre un scan séquentiel** qu'un index honnête corrigerait ?
2. **Y a-t-il des requêtes N+1** provenant de la couche ORM, indépendamment de la vitesse du disque ?
3. **Le goulot d'étranglement est-il lié aux connexions** ou au CPU sur la base de données — ou à une **contention de verrous** causée par de longues transactions ?
4. **Avez-ce mesuré le délai de réplication** si vous déportez les lectures sur des réplicas ?
5. **La croissance des données est-elle limitée par la rétention** (archiver les partitions froides), ce qui est moins coûteux qu'une nouvelle topologie ?

---

## Résumé

Les bases de données récompensent la **rigueur simple** et la **mesure**. Les index et le choix du moteur font gagner du temps ; **l'architecture** — files d'attente, modèles de lecture séparés, et parfois sharding — fait gagner de la **marge de croissance**.

---

<a id="self-test-quiz"></a>
## Quiz d'auto-évaluation

### Question 1 : Pourquoi une requête telle que `SELECT * FROM users ORDER BY created_at DESC LIMIT 1` s'exécute-t-elle lentement sur une table de 10 millions de lignes, même si `created_at` est indexé ?
- A) Les index ne peuvent pas être scannés en ordre descendant.
- B) Si la colonne est nullable, le moteur de base de données peut analyser toute la table pour rechercher les valeurs NULL.
- C) Vous devez utiliser un index couvrant avec `INCLUDE`.

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse : B**
Si la colonne triée est nullable et que la requête ne filtre pas les valeurs NULL (par exemple, sans clause `WHERE created_at IS NOT NULL`), le moteur peut se rabattre sur un scan complet de la table pour localiser les valeurs nulles, contournant ainsi le scan d'index.
</details>

---

### Question 2 : Quel type d'index est le plus adapté pour rechercher des clés dans des documents JSON arbitraires sous PostgreSQL ?
- A) Index B-Tree.
- B) Index GIN (Generalized Inverted Index).
- C) Index Hash.

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse : B**
Les index GIN sont spécifiquement conçus pour indexer les éléments multi-valeurs comme les tableaux et les structures JSONB, permettant des requêtes de contenance rapides.
</details>
