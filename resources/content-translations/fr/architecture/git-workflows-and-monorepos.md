---
title: "Hygiène Git et monorepos : commits atomiques, rebase et CI/CD des microservices"
description: "Un guide complet sur l'hygiène Git, les commits atomiques, les Conventional Commits, la fusion vs le rebase, le rebase interactif, les monorepos vs polyrepos et l'optimisation des flux de travail CI/CD."
published: 2026-06-15
faq:
  - question: "Quel est le principal avantage des commits atomiques ?"
    answer: "Les commits atomiques garantissent que chaque commit contient exactement une modification logique et ses tests correspondants. Cela facilite les revues de code, maintient l'historique clair, permet des retours en arrière propres (reverts) et rend le débogage avec des outils comme 'git bisect' extrêmement efficace."
  - question: "Quand dois-je utiliser merge et quand dois-je utiliser rebase ?"
    answer: "Utilisez rebase sur vos branches locales et privées pour nettoyer votre historique, fusionner les commits intermédiaires (WIP) et maintenir un historique linéaire avant l'intégration. Utilisez merge lorsque vous fusionnez des branches de fonctionnalités complétées dans des branches publiques partagées (comme main ou develop) afin de préserver l'ordre chronologique réel et d'éviter de réécrire l'historique partagé."
  - question: "Comment puis-je éviter d'exécuter des tests pour tous les services d'un monorepo lors de la modification d'un seul service ?"
    answer: "Vous pouvez optimiser votre pipeline CI en utilisant le filtrage basé sur les chemins (par exemple, le filtre 'paths' dans GitHub Actions ou 'rules:changes' dans GitLab CI) de sorte qu'un flux de travail ne se déclenche que si des fichiers dans le sous-répertoire d'un service spécifique sont modifiés."
---

# Hygiène Git et monorepos : commits atomiques, rebase et CI/CD des microservices

Imaginez ceci : nous sommes vendredi après-midi et un bug critique est actif en production. Vous regardez l'historique git pour trouver la régression, mais vous ne voyez qu'un mur de commits : \"wip\", \"fix typo\", \"test\", \"hope this works\" et un réseau complexe et entremêlé de commits de fusion (merge). Trouver la modification problématique devient une recherche d'aiguille dans une botte de foin. Dans un autre dépôt, un développeur modifie une seule ligne dans un fichier readme d'un microservice, ce qui déclenche un pipeline CI/CD de 40 minutes qui recompile et redéploie les vingt microservices du système. Ces problèmes courants ne sont pas des défaillances de la pile technologique, mais de la stratégie de contrôle de version et de l'architecture du dépôt.

Des standards de commits clairs et des configurations de monorepos optimisées sont essentiels pour concevoir des pipelines de développement robustes, scalables et conviviaux pour les développeurs de microservices.

