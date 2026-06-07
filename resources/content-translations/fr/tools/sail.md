---
title: "Laravel Sail : stack locale Docker, versions PHP, Redis, Postgres, files d'attente & notes de déploiement | DevSense"
description: "Guide pratique de Laravel Sail : changer la version de PHP, ajouter Redis ou RabbitMQ, passer de MySQL à PostgreSQL, notes sur MongoDB, workers de file d'attente dans des conteneurs, séparation des environnements, et différences entre Sail et les serveurs de dev/staging/production."
faq:
  - question: "Qu'est-ce que Laravel Sail ?"
    answer: "Laravel Sail est une interface en ligne de commande légère pour interagir avec l'environnement de développement Docker par défaut de Laravel, basé sur Docker Compose."
  - question: "Comment changer la version de PHP dans Laravel Sail ?"
    answer: "Modifiez l'argument de build PHP_VERSION dans votre fichier docker-compose.yml sous le service laravel.test, puis recompilez à l'aide de sail build --no-cache."
  - question: "Pourquoi ai-je des erreurs de connexion refusée (connection refused) vers Redis ou MySQL ?"
    answer: "Parce que vous essayez de vous connecter via 127.0.0.1. Dans le réseau de conteneurs de Sail, vous devez utiliser le nom du service (par exemple, mysql ou redis) comme hôte."
  - question: "Puis-je exécuter Laravel Sail en production ?"
    answer: "Non, Laravel Sail est strictement conçu pour le développement local. Pour la production, vous devez créer des images Docker optimisées et les déployer en utilisant une orchestration appropriée telle que Kubernetes, ECS ou Docker Swarm."
---

# Laravel Sail : Le guide ultime de la stack locale

Laravel Sail est un excellent wrapper pour **Docker Compose** conçu pour mettre en place un environnement de développement complet (PHP-FPM, MySQL, Redis, Meilisearch, Mailpit, etc.) sans avoir à gérer les configurations de serveurs sur votre machine hôte.

Cependant, Sail n'est **pas** un environnement de production. Comprendre la frontière entre le Sail local et un déploiement en production réel est essentiel pour éviter le syndrome redouté du \"ça marche sur ma machine\". Maîtrisons Sail ensemble !

---

