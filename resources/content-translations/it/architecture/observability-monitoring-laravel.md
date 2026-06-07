---
title: "Osservabilità: log, metriche e salute per Laravel e microservizi | DevSense"
description: "Come monitorare lo stato dell'applicazione tra ambienti e carichi: logging strutturato, metriche, tracce, ID di correlazione tra i servizi e uno spettro pratico di strumenti che va dal classico syslog a Prometheus, Loki, OpenTelemetry e APM SaaS."
published: 2026-04-15
faq:
  - question: "Qual è la differenza tra livello di log (log level) e contesto di log (log context)?"
    answer: "Il livello di log (ad esempio, DEBUG, INFO, WARNING, ERROR) categorizza la gravità di un messaggio, consentendo di applicare filtri in produzione. Il contesto di log rappresenta metadati strutturati (array contenenti ID utente, ID richiesta, tempi di esecuzione) associati alla voce di log, abilitando l'indicizzazione e la correlazione automatizzate senza dover analizzare testo non strutturato."
  - question: "Perché le etichette (label) ad alta cardinalità sono dannose per i sistemi di metriche come Prometheus?"
    answer: "Prometheus memorizza le metriche come voci di un database di serie temporali. Ogni combinazione univoca di etichette crea una nuova serie temporale. La memorizzazione di parametri ad alta cardinalità (come gli ID utente o gli URL dinamici completi) come etichette causa una 'esplosione delle serie', che esaurisce la memoria di Prometheus e manda in crash il server delle metriche."
  - question: "In che modo il tracciamento distribuito propaga la correlazione tra i microservizi?"
    answer: "Il tracciamento distribuito utilizza header HTTP (come `X-Request-Id` o `traceparent` di W3C) per trasmettere un ID di traccia univoco e un ID di span lungo la catena delle richieste. Ogni microservizio nel flusso delle richieste estrae questo header, lo include nei propri log e lo inietta nelle richieste client HTTP in uscita o negli header dei messaggi in coda."
  - question: "Perché Laravel Telescope dovrebbe essere disabilitato in produzione?"
    answer: "Laravel Telescope acquisisce informazioni dettagliate sul contesto delle richieste, query di database e record di log, memorizzandoli nel database. In un ambiente di produzione ad alto traffico, questo overhead di query e memorizzazione compromette il throughput dell'applicazione, aumenta il carico del database e può consumare uno spazio di memorizzazione eccessivo."
---

# Osservabilità: log, metriche e salute nei monoliti Laravel e nei microservizi

“Funziona sulla mia macchina” non è una strategia di monitoraggio. In produzione, i guasti avvengono in **modi specifici**: il disco si riempie, la latenza delle code subisce picchi improvvisi, una dipendenza va in timeout o una replica serve letture obsolete. Una buona osservabilità consente di rispondere a domande quali **cosa è cambiato**, **per chi** e **quale passaggio (hop) è fallito** — senza doversi connettere via SSH a ogni macchina. Gli stessi concetti si applicano sia che si esegua un **monolite Laravel** sia che si gestisca una **flotta di servizi**; solo l'**infrastruttura** e la **cardinalità** diventano più complesse man mano che si dividono i confini.

**Guide correlate:** [PHP database connection pooling](php-database-connection-pooling) · [Databases under load](database-performance-and-scaling) · [API gateway & messaging](../microservices/api-gateway)

## Indice

