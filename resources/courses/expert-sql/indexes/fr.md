# Index : Étude approfondie des index B-Tree, GIN, BRIN et clusterisés

Les index sont le principal outil pour accélérer les performances des requêtes. Cependant, appliquer des index aveuglément peut dégrader les performances d'écriture et consommer d'immenses quantités d'espace disque. Les bases de données relationnelles prennent en charge plusieurs types d'index, chacun conçu pour des distributions de données, des profils de requêtes et des empreintes de stockage spécifiques. Comprendre le fonctionnement interne de B-Tree, la clusterisation des clés primaires dans MySQL et les types d'index avancés dans PostgreSQL is essentiel pour le réglage professionnel des bases de données.

---

## 1. Fonctionnement interne de B-Tree et comportements de clusterisation

Le **B-Tree** (Balanced Tree) est le type d'index par défaut dans presque toutes les bases de données relationnelles. Il maintient les données triées et permet la recherche, l'accès séquentiel, les insertions et les suppressions en temps logarithmique ($O(\log n)$).

### MySQL InnoDB : Index clusterisés vs. secondaires
Dans le moteur InnoDB de MySQL, toutes les tables sont physiquement organisées autour d'un **index clusterisé** (Clustered Index).
* **Index clusterisé** : Les nœuds feuilles de l'index contiennent les données réelles de la ligne. Par défaut, il s'agit de la clé primaire (`PRIMARY KEY`) de la table. Si aucune clé primaire n'est définie, InnoDB sélectionne le premier index unique (`UNIQUE`) composé uniquement de colonnes non nulles. Si aucun n'existe, InnoDB génère un identifiant de ligne caché de 6 octets.
* **Index secondaire** : Les nœuds feuilles de tout index secondaire ne contiennent *pas* de pointeurs de données. Au lieu de cela, ils stockent la **valeur de la clé primaire** de la ligne.
* **Recherche par signet (Bookmark Lookup)** : Lorsque vous effectuez une requête à l'aide d'un index secondaire, MySQL recherche d'abord dans l'index secondaire pour trouver la clé primaire, puis effectue une seconde recherche dans l'index clusterisé pour récupérer la ligne.

```
Recherche d'index dans MySQL InnoDB :
[Recherche dans l'index secondaire] ---> Renvoie la valeur de la clé primaire (ex. ID : 42)
                                              |
                                              v
[Recherche dans l'index clusterisé]  ---> Renvoie les données de la ligne (Nom, Email, etc.)
```

