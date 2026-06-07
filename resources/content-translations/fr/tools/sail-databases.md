---
title: "Laravel Sail : bases de données, Redis, Postgres, MongoDB, RabbitMQ dans Docker Compose | DevSense"
description: "Recettes prêtes à l'emploi pour Laravel Sail : ajouter Redis, passer à PostgreSQL, exécuter MongoDB avec l'extension PHP, conteneur RabbitMQ, configuration de Mailpit et Meilisearch, healthchecks et volumes nommés."
faq:
  - question: "Pourquoi mon application Laravel ne parvient-elle pas à se connecter à MySQL/PostgreSQL dans Sail ?"
    answer: "Cela se produit généralement parce que DB_HOST est configuré sur 127.0.0.1 oder localhost dans le fichier .env. Dans le réseau Docker, laravel.test doit se connecter en utilisant le nom du service Compose (par exemple, mysql ou pgsql) comme nom d'hôte."
  - question: "Comment ajouter une nouvelle extension PHP (comme MongoDB) à Sail ?"
    answer: "Vous devez publier les Dockerfiles de Sail à l'aide de 'php artisan sail:publish', modifier le Dockerfile correspondant à votre version de PHP (par exemple, pecl install mongodb) et reconstruire l'image avec 'sail build --no-cache'."
  - question: "Comment vider la base de données sans perdre les autres volumes du projet ?"
    answer: "Au lieu de lancer 'sail down -v' qui détruit tous les volumes, identifiez le nom du volume spécifique à l'aide de 'docker volume ls' et supprimez-le individuellement avec 'docker volume rm <volume_name>'."
  - question: "Comment puis-je éviter les échecs de migration lors du démarrage de Sail ?"
    answer: "Configurez des healthchecks Docker pour votre base de données dans docker-compose.yml et configurez le service 'laravel.test' pour qu'il en dépende avec 'condition: service_healthy' afin que les migrations attendent que la base de données soit prête."
---

# Sail : bases de données & services Docker

Vous lancez `sail up -d`, tapez `php artisan migrate`, et obtenez un mur d'erreurs rouge `Connection refused`. Même si Docker Desktop indique que le conteneur de votre base de données fonctionne et est en bonne santé, l'application à l'intérieur refuse de lui parler. Cette rupture de connexion met en évidence une règle fondamentale du développement conteneurisé : dans Docker Compose, `127.0.0.1` fait référence au conteneur lui-même, pas au réseau.

Configurer des services externes (bases de données, caches, courtiers de messages) pour un projet Laravel peut rapidement entraîner des problèmes d'incompatibilité d'environnement entre les équipes. Sail résout ce problème en traitant vos services comme des conteneurs indépendants et reproductibles, mais vous devez configurer correctement leur connectivité réseau, leurs exigences de build et l'ordre de démarrage.

Dans ce guide, nous vous montrons comment changer et configurer les bases de données (Postgres, MongoDB), connecter le cache et les brokers (Redis, RabbitMQ), et gérer les healthchecks ainsi que la réinitialisation des volumes en toute sécurité.

