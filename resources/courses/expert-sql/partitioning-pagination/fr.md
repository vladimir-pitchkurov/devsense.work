# Partitionnement et pagination

À mesure que les tables grandissent pour atteindre des dizaines ou des centaines de millions de lignes, les requêtes standards et les structures simples de pagination commencent à échouer. L'exécution de mises à jour, de sauvegardes et de recherches d'index sur des ensembles de données massifs introduit une latence de disque élevée. Pour passer à l'échelle, les bases de données utilisent deux stratégies principales : le **partitionnement de table** (diviser une table massive en plus petits morceaux physiques sous le capot) et la **pagination efficace** (récupérer des enregistrements paginés sans scanner des millions de lignes d'offset).

---

## 1. Partitionnement de table : Règles déclaratives et élagage (Pruning)

Le partitionnement de table divise une seule table logique en plusieurs tables physiques enfants (partitions). La table parente sert d'interface de routage.

### Types de partitionnement
1. **Partitionnement par plage (Range Partitioning)** : Associe les lignes aux partitions en fonction d'une plage de valeurs (par exemple, partitionner une table de logs par mois).
2. **Partitionnement par liste (List Partitioning)** : Associe les lignes aux partitions en fonction de valeurs de clés explicites (par exemple, partitionner par code pays).
3. **Partitionnement par hachage (Hash Partitioning)** : Répartit les lignes sur un nombre fixe de partitions à l'aide d'une fonction de hachage modulo. Idéal pour égaliser les charges d'écriture.

### Partitionnement déclaratif de PostgreSQL
Depuis la version 10, PostgreSQL prend en charge le partitionnement déclaratif. Les partitions sont déclarées à l'aide de la clause `PARTITION BY`.

```sql
-- PostgreSQL: Declarative Range Partitioning
CREATE TABLE app_logs (
    id BIGINT NOT NULL,
    log_date DATE NOT NULL,
    message TEXT
) PARTITION BY RANGE (log_date);

-- Create individual partitions
CREATE TABLE app_logs_y2026m01 PARTITION OF app_logs
    FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');
CREATE TABLE app_logs_y2026m02 PARTITION OF app_logs
    FOR VALUES FROM ('2026-02-01') TO ('2026-03-01');
```

### Syntaxe de partitionnement de MySQL
MySQL implémente le partitionnement directement à l'intérieur de la définition de la table, sans nécessiter d'instructions distinctes pour les tables enfants.

```sql
-- MySQL: InnoDB Range Partitioning
CREATE TABLE app_logs (
    id BIGINT NOT NULL,
    log_date DATE NOT NULL,
    message TEXT,
    PRIMARY KEY (id, log_date)
) ENGINE=InnoDB
PARTITION BY RANGE COLUMNS(log_date) (
    PARTITION p2026m01 VALUES LESS THAN ('2026-02-01'),
    PARTITION p2026m02 VALUES LESS THAN ('2026-03-01')
);
```

### Élagage de partition (Partition Pruning)
Le principal avantage du partitionnement est l'**élagage de partition** (Partition Pruning). L'optimiseur de requêtes analyse le filtre de la clause `WHERE` et exclut les partitions qui ne peuvent pas contenir de lignes correspondantes, évitant ainsi des balayages complets de ces fichiers physiques.

```sql
-- Triggering Partition Pruning
EXPLAIN SELECT * FROM app_logs WHERE log_date = '2026-02-15';
-- PostgreSQL plan will only scan 'app_logs_y2026m02'
-- MySQL plan will list partitions: 'p2026m02'
```

> [!WARNING]
> **Restrictions sur les clés primaires**
> Dans MySQL et PostgreSQL, toute contrainte d'unicité ou clé primaire sur une table partitionnée **DOIT** inclure toutes les colonnes de la clé de partitionnement. Dans MySQL, vous ne pouvez pas avoir une clé primaire autonome `PRIMARY KEY (id)` si vous partitionnez par `log_date` ; elle doit être définie comme `PRIMARY KEY (id, log_date)`. Cela évite aux bases de données d'avoir à parcourir toutes les partitions pour appliquer l'unicité lors des insertions.

---

## 2. Pagination : Offset vs. Keyset (Curseur)

La récupération de listes de données par pages est une exigence essentielle de toute application. Cependant, l'approche SQL par défaut peut entraîner des goulots d'étranglement majeurs en matière de performances.

