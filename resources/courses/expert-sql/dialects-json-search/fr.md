# Dialectes, JSON et recherche JSON

Les applications modernes gèrent fréquemment des données semi-structurées, telles que des charges utiles d'API (payloads), des paramètres utilisateur dynamiques ou des attributs polymorphes. Traditionnellement, cela nécessitait des anti-patterns EAV (Entity-Attribute-Value) ou le passage à des bases de données NoSQL. Aujourd'hui, les bases de données relationnelles prennent en charge nativement le format JSON avec des formats de stockage binaires, de l'indexation et de riches capacités de requête. Cependant, PostgreSQL et MySQL abordent la représentation JSON, la syntaxe des chemins et les mécanismes d'indexation de manières différentes.

---

## 1. Fonctionnement interne du stockage : JSON texte vs. JSON binaire

La façon dont le JSON est stocké dicte les performances des requêtes et la viabilité des index.

### PostgreSQL : `json` vs. `jsonb`
PostgreSQL propose deux types de données JSON :
* **`json`** : Stocke les données sous forme d'une copie textuelle exacte du JSON d'entrée. Il conserve les espaces, la mise en forme et les clés en double. Cependant, il nécessite d'analyser le texte à chaque opération de lecture, ce qui ralentit les requêtes de recherche.
* **`jsonb`** : Décompose le JSON dans un format binaire analysé. Il supprime les espaces, élimine les clés en double (en conservant la dernière) et trie les clés d'objet pour des recherches rapides. L'analyse a lieu une seule fois lors des opérations d'écriture, ce qui rend l'indexation et la recherche extrêmement rapides.

### MySQL : Le type de données `JSON`
MySQL fournit un type `JSON` unique. Sous le capot, MySQL stocke le JSON dans un format binaire similaire au `jsonb` de PostgreSQL. Il valide la syntaxe JSON lors de l'insertion et permet un accès rapide en lecture aux éléments du document sans réanalyser le texte brut.

---

## 2. Interroger du JSON : Syntaxe et expressions de chemin

L'extraction de données à partir d'objets JSON nécessite des opérateurs de chemin et une syntaxe spécifiques.

### Opérateurs JSON de PostgreSQL
PostgreSQL fournit des opérateurs pour extraire des données et tester la structure du document :
* `->` renvoie un objet JSON ou un élément de tableau (en conservant le type `jsonb`).
* `->>` renvoie l'élément sous forme de chaîne de caractères en texte brut.
* `#>` et `#>>` extraient des objets imbriqués en utilisant un tableau de chemins.
* `@>` teste l'inclusion (si le JSON de gauche contient le JSON de droite).

```sql
-- PostgreSQL: Querying jsonb
SELECT 
    data -> 'user' ->> 'name' AS username,
    data #>> '{user, profile, age}' AS age
FROM app_logs
WHERE data @> '{"status": "error"}';
```

### Fonctions & opérateurs JSON de MySQL
MySQL utilise des fonctions standards ou des opérateurs en ligne à l'aide de la syntaxe de chemin `$` :
* `JSON_EXTRACT(col, 'path')` extrait des données.
* `->` agit comme un alias pour `JSON_EXTRACT`.
* `->>` (opérateur de chemin en ligne) extrait les données et supprime les guillemets du résultat (équivalent à `JSON_UNQUOTE(JSON_EXTRACT(...))`).
* `JSON_CONTAINS(target, candidate, [path])` vérifie si un document en contient un autre.

```sql
-- MySQL: Querying JSON
SELECT 
    data->'$.user.name' AS username,
    data->>'$.user.profile.age' AS age
FROM app_logs
WHERE JSON_CONTAINS(data, '"error"', '$.status');
```

---

## 3. Indexation de documents JSON

Scanner chaque document JSON dans une table d'un million de lignes est destructeur pour les performances. Nous devons indexer les données.

