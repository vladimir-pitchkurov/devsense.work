---
title: "Message queue a confronto: Redis, RabbitMQ, Kafka | DevSense"
description: "Come scegliere un broker per il lavoro asincrono: confronto tra code in memoria (Redis), broker AMQP (RabbitMQ) e commit log (Kafka) in base a ordinamento, scalabilità, durabilità e costi operativi."
published: 2026-04-12
faq:
  - question: "Qual è la differenza principale tra una coda di messaggi (come RabbitMQ) e un broker basato su log (come Kafka)?"
    answer: "RabbitMQ utilizza un modello 'smart broker, dumb consumer', in cui il broker tiene traccia dello stato dei messaggi (ack, letture, eliminazioni) e li cancella immediatamente dopo che sono stati consumati con successo. Kafka utilizza un modello 'dumb broker, smart consumer', in cui i messaggi vengono aggiunti a un log sequenziale (write-ahead log) su disco e conservati per un periodo di retention definito. I consumatori tengono traccia della propria posizione di lettura (offset), consentendo loro di riprodurre i messaggi in modo indipendente."
  - question: "Perché le code di messaggi sono preferite ai database per le code di task asincroni?"
    answer: "I database non sono progettati per pattern di accodamento. Il polling delle tabelle crea contesa sui lock, utilizzo elevato della CPU e frammentazione delle tabelle (bloat) a causa di rapide inserimenti ed eliminazioni. Le code di messaggi memorizzano lo stato in memoria (o in segmenti di disco sequenziali strutturati) e supportano avvisi push ai consumatori, garantendo un throughput molto più elevato e una minore latenza di esecuzione."
  - question: "In che modo Kafka ottiene un throughput elevato rispetto ai broker tradizionali?"
    answer: "Kafka scrive i messaggi in modo sequenziale su un log su disco (sfruttando il page cache del sistema operativo e il trasferimento zero-copy sui socket di rete), bypassando l'overhead di serializzazione in memoria. Partiziona i log per parallelizzare i carichi di lavoro di scrittura/lettura su più broker e raggruppa i messaggi in batch per ridurre l'overhead di rete e di I/O."
  - question: "Quando Redis è una buona scelta per la messaggistica e quali sono i suoi limiti?"
    answer: "Redis è una scelta eccellente a bassa latenza per code semplici (utilizzando Liste o Pub/Sub) o stream strutturati (Streams) se lo si utilizza già per il caching. Tuttavia, è limitato dalla RAM del server, poiché tutti i dati attivi sono memorizzati in memoria, e manca di funzionalità di instradamento avanzate come l'instradamento tramite exchange, il dead-lettering o la durabilità del disco garantita a lungo termine."
---

# Message queue a confronto: Redis, RabbitMQ e Apache Kafka

Decidere come spostare il lavoro fuori dal percorso della richiesta HTTP è una pietra miliare dell'architettura standard. Ma scegliere **come** instradare quel lavoro può portare alla paralisi da strumenti. Non è necessario eseguire un cluster Kafka a tre nodi per inviare cinquanta e-mail di benvenuto al giorno, né si dovrebbe creare un registro finanziario su Redis Pub/Sub. Il broker giusto rappresenta un equilibrio tra **requisiti di ordinamento**, **garanzie di consegna**, **throughput** e **la quantità di overhead operativo (Ops) che il team è disposto a sostenere**.

**Guide correlate:** [High-load event ingestion](high-load-event-ingestion) · [Databases under load](database-performance-and-scaling) · [Observability and monitoring](observability-monitoring-laravel)

## Indice