## Table des matières
* [Modèle mental & prérequis](#mental-model)
* [Raccourcis CLI indispensables](#shortcuts)
* [Changer de version PHP](#php-version)
* [Personnalisation des services (Redis, RabbitMQ, PostgreSQL)](#customizing-services)
* [Débogage avec Mailpit & Xdebug](#debugging)
* [Optimisation des performances de WSL2](#performance)
* [Comparatif : Sail (Local) vs Production](#local-vs-deploy)
* [🧠 Questions d'auto-évaluation](#self-check)

---

<a id="mental-model"></a>
## Modèle mental & prérequis

Sail n'installe pas PHP ni Node sur votre machine hôte. À la place, il exécute les commandes **à l'intérieur de runtimes conteneurisés**.

Si vous êtes sur Windows, vous **DEVEZ** exécuter Docker dans **WSL2** (Windows Subsystem for Linux) et conserver votre dossier de projet dans le système de fichiers Linux (par exemple, `~/Development/my-app`), et non sur la partition Windows `C:\`. Travailler à travers la frontière de montage Windows-Linux provoque d'extrêmes goulots d'étranglement au niveau des E/S disque.

---

<a id="shortcuts"></a>
## Raccourcis CLI indispensables

Fatigué de taper `./vendor/bin/sail` à chaque fois ? Ajoutez un alias à votre shell !

```bash
# ~/.zshrc ou ~/.bashrc
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Vous pouvez maintenant exécuter vos commandes quotidiennes efficacement :
- `sail up -d` — Démarre tous les services en arrière-plan.
- `sail down` — Arrête les services.
- `sail artisan migrate` — Exécute les migrations de base de données.
- `sail npm run dev` — Exécute les assets Vite localement.

---

<a id="php-version"></a>
## Changer de version PHP

Pour mettre à niveau ou rétrograder votre version de PHP dans Sail, vous devez modifier votre configuration Compose.

```yaml
# docker-compose.yml
services:
    laravel.test:
        build:
            context: ./vendor/laravel/sail/runtimes/8.4
            dockerfile: Dockerfile
            args:
                WWWGROUP: '${WWWGROUP}'
                # Modifiez cet argument de build pour la version mineure souhaitée :
                PHP_VERSION: '8.4'
```

Après avoir modifié le fichier, recompilez les couches de conteneurs sans utiliser le cache :

```bash
# Terminal
sail build --no-cache
sail up -d
```

---

<a id="customizing-services"></a>
## Personnalisation des services

### 1. Connexion à Redis
Si vous avez ignoré Redis lors de l'installation, vous pouvez l'ajouter en modifiant votre configuration Compose :

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
```

Assurez-vous que votre fichier d'environnement correspond à ceci :

```dotenv
# .env
REDIS_HOST=redis
REDIS_PORT=6379
```

> [!NOTE]
> **Le saviez-vous ?**
> À l'intérieur du réseau Sail, les conteneurs communiquent entre eux en utilisant leurs noms de service (comme `redis` ou `mysql`) en tant que noms d'hôte. Utiliser `127.0.0.1` échouera car cela pointe vers le conteneur de l'application lui-même !

### 2. Passer de MySQL à PostgreSQL
Pour changer de moteur de base de données, remplacez le bloc `mysql` par `pgsql` dans votre fichier `docker-compose.yml` :

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
```

Et mettez à jour votre fichier `.env` :

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
```

---

<a id="debugging"></a>
## Débogage avec Mailpit & Xdebug

### Mailpit (Faux serveur de messagerie)
Sail redirige automatiquement les e-mails sortants vers Mailpit. Il intercepte les e-mails afin que vous n'envoyiez pas accidentellement des messages de test à de vrais clients. Configurez SMTP dans votre `.env` :

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```
Vous pouvez consulter les e-mails interceptés en ouvrant l'interface web à l'adresse `http://localhost:8025`.

### Xdebug
Pour activer le débogage pas à pas, définissez simplement le mode Xdebug dans votre environnement :

```dotenv
# .env
SAIL_XDEBUG_MODE=develop,debug
```
Redémarrez Sail (`sail down && sail up -d`) pour appliquer la configuration.

---

<a id="local-vs-deploy"></a>
## Comparatif : Sail (Local) vs Production

| Fonctionnalité | Laravel Sail (Dev Local) | Environnement de Production |
|----------------|--------------------------|-----------------------------|
| **Serveur HTTP** | Serveur intégré de PHP | Nginx / Apache + PHP-FPM, ou Octane |
| **Files d'attente** | Exécution via le terminal `sail artisan queue:work` | Géré via Supervisor ou Systemd |
| **SSL / HTTPS** | HTTP simple sur localhost | TLS terminé sur Nginx, Cloudflare ou Load Balancer |
| **Cache & Sessions** | Stockage fichier ou conteneur Redis local | Redis clusterisé ou Memcached managé |
| **Base de données** | Conteneur éphémère | Base managée (RDS, Cloud SQL) avec sauvegardes |

---

## ⚠️ Erreurs courantes

**1. Connexion à `127.0.0.1` pour Redis ou la base de données dans Sail**
Si votre variable `DB_HOST` est définie sur `127.0.0.1` dans `.env`, votre application ne pourra pas trouver le conteneur de la base de données. Utilisez plutôt le nom du service (`mysql` ou `pgsql`).

**2. Exécuter des commandes sur l'hôte au lieu du conteneur**
Exécuter `composer install` ou `php artisan migrate` sur votre terminal local peut entraîner des incompatibilités de version ou des erreurs d'extensions PHP manquantes. Exécutez-les toujours via Sail :
```bash
# Terminal
sail composer install
sail artisan migrate
```

---

<a id="self-check"></a>
## 🧠 Questions d'auto-évaluation

1. **Pourquoi est-il important d'utiliser les chemins du système de fichiers de WSL2 (comme `~/Development`) au lieu de monter directement depuis les partitions Windows (`/mnt/c/...`) ?**
2. **Vrai ou Faux ?** Pour activer PostgreSQL, vous devez télécharger et compiler manuellement le pilote `pdo_pgsql` à l'intérieur du conteneur Sail.
3. **Quel nom d'hôte devez-vous utiliser dans le fichier `.env` pour envoyer des e-mails via Mailpit dans Sail ?**
4. **Comment redémarrer un worker de file d'attente dans Sail afin qu'il charge les modifications de code mises à jour ?**

<details>
<summary><b>Afficher les réponses</b></summary>

1. Le montage depuis des disques Windows (`C:\`) sur un conteneur Docker Linux introduit d'importantes couches de traduction et de virtualisation, ralentissant les opérations sur les fichiers et la compilation des assets (Vite/Mix) jusqu'à 10 fois.
2. **Faux.** Les runtimes PHP préconstruits de Sail intègrent déjà les extensions standard, y compris `pdo_pgsql`. Il vous suffit de changer le service dans `docker-compose.yml` et `.env`.
3. Vous devez utiliser `mailpit` comme hôte.
4. Exécutez `sail artisan queue:restart`, ou lancez le worker avec `--max-jobs=1` pendant le développement.
</details>
