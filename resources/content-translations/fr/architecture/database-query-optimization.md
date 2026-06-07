---
title: "Optimisation des requêtes de base de données : Master Class | DevSense"
description: "Apprenez à lire EXPLAIN et EXPLAIN ANALYZE, à optimiser les JOINs et les CTEs, à implémenter la pagination keyset, et à comprendre les comportements de charge MVCC des moteurs de bases de données."
published: 2026-05-31
faq:
  - question: "Quelle est la différence entre un scan séquentiel (Sequential Scan) et un scan d'index (Index Scan) ?"
    answer: "Un scan séquentiel parcourt chaque page de la table du début à la fin pour trouver les lignes correspondantes. Un scan d'index traverse l'arbre de l'index pour localiser les entrées correspondantes, puis ne récupère que les pages correspondantes du heap. Pour les petites tables ou les requêtes récupérant un grand pourcentage de lignes, le Seq Scan est souvent plus rapide que les E/S aléatoires d'un Index Scan."
  - question: "Que sont les Nested Loops, les Hash Joins et les Merge Joins ?"
    answer: "Les Nested Loops joignent des tables en scannant la table externe et en recherchant les lignes correspondantes dans la table interne (optimal pour les petits ensembles). Les Hash Joins construisent une table de hachage en mémoire à partir de la plus petite relation et scannent la plus grande relation pour trouver des correspondances (optimal pour les grands ensembles non ordonnés). Les Merge Joins trient les deux relations sur la clé de jointure et les scannent en parallèle (optimal pour les grands ensembles pré-triés ou lorsque les scans d'index peuvent éviter le tri)."
  - question: "Pourquoi la pagination keyset est-elle plus rapide que LIMIT/OFFSET ?"
    answer: "Les requêtes LIMIT/OFFSET doivent lire et rejeter toutes les lignes jusqu'à la valeur de décalage (offset), ce qui entraîne une dégradation des performances en O(N) à mesure que l'on s'enfonce dans les pages. La pagination keyset utilise un filtre WHERE sur une colonne unique triée (comme `WHERE id > ?`) pour sauter directement à la ligne cible à l'aide d'un index, réalisant ainsi une recherche en temps constant O(log N)."
  - question: "Qu'est-ce qu'une barrière d'optimisation (optimization fence) pour les CTE ?"
    answer: "Dans les anciennes versions de Postgres (antérieures à la 12), et éventuellement dans les plus récentes en utilisant `MATERIALIZED`, les Common Table Expressions (CTEs) agissent comme une barrière d'optimisation : la base de données exécute le CTE en premier, sauvegarde le résultat dans un espace temporaire, puis effectue la jointure. Cela empêche l'optimiseur de pousser les prédicats de la requête externe (comme les clauses WHERE) vers le bas à l'intérieur du CTE (push-down), ce qui peut ralentir l'exécution."
---

# Optimisation des requêtes de base de données : Master Class

Une seule requête lente peut paralyser une application d'entreprise entière. En production, les performances d'une base de données dépendent rarement de la puissance du processeur (CPU) ; elles dépendent de l'efficacité avec laquelle le planificateur de requêtes parcourt les pages du disque, construit les tables de jointure en mémoire et gère la concurrence.

Lorsque vous écrivez une requête SQL, vous décrivez *quelles* données vous voulez, et non *comment* les obtenir. C'est l'optimiseur de requêtes du moteur de base de données qui est chargé de tracer le plan d'exécution. Pour concevoir des applications performantes, vous devez apprendre à penser comme l'optimiseur. Dans cette master class, nous examinerons des plans d'exécution, décortiquerons des algorithmes de jointure, explorerons la mise à l'échelle de la pagination et analyserons les mécanismes MVCC de PostgreSQL et MySQL.

---

## 1. Décoder EXPLAIN et EXPLAIN ANALYZE

Pour optimiser une requête, vous devez d'abord inspecter son plan d'exécution à l'aide d'`EXPLAIN`. Cependant, un `EXPLAIN` standard ne montre que l'*estimation* du coût de la requête par l'optimiseur. Pour voir ce qui s'est réellement passé pendant l'exécution, vous devez utiliser `EXPLAIN ANALYZE` (qui exécute réellement la requête).

### Comprendre les indicateurs de coût (format Postgres)
Un plan d'exécution affiche les coûts sous la forme : `cost=0.00..431.25 rows=10500 width=244`.
*   **Coût de démarrage (`0.00`) :** Le coût encouru avant que la première ligne puisse être renvoyée (par exemple, la construction d'une table de hachage ou le tri des nœuds d'index).
*   **Coût total (`431.25`) :** Le coût estimé pour renvoyer toutes les lignes. Il est mesuré en unités arbitraires de lectures de pages (généralement `1.0` pour une lecture séquentielle de page, `4.0` pour une lecture aléatoire de page).
*   **Lignes (`10500`) :** Le nombre estimé de lignes renvoyées (rows).
*   **Largeur (`244`) :** La taille moyenne en octets des lignes renvoyées (width).