### PostgreSQL : Indexation basée sur le tas (Heap)
Contrairement à MySQL, PostgreSQL n'utilise pas de tables clusterisées par défaut. Les tables sont organisées sous forme d'un « tas » (heap) de pages.
* Tous les index (y compris l'index de la clé primaire) sont des **index secondaires**.
* Les nœuds feuilles d'un index PostgreSQL pointent directement vers l'adresse physique (TID - Tuple ID, composé du numéro de page et du décalage) de la ligne dans le tas de la table.
* **Pas de recherche par signet** : Postgres va directement du nœud feuille de l'index à la page du tas. Cependant, la mise à jour d'une ligne dans Postgres modifie son adresse physique, nécessitant la mise à jour de tous les index (sauf si une mise à jour HOT se produit).

---

## 2. Types d'index avancés dans PostgreSQL

PostgreSQL propose des types d'index spécialisés qui n'ont pas d'équivalent natif dans MySQL.

### BRIN (Block Range Index)
* **Principe** : Au lieu d'indexer chaque ligne individuellement, un index BRIN divise la table en plages de blocs physiques (par défaut 128 pages ou 1 Mo de données) et stocke uniquement la valeur **minimale** et **maximale** pour chaque plage.
* **Quand l'utiliser** : Tables extrêmement volumineuses (centaines de gigaoctets) où les données sont naturellement triées sur le disque (par exemple, des identifiants auto-incrémentés, des horodatages `created_at`).
* **Avantage** : Empreinte de stockage incroyablement petite. Un index B-Tree de 10 Go peut souvent être remplacé par un index BRIN de 10 Mo.

```sql
-- PostgreSQL: Creating a BRIN index
CREATE INDEX idx_orders_date_brin ON orders USING brin (created_at);
```

### GIN (Generalized Inverted Index)
* **Principe** : Associe des valeurs (comme des éléments de tableau, des mots dans un texte ou des clés JSON) aux lignes dans lesquelles elles apparaissent.
* **Quand l'utiliser** : Indexation de tableaux, de documents JSONB ou de colonnes de recherche plein texte (full-text search).

```sql
-- PostgreSQL: GIN index for arrays
CREATE INDEX idx_user_tags ON users USING gin (tags);
```

### GiST (Generalized Search Tree)
* **Principe** : Un modèle pour construire des structures B-Tree personnalisées. Il est utilisé pour indexer des coordonnées géométriques, des types de plages et des adresses réseau.

---

## 3. Index couvrants, partiels et fonctionnels

### Index couvrants (Optimisation de l'Index-Only Scan)
Un index couvrant contient toutes les colonnes demandées par une requête. Vous pouvez ajouter des colonnes de données supplémentaires à un nœud feuille d'index en utilisant la clause `INCLUDE`.
* **Syntaxe PostgreSQL & MySQL** :
  ```sql
  -- PostgreSQL (using INCLUDE)
  CREATE INDEX idx_users_email_include ON users (email) INCLUDE (username, status);

  -- MySQL (using Composite Index - columns must be ordered)
  CREATE INDEX idx_users_email_cover ON users (email, username, status);
  ```

### Index partiels
Indexent uniquement un sous-ensemble de lignes qui correspondent à une condition de filtrage spécifique. Cela réduit la taille de l'index et la surcharge d'écriture.
* **PostgreSQL uniquement** :
  ```sql
  -- Index only active accounts
  CREATE INDEX idx_users_active_email ON users (email) WHERE status = 'active';
  ```
* *MySQL ne prend pas en charge les index partiels nativement. Vous devez utiliser des index fonctionnels avec `CASE WHEN` pour imiter ce comportement.*

### Index fonctionnels (sur expression)
Indexent le résultat d'une fonction ou d'une expression plutôt que les valeurs brutes de la colonne.
* **Syntaxe MySQL & PostgreSQL** :
  ```sql
  -- PostgreSQL
  CREATE INDEX idx_users_lower_email ON users (LOWER(email));

  -- MySQL 8.0+
  CREATE INDEX idx_users_lower_email ON users ((LOWER(email)));
  ```

> [!WARNING]
> **Pièges syntaxiques des index fonctionnels**
> Dans MySQL 8.0, les index d'expression DOIVENT être entourés de doubles parenthèses : `((expression))`. L'omission des parenthèses externes entraîne une erreur de syntaxe.

> [!TIP]
> **Fusion d'index (Index Merging)**
> Lorsqu'un filtre de requête contient des conditions `AND` ou `OR` sur plusieurs colonnes, les bases de données peuvent effectuer une **fusion d'index** (Index Merge). Elles scannent plusieurs index mono-colonne et effectuent l'intersection ou l'union des bitmaps résultants. Cependant, un index composite (multi-colonne) unique est presque toujours plus rapide que la fusion d'index distincts.

---

## 4. Matrice de comparaison des fonctionnalités d'indexation

| Fonctionnalité | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Disposition de table clusterisée** | Non (Les tables sont basées sur le tas) | Oui (Table organisée par l'index de clé primaire B-Tree) |
| **Recherche de clé primaire** | Index -> Page du tas | Directement au niveau de la feuille du B-Tree clusterisé |
| **Index partiels** | Oui (clause `WHERE`) | Non (Solution de contournement via index fonctionnel) |
| **Clause d'index couvrant** | Oui (`INCLUDE`) | Non (Doit être défini comme clé composite) |
| **Index de plage de blocs (BRIN)** | Oui | Non |
| **Index inversés (GIN)** | Oui | Non |

---

## 5. Résumé & Bonnes pratiques

1. **Évitez la surcharge des clés primaires auto-incrémentées dans MySQL** : Comme InnoDB organise les tables par clé primaire, l'insertion de UUID aléatoires en guise de clé primaire provoque de graves fractionnements de pages et une forte fragmentation. Utilisez des identifiants séquentiels ou des UUID ordonnés.
2. **Tirez parti de BRIN pour les grandes séries temporelles** : Si vous avez une table de journaux (logs) de plusieurs gigaoctets triée par horodatage, utilisez un index BRIN. Cela permet d'économiser des gigaoctets de mémoire par rapport à un index B-Tree standard.
3. **Utilisez des index partiels pour les colonnes creuses (sparse)** : Si vous interrogez fréquemment une table pour un statut rare (par exemple `WHERE status = 'retry'`), créez un index partiel sur cette condition pour maintenir la taille de l'index au plus bas.
