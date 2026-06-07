---
title: "Laravel Sail: risoluzione dei problemi di WSL2, permessi, porte, ricostruzioni, OPcache e Vite | DevSense"
description: "Risolvi i problemi comuni con Laravel Sail: sincronizzazione dei file di WSL2, permessi di UID/GID e storage, conflitti di porte FORWARD_*, layer Docker obsoleti, OPcache e Xdebug in sviluppo, npm/Vite su host vs container, e reset sicuri dei volumi."
faq:
  - question: "Perché la compilazione degli asset di Vite su Windows con WSL2 è estremamente lenta?"
    answer: "Questo succede solitamente perché i file del progetto si trovano nel file system di Windows (/mnt/c/...). Per risolvere il problema, sposta la cartella del progetto nel file system nativo di Linux (ad es. ~/projects/) all'interno di WSL2."
  - question: "Come posso risolvere gli errori di permesso negato (permission denied) nella directory storage?"
    answer: "Esegui 'sail artisan cache:clear' e configura le variabili d'ambiente WWWUSER e WWWGROUP nel file .env affinché corrispondano all'ID utente della tua macchina host, in modo che i file generati dai tool CLI all'interno dei container mantengano i diritti di proprietà corretti."
  - question: "Perché le modifiche al codice non vengono aggiornate nel container in esecuzione?"
    answer: "Se si tratta di una modifica web, OPcache potrebbe essere attivo nella configurazione PHP. Se si tratta di un queue worker, ha memorizzato nella cache il bootstrap. Se si tratta di una modifica alla configurazione del Dockerfile, devi eseguire 'sail build --no-cache' e riavviare i container."
  - question: "Come posso verificare quali estensioni sono caricate nel mio ambiente PHP di Sail?"
    answer: "Esegui il comando 'sail exec laravel.test php -m' per elencare tutti i moduli PHP attivi in esecuzione all'interno del container, poiché questo elenco differirà da quello della tua macchina host."
---

# Sail: risoluzione dei problemi e prestazioni

La tua applicazione funzionava perfettamente ieri, ma oggi `sail up` restituisce un conflitto di allocazione delle porte, gli asset non vengono compilati tramite Vite e le scritture sul database falliscono con errori di autorizzazione. Quando Sail fallisce, raramente si tratta di un bug di Laravel; è quasi sempre un punto di attrito tra l'ambiente virtualizzato del container e il file system, la rete o lo stato dei processi della tua macchina host.

I layer di virtualizzazione (in particolare WSL2 su Windows) e i namespace di rete dei container introducono peculiarità uniche. Comprendere come diagnosticare e aggirare questi ostacoli è essenziale per mantenere un flusso di lavoro di sviluppo rapido e fluido.

In questa guida raccogliamo i sintomi più comuni di Sail, spieghiamo perché si verificano e forniamo i comandi diretti per risolverli.

