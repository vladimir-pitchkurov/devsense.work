---
title: "Laravel Sail : configuration .env, redirections de ports, CI et local vs production | DevSense"
description: "Séparez la configuration de Laravel Sail et de l'hôte : .env.example, ports FORWARD_*, APP_URL dans Docker, env_file optionnel, GitHub Actions avec Docker Compose, et listes de contrôle lorsque Sail n'est pas votre serveur de production."
faq:
  - question: "Pourquoi ai-je des erreurs 'port already allocated' lors de l'exécution de sail up ?"
    answer: "Cela se produit lorsqu'un autre service sur votre machine hôte (comme un MySQL local ou une autre instance de Sail) utilise déjà le port. Vous pouvez résoudre ce problème en modifiant FORWARD_DB_PORT ou APP_PORT dans votre fichier .env."
  - question: "Dois-je commiter mon fichier .env sur Git ?"
    answer: "Non, vous ne devez jamais commiter le fichier .env. Il contient des secrets et des paramètres spécifiques au développeur. À la place, conservez et commitez des valeurs par défaut sécurisées dans .env.example."
  - question: "Comment charger des paramètres de conteneur spécifiques à un développeur sans modifier la configuration partagée ?"
    answer: "Vous pouvez spécifier une liste 'env_file' sous votre service dans docker-compose.yml pour charger un fichier local secondaire (par exemple, .env.docker.local) contenant des surcharges locales."
  - question: "Puis-je exécuter Laravel Sail sur un serveur de production ?"
    answer: "Non. Sail est conçu pour l'ergonomie des développeurs et ne dispose pas de sécurité de niveau production, de terminaison TLS, de systèmes de sauvegarde ou d'optimisations de mise à l'échelle. Déployez en utilisant des configurations dédiées (par exemple, Kubernetes, Ansible ou Laravel Forge)."
---

# Sail : environnements & déploiement

Vous commitez un fichier `.env` local contenant des mots de passe de base de données sur GitHub, et vos scanners de sécurité déclenchent immédiatement une alerte. Ou bien un coéquipier clone votre dépôt, lance `sail up` et voit le processus planter parce que son port MySQL local `3306` est déjà occupé par un autre projet. La gestion de l'environnement ne consiste pas seulement à définir des paires clé-valeur ; il s'agit de tracer une frontière stricte entre l'ergonomie locale, les tests CI/CD et les déploiements de production sécurisés.

Le développement conteneurisé modifie le comportement des variables d'environnement. Comme PHP s'exécute à l'intérieur de `laravel.test` tandis que les bases de données se lient aux ports de l'hôte, les chemins, les URL et les mappages de ports doivent s'ajuster selon qu'ils sont accédés depuis la machine hôte ou depuis le réseau Docker Compose.

Dans ce guide, nous vous montrons comment structurer les fichiers `.env`, éviter les collisions de ports d'hôte, configurer des pipelines de CI basés sur Docker, et auditer votre configuration avant de passer en production.

