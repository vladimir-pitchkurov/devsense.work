# Tri, limites et valeurs NULL

Récupérer des données dans un ordre aléatoire est rarement utile. Dans les applications réelles, vous devez présenter les enregistrements triés par ordre alphabétique, numérique ou chronologique. De plus, les bases de données contiennent souvent des ensembles de données massifs, ce qui rend nécessaire la limitation du volume de lignes récupérées (par exemple, pour la pagination). Enfin, vous devez comprendre comment SQL gère les données manquantes ou inconnues représentées par `NULL`.

Dans ce chapitre, nous explorons comment organiser, restreindre et interroger en toute sécurité les données à l'aide de `ORDER BY`, `LIMIT` et des comparaisons avec `NULL`, ainsi que les différentes manières dont MySQL et PostgreSQL gèrent ces fonctionnalités.

---

## 1. Trier les résultats avec `ORDER BY`

Par défaut, les moteurs de bases de données relationnelles renvoient les lignes dans un ordre non spécifié (souvent basé sur la façon dont elles sont physiquement stockées sur le disque). Pour garantir un ordre spécifique, vous devez utiliser la clause `ORDER BY`.

### Ordre croissant et décroissant
* **`ASC` (Ascending)** : Trie les valeurs de la plus basse à la plus haute (comportement par défaut).
* **`DESC` (Descending)** : Trie les valeurs de la plus haute à la plus basse.

```sql
-- MySQL & PostgreSQL
-- Sort products by price, highest first
SELECT name, price FROM products ORDER BY price DESC;
```

### Tri sur plusieurs colonnes
Vous pouvez trier par plusieurs colonnes. Le moteur trie d'abord par la première colonne, et s'il y a des valeurs en double, il trie ces doublons par la deuxième colonne, et ainsi de suite.
```sql
-- MySQL & PostgreSQL
-- Sort by category alphabetically, and then by price descending within each category
SELECT category, name, price 
FROM products 
ORDER BY category ASC, price DESC;
```

---

## 2. Restreindre les résultats : `LIMIT` et `OFFSET`

La récupération de millions de lignes peut faire planter votre serveur d'application et bloquer les ressources de la base de données. Pour éviter cela, vous pouvez restreindre le jeu de résultats.

* **`LIMIT`** : Spécifie le nombre maximum de lignes à renvoyer.
* **`OFFSET`** : Ignore un nombre spécifique de lignes avant de renvoyer les résultats (couramment utilisé pour la pagination).

```sql
-- MySQL & PostgreSQL
-- Get the second page of products (items 11-20)
SELECT name, price 
FROM products 
ORDER BY price DESC 
LIMIT 10 OFFSET 10;
```

---

## 3. Le mystère des valeurs `NULL`

En SQL, `NULL` représente une absence de données, une valeur inconnue ou un attribut manquant. Il n'est **pas** équivalent à une chaîne vide `''` ou au nombre `0`.

### Le piège de la logique à trois valeurs
Dans les langages de programmation standards, `true` et `false` sont les seuls états booléens. SQL, cependant, utilise la **logique à trois valeurs** : `true`, `false` et `unknown` (inconnu, représenté par `NULL`).

Parce que `NULL` signifie « inconnu », vous ne pouvez pas le comparer à l'aide d'opérateurs standards comme `=` ou `!=`. Par exemple :
* Une valeur inconnue est-elle égale à 5 ? **Inconnu (`NULL`)**.
* Une valeur inconnue est-elle égale à une autre valeur inconnue ? **Inconnu (`NULL`)**.

```sql
-- THIS WILL NOT WORK! It returns zero rows.
SELECT * FROM users WHERE middle_name = NULL;
```

### Comparaisons correctes avec NULL
Pour vérifier si une colonne est vide ou renseignée, vous devez utiliser `IS NULL` ou `IS NOT NULL`.
```sql
-- MySQL & PostgreSQL
-- Correct way to find users without a middle name
SELECT email FROM users WHERE middle_name IS NULL;

-- Correct way to find users with a middle name
SELECT email FROM users WHERE middle_name IS NOT NULL;
```

---

## 4. Différences clés : MySQL vs. PostgreSQL

