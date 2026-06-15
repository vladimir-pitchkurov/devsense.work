---
title: "Igiene di Git e monorepo: commit atomici, rebase e CI/CD dei microservizi"
description: "Una guida completa sull'igiene di Git, commit atomici, Conventional Commits, merge vs rebase, rebase interattivo, monorepo vs polyrepo e l'ottimizzazione dei flussi di lavoro CI/CD."
published: 2026-06-15
faq:
  - question: "Qual è il vantaggio principale dei commit atomici?"
    answer: "I commit atomici garantiscono che ogni commit contenga esattamente una modifica logica e i relativi test. Questo semplifica le revisioni del codice, mantiene pulita la cronologia dei commit, consente rollback puliti di modifiche specifiche e rende il debug con strumenti come 'git bisect' estremamente efficiente."
  - question: "Quando dovrei usare merge e quando rebase?"
    answer: "Usa rebase sui rami locali e privati per pulire la cronologia, unire i commit di lavoro in corso (WIP) e mantenere una cronologia lineare prima dell'integrazione. Usa merge quando unisci rami di funzionalità completati in rami pubblici condivisi (come main o develop) per preservare l'ordine cronologico reale ed evitare di riscrivere la cronologia condivisa."
  - question: "Come posso evitare di eseguire test per tutti i servizi in un monorepo quando modifico un solo servizio?"
    answer: "Puoi ottimizzare la pipeline di CI utilizzando il filtraggio basato sui percorsi (ad esempio, il filtro 'paths' in GitHub Actions o 'rules:changes' in GitLab CI) in modo che un flusso di lavoro si attivi solo quando vengono modificati file all'interno della sottocartella di un servizio specifico."
---

# Igiene di Git e monorepo: commit atomici, rebase e CI/CD dei microservizi

Immaginate questo: è venerdì pomeriggio e c'è un bug critico in produzione. Guardate la cronologia di git per trovare la regressione, ma vedete solo un muro di commit: \"wip\", \"fix typo\", \"test\", \"hope this works\" e una rete aggrovigliata e massiccia di commit di merge. Trovare la modifica che ha causato il bug diventa come cercare un ago in un pagliaio. In un altro repository, uno sviluppatore modifica una singola riga di testo nel file readme di un microservizio, attivando una pipeline di CI/CD di 40 minuti che ricompila e distribuisce tutti i venti microservizi del sistema. Questi problemi comuni non sono difetti dello stack tecnologico, ma fallimenti della strategia di controllo delle versioni e dell'architettura del repository.

Standard di commit puliti e configurazioni di monorepo ottimizzate sono essenziali per creare pipeline di sviluppo robuste, scalabili e comode per gli sviluppatori nelle architetture a microservizi.