### Types de scan de nœuds
1.  **Séquential Scan (Seq Scan) :** Le moteur lit la table entière du début à la fin. Il est optimal pour les petites tables ou lors de la récupération de $> 20\%$ des lignes de la table.
2.  **Index Scan :** Le moteur parcourt l'arbre B de l'index, récupère les TIDs/clés primaires correspondants, puis récupère les pages correspondantes dans le Heap/la table. Cela implique des accès disque aléatoires.
3.  **Index Only Scan :** Si toutes les colonnes sélectionnées se trouvent dans l'index lui-même, la base de données contourne complètement la lecture de la table.
4.  **Bitmap Index Scan & Bitmap Heap Scan (Postgres) :** Lors de la récupération de plusieurs lignes correspondantes via un index, Postgres construit d'abord un bitmap des pages correspondantes en mémoire (Bitmap Index Scan) puis lit ces pages dans l'ordre physique séquentiel (Bitmap Heap Scan). Cela convertit les E/S aléatoires lentes en E/S séquentielles plus rapides.

### Algorithmes de jointure
*   **Nested Loop :** La base de données parcourt la table externe et, pour chaque ligne, recherche les lignes correspondantes dans la table interne. C'est extrêmement rapide pour les petits ensembles de données, en particulier si la colonne de jointure de la table interne est indexée.
*   **Hash Join :** La base de données parcourt la table la plus petite, construit une table de hachage en mémoire à partir des clés de jointure, puis parcourt la table la plus grande en hachant ses clés pour trouver des correspondances immédiates. C'est optimal pour les grands ensembles de données non ordonnés.
*   **Merge Join :** Les deux tables sont triées par leurs clés de jointure, et la base de données les parcourt en parallèle, en fusionnant les lignes correspondantes. C'est très efficace pour les très grands ensembles de données, en particulier s'ils sont déjà pré-triés par des index.

---

## 2. Optimisation avancée des jointures et CTEs

Toutes les jointures ne se valent pas. La façon dont vous écrivez vos sous-requêtes et vos CTEs influence fortement la capacité de l'optimiseur à élaguer les chemins d'exécution.

### Inner vs Outer Joins et Anti-Joins
Un anti-join trouve les enregistrements d'une table qui n'existent *pas* dans une autre. Les développeurs écrivent souvent cela en utilisant `NOT IN`, ce qui offre de mauvaises performances et échoue si la sous-requête renvoie `NULL`. Une approche hautement optimisée consiste à utiliser `NOT EXISTS`.

```sql
-- database/queries/anti_join_bad.sql
-- Mauvais : NOT IN parcourt la sous-requête et se comporte de manière inattendue si des NULLs sont présents
SELECT id, name FROM users 
WHERE id NOT IN (SELECT user_id FROM orders);

-- database/queries/anti_join_good.sql
-- Bon : NOT EXISTS se traduit par un Hash Anti Join ou un Merge Anti Join hautement optimisé
SELECT u.id, u.name 
FROM users u
WHERE NOT EXISTS (
    SELECT 1 
    FROM orders o 
    WHERE o.user_id = u.id
);
```

### Jointures LATERAL (sous-requêtes corrélées)
Une jointure `LATERAL` agit comme une boucle `foreach` en SQL. Elle permet à une sous-requête de référencer des colonnes des tables précédentes dans la clause `FROM`. C'est incroyablement utile pour récupérer les \"Top N\" enregistrements par groupe.

```sql
-- database/queries/lateral_join.sql
-- Récupérer les 3 dernières commandes de chaque utilisateur (Postgres & MySQL 8.0.14+)
SELECT u.id, u.name, recent_orders.id AS order_id, recent_orders.amount, recent_orders.created_at
FROM users u
CROSS JOIN LATERAL (
    SELECT o.id, o.amount, o.created_at
    FROM orders o
    WHERE o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 3
) AS recent_orders;
```

### Barrières d'optimisation des CTE
Les expressions de table communes (CTEs) rendent le code des requêtes propre, mais elles ont historiquement agi comme des **barrières d'optimisation**.
*   **Postgres (avant la version 12) :** Les CTEs étaient toujours matérialisés (calculés et écrits dans un espace temporaire) avant d'exécuter la requête externe. Cela empêchait l'utilisation d'index à partir des clauses WHERE externes.
*   **Postgres (version 12+) :** Les CTEs sont désormais intégrés (inlined) par défaut, à moins qu'ils ne soient récursifs ou qu'ils n'aient des effets secondaires. Vous pouvez explicitement forcer ou empêcher la matérialisation à l'aide de `MATERIALIZED` ou `NOT MATERIALIZED`.

