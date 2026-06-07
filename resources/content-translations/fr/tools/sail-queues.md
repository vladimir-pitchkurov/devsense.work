---
title: "Laravel Sail : workers de file d'attente, Horizon, Redis, RabbitMQ & jobs échoués | DevSense"
description: "Exécuter les files d'attente Laravel dans Sail : sync vs redis vs database, queue:work et Horizon localement, RabbitMQ avec les pilotes de la communauté, failed_jobs, queue:restart et différences en production."
faq:
  - question: "Pourquoi mes tâches d'arrière-plan ne s'exécutent-elles pas dans Sail ?"
    answer: "Contrairement au pilote 'sync', les pilotes comme 'database' ou 'redis' nécessitent un processus worker en cours d'exécution. Vous devez démarrer un conteneur worker ou lancer 'sail artisan queue:work' dans votre terminal."
  - question: "Pourquoi mes modifications dans les classes Job ne sont-elles pas prises en compte dans la file d'attente ?"
    answer: "Les workers de file d'attente de Laravel chargent le code de l'application en mémoire une seule fois. Lorsque vous modifiez le code, vous devez exécuter 'sail artisan queue:restart' pour forcer les workers à se recharger."
  - question: "Comment exécuter le planificateur dans Laravel Sail sans tâche cron sur l'hôte ?"
    answer: "Vous pouvez exécuter 'sail run --rm laravel.test php artisan schedule:work' dans une fenêtre de terminal séparée, ce qui interroge et exécute les tâches chaque minute localement."
  - question: "En quoi la gestion des files d'attente dans Sail diffère-t-elle de la production ?"
    answer: "Dans Sail, les workers s'exécutent au premier plan de votre terminal et s'arrêtent lorsque vous le fermez. En production, des gestionnaires de processus comme Supervisor ou Kubernetes maintiennent les workers actifs de manière persistante."
---

# Sail : files d'attente & workers

Vous déclenchez une tâche en arrière-plan dans votre application Laravel, mais rien ne se passe. La page du job indique « En attente » (Pending) et vos enregistrements en base de données restent inchangés. C'est alors que vous réalisez : dans un environnement conteneurisé, les tâches d'arrière-plan ne s'exécutent pas d'elles-mêmes. Sans un processus worker actif à l'écoute dans le réseau de conteneurs de Sail, vos files d'attente ne sont que des fichiers morts ou des listes Redis silencieuses.

Exécuter des workers de file d'attente et des planificateurs de tâches (schedulers) sur votre OS hôte échouera car ils n'ont pas accès aux bases de données conteneurisées, aux variables d'environnement et aux extensions PHP de Sail. Pour reproduire le comportement de la production, vous devez exécuter et gérer vos runtimes de workers à l'intérieur du réseau Compose.

Dans ce guide, nous vous montrons comment configurer les connexions de file d'attente, exécuter des workers et Horizon, gérer les échecs de tâches et appliquer les mises à jour de code sans conserver de caches de workers obsolètes.

