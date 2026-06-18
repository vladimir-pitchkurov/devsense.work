# Agrégats et regroupement

Jusqu'à présent, nous n'avons récupéré que des lignes individuelles. Dans de nombreux scénarios, cependant, vous devez analyser les données à un niveau supérieur : trouver le salaire moyen d'un employé, compter le nombre total de commandes ou identifier le produit le plus cher dans une catégorie spécifique.

SQL fournit de puissantes **fonctions d'agrégation** et la clause `GROUP BY` pour condenser des milliers de lignes en résumés significatifs. Dans ce chapitre, nous allons maîtriser le regroupement de données, le filtrage de ces groupes à l'aide de la clause `HAVING`, et examiner comment MySQL et PostgreSQL diffèrent dans leur exécution.

---

## 1. Fonctions d'agrégation

Les fonctions d'agrégation effectuent un calcul sur un ensemble de valeurs et renvoient une seule valeur.

* **`COUNT()`** : Renvoie le nombre de lignes.
* **`SUM()`** : Renvoie la somme des valeurs numériques.
* **`AVG()`** : Renvoie la moyenne des valeurs numériques.
* **`MIN()`** : Renvoie la valeur la plus basse.
* **`MAX()`** : Renvoie la valeur la plus élevée.

```sql
-- MySQL & PostgreSQL
-- Calculate count, average price, and max price for all products
SELECT COUNT(*) AS total_products, AVG(price) AS average_price, MAX(price) AS highest_price 
FROM products;
```

### Fonctions d'agrégation et valeurs `NULL`
C'est une idée reçue courante que les fonctions d'agrégation incluent les valeurs `NULL` dans leurs calculs.
* La plupart des fonctions d'agrégation (comme `SUM`, `AVG`, `MIN`, `MAX`) **ignorent complètement les valeurs NULL**.
* `COUNT(column_name)` compte uniquement les lignes où la colonne spécifiée n'est **pas NULL**.
* `COUNT(*)` compte toutes les lignes, y compris les lignes avec des valeurs `NULL`.

```sql
-- If we have 5 users, and only 3 have a middle name:
SELECT COUNT(*) FROM users;            -- Returns 5
SELECT COUNT(middle_name) FROM users;  -- Returns 3
```

---

## 2. Regrouper des données avec `GROUP BY`

La clause `GROUP BY` divise les lignes d'une table en groupes. Le moteur de base de données applique ensuite les fonctions d'agrégation à chaque groupe indépendamment.

```sql
-- MySQL & PostgreSQL
-- Get average price per product category
SELECT category, AVG(price) AS avg_price 
FROM products 
GROUP BY category;
```

---

## 3. Filtrer les groupes avec `HAVING`

Et si vous vouliez trouver uniquement les catégories dont le prix moyen est supérieur à 100 $ ? Vous pourriez essayer d'écrire :
```sql
-- THIS WILL FAIL!
SELECT category, AVG(price) FROM products WHERE AVG(price) > 100 GROUP BY category;
```
Cela échoue car la clause `WHERE` filtre les lignes **avant** qu'elles ne soient regroupées et agrégées. Le moteur de base de données ne connaît pas encore le prix moyen lors de l'évaluation de la condition `WHERE`.

Pour filtrer les groupes, vous devez utiliser la clause `HAVING`, qui s'exécute **après** le regroupement.

| Clause | Lieu de filtrage | Peut utiliser des agrégats ? |
| :--- | :--- | :--- |
| **`WHERE`** | Lignes individuelles (avant regroupement). | Non |
| **`HAVING`** | Résultats regroupés (après regroupement). | Oui |

```sql
-- MySQL & PostgreSQL
-- Correct way to filter aggregated groups
SELECT category, AVG(price) AS avg_price 
FROM products 
WHERE is_available = true -- 1. Filters rows
GROUP BY category         -- 2. Groups remaining rows
HAVING AVG(price) > 100;  -- 3. Filters groups
```

---

## 4. Différences clés : MySQL vs. PostgreSQL