### PostgreSQL : GIN (Generalized Inverted Indexes)
Le type `jsonb` de PostgreSQL s'intègre pleinement aux **index GIN**, qui indexent chaque clé et valeur à l'intérieur du document JSON.
* **GIN par défaut** (`jsonb_ops`) : Indexe les clés, les valeurs et les chemins. Prend en charge les requêtes contenant `@>`, `?`, `?|` et `?&`.
* **GIN spécifique au chemin** (`jsonb_path_ops`) : Indexe uniquement les paires chemin-valeur. Crée des fichiers d'index plus petits et s'avère plus rapide pour les requêtes d'inclusion (`@>`), mais ne prend pas en charge les vérifications d'existence de clé (`?`).

```sql
-- PostgreSQL: Creating GIN Indexes
CREATE INDEX idx_logs_data ON app_logs USING gin (data);
CREATE INDEX idx_logs_data_path ON app_logs USING gin (data jsonb_path_ops);
```

### MySQL : Colonnes virtuelles & index multi-valeurs
MySQL ne prend pas en charge l'indexation directe de l'ensemble du document JSON. À la place, il s'appuie sur deux techniques :
1. **Colonnes générées (virtuelles) + B-Tree** : Extraire une clé JSON spécifique dans une colonne virtuelle et indexer cette colonne.
2. **Index multi-valeurs** : Introduits dans MySQL 8.0.17, ils permettent d'indexer des tableaux à l'intérieur d'un document JSON, prenant en charge les recherches via `MEMBER OF()`, `JSON_CONTAINS()` et `JSON_OVERLAPS()`.

```sql
-- MySQL: Generated Column Indexing
ALTER TABLE app_logs ADD COLUMN log_status VARCHAR(50) 
    GENERATED ALWAYS AS (data->>'$.status') VIRTUAL;
CREATE INDEX idx_logs_status ON app_logs(log_status);

-- MySQL: Multi-Valued Index on JSON Array
-- If data contains: {"tags": ["admin", "system", "web"]}
CREATE INDEX idx_logs_tags ON app_logs( (CAST(data->'$.tags' AS UNSIGNED ARRAY)) );

-- Query using Multi-Valued Index
SELECT * FROM app_logs WHERE 3 MEMBER OF (data->'$.tags');
```

> [!WARNING]
> **Pièges des types de données avec les colonnes générées**
> Lors de la création de colonnes virtuelles dans MySQL, faites toujours correspondre le type de données extrait. Si votre champ JSON contient des entiers, convertissez (cast) la colonne virtuelle ou utilisez le type approprié. Les incompatibilités empêchent l'optimiseur d'utiliser l'index lors de l'exécution de la requête.

---

## 4. Matrice de comparaison des fonctionnalités

| Fonctionnalité | PostgreSQL (`jsonb`) | MySQL (`JSON`) |
| :--- | :--- | :--- |
| **Format de stockage** | Représentation binaire triée | Représentation binaire native |
| **Opérateur de chemin (sans guillemets)** | `->>` ou `#>>` | `->>` |
| **Langage de chemin standard** | SQL/JSON Path (PostgreSQL 12+) | Syntaxe JSONPath (`$.key`) |
| **Vérification d'inclusion** | Opérateur `@>` | Fonction `JSON_CONTAINS()` |
| **Indexation complète du document** | Oui (via les index GIN) | Non (nécessite des col. générées / index fonctionnel) |
| **Indexation de tableau** | Oui (GIN intégré) | Oui (Index multi-valeurs, MySQL 8.0.17+) |

---

## 5. Résumé & Bonnes pratiques

1. **Utilisez toujours des types binaires** : Dans PostgreSQL, choisissez toujours `jsonb` plutôt que `json` à moins que vous ne fassiez que stocker et récupérer sans jamais interroger ni modifier.
2. **Concevez pour les index** : Dans PostgreSQL, utilisez des index GIN pour une recherche flexible. Dans MySQL, définissez des colonnes générées virtuelles pour les clés imbriquées fréquemment recherchées.
3. **Gérez correctement les guillemets** : Faites attention à `->` vs `->>` (ou `JSON_EXTRACT` vs `JSON_UNQUOTE`). L'utilisation du mauvais opérateur laisse des guillemets autour des valeurs de chaîne, ce qui fait échouer les vérifications de comparaison.