### Tri des valeurs Null (`NULLS FIRST` vs. `NULLS LAST`)
Lors du tri d'une colonne contenant des valeurs `NULL`, comment le moteur les positionne-t-il ?
* **MySQL** : Traite `NULL` comme la plus petite valeur possible. Dans l'ordre croissant (`ASC`), les `NULL` apparaissent en premier. Dans l'ordre décroissant (`DESC`), les `NULL` apparaissent en dernier.
* **PostgreSQL** : Traite `NULL` comme la plus grande valeur possible. Dans l'ordre croissant (`ASC`), les `NULL` apparaissent en dernier. Dans l'ordre décroissant (`DESC`), les `NULL` apparaissent en premier.

Cependant, PostgreSQL prend en charge la surcharge standard de SQL : `NULLS FIRST` ou `NULLS LAST`. MySQL ne le prend pas en charge nativement.

| Base de données | Ordre | Position par défaut de NULL | Surcharge personnalisée |
| :--- | :--- | :--- | :--- |
| **MySQL** | `ASC` | En premier | Aucune (Nécessite une astuce) |
| **MySQL** | `DESC` | En dernier | Aucune (Nécessite une astuce) |
| **PostgreSQL** | `ASC` | En dernier | `ORDER BY price ASC NULLS FIRST` |
| **PostgreSQL** | `DESC` | En premier | `ORDER BY price DESC NULLS LAST` |

#### Solution de contournement MySQL pour le tri des Nulls
Pour forcer les `NULL` à la fin d'un tri croissant dans MySQL, vous pouvez utiliser une condition avec une expression booléenne :
```sql
-- MySQL: NULLs sorted last in ascending order
SELECT name, price FROM products ORDER BY price IS NULL ASC, price ASC;
```

### Syntaxe non standard de `LIMIT`
* **MySQL** prend en charge une syntaxe abrégée séparée par des virgules : `LIMIT offset, row_count`.
* **PostgreSQL** ne prend pas en charge cette syntaxe et lèvera une erreur de syntaxe.

```sql
-- MySQL Only (Shorthand syntax: limit 10 rows, skipping the first 5)
SELECT name FROM products LIMIT 5, 10;

-- PostgreSQL & MySQL Standard (Recommended)
SELECT name FROM products LIMIT 10 OFFSET 5;
```

> [!WARNING]
> **Piège de performance de l'Offset !**
> L'utilisation d'un `OFFSET` élevé (par exemple, `LIMIT 10 OFFSET 500000`) oblige le moteur de base de données à analyser et ignorer 500 000 lignes avant de renvoyer les 10 lignes demandées. Cela entraîne une dégradation sévère des performances sur les grandes tables. Pour une pagination profonde, préférez la pagination basée sur un curseur (pagination par clé) en utilisant `WHERE id > last_seen_id LIMIT 10`.

> [!TIP]
> **Le saviez-vous ?**
> Le standard SQL définit `FETCH FIRST n ROWS ONLY` au lieu de `LIMIT`. Bien que MySQL et PostgreSQL prennent tous deux en charge `LIMIT`, PostgreSQL prend également en charge le standard officiel :
> `SELECT name FROM products ORDER BY price DESC FETCH FIRST 10 ROWS ONLY;`

---

## 5. Résumé & Bonnes pratiques

1. **Associez toujours `ORDER BY` à `LIMIT`** : Sans tri, `LIMIT` renverra un ensemble de lignes aléatoires en fonction de l'état de la base de données.
2. **N'utilisez jamais `=` avec `NULL`** : Utilisez toujours `IS NULL` ou `IS NOT NULL`.
3. **Utilisez le standard `LIMIT/OFFSET`** : Évitez la syntaxe abrégée de `LIMIT` avec virgule de MySQL pour préserver la portabilité des requêtes.
4. **Attention au tri des Nulls** : Soyez conscient que MySQL place les `NULL` en premier avec `ASC`, tandis que PostgreSQL les place en dernier. Utilisez les modificateurs `NULLS FIRST/LAST` de Postgres ou les astuces de tri `IS NULL` de MySQL lorsqu'un comportement précis est requis.
