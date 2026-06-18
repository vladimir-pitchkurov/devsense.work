# Jointure de tables

Dans une base de données relationnelle normalisée, les données sont réparties sur plusieurs tables afin d'éliminer la redondance et de maintenir l'intégrité. Par exemple, plutôt que de répéter les détails d'un département pour chaque employé, vous stockez les employés dans une table et les départements dans une autre, en les reliant via une clé étrangère.

Pour reconstituer la vue unifiée de vos données, vous devez utiliser des **jointures** (Joins). Une jointure combine les colonnes de deux tables ou plus sur la base d'une colonne commune entre elles. Dans ce chapitre, nous allons maîtriser les différents types de jointures, leurs conditions, et explorer comment PostgreSQL et MySQL les exécutent.

---

## 1. Visualiser les types de jointures

SQL propose plusieurs façons de joindre des tables, chacune répondant à une logique différente :

| Type de jointure | Description |
| :--- | :--- |
| **`INNER JOIN`** | Renvoie les enregistrements qui ont des valeurs correspondantes dans les deux tables. |
| **`LEFT JOIN`** | Renvoie tous les enregistrements de la table de gauche, et les enregistrements correspondants de la table de droite. Renvoie `NULL` pour la table de droite s'il n'y a pas de correspondance. |
| **`RIGHT JOIN`** | Renvoie tous les enregistrements de la table de droite, et les enregistrements correspondants de la table de gauche. Renvoie `NULL` pour la table de gauche s'il n'y a pas de correspondance. |
| **`FULL JOIN`** | Renvoie tous les enregistrements lorsqu'il y a une correspondance dans l'une des tables de gauche ou de droite. |
| **`CROSS JOIN`** | Renvoie le produit cartésien des deux tables (chaque ligne de la table A est associée à chaque ligne de la table B). |

---

## 2. Syntaxe et exemples

Supposons que nous ayons deux tables : `employees` (avec une colonne `department_id`) et `departments` (avec une colonne `id`).

### Jointure interne (Inner Join)
Récupère uniquement les employés qui appartiennent à un département, et uniquement les départements qui ont des employés.
```sql
-- MySQL & PostgreSQL
SELECT e.name AS employee_name, d.name AS department_name
FROM employees e
INNER JOIN departments d ON e.department_id = d.id;
```

### Jointure gauche (Left Join - Jointure externe la plus courante)
Récupère tous les employés, y compris ceux qui n'appartiennent à aucun département (leur `department_name` sera renvoyé comme `NULL`).
```sql
-- MySQL & PostgreSQL
SELECT e.name AS employee_name, d.name AS department_name
FROM employees e
LEFT JOIN departments d ON e.department_id = d.id;
```

### Conditions de jointure : `ON` vs. `USING`
Lorsque les colonnes sur lesquelles vous effectuez la jointure ont exactement le même nom dans les deux tables (par exemple, `department_id` dans `employees` et `departments`), vous pouvez utiliser la syntaxe abrégée `USING` qui est plus propre à la place de `ON`.
```sql
-- MySQL & PostgreSQL
SELECT e.name, d.name
FROM employees e
INNER JOIN departments d USING (department_id);
```

---

## 3. Différences clés : MySQL vs. PostgreSQL

### Prise en charge native du `FULL OUTER JOIN`
* **PostgreSQL** prend en charge nativement le `FULL OUTER JOIN` standard (ou simplement `FULL JOIN`).
* **MySQL** ne prend PAS en charge le `FULL JOIN`. Si vous essayez de l'utiliser, MySQL lèvera une erreur de syntaxe.

#### Solution de contournement MySQL pour le FULL JOIN
Pour obtenir une jointure externe totale dans MySQL, vous devez écrire un `LEFT JOIN` et un `RIGHT JOIN` sur les mêmes tables, et combiner leurs résultats à l'aide de l'opérateur `UNION` (qui supprime automatiquement les lignes en double).

```sql
-- PostgreSQL (Native syntax)
SELECT e.name, d.name
FROM employees e
FULL JOIN departments d ON e.department_id = d.id;

-- MySQL Workaround (Emulation)
SELECT e.name, d.name
FROM employees e
LEFT JOIN departments d ON e.department_id = d.id
UNION
SELECT e.name, d.name
FROM employees e
RIGHT JOIN departments d ON e.department_id = d.id;
```

### Algorithmes de jointure et performances sous le capot
Comment les moteurs de bases de données calculent-ils les jointures ? Ils utilisent différents algorithmes internes :
* **PostgreSQL** : Dispose d'un optimiseur très sophistiqué qui prend en charge les jointures par boucles imbriquées (**Nested Loop Joins**), par hachage (**Hash Joins**) et par fusion-tri (**Merge Joins**) depuis des décennies. Il sélectionne le meilleur algorithme en fonction de la taille de la table et de la disponibilité des index.
* **MySQL** : Historiquement, MySQL ne prenait en charge que les jointures par boucles imbriquées (**Nested Loop Joins**), qui sont lentes pour les grandes tables car elles nécessitent de parcourir la seconde table pour chaque ligne de la première. MySQL 8.0 a introduit les jointures par hachage (**Hash Joins**) pour optimiser les opérations de jointure sur de grandes colonnes non indexées, le rapprochant ainsi des performances de jointure de PostgreSQL.

> [!WARNING]
> **Le piège du produit cartésien !**
> L'exécution d'un `CROSS JOIN` (ou l'oubli d'une condition de jointure dans l'ancienne syntaxe SQL comme `FROM table_a, table_b`) crée un produit cartésien. Si la Table A contient 10 000 lignes et la Table B contient 10 000 lignes, un CROSS JOIN génère **100 000 000 (100 millions) de lignes**, ce qui peut instantanément épuiser la mémoire du serveur et figer la base de données.

> [!TIP]
> **Le saviez-vous ?**
> Lors du filtrage de colonnes dans un `LEFT JOIN`, le fait de placer le filtre dans la clause `ON` ou dans la clause `WHERE` modifie complètement le résultat.
> - **Dans `ON`** : Filtre la table de droite *avant* la jointure. La table de gauche renvoie toujours toutes ses lignes.
> - **Dans `WHERE`** : Filtre le résultat *après* la jointure, transformant ainsi votre `LEFT JOIN` en un `INNER JOIN` restrictif car il vérifie les valeurs dans une colonne qui pourrait être devenue `NULL`.

---

## 4. Résumé & Bonnes pratiques

1. **Préférez `LEFT JOIN` à `RIGHT JOIN`** : Les jointures gauches sont beaucoup plus faciles à lire et à visualiser car les requêtes SQL se lisent de gauche à droite (de haut en bas).
2. **Attention au `WHERE` sur les jointures externes** : Ne filtrez pas les colonnes de la table de droite dans la clause `WHERE` à moins de vérifier la condition `IS NULL` pour trouver les lignes sans correspondance.
3. **Gardez à l'esprit la limitation du `FULL JOIN` de MySQL** : Utilisez la solution de contournement `LEFT JOIN UNION RIGHT JOIN` dans MySQL.
4. **Utilisez une syntaxe de jointure explicite** : Utilisez toujours des mots-clés de jointure explicites (`INNER JOIN`, `LEFT JOIN`) plutôt que des tables séparées par des virgules dans la clause `FROM` (`FROM table_a, table_b`) pour éviter les produits cartésiens accidentels.
