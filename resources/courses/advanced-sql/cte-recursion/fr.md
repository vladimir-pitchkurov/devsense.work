# Expressions de table communes (CTE) & Récursion

Imaginez-vous en train de déboguer une requête SQL de 200 lignes remplie de sous-requêtes profondément imbriquées, où la même sous-requête est dupliquée trois fois uniquement pour effectuer des jointures sur elle-même. C'est un cauchemar de maintenance, et les analyseurs de plans de requêtes des bases de données ont du mal à l'optimiser. Ou pensez à la modélisation d'un organigramme d'entreprise (managers et subordonnés) ou d'un arbre de catégories de produits à plusieurs niveaux. Dans des langages impératifs comme PHP ou JavaScript, vous récupéreriez toutes les lignes pour exécuter des boucles récursives. Mais faire cela à travers le réseau est lent et très inefficace. C'est là que les **expressions de table communes (Common Table Expressions - CTE)** et les **CTE récursives** viennent à la rescousse.

Une CTE agit comme un jeu de résultats temporaire et nommé qui n'existe que dans la portée d'exécution d'une seule requête. Elle fonctionne comme une vue en ligne dynamique et lisible.

---

## 1. CTE non récursives & séquentielles

### Syntaxe et structure
* **Concept** : Les CTE vous permettent de définir des jeux de résultats temporaires à l'aide de la clause `WITH` avant votre instruction principale `SELECT`, `INSERT`, `UPDATE` ou `DELETE`.
* **Pourquoi c'est important** : Cela décompose les requêtes complexes en étapes logiques et lisibles, remplaçant les sous-requêtes imbriquées et rendant le code auto-documenté.
* **Exemple** :
  ```sql
  -- MySQL & PostgreSQL
  WITH regional_sales AS (
      SELECT region, SUM(amount) AS total_sales
      FROM orders
      GROUP BY region
  ),
  top_regions AS (
      SELECT region
      FROM regional_sales
      WHERE total_sales > 100000
  )
  SELECT o.employee_id, o.amount, o.region
  FROM orders o
  JOIN top_regions t ON o.region = t.region;
  ```
* **Conséquence** : La requête se lit de manière linéaire du haut vers le bas. Vous évitez de dupliquer les sous-requêtes, et le débogage devient aussi simple que d'effectuer une sélection à partir d'une seule CTE.

> [!TIP]
> **Le saviez-vous ?**
> Les CTE peuvent être utilisées pour isoler les opérations d'écriture. Dans PostgreSQL, vous pouvez écrire des données dans une CTE et sélectionner les identifiants résultants pour les insérer dans une autre table au sein de la même requête.
> ```sql
> -- PostgreSQL Only: Writing in a CTE
> WITH inserted_user AS (
>     INSERT INTO users (name, email)
>     VALUES ('Alice', 'alice@devsense.work')
>     RETURNING id
> )
> INSERT INTO profiles (user_id, bio)
> SELECT id, 'Software Engineer' FROM inserted_user;
> ```
> *MySQL ne prend pas en charge les instructions de modification de données (INSERT/UPDATE/DELETE) dans les CTE.*

---

## 2. CTE récursives (`WITH RECURSIVE`)

Lorsque vous devez parcourir des structures de données hiérarchiques, les jointures SQL standard échouent car la profondeur de l'arbre est inconnue. Les **CTE récursives** résolvent ce problème en exécutant une requête de manière répétée jusqu'à ce qu'aucune nouvelle ligne ne soit renvoyée.

### L'anatomie de la récursion
Une CTE récursive se compose de trois parties :
1. **Membre d'ancrage (Anchor Member)** : La requête de base qui initialise le jeu de résultats (s'exécute une fois).
2. **Membre récursif (Recursive Member)** : La requête qui fait référence à la CTE elle-même et qui est jointe au résultat de l'étape précédente.
3. **Condition de terminaison** : Déclenchée implicitement lorsque le membre récursif renvoie zéro ligne.

```
Flux d'exécution :
[Requête d'ancrage] ---> Lignes initiales
         |
         +---> [Requête récursive] (S'exécute sur les lignes d'ancrage) ---> Lignes de l'étape 1
                     |
                     +---> [Requête récursive] (S'exécute sur les lignes de l'étape 1) ---> Lignes de l'étape 2
                                 |
                                 +---> Renvoie un ensemble vide ---> TERMINER
```

