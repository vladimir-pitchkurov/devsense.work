# Jointures non équivalentes et opérations sur les ensembles

La plupart des tutoriels SQL se concentrent fortement sur la jointure de tables à l'aide de l'opérateur d'égalité (`ON a.id = b.id`). Cependant, les problèmes de base de données du monde réel nécessitent souvent de faire correspondre des enregistrements basés sur des plages, des intervalles, des inégalités ou de fusionner des jeux de données à l'aide de la théorie mathématique des ensembles.

Comprendre les **jointures non équivalentes** (non-equi joins) et les **opérations avancées sur les ensembles** est ce qui sépare les développeurs SQL débutants des ingénieurs de bases de données seniors.

---

## 1. Jointures non équivalentes : Au-delà de l'égalité

Une **jointure non équivalente** (non-equi join) est une condition de jointure qui utilise des opérateurs autres que le signe égal (`=`), tels que `<`, `>`, `<=`, `>=`, `BETWEEN` ou `!=`.

### Cas d'utilisation : Intervalles de dates qui se chevauchent
Imaginez que vous gérez les réservations de chambres d'hôtel. Vous devez vérifier si une nouvelle demande de réservation chevauche des réservations existantes.
* **Logique** : Une collision se produit si la date de début demandée est antérieure à la date de fin d'une réservation existante, ET si la date de fin demandée est postérieure à la date de début de la réservation existante.

```sql
-- MySQL & PostgreSQL
SELECT 
    b1.room_id,
    b1.booking_id AS booking_1,
    b2.booking_id AS booking_2
FROM bookings b1
JOIN bookings b2 ON b1.room_id = b2.room_id
    AND b1.booking_id < b2.booking_id -- Avoid self-matching and duplicate pairs
    AND b1.start_date < b2.end_date 
    AND b1.end_date > b2.start_date;
```

### Cas d'utilisation : Regroupement par plages (par exemple, tranches de prix)
Vous pouvez attribuer des produits à des tranches de prix sans coder en dur ces tranches ou utiliser des boucles imbriquées complexes.

```sql
-- MySQL & PostgreSQL
SELECT 
    p.product_name, 
    p.price, 
    t.tier_name
FROM products p
JOIN price_tiers t ON p.price BETWEEN t.min_price AND t.max_price;
```

> [!WARNING]
> **Problème de performance avec les jointures non équivalentes !**
> Alors que les planificateurs de requêtes modernes utilisent des jointures par hachage (**Hash Joins**) efficaces pour les jointures équivalentes, ils ne peuvent pas les utiliser pour les conditions non équivalentes. À la place, ils se rabattent sur des jointures par boucles imbriquées (**Nested Loop Joins** ou **Block Nested Loops**). Cela peut se traduire par une complexité temporelle en `O(N * M)`.
> Dans PostgreSQL, vous pouvez atténuer ce problème en utilisant des **types de plages** (Range Types) et des **index GiST**. MySQL ne dispose pas de types de plages natifs, ce qui signifie que vous devez optimiser soigneusement vos index B-Tree composites.

---

## 2. Opérations avancées sur les ensembles

Les opérations sur les ensembles combinent les résultats de deux requêtes ou plus en un seul jeu de résultats.

```
Opérations sur les ensembles :
[Requête 1] UNION [Requête 2]      --> Renvoie toutes les lignes uniques des deux requêtes.
[Requête 1] INTERSECT [Requête 2]  --> Renvoie les lignes présentes dans les DEUX requêtes.
[Requête 1] EXCEPT [Requête 2]     --> Renvoie les lignes présentes dans la Requête 1 mais PAS dans la Requête 2.
```

### `UNION` vs. `UNION ALL`
* **`UNION`** : Fusionne les jeux de résultats et supprime les doublons. Pour ce faire, la base de données doit trier les données ou construire une table de hachage temporaire, ce qui engendre un coût en termes de performances.
* **`UNION ALL`** : Fusionne les jeux de résultats mais préserve tous les doublons. Il n'effectue aucun tri ni dédoublonnement, ce qui le rend beaucoup plus rapide.

