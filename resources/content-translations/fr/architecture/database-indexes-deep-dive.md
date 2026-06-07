---
title: "Index de base de données : Sous le capot et plongée profonde | DevSense"
description: "Un guide complet pour les développeurs sur les arbres B+ dans InnoDB, les structures Heap dans Postgres, les pointeurs de nœuds d'index, les règles d'index composites et l'amplification d'écriture."
published: 2026-05-31
faq:
  - question: "Pourquoi InnoDB utilise-t-il un index clusterisé tandis que Postgres utilise une structure Heap ?"
    answer: "InnoDB stocke les données de ligne directement dans les nœuds feuilles de l'arbre B+ de la clé primaire pour rendre les recherches de clé primaire extrêmement rapides et garder les données associées contiguës. Postgres stocke toutes les données dans un fichier Heap et fait de tous les index (y compris la clé primaire) des index secondaires pointant vers les emplacements du Heap via des TIDs. Cela simplifie les déplacements de lignes lors des mises à jour et évite les doubles recherches pour les index secondaires, mais nécessite des accès au Heap pour les requêtes sur la clé primaire."
  - question: "Qu'est-ce qu'un index GIN et quand dois-je l'utiliser dans Postgres ?"
    answer: "Un index inversé généralisé (GIN) est conçu pour indexer des valeurs composites telles que le JSONB ou les tableaux. Il associe des éléments individuels (clés ou valeurs) à leurs TIDs correspondants, permettant une correspondance très efficace des requêtes contenant des opérateurs comme `@>` (contient) ou `?` (a la clé)."
  - question: "Comment l'amplification d'écriture se produit-elle pendant la maintenance des index ?"
    answer: "L'amplification d'écriture se produit parce que chaque INSERT, UPDATE ou DELETE oblige la base de données à mettre à jour les données de la table ainsi que tous les index associés. De plus, si une insertion tombe dans une page d'arbre B pleine, la page doit être divisée (page split), ce qui écrit plusieurs pages sur le disque et provoque une fragmentation."
  - question: "Comment puis-je éviter les doubles recherches dans les index secondaires sous InnoDB ?"
    answer: "Pour éviter les doubles recherches (un scan d'index secondaire suivi d'une recherche par clé primaire dans l'index clusterisé), vous pouvez concevoir un index couvrant (covering index) qui contient toutes les colonnes demandées par la requête SELECT, permettant à la base de données de satisfaire entièrement la requête à partir de l'index secondaire."
---

# Index de base de données : Sous le capot et plongée profonde

Chaque requête de base de données que vous écrivez est une course contre les E/S disque. Lorsque votre ensemble de données tient en RAM, les requêtes sont instantanées. Mais à mesure que vos données atteignent des millions de lignes et débordent sur le disque, votre moteur de base de données doit soit parcourir chaque page du disque (un parcours séquentiel), soit utiliser une carte pour accéder directement aux données dont il a besoin. Cette carte est un index.

Comprendre le fonctionnement des index au niveau du disque et des pages distingue les développeurs juniors qui ajoutent des index au hasard aux tables, des architectes de bases de données qui conçoivent des moteurs de stockage auto-optimisés à haut débit. Dans cette plongée profonde, nous allons soulever la couche d'abstraction de MySQL (InnoDB) et de PostgreSQL pour voir comment ils représentent les index sur le disque, comment l'ordre des colonnes composites est résolu, et le coût réel de l'amplification d'écriture.

---

## 1. Sous le capot : Arbre B+ dans InnoDB vs Heap et Arbre B dans Postgres

Les moteurs de bases de données ne stockent pas les tables comme de simples tableaux de lignes. Ils les structurent sur le disque en utilisant des pages (généralement 16 Ko pour InnoDB, 8 Ko pour PostgreSQL). Cependant, leurs architectures de stockage sont fondamentalement différentes.

### InnoDB : L'index clusterisé (Arbre B+)
Dans le moteur InnoDB de MySQL, **la table est l'index**. Plus précisément, la table est structurée comme un arbre B+ construit autour de la clé primaire (Primary Key). C'est ce qu'on appelle un **index clusterisé** (Clustered Index).