**Navigation :** [Tous les outils](../) · [Aperçu de Sail](sail#what-sail-is) · [Files d'attente](sail-queues#connections) · [Env & déploiement](sail-env-deploy#forward-ports) · [Dépannage](sail-troubleshooting#wsl-filesync)

## Table des matières

* [Règles de réseau et de nom d'hôte](#networking)
* [Service Redis (exemple complet)](#redis-service)
* [Remplacer MySQL par PostgreSQL](#postgres-swap)
* [MongoDB + extension PHP dans Sail](#mongodb)
* [RabbitMQ comme conteneur](#rabbitmq-sidecar)
* [Mailpit (développement SMTP)](#mailpit)
* [Meilisearch / Typesense (recherche optionnelle)](#search-engines)
* [Healthchecks et ordre de démarrage](#healthchecks)
* [Volumes nommés et réinitialisation des données](#volumes)
* [Erreurs courantes](#common-mistakes)
* [Questions d'auto-évaluation](#self-check)

---

<a id="networking"></a>
## Règles de réseau et de nom d'hôte

Tous les hôtes côté application dans **`.env`** doivent être des **noms de service Compose** (`mysql`, `pgsql`, `redis`, `mongo`), et non `127.0.0.1`. En effet, PHP s'exécute **à l'intérieur** du conteneur `laravel.test` et communique via un réseau Docker isolé.

> [!NOTE]
> **Machine hôte vs Réseau de conteneurs**
> Depuis votre machine hôte (IDE, terminal, DBeaver), vous vous connectez aux bases de données via `127.0.0.1` en utilisant le port redirigé (par exemple, `FORWARD_DB_PORT`). Mais à l'intérieur des conteneurs, les services doivent s'adresser les uns aux autres en utilisant leurs noms de service Compose.

---

<a id="redis-service"></a>
## Service Redis (exemple complet)

Pour ajouter Redis à votre environnement Sail, définissez le service et montez un volume nommé.

```yaml
# docker-compose.yml
services:
    redis:
        image: 'redis:alpine'
        ports:
            - '${FORWARD_REDIS_PORT:-6379}:6379'
        volumes:
            - 'sail-redis:/data'
        networks:
            - sail
        healthcheck:
            test: ["CMD", "redis-cli", "ping"]
            retries: 3
            timeout: 5s
```

Enregistrez **`sail-redis`** sous la section principale **`volumes:`** et déclarez la dépendance à l'intérieur de **`laravel.test`** :

```yaml
# docker-compose.yml
services:
    laravel.test:
        depends_on:
            redis:
                condition: service_healthy
```

```dotenv
# .env
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Exécutez `sail exec redis redis-cli ping` pour vérifier la connexion.

---

<a id="postgres-swap"></a>
## Remplacer MySQL par PostgreSQL

Si votre serveur de production utilise PostgreSQL, exécuter MySQL localement est un risque inutile.

1. Commentez ou supprimez le service `mysql` et son volume associé dans `docker-compose.yml`.
2. Ajoutez le bloc de service `pgsql` en utilisant le stub standard de Sail :

```yaml
# docker-compose.yml
services:
    pgsql:
        image: 'postgres:15-alpine'
        ports:
            - '${FORWARD_DB_PORT:-5432}:5432'
        environment:
            POSTGRES_DB: '${DB_DATABASE}'
            POSTGRES_USER: '${DB_USERNAME}'
            POSTGRES_PASSWORD: '${DB_PASSWORD}'
        volumes:
            - 'sail-pgsql:/var/lib/postgresql/data'
        networks:
            - sail
        healthcheck:
            test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME} -d ${DB_DATABASE}"]
            retries: 3
            timeout: 5s
```

3. Mettez à jour le tableau `depends_on` de `laravel.test` pour pointer vers `pgsql`.
4. Mettez à jour le fichier **`.env`** :

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

Reconstruisez et démarrez les conteneurs à l'aide de `sail build --no-cache && sail up -d`.

---

<a id="mongodb"></a>
## MongoDB + extension PHP dans Sail

MongoDB n'est pas pris en charge nativement par les runtimes par défaut de Sail et nécessite l'installation de l'extension PHP MongoDB à l'intérieur du conteneur de l'application.

1. Ajoutez le service MongoDB à votre fichier Compose :

```yaml
# docker-compose.yml
services:
    mongo:
        image: 'mongo:7'
        ports:
            - '${FORWARD_MONGO_PORT:-27017}:27017'
        volumes:
            - 'sail-mongo:/data/db'
        networks:
            - sail
```

2. Enregistrez le volume `sail-mongo` sous `volumes:`.
3. Publiez les Dockerfiles de Sail si ce n'est pas déjà fait :
   ```bash
   php artisan sail:publish
   ```
4. Modifiez le Dockerfile du runtime (par exemple, `docker/8.3/Dockerfile`) pour installer l'extension PECL :

```dockerfile
# docker/8.3/Dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

5. Reconstruisez vos images Sail et démarrez :
   ```bash
   sail build --no-cache
   sail up -d
   ```

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ comme conteneur

Pour les projets nécessitant des fonctionnalités AMQP avancées au lieu de Redis ou de files d'attente basées sur une base de données, exécutez RabbitMQ en tant que conteneur sidecar.

```yaml
# docker-compose.yml
services:
    rabbitmq:
        image: 'rabbitmq:3-management-alpine'
        hostname: rabbitmq
        ports:
            - '${FORWARD_RABBITMQ_PORT:-5672}:5672'
            - '${FORWARD_RABBITMQ_MANAGEMENT:-15672}:15672'
        volumes:
            - 'sail-rabbitmq:/var/lib/rabbitmq'
        networks:
            - sail
        environment:
            RABBITMQ_DEFAULT_USER: '${RABBITMQ_USER:-sail}'
            RABBITMQ_DEFAULT_PASS: '${RABBITMQ_PASSWORD:-password}'
```

> [!NOTE]
> Veillez à déclarer `sail-rabbitmq` sous votre liste principale `volumes:`. Accédez localement au tableau de bord de gestion à l'adresse `http://localhost:15672` en utilisant vos identifiants personnalisés (par exemple, `sail` / `password`).

---

<a id="mailpit"></a>
## Mailpit (développement SMTP)

Sail inclut Mailpit pour intercepter les e-mails sortants. Cela évite que de vrais messages soient envoyés accidentellement à des utilisateurs lors de tests locaux.

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

Tous les e-mails envoyés par l'application peuvent être consultés via l'interface web à l'adresse `http://localhost:8025`.

---

<a id="search-engines"></a>
## Meilisearch / Typesense (recherche optionnelle)

Pour activer une recherche locale ultra-rapide avec Laravel Scout :

```bash
php artisan sail:install --with=meilisearch
```

Ou copiez le stub du service Meilisearch dans votre configuration Compose. Pensez à faire pointer vos variables d'environnement vers le nom du service :

```dotenv
# .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
```

---

<a id="healthchecks"></a>
## Healthchecks et ordre de démarrage

Par défaut, Docker Compose démarre les conteneurs en parallèle. Un `depends_on: [mysql]` standard garantit uniquement que le conteneur MySQL *démarre*, et non que le moteur de base de données est *totalement prêt* à recevoir des connexions.

Implémenter un `healthcheck` sur le service de base de données et définir `condition: service_healthy` sur le service d'application garantit que les migrations s'exécutent proprement pendant l'initialisation.

---

<a id="volumes"></a>
## Volumes nommés et réinitialisation des données

Les volumes nommés agissent comme des disques virtuels persistants.
- Exécuter `sail down` arrête les conteneurs mais laisse vos volumes de base de données intacts.
- Exécuter `sail down -v` détruit **tous** les volumes, réinitialisant complètement votre environnement local.
- Pour réinitialiser une seule base de données : localisez le volume cible avec `docker volume ls` et supprimez-le à l'aide de `docker volume rm <volume_name>`.

---

## ⚠️ Erreurs courantes

**1. Utilisation de `127.0.0.1` ou `localhost` dans le fichier `.env` pour les connexions aux conteneurs**
Définir `DB_HOST=127.0.0.1` oblige Laravel à chercher la base de données à l'intérieur du conteneur PHP lui-même, ce qui entraîne un échec de connexion.
*Solution :* Utilisez `DB_HOST=mysql` ou `DB_HOST=pgsql`.

**2. Réinitialisation destructive des volumes avec `sail down -v`**
Exécuter `sail down -v` alors que vous souhaitez simplement arrêter vos conteneurs effacera vos bases de données, vos enregistrements de seeders et vos clés Redis.
*Solution :* Utilisez `sail down` pour arrêter les conteneurs en toute sécurité. Utilisez uniquement `-v` si vous avez l'intention d'effacer complètement l'état.

**3. Modification des Dockerfiles personnalisés ohne réassemblage de l'image**
Modifier les Dockerfiles de runtime publiés pour installer des extensions PHP (comme `mongodb` ou `gd`) n'a aucun effet tant que vous ne forcez pas une reconstruction.
*Solution :* Lancez toujours `sail build --no-cache` après avoir modifié les Dockerfiles.

---

## 🧠 Questions d'auto-évaluation

1. **Pourquoi la configuration `DB_HOST=127.0.0.1` échoue-t-elle lors de l'exécution des migrations Laravel dans Sail ?**
2. **En quoi `depends_on` avec `condition: service_healthy` diffère-t-il d'un simple tableau `depends_on` ?**
3. **Qu'advient-il de vos données PostgreSQL locales si vous exécutez `sail down -v` ?**
4. **Pourquoi devez-vous publier les Dockerfiles de Sail (via `sail:publish`) avant de pouvoir utiliser l'extension MongoDB ?**

<details>
<summary><b>Afficher les réponses</b></summary>

1. Dans Docker Compose, chaque conteneur possède sa propre interface réseau de boucle locale (loopback). `127.0.0.1` fait référence au conteneur `laravel.test` lui-même, qui n'exécute pas la base de données. Vous devez utiliser le nom de service du conteneur de base de données comme nom d'hôte.
2. Un simple `depends_on` vérifie uniquement si le conteneur a démarré (état `running`). Le modificateur `condition: service_healthy` empêche le conteneur dépendant de démarrer tant que la base de données n'a pas réussi son test d'aptitude interne (par exemple, être prête à accepter des connexions socket).
3. Toutes les données PostgreSQL locales seront définitivement détruites car le volume stockant `/var/lib/postgresql/data` est supprimé.
4. Sail utilise des images Docker génériques préconfigurées. Pour installer des extensions PECL personnalisées, vous devez accéder et modifier la configuration du Dockerfile sous-jacent et compiler une image locale.
</details>
