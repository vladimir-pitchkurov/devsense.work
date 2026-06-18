# Concurrence, MVCC et gonflement de table

Dans une base de données à haut débit, des milliers de transactions lisent et écrivent des données de manière concurrente. Si chaque lecture verrouillait la ligne correspondante, les bases de données tourneraient au ralenti. Pour résoudre ce problème, les bases de données relationnelles modernes utilisent le **contrôle de concurrence multi-version (Multi-Version Concurrency Control - MVCC)**. La philosophie centrale de MVCC est simple : *les lecteurs ne bloquent pas les rédacteurs, et les rédacteurs ne bloquent pas les lecteurs*. Cependant, MySQL et PostgreSQL implémentent cette philosophie de manières fondamentalement différentes, ce qui conduit à des profils de performance, des exigences de maintenance et des stratégies d'optimisation complètement différents.

---

## 1. Comment fonctionne MVCC sous le capot

Lorsqu'une transaction met à jour une ligne, la base de données n'écrase pas immédiatement l'ancienne donnée. Au lieu de cela, elle conserve plusieurs versions de cette ligne, permettant aux transactions actives de voir un instantané cohérent des données en fonction de leur niveau d'isolation.

### PostgreSQL : Versionnage des tuples (In-Heap)
PostgreSQL conserve toutes les versions de lignes (appelées « tuples ») directement dans le tas de la table (heap).
* **xmin** : L'identifiant de transaction (TxID) de la transaction qui a inséré la ligne.
* **xmax** : L'identifiant de transaction de la transaction qui a supprimé ou mis à jour la ligne (initialement `0` ou null).

Lorsque vous mettez à jour une ligne dans Postgres, le moteur effectue un `DELETE` logique suivi d'un `INSERT`. Il marque le `xmax` de la ligne existante avec le TxID actuel et insère une nouvelle ligne avec un `xmin` égal au TxID actuel.

