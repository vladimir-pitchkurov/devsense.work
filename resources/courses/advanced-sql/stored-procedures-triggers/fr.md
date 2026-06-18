# Procédures stockées, fonctions et déclencheurs

Rapprocher la logique métier de vos données peut réduire considérablement la latence du réseau et garantir une intégrité stricte des données. Lorsque vous écrivez une logique de programmation qui s'exécute directement à l'intérieur de la base de données, vous utilisez trois structures principales : les **fonctions**, les **procédures stockées** (stored procedures) et les **déclencheurs** (triggers).

Comprendre comment ces éléments gèrent les transactions, accèdent à la mémoire et diffèrent entre MySQL et PostgreSQL est essentiel pour concevoir des bases de données fiables.

---

## 1. Fonctions définies par l'utilisateur (UDF) vs. Procédures stockées

Bien que ces deux éléments regroupent des instructions SQL, ils répondent à des objectifs très différents. La différence la plus critique réside dans le **contrôle des transactions**.

| Fonctionnalité | Fonction définie par l'utilisateur (UDF) | Procédure stockée |
| :--- | :--- | :--- |
| **Appel** | Appelée en ligne dans les requêtes (ex. `SELECT ma_fonction(val)`) | Appelée de manière autonome avec `CALL ma_procedure(val)` |
| **Valeur de retour** | Doit renvoyer une valeur unique ou une table | Ne renvoie pas de valeur (utilise des paramètres `OUT` à la place) |
| **Transactions** | **Ne peut pas** exécuter `COMMIT` ou `ROLLBACK` | **Peut** exécuter `COMMIT` ou `ROLLBACK` |
| **Contexte d'utilisation** | Lire / Transformer les données dans les requêtes | Orchestrer des écritures et opérations par lots complexes |

### Exemple de contrôle de transaction (procédure stockée)
Les procédures stockées peuvent contrôler les transactions nativement, ce qui vous permet de valider (commit) ou d'annuler (rollback) les modifications en cours d'exécution.

```sql
-- PostgreSQL 11+ Syntax
CREATE PROCEDURE transfer_funds(sender INT, receiver INT, amount DECIMAL)
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE accounts SET balance = balance - amount WHERE id = sender;
    UPDATE accounts SET balance = balance + amount WHERE id = receiver;
    
    -- Commit the transaction inside the procedure
    COMMIT;
EXCEPTION WHEN OTHERS THEN
    -- Rollback changes if anything fails
    ROLLBACK;
END;
$$;

-- MySQL Syntax
CREATE PROCEDURE transfer_funds(IN sender INT, IN receiver INT, IN amount DECIMAL)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
    END;

    START TRANSACTION;
    UPDATE accounts SET balance = balance - amount WHERE id = sender;
    UPDATE accounts SET balance = balance + amount WHERE id = receiver;
    COMMIT;
END;
```

---

## 2. Déclencheurs (Triggers) : Automatiser les règles métier

Un **déclencheur** (trigger) est un objet de base de données qui exécute automatiquement un ensemble d'instructions SQL spécifié lorsqu'un événement (tel que `INSERT`, `UPDATE` ou `DELETE`) se produit sur une table.

### Moment d'exécution du déclencheur
* **`BEFORE`** : S'exécute *avant* que la base de données n'écrive les modifications sur le disque. Utilisé pour valider ou modifier les valeurs entrantes.
* **`AFTER`** : S'exécute *après* que la base de données a écrit les modifications sur le disque. Utilisé pour la journalisation, l'audit ou la mise à jour d'autres tables.
* **`INSTEAD OF`** : Utilisé sur les vues pour remplacer les actions d'écriture par défaut par une logique personnalisée.

### Déclencheurs au niveau de la ligne vs. au niveau de l'instruction
* **`FOR EACH ROW`** : Le déclencheur s'exécute une fois pour chaque ligne affectée par la requête. Si une mise à jour affecte 10 000 lignes, le déclencheur s'exécute 10 000 fois.
* **`FOR EACH STATEMENT`** : Le déclencheur s'exécute exactement une fois par instruction SQL, quel que soit le nombre de lignes modifiées. Utile pour la journalisation ou les vérifications globales.