* [Perché non usare una tabella di database?](#why-not-db)
* [I tre modelli di messaggistica](#three-models)
* [Redis: semplice, in-memory, bassa latenza](#redis)
* [RabbitMQ: smart broker, instradamento flessibile](#rabbitmq)
* [Apache Kafka: il commit log distribuito](#kafka)
* [Matrice di confronto delle funzionalità](#matrix)
* [Garanzie di consegna: At-least-once, At-most-once, Exactly-once](#delivery)
* [Ordinamento, gruppi di consumatori e scalabilità](#ordering)
* [Costi operativi: Gestito vs Autogestito](#ops-cost)
* [Errori comuni](#common-mistakes)
* [Checklist](#checklist)
* [Quiz di autovalutazione](#self-test-quiz)

---

<a id="why-not-db"></a>
## Perché non usare una tabella di database?

È allettante scrivere i `jobs` su PostgreSQL o MySQL, indicizzare lo `status` ed effettuare il polling ogni secondo.
Per configurazioni a basso volume, questo **può funzionare**. Tuttavia, all'aumentare del volume, il database cede sotto i carichi di lavoro di accodamento:
* **Frammentazione della tabella (Bloat)** — i motori di database non amano i pattern costanti di scrittura e successiva eliminazione. Le tuple morte si accumulano, il vacuuming rallenta e le prestazioni dell'indice degradano.
* **Contesa sui lock da polling** — più worker che eseguono `SELECT ... FOR UPDATE LIMIT 1` bloccano in scrittura le stesse pagine dell'indice, creando un collo di bottiglia per il throughput.
* **Push vs Pull** — i database costringono a effettuare il polling (pull); i broker inviano (push) i messaggi istantaneamente alle connessioni TCP in attesa.

---

<a id="three-models"></a>
## I tre modelli di messaggistica

1. **Coda temporanea in memoria (Redis Lists / Pub/Sub)** — Veloce, leggera, i dati devono risiedere nella RAM, pattern semplici.
2. **Coda di messaggi classica (RabbitMQ / ActiveMQ)** — Instradamento complesso, le code tengono traccia dello stato del consumatore, i messaggi vengono eliminati una volta confermati (ack).
3. **Stream di eventi basato su log (Kafka / Redpanda)** — Commit log distribuito append-only su disco, i messaggi rimangono dopo la lettura, i consumatori tengono traccia delle proprie posizioni (offset).

---

<a id="redis"></a>
## Redis: semplice, in-memory, bassa latenza

Redis è un archivio dati in memoria che supporta primitive di accodamento.

### Meccanismi
* **Liste (`LPUSH` / `BRPOP`)** — Una coda FIFO di base. Semplice, latenza estremamente bassa (microsecondi), ma priva di routing avanzato.
* **Streams (Redis 5.0+)** — Aggiunge voci a un log, supporta gruppi di consumatori e conferme (`XACK`).
* **Pub/Sub** — Trasmissione fire-and-forget. Se nessun consumatore è connesso al momento della pubblicazione, il messaggio viene **perso**.

### Punti di forza
* Zero infrastruttura aggiuntiva se usi già Redis per la cache o la memorizzazione delle sessioni.
* Latenza estremamente bassa.

### Punti deboli
* **Limiti di RAM** — Se i worker rallentano e la coda cresce, rischi di esaurire la memoria del server.
* **Compromessi sulla durabilità** — La persistenza AOF/RDB scrive in modo asincrono; un crash improvviso può far perdere i messaggi recenti.

---

<a id="rabbitmq"></a>
## RabbitMQ: smart broker, instradamento flessibile

RabbitMQ è un broker AMQP (Advanced Message Queuing Protocol) scritto in Erlang.

### Concetti chiave
* I **Produttori** pubblicano messaggi sugli **Exchange**.
* Gli **Exchange** instradano i messaggi alle **Code (Queue)** utilizzando i **Binding** (regole basate su chiavi di routing, header o fanout).
* I **Consumatori** prelevano dalle **Code**.

```
Produttore ──> [ Exchange ] ──(Regole di binding)──> [ Coda ] ──> Consumatore
```

### Punti di forza
* Pattern di instradamento ricchi (es. corrispondenza di topic, routing per header).
* Semantica di conferma (Ack / Negative-Ack) per singolo messaggio.
* Exchange per messaggi non recapitati (DLX - Dead Letter Exchange) preconfigurati per i tentativi falliti.

### Punti deboli
* **Il runtime Erlang** aumenta l'overhead operativo per la configurazione e la gestione del cluster.
* Le prestazioni delle code calano se contengono milioni di messaggi e devono scaricarli su disco.

---

<a id="kafka"></a>
## Apache Kafka: il commit log distribuito

Kafka non è una coda di messaggi tradizionale. È un log delle transazioni distribuito, partizionato e append-only.

### Concetti chiave
* I **Topic** sono divisi in **Partizioni**.
* I messaggi vengono aggiunti sequenzialmente su disco.
* Un messaggio è identificato dal suo **Offset** (posizione dell'indice).
* I consumatori si uniscono a **Gruppi di consumatori (Consumer Group)**; Kafka assegna le partizioni ai membri del gruppo.

```
Topic: Ordini
Partizione 0: [Msg 0][Msg 1][Msg 2][Msg 3]  <-- Consumatore A (Offset 3)
Partizione 1: [Msg 0][Msg 1]                <-- Consumatore B (Offset 1)
```

### Punti di forza
* **Throughput elevato** — Le scritture sono sequenziali su disco; le letture sfruttano la page cache del sistema operativo e il trasferimento di rete zero-copy.
* **Message Replay** — Poiché i messaggi rimangono su disco per un periodo di retention stabilito, puoi reimpostare gli offset e riprodurre lo storico.
* **Scalabilità** — Il partizionamento consente lo scaling orizzontale su più server (broker).

### Punti deboli
* Elevata complessità. Richiede Apache ZooKeeper o KRaft per il coordinamento.
* Latenza più elevata rispetto a Redis (millisecondi vs microsecondi).
* Eccessivo per la semplice elaborazione dei task.

---

<a id="matrix"></a>
## Matrice di confronto delle funzionalità

| Funzionalità | Redis (Lists) | RabbitMQ | Apache Kafka |
|--------------|---------------|----------|--------------|
| **Modello principale** | Lista / Stream in memoria | Smart Broker (AMQP) | Commit log distribuito |
| **Persistenza** | Volatile / Disco opzionale | Disco/Memoria (configurabile) | Sempre su disco (Commit Log) |
| **Throughput max**| Elevato (limitato da CPU single-core/RAM) | Moderato (decine di migliaia/s) | Estremo (milioni/s tramite partizioni) |
| **Ciclo di vita del messaggio** | Eliminato al pop | Eliminato dopo l'Ack | Conservato in base a tempo/dimensione |
| **Flessibilità di routing** | Nessuna (FIFO) | Elevata (Exchange & Binding) | Mappatura chiave-partizione |
| **Garanzia sull'ordine** | FIFO rigido | Rigido per coda (singolo consumatore) | Rigido **all'interno di una partizione** |

---

<a id="delivery"></a>
## Garanzie di consegna

Nessun sistema di messaggistica può garantire la consegna \"esattamente una volta\" (exactly-once) attraverso la rete senza coordinamento delle transazioni distribuite (che degrada le prestazioni).

* **Al massimo una volta (At-most-once)** — I messaggi vengono inviati senza conferma. In caso di interruzione di rete o crash, il messaggio viene perso.
* **Almeno una volta (At-least-once)** — I consumatori devono confermare l'elaborazione. In caso di crash a metà processo, il broker invia nuovamente il messaggio. **La logica dell'applicazione deve essere idempotente** per gestire i duplicati.
* **Esattamente una volta (Exactly-once)** — Richiede coordinamento end-to-end (come le transazioni di Kafka). Spesso simulato combinando la consegna \"almeno una volta\" con la deduplicazione a destinazione.

> [!NOTE]
> **Regola di idempotenza**
> Progetta sempre i tuoi consumatori in modo che possano gestire i duplicati. Utilizza ID di transazione o chiavi di business univoci per evitare di elaborare lo stesso evento due volte.

---

<a id="ordering"></a>
## Ordinamento, gruppi di consumatori e scalabilità

* **RabbitMQ** garantisce l'ordine all'interno di una singola coda. Se scali su più consumatori paralleli, questi elaborano i messaggi a velocità diverse, il che può comportare un'esecuzione fuori ordine a livello applicativo.
* **Kafka** garantisce l'ordine **solo all'interno di una partizione**. Per mantenere l'ordine per una risorsa (es. aggiornamenti per l'Ordine #105), devi instradare tutti i suoi eventi alla stessa partizione usando una chiave di partizione (es. `order_id`).

---

<a id="ops-cost"></a>
## Costi operativi: Gestito vs Autogestito

* **Redis** è facile da eseguire e gestire. Quasi tutti i cloud provider offrono un servizio Redis gestito.
* **RabbitMQ** richiede un monitoraggio attivo dell'uso della memoria della coda, dello spazio su disco e della sincronizzazione del cluster Erlang. Le opzioni gestite (come CloudAMQP o AWS Amazon MQ) riducono questo carico.
* **Kafka** è il più complesso da gestire. Amministrare i ribilanciamenti delle partizioni, la configurazione dei broker, la retention su disco e il consenso KRaft richiede un ruolo operativo a tempo pieno. Utilizza piattaforme gestite (come Confluent Cloud, AWS MSK o Aiven) a meno che tu non disponga di un team infrastrutturale dedicato.

---

<a id="common-mistakes"></a>
## Errori comuni

1. **Pubblicare eventi all'interno di transazioni DB**: mettere in coda un messaggio prima di aver eseguito il commit della transazione del database. Se il consumatore viene eseguito più velocemente del commit DB, cercherà record che non esistono ancora.
2. **Mancanza di limiti di prefetch in RabbitMQ**: lasciare non configurato il limite di prefetch predefinito. RabbitMQ invierà tutti i messaggi della coda al primo consumatore disponibile, sovraccaricandolo mentre gli altri worker rimangono inattivi.
3. **Usare Kafka senza chiavi di partizione**: pubblicare eventi su Kafka senza specificare una chiave di instradamento, il che distribuisce i messaggi in modo casuale compromettendo le garanzie sull'ordine per i record correlati.
4. **Trattare il Pub/Sub come una coda persistente**: utilizzare Redis Pub/Sub per i task in background, presumendo che i messaggi vengano memorizzati nel buffer quando i consumatori sono offline.

---

<a id="checklist"></a>
## Checklist

1. **Verifica il tuo volume:** Meno di 10.000 messaggi/sec? Salta Kafka; inizia con Redis o RabbitMQ.
2. **Definisci la durabilità:** Puoi permetterti di perdere un messaggio in caso di crash del server? In caso contrario, evita le liste Redis puramente in memoria.
3. **Verifica i requisiti di routing:** Hai bisogno di pattern di instradamento complessi (es. routing per topic)? Scegli RabbitMQ.
4. **Stabilisci i confini sull'ordine:** Hai bisogno di un ordine rigoroso per entità tra worker paralleli? Scegli Kafka con chiavi di partizione.
5. **Considera i costi operativi:** Il tuo team ha la banda necessaria per gestire KRaft/ZooKeeper? In caso contrario, scegli un servizio gestito.

---

## Riepilogo

Lo strumento giusto corrisponde alla forma dei tuoi dati. Utilizza **Redis** per code di task veloci, **RabbitMQ** quando la logica di instradamento è complessa e **Kafka** quando hai bisogno di un commit log ad alto throughput.

---

<a id="self-test-quiz"></a>
## Quiz di autovalutazione

### Domanda 1: Cosa succede a un messaggio in Redis Pub/Sub se al momento della pubblicazione non sono connessi iscritti attivi?
- A) Viene messo in coda in memoria finché non si connette un iscritto.
- B) Viene scartato e perso permanentemente.
- C) Viene scritto nel file di snapshot RDB.

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta: B**
Redis Pub/Sub è un meccanismo di trasmissione fire-and-forget. Non memorizza i messaggi nel buffer per i client disconnessi; vengono persi immediatamente se nessun iscritto è attivo.
</details>

---

### Domanda 2: In Apache Kafka, come ci si assicura che tutti gli aggiornamenti di stato per un utente specifico siano elaborati nell'ordine esatto in cui si sono verificati?
- A) Esegui un solo broker.
- B) Utilizza l'`user_id` come chiave di partizione in modo che tutti gli eventi per quell'utente finiscano nella stessa partizione.
- C) Imposta la retention del log su infinito.

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta: B**
Kafka garantisce l'ordinamento dei messaggi solo all'interno di una singola partizione. Utilizzando l'`user_id` come chiave di partizione, Kafka instrada tutti gli eventi per quell'utente alla stessa partizione, preservando l'ordine di esecuzione.
</details>
