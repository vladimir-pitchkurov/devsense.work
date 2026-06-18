# Fonctions de fenêtrage : Partitionnement, tri et cadrage

Imaginez que vous construisez un tableau de bord pour afficher la liste des transactions d'un client. Vous devez afficher les détails de la transaction, mais vous souhaitez également afficher le total cumulé des dépenses de chaque client, leur classement basé sur le montant de la transaction et le montant de leur transaction précédente.

L'utilisation d'un `GROUP BY` standard réduit vos lignes, ce qui vous fait perdre les détails individuels des transactions. Faire cela dans la logique applicative nécessite de récupérer tous les enregistrements et de boucler sur eux, ce qui est lent et gourmand en mémoire. Les **fonctions de fenêtrage** (window functions) résolvent ce problème en effectuant des calculs sur un ensemble de lignes de table qui sont liées à la ligne actuelle, sans réduire le jeu de résultats.

---

## 1. Le mécanisme de base : Regroupement vs. Fenêtrage

Contrairement à `GROUP BY`, qui agrège plusieurs lignes en une seule ligne de résumé, les fonctions de fenêtrage calculent un agrégat ou un classement pour chaque ligne individuellement tout en préservant tous les champs détaillés.

```
GROUP BY :
[Ligne 1] \
[Ligne 2]  --> [Ligne agrégée]
[Ligne 3] /

FONCTION DE FENÊTRAGE :
[Ligne 1] --> [Ligne 1] [Valeur calculée 1]
[Ligne 2] --> [Ligne 2] [Valeur calculée 2]
[Ligne 3] --> [Ligne 3] [Valeur calculée 3]
```

### Syntaxe de base
```sql
-- MySQL 8.0+ & PostgreSQL
SELECT 
    employee_id, 
    department_id, 
    salary,
    SUM(salary) OVER(PARTITION BY department_id) AS dept_total_salary
FROM employees;
```
* **`PARTITION BY`** : Divise les lignes en groupes (partitions) qui partagent les mêmes valeurs. S'il est omis, l'ensemble du jeu de résultats est traité comme une seule partition.
* **`ORDER BY`** : Définit l'ordre de tri physique des lignes à l'intérieur de chaque partition. Cela détermine la manière dont les valeurs sont traitées séquentiellement.

---

## 2. Fonctions de classement et de valeur

Les fonctions de fenêtrage sont classées en agrégats (comme `SUM` ou `AVG`), fonctions de classement (ranking) et fonctions de récupération de valeurs.

### Classement : `ROW_NUMBER()`, `RANK()` et `DENSE_RANK()`
Lorsque des valeurs sont identiques (ex-æquo), ces fonctions se comportent différemment :
* **`ROW_NUMBER()`** : Attribue un entier séquentiel unique commençant à 1. Les ex-æquo sont résolus de manière arbitraire.
* **`RANK()`** : Attribue un classement avec des sauts. Si deux lignes sont à égalité pour la 1ère place, les deux obtiennent le classement 1, et le classement suivant est 3.
* **`DENSE_RANK()`** : Attribue un classement sans sauts. Si deux lignes sont à égalité pour la 1ère place, les deux obtiennent le classement 1, et le classement suivant est 2.

| Employé | Salaire | `ROW_NUMBER()` | `RANK()` | `DENSE_RANK()` |
| :--- | :--- | :--- | :--- | :--- |
| Alice | 10 000 $ | 1 | 1 | 1 |
| Bob | 10 000 $ | 2 | 1 | 1 |
| Charlie | 8 000 $ | 3 | 3 | 2 |
| David | 7 000 $ | 4 | 4 | 3 |

### Fonctions de valeur : `LAG()`, `LEAD()` et `FIRST_VALUE()`
* **`LAG(col, offset, default)`** : Accède à une valeur provenant d'une ligne située à un décalage physique spécifique *avant* la ligne actuelle.
* **`LEAD(col, offset, default)`** : Accède à une valeur provenant d'une ligne située à un décalage physique spécifique *après* la ligne actuelle.

```sql
-- Fetch current and previous transaction amount to calculate the difference
SELECT 
    transaction_date,
    amount,
    LAG(amount, 1, 0) OVER(ORDER BY transaction_date) AS prev_amount
FROM transactions;
```

---

## 3. Cadrage de fenêtre (Window Framing) : La fenêtre glissante