### Le piège de la pagination par OFFSET
* **Syntaxe** : `SELECT * FROM orders ORDER BY created_at DESC LIMIT 10 OFFSET 500000;`
* **Sous le capot** : Le moteur de base de données ne peut pas sauter directement à la ligne 500 000. Il doit parcourir l'index, lire les 500 000 lignes précédentes, les ignorer, puis renvoyer seulement les 10 lignes suivantes. Cela entraîne une utilisation élevée du processeur et des E/S disque.

### Pagination par Keyset (basée sur un curseur)
* **Syntaxe** : Au lieu des offsets, utilisez les dernières valeurs récupérées pour filtrer les requêtes suivantes.
  ```sql
  -- MySQL & PostgreSQL: Keyset Pagination
  SELECT * FROM orders 
  WHERE created_at < '2026-06-17 10:00:00' 
  ORDER BY created_at DESC 
  LIMIT 10;
  ```
* **Performances** : Avec un index composite sur `(created_at, id)`, la requête effectue une recherche d'index directement au point de départ, s'exécutant en temps $O(1)$ quel que soit le numéro de la page.

### Pagination Keyset multi-colonne (Tri sur des champs non uniques)
Si le champ de tri (comme `created_at` ou `price`) peut contenir des valeurs en double, vous devez ajouter une colonne unique de départage (généralement la clé primaire) pour éviter de sauter des lignes.
* **Syntaxe de comparaison de tuples (PostgreSQL)** :
  ```sql
  -- PostgreSQL supports row value comparisons natively
  SELECT * FROM orders
  WHERE (created_at, id) < ('2026-06-17 10:00:00', 9876)
  ORDER BY created_at DESC, id DESC
  LIMIT 10;
  ```
* **Expansion logique explicite (MySQL)** :
  *MySQL 8.0 prend en charge la comparaison de valeurs de lignes, mais les anciennes versions ou les optimiseurs peu performants l'exécutent de manière inefficace. Développez-la explicitement par sécurité :*
  ```sql
  -- MySQL Safe Keyset Expansion
  SELECT * FROM orders
  WHERE created_at < '2026-06-17 10:00:00'
     OR (created_at = '2026-06-17 10:00:00' AND id < 9876)
  ORDER BY created_at DESC, id DESC
  LIMIT 10;
  ```

> [!TIP]
> **Pagination multi-catégorie avec LATERAL JOIN**
> Si vous devez paginer et récupérer les « N premiers éléments par catégorie » (par exemple, les 3 meilleurs produits pour chacune des 20 catégories), un `GROUP BY` standard ne fonctionnera pas. Utilisez une jointure `LATERAL` (prise en charge dans PostgreSQL 10+ et MySQL 8.0.14+).
> ```sql
> SELECT c.name, p.title, p.price
> FROM categories c
> INNER JOIN LATERAL (
>     SELECT title, price 
>     FROM products 
>     WHERE category_id = c.id 
>     ORDER BY price DESC LIMIT 3
> ) p ON TRUE;
> ```
> *Cela exécute une sous-requête pilotée par index pour chaque catégorie, ce qui est extrêmement rapide par rapport aux balayages complets des fonctions de fenêtrage.*

---

## 3. Matrice de comparaison des fonctionnalités

| Fonctionnalité | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Définition du partitionnement** | Tables enfants déclaratives | Défini directement sur la table parente |
| **Routage des lignes** | Géré par le routage parent | Géré en interne par InnoDB |
| **Restriction PK** | La PK doit contenir la clé de partition | La PK doit contenir la clé de partition |
| **Comparaison de valeurs de lignes** | Nativement optimisée (ex. `(a, b) > (x, y)`) | Prise en charge mais pièges d'optimisation possibles |
| **Jointures latérales** | Prise en charge (PostgreSQL 9.3+) | Prise en charge (MySQL 8.0.14+) |

---

## 4. Résumé & Bonnes pratiques

1. **Partitionnez par date pour l'archivage** : Le partitionnement par plage est parfait pour les journaux de transactions. Lorsque les données deviennent obsolètes, vous pouvez supprimer la partition à l'aide de `DROP TABLE nom_partition` (instantané) au lieu d'exécuter un `DELETE` massif (lent, génère un gonflement de l'espace d'annulation).
2. **N'utilisez jamais d'offsets élevés** : Implémentez la pagination par keyset pour le défilement infini ou les listes paginées.
3. **Indexez vos clés de pagination** : Assurez-vous toujours que vos filtres de pagination par keyset correspondent à un index B-Tree composite (par exemple, un index sur `(created_at, id)`).