### `INTERSECT` et `EXCEPT` (Avec et sans `ALL`)
Le standard SQL définit deux modes pour les opérations sur les ensembles :
1. **Par défaut (Distinct)** : Supprime les lignes en double avant de renvoyer les résultats.
2. **`ALL`** : Préserve la cardinalité des doublons. Par exemple, si une ligne apparaît 3 fois dans la Requête 1 et 2 fois dans la Requête 2 :
   - `INTERSECT ALL` la renvoie `MIN(3, 2) = 2` fois.
   - `EXCEPT ALL` la renvoie `3 - 2 = 1` fois.

---

## 3. MySQL vs. PostgreSQL : Compatibilité & Syntaxe

C'est l'un des domaines où les deux moteurs de base de données divergent considérablement, en particulier concernant les anciennes versions.

### Tableau de compatibilité des opérations sur les ensembles

| Opérateur | PostgreSQL (Toutes versions) | MySQL 8.0.31+ | MySQL < 8.0.31 |
| :--- | :--- | :--- | :--- |
| `UNION` / `UNION ALL` | Supporté nativement | Supporté nativement | Supporté nativement |
| `INTERSECT` (Distinct) | Supporté nativement | Supporté nativement | *Non supporté* |
| `EXCEPT` (Distinct) | Supporté nativement | Supporté nativement | *Non supporté* |
| `INTERSECT ALL` | Supporté nativement | *Non supporté* | *Non supporté* |
| `EXCEPT ALL` | Supporté nativement | *Non supporté* | *Non supporté* |

### Solutions de contournement pour MySQL (Simulation)

Si vous travaillez sur des versions de MySQL antérieures à 8.0.31, vous devez simuler `INTERSECT` et `EXCEPT` en utilisant des jointures ou des sous-requêtes.

#### Simuler `INTERSECT` :
```sql
-- MySQL < 8.0.31 Equivalent of INTERSECT
SELECT DISTINCT a.email 
FROM users_a a
INNER JOIN users_b b ON a.email = b.email;
```

#### Simuler `EXCEPT` :
```sql
-- MySQL < 8.0.31 Equivalent of EXCEPT
SELECT DISTINCT a.email 
FROM users_a a
LEFT JOIN users_b b ON a.email = b.email
WHERE b.email IS NULL;
```

> [!TIP]
> **Exclusivité PostgreSQL : Contraintes d'exclusion**
> Dans PostgreSQL, vous pouvez interdire le chevauchement de deux lignes dans une table en utilisant une contrainte d'exclusion (`EXCLUSION CONSTRAINT`) avec un index GiST.
> ```sql
> -- PostgreSQL Only: Prevent overlapping bookings at the database schema level
> ALTER TABLE bookings ADD CONSTRAINT no_overlap 
> EXCLUDE USING gist (room_id WITH =, tsrange(start_date, end_date) WITH &&);
> ```
> *Dans MySQL, l'application de cette règle nécessite des déclencheurs (triggers) personnalisés BEFORE INSERT/UPDATE.*

---

## 4. Résumé & Bonnes pratiques

1. **Préférez `UNION ALL` à `UNION`** : À moins que vous n'ayez explicitement besoin de filtrer les doublons, utilisez toujours `UNION ALL` pour éviter la surcharge de tri de la base de données.
2. **Attention aux jointures** : Lors de l'écriture de jointures non équivalentes, vérifiez le plan d'exécution de la requête (`EXPLAIN`) pour vous assurer que la base de données n'exécute pas une boucle imbriquée lente sur des millions de lignes.
3. **Gérez la compatibilité** : Si vous écrivez des requêtes pour des applications multi-bases de données, évitez le `INTERSECT`/`EXCEPT` natif ou les syntaxes lourdes en simulations. Utilisez plutôt des structures avec `EXISTS` et `LEFT JOIN`.