**Navigazione:** [Tutti gli strumenti](../) · [Panoramica di Sail](sail#what-sail-is) · [Database](sail-databases#networking) · [Code](sail-queues#connections) · [Env e deploy](sail-env-deploy#forward-ports)

## Indice

* [WSL2, Docker Desktop e sincronizzazione dei file](#wsl-filesync)
* [Permessi, `storage` e `vendor`](#permissions)
* [Porta già allocata / `FORWARD_*`](#ports)
* [Discrepanze host vs container](#host-vs-container)
* [Codice obsoleto: ricostruzioni e layer](#rebuild)
* [OPcache e autoload nello sviluppo locale](#opcache)
* [Xdebug sembra lento](#xdebug)
* [Vite, npm e Node all'interno vs all'esterno di Sail](#frontend)
* [Log e diagnostica rapida](#logs)
* [Quando resettare i volumi (perdita di dati)](#volumes)
* [Errori comuni](#common-mistakes)
* [Domande di autovalutazione](#self-check)

---

<a id="wsl-filesync"></a>
## WSL2, Docker Desktop e sincronizzazione dei file

Su Windows + WSL2, montare file dalla partizione di Windows (`/mnt/c/...`) nei container Linux provoca enormi colli di bottiglia nell'I/O del disco. I file watcher dell'hot-module replacement (HMR) di Vite e webpack non riceveranno inoltre le notifiche dei cambiamenti dei file.

Conserva le cartelle dei tuoi progetti interamente **all'interno del file system nativo di WSL** (ad es. `~/projects/...`). Aprile in VS Code utilizzando l'**estensione WSL** per garantire che gli eventi di sincronizzazione dei file e le compilazioni richiedano millisecondi anziché secondi.

---

<a id="permissions"></a>
## Permessi, `storage` e `vendor`

Laravel richiede permessi di scrittura per `storage/` e `bootstrap/cache/`. Se Docker viene eseguito come utente root, i file creati all'interno dei container diventeranno di sola lettura sulla tua macchina host.

Verifica i permessi all'interno del container:

```bash
# Terminal
sail exec laravel.test ls -la storage bootstrap/cache
```

> [!NOTE]
> **Mappatura di UID e GID**
> Sui sistemi host Linux, allinea gli UID e i GID del tuo host definendo le variabili `WWWUSER` e `WWWGROUP` nel tuo file delle variabili d'ambiente:
> ```dotenv
> # .env
> WWWUSER=1000
> WWWGROUP=1000
> ```
> Riavvia Sail dopo aver apportato queste modifiche.

---

<a id="ports"></a>
## Porta già allocata / `FORWARD_*`

Se un altro servizio (come un database locale o un'altra istanza di Sail in esecuzione) sta già utilizzando porte come la `3306` o la `80`, visualizzerai un errore del tipo `address already in use`.

Definisci porte alternative nel tuo file `.env`:

```dotenv
# .env
APP_PORT=8081
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
```

Esegui `sail down && sail up -d` per applicare le modifiche.

---

<a id="host-vs-container"></a>
## Discrepanze host vs container

Un errore comune è quello di eseguire comandi come `php artisan migrate` o `composer install` direttamente sulla macchina host. Se l'host utilizza una versione diversa di PHP o non dispone di estensioni (come `redis` o `mongodb`), il comando fallirà o corromperà il file lock.

- **Host (Sconsigliato):** `composer install`
- **Sail (Consigliato):** `sail composer install`

---

<a id="rebuild"></a>
## Codice obsoleto: ricostruzioni e layer

Quando modifichi dipendenze, runtime PHP o pacchetti personalizzati nei Dockerfile pubblicati, Docker non ricostruirà le immagini automaticamente.

Forza una ricostruzione completa dell'immagine senza utilizzare i layer memorizzati nella cache:

```bash
# Terminal
sail build --no-cache
sail up -d
```

---

<a id="opcache"></a>
## OPcache e autoload nello sviluppo locale

Se le modifiche al codice non vengono visualizzate nel browser, verifica se OPcache è attivo nel container. In fase di sviluppo, OPcache deve validare i timestamp ad ogni richiesta.

Assicurati che le seguenti opzioni siano configurate nel file `php.ini` del tuo container:

```ini
# php.ini
opcache.validate_timestamps=1
opcache.revalidate_freq=0
```

Se aggiungi nuove classi e ricevi errori di namespace, esegui `sail composer dump-autoload`.

---

<a id="xdebug"></a>
## Xdebug sembra lento

Xdebug aggiunge un sovraccarico di prestazioni a ogni richiesta. Disabilitalo quando non stai eseguendo il debug attivo configurando:

```dotenv
# .env
SAIL_XDEBUG_MODE=off
```

Riavvia Sail con il comando `sail down && sail up -d`.

---

<a id="frontend"></a>
## Vite, npm e Node all'interno vs all'esterno di Sail

Puoi compilare gli asset frontend sulla macchina host o all'interno di Sail.

- **Compilazione su host (Più veloce):** Esegui `npm run dev` nel terminale locale.
- **Compilazione in container (Configurazione zero):** Esegui `sail npm run dev`.

Assicurati che `APP_URL` e le porte di Vite corrispondano, altrimenti riscontrerai errori di connessione HMR nella console del browser.

---

<a id="logs"></a>
## Log e diagnostica rapida

Esegui questi comandi per controllare lo stato di salute dei container e lo stato di PHP:

```bash
# Terminal
sail logs -f laravel.test
docker compose ps
sail exec laravel.test php -v
sail exec laravel.test php -m
```

---

<a id="volumes"></a>
## Quando resettare i volumi (perdita di dati)

Se il database locale si corrompe o se gli aggiornamenti dello schema si bloccano, esegui il reset del volume. Attenzione: **`sail down -v` cancella tutte le tabelle del database e le chiavi della cache di Redis.**

Per resettare in sicurezza un singolo volume di database:
1. Elenca i volumi: `docker volume ls`
2. Rimuovi il volume di destinazione: `docker volume rm <volume_name>`

---

## ⚠️ Errori comuni

**1. Montaggio di file superando i confini delle partizioni di Windows**
Mantenere la cartella del progetto in `C:/Users/...` e utilizzare WSL2 per eseguire Sail. Questo riduce la velocità di I/O fino a 10 volte.
*Soluzione:* Sposta i file nella directory Linux nativa, come ad esempio `\\wsl$\Ubuntu\home\ubuntu\projects\`.

**2. Estensioni PHP mancanti nei container**
Eseguire una migrazione del database che utilizza PostgreSQL, quando il runtime del tuo container non è stato compilato con il driver PostgreSQL.
*Soluzione:* Controlla i moduli attivi eseguendo `sail exec laravel.test php -m`. Pubblica i Dockerfile e avvia la ricostruzione se manca un'estensione.

**3. Collisione di porte su localhost**
Eseguire due applicazioni Sail contemporaneamente senza modificare la variabile `APP_PORT` in una delle due configurazioni `.env`.
*Soluzione:* Regola `APP_PORT` e le porte `FORWARD_*` nel file `.env` del secondo progetto.

---

## 🧠 Domande di autovalutazione

1. **Perché i file di progetto in WSL2 non dovrebbero essere memorizzati nel percorso `/mnt/c/`?**
2. **Quale comando devi eseguire per ricostruire le tue immagini Sail dopo aver modificato un Dockerfile pubblicato?**
3. **Vero o Falso? Eseguire `composer install` nel terminale dell'host è sempre sicuro se hai installato la stessa versione di PHP di Sail.**
4. **Come disabiliti Xdebug in Sail quando non lo usi per evitare rallentamenti?**

<details>
<summary><b>Mostra risposte</b></summary>

1. L'accesso ai file attraverso il confine del file system di Windows (`/mnt/c/`) introduce pesanti layer di traduzione e virtualizzazione, che rallentano le operazioni sul disco e disturbano i listener delle modifiche ai file.
2. Esegui `sail build --no-cache` seguito da `sail up -d`.
3. **Falso.** Anche se la versione secondaria di PHP corrisponde, il tuo sistema host potrebbe non disporre delle librerie di compilazione necessarie o delle estensioni PHP presenti nel container. Esegui sempre i comandi tramite Sail.
4. Imposta `SAIL_XDEBUG_MODE=off` nel file `.env` e riavvia i container di Sail.
</details>