* [Tre pilastri: log, metriche, tracce](#pillars)
* [Cosa differisce in base all'ambiente](#environments)
* [Monolite Laravel: livelli pratici](#monolith)
* [Microservizi: correlazione e tracciamento](#microservices)
* [Spettro degli strumenti: dal classico al moderno](#tools)
* [Sotto carico: campionamento, cardinalità, costi](#load)
* [Allarmi che gli umani rispetteranno](#alerting)
* [Errori comuni](#common-mistakes)
* [Checklist](#checklist)
* [Quiz di autovalutazione](#self-test-quiz)

---

<a id="pillars"></a>
## Tre pilastri: log, metriche, tracce

| Pilastro | Risposte | Errori tipici |
|----------|----------|---------------|
| **Log** | *Quale storia è accaduta?* (errori, audit, contesto di debug) | Frasi singole non strutturate che non possono essere interrogate; registrazione di segreti; valanghe di log **INFO** in produzione |
| **Metriche** | *Quanto, quanto velocemente, rispetto a ieri?* (tassi, istogrammi, saturazione) | Etichette (label) ad **alta cardinalità** (URL completo, ID utente su ogni serie); grafici che nessuno guarda |
| **Tracce (Traces)** | *Quale span nella catena è stato lento?* | Mancanza di **propagazione del contesto**, per cui il microservizio B non sa che fa parte della richiesta A |

I pilastri si rafforzano a vicenda: un **picco nel tasso di errore** (metrica) rimanda a **log rappresentativi** e a una **traccia** di un checkout lento. Nessun pilier da solo sostituisce gli altri.

---

<a id="environments"></a>
## Cosa differisce in base all'ambiente

* **Locale / dev** — massimizzare la **velocità dello sviluppatore**: `tail`, interfacce grafiche di debug in stile Telescope, log dettagliati, breakpoint. **Non** inviare questa verbosità in produzione così com'è.
* **Staging / pre-prod** — riprodurre collettori di log e dashboard **simili a quelli di produzione** dove accessibile; intercettare errori della classe “funziona finché non abilitiamo l'invio di JSON a Loki”.
* **Produzione** — ottimizzare per il **segnale**, il **costo di conservazione (retention)** e **impostazioni predefinite sicure**: log strutturati, rimozione dei dati sensibili, campionamento (sampling) sui percorsi di debug, **health check** che riflettano le reali dipendenze.

> [!NOTE]
> **Principio di compatibilità**
> L'obiettivo non è avere strumenti identici ovunque, bensì **contratti compatibili** (stessi nomi dei campi di correlazione, stessi nomi delle metriche) in modo che la gestione degli incidenti non richieda di imparare di nuovo come funziona il sistema.

---

<a id="monolith"></a>
## Monolite Laravel: livelli pratici

### Registrazione dell'applicazione (Logging)

* Utilizza i canali in **`config/logging.php`**: `stack`, `daily`, `syslog`, oppure un formattatore **JSON** su stdout per consentire la raccolta da parte degli host dei container.
* Preferisci **campi strutturati** (array `context`) rispetto all'analisi successiva di frasi discorsive.
* Associa l'**ID richiesta**, l'**ID utente** (se consentito dalle policy) e l'**ID del job in coda** — qualsiasi elemento utile a risalire da una riga di log al resto della storia.

Esempio di contesto strutturato:
```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily'],
        'ignore_exceptions' => false,
    ],
    // ...
],
```

E utilizzo del codice:
```php
// app/Http/Controllers/OrderController.php
Log::info('Order processed successfully', [
    'order_id' => $order->id,
    'user_id' => auth()->id(),
    'execution_time_ms' => $timeMs,
]);
```

### Ciclo di vie delle richieste

* Middleware per l'**ID di correlazione**: accetta un header `X-Request-Id` in entrata o ne genera uno; restituiscilo nelle risposte; passalo ai job e ai client HTTP.
* Il metodo **`Log::withContext()`** di Laravel consente di mantenere il contesto sulla richiesta senza dover passare parametri ovunque.

### Code e pianificazioni (Queues & Schedules)

* **Horizon** (Redis) mostra **la profondità delle code, il throughput, i job falliti** — consideralo come un **monitoraggio di prima classe**, non come un'interfaccia opzionale.
* Attività pianificate: registra **inizio, fine e durata**; allerta in caso di **esecuzioni saltate** (monitoraggio dei cron o heartbeat esterno).

### Introspezione profonda (non in produzione)

* **Telescope** è prezioso in **locale/staging**; mantienilo **disattivato** in produzione a meno che tu non disponga di filtri severi (IP, autenticazione, campionamento) e ne accetti l'overhead.
* **Laravel Pulse** mostra **query lente, eccezioni, code** in una dashboard — monitora comunque il **campionamento e la conservazione** sulle app ad alto traffico.

### Salute e prontezza (Health & Readiness)

* **`/up` (Health)** in Laravel 11+: distingue l'**esistenza in vita (liveness)** (“il processo è in esecuzione”) dalla **prontezza (readiness)** (“può comunicare con il DB e la cache”). I bilanciatori di carico e le sonde Kubernetes tengono molto a questa distinzione.

### Errori come prodotto

* **Sentry**, **Flare**, **Bugsnag** — stack trace raggruppati, tracciamento dei rilasci, breadcrumb. Completano i log; non sostituiscono le **metriche di saturazione**.

---

<a id="microservices"></a>
## Microservizi: correlazione e tracciamento

Quando una chiamata HTTP segue il percorso **gateway → servizio A → servizio B → broker → worker**, un semplice log di accesso per ciascun servizio è **insufficiente**.

### ID di correlazione

* Propaga un ID stabile su ogni chiamata in uscita (`X-Request-Id` o il formato **`traceparent` di W3C** insieme al tuo ID interno).
* Registralo in **ogni** servizio all'ingresso; includilo nei payload **asincroni** (payload del job, header dei messaggi).

### Tracciamento distribuito

* **OpenTelemetry** è lo standard emergente **indipendente dai vendor** per emettere tracce; i collector le inviano a **Jaeger**, **Tempo**, **Zipkin** o a backend SaaS.
* Gli ecosistemi PHP variano in termini di maturità — verifica l'**instrumentazione** per il tuo client HTTP, driver DB e libreria delle code. Un tracciamento parziale è comunque preferibile a nessun tracciamento.

### Confini dei servizi

* Standardizza le policy di **timeout, tentativi (retry) e idempotenza**; l'osservabilità mostrerà **tentativi a cascata** se ogni livello tenta nuovamente in modo cieco.

---

<a id="tools"></a>
## Spettro degli strumenti: dal classico al moderno

### Era dell'host e della rete

* **syslog**, **rsyslog**, **logrotate** — centralizzazione di file di testo semplici; ancora validi come fase di **trasporto**.
* **Nagios**, **Icinga**, **Zabbix** — controlli degli host, ping, disco, semplici sonde di servizio. Meno orientati alle **tracce delle applicazioni**, ma ancora comuni per le **metriche infrastrutturali di base**.

### Aggregazione dei log

* **ELK / Elastic Stack** (Elasticsearch, Logstash/Beats, Kibana) — ricerca potente; gestisci l'infrastruttura o acquista capacità in modo consapevole.
* **Graylog**, **Splunk** (enterprise) — spazio di soluzioni analogo.

### Metriche e dashboard

* Modello di raccolta di **Prometheus** + dashboard **Grafana** — standard de facto per **Kubernetes** e molti ambienti bare-metal; **Alertmanager** per il routing.
* **VictoriaMetrics**, **Mimir**, **Thanos** — varianti a lungo termine o ad alta disponibilità basate sui protocolli di Prometheus.

### Log “come metriche”

* **Grafana Loki** — archiviazione dei log basata su etichette che si sposa naturalmente con Grafana; spesso più economica rispetto all'indicizzazione di ogni campo effettuata dai motori di ricerca.

### Cloud-native

* **AWS CloudWatch**, **Google Cloud Logging/Monitoring**, **Azure Monitor** — stretta integrazione se utilizzi già tali servizi.

### APM SaaS all-in-one

* **Datadog**, **New Relic**, **Honeycomb** — log, metriche, APM, RUM; **rapida generazione di valore**, **tariffe basate sul volume** — attenzione alla cardinalità.

### Errori e APM per PHP

* **Sentry** (errori + prestazioni), **Scout**, **Tideways** (profilazione orientata a PHP) — forte adozione all'interno della community di Laravel.

### Ondata di standardizzazione

* **OpenTelemetry (OTel)** — SDK/esportatori unificati; il **collector** può distribuire i flussi a molti backend. **L'adozione è in crescita** proprio per evitare il vendor lock-in per ciascun segnale.

---

<a id="load"></a>
## Sotto carico: campionamento, cardinalità, costi

* Il **volume dei log** cresce linearmente con il traffico; l'uso del formato **JSON per richiesta** in modalità `debug` può appesantire la CPU dell'applicazione. Utilizza i **livelli (level)** e il **campionamento del debug** per i percorsi ad alto traffico.
* **Etichette di Prometheus**: non utilizzare mai valori **illimitati** (URL grezzi contenenti ID, email) come nomi di etichette o valori ad alta cardinalità — **le serie temporali esplodono**.
* **Campionamento delle tracce**: conserva il **100%** per gli errori o le richieste lente; campiona il resto — i backend e il tuo budget ti ringrazieranno.
* **Conservazione (Retention)**: definisci i dati **caldi** (giorni) rispetto a quelli **freddi** (object storage) rispetto all'**eliminazione**; la conformità potrebbe richiedere una conservazione più lunga per i log di **audit**, separatamente dai log di **debug**.

---

<a id="alerting"></a>
## Allarmi che gli umani rispetteranno

Invia allarmi per guasti **visibili all'utente** o **imminenti**: consumo del budget di errore (**SLO burn**), aumento del **tasso di errore**, tempo di attesa in coda **p95**, limite del **disco**, scadenza dei **certificati**.

Evita di notificare per condizioni **note come rumorose**, a meno che non vi si associno **procedure operative (runbook)**. Un valore di “CPU > 80%” per cinque minuti spesso **non** costituisce un incidente; una riduzione di 10 volte del **tasso di successo dei pagamenti** lo è.

---

<a id="common-mistakes"></a>
## Errori comuni

1. **Etichette di metriche ad alta cardinalità**: aggiungere identificatori univoci come `user_id` o parametri di `url` generati dinamici come etichette Prometheus, esaurendo la memoria del server.
2. **Perdita di credenziali nei log**: registrare `$request->all()` sugli endpoint di accesso o di reimpostazione della password, esponendo password, token e dati personali (PII).
3. **Mantenere Telescope abilitato in produzione**: memorizzazione illimitata nel database degli span dell'applicazione, rallentando le query e sovraccaricando lo storage principale.
4. **Mancanza di timeout sulle richieste esterne**: chiamare API di terze parti senza specificare un timeout, bloccando i processi HTTP ed esaurendo i processi figli FPM.

---

<a id="checklist"></a>
## Checklist

1. **Log strutturati** su stdout o verso un logger; **un solo** ID di correlazione tra le attività sincrone e asincrone.
2. **Segnali d'oro (golden signals)** per ciascun servizio: latenza, traffico, errori, saturazione (più la **profondità delle code** per i worker Laravel).
3. Endpoint di **salute** che testano dipendenze **reali**; separa la **liveness** dalla **readiness** dove richiesto dagli orchestratori.
4. **Rilevatore di errori** in produzione; strumenti **in stile Telescope** limitati agli ambienti di non-produzione.
5. Verifica dei **costi e della cardinalità** prima di abilitare l'opzione “registra tutto” o “etichetta tutto”.

---

## Riepilogo

L'osservabilità fa **parte del prodotto**: lo stesso codice Laravel che serve gli utenti deve dirti — **con prove concrete** — quando sta per fallire e **dove** guardare per primo.

---

<a id="self-test-quiz"></a>
## Quiz di autovalutazione

### Domanda 1: Qual è il problema principale nell'aggiunta degli indirizzi email degli utenti come etichette in un database di metriche Prometheus?
- A) Prometheus non supporta i valori di tipo stringa.
- B) Causa un'esplosione della cardinalità delle metriche, mandando in crash il database delle metriche.
- C) Viola le policy standard sulla formattazione degli URL.

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta: B**
Ciascuna etichetta email univoca crea una nuova serie temporale. Se si hanno migliaia di utenti, Prometheus esaurirà rapidamente la memoria a causa dell'esplosione della cardinalità.
</details>

---

### Domanda 2: In un ambiente Kubernetes o con bilanciamento del carico, qual è lo scopo del controllo di prontezza (readiness check)?
- A) Verificare se il processo del server è stato avviato.
- B) Verificare se l'applicazione è pronta a ricevere traffico (es. la connessione al database è attiva).
- C) Tracciare le query lente.

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta: B**
Una sonda di prontezza verifica se il container è pronto a gestire il traffico web in entrata. Se fallisce, il bilanciatore smette di instradare le richieste verso quel nodo.
</details>
