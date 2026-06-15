---
title: "Git-Hygiene und Monorepos: Atomare Commits, Rebasing und Mikroservices-CI/CD"
description: "Ein umfassender Leitfaden zur Git-Hygiene, zu atomaren Commits, Conventional Commits, Merging vs. Rebasing, interaktivem Rebase, Monorepos vs. Polyrepos und zur Optimierung von CI/CD-Workflows."
published: 2026-06-15
faq:
  - question: "Was ist der Hauptvorteil von atomaren Commits?"
    answer: "Atomare Commits stellen sicher, dass jeder Commit genau eine logische Änderung und die entsprechenden Tests enthält. Dies erleichtert Code-Reviews, hält die Commit-Historie übersichtlich, ermöglicht saubere Rollbacks spezifischer Änderungen und macht das Debugging mit Tools wie 'git bisect' äußerst effizient."
  - question: "Wann sollte ich Merge und wann Rebase verwenden?"
    answer: "Verwenden Sie Rebase auf lokalen, privaten Branches, um Ihre Historie aufzuräumen, WIP-Commits zusammenzufassen und vor der Integration eine lineare Historie beizubehalten. Verwenden Sie Merge, wenn Sie fertige Feature-Branches in öffentliche, freigegebene Branches (wie main oder develop) zusammenführen, um die tatsächliche chronologische Reihenfolge zu bewahren und das Umschreiben der gemeinsamen Historie zu vermeiden."
  - question: "Wie kann ich vermeiden, dass bei einer Änderung an einem einzelnen Service in einem Monorepo die Tests für alle Services ausgeführt werden?"
    answer: "Sie können Ihre CI-Pipeline optimieren, indem Sie eine pfadbasierte Filterung verwenden (z. B. den 'paths'-Filter in GitHub Actions oder 'rules:changes' in GitLab CI), sodass ein Workflow nur ausgelöst wird, wenn Dateien im Unterverzeichnis eines bestimmten Services geändert werden."
---

# Git-Hygiene und Monorepos: Atomare Commits, Rebasing und Mikroservices-CI/CD

Stellen Sie sich Folgendes vor: Es ist Freitagnachmittag, und ein kritischer Fehler ist in der Produktion aktiv. Sie schauen sich die Git-Historie an, um die Regression zu finden, sehen aber nur eine Wand von Commits: \"wip\", \"fix typo\", \"test\", \"hope this works\" und ein riesiges, verworrenes Netz aus Merge-Commits. Das Finden der fehlerhaften Änderung wird zur Suche nach der Nadel im Heuhaufen. In einem anderen Repository ändert ein Entwickler eine einzige Textzeile in einer Readme-Datei eines Mikroservices, nur um eine 40-minütige CI/CD-Pipeline auszulösen, die alle zwanzig Mikroservices im System neu erstellt und bereitstellt. Diese häufigen Probleme sind keine Fehler des Technologie-Stacks, sondern Fehler der Versionskontrollstrategie und der Repository-Architektur.

Saubere Commit-Standards und optimierte Monorepo-Konfigurationen sind unerlässlich, um robuste, skalierbare und entwicklerfreundliche Pipelines für Mikroservices aufzubauen.

