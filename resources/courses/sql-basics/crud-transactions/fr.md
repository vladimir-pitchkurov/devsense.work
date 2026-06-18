# CRUD et transactions

Récupérer des données ne représente que la moitié du travail. Pour créer des applications dynamiques, vous devez écrire, modifier et supprimer des enregistrements (des opérations collectivement connues sous le sigle CRUD : Create, Read, Update, Delete). De plus, lors de l'exécution de plusieurs modifications de base de données liées (telles que le transfert d'argent entre deux comptes), vous devez garantir que soit toutes les opérations réussissent, soit aucune ne s'applique. C'est là que les **transactions** interviennent.

Dans ce dernier chapitre, nous allons maîtriser la modification des données, la mise en œuvre des transactions et la compréhension des différences clés dans la façon dont PostgreSQL et MySQL gèrent les états transactionnels et les conflits d'écriture.

---

## 1. Modifier les données : INSERT, UPDATE et DELETE

### Insérer des enregistrements
Vous pouvez insérer une seule ligne ou effectuer une insertion en masse dans une seule requête.
```sql
-- MySQL & PostgreSQL
-- Single insert
INSERT INTO users (email, name) VALUES ('bob@devsense.work', 'Bob');

-- Bulk insert (recommended for performance)
INSERT INTO users (email, name) 
VALUES 
    ('alice@devsense.work', 'Alice'),
    ('charlie@devsense.work', 'Charlie');
```

### Mettre à jour des enregistrements
Modifie des enregistrements existants. Assurez-vous de toujours filtrer votre mise à jour à l'aide d'une clause `WHERE`.
```sql
-- MySQL & PostgreSQL
UPDATE users SET is_active = true WHERE id = 42;
```

### Supprimer des enregistrements
Supprime définitivement des enregistrements. Tout comme `UPDATE`, cela nécessite une clause `WHERE` pour éviter des pertes de données catastrophiques.
```sql
-- MySQL & PostgreSQL
DELETE FROM users WHERE is_active = false;
```

> [!WARNING]
> **Le piège de la clause `WHERE` manquante !**
> Si vous exécutez `UPDATE users SET is_active = true;` ou `DELETE FROM users;` sans clause `WHERE`, le moteur modifiera ou supprimera **chaque ligne** de votre table. Vérifiez toujours deux fois votre requête avant l'exécution !

---

## 2. Transactions : Préserver l'intégrité des données

Une transaction est une séquence d'instructions SQL exécutées comme une seule unité de travail indivisible. Les transactions respectent les normes **ACID** :
* **Atomicité (Atomicity)** : Toutes les instructions réussissent, ou la transaction entière est annulée (tout ou rien).
* **Cohérence (Consistency)** : Garantit que la base de données passe d'un état valide à un autre, en respectant toutes les contraintes.
* **Isolation (Isolation)** : Les transactions s'exécutant simultanément n'interfèrent pas les unes avec les autres.
* **Durabilité (Durability)** : Une fois validées, les modifications sont écrites de manière permanente sur le disque et survivent aux pannes du système.

### Commandes de transaction
* **`BEGIN` / `START TRANSACTION`** : Démarre le bloc de transaction.
* **`COMMIT`** : Enregistre définitivement toutes les modifications apportées au cours de la transaction.
* **`ROLLBACK`** : Annule toutes les modifications apportées au cours de la transaction, rétablissant la base de données dans son état antérieur à la transaction.

```sql
-- MySQL & PostgreSQL
BEGIN; -- Start transaction

UPDATE accounts SET balance = balance - 100 WHERE id = 1;
UPDATE accounts SET balance = balance + 100 WHERE id = 2;

-- If everything is fine:
COMMIT;

-- If a query failed or we changed our mind:
-- ROLLBACK;
```

---

## 3. Différences clés : MySQL vs. PostgreSQL