## Tabella dei contenuti
* [Igiene dei commit e il potere dell'atomicità](#git-hygiene)
* [Strategie di integrazione: Merge vs Rebase](#merge-vs-rebase)
* [Funzionalità avanzate di Git: Rebase interattivo e Reflog](#advanced-git)
* [Organizzazione dei microservizi: Monorepo vs Polyrepo](#monorepo-vs-polyrepo)
* [Strumenti di monorepo per PHP & Laravel](#php-monorepos)
* [Ottimizzazione delle pipeline di CI/CD nei monorepo](#monorepo-ci)
* [Limitazioni e compromessi](#limitations)
* [Consigli pratici](#takeaways)

---

<a id="git-hygiene"></a>
## Igiene dei commit e il potere dell'atomicità

Il controllo delle versioni è molto più di un semplice meccanismo di backup; è uno strumento di comunicazione e un registro storico.

### Commit atomici
* **Punto**: Un commit dovrebbe essere la più piccola unità di codice possibile che sia completa, funzionante e contenga sia la modifica che i relativi test.
* **Perché è importante**: Se un commit è atomico, può essere annullato (revert) facilmente senza rompere altre funzionalità. Rende anche `git bisect` (la ricerca binaria dei bug nella cronologia) estremamente veloce.
* **Esempio**: Invece di combinare il refactoring, la correzione di un bug e l'aggiornamento di una dipendenza in un unico commit gigante, dividili in tre commit distinti.
* **Conseguenza**: Le revisioni del codice diventano più veloci, la cronologia del codice rimane pulita e i rollback sono indolori.

### Conventional Commits
Per standardizzare la cronologia dei commit, i team utilizzano la specifica **Conventional Commits**. I messaggi seguono questa struttura: `<type>(<scope>): <description>`.
* `feat`: Una nuova funzionalità.
* `fix`: Una correzione di bug.
* `chore`: Attività di manutenzione, dipendenze, configurazioni di build.
* `docs`: Modifiche alla documentazione.
* `refactor`: Modifiche al codice che non correggono bug né aggiungono funzionalità.
* `test`: Aggiunta o correzione di test.

---

<a id="merge-vs-rebase"></a>
## Strategie di integrazione: Merge vs Rebase

Il modo in cui si integra la cronologia dei rami nel ramo principale determina la forma e la leggibilità del repository.

```
Merge (Cronologia non lineare):
A --- B --- C (main)
 \         /
  D --- E (feature)

Rebase (Cronologia lineare):
A --- B --- C --- D' --- E' (main/feature)
```

### Git Merge
* **Punto**: Combina i rami creando un nuovo \"commit di merge\" (merge commit) che ha più commit genitori.
* **Perché è importante**: Preserva l'ordine cronologico esatto degli eventi e rappresenta la realtà storica di quando i rami si sono biforcati e uniti.
* **Conseguenza**: La cronologia diventa non lineare e sovraccarica di commit del tipo \"Merge branch 'main' into feature\", rendendo difficile la lettura.

### Git Rebase
* **Punto**: Sposta la base del ramo di funzionalità sull'ultimo commit del ramo di destinazione, riscrivendo i commit su di esso.
* **Perché è importante**: Crea una cronologia lineare e pulita in cui ogni funzionalità sembra essere stata sviluppata in modo sequenziale.
* **Conseguenza**: Riscrive le impronte (hashes) dei commit. Se si applica il rebase su rami pubblici e condivisi, si verificheranno conflitti per il resto del team. **Regola: Non applicare mai il rebase su rami condivisi.**

---

<a id="advanced-git"></a>
## Funzionalità avanzate di Git: Rebase interattivo e Reflog

Le funzionalità avanzate di Git aiutano a pulire il codice locale prima di condividerlo con il team.

### Rebase interattivo (`git rebase -i`)
* **Punto**: Consente di riscrivere, combinare, riordinare o eliminare i commit nella cronologia del ramo locale prima di spingere le modifiche.
* **Perché è importante**: Consente di ripulire i commit temporanei (\"wip\") e le correzioni di bozze (\"typo\"), presenta una cronologia ordinata durante le pull request e mantiene i commit atomici.
* **Esempio**: L'esecuzione di `git rebase -i HEAD~4` apre un elenco degli ultimi 4 commit:
  ```text
  pick a1b2c3d feat(auth): add google oauth provider
  squash d4e5f6g wip oauth login
  squash h7i8j9k fix typo in redirect url
  reword l0m1n2o feat(auth): add docs for oauth integration
  ```
  Questo unisce (squash) i commit intermedi disordinati nel commit della funzionalità principale e riscrive il messaggio finale.

### Cherry-Picking (`git cherry-pick`)
* **Punto**: Applica un commit specifico da un ramo al ramo corrente.
* **Perché è importante**: Utile per applicare una correzione urgente (hotfix) su un ramo di produzione senza unire l'intero ramo di funzionalità che lo contiene.

### Git Reflog (`git reflog`)
* **Punto**: Un diario locale che registra ogni singola modifica apportata alle teste dei rami, anche se quei commit sono stati eliminati o abbandonati da un rebase.
* **Perché è importante**: Se si commette un errore durante un rebase e si perdono dei commit, è possibile utilizzare `git reflog` per trovare il hash del commit originale e ripristinarlo con `git reset --hard <hash>`.

---

<a id="monorepo-vs-polyrepo"></a>
## Organizzazione dei microservizi: Monorepo vs Polyrepo

Durante lo sviluppo di microservizi, dobbiamo scegliere come organizzare i nostri repository.

### Polyrepo (un repository per servizio)
* **Punto**: Ogni microservizio ha il proprio repository isolato.
* **Perché è importante**: Confini chiari, dimensioni di clonazione ridotte e pipeline di CI/CD isolate.
* **Conseguenza**: È più difficile condividere il codice (richiede la pubblicazione di pacchetti), rende complessi i cambiamenti trasversali e causa divergenze di versione nelle dipendenze comuni.

### Monorepo (un unico repository per tutto)
* **Punto**: Tutti i microservizi, le librerie e i gateway vivono in un unico repository.
* **Perché è importante**: Cambiamenti trasversali semplificati, versioni delle dipendenze unificate e condivisione del codice istantanea senza pubblicare pacchetti esterni.
* **Conseguenza**: Repository di grandi dimensioni, configurazioni di CI/CD complesse e il pericolo di confini sfocati se i servizi si accoppiano fortemente attraverso cartelle condivise.

---

<a id="php-monorepos"></a>
## Strumenti di monorepo per PHP & Laravel

Mentre gli sviluppatori JavaScript utilizzano Turborepo o Lerna, gli sviluppatori PHP possono costruire monorepo robusti utilizzando le funzionalità native di Composer.

### 1. Repository di percorsi Composer (Path Repositories)
Invece di pubblicare pacchetti condivisi su Packagist, puoi fare riferimento a directory locali utilizzando repository di tipo `path` nel file `composer.json` del tuo microservizio:
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
Composer crea un collegamento simbolico (symlink) al pacchetto condiviso, consentendoti di modificare il codice condiviso e vedere i cambiamenti nel microservizio immediatamente senza eseguire `composer update`.

### 2. Monorepo Builder
Strumenti come **Monorepo Builder** di Symplify aiutano a unire le configurazioni di composer, automatizzare il versionamento semantico dei sotto-pacchetti e mantenere sincronizzate le versioni delle dipendenze esterne in tutti i servizi.

---

<a id="monorepo-ci"></a>
## Ottimizzazione delle pipeline di CI/CD nei monorepo

Il collo di bottiglia principale di un monorepo è il tempo di compilazione. Se ogni commit attiva i test per tutti i servizi, il flusso di CI/CD diventa lento e costoso.

* **Punto**: Attivare i passaggi di CI/CD in modo selettivo tramite il filtraggio dei percorsi.
* **Perché è importante**: Limita l'uso delle risorse e mantiene rapide le pipeline di distribuzione.
* **Esempio**: In GitHub Actions, configura i workflow in modo che vengano eseguiti solo quando cambiano i file in directory specifiche:
  ```yaml
  # .github/workflows/user-service.yml
  on:
    push:
      branches: [ main ]
      paths:
        - 'services/user-service/**'
        - 'packages/shared-dto/**' # Si attiva se le dipendenze condivise cambiano
  ```
* **Conseguenza**: Vengono testati e compilati solo il servizio modificato e quelli da esso dipendenti, riducendo i tempi di build da 30 a 2 minuti.

---

<a id="code-demo"></a>
## Dimostrazione pratica di codice

Ecco modelli di configurazione reali per i flussi di lavoro in monorepo.

### 1. Flusso di lavoro di rebase interattivo Git
Per pulire la cronologia del ramo prima di spingere le modifiche:
```bash
# 1. Avvia il rebase interattivo per gli ultimi 3 commit
git rebase -i HEAD~3

# 2. Nell'editor, cambia 'pick' in 'squash' (o 's') per i commit intermedi:
# pick 82a17f2 feat: add database indexing
# squash d928f01 fix syntax error in migration
# squash a19f291 add missing index fields

# 3. Salva e chiudi. Git ti chiederà di modificare il messaggio di commit combinato:
# feat: add database indexing and migrations
```

### 2. Configurazione CI selettiva nei monorepo (GitHub Actions)
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
## Limitazioni e compromessi

* **Rischi del rebase**: Il rebase è un'operazione distruttiva perché riscrive la cronologia. Se si forza la pubblicazione (`git push --force`) di un ramo condiviso dopo un rebase, si rischia di sovrascrivere il lavoro di altri sviluppatori. Usa sempre `git push --force-with-lease` per sicurezza.
* **Scala del monorepo**: Man mano che un monorepo cresce, i comandi come `git status` e `git fetch` possono rallentare. I file di grandi dimensioni devono essere gestiti tramite Git LFS (Large File Storage) o esclusi dal repository.
* **Complessità della CI/CD**: Gestire manualmente le dipendenze dei percorsi nelle pipeline di CI può introdurre bug se una modifica in una libreria condivisa non attiva i test in un servizio che ne dipende. L'uso di strumenti come Turborepo o Nx aiuta a gestire automaticamente questo grafo di dipendenze.

---

<a id="takeaways"></a>
## Consigli pratici

1. **Mantieni commit atomici**: Una modifica logica per commit. Scrivi i messaggi all'imperativo (ad esempio, `feat(auth): add token verification`, non `added verification`).
2. **Rebase in locale, Merge in pubblico**: Mantieni i rami di funzionalità lineari con `git rebase`, ma fondili nei rami principali con commit di merge espliciti (`git merge --no-ff`) per preservare i punti di integrazione.
3. **Usa Path Repositories per i monorepo PHP**: Collega simbolicamente i pacchetti condivisi in Composer per consentire modifiche locali immediate.
4. **Implementa il filtraggio dei percorsi nella CI**: Evita lo spreco di risorse indirizzando le pipeline solo alle cartelle modificate.
5. **Usa `git push --force-with-lease`**: Non spingere mai le modifiche alla cieca; assicurati di non sovrascrivere il lavoro remoto dei tuoi colleghi.