## Inhaltsverzeichnis
* [Commit-Hygiene und die Macht der Atomarität](#git-hygiene)
* [Integrationsstrategien: Merge vs. Rebase](#merge-vs-rebase)
* [Fortgeschrittene Git-Funktionen: Interaktiver Rebase und Reflog](#advanced-git)
* [Mikroservices-Layouts: Monorepos vs. Polyrepos](#monorepo-vs-polyrepo)
* [Monorepo-Tooling für PHP & Laravel](#php-monorepos)
* [CI/CD-Pipeline-Optimierung in Monorepos](#monorepo-ci)
* [Einschränkungen und Kompromisse](#limitations)
* [Praktische Erkenntnisse](#takeaways)

---

<a id="git-hygiene"></a>
## Commit-Hygiene und die Macht der Atomarität

Die Versionskontrolle ist mehr als nur ein Backup-Mechanismus; sie ist ein Kommunikationswerkzeug und ein Verlaufsprotokoll.

### Atomare Commits
* **Punkt**: Ein Commit sollte die kleinstmögliche Codeeinheit sein, die vollständig und funktionsfähig ist und sowohl die Änderung als auch ihre Tests enthält.
* **Warum es wichtig ist**: Wenn ein Commit atomar ist, kann er einfach per Revert rückgängig gemacht werden, ohne andere Funktionen zu beeinträchtigen. Es macht auch `git bisect` (die binäre Suche nach Fehlern im Verlauf) extrem schnell.
* **Beispiel**: Anstatt Refactoring, Fehlerbehebung und ein Abhängigkeits-Update in einem riesigen Commit zu kombinieren, teilen Sie diese in drei verschiedene Commits auf.
* **Konsequenz**: Code-Reviews werden schneller, der Code-Verlauf bleibt sauber und Rollbacks sind schmerzlos.

### Conventional Commits
Um den Commit-Verlauf zu standardisieren, verwenden Teams die Spezifikation **Conventional Commits**. Nachrichten folgen dieser Struktur: `<type>(<scope>): <description>`.
* `feat`: Ein neues Feature.
* `fix`: Eine Fehlerbehebung.
* `chore`: Wartungsaufgaben, Abhängigkeiten, Build-Konfigurationen.
* `docs`: Änderungen an der Dokumentation.
* `refactor`: Codeänderungen, die weder einen Fehler beheben noch ein Feature hinzufügen.
* `test`: Hinzufügen oder Korrigieren von Tests.

---

<a id="merge-vs-rebase"></a>
## Integrationsstrategien: Merge vs. Rebase

Wie Sie den Branch-Verlauf wieder in den Hauptzweig integrieren, bestimmt die Form und Lesbarkeit Ihres Repositories.

```
Merge (Nicht-lineare Historie):
A --- B --- C (main)
 \         /
  D --- E (feature)

Rebase (Lineare Historie):
A --- B --- C --- D' --- E' (main/feature)
```

### Git Merge
* **Punkt**: Kombiniert Branches, indem ein neuer \"Merge-Commit\" erstellt wird, der mehrere Eltern-Commits hat.
* **Warum es wichtig ist**: Es bewahrt die exakte chronologische Reihenfolge der Ereignisse und stellt die historische Realität dar, wann Branches voneinander abwichen und sich trafen.
* **Konsequenz**: Der Verlauf wird nicht-linear und mit \"Merge branch 'main' into feature\"-Commits überladen, was das Lesen erschwert.

### Git Rebase
* **Punkt**: Verschiebt die Basis Ihres Feature-Branches auf den neuesten Commit des Ziel-Branches und schreibt die Commits darauf neu.
* **Warum es wichtig ist**: Es erstellt eine saubere, lineare Historie, in der jedes Feature nacheinander entwickelt worden zu sein scheint.
* **Konsequenz**: Es schreibt Commit-Hashes neu. Wenn Sie öffentliche, freigegebene Branches rebasen, verursachen Sie Konflikte für alle anderen im Team. **Regel: Rebasen Sie niemals freigegebene Branches.**

---

<a id="advanced-git"></a>
## Fortgeschrittene Git-Funktionen: Interaktiver Rebase und Reflog

Fortgeschrittene Git-Funktionen helfen dabei, den lokalen Code aufzuräumen, bevor er mit dem Team geteilt wird.

### Interaktiver Rebase (`git rebase -i`)
* **Punkt**: Ermöglicht das Umschreiben, Kombinieren, Umordnen oder Löschen von Commits in Ihrem lokalen Branch-Verlauf vor dem Push.
* **Warum es wichtig ist**: Es ermöglicht das Aufräumen von \"wip\"- und \"typo\"-Commits, präsentiert einen polierten Verlauf während Pull Requests und hält Commits atomar.
* **Beispiel**: Das Ausführen von `git rebase -i HEAD~4` öffnet eine Liste der letzten 4 Commits:
  ```text
  pick a1b2c3d feat(auth): add google oauth provider
  squash d4e5f6g wip oauth login
  squash h7i8j9k fix typo in redirect url
  reword l0m1n2o feat(auth): add docs for oauth integration
  ```
  Dies fasst die unordentlichen Zwischen-Commits in den Haupt-Feature-Commit zusammen und schreibt die finale Nachricht neu.

### Cherry-Picking (`git cherry-pick`)
* **Punkt**: Hängt einen bestimmten Commit von einem Branch an Ihren aktuellen Branch an.
* **Warum es wichtig ist**: Nützlich, um einen Hotfix für einen Fehler in einen Produktions-Branch einzuspielen, ohne den gesamten Feature-Branch zu mergen.

### Git Reflog (`git reflog`)
* **Punkt**: Ein lokales Tagebuch, das jede einzelne Änderung an den Spitzen Ihrer Branches aufzeichnet, selbst wenn diese Commits gelöscht oder durch einen Rebase verwaist wurden.
* **Warum es wichtig ist**: Wenn Sie während eines Rebases einen Fehler machen und Commits verlieren, können Sie mit `git reflog` den ursprünglichen Commit-Hash finden und ihn mit `git reset --hard <hash>` wiederherstellen.

---

<a id="monorepo-vs-polyrepo"></a>
## Mikroservices-Layouts: Monorepos vs. Polyrepos

Bei der Entwicklung von Mikroservices müssen wir entscheiden, wie wir unsere Repositories organisieren.

### Polyrepo (Repository pro Service)
* **Punkt**: Jeder Mikroservice hat sein eigenes, isoliertes Repository.
* **Warum es wichtig ist**: Klare Grenzen, geringe Klongrößen und isolierte CI/CD-Pipelines.
* **Konsequenz**: Schwerer Code auszutauschen (erfordert das Veröffentlichen von Paketen), schwierige serviceübergreifende Änderungen und abweichende Abhängigkeitsversionen über Services hinweg.

### Monorepo (Ein Repository für alles)
* **Punkt**: Alle Mikroservices, Bibliotheken und Gateways leben in einem einzigen Repository.
* **Warum es wichtig ist**: Vereinfachte serviceübergreifende Änderungen, einheitliche Abhängigkeitsversionen und sofortige gemeinsame Nutzung von Code, ohne Pakete veröffentlichen zu müssen.
* **Konsequenz**: Große Repositories, komplexe CI/CD-Konfigurationen und die Gefahr von unscharfen Grenzen, bei denen sich Services eng mit gemeinsamen Ordnern koppeln.

---

<a id="php-monorepos"></a>
## Monorepo-Tooling für PHP & Laravel

Während JavaScript-Entwickler Turborepo oder Lerna verwenden, können PHP-Entwickler robuste Monorepos mit nativen Composer-Funktionen aufbauen.

### 1. Composer Path Repositories
Anstatt gemeinsame Pakete auf Packagist zu veröffentlichen, können Sie lokale Verzeichnisse mithilfe von `path`-Repositories in der `composer.json` Ihres Mikroservices referenzieren:
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
Composer erstellt einen Symlink zum gemeinsamen Paket, sodass Sie den gemeinsamen Code bearbeiten und Änderungen im Mikroservice sofort sehen können, ohne `composer update` auszuführen.

### 2. Monorepo Builder
Tools wie Symplifys **Monorepo Builder** helfen dabei, Composer-Konfigurationen zusammenzuführen, die semantische Versionierung von Unterpaketen zu automatisieren und die Abhängigkeitsversionen über alle Services hinweg synchron zu halten.

---

<a id="monorepo-ci"></a>
## CI/CD-Pipeline-Optimierung in Monorepos

Der größte Flaschenhals eines Monorepos sind die Build-Zeiten. Wenn jeder Commit Tests für alle Services auslöst, wird CI/CD langsam und teuer.

* **Punkt**: Trigger-CI/CD-Schritte selektiv mittels Pfadfilterung ausführen.
* **Warum es wichtig ist**: Es schränkt die Ressourcennutzung ein und hält Bereitstellungspipelines schnell.
* **Beispiel**: Konfigurieren Sie in GitHub Actions Workflows so, dass sie nur ausgeführt werden, wenn Dateien in bestimmten Verzeichnissen geändert werden:
  ```yaml
  # .github/workflows/user-service.yml
  on:
    push:
      branches: [ main ]
      paths:
        - 'services/user-service/**'
        - 'packages/shared-dto/**' # Auslösen, wenn sich gemeinsame Abhängigkeiten ändern
  ```
* **Konsequenz**: Nur der geänderte Service und seine Abhängigkeiten werden getestet und erstellt, wodurch sich die Build-Zeiten von 30 Minuten auf 2 Minuten reduzieren.

---

<a id="code-demo"></a>
## Praktisches Codebeispiel

Hier sind reale Konfigurationsvorlagen für Monorepo-Workflows.

### 1. Git Interactive Rebase Workflow
So räumen Sie Ihren Branch-Verlauf vor dem Pushen auf:
```bash
# 1. Starten Sie den interaktiven Rebase für die letzten 3 Commits
git rebase -i HEAD~3

# 2. Ändern Sie im Editor 'pick' in 'squash' (oder 's') für Zwischen-Commits:
# pick 82a17f2 feat: add database indexing
# squash d928f01 fix syntax error in migration
# squash a19f291 add missing index fields

# 3. Speichern und schließen. Git fordert Sie auf, die kombinierte Commit-Nachricht zu bearbeiten:
# feat: add database indexing and migrations
```

### 2. Monorepo Selektive CI-Konfiguration (GitHub Actions)
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
## Einschränkungen und Kompromisse

* **Rebase-Risiken**: Das Rebasen ist destruktiv, da es die Historie neu schreibt. Wenn Sie einen gerebasten Branch erzwingen (`git push --force`), können Sie Commits anderer Entwickler überschreiben. Verwenden Sie zur Sicherheit immer `git push --force-with-lease`.
* **Monorepo-Größe**: Wenn ein Monorepo wächst, können Git-Befehle wie `status` und `fetch` langsamer werden. Große Dateien und Medien sollten über Git LFS (Large File Storage) verwaltet oder ganz aus dem Repository herausgehalten werden.
* **CI/CD-Komplexität**: Die manuelle Verwaltung von Pfadabhängigkeiten in CI-Pipelines kann zu Fehlern führen, bei denen eine Änderung in einer gemeinsamen Bibliothek in einem Service, der darauf basiert, ungetestet bleibt. Build-Tools wie Turborepo oder Nx verwalten diesen Abhängigkeitsgraph automatisch für Sie.

---

<a id="takeaways"></a>
## Praktische Erkenntnisse

1. **Halten Sie Commits atomar**: Eine logische Änderung pro Commit. Schreiben Sie Nachrichten im Imperativ (z. B. `feat(auth): add token verification`, nicht `added verification`).
2. **Lokal rebasen, öffentlich mergen**: Halten Sie Feature-Branches mit `git rebase` linear, aber führen Sie sie mit expliziten Merge-Commits (`git merge --no-ff`) in Hauptzweige zusammen, um Integrationspunkte zu erhalten.
3. **Verwenden Sie Path Repositories für PHP-Monorepos**: Verlinken Sie gemeinsame Pakete in Composer per Symlink, um sofortige lokale Bearbeitungen zu ermöglichen.
4. **Implementieren Sie Pfadfilterung in der CI**: Verhindern Sie Ressourcenverschwendung, indem Sie Pipelines auf geänderte Verzeichnisse ausrichten.
5. **Verwenden Sie `git push --force-with-lease`**: Pushen Sie niemals blind; stellen Sie sicher, dass Sie keine Remotearbeiten überschreiben.