Le cadre de la fenêtre (window frame) définit un sous-ensemble dynamique de lignes au sein de la partition, par rapport à la ligne actuelle. Le cadre se déplace à mesure que le moteur de base de données traite chaque ligne.

### Types de cadres : `ROWS` vs. `RANGE` vs. `GROUPS`
* **`ROWS`** : Compte les lignes physiques par rapport à la ligne actuelle (par exemple, 5 lignes avant).
* **`RANGE`** : Compte les valeurs logiques basées sur la colonne de la clause `ORDER BY`. Il inclut toutes les lignes partageant les mêmes valeurs que la ligne actuelle (les ex-æquo sont traités ensemble).
* **`GROUPS`** : Groupes de valeurs dupliquées basées sur la colonne de tri.

```sql
-- Running Total Frame Example
SUM(amount) OVER(
    PARTITION BY user_id 
    ORDER BY transaction_date
    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
)
```

> [!WARNING]
> **Le piège de `LAST_VALUE()` !**
> Si vous écrivez `LAST_VALUE(salary) OVER(PARTITION BY dept_id ORDER BY salary)`, vous pourriez vous attendre à ce qu'il renvoie le salaire le plus élevé du département. Au lieu de cela, il renvoie le salaire de la ligne actuelle !
> En effet, lorsque `ORDER BY` est présent, le cadre par défaut est :
> `RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW`
> Pour corriger cela, spécifiez explicitement le cadre :
> `LAST_VALUE(salary) OVER(PARTITION BY dept_id ORDER BY salary RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING)`

---

## 4. MySQL vs. PostgreSQL : Différences architecturales

Bien que les deux moteurs soient conformes aux normes SQL ANSI, ils diffèrent par leurs fonctionnalités, leurs extensions syntaxiques et leurs performances d'exécution.

### 1. La clause `FILTER` (PostgreSQL uniquement)
PostgreSQL prend en charge la clause `FILTER` avec les fonctions de fenêtrage d'agrégation, ce qui vous permet d'agréger sélectivement des lignes sans utiliser de structures complexes avec `CASE WHEN`.
```sql
-- PostgreSQL Only
SELECT 
    department_id,
    COUNT(employee_id) FILTER (WHERE salary > 5000) OVER(PARTITION BY department_id) AS highly_paid_count
FROM employees;
```
Dans MySQL, vous devez écrire :
```sql
-- MySQL Equivalent
SELECT 
    department_id,
    SUM(CASE WHEN salary > 5000 THEN 1 ELSE 0 END) OVER(PARTITION BY department_id) AS highly_paid_count
FROM employees;
```

### 2. Exclusions de cadres (PostgreSQL 11+ uniquement)
PostgreSQL prend en charge des options d'exclusion de cadre avancées, vous permettant d'exclure des lignes spécifiques du calcul du cadre de la fenêtre.
* `EXCLUDE CURRENT ROW` : Exclut la ligne actuelle du cadre.
* `EXCLUDE GROUP` : Exclut la ligne actuelle et toutes les lignes ex-æquo selon le critère de tri.
* *MySQL 8.0 ne prend pas en charge les clauses `EXCLUDE` dans le cadrage de fenêtre.*

> [!TIP]
> **Conseil de performance :**
> Les fonctions de fenêtrage sont exécutées lors de l'étape finale du traitement de la requête (après `WHERE` et `GROUP BY`). Pour les optimiser, créez un index composite correspondant aux colonnes de `PARTITION BY` et `ORDER BY`. Cela permet au moteur de requête de récupérer directement les données triées, évitant ainsi des opérations de tri coûteuses (filesorts).

---

## 5. Résumé & Bonnes pratiques

1. **Conservez l'identité des lignes** : Utilisez les fonctions de fenêtrage lorsque vous avez besoin de calculs d'agrégats en plus des champs de lignes individuelles.
2. **Attention aux valeurs par défaut** : Rappelez-vous que l'ajout d'un `ORDER BY` modifie automatiquement le cadre de fenêtre par défaut, ce qui affecte les sommes cumulées et les fonctions comme `LAST_VALUE()`.
3. **Utilisez les index** : Vérifiez toujours que vos clés de `PARTITION BY` et `ORDER BY` sont indexées afin d'éviter la création de tables temporaires de base de données sur disque.
