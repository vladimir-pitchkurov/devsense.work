---
title: "Laravel Sail : dépannage de WSL2, permissions, ports, rebuilds, OPcache & Vite | DevSense"
description: "Résolvez les problèmes courants de Laravel Sail : synchronisation des fichiers WSL2, permissions UID/GID et stockage, conflits de ports FORWARD_*, couches Docker obsolètes, OPcache et Xdebug en développement, npm/Vite hôte vs conteneur, et réinitialisation sécurisée des volumes."
faq:
  - question: "Pourquoi la compilation des assets Vite est-elle extrêmement lente sur Windows avec WSL2 ?"
    answer: "Cela est généralement dû au fait que vos fichiers de projet sont conservés sur le système de fichiers Windows (/mnt/c/...). Pour corriger cela, déplacez le dossier de votre projet vers le système de fichiers natif de Linux (par exemple, ~/projects/) à l'intérieur de WSL2."
  - question: "Comment corriger les erreurs de permission refusée (permission denied) sur le répertoire storage ?"
    answer: "Exécutez 'sail artisan cache:clear' et configurez les variables d'environnement WWWUSER et WWWGROUP dans votre fichier .env pour qu'elles correspondent à l'ID utilisateur de votre machine hôte, afin que les fichiers générés par les outils CLI conteneurisés conservent les bons droits de propriété."
  - question: "Pourquoi mes modifications de code ne sont-elles pas mises à jour dans le conteneur en cours d'exécution ?"
    answer: "S'il s'agit d'une modification web, OPcache est peut-être activé dans la configuration PHP. S'il s'agit d'un worker de file d'attente, il a mis en cache le bootstrap. S'il s'agit d'une modification de configuration de Dockerfile, vous devez exécuter 'sail build --no-cache' et redémarrer les conteneurs."
  - question: "Comment puis-je vérifier quelles extensions sont chargées dans mon environnement PHP Sail ?"
    answer: "Exécutez la commande 'sail exec laravel.test php -m' pour lister tous les modules PHP actifs s'exécutant dans le conteneur, car cela différera de votre environnement hôte."
---

# Sail : dépannage & performance

Votre application fonctionnait parfaitement hier, mais aujourd'hui `sail up` génère un conflit d'allocation de ports, la compilation des assets via Vite échoue, et les écritures en base de données plantent avec des erreurs de permissions. Lorsque Sail échoue, c'est rarement un bug de Laravel ; c'est presque toujours un point de friction entre l'environnement de conteneur virtualisé et le système de fichiers, le réseau ou l'état des processus de votre machine hôte.

Les couches de virtualisation (en particulier WSL2 sur Windows) et les espaces de noms réseau des conteneurs introduisent des particularités uniques. Comprendre comment diagnostiquer et contourner ces obstacles est essentiel pour maintenir un flux de développement rapide et fluide.

Dans ce guide, nous rassemblons les symptômes les plus fréquents de Sail, expliquons pourquoi ils se produisent et fournissons des commandes directes pour les résoudre.