**Navigation :** [Tous les outils](../) · [Aperçu de Sail](sail#what-sail-is) · [Bases de données](sail-databases#networking) · [Env & déploiement](sail-env-deploy#forward-ports) · [Dépannage](sail-troubleshooting#wsl-filesync)

## Table des matières

* [Aperçu des connexions](#connections)
* [Exécuter `queue:work` dans Sail](#queue-work)
* [Horizon (Redis)](#horizon)
* [RabbitMQ et packages AMQP](#rabbitmq)
* [Jobs échoués et tentatives](#failed-jobs)
* [Modifications de code et `queue:restart`](#restart)
* [Planificateur (`schedule:run`)](#scheduler)
* [Différences avec la production](#production)
* [Erreurs courantes](#common-mistakes)
* [Questions d'auto-évaluation](#self-check)

---

<a id="connections"></a>
## Aperçu des connexions

Sélectionnez votre pilote de file d'attente dans le fichier `.env` :

```dotenv
# .env
QUEUE_CONNECTION=redis
```

| Pilote | Cas d'utilisation dans Sail |
|--------|-------------------|
| **`sync`** | Déboguer la logique des jobs de manière synchrone sans démarrer de worker. |
| **`database`** | File d'attente asynchrone simple ; nécessite l'exécution de `php artisan queue:table` et un worker. |
| **`redis`** | Pilote standard de production ; s'intègre avec Horizon ; nécessite un service Redis. |
| **`rabbitmq`** | Échanges AMQP ; nécessite un conteneur sidecar RabbitMQ et un package de la communauté. |

> [!NOTE]
> **Mise en cache de la configuration**
> Si vous modifiez `QUEUE_CONNECTION` ou toute autre variable d'environnement de file d'attente, exécutez `sail artisan config:clear` ou `sail artisan config:cache` pour appliquer les modifications à vos conteneurs en cours d'exécution.

---

<a id="queue-work"></a>
## Exécuter `queue:work` dans Sail

Pour traiter les tâches, ouvrez un terminal dédié et exécutez :

```bash
# Terminal
sail artisan queue:work
```

Pour des contrôles avancés, ciblez des connexions et des options spécifiques :

```bash
# Terminal
sail artisan queue:work redis --queue=high,default --tries=3 --timeout=90
```

Pour exécuter une seule tâche puis quitter (utile pour le débogage) :

```bash
# Terminal
sail artisan queue:work --once
```

---

<a id="horizon"></a>
## Horizon (Redis)

Laravel Horizon fournit un magnifique tableau de bord et une configuration basée sur le code pour vos files d'attente Redis.

1. Installez Horizon via Composer :
   ```bash
   sail composer require laravel/horizon
   sail artisan horizon:install
   ```
2. Démarrez Horizon dans votre conteneur :

```bash
# Terminal
sail artisan horizon
```

Accédez au tableau de bord à l'adresse `http://localhost/horizon`. En développement, Horizon s'arrête lorsque vous appuyez sur `Ctrl+C`.

---

<a id="rabbitmq"></a>
## RabbitMQ et packages AMQP

Laravel n'inclut pas de pilote RabbitMQ officiel. Pour utiliser AMQP :

1. Ajoutez le service RabbitMQ à votre fichier `docker-compose.yml` ([voir les recettes de base de données](sail-databases#rabbitmq-sidecar)).
2. Installez un package maintenu par la communauté (par exemple, `vyuldashev/laravel-queue-rabbitmq`).
3. Définissez les détails de votre connexion :

```dotenv
# .env
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
```

4. Démarrez le traitement :
   ```bash
   sail artisan queue:work rabbitmq
   ```

---

<a id="failed-jobs"></a>
## Jobs échoués et tentatives

Lorsqu'une tâche en arrière-plan lève eine exception, Laravel l'enregistre dans la table `failed_jobs`.

- Lister les jobs échoués :
  ```bash
  sail artisan queue:failed
  ```
- Recommencer un job spécifique :
  ```bash
  sail artisan queue:retry 5
  ```
- Supprimer tous les jobs échoués :
  ```bash
  sail artisan queue:flush
  ```

---

<a id="restart"></a>
## Modifications de code et `queue:restart`

Les workers Laravel démarrent l'application une fois et traitent les jobs en boucle continue. Si vous modifiez un fichier Job, le worker actif continuera d'exécuter l'ancien code stocké en mémoire.

Chaque fois que vous sauvegardez des modifications dans votre codebase, redémarrez les workers :

```bash
# Terminal
sail artisan queue:restart
```

> [!NOTE]
> **Rechargement automatique en développement**
> Pour éviter de taper constamment `queue:restart` lors du développement local rapide, vous pouvez exécuter le worker avec les drapeaux `--max-jobs=1` ou `--once`.

---

<a id="scheduler"></a>
## Planificateur (`schedule:run`)

Sail ne lance pas automatiquement de démon cron système. Pour exécuter des tâches planifiées en développement, vous avez deux options :

1. Exécuter le planificateur manuellement :
   ```bash
   sail artisan schedule:run
   ```
2. Exécuter une boucle de vérification continue en arrière-plan :

```bash
# Terminal
sail run --rm laravel.test php artisan schedule:work
```

---

<a id="production"></a>
## Différences avec la production

| Sujet | Sail (Développement Local) | Environnement de Production |
|-------|------------------|------------------------|
| **Gestionnaire de Worker** | Manuel au premier plan du terminal | Daemon **Supervisor** ou systemd |
| **Mise à l'échelle** | Un seul conteneur Docker | Conteneurs multiples / Autoscaling Kubernetes |
| **Tolérance aux pannes** | Redémarrage manuel du conteneur | Nœuds redondants et clustering |

---

## ⚠️ Erreurs courantes

**1. Modifier le code d'un Job sans redémarrer le worker**
Vous corrigez un bug dans votre classe de Job, mais le gestionnaire de file d'attente continue de renvoyer la même erreur.
*Solution :* Le worker a mis en cache les anciens fichiers PHP. Exécutez `sail artisan queue:restart`.

**2. Exécuter `php artisan queue:work` sur votre terminal hôte**
Lancer la commande directement sur votre machine hôte provoquera un plantage en raison de pilotes de base de données manquants ou de l'incapacité à résoudre les noms d'hôte internes comme `redis` ou `mysql`.
*Solution :* Ajoutez toujours le préfixe `sail` pour exécuter la commande dans l'environnement du conteneur : `sail artisan queue:work`.

**3. Mal comprendre le pilote de file d'attente `sync`**
Utiliser `QUEUE_CONNECTION=sync` exécute les jobs immédiatement dans le cycle de vie de la requête HTTP. Cela masque les problèmes de concurrence, les timeouts et les erreurs de sérialisation qui n'apparaîtront qu'en production.
*Solution :* Utilisez `database` ou `redis` localement pour correspondre au comportement de la production.

---

## 🧠 Questions d'auto-évaluation

1. **Pourquoi un worker de file d'attente actif ne parvient-il pas à exécuter les nouvelles modifications de code que Sie venez de sauvegarder sur le disque ?**
2. **Quel nom d'hôte devez-vous écrire dans votre fichier `.env` pour RabbitMQ lorsque vous l'exécutez dans Sail ?**
3. **Comment faire fonctionner le planificateur de tâches de Laravel en continu sans configurer de tâches cron côté hôte ?**
4. **Quelle commande artisan exécutez-vous pour remettre en file d'attente un job qui a échoué pendant son exécution ?**

<details>
<summary><b>Afficher les réponses</b></summary>

1. Les workers PHP CLI démarrent l'ensemble du framework de l'application une seule fois et le maintiennent en mémoire. Ils ne relisent pas les fichiers sur le disque pour les jobs suivants. Vous devez exécuter `sail artisan queue:restart`.
2. Vous devez utiliser le nom du service Compose, qui est `rabbitmq`.
3. Exécutez `sail run --rm laravel.test php artisan schedule:work`, ce qui démarre un processus de longue durée qui exécute le planificateur chaque minute.
4. Lancez `sail artisan queue:retry {id}` où `{id}` est l'identifiant de la table `failed_jobs`, ou `all` pour retenter tous les jobs échoués.
</details>