```sql
-- database/queries/cte_fence.sql
-- Empêcher la matérialisation pour permettre au planificateur de requêtes de pousser le prédicat user_id vers le bas
WITH user_stats AS NOT MATERIALIZED (
    SELECT user_id, COUNT(*) as total_orders, SUM(amount) as total_spent
    FROM orders
    GROUP BY user_id
)
SELECT u.name, s.total_orders, s.total_spent
FROM users u
JOIN user_stats s ON u.id = s.user_id
WHERE u.id = 42;
```

---

## 3. Mise à l'échelle des chemins de requêtes : pagination keyset et partitionnement

À mesure que vos tables de bases de données grandissent pour atteindre des milliards de lignes, les requêtes simples se dégradent à moins que vous ne changiez la manière de parcourir les données.

### Pagination Keyset (basée sur un curseur) vs. LIMIT / OFFSET
La pagination offset (`LIMIT 10 OFFSET 100000`) oblige la base de données à lire et rejeter 100 000 lignes uniquement pour en renvoyer 10. Les performances diminuent linéairement ($O(N)$), se dégradant fortement sur les pages profondes.
La pagination keyset filtre les lignes déjà vues à l'aide d'une clause WHERE sur un index trié, offrant ainsi un temps de recherche constant en $O(\log N)$.

```sql
-- database/queries/offset_pagination_bad.sql
-- Mauvais : Parcourt et rejette 100 000 enregistrements avant de renvoyer les 10 suivants
SELECT id, amount, created_at
FROM orders
ORDER BY created_at DESC, id DESC
LIMIT 10 OFFSET 100000;

-- database/queries/keyset_pagination_good.sql
-- Bon : Saute directement au dernier keyset vu en utilisant un scan d'index composite
SELECT id, amount, created_at
FROM orders
WHERE (created_at, id) < ('2026-05-30 15:30:00', 987654)
ORDER BY created_at DESC, id DESC
LIMIT 10;
```

> [!NOTE]
> La pagination keyset nécessite un tri déterministe. Si vous triez sur une colonne non unique comme `created_at`, vous devez ajouter une colonne unique comme pivot (par exemple, `id`) pour éviter les lignes manquantes ou dupliquées.

### Partitionnement de table
Le partitionnement de table consiste à diviser une grande table en tables physiques plus petites (partitions) basées sur une clé (par exemple, des plages de dates), tout en conservant une interface logique unique.
*   **Élagage des requêtes (Query Pruning) :** Lorsqu'une requête filtre par la clé de partition, le planificateur ignore entièrement les partitions non concernées pour ne scanner que la partition correspondante.

```sql
-- database/migrations/2026_05_31_partitioned_orders.sql
-- Partitionnement déclaratif par plage (Range) sous PostgreSQL
CREATE TABLE orders_partitioned (
    id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    PRIMARY KEY (created_at, id)
) PARTITION BY RANGE (created_at);

-- Créer les tables de partitions individuelles
CREATE TABLE orders_2026_q1 PARTITION OF orders_partitioned
    FOR VALUES FROM ('2026-01-01 00:00:00') TO ('2026-04-01 00:00:00');
```

---

## 4. MVCC, Vacuuming et Purge Lag

La concurrence dans les bases de données modernes est obtenue grâce au **Multi-Version Concurrency Control (MVCC)**. Au lieu de verrouiller les lignes pour la lecture, les bases de données conservent plusieurs versions d'une ligne en même temps.

### PostgreSQL MVCC & Autovacuum
Dans Postgres, chaque ligne (tuple) est stockée sur disque avec des en-têtes de métadonnées contenant notamment `xmin` (l'ID de transaction qui a créé la ligne) et `xmax` (l'ID de transaction qui a supprimé/expiré la ligne).
*   **Mises à jour (Updates) :** Un `UPDATE` n'écrase pas la ligne. Il écrit un tuple complètement nouveau sur le heap et définit `xmax` sur l'ancien tuple pour pointer vers la transaction de mise à jour.
*   **Bloat (Gonflement) :** L'ancienne version de la ligne devient un \"tuple mort\" (dead tuple) dès qu'aucune transaction active ne peut plus la voir.
*   **Vacuuming :** Postgres nécessite un `VACUUM` (géré par le démon autovacuum) pour scanner les pages, récupérer l'espace occupé par les tuples morts, et mettre à jour la carte de visibilité (visibility map). Si l'autovacuum ne parvient pas à suivre les charges d'écriture importantes, le gonflement des tables et des index dégradera les performances des requêtes.