### MySQL (InnoDB) : Journaux d'annulation (Undo Logs) & segments de rollback
Le moteur InnoDB de MySQL adopte une approche différente. Il effectue les mises à jour sur place à l'intérieur de l'espace de table (tablespace) mais écrit l'ancienne version de la ligne dans une structure dédiée appelée le journal d'annulation (**Undo Log**).
* Chaque en-tête de ligne contient un `DB_TRX_ID` (l'identifiant de transaction qui a modifié la ligne en dernier) et un `DB_ROLL_PTR` (un pointeur de rollback pointant vers l'enregistrement de l'undo log contenant la version précédente).
* Lorsqu'un lecteur a besoin d'un instantané plus ancien, InnoDB démarre à partir de la ligne active et parcourt la chaîne d'annulation vers l'arrière en utilisant le pointeur de rollback pour reconstruire les données à la volée.

---

## 2. Gonflement de table PostgreSQL, VACUUM et mises à jour HOT

Puisque PostgreSQL stocke les tuples obsolètes (dead tuples - les anciennes versions des lignes mises à jour ou supprimées) directement dans les pages de table, la taille physique des tables augmente naturellement avec le temps. Ce phénomène est appelé le **gonflement de table** (table bloat).

```
Mise à jour d'une page du tas (Heap Page) PostgreSQL :
+-------------------------------------------------------------+
| [Tuple v1 (xmin: 100, xmax: 101)] -> Tuple mort (Gonflement)|
| [Tuple v2 (xmin: 101, xmax: 0)]   -> Tuple vivant           |
+-------------------------------------------------------------+
```

### Le rôle de VACUUM
Pour récupérer l'espace occupé par les tuples morts, PostgreSQL exécute un processus d'arrière-plan appelé **Autovacuum**.
* **VACUUM standard** : Parcourt les pages, marque les tuples morts comme réutilisables pour de futures insertions. Il ne restitue *pas* l'espace au système d'exploitation (sauf si les pages tout à la fin de la table sont complètement vides).
* **VACUUM FULL** : Reconstruit entièrement la table, la réduisant et restituant l'espace au système d'exploitation. **Attention** : Cette opération verrouille la table de manière exclusive (`ACCESS EXCLUSIVE`), bloquant toutes les lectures et écritures.

### Optimisation HOT (Heap-Only Tuple)
Pour éviter de mettre à jour les pointeurs d'index à chaque modification de version de ligne, PostgreSQL utilise des **mises à jour HOT**. Si une mise à jour ne modifie pas les colonnes indexées et que la page dispose de suffisamment d'espace libre :
1. Postgres place le nouveau tuple sur la même page.
2. Il lie l'ancien tuple au nouveau tuple.
3. Les index continuent de pointer vers l'ancien tuple ; les lecteurs suivent la chaîne.

> [!WARNING]
> **Le réglage de l'Autovacuum est obligatoire !**
> Par défaut, les paramètres d'autovacuum de PostgreSQL sont notoirement conservateurs. Sur des tables subissant de nombreuses écritures, cela entraîne un gonflement incontrôlé (bloat), ce qui dégrade les performances des requêtes car la base de données doit scanner des tuples morts. Ajustez toujours le paramètre `autovacuum_vacuum_scale_factor` (par défaut à 0,20 ou 20 % de lignes modifiées) à `0,05` ou moins pour les grandes tables.

---

## 3. MySQL InnoDB : Purge et troncature des Undo Logs

Dans MySQL, le gonflement des tables est un problème beaucoup moins prononcé car les anciennes versions de lignes sont stockées dans les undo logs plutôt que dans l'espace de table. Cependant, InnoDB possède ses propres goulots d'étranglement de concurrence.

### Threads de purge d'InnoDB
Dès que la transaction active la plus ancienne n'a plus besoin d'un enregistrement d'undo log, un processus d'arrière-plan appelé le **Purge Thread** nettoie les pages des undo logs.
* Si une transaction reste ouverte pendant des heures (par exemple, pour une longue requête de rapport), InnoDB ne peut purger aucun undo log créé depuis le démarrage de cette transaction.
* Cela provoque le gonflement de l'**espace de table d'annulation** (Undo Tablespace). Dans les anciennes versions de MySQL, ces espaces de table d'annulation ne pouvaient pas se contracter, ce qui nécessitait une reconstruction complète de la base de données pour récupérer de l'espace disque.

```sql
-- MySQL: Inspecting InnoDB Undo Log & Transaction States
SELECT 
    trx_id, trx_state, trx_started, 
    TIMESTAMPDIFF(SECOND, trx_started, NOW()) AS duration_sec
FROM information_schema.innodb_trx
ORDER BY trx_started ASC;
```

> [!TIP]
> **Troncature automatique des Undo Logs**
> Dans MySQL 8.0, InnoDB tronque automatiquement les espaces de table d'annulation par défaut. Assurez-vous que `innodb_undo_log_truncate = ON` et `innodb_max_undo_log_size` (généralement 1 Go) sont configurés pour récupérer automatiquement l'espace disque de l'espace de table d'annulation.

---

## 4. Matrice de comparaison MVCC

| Fonctionnalité | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Stockage des anciennes versions** | In-Heap (directement dans les fichiers de la table) | Journaux d'annulation (Undo Logs - segments de rollback distincts) |
| **Mécanisme de mise à jour** | Suppression + Insertion (crée un nouveau tuple) | Mise à jour sur place + écriture de l'ancienne version dans l'Undo |
| **Mises à jour d'index** | Nécessite la mise à jour de tous les index (sauf HOT) | Ne met à jour l'index que si la colonne indexée a changé |
| **Nettoyage de l'espace disque** | Vacuum / Autovacuum (récupère les emplacements de page) | Threads de purge (nettoie les pages des journaux d'annulation) |
| **Risque de gonflement** | Gonflement élevé des tables et des index | Gonflement élevé des journaux d'annulation avec les transactions longues |

---

## 5. Résumé & Bonnes pratiques

1. **Gardez les transactions courtes** : Les deux moteurs souffrent si les transactions restent ouvertes trop longtemps. Dans Postgres, cela empêche l'autovacuum de nettoyer les tuples morts ; dans MySQL, cela empêche les threads de purge de nettoyer les undo logs.
2. **Configurez le Fillfactor de PostgreSQL** : Pour les tables avec de gros volumes de mises à jour, réduisez le `fillfactor` de la table (par exemple à 80 ou 90) afin de laisser de l'espace libre sur chaque page pour les mises à jour HOT.
3. **Surveillez l'espace d'annulation (Undo) dans MySQL** : Configurez des alertes pour les transactions actives de longue durée afin de prévenir l'explosion de l'espace d'annulation.