### Les pseudo-enregistrements `OLD` et `NEW`
Les déclencheurs ont accès à des variables spéciales représentant l'état de la ligne :
* **`NEW`** : Contient les nouvelles valeurs de la ligne (disponible pour `INSERT` et `UPDATE`).
* **`OLD`** : Contient les valeurs d'origine de la ligne (disponible pour `UPDATE` et `DELETE`).

---

## 3. MySQL vs. PostgreSQL : Différences syntaxiques et structurelles

L'architecture d'exécution des déclencheurs et des procédures diffère fortement entre ces deux bases de données.

### 1. Architecture des déclencheurs : Fonctions vs. En ligne
* **PostgreSQL** : Nécessite un processus en deux étapes. Tout d'abord, vous devez écrire une fonction de déclenchement (trigger function) qui renvoie le type spécial `TRIGGER`. Deuxièmement, vous liez cette fonction à la table à l'aide de `CREATE TRIGGER`.
```sql
-- PostgreSQL: 1. Create Trigger Function
CREATE FUNCTION log_salary_change() RETURNS TRIGGER AS $$
BEGIN
    IF NEW.salary <> OLD.salary THEN
        INSERT INTO salary_audit(emp_id, old_val, new_val)
        VALUES (OLD.id, OLD.salary, NEW.salary);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- PostgreSQL: 2. Bind Function to Table
CREATE TRIGGER after_salary_update
AFTER UPDATE ON employees
FOR EACH ROW EXECUTE FUNCTION log_salary_change();
```

* **MySQL** : Vous permet d'écrire la logique du déclencheur directement en ligne dans la définition de celui-ci.
```sql
-- MySQL: Direct inline trigger definition
CREATE TRIGGER after_salary_update
AFTER UPDATE ON employees
FOR EACH ROW
BEGIN
    IF NEW.salary <> OLD.salary THEN
        INSERT INTO salary_audit(emp_id, old_val, new_val)
        VALUES (OLD.id, OLD.salary, NEW.salary);
    END IF;
END;
```

### 2. Prise en charge des langages
* **MySQL** : Ne prend en charge que son extension de syntaxe SQL propriétaire.
* **PostgreSQL** : Prend en charge plusieurs langages. Bien que `PL/pgSQL` soit le langage par défaut, vous pouvez écrire des fonctions et des déclencheurs en `PL/Python`, `PL/Perl` ou `PL/v8` (JavaScript).

> [!WARNING]
> **Surcharge de performance des déclencheurs !**
> Les déclencheurs au niveau de la ligne (`FOR EACH ROW`) peuvent entraîner une grave dégradation des performances lors des mises à jour en masse. Si vous mettez à jour 1 million de lignes, un déclencheur au niveau de la ligne oblige la base de données à effectuer 1 million de changements de contexte entre le moteur d'exécution SQL et l'interpréteur procédural, transformant une requête rapide basée sur des ensembles en une boucle lente ligne par ligne.

> [!TIP]
> **Contrainte de transaction dans PostgreSQL :**
> Bien que les procédures stockées de PostgreSQL 11+ prennent en charge les commandes de transaction (`COMMIT`/`ROLLBACK`), elles ne peuvent pas les exécuter si la procédure est appelée depuis l'intérieur d'un bloc de transaction déjà actif (par exemple, si vous exécutez `BEGIN; CALL ma_procedure();`).

---

## 4. Résumé & Bonnes pratiques

1. **Fonctions pour les calculs** : Utilisez les UDF pour les transformations et les calculs au sein des requêtes. N'essayez pas d'y modifier les données ou de contrôler les transactions.
2. **Procédures pour les flux de travail** : Utilisez des procédures stockées pour exécuter des mises à jour par lots, orchestrer des écritures complexes et contrôler les transactions.
3. **Gardez les déclencheurs légers** : N'utilisez les déclencheurs que pour l'intégrité des données critiques, la journalisation ou l'audit. Si vous devez les utiliser, gardez la logique minimale pour éviter les goulots d'étranglement en écriture.
4. **Attention aux opérations en masse** : Désactivez ou contournez les déclencheurs lors d'importations massives de données ou de migrations pour éviter l'effondrement des performances du serveur.