**Navigation :** [Tous les outils](../) · [Aperçu de Sail](sail#what-sail-is) · [Bases de données](sail-databases#networking) · [Files d'attente](sail-queues#connections) · [Dépannage](sail-troubleshooting#wsl-filesync)

## Table des matières

* [`.env`, `.env.example` et secrets](#env-files)
* [Ports `FORWARD_*` et collisions](#forward-ports)
* [`APP_URL` et proxys de confiance](#app-url)
* [`env_file` optionnel dans Compose](#compose-env-file)
* [CI : pattern GitHub Actions](#ci-example)
* [Sail vs serveurs de dev/staging/prod](#not-production)
* [Checklist avant la mise en ligne](#checklist)
* [Erreurs courantes](#common-mistakes)
* [Questions d'auto-évaluation](#self-check)

---

<a id="env-files"></a>
## `.env`, `.env.example` et secrets

- **`.env`** : Stocke les secrets locaux et les détails de l'environnement. **Ne commitez jamais ce fichier dans le contrôle de version.**
- **`.env.example`** : Commité dans Git. Les clés par défaut doivent représenter des valeurs locales sécurisées afin qu'un nouveau développeur puisse copier le fichier sous le nom de `.env` et lancer immédiatement `sail up`.

```dotenv
# .env.example
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306

# Surcharges spécifiques à Sail
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
```

---

<a id="forward-ports"></a>
## Ports `FORWARD_*` et collisions

Par défaut, Sail lie les ports de la base de données et de l'application à localhost. Si vous exécutez plusieurs projets, des ports comme `3306` (MySQL) ou `80` (HTTP) entreront en collision.

Pour corriger cela, modifiez les ports redirigés dans votre `.env` :

```dotenv
# .env
APP_PORT=8080
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
```

> [!NOTE]
> **Ports internes vs externes**
> Modifier `FORWARD_DB_PORT` change le port exposé sur votre machine hôte. À l'intérieur du réseau Docker, MySQL communique toujours sur le port par défaut `3306`. Votre configuration Laravel n'a pas besoin de changer sa variable `DB_PORT`.

---

<a id="app-url"></a>
## `APP_URL` et proxys de confiance

Laravel utilise `APP_URL` pour générer des URL signées et des redirections de routes.

```dotenv
# .env
APP_URL=http://localhost:8080
```

Assurez-vous que cela correspond au port mappé sur votre hôte. Si vous placez un répartiteur de charge (load balancer) ou un reverse proxy (comme Nginx ou Traefik) devant votre application de production, configurez `TrustProxies` dans Laravel pour lire correctement l'IP d'origine et le protocole (HTTPS) du client.

---

<a id="compose-env-file"></a>
## `env_file` optionnel dans Compose

Si vous avez des variables d'environnement qui ne s'appliquent qu'à l'environnement Docker, chargez-les dans `docker-compose.yml` :

```yaml
# docker-compose.yml
services:
    laravel.test:
        env_file:
            - .env
            - .env.docker.local
```

Cela évite d'encombrer le fichier `.env` partagé avec des variables d'environnement qui n'ont d'importance qu'à l'intérieur des conteneurs.

---

<a id="ci-example"></a>
## CI : pattern GitHub Actions

Vous pouvez utiliser les services Docker Compose de Sail à l'intérieur de votre pipeline CI/CD pour exécuter des tests automatisés dans un environnement propre.

```yaml
# .github/workflows/tests.yml
name: Run Tests
on: [push]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Copy CI Env
        run: cp .env.ci .env
      - name: Start Sail
        run: docker compose up -d
      - name: Run Pest/PHPUnit
        run: docker compose exec -T laravel.test php artisan test
```

---

<a id="not-production"></a>
## Sail vs serveurs de dev/staging/prod

- **Sail** est optimisé für la vitesse de développement local (inclut des outils comme Mailpit, Meilisearch et des environnements d'observation de fichiers).
- **Les environnements de staging/production** nécessitent une sécurisation renforcée, une agrégation des journaux, des routines de sauvegarde automatisées, des certificats SSL/TLS et des serveurs de bases de données distincts (par exemple, AWS RDS).

> [!NOTE]
> N'essayez pas d'utiliser `sail up` sur un serveur cloud public. Cela expose les services de développement et utilise des configurations stubs qui sont très peu sécurisées.

---

<a id="checklist"></a>
## Checklist avant la mise en ligne

- [ ] `APP_ENV=production` et `APP_DEBUG=false` dans l'environnement de production.
- [ ] Générez une clé `APP_KEY` sécurisée à l'aide de `php artisan key:generate`.
- [ ] Connectez-vous à des instances de base de données gérées, et non à des bases de données locales conteneurisées.
- [ ] Définissez `QUEUE_CONNECTION=redis` ou `sqs` avec des systèmes de workers supervisés.
- [ ] Activez la terminaison TLS/HTTPS.
- [ ] Configurez des gestionnaires de logs centralisés (par exemple, Bugsnag, Sentry ou AWS CloudWatch).

---

## ⚠️ Erreurs courantes

**1. Commiter le fichier `.env` sur Git**
Exposer des clés d'API, des identifiants de base de données ou des mots de passe d'application dans l'historique du dépôt.
*Solution :* Vérifiez que `.env` est bien listé dans votre fichier `.gitignore` avant de commiter.

**2. Modifier `DB_PORT` au lieu de `FORWARD_DB_PORT` pour résoudre les conflits locaux**
Modifier `DB_PORT=3307` dans votre fichier `.env` sans modifier la configuration Compose de Sail rompra la connexion interne car la base de données du conteneur écoute toujours sur le port `3306`.
*Solution :* Conservez `DB_PORT=3306` (pour la connexion interne au conteneur) et définissez `FORWARD_DB_PORT=3307` (pour modifier le port de la machine hôte).

**3. Déploiement direct du fichier `docker-compose.yml` de Sail en production**
Lancer des bases de données à côté du conteneur de l'application sans réplication persistante, sauvegardes ou politiques d'accès sécurisées.
*Solution :* Le fichier Compose de Sail est un utilitaire de développement, pas un manifeste de déploiement.

---

## 🧠 Questions d'auto-évaluation

1. **Pourquoi le fait de changer `DB_PORT=3307` dans le fichier `.env` casse-t-il les migrations dans Sail, alors que changer `FORWARD_DB_PORT=3307` ne le fait pas ?**
2. **Quel est le risque de sécurité lié au déploiement direct du fichier Compose de Sail sur un serveur de production public ?**
3. **Comment le bloc `env_file` dans `docker-compose.yml` aide-t-il à structurer les surcharges d'environnement ?**
4. **Quel fichier devez-vous mettre à jour pour vous assurer que vos coéquipiers disposent de la liste de toutes les clés d'API requises pour une nouvelle fonctionnalité ?**

<details>
<summary><b>Afficher les réponses</b></summary>

1. Modifier `DB_PORT` indique à Laravel de rechercher la base de données sur le port `3307` au sein du réseau de conteneurs, où le conteneur de la base de données écoute toujours sur `3306`. Modifier `FORWARD_DB_PORT` ne change que le port externe exposé à votre système hôte, laissant le réseau interne du conteneur intact.
2. Il expose des outils réservés au développement (comme Mailpit et Meilisearch) à l'internet public, exécute des conteneurs avec des privilèges de développeur et expose des bases de données sans réplication ni sauvegardes de production.
3. Il vous permet de diviser vos variables sur plusieurs fichiers (comme `.env` et `.env.docker.local`), chargeant des surcharges spécifiques à Docker sans encombrer le fichier de configuration principal.
4. Vous devez mettre à jour `.env.example` avec des clés vides ou des valeurs fictives sécurisées et documenter leur utilisation.
</details>
