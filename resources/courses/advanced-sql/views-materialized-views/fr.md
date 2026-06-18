# Vues et vues matérialisées : Abstraction vs. Mise en cache

Dans la conception de bases de données hautes performances, nous sommes souvent confrontés à deux problèmes contradictoires :
1. **La complexité des requêtes** : Écrire et maintenir de longues requêtes imbriquées qui s'étendent sur des dizaines de jointures.
2. **La latence d'exécution** : Exécuter des requêtes lourdes en agrégations (comme des rapports de ventes) sur des millions de lignes à chaque chargement de page.

Le langage SQL répond à ces défis grâce aux **vues** (Views) et aux **vues matérialisées** (Materialized Views). Bien qu'elles semblent similaires, leurs modèles d'exécution sous-jacents sont complètement différents : l'une est un raccourci logique (abstraction) et l'autre est un cache physique de table.

---

## 1. Vues standards (virtuelles)

Une **vue** standard est une définition de requête enregistrée. Il s'agit d'une table virtuelle — elle ne stocke aucune donnée physique sur le disque. Lorsque vous interrogez une vue, le moteur de base de données fusionne la définition de la requête de la vue dans la requête principale et les exécute ensemble.

### Syntaxe de base
```sql
-- MySQL & PostgreSQL
CREATE VIEW active_customer_summary AS
SELECT c.id, c.name, COUNT(o.id) AS total_orders
FROM customers c
LEFT JOIN orders o ON c.id = o.customer_id
WHERE c.status = 'active'
GROUP BY c.id, c.name;
```

### Vues modifiables
Pouvez-vous exécuter des instructions `INSERT`, `UPDATE` ou `DELETE` sur une vue ? Oui, sous des conditions strictes. Une vue est modifiable uniquement si le moteur de base de données peut mapper les opérations d'écriture directement sur une seule table physique sous-jacente.
* **Règles** : La vue ne doit pas contenir :
  - De fonctions d'agrégation (`SUM`, `COUNT`, `AVG`).
  - De clauses `GROUP BY`, `HAVING` ou `DISTINCT`.
  - D'opérateurs d'ensembles (`UNION`, `INTERSECT`, `EXCEPT`).
  - De fonctions de fenêtrage.

> [!WARNING]
> **La clause `WITH CHECK OPTION`**
> Lors de la mise à jour de données via une vue, vous pouvez accidentellement écrire des données qui font disparaître la ligne de la vue elle-même !
> ```sql
> CREATE VIEW premium_customers AS 
> SELECT * FROM customers WHERE balance > 1000;
> ```
> Si vous exécutez `UPDATE premium_customers SET balance = 500 WHERE id = 1`, la mise à jour réussit, mais le client disparaît de la vue. Pour éviter cela, ajoutez `WITH CHECK OPTION` à la définition de la vue. Cela oblige la base de données à rejeter toute insertion ou mise à jour qui enfreint la clause `WHERE` de la vue.

---

## 2. Vues matérialisées (PostgreSQL)

Contrairement aux vues standards, une **vue matérialisée** stocke physiquement les résultats de la requête sur le disque, se comportant comme une table ordinaire. L'interrogation d'une vue matérialisée est extrêmement rapide car elle contourne les jointures et les calculs d'agrégation. Cependant, les données peuvent devenir obsolètes.

### Syntaxe et rafraîchissement
```sql
-- PostgreSQL Only
CREATE MATERIALIZED VIEW monthly_revenue_report AS
SELECT extract(year from order_date) as year, extract(month from order_date) as month, SUM(total_amount) as revenue
FROM orders
GROUP BY 1, 2;
```

Pour mettre à jour les données, vous devez déclencher manuellement un rafraîchissement :
```sql
REFRESH MATERIALIZED VIEW monthly_revenue_report;
```

### Mises à jour non bloquantes : `CONCURRENTLY`
Par défaut, `REFRESH MATERIALIZED VIEW` applique un verrou exclusif sur la vue, bloquant toutes les opérations de lecture (`SELECT`) jusqu'à ce que le rafraîchissement soit terminé. Pour mettre à jour la vue sans bloquer vos utilisateurs, utilisez l'option `CONCURRENTLY`.