*   **Nœuds internes (Internal Nodes) :** Ils ne contiennent que des clés et des pointeurs vers des pages enfants. Ils guident le moteur lors des recherches.
*   **Nœuds feuilles (Leaf Nodes) :** Ils contiennent les lignes de données réelles. Les pages feuilles sont liées séquentiellement dans une liste doublement chaînée, ce qui rend les scans de plage (`WHERE id BETWEEN 10 AND 50`) incroyablement rapides.
*   **Index secondaires (Secondary Indexes) :** Tout index autre que la clé primaire dans InnoDB est un index secondaire. Les nœuds feuilles d'un index secondaire ne pointent *pas* vers des adresses physiques sur le disque. À la place, ils stockent la **valeur de la clé primaire** de la ligne.

> [!NOTE]
> Comme les index secondaires d'InnoDB stockent la clé primaire, la recherche d'une ligne via un index secondaire (par exemple, `WHERE email = 'user@example.com'`) nécessite une recherche en deux étapes : d'abord, parcourir l'index secondaire pour trouver la clé primaire, puis parcourir l'arbre B+ de l'index clusterisé pour récupérer les données de la ligne.

```
[Secondary Index (Email)]
  Leaf Node: 'user@example.com' -> PK: 42
          |
          v
[Clustered Index (ID)]
  Leaf Node: PK: 42 -> Row Data: {id: 42, email: 'user@example.com', name: 'John Doe'}
```

### PostgreSQL : Le Heap et l'arbre B
PostgreSQL n'utilise pas d'index clusterisés par défaut. À la place, il stocke les données des lignes dans une structure non ordonnée appelée un **Heap** (tas).

*   **Pages Heap :** Les lignes de table sont ajoutées aux pages dans l'ordre de leur insertion (ou là où de l'espace est disponible).
*   **Index B-Tree :** Chaque index dans Postgres (y compris la clé primaire) est un index secondaire.
*   **Nœuds feuilles :** Les nœuds feuilles d'un index B-Tree Postgres contiennent un **identifiant de tuple (TID)**, qui est une adresse physique sur le disque composée d'un numéro de bloc (page) et d'un index de décalage (offset) dans cette page (par exemple, `(Page 14, Offset 3)`).

> [!WARNING]
> Comme les index Postgres stockent des TIDs physiques, toute opération qui déplace une ligne sur le disque (comme un UPDATE qui modifie la taille de la ligne ou une migration de page MVCC) briserait ces TIDs. Postgres gère cela à l'aide du vacuuming et de l'optimisation HOT (Heap Only Tuples), mais cela signifie que tous les index pointent directement vers le Heap.

```
[Postgres Index (Email)]                         [Postgres Heap (Unordered)]
  Leaf: 'user@example.com' -> TID: (Page 14, Offset 3) ----> Page 14, Slot 3: {id: 42, ...}
```

---

## 2. Règles d'ordre des colonnes dans les index composites

Lors de la création d'un index sur plusieurs colonnes — un **index composite** — l'ordre des colonnes dans la déclaration de l'index est critique. Un mauvais ordre de colonnes rendra l'index complètement inutile pour certaines requêtes.

Les règles d'or de la conception d'index composites sont :
1.  **La règle du préfixe le plus à gauche (Leftmost Prefix Rule) :** La base de données ne peut utiliser un index composite que si la requête filtre d'abord sur la colonne la plus à gauche de l'index. Un index sur `(A, B, C)` peut optimiser les requêtes filtrant sur `(A)`, `(A, B)` et `(A, B, C)`, mais *ne peut pas* optimiser les requêtes filtrant sur `(B)` ou `(C)` uniquement.
2.  **L'égalité d'abord, la plage en dernier (Equality First, Range Last) :** Les colonnes filtrées avec une égalité exacte (`=`, `IN`) doivent venir en premier dans l'index. Les colonnes filtrées avec des plages (`<`, `>`, `BETWEEN`, `LIKE`) doivent venir en dernier. Une fois qu'une colonne de plage est évaluée, la base de données ne peut plus utiliser les colonnes suivantes de l'index pour le filtrage.
3.  **La cardinalité élevée en premier :** Les colonnes ayant une cardinalité élevée (beaucoup de valeurs uniques, par exemple `user_id`) doivent généralement précéder les colonnes ayant une faible cardinalité (peu de valeurs uniques, par exemple `status`), à condition qu'elles soient toutes deux interrogées avec une égalité.

Voyons une migration et une requête concrètes :