### MySQL InnoDB MVCC & Undo Logs
Le moteur InnoDB de MySQL gère le MVCC différemment.
*   **Mises à jour dans l'index clusterisé :** InnoDB effectue les mises à jour directement sur place dans le nœud feuille de l'index clusterisé.
*   **Undo Logs & rollback segments :** L'ancienne version de la ligne n'est pas stockée dans la table principale. À la place, InnoDB écrit l'état historique de la ligne dans l'**Undo Log** et stocke un pointeur de rollback dans la ligne mise à jour. Lorsqu'une transaction de lecture a besoin d'une version plus ancienne, elle lit la ligne actuelle et reconstruit l'ancien état à l'aide des journaux d'annulation (undo logs).
*   **Threads de purge :** À mesure que les anciennes transactions se terminent, un processus en arrière-plan appelé **Purge Thread** nettoie les undo logs et supprime les enregistrements marqués pour suppression. Sous de lourdes charges d'écriture, un **Purge Lag** (retard de purge) peut se produire, entraînant une croissance massive des fichiers d'undo logs et une dégradation des performances.

---

## 5. Erreurs courantes

### Erreur 1 : Pagination profonde avec OFFSET
**Mauvaise pratique :**
```sql
-- database/queries/bad_pagination.sql
SELECT * FROM users ORDER BY id LIMIT 20 OFFSET 50000;
```
*Pourquoi c'est mauvais :* La base de données doit scanner l'index/heap pour 50 000 enregistrements, les charger en mémoire puis les rejeter, ce qui consomme inutilement du CPU et des E/S disque.

**Bonne pratique :**
```sql
-- database/queries/good_pagination.sql
-- Pagination keyset utilisant le dernier ID vu (par exemple, 50000)
SELECT * FROM users WHERE id > 50000 ORDER BY id LIMIT 20;
```
*Pourquoi c'est bon :* La base de données effectue une recherche d'index (seek) pour sauter directement à `id > 50000` en un temps $O(\log N)$, évitant ainsi les parcours de lignes inutiles.

### Erreur 2 : Index manquant sur les clés étrangères
**Mauvaise pratique :**
```sql
-- database/migrations/bad_foreign_key.sql
CREATE TABLE orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```
*Pourquoi c'est mauvais :* PostgreSQL et MySQL ne créent pas automatiquement d'index sur las colonnes de clés étrangères. Si vous joignez fréquemment des tables sur `user_id` ou supprimez des utilisateurs (ce qui déclenche des vérifications en cascade), le moteur devra effectuer un scan séquentiel sur la table enfant.

---

## 6. Quiz d'auto-évaluation

### Question 1
Lors d'une exécution de `EXPLAIN ANALYZE` dans Postgres, vous remarquez un nœud nommé `Bitmap Heap Scan` faisant suite à un `Bitmap Index Scan`. Qu'est-ce que cela indique ?

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse :**
Cela indique que Postgres a estimé que la lecture directe des lignes via des recherches d'index individuelles entraînerait des E/S aléatoires trop lentes. À la place :
1. Le `Bitmap Index Scan` a scanné l'index et construit en mémoire un bitmap des adresses des pages de la table contenant les lignes correspondantes.
2. Le `Bitmap Heap Scan` a ensuite lu ces pages de table dans l'ordre physique séquentiel, accédant aux lignes correspondantes sur ces pages. Cela convertit les E/S aléatoires en lectures physiques séquentielles plus rapides.
</details>

---

### Question 2
Sous une charge d'écriture importante dans MySQL InnoDB, pourquoi la taille de l'espace de table des undo logs gonfle-t-elle, et comment cela affecte-t-il les requêtes de lecture qui ont besoin de données plus anciennes ?

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse :**
Lorsque les écritures sont très intensives, le thread de purge (Purge Thread) peut prendre du retard (Purge Lag) dans le nettoyage des segments de rollback historiques. Par conséquent, l'espace d'undo logs croît rapidement pour conserver la trace des anciennes versions des lignes. Les transactions de lecture plus anciennes subiront des baisses de performances car elles devront parcourir une longue chaîne d'undo logs pour reconstruire l'état des données au moment du démarrage de leur transaction.
</details>

---

### Question 3
Pourquoi un CTE défini avec une clause `WITH` standard dans Postgres 10 peut-il provoquer un goulot d'étranglement des performances si la requête externe filtre par un ID spécifique ?

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse :**
Dans Postgres 10 (et les versions antérieures), les CTEs agissent comme une barrière d'optimisation. Le moteur évalue le CTE indépendamment et matérialise l'intégralité de ses résultats en mémoire ou sur disque. Ce n'est qu'ensuite qu'il exécute la requête externe et applique le filtre `WHERE id = 42`. Dans Postgres 12+ (ou en écrivant `WITH ... AS NOT MATERIALIZED`), l'optimiseur peut intégrer (inline) le CTE, ce qui lui permet de pousser le filtre `WHERE id = 42` vers le bas à l'intérieur de la sous-requête, autorisant ainsi des scans pilotés par index.
</details>