**Navigation :** [Tous les outils](../) · [Aperçu de Sail](sail#what-sail-is) · [Bases de données](sail-databases#networking) · [Files d'attente](sail-queues#connections) · [Env & déploiement](sail-env-deploy#forward-ports)

## Table des matières

* [WSL2, Docker Desktop et synchronisation de fichiers](#wsl-filesync)
* [Permissions, `storage` et `vendor`](#permissions)
* [Port déjà alloué / `FORWARD_*`](#ports)
* [Incohérences hôte vs conteneur](#host-vs-container)
* [Code obsolète : reconstructions et couches](#rebuild)
* [OPcache et autoload en développement local](#opcache)
* [Xdebug semble lent](#xdebug)
* [Vite, npm et Node à l'intérieur vs à l'extérieur de Sail](#frontend)
* [Journaux et diagnostics rapides](#logs)
* [Quand réinitialiser les volumes (perte de données)](#volumes)
* [Erreurs courantes](#common-mistakes)
* [Questions d'auto-évaluation](#self-check)

---

<a id="wsl-filesync"></a>
## WSL2, Docker Desktop et synchronisation de fichiers

Sur Windows + WSL2, monter des fichiers de la partition Windows (`/mnt/c/...`) dans des conteneurs Linux provoque d'énormes ralentissements d'E/S disque. Les observateurs de fichiers de remplacement de module à chaud (HMR) de Vite et webpack échoueront également à recevoir les notifications de modification.

Conservez vos dossiers de projet entièrement **à l'intérieur du système de fichiers natif de WSL** (par exemple, `~/projects/...`). Ouvrez-les dans VS Code en utilisant l'**extension WSL** pour vous assurer que les événements de synchronisation de fichiers et de compilation prennent des millisecondes au lieu de secondes.

---

<a id="permissions"></a>
## Permissions, `storage` et `vendor`

Laravel requiert des permissions d'écriture pour `storage/` et `bootstrap/cache/`. Si Docker s'exécute en tant qu'utilisateur root, les fichiers créés à l'intérieur des conteneurs deviendront en lecture seule sur votre machine hôte.

Vérifiez les permissions à l'intérieur du conteneur :

```bash
# Terminal
sail exec laravel.test ls -la storage bootstrap/cache
```

> [!NOTE]
> **Mappage UID et GID**
> Sur les hôtes Linux, faites correspondre les UID et GID de votre hôte en définissant les variables `WWWUSER` et `WWWGROUP` dans votre environnement :
> ```dotenv
> # .env
> WWWUSER=1000
> WWWGROUP=1000
> ```
> Redémarrez Sail après ces modifications.

---

<a id="ports"></a>
## Port déjà alloué / `FORWARD_*`

Si un autre service (comme une base de données locale ou une autre instance de Sail en cours d'exécution) utilise déjà des ports comme `3306` ou `80`, vous verrez une erreur `address already in use`.

Définissez des ports alternatifs dans votre fichier `.env` :

```dotenv
# .env
APP_PORT=8081
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
```

Exécutez `sail down && sail up -d` pour appliquer.

---

<a id="host-vs-container"></a>
## Incohérences hôte vs conteneur

Une erreur courante consiste à exécuter des commandes comme `php artisan migrate` ou `composer install` directement sur la machine hôte. Si votre hôte utilise une version différente de PHP ou ne dispose pas d'extensions (comme `redis` ou `mongodb`), la commande échouera ou corrompra votre fichier lock.

- **Hôte (Mauvais) :** `composer install`
- **Sail (Bon) :** `sail composer install`

---

<a id="rebuild"></a>
## Code obsolète : reconstructions et couches

Lorsque vous modifiez des dépendances, des runtimes PHP oder des packages personnalisés dans vos Dockerfiles publiés, Docker ne reconstruit pas vos images automatiquement.

Forcez une reconstruction complète de l'image sans utiliser les couches en cache :

```bash
# Terminal
sail build --no-cache
sail up -d
```

---

<a id="opcache"></a>
## OPcache et autoload en développement local

Si les modifications de code n'apparaissent pas dans votre navigateur, vérifiez si OPcache est actif dans le conteneur. En développement, OPcache doit valider les horodatages à chaque requête.

Assurez-vous que les éléments suivants sont configurés dans le fichier PHP.ini de votre conteneur :

```ini
# php.ini
opcache.validate_timestamps=1
opcache.revalidate_freq=0
```

Si vous ajoutez de nouvelles classes et obtenez des erreurs d'espace de noms, lancez `sail composer dump-autoload`.

---

<a id="xdebug"></a>
## Xdebug semble lent

Xdebug ajoute une surcharge d'exécution à chaque requête. Désactivez-le lorsque vous ne l'utilisez pas activement en configurant :

```dotenv
# .env
SAIL_XDEBUG_MODE=off
```

Redémarrez Sail avec `sail down && sail up -d`.

---

<a id="frontend"></a>
## Vite, npm et Node à l'intérieur vs à l'extérieur de Sail

Vous pouvez compiler les assets frontend soit sur la machine hôte, soit à l'intérieur de Sail.

- **Compilation sur l'hôte (Le plus rapide) :** Exécutez `npm run dev` dans votre terminal local.
- **Compilation dans le conteneur (Zéro configuration) :** Exécutez `sail npm run dev`.

Assurez-vous que votre `APP_URL` et vos ports Vite correspondent, sinon vous rencontrerez des échecs de connexion HMR dans votre console de développement.

---

<a id="logs"></a>
## Journaux et diagnostics rapides

Exécutez ces commandes pour inspecter la santé des conteneurs et les états de PHP :

```bash
# Terminal
sail logs -f laravel.test
docker compose ps
sail exec laravel.test php -v
sail exec laravel.test php -m
```

---

<a id="volumes"></a>
## Quand réinitialiser les volumes (perte de données)

Si votre base de données locale est corrompue oder si des mises à jour de schéma bloquent, réinitialisez le volume. Attention : **`sail down -v` supprime toutes les tables de base de données et les clés de cache Redis.**

Pour réinitialiser en toute sécurité un seul volume de base de données :
1. Listez les volumes : `docker volume ls`
2. Supprimez le volume cible : `docker volume rm <volume_name>`

---

## ⚠️ Erreurs courantes

**1. Monter des fichiers à travers les frontières de partitions Windows**
Conserver votre dossier de projet dans `C:/Users/...` et utiliser WSL2 pour exécuter Sail. Cela réduit les vitesses d'E/S jusqu'à 10 fois.
*Solution :* Déplacez les fichiers vers le répertoire natif de Linux, tel que `\\wsl$\Ubuntu\home\ubuntu\projects\`.

**2. Extensions PHP manquantes dans les conteneurs**
Exécuter une migration de base de données qui utilise PostgreSQL, alors que votre runtime de conteneur n'a pas été compilé avec le pilote PostgreSQL.
*Solution :* Vérifiez les modules en cours d'exécution à l'aide de `sail exec laravel.test php -m`. Publiez les Dockerfiles et reconstruisez si une extension est manquante.

**3. Collision de ports sur localhost**
Exécuter deux applications Sail en même temps sans modifier la variable APP_PORT dans l'une des configurations `.env`.
*Solution :* Ajustez `APP_PORT` et les variables de ports `FORWARD_*` dans le fichier `.env` du second projet.

---

## 🧠 Questions d'auto-évaluation

1. **Pourquoi les fichiers de projet dans WSL2 ne doivent-ils pas être stockés dans le chemin `/mnt/c/` ?**
2. **Quelle commande exécutez-vous pour reconstruire vos images Sail après avoir modifié un Dockerfile publié ?**
3. **Vrai ou Faux ? Exécuter `composer install` sur votre terminal hôte est toujours sûr si vous avez la même version de PHP que Sail.**
4. **Comment désactiver Xdebug dans Sail lorsque vous ne l'utilisez pas afin d'éviter les ralentissements ?**

<details>
<summary><b>Afficher les réponses</b></summary>

1. L'accès aux fichiers à travers la frontière du système de fichiers Windows (`/mnt/c/`) introduit de lourdes couches de virtualisation, ce qui ralentit les opérations disque et perturbe les écouteurs de modifications de fichiers.
2. Exécutez `sail build --no-cache` suivi de `sail up -d`.
3. **Faux.** Même si la version mineure de PHP correspond, votre système hôte peut ne pas disposer des bibliothèques de compilation requises ou des extensions PHP présentes dans le conteneur. Exécutez toujours les commandes via Sail.
4. Définissez `SAIL_XDEBUG_MODE=off` dans votre fichier `.env` et redémarrez les conteneurs Sail.
</details>