```sql
-- database/migrations/2026_05_31_create_orders_table.sql
CREATE TABLE orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    status VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP NOT NULL
);

-- database/migrations/2026_05_31_add_composite_index.sql
-- Configuration d'index optimale pour : user_id (égalité) + status (égalité) + created_at (plage/tri)
CREATE INDEX idx_orders_user_status_created ON orders (user_id, status, created_at);
```

Pour la requête ci-dessous, l'index permet au moteur de filtrer immédiatement par `user_id`, puis par `status`, puis de lire les valeurs de `created_at` pré-triées dans l'ordre inverse sans nécessiter d'opération de filesort distincte.

```sql
-- database/queries/find_user_orders.sql
SELECT id, user_id, status, created_at, amount
FROM orders
WHERE user_id = 42
  AND status = 'completed'
ORDER BY created_at DESC
LIMIT 10;
```

---

## 3. Plongée profonde dans les types d'index

Différents profils de requêtes nécessitent des structures de données différentes. Voici une comparaison des types d'index disponibles dans MySQL et PostgreSQL.

### B-Tree
Le pilier des moteurs de bases de données. Il prend en charge l'égalité (`=`), les plages (`>`, `<`, `BETWEEN`) et le tri (`ORDER BY`). Les arbres B restent équilibrés, garantissant que les opérations de recherche, d'insertion et de suppression s'exécutent toutes en un temps $O(\log n)$.

### Hash
Les index de hachage stockent un hachage de la valeur indexée et pointent vers la ligne correspondante.
*   **Postgres :** Prend en charge les index Hash explicites. Ils sont extrêmement rapides ($O(1)$) pour les recherches d'égalité mais ne prennent pas en charge les requêtes de plage, le tri ou les correspondances partielles multi-colonnes.
*   **MySQL (InnoDB) :** Ne prend pas en charge la création explicite d'index Hash. À la place, il utilise un **index de hachage adaptatif** (Adaptive Hash Index) — une fonctionnalité interne où InnoDB surveille automatiquement les profils de requêtes sur les arbres B et construit des tables de hachage en mémoire pour les recherches très fréquentes.

### GIN (Generalized Inverted Index) - Postgres
GIN est conçu pour indexer des valeurs composites pour lesquelles vous devez rechercher des éléments *à l'intérieur* de la valeur (comme des documents JSONB ou des tableaux). GIN associe les éléments individuels à l'intérieur du document à leurs TIDs de lignes physiques.

```sql
-- database/migrations/2026_05_31_postgres_features.sql
-- Créer une table avec du JSONB et ajouter un index GIN dans PostgreSQL
CREATE TABLE user_profiles (
    id BIGINT PRIMARY KEY,
    metadata JSONB NOT NULL
);

CREATE INDEX idx_user_metadata_gin ON user_profiles USING GIN (metadata);

-- Cette requête utilise l'index GIN pour faire correspondre instantanément les clés
SELECT * FROM user_profiles 
WHERE metadata @> '{"role": "administrator", "status": "active"}';
```

### Index partiels / filtrés
Un index partiel ne contient qu'un sous-ensemble des lignes d'une table, défini par une condition `WHERE`. Cela permet d'économiser un espace disque considérable et de maintenir l'index petit et rapide.
*   **Postgres :** Prend en charge nativement les index partiels.
*   **MySQL :** Ne prend pas en charge directement les index partiels (à partir de la version 8.0). Vous devez utiliser des index fonctionnels avec des expressions qui s'évaluent à `NULL` pour obtenir un effet similaire, ce qui est moins élégant.

```sql
-- database/migrations/2026_05_31_partial_index.sql
-- Postgres uniquement : Indexer uniquement les commandes actives non payées pour rester compact
CREATE INDEX idx_orders_active_unpaid ON orders (user_id) 
WHERE status = 'pending' AND amount > 100.00;
```

### Index d'expressions / fonctionnels
Vous pouvez indexer le résultat d'une fonction ou d'une expression plutôt que la valeur brute de la colonne. C'est très utile lorsque les requêtes effectuent des manipulations sur des colonnes dans la clause `WHERE`.

```sql
-- database/migrations/2026_05_31_functional_index.sql
-- Créer un index d'expression pour optimiser les recherches en minuscules
-- PostgreSQL :
CREATE INDEX idx_orders_lower_status ON orders (LOWER(status));

-- MySQL 8.0+ :
CREATE INDEX idx_orders_lower_status ON orders ((LOWER(status)));
```