### Exemple pratique : Hiérarchie d'organisation
* **Concept** : Parcourir récursivement une table contenant des relations parent-enfant.
* **Exemple** :
  ```sql
  -- MySQL & PostgreSQL
  WITH RECURSIVE org_chart AS (
      -- 1. Anchor: Find the CEO
      SELECT id, name, manager_id, 1 AS depth
      FROM employees
      WHERE manager_id IS NULL
      
      UNION ALL
      
      -- 2. Recursive Member: Join employees with their managers
      SELECT e.id, e.name, e.manager_id, o.depth + 1
      FROM employees e
      INNER JOIN org_chart o ON e.manager_id = o.id
  )
  SELECT * FROM org_chart ORDER BY depth;
  ```
* **Conséquence** : Vous récupérez l'arbre entier des subordonnés, avec leur niveau de profondeur, en une seule requête exécutée entièrement dans la base de données.

> [!WARNING]
> **Protection contre les boucles infinies !**
> Si vos données contiennent une référence circulaire (par exemple, l'employé A rapporte à B, B rapporte à C, C rapporte à A), une CTE récursive s'exécutera à l'infini, provoquant l'épuisement de la mémoire du serveur.
> - **PostgreSQL** fournit la clause `CYCLE` pour empêcher les boucles :
>   `CYCLE id SET is_cycle USING path`
> - **MySQL** ne dispose pas de la clause `CYCLE` mais vous permet de limiter la profondeur de récursion globalement ou par requête :
>   `SET max_sp_recursion_depth = 255;` ou en utilisant des indices d'optimisation (hints) : `/*+ MAX_EXECUTION_TIME(1000) */`

---

## 3. Fonctionnement interne : Matérialisation & Optimisation

Comment les moteurs de bases de données exécutent-ils les CTE ? Les exécutent-ils comme des tables temporaires, ou copient-ils/collent-ils leur code SQL directement dans la requête principale ? Les moteurs se comportent différemment :

### Règles de matérialisation de PostgreSQL
* **PostgreSQL < 12** : Historiquement, Postgres traitait toutes les CTE comme des « barrières d'optimisation » (optimization fences). Il exécutait toujours la CTE en premier, enregistrait les résultats dans une table temporaire (la matérialisait), puis la joignait. Cela empêchait l'optimiseur de propager les filtres `WHERE` externes dans la CTE, ce qui entraînait des goulots d'étranglement majeurs en matière de performances.
* **PostgreSQL 12+** : A modifié le comportement par défaut en **NOT MATERIALIZED**. Si une CTE n'est appelée qu'une seule fois, Postgres fusionne sa logique dans la requête principale (en l'intégrant) afin de pouvoir utiliser les scans d'index.
* **Surcharge manuelle** :
  - `WITH cte AS MATERIALIZED (...)` force Postgres à l'évaluer une fois et à mettre le résultat en cache.
  - `WITH cte AS NOT MATERIALIZED (...)` force Postgres à l'intégrer en ligne (inlining).

### Calcul du coût en ligne de MySQL
* **MySQL 8.0** : Utilise un optimiseur basé sur les coûts pour décider d'intégrer une CTE ou de la matérialiser dans une table temporaire. Si la CTE est simple, MySQL l'intègre toujours. Si elle est référencée plusieurs fois, MySQL la matérialise pour éviter des exécutions répétées.

---

## 4. Résumé & Bonnes pratiques

1. **Utilisez les CTE pour la lisibilité** : Remplacez les sous-requêtes imbriquées complexes par des CTE séquentielles et nommées.
2. **Attention aux barrières d'optimisation** : Si vous utilisez PostgreSQL, faites attention aux références multiples à la même CTE. Utilisez `AS NOT MATERIALIZED` si vous souhaitez que l'optimiseur applique les scans d'index.
3. **Protégez-vous contre l'emballement des cycles** : Vérifiez toujours la présence de références circulaires dans vos données avant d'exécuter un `WITH RECURSIVE`, ou définissez des limites d'exécution.