### Syntaxe d'Upsert (Insertion ou mise à jour lors d'un conflit de clé)
Un « upsert » insère une ligne si elle n'existe pas, ou la met à jour si elle entre en conflit avec un index unique ou une clé primaire existante.
* **MySQL** : Utilise la clause `ON DUPLICATE KEY UPDATE`.
* **PostgreSQL** : Utilise la clause `ON CONFLICT (conflict_column) DO UPDATE`.

```sql
-- MySQL (ON DUPLICATE KEY UPDATE)
INSERT INTO users (id, email, visits) 
VALUES (1, 'user@devsense.work', 1)
ON DUPLICATE KEY UPDATE visits = visits + 1;

-- PostgreSQL (ON CONFLICT DO UPDATE)
INSERT INTO users (id, email, visits) 
VALUES (1, 'user@devsense.work', 1)
ON CONFLICT (id) DO UPDATE SET visits = users.visits + 1;
```

### DDL transactionnel (Data Definition Language)
C'est l'une des différences structurelles les plus critiques entre les deux bases de données.
* **PostgreSQL** prend pleinement en charge le DDL transactionnel. Vous pouvez exécuter des commandes telles que `CREATE TABLE`, `DROP TABLE` ou `ALTER TABLE` à l'intérieur d'une transaction et les annuler en toute sécurité si quelque chose se passe mal.
* **MySQL** ne prend PAS en charge le DDL transactionnel. Si vous exécutez une instruction DDL à l'intérieur d'une transaction dans MySQL, cela déclenche une **validation implicite** (implicit commit, également appelée validation automatique). MySQL valide immédiatement la transaction jusqu'à ce point, exécute le DDL, et vous ne pouvez plus annuler les écritures précédentes.

```sql
-- PostgreSQL (This works and will be fully rolled back!)
BEGIN;
DROP TABLE users;
ROLLBACK; -- Table 'users' is safely restored!

-- MySQL (This will fail to roll back!)
START TRANSACTION;
INSERT INTO logs (message) VALUES ('Deleting users table');
DROP TABLE users; -- Triggers implicit commit!
ROLLBACK; -- Does nothing; the insert and DROP are already committed!
```

### Syntaxe de démarrage de transaction
* **PostgreSQL** : Préfère la commande standard `BEGIN` (bien qu'il accepte `START TRANSACTION`).
* **MySQL** : Préfère `START TRANSACTION` (bien qu'il accepte `BEGIN` dans la plupart des contextes clients ; cependant, à l'intérieur des procédures stockées, `BEGIN` est réservé pour les structures de blocs, donc `START TRANSACTION` y est requis).

> [!TIP]
> **Le saviez-vous ?**
> PostgreSQL prend en charge la clause `RETURNING` pour les opérations d'écriture. Cela vous permet de récupérer immédiatement la clé primaire générée ou les colonnes calculées sans exécuter de requête `SELECT` distincte.
> `INSERT INTO users (email) VALUES ('new@devsense.work') RETURNING id, created_at;`
> *MySQL ne prend pas en charge `RETURNING` et nécessite que les bibliothèques clientes appellent des fonctions telles que `LAST_INSERT_ID()` pour récupérer la clé générée.*

---

## 4. Résumé & Bonnes pratiques

1. **Gardez les transactions courtes** : Les transactions de longue durée maintiennent des verrous sur les lignes de la base de données, bloquant les autres utilisateurs et augmentant la probabilité d'interblocages (deadlocks).
2. **Attention aux validations implicites dans MySQL** : Ne mélangez pas les modifications de schéma (DDL comme `ALTER TABLE`) avec les modifications de données (DML comme `UPDATE`) au sein des transactions si vous prévoyez d'utiliser des annulations (rollbacks).
3. **Utilisez les Upserts avec précaution** : Utilisez la syntaxe correcte pour votre base de données (`ON CONFLICT` pour PostgreSQL et `ON DUPLICATE KEY UPDATE` pour MySQL) afin de gérer proprement les violations de contrainte de clé en double.
4. **Spécifiez toujours WHERE** : Protégez-vous contre la suppression accidentelle de tables entières en vous assurant que les requêtes `UPDATE` et `DELETE` contiennent des clauses `WHERE` strictes.