### Index couvrants (Covering Indexes)
Un index couvrant est un index secondaire qui contient toutes les colonnes requises par une requête, ce qui permet à la base de données de satisfaire entièrement la requête à partir de la page d'index sans toucher aux données de la table (Heap ou Index clusterisé).
*   Dans Postgres, vous pouvez explicitement ajouter des colonnes de données (non-clés) à un index en utilisant la clause `INCLUDE`.
*   Dans MySQL, vous ajoutez simplement les colonnes aux clés de l'index composite.

```sql
-- database/migrations/2026_05_31_covering_index.sql
-- Postgres : L'index est trié par user_id, mais transporte les données amount/created_at
CREATE INDEX idx_orders_covering ON orders (user_id) INCLUDE (amount, created_at);
```

---

## 4. Amplification d'écriture et coût de maintenance

Les index ne sont pas gratuits. Chaque index que vous ajoutez à une table agit comme un frein sur les performances d'écriture. Cette pénalité se manifeste sous la forme d'une **amplification d'écriture** (Write Amplification).

### Divisions de pages (Page Splits) dans les arbres B+
Les pages de base de données sont allouées avec un espace libre (fillfactor) pour permettre les futures mises à jour. Lorsque vous insérez une ligne, la base de données doit l'écrire dans le nœud d'index B-Tree approprié. Si la page du nœud d'index est pleine, une **division de page** (Page Split) se produit :
1.  Une nouvelle page est allouée.
2.  Environ 50 % des clés de la page pleine sont déplacées vers la nouvelle page.
3.  La page parente est mise à jour avec un pointeur vers la nouvelle page.

Cette opération nécessite plusieurs écritures sur disque et provoque une fragmentation de l'index, ce qui dégrade les performances de lecture séquentielle.

### Vacuuming vs Purge Lag
Lorsque des lignes sont mises à jour ou supprimées, l'espace occupé par les anciens enregistrements ne peut pas être immédiatement réutilisé en raison du MVCC (Multi-Version Concurrency Control).

*   **Postgres (Autovacuum) :** Lorsqu'un UPDATE se produit, Postgres écrit un nouveau tuple dans le Heap et met à jour les index pour pointer vers lui. Cela laisse un \"tuple mort\" (dead tuple) dans la page Heap. Si le démon autovacuum ne parvient pas à suivre le nettoyage de ces tuples morts, la table et ses index gonflent (bloat), entraînant des dégradations massives des performances de la mémoire et du disque.
*   **MySQL InnoDB (Purge Lag) :** Dans InnoDB, les mises à jour sont effectuées sur place dans l'index clusterisé, et les anciennes versions sont écrites dans l'Undo Log. Cependant, les index secondaires sont modifiés en marquant l'ancien enregistrement comme \"supprimé\" et en insérant le nouvel enregistrement. Un thread en arrière-plan appelé le **Purge Thread** est responsable de la suppression de ces enregistrements d'index secondaires marqués comme supprimés. Si les écritures sont trop lourdes, le retard de purge (**Purge Lag**) augmente, consommant de l'espace de stockage et provoquant des ralentissements des requêtes.

---

## 5. Erreurs courantes

### Erreur 1 : Mauvais ordre des colonnes dans les index composites
**Mauvaise pratique :**
Le développeur s'attend à ce que l'index optimise les requêtes sur les deux colonnes, mais place la colonne de requête de plage en premier.
```sql
-- database/queries/bad_composite_order.sql
-- Index défini comme : (created_at, user_id)
CREATE INDEX idx_bad_order ON orders (created_at, user_id);

-- Requête :
SELECT * FROM orders 
WHERE created_at >= '2026-01-01 00:00:00' 
  AND user_id = 42;
```
*Pourquoi c'est mauvais :* Le filtre de plage sur `created_at` empêche la base de données d'utiliser la partie `user_id` de l'index pour localiser les lignes correspondantes. Le moteur doit scanner l'index pour tous les enregistrements postérieurs à la date, en vérifiant chaque ligne pour l'ID utilisateur.