### La règle de regroupement stricte
Le standard SQL dicte que lors de l'utilisation de `GROUP BY`, toute colonne de votre liste `SELECT` qui n'est pas enveloppée dans une fonction d'agrégation **doit** être déclarée dans la clause `GROUP BY`.
* **PostgreSQL** applique cette règle de manière stricte. Si vous sélectionnez une colonne qui ne figure pas dans la clause `GROUP BY` (et qui ne dépend pas fonctionnellement des clés primaires du `GROUP BY`), Postgres lève une erreur de syntaxe.
* **MySQL** se comporte de manière similaire sous son mode SQL par défaut `ONLY_FULL_GROUP_BY`. Cependant, si ce mode est désactivé, MySQL vous permet de sélectionner des colonnes non listées dans `GROUP BY`, renvoyant la valeur d'une ligne aléatoire pour ces colonnes — une source fréquente de bugs.

```sql
-- Violating the strict grouping rule
SELECT category, brand, AVG(price) 
FROM products 
GROUP BY category;
```
* **PostgreSQL** : Échoue immédiatement avec `ERROR: column "products.brand" must appear in the GROUP BY clause...`
* **MySQL** : Échoue uniquement si `ONLY_FULL_GROUP_BY` est activé. Si désactivé, il renvoie la catégorie, une marque aléatoire de cette catégorie, et le prix moyen.

### Agrégation de chaînes (`GROUP_CONCAT` vs. `string_agg`)
Si vous souhaitez combiner les valeurs textuelles de lignes regroupées en une seule chaîne de caractères séparée par des virgules :
* **MySQL** utilise la fonction `GROUP_CONCAT()`.
* **PostgreSQL** utilise la fonction `string_agg()` et nécessite une syntaxe de clause `ORDER BY` explicite si un ordre est souhaité.

```sql
-- MySQL: Combines tags per product
SELECT product_id, GROUP_CONCAT(tag_name ORDER BY tag_name SEPARATOR ', ') AS tags
FROM product_tags
GROUP BY product_id;

-- PostgreSQL: Combines tags per product
SELECT product_id, string_agg(tag_name, ', ' ORDER BY tag_name) AS tags
FROM product_tags
GROUP BY product_id;
```

> [!WARNING]
> **Le piège des Nulls avec `AVG()` !**
> Parce que `AVG()` ignore les valeurs `NULL`, cela peut fausser vos indicateurs commerciaux. Par exemple, si vous calculez le bonus moyen des employés et que 9 employés sur 10 ont un bonus `NULL` (inconnu/aucun), `AVG(bonus)` calculera la moyenne sur la base du seul employé ayant un bonus, plutôt que de diviser le total par 10. Utilisez `COALESCE(bonus, 0)` à l'intérieur de l'agrégation pour traiter les `NULL` comme des `0`.

> [!TIP]
> **Le saviez-vous ?**
> Vous pouvez effectuer une agrégation conditionnelle en combinant `SUM` ou `COUNT` avec des expressions. Dans PostgreSQL moderne, vous pouvez utiliser la clause `FILTER` qui est plus propre :
> `SELECT count(*) FILTER (WHERE price > 100) AS expensive_count FROM products;`
> Dans MySQL, vous devez utiliser une instruction `CASE` dans l'agrégat :
> `SELECT SUM(CASE WHEN price > 100 THEN 1 ELSE 0 END) AS expensive_count FROM products;`

---

## 5. Résumé & Bonnes pratiques

1. **Gardez le SELECT propre** : Assurez-vous que chaque colonne non agrégée de votre liste `SELECT` se trouve également dans votre clause `GROUP BY` pour maintenir la compatibilité entre les moteurs.
2. **`WHERE` vs `HAVING`** : Utilisez `WHERE` pour filtrer les lignes brutes avant l'agrégation, et `HAVING` pour filtrer les groupes agrégés.
3. **Gerez les NULL dans les calculs** : Rappelez-vous que les calculs d'agrégation (sauf `COUNT(*)`) ignorent `NULL`. Enveloppez les colonnes dans `COALESCE` pour leur attribuer une valeur par défaut de 0 si nécessaire.
4. **Apprenez les fonctions spécifiques** : Utilisez `GROUP_CONCAT` dans MySQL et `string_agg` dans PostgreSQL lors de la concaténation de chaînes sur des lignes regroupées.