```sql
-- PostgreSQL Only: Non-blocking refresh
CREATE UNIQUE INDEX idx_monthly_rev ON monthly_revenue_report (year, month);
REFRESH MATERIALIZED VIEW CONCURRENTLY monthly_revenue_report;
```
* **Condition requise** : Vous devez créer un index unique sur une ou plusieurs colonnes de la vue matérialisée avant de pouvoir utiliser la clause `CONCURRENTLY`.

---

## 3. Solutions de contournement MySQL pour les vues matérialisées

MySQL ne prend **pas** en charge nativement les vues matérialisées. Si vous avez besoin de cette fonctionnalité dans MySQL, vous devez la simuler à l'aide de l'une des deux solutions de contournement courantes.

### Solution 1 : Table standard + Planificateur d'événements (Event Scheduler)
Vous pouvez créer une table normale pour servir de cache, et écrire un événement planifié en base de données pour la rafraîchir périodiquement.

```sql
-- MySQL Only
-- 1. Create the physical table
CREATE TABLE monthly_revenue_report_cache AS
SELECT YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as revenue
FROM orders GROUP BY 1, 2;

-- 2. Create an event to refresh the table every hour
CREATE EVENT refresh_revenue_report
ON SCHEDULE EVERY 1 HOUR
DO
  BEGIN
    TRUNCATE TABLE monthly_revenue_report_cache;
    INSERT INTO monthly_revenue_report_cache
    SELECT YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as revenue
    FROM orders GROUP BY 1, 2;
  END;
```

### Solution 2 : Déclencheurs de base de données (Triggers)
Si vous avez besoin de vues matérialisées en temps réel dans MySQL, vous pouvez écrire des déclencheurs `AFTER INSERT/UPDATE/DELETE` sur la table source qui mettent à jour progressivement la table de cache de résumé.

---

## 4. Comparaison des fonctionnalités : MySQL vs. PostgreSQL

| Fonctionnalité | MySQL 8.0 | PostgreSQL |
| :--- | :--- | :--- |
| Vues virtuelles standards | Supporté nativement | Supporté nativement |
| Vues modifiables | Supporté (avec restrictions) | Supporté (avec restrictions) |
| Vues matérialisées natives | *Non supporté* | Supporté nativement |
| Rafraîchissement concurrent / non bloquant | *Non supporté* | Supporté nativement (via `CONCURRENTLY`) |
| Sécurité des vues (DEFINER/INVOKER) | Supporté nativement | Supporté nativement |

> [!TIP]
> **Conseil de sécurité : INVOKER vs. DEFINER**
> Par défaut, les vues standards dans MySQL et PostgreSQL s'exécutent avec les privilèges de l'utilisateur qui a *créé* la vue (`DEFINER`). C'est une fonctionnalité puissante qui vous permet d'accorder aux utilisateurs l'accès à des sous-ensembles spécifiques de données dans une table (comme l'exclusion d'une colonne de mot de passe) sans leur accorder de privilèges de lecture sur l'ensemble de la table sous-jacente.

---

## 5. Résumé & Bonnes pratiques

1. **Utilisez les vues standards pour l'abstraction** : Les vues standards sont excellentes pour simplifier les requêtes complexes et implémenter des rôles de sécurité en base de données.
2. **Utilisez les vues matérialisées pour la mise en cache** : Pour les agrégations lourdes sur les systèmes à forte lecture, mettez en cache les données physiquement.
3. **Rafraîchissez toujours de manière concurrente** : Dans les environnements PostgreSQL de production, créez toujours un index unique sur vos vues matérialisées afin de pouvoir les rafraîchir de manière concurrente.
4. **Utilisez le planificateur ou des déclencheurs dans MySQL** : Si vous utilisez MySQL, concevez une table de cache à l'aide d'événements ou d'une mise en cache au niveau de l'application pour simuler des vues matérialisées.