**Bonne pratique :**
```sql
-- database/queries/good_composite_order.sql
-- Index défini comme : (user_id, created_at)
CREATE INDEX idx_good_order ON orders (user_id, created_at);

-- Requête :
SELECT * FROM orders 
WHERE user_id = 42 
  AND created_at >= '2026-01-01 00:00:00';
```
*Pourquoi c'est bon :* Le filtre d'égalité exacte sur `user_id` permet à la base de données de sauter instantanément aux enregistrements de l'utilisateur, puis d'utiliser les nœuds feuilles ordonnés de l'index `created_at` pour scanner la plage.

### Erreur 2 : Indexation des colonnes à faible cardinalité
**Mauvaise pratique :**
Ajouter un index sur une colonne booléenne (par exemple, `is_active` ou `has_paid`).
```sql
-- database/queries/bad_low_cardinality.sql
CREATE INDEX idx_orders_active ON orders (status); -- status a uniquement les valeurs 'pending', 'completed'
```
*Pourquoi c'est mauvais :* Si les valeurs de statut sont uniformément distribuées, l'index n'aide pas. L'optimiseur de requêtes évaluera le coût du parcours de l'index secondaire puis de la recherche d'E/S aléatoire vers l'index clusterisé/Heap, et décidera qu'un parcours séquentiel de la table est plus rapide. L'index reste inutilisé mais dégrade tout de même les performances d'écriture.

---

## 6. Quiz d'auto-évaluation

Testez votre compréhension des architectures d'index :

### Question 1
Supposons que vous ayez un index composite `idx_test (A, B, C)` sur une table. Laquelle des requêtes suivantes ne sera **PAS** en mesure d'utiliser l'index ?
1. `WHERE A = 1 AND B = 2`
2. `WHERE B = 2 AND C = 3`
3. `WHERE A = 1 AND C = 3`

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse : Requête 2 (`WHERE B = 2 AND C = 3`)**

**Explication :**
Selon la règle du préfixe le plus à gauche, la requête doit filtrer sur la première colonne de l'index (`A`) pour pouvoir l'utiliser. Comme la requête 2 ne filtre pas sur `A`, la base de données ne peut pas parcourir les nœuds racines de l'arbre B et doit effectuer un parcours complet de la table. La requête 3 *peut* utiliser l'index, mais seulement la partie `A` ; elle localisera les enregistrements où `A = 1`, puis les filtrera manuellement pour `C = 3`.
</details>

---

### Question 2
Pourquoi la mise à jour d'une colonne non indexée dans PostgreSQL déclenche-t-elle parfois une amplification d'écriture d'index, alors que dans MySQL InnoDB ce n'est pas le cas ?

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse :**
En raison de la différence entre les structures Heap et Index clusterisé.
*   Dans PostgreSQL, si un UPDATE ne peut pas effectuer une optimisation HOT (Heap Only Tuples) (par exemple s'il n'y a plus de place sur la page), une nouvelle version de la ligne est écrite sur une page différente. Cela modifie l'adresse physique (TID) de la ligne. Par conséquent, *tous* les index sur cette table doivent être mis à jour pour pointer vers le nouveau TID, provoquant une amplification d'écriture d'index.
*   Dans MySQL InnoDB, la ligne reste dans le même nœud feuille de l'index clusterisé (à moins que la clé primaire elle-même soit mise à jour). Les index secondaires pointent vers la valeur de la clé primaire, pas vers une adresse disque physique, ce qui signifie que leurs nœuds feuilles n'ont pas besoin d'être mis à jour.
</details>

---

### Question 3
Vous avez une table de 10 millions de lignes. Vous exécutez la requête suivante :
`SELECT user_id, status FROM orders WHERE user_id = 100500;`
L'index sur `user_id` est défini comme : `CREATE INDEX idx_user ON orders (user_id);`
Comment pouvez-vous optimiser cette requête pour éviter toute recherche de page de données de table ?

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse :**
Créez un **index couvrant** (covering index) qui inclut `status`.
*   Dans PostgreSQL : `CREATE INDEX idx_user_status ON orders (user_id) INCLUDE (status);`
*   Dans MySQL (ou PostgreSQL) : `CREATE INDEX idx_user_status ON orders (user_id, status);`

**Explication :**
En ajoutant `status` à l'index, toutes les colonnes interrogées dans les clauses `SELECT` et `WHERE` sont entièrement contenues dans la page d'index. Le moteur de base de données peut effectuer un **Index Only Scan**, en contournant complètement la recherche dans le Heap ou l'index clusterisé.
</details>