## Table des matières
* [Hygiène des commits et pouvoir de l'atomicité](#git-hygiene)
* [Stratégies d'intégration : Merge vs Rebase](#merge-vs-rebase)
* [Fonctionnalités avancées de Git : Rebase interactif et Reflog](#advanced-git)
* [Architecture des microservices : Monorepos vs Polyrepos](#monorepo-vs-polyrepo)
* [Outils de monorepo pour PHP & Laravel](#php-monorepos)
* [Optimisation des pipelines CI/CD dans les monorepos](#monorepo-ci)
* [Limites et compromis](#limitations)
* [Conseils pratiques](#takeaways)

---

<a id="git-hygiene"></a>
## Hygiène des commits et pouvoir de l'atomicité

Le contrôle de version est plus qu'un simple mécanisme de sauvegarde ; c'est un outil de communication et un registre historique.

### Commits atomiques
* **Point** : Un commit doit être la plus petite unité de code possible qui soit complète, fonctionnelle et contienne à la fois la modification et ses tests.
* **Pourquoi c'est important** : Si un commit est atomique, il peut être annulé (revert) facilement sans casser d'autres fonctionnalités. Cela rend également `git bisect` (la recherche binaire des bugs dans l'historique) extrêmement rapide.
* **Exemple** : Au lieu de combiner le refactoring, la correction d'un bug et la mise à jour d'une dépendance dans un seul commit géant, divisez-les en trois commits distincts.
* **Conséquence** : Les revues de code deviennent plus rapides, l'historique du code reste propre et les retours en arrière sont indolores.

### Conventional Commits
Pour standardiser l'historique des commits, les équipes utilisent la spécification **Conventional Commits**. Les messages suivent cette structure : `<type>(<scope>): <description>`.
* `feat` : Une nouvelle fonctionnalité.
* `fix` : Une correction de bug.
* `chore` : Tâches de maintenance, dépendances, configurations de build.
* `docs` : Modifications de la documentation.
* `refactor` : Modifications du code qui ne corrigent pas de bug et n'ajoutent pas de fonctionnalité.
* `test` : Ajout ou correction de tests.

---

<a id="merge-vs-rebase"></a>
## Stratégies d'intégration : Merge vs Rebase

La manière dont vous intégrez l'historique des branches dans la branche principale détermine la forme et la lisibilité de votre dépôt.

```
Merge (Historique non linéaire) :
A --- B --- C (main)
 \         /
  D --- E (feature)

Rebase (Historique linéaire) :
A --- B --- C --- D' --- E' (main/feature)
```

### Git Merge
* **Point** : Combine les branches en créant un nouveau \"commit de fusion\" (merge commit) qui possède plusieurs parents.
* **Pourquoi c'est important** : Il préserve l'ordre chronologique exact des événements et représente la réalité historique du moment où les branches ont divergé et se sont rejointes.
* **Conséquence** : L'historique devient non linéaire et encombré de commits du type \"Merge branch 'main' into feature\", ce qui rend sa lecture difficile.

### Git Rebase
* **Point** : Déplace la base de votre branche de fonctionnalité sur le dernier commit de la branche cible, en réécrivant les commits par-dessus.
* **Pourquoi c'est important** : Il crée un historique linéaire et propre où chaque fonctionnalité semble avoir été développée de manière séquentielle.
* **Conséquence** : Il réécrit les empreintes (hashes) des commits. Si vous appliquez un rebase sur des branches publiques et partagées, vous provoquerez des conflits pour le reste de l'équipe. **Règle : N'appliquez jamais de rebase sur des branches partagées.**

---

<a id="advanced-git"></a>
## Fonctionnalités avancées de Git : Rebase interactif et Reflog

Les fonctionnalités avancées de Git aident à nettoyer le code local avant de le partager avec l'équipe.

### Rebase interactif (`git rebase -i`)
* **Point** : Permet de réécrire, combiner, réordonner ou supprimer des commits dans l'historique de votre branche locale avant de pousser les modifications.
* **Pourquoi c'est important** : Permet de nettoyer les commits temporaires (\"wip\") et les corrections de fautes de frappe (\"typo\"), présente un historique soigné lors des pull requests et maintient les commits atomiques.
* **Exemple** : L'exécution de `git rebase -i HEAD~4` ouvre une liste des 4 derniers commits :
  ```text
  pick a1b2c3d feat(auth): add google oauth provider
  squash d4e5f6g wip oauth login
  squash h7i8j9k fix typo in redirect url
  reword l0m1n2o feat(auth): add docs for oauth integration
  ```
  Cela fusionne (squash) les commits intermédiaires désordonnés dans le commit de la fonctionnalité principale et réécrit le message final.

### Cherry-Picking (`git cherry-pick`)
* **Point** : Applique un commit spécifique d'une branche sur votre branche actuelle.
* **Pourquoi c'est important** : Utile pour appliquer un correctif urgent (hotfix) sur une branche de production sans fusionner toute la branche de fonctionnalité qui le contient.

### Git Reflog (`git reflog`)
* **Point** : Un journal local qui enregistre chaque modification apportée aux têtes de vos branches, même si ces commits ont été supprimés ou abandonnés par un rebase.
* **Pourquoi c'est important** : Si vous faites une erreur lors d'un rebase et perdez des commits, vous pouvez utiliser `git reflog` pour retrouver le hash du commit original et le restaurer avec `git reset --hard <hash>`.

---

<a id="monorepo-vs-polyrepo"></a>
## Architecture des microservices : Monorepos vs Polyrepos

Lors du développement de microservices, nous devons choisir comment organiser nos dépôts.

### Polyrepo (un dépôt par service)
* **Point** : Chaque microservice possède son propre dépôt isolé.
* **Pourquoi c'est important** : Des limites claires, des tailles de clonage réduites et des pipelines CI/CD isolés.
* **Conséquence** : Il est plus difficile de partager du code (nécessite la publication de packages), de réaliser des modifications transversales et de gérer les divergences de versions des dépendances communes.

### Monorepo (un seul dépôt pour tout)
* **Point** : Tous les microservices, bibliothèques et gateways vivent dans un unique dépôt.
* **Pourquoi c'est important** : Des modifications transversales simplifiées, des versions de dépendances unifiées et un partage de code instantané sans publication de packages externes.
* **Conséquence** : Dépôts de grande taille, configurations CI/CD complexes et risque de flou dans les limites si les services se couplent fortement via des dossiers partagés.

---

<a id="php-monorepos"></a>
## Outils de monorepo pour PHP & Laravel

Alors que les développeurs JavaScript utilisent Turborepo ou Lerna, les développeurs PHP peuvent construire des monorepos robustes en utilisant les fonctionnalités natives de Composer.

### 1. Dépôts de chemins Composer (Path Repositories)
Au lieu de publier des packages partagés sur Packagist, vous pouvez référencer des répertoires locaux en utilisant des dépôts de type `path` dans le fichier `composer.json` de votre microservice :
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/shared-dto",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "devsense/shared-dto": "*"
    }
}
```
Composer crée un lien symbolique (symlink) vers le package partagé, ce qui vous permet de modifier le code partagé et de voir les changements dans le microservice immédiatement sans exécuter `composer update`.

### 2. Monorepo Builder
Des outils comme **Monorepo Builder** de Symplify aident à fusionner les configurations de composer, à automatiser le versionnage sémantique des sous-packages et à maintenir synchronisées les versions des dépendances externes dans tous les services.

---

<a id="monorepo-ci"></a>
## Optimisation des pipelines CI/CD dans les monorepos

Le plus grand goulot d'étranglement d'un monorepo est le temps de build. Si chaque commit déclenche des tests pour tous les services, le flux de CI/CD devient lent et coûteux.

* **Point** : Déclencher les étapes de CI/CD de manière sélective via le filtrage de chemins.
* **Pourquoi c'est important** : Limite l'utilisation des ressources et maintient des pipelines de déploiement rapides.
* **Exemple** : Dans GitHub Actions, configurez les workflows pour qu'ils s'exécutent uniquement lorsque des fichiers dans des répertoires spécifiques changent :
  ```yaml
  # .github/workflows/user-service.yml
  on:
    push:
      branches: [ main ]
      paths:
        - 'services/user-service/**'
        - 'packages/shared-dto/**' # Se déclenche si les dépendances partagées changent
  ```
* **Conséquence** : Seuls le service modifié et ses dépendants sont testés et compilés, réduisant les temps de build de 30 minutes à 2 minutes.

---

<a id="code-demo"></a>
## Démonstration pratique de code

Voici des modèles de configuration réels pour les flux de travail en monorepo.

### 1. Flux de travail de rebase interactif Git
Pour nettoyer l'historique de votre branche avant de pousser les modifications :
```bash
# 1. Démarrer le rebase interactif pour les 3 derniers commits
git rebase -i HEAD~3

# 2. Dans l'éditeur, remplacez 'pick' par 'squash' (ou 's') pour les commits intermédiaires :
# pick 82a17f2 feat: add database indexing
# squash d928f01 fix syntax error in migration
# squash a19f291 add missing index fields

# 3. Enregistrez et fermez. Git vous demandera de modifier le message de commit combiné :
# feat: add database indexing and migrations
```

### 2. Configuration CI sélective dans les monorepos (GitHub Actions)
```yaml
# .github/workflows/checkout-service.yml
name: Checkout Service CI

on:
  push:
    branches: [ main, development ]
    paths:
      - 'services/checkout-service/**'
      - 'packages/shared-kernel/**'
  pull_request:
    branches: [ main, development ]
    paths:
      - 'services/checkout-service/**'
      - 'packages/shared-kernel/**'

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout Code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: mbstring, xml, bcmath, pdo_pgsql

      - name: Install Dependencies
        run: |
          cd services/checkout-service
          composer install --no-interaction --prefer-dist --optimize-autoloader

      - name: Run Tests
        run: |
          cd services/checkout-service
          ./vendor/bin/phpunit
```

---

<a id="limitations"></a>
## Limites et compromis

* **Risques du rebase** : Le rebase est destructeur car il réécrit l'historique. Si vous forcez la publication (`git push --force`) d'une branche partagée après un rebase, vous risquez d'écraser le travail d'autres développeurs. Utilisez toujours `git push --force-with-lease` par sécurité.
* **Taille du monorepo** : À mesure qu'un monorepo grandit, les commandes comme `git status` et `git fetch` peuvent ralentir. Les fichiers volumineux doivent être gérés via Git LFS (Large File Storage) ou exclus du dépôt.
* **Complexité de la CI/CD** : Gérer manuellement les dépendances de chemins dans les pipelines de CI peut introduire des bugs si une modification dans une bibliothèque partagée ne déclenche pas les tests dans un service qui en dépend. L'utilisation d'outils comme Turborepo ou Nx aide à gérer automatiquement ce graphe de dépendances.

---

<a id="takeaways"></a>
## Conseils pratiques

1. **Maintenez des commits atomiques** : Une modification logique par commit. Écrivez les messages à l'impératif (par exemple, `feat(auth): add token verification`, non `added verification`).
2. **Rebase en local, Merge en public** : Gardez les branches de fonctionnalités linéaires avec `git rebase`, mais fusionnez-les dans les branches principales avec des commits de fusion explicites (`git merge --no-ff`) pour préserver les points d'intégration.
3. **Utilisez Path Repositories pour les monorepos PHP** : Liez symboliquement les packages partagés dans Composer pour permettre des éditions locales immédiates.
4. **Implémentez le filtrage de chemins dans la CI** : Évitez le gaspillage de ressources en ciblant les pipelines uniquement sur les répertoires modifiés.
5. **Utilisez `git push --force-with-lease`** : Ne poussez jamais vos modifications à l'aveugle ; assurez-vous de ne pas écraser le travail distant de vos collègues.
