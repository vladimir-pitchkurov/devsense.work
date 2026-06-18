# Bases de données relationnelles & L'instruction SELECT

Au cœur du stockage moderne des données se trouve le modèle de base de données relationnelle, proposé pour la première fois par Edgar F. Codd en 1970. Au lieu de stocker les données dans des fichiers texte non structurés ou des arborescences hiérarchiques rigides, une base de données relationnelle organise les informations en tables structurées qui peuvent être jointes et irrégulières dynamiquement à l'aide du langage SQL (Structured Query Language). Que vous utilisiez MySQL ou PostgreSQL, il est crucial de comprendre les concepts fondamentaux de la conception relationnelle et la manière de récupérer les données efficacement.

---

## 1. Le modèle relationnel : Tables, colonnes et clés

Dans une base de données relationnelle, les données sont représentées sous la forme d'une collection de tables de relation. Chaque table se compose de :
* **Colonnes (Attributs)** : Définissent le type de données et les propriétés des informations stockées (par exemple, `user_id` en tant qu'entier, `email` en tant que chaîne de caractères).
* **Lignes (Enregistrements/Tuples)** : Représentent des instances individuelles de données (par exemple, l'enregistrement d'un utilisateur spécifique).

Pour maintenir l'intégrité, les tables s'appuient sur deux concepts essentiels :
1. **Clé primaire (Primary Key - PK)** : Une colonne (ou un ensemble de colonnes) qui identifie de manière unique chaque ligne d'une table. Elle ne peut pas contenir de valeurs `NULL`.
2. **Clé étrangère (Foreign Key - FK)** : Une colonne dans une table qui fait référence à la clé primaire d'une autre table, établissant ainsi une relation entre elles.

| Concept | Description | Analogie |
| :--- | :--- | :--- |
| **Table** | Une grille structurée de colonnes et de lignes. | Un onglet de feuille de calcul. |
| **Row** | Un seul enregistrement de données. | Une seule ligne dans une feuille de calcul. |
| **Primary Key** | Identifiant unique pour une ligne. | Numéro de passeport. |
| **Foreign Key** | Référence à la clé primaire d'une autre table. | L'identifiant du département d'un employé. |

---

## 2. Récupérer des données avec `SELECT`

L'opération la plus fondamentale en SQL est la récupération de données à l'aide de l'instruction `SELECT`. Dans sa forme la plus simple, une requête nécessite une clause `SELECT` (quelles colonnes récupérer) et une clause `FROM` (quelle table interroger).

### Sélectionner toutes les colonnes vs. Colonnes spécifiques
Vous pouvez récupérer toutes les colonnes à l'aide du caractère générique astérisque (`*`), ou spécifier uniquement les colonnes dont vous avez besoin.
```sql
-- MySQL & PostgreSQL
-- Retrieve all columns (not recommended for production)
SELECT * FROM users;

-- Retrieve specific columns (recommended)
SELECT id, email, first_name FROM users;
```

> [!TIP]
> **Le saviez-vous ?**
> L'utilisation de `SELECT *` en production est considérée comme un anti-pattern. Cela oblige le moteur de base de données à effectuer des E/S disque supplémentaires pour lire des colonnes dont vous n'avez pas besoin, consomme de la bande passante réseau inutilement et peut perturber votre application si une nouvelle colonne est ajoutée ou si une ancienne est supprimée de la table.

### Alias de colonne (`AS`)
Les alias vous permettent de renommer les colonnes dans le jeu de résultats de votre requête pour une meilleure lisibilité ou pour correspondre aux attentes de l'application.
```sql
-- MySQL & PostgreSQL
SELECT first_name AS given_name, last_name AS surname FROM users;
```

---

## 3. Filtrer les résultats avec `WHERE`

Pour récupérer uniquement des lignes spécifiques, utilisez la clause `WHERE`. La clause `WHERE` évalue une condition booléenne pour chaque ligne, renvoyant uniquement celles pour lesquelles la condition est vraie.

### Opérateurs standards
SQL prend en charge les opérateurs de comparaison standards :
* `=` (égal à)
* `<>` ou `!=` (différent de)
* `>` (supérieur à), `<` (inférieur à)
* `>=` (supérieur ou égal à), `<=` (inférieur ou égal à)

```sql
-- Retrieve active users registered after a specific ID
SELECT email FROM users WHERE is_active = true AND id > 100;
```

### Opérateurs de filtrage avancés
* **`BETWEEN`** : Filtre les valeurs dans une plage spécifique (bornes incluses).
* **`IN`** : Vérifie si une valeur correspond à l'une des valeurs d'une liste spécifiée.
* **`LIKE`** : Effectue une recherche de motif simple à l'aide de caractères génériques :
  - `%` représente zéro, un ou plusieurs caractères.
  - `_` représente exactement un caractère.

```sql
-- Retrieve users with IDs 1, 3, or 5
SELECT email FROM users WHERE id IN (1, 3, 5);

-- Retrieve users registered in a specific range
SELECT email FROM users WHERE id BETWEEN 10 AND 50;

-- Retrieve users whose email starts with 'admin'
SELECT email FROM users WHERE email LIKE 'admin%';
```

---

## 4. Différences clés : MySQL vs. PostgreSQL

Bien que les deux moteurs suivent le standard SQL, ils divergent sur la syntaxe et les comportements par défaut.

### Guillemets pour les identifiants
Les identifiants (noms de tables, noms de colonnes) doivent être entourés de guillemets s'ils entrent en conflit avec des mots-clés réservés SQL ou s'ils contiennent des caractères spéciaux/espaces.
* **MySQL** : Utilise des accents graves (`` ` ``).
* **PostgreSQL** : Utilise des guillemets doubles (`"`).

```sql
-- MySQL
SELECT `select`, `group` FROM `my_table`;

-- PostgreSQL
SELECT "select", "group" FROM "my_table";
```

### Sensibilité à la casse dans la recherche de motifs
* **PostgreSQL** est strictement sensible à la casse pour `LIKE`. Pour effectuer une recherche insensible à la casse, vous devez utiliser l'opérateur `ILIKE` spécifique à PostgreSQL.
* Dans **MySQL**, `LIKE` est insensible à la casse par défaut avec les collations standards (par exemple, `utf8mb4_0900_ai_ci`). Pour le rendre sensible à la casse, vous devez convertir la chaîne en binaire ou utiliser une collation binaire.

```sql
-- Case-insensitive search for 'john'
-- PostgreSQL
SELECT email FROM users WHERE email ILIKE 'john%';

-- MySQL
SELECT email FROM users WHERE email LIKE 'john%';
```

### Concaténation de chaînes
* **PostgreSQL** utilise l'opérateur standard SQL double barre verticale (`||`).
* **MySQL** ne prend pas en charge `||` pour la concaténation par défaut (il traite `||` comme l'opérateur logique `OR` à moins que le mode SQL `PIPES_AS_CONCAT` ne soit activé). À la place, MySQL utilise la fonction `CONCAT()`.

```sql
-- PostgreSQL
SELECT first_name || ' ' || last_name AS full_name FROM users;

-- MySQL
SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users;
```

### Types de données booléens
* **PostgreSQL** dispose d'un type `BOOLEAN` natif prenant en charge les valeurs littérales `true` et `false`.
* **MySQL** ne possède pas de véritable type booléen ; il associe `BOOLEAN` à un alias de `TINYINT(1)`, où `0` représente false et `1` représente true.

```sql
-- PostgreSQL: Returns true/false
SELECT is_active FROM users;

-- MySQL: Returns 1/0
SELECT is_active FROM users;
```

> [!WARNING]
> **Le piège des guillemets !**
> N'utilisez jamais de guillemets doubles (`"`) pour les chaînes de caractères littérales (valeurs textuelles) dans PostgreSQL. PostgreSQL traite les guillemets doubles comme des délimiteurs d'identifiants (pour les noms de colonnes ou de tables), ce qui provoquera une erreur de syntaxe `column "value" does not exist`. Utilisez toujours des guillemets simples (`'`) pour les chaînes littérales dans MySQL et PostgreSQL.

---

## 5. Résumé & Bonnes pratiques

1. **Soyez spécifique** : Énumérez explicitement les noms des colonnes au lieu d'utiliser `SELECT *` pour améliorer les performances et la durabilité du code.
2. **Standardisez les guillemets** : Utilisez des guillemets simples (`'`) pour les chaînes littérales sur tous les moteurs de bases de données.
3. **Choisissez le bon opérateur** : Utilisez `ILIKE` dans PostgreSQL pour les correspondances insensibles à la casse, et rappelez-vous que la concaténation par `||` est standard dans Postgres mais nécessite `CONCAT()` dans MySQL.
4. **Vérifications booléennes** : N'oubliez pas que MySQL stocke les booléens sous la forme de `1` ou `0`, ce qui peut affecter la manière dont le code de votre application analyse les résultats des requêtes.
