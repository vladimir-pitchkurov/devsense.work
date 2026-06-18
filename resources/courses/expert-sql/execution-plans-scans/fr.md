# Plans d'exécution et types de balayage (Scans)

Pour optimiser les requêtes de base de données lentes, vous devez comprendre comment la base de données récupère les données. L'optimiseur de requêtes évalue plusieurs chemins d'exécution et construit un **Plan d'exécution** basé sur des estimations de coûts. En analysant les plans d'exécution à l'aide d'un `EXPLAIN`, vous pouvez identifier les goulots d'étranglement, tels que les balayages complets de table (full table scans), les mauvais ordres de jointure ou les index ignorés. PostgreSQL et MySQL utilisent une terminologie et des techniques de visualisation de plans différentes, mais leurs méthodes de balayage (scanning) sous-jacentes partagent des concepts clés.

---

## 1. Générer et lire des plans d'exécution

Les deux bases de données fournissent des outils pour inspecter la façon dont les requêtes sont exécutées.

### PostgreSQL : EXPLAIN et EXPLAIN ANALYZE
Dans Postgres, `EXPLAIN` renvoie le coût estimé par le planificateur. L'ajout d'un `ANALYZE` force la base de données à exécuter la requête, fournissant des temps d'exécution réels (runtimes) et les nombres de lignes réels.
* **Mesures de coût** : Représentées sous la forme `cost=startup..total` (par exemple, `cost=0.00..45.10`). Le coût est une unité relative (où `1.0` correspond au coût de lecture d'une seule page de manière séquentielle).
* **Temps d'exécution réels** : Indiqués en millisecondes.

```sql
-- PostgreSQL: Inspect actual execution stats
EXPLAIN (ANALYZE, BUFFERS, COSTS)
SELECT email FROM users WHERE status = 'active';
```
*L'option `BUFFERS` affiche les lectures de blocs de mémoire partagée (hits et lectures depuis le disque).*

### MySQL : EXPLAIN et EXPLAIN ANALYZE
MySQL utilisait historiquement un format tabulaire pour `EXPLAIN`. MySQL 8.0 a introduit `EXPLAIN ANALYZE`, qui affiche les plans sous forme d'arbre avec les coûts et durées d'exécution réels.

```sql
-- MySQL: Visualizing with Tree structure & actual runtimes
EXPLAIN ANALYZE
SELECT email FROM users WHERE status = 'active';
```

---

## 2. Types de balayage (Scans) dans PostgreSQL

PostgreSQL choisit parmi plusieurs méthodes de balayage en fonction de la présence d'index, de la taille de la table et de la sélectivité des données.

```
Flux de sélection de balayage PostgreSQL :
Sélectivité :  Élevée (1-2 lignes)     Moyenne (5-15 %)      Faible (Table entière)
Méthode :      [Index Scan]      ->   [Bitmap Scan]     ->  [Seq Scan]
```

### Balayage séquentiel (Seq Scan)
* **Description** : Lit le fichier du tas (heap) de la table entière du début à la fin, en évaluant la clause `WHERE` pour chaque ligne.
* **Quand il se produit** : Utilisé lorsque la requête n'a pas d'index correspondant, ou lorsque le planificateur estime que récupérer la majeure partie de la table est plus rapide que d'utiliser un index.

### Balayage d'index (Index Scan)
* **Description** : Parcourt l'index B-Tree pour trouver les emplacements (TID) des lignes correspondantes, puis récupère ces blocs spécifiques dans le tas de la table.
* **Inconvénient** : Si de nombreuses lignes correspondent, faire des allers-retours entre les pages d'index et les pages du tas provoque des goulots d'étranglement d'E/S aléatoires.

### Balayage d'index Bitmap & Balayage du tas Bitmap (Bitmap Index Scan & Bitmap Heap Scan)
* **Description** : Utilisé lorsque Postgres récupère un nombre modéré de lignes.
  1. Le **Bitmap Index Scan** parcourt l'index et construit une carte de bits (bitmap) des pages du tas correspondantes en mémoire, en triant les TID par ordre physique des pages.
  2. Le **Bitmap Heap Scan** lit les pages triées séquentiellement, évitant les E/S aléatoires et la double lecture de pages.

### Balayage d'index seul (Index Only Scan)
* **Description** : Récupère les données directement à partir des nœuds feuilles de l'index sans visiter le tas de la table.
* **Piège de la carte de visibilité (Visibility Map)** : Postgres doit vérifier la carte de visibilité (**Visibility Map**) pour s'assurer que les pages n'ont pas été modifiées par des transactions non nettoyées par le vacuum. Si une page est marquée « sale » (dirty), Postgres doit tout de même visiter le tas, ce qui dégrade les performances.

---

## 3. Types de balayage dans MySQL (InnoDB)

La colonne `type` de la sortie `EXPLAIN` de MySQL décrit comment les lignes sont récupérées. Les types, classés du plus rapide au plus lent, comprennent :

### const / system
* La table contient au plus une ligne correspondante (par exemple, lors de l'interrogation d'une clé primaire `PRIMARY KEY` ou d'un index unique `UNIQUE` avec une valeur constante). C'est extrêmement rapide.

### eq_ref
* Utilisé dans les jointures lorsque MySQL lit une ligne de cette table pour chaque combinaison de lignes de la table précédente (se produit avec les clés primaires ou uniques).

### ref
* Utilisé lorsque les lignes correspondent à un index non unique. Plusieurs lignes peuvent correspondre.

### range
* Utilise un index pour sélectionner une plage de lignes (par exemple, les requêtes utilisant `>`, `<`, `BETWEEN` ou `IN`).

### index (Balayage d'index complet)
* MySQL effectue un balayage complet de l'arborescence de l'index. C'est l'équivalent de l'Index Only Scan de PostgreSQL. Il évite de scanner l'espace réel de la table mais lit tout de même l'intégralité de l'index.

### ALL (Balayage de table complet)
* MySQL lit chaque ligne de la table à partir du disque. Il s'agit du type d'accès le plus lent, à éviter pour les grandes tables.

---

## 4. Matrice de comparaison des types de balayage

| Concept de balayage | Nom PostgreSQL | Type MySQL (InnoDB) | Description |
| :--- | :--- | :--- | :--- |
| **Balayage complet de table** | `Seq Scan` | `ALL` | Parcourt l'ensemble de la table ; E/S disque élevées. |
| **Recherche par index** | `Index Scan` | `ref` ou `range` | Parcourt l'index, puis récupère la ligne dans l'espace de table. |
| **Recherche d'index seul** | `Index Only Scan` | `index` | Récupère les données strictement depuis les nœuds feuilles de l'index. |
| **Recherche d'index groupée** | `Bitmap Index/Heap Scan` | N/A | Regroupe les TID par page pour optimiser l'accès au disque. |
| **Recherche de constante** | `Index Scan` (1 ligne) | `const` | Recherche instantanée sur un index unique. |

---

## 5. Résumé & Bonnes pratiques

1. **Utilisez toujours ANALYZE pour les données réelles** : L'instruction `EXPLAIN` standard ne montre que des estimations. Exécutez toujours `EXPLAIN ANALYZE` (dans des environnements sûrs) pour voir les nombres de lignes réels et l'utilisation de la mémoire.
2. **Attention à la dégradation de l'« Index Only Scan »** : Si un Index Only Scan sous Postgres affiche un nombre élevé d'accès au tas (heap fetches), exécutez `VACUUM` sur la table pour mettre à jour la carte de visibilité (Visibility Map).
3. **Évitez le type `ALL` dans MySQL** : Si une requête sur une grande table affiche `type: ALL` ou `Extra: Using join buffer`, ajoutez un index pour couvrir les colonnes de recherche.
4. **Mettez à jour les statistiques de table** : Si l'optimiseur choisit un mauvais type de balayage, les statistiques de la table sont peut-être obsolètes. Exécutez `ANALYZE TABLE ma_table;` dans MySQL ou `ANALYZE ma_table;` dans PostgreSQL.
