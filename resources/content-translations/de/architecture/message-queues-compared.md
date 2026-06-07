---
title: "Message-Queues im Vergleich: Redis, RabbitMQ, Kafka | DevSense"
description: "Wie Sie einen Broker für asynchrone Aufgaben auswählen: Vergleich von In-Memory-Queues (Redis), AMQP-Brokern (RabbitMQ) und Commit-Logs (Kafka) basierend auf Sortierung, Skalierung, Persistenz und Betriebskosten."
published: 2026-04-12
faq:
  - question: "Was ist der Hauptunterschied zwischen einer Message-Queue (wie RabbitMQ) und einem Log-basierten Broker (wie Kafka)?"
    answer: "RabbitMQ verwendet das Modell 'Smart Broker, Dumb Consumer', bei dem der Broker den Nachrichtenstatus (Acks, gelesene Daten, Löschungen) verfolgt und Nachrichten sofort nach erfolgreichem Konsum löscht. Kafka verwendet das Modell 'Dumb Broker, Smart Consumer', bei dem Nachrichten an ein Write-Ahead-Log auf der Festplatte angehängt und für eine festgelegte Aufbewahrungsdauer (Retention Period) aufbewahrt werden. Consumer verfolgen ihre eigene Leseposition (Offset) selbst, was es ihnen ermöglicht, Nachrichten unabhängig voneinander erneut abzuspielen."
  - question: "Warum werden Message-Queues für asynchrone Task-Queues gegenüber Datenbanken bevorzugt?"
    answer: "Datenbanken sind nicht für Queuing-Muster ausgelegt. Das Abfragen (Polling) von Tabellen führt zu Sperrkonflikten (Lock Contention), hoher CPU-Auslastung und Tabellenfragmentierung (Bloat) durch schnelle Inserts und Deletes. Message-Queues speichern den Queue-Status im Speicher (oder in strukturierten, sequenziellen Festplattensegmenten) und unterstützen Push-basierte Consumer-Benachrichtigungen, was einen wesentlich höheren Durchsatz und eine geringere Ausführungslatenz ermöglicht."
  - question: "Wie erreicht Kafka im Vergleich zu herkömmlichen Brokern einen so hohen Durchsatz?"
    answer: "Kafka schreibt Nachrichten sequenziell in ein Festplatten-Log (unter Nutzung des OS-Page-Caches und Zero-Copy-Transfers auf Netzwerk-Sockets) und umgeht so den Overhead für die Serialisierung im Arbeitsspeicher. Es partitioniert Logs, um Schreib-/Lesearbeitslasten auf mehrere Broker zu parallelisieren, und fasst Nachrichten in Batches zusammen, um den Netzwerk- und I/O-Overhead zu reduzieren."
  - question: "Wann ist Redis eine gute Wahl für Messaging und wo liegen seine Grenzen?"
    answer: "Redis ist eine hervorragende Wahl mit geringer Latenz für einfache Queues (unter Verwendung von Listen oder Pub/Sub) oder strukturierte Streams, wenn Sie es bereits für das Caching nutzen. Es ist jedoch durch den Arbeitsspeicher des Servers begrenzt, da alle aktiven Daten im RAM liegen, und es fehlen erweiterte Routing-Funktionen wie Exchange-Routing, Dead-Lettering oder eine garantierte langfristige Festplattenpersistenz."
---

# Message-Queues im Vergleich: Redis, RabbitMQ und Apache Kafka

Die Entscheidung, wie Arbeit aus dem HTTP-Anfragepfad ausgelagert werden soll, ist ein standardmäßiger architektonischer Meilenstein. Die Wahl des **Wie** beim Routing dieser Arbeit kann jedoch zu einer werkzeugbedingten Lähmung führen. Sie müssen keinen Kafka-Cluster mit drei Knoten betreiben, um fünfzig Willkommens-E-Mails am Tag zu versenden, und Sie sollten auch kein Finanzbuch auf Redis Pub/Sub aufbauen. Der richtige Broker ist ein Gleichgewicht aus **Sortieranforderungen (Ordering)**, **Zustellungsgarantien (Delivery Guarantees)**, **Durchsatz** und **wie viel betrieblichen Aufwand (Ops Overhead) Ihr Team zu tragen bereit ist**.

**Verwandte Leitfäden:** [High-load event ingestion](high-load-event-ingestion) · [Databases under load](database-performance-and-scaling) · [Observability and monitoring](observability-monitoring-laravel)

## Inhalt

* [Warum nicht eine Datenbanktabelle verwenden?](#why-not-db)
* [Die drei Modelle des Messaging](#three-models)
* [Redis: Einfach, In-Memory, geringe Latenz](#redis)
* [RabbitMQ: Smarter Broker, flexibles Routing](#rabbitmq)
* [Apache Kafka: Das verteilte Commit-Log](#kafka)
* [Feature-Vergleichsmatrix](#matrix)
* [Zustellungsgarantien: At-least-once, At-most-once, Exactly-once](#delivery)
* [Sortierung, Consumer-Gruppen und Skalierung](#ordering)
* [Betriebskosten: Managed vs. Self-hosted](#ops-cost)
* [Häufige Fehler](#common-mistakes)
* [Checkliste](#checklist)
* [Selbsttest-Quiz](#self-test-quiz)

---

<a id="why-not-db"></a>
## Warum nicht eine Datenbanktabelle verwenden?

Es ist verlockend, Einträge in einer Tabelle `jobs` in PostgreSQL oder MySQL zu erfassen, einen Index auf `status` zu legen und diese jede Sekunde abzufragen (Polling).
Für Setups mit geringem Volumen **kann dies funktionieren**. Wenn das Volumen jedoch wächst, bricht die Datenbank unter den Queuing-Workloads zusammen:
* **Tabellenfragmentierung (Bloat)** – Datenbank-Engines mögen keine ständigen Schreib-und-Lösch-Muster. Tote Tupel (Dead Tuples) sammeln sich an, das Aufräumen (Vacuuming) verzögert sich und die Index-Performance bricht ein.
* **Sperrkonflikte beim Polling (Lock Contention)** – Mehrere Worker, die `SELECT ... FOR UPDATE LIMIT 1` ausführen, sperren dieselben Indexseiten für Schreibvorgänge, was den Durchsatz blockiert.
* **Push vs. Pull** – Datenbanken zwingen Sie zum Polling; Broker pushen Nachrichten sofort an wartende TCP-Verbindungen.

---

<a id="three-models"></a>
## Die drei Modelle des Messaging

1. **Flüchtige In-Memory-Queue (Redis Lists / Pub/Sub)** – Schnell, leichtgewichtig, Daten müssen in den RAM passen, einfache Muster.
2. **Klassische Message-Queue (RabbitMQ / ActiveMQ)** – Komplexes Routing, Queues verfolgen den Consumer-Status, Nachrichten werden nach der Bestätigung gelöscht.
3. **Log-basierter Event-Stream (Kafka / Redpanda)** – Append-only Festplatten-Log, Nachrichten bleiben nach dem Lesen bestehen, Consumer verfolgen ihre eigenen Positionen (Offsets) selbst.

---

<a id="redis"></a>
## Redis: Einfach, In-Memory, geringe Latenz

Redis ist ein In-Memory-Datenspeicher, der Queuing-Primitive unterstützt.

### Mechanismen
* **Listen (`LPUSH` / `BRPOP`)** – Eine grundlegende FIFO-Queue. Einfach, extrem geringe Latenz (Mikrosekunden), aber ohne erweitertes Routing.
* **Streams (Redis 5.0+)** – Hängt Einträge an ein Log an, unterstützt Consumer-Gruppen und Bestätigungen (`XACK`).
* **Pub/Sub** – Fire-and-Forget-Broadcasting. Wenn keine Consumer verbunden sind, wenn eine Nachricht veröffentlicht wird, geht die Nachricht **verloren**.

### Stärken
* Keine zusätzliche Infrastruktur erforderlich, wenn Sie Redis bereits für Caching oder Session-Speicherung nutzen.
* Extrem geringe Latenz.

### Schwächen
* **RAM-Grenzen** – Wenn Worker langsamer werden und die Queue wächst, kann der Arbeitsspeicher des Servers erschöpft werden.
* **Kompromisse bei der Persistenz** – Die AOF/RDB-Persistenz schreibt asynchron; ein plötzlicher Absturz kann zum Verlust der jüngsten Nachrichten führen.

---

<a id="rabbitmq"></a>
## RabbitMQ: Smarter Broker, flexibles Routing

RabbitMQ ist ein auf Erlang aufgebauter AMQP-Broker (Advanced Message Queuing Protocol).

### Kernkonzepte
* **Producer** veröffentlichen Nachrichten an **Exchanges**.
* **Exchanges** leiten Nachrichten an **Queues** weiter unter Verwendung von **Bindings** (Regeln basierend auf Routing-Keys, Headern oder Fanout).
* **Consumer** lesen aus **Queues**.

```
Producer ──> [ Exchange ] ──(Binding-Regeln)──> [ Queue ] ──> Consumer
```

### Stärken
* Vielfältige Routing-Muster (z. B. Topic-Match, Header-Routing).
* Bestätigungs-Semantik (Acknowledge / Negative-Acknowledge) pro Nachricht.
* Integrierte Dead Letter Exchanges (DLX) für fehlgeschlagene Wiederholungsversuche.

### Schwächen
* **Erlang-Laufzeitumgebung** erhöht den betrieblichen Aufwand für Konfiguration und Cluster-Management.
* Die Performance der Queue bricht ein, wenn Queues auf Millionen von Nachrichten anwachsen und auf die Festplatte ausgelagert werden müssen.

---

<a id="kafka"></a>
## Apache Kafka: Das verteilte Commit-Log

Kafka ist keine traditionelle Message-Queue. Es ist ein verteiltes, partitioniertes Append-only Transaktions-Log.

### Kernkonzepte
* **Topics** sind in **Partitionen** unterteilt.
* Nachrichten werden sequenziell an ein Festplatten-Log angehängt.
* Eine Nachricht wird durch ihren **Offset** (Indexposition) identifiziert.
* Consumer treten **Consumer-Gruppen** bei; Kafka weist Gruppenmitgliedern Partitionen zu.

```
Topic: Bestellungen
Partition 0: [Msg 0][Msg 1][Msg 2][Msg 3]  <-- Consumer A (Offset 3)
Partition 1: [Msg 0][Msg 1]                <-- Consumer B (Offset 1)
```

### Stärken
* **Hoher Durchsatz** – Schreibvorgänge erfolgen sequenziell auf die Festplatte; Lesevorgänge nutzen den Page-Cache des Betriebssystems und Zero-Copy-Netzwerkübertragungen.
* **Message Replay** – Da Nachrichten über ein festgelegtes Aufbewahrungsfenster auf der Festplatte verbleiben, können Sie Offsets zurücksetzen und die Historie erneut abspielen.
* **Skalierbarkeit** – Partitionierung ermöglicht die horizontale Skalierung über mehrere Server (Broker) hinweg.

### Schwächen
* Hohe Komplexität. Erfordert Apache ZooKeeper oder KRaft für die Koordination.
* Höhere Latenz im Vergleich zu Redis (Millisekunden vs. Mikrosekunden).
* Überdimensioniert für einfache Aufgabenverarbeitung.

---

<a id="matrix"></a>
## Feature-Vergleichsmatrix

| Feature | Redis (Lists) | RabbitMQ | Apache Kafka |
|---------|---------------|----------|--------------|
| **Primäres Modell** | In-Memory-Liste / Stream | Smarter Broker (AMQP) | Verteiltes Commit-Log |
| **Persistenz** | Flüchtig / Optionale Festplatte | Festplatte/Speicher (konfigurierbar) | Immer Festplatte (Commit-Log) |
| **Max. Durchsatz**| Hoch (begrenzt durch Single-Core-CPU/RAM) | Moderat (Zehntausende/Sek.) | Extrem (Millionen/Sek. via Partitionierung) |
| **Lebensdauer** | Gelöscht beim Auslesen (Pop) | Gelöscht nach Ack | Beibehalten nach Zeit-/Größenrichtlinie |
| **Routing-Flexibilität** | Keine (FIFO) | Hoch (Exchanges & Bindings) | Key-to-Partition Mapping |
| **Zustellreihenfolge** | Striktes FIFO | Strikt pro Queue (einzelner Consumer) | Strikt **innerhalb einer Partition** |

---

<a id="delivery"></a>
## Zustellungsgarantien

Kein Messaging-System kann eine „Exactly-once“-Zustellung über Netzwerkgrenzen hinweg garantieren, ohne eine verteilte Transaktionskoordination zu nutzen (was die Leistung stark beeinträchtigt).

* **At-most-once (Höchstens einmal)** – Nachrichten werden ohne Bestätigung gesendet. Bei einem Netzwerkfehler oder Absturz geht die Nachricht verloren.
* **At-least-once (Mindestens einmal)** – Consumer müssen die Verarbeitung bestätigen. Wenn ein Absturz während der Verarbeitung auftritt, stellt der Broker die Nachricht erneut zu. **Ihre Anwendungslogik muss idempotent sein**, um Duplikate zu handhaben.
* **Exactly-once (Genau einmal)** – Erfordert eine End-to-End-Koordination (wie Kafka-Transaktionen). Wird oft durch die Kombination von „At-least-once“-Zustellung mit Deduplizierung am Zielort simuliert.

> [!NOTE]
> **Die Idempotenz-Regel**
> Entwerfen Sie Ihre Consumer immer so, dass sie Duplikate verarbeiten können. Verwenden Sie eindeutige Transaktions-IDs oder Business-Keys, um zu verhindern, dass dasselbe Event zweimal verarbeitet wird.

---

<a id="ordering"></a>
## Sortierung, Consumer-Gruppen und Skalierung

* **RabbitMQ** garantiert die Reihenfolge innerhalb einer einzelnen Queue. Wenn Sie auf mehrere parallele Consumer skalieren, verarbeiten diese Nachrichten mit unterschiedlichen Geschwindigkeiten, was zu einer unkontrollierten Ausführungsreihenfolge auf Anwendungsebene führen kann.
* **Kafka** garantiert die Reihenfolge **nur innerhalb einer Partition**. Um die Reihenfolge für eine bestimmte Ressource beizubehalten (z. B. Aktualisierungen für Bestellung #105), müssen Sie alle zugehörigen Events über einen Partitionsschlüssel (z. B. `order_id`) an dieselbe Partition leiten.

---

<a id="ops-cost"></a>
## Betriebskosten: Managed vs. Self-hosted

* **Redis** ist einfach auszuführen und zu verwalten. Fast jeder Cloud-Anbieter bietet einen Managed Redis-Service an.
* **RabbitMQ** erfordert eine aktive Überwachung der Speichernutzung der Queues, des Festplattenspeichers und der Erlang-Cluster-Synchronisation. Managed Optionen (wie CloudAMQP oder AWS Amazon MQ) reduzieren diesen Aufwand.
* **Kafka** ist im Betrieb am komplexesten. Die Verwaltung von Partitions-Rebalances, Broker-Konfigurationen, Festplatten-Retention und KRaft-Konsens ist eine Vollzeitaufgabe. Verwenden Sie Managed Plattformen (wie Confluent Cloud, AWS MSK oder Aiven), es sei denn, Sie haben ein eigenes Plattform-Team.

---

<a id="common-mistakes"></a>
## Häufige Fehler

1. **Veröffentlichen von Events innerhalb von DB-Transaktionen**: Einstellen einer Nachricht in die Queue, bevor die Datenbanktransaktion committet ist. Wenn der Consumer schneller läuft als der DB-Commit, sucht er nach Datensätzen, die noch nicht existieren.
2. **Fehlende Prefetch-Limits in RabbitMQ**: Den Standardwert für das Prefetch-Limit nicht setzen. RabbitMQ pusht dann alle Nachrichten der Queue an den ersten verfügbaren Consumer, was diesen überlastet, während andere Worker untätig bleiben.
3. **Nutzung von Kafka ohne Partitionsschlüssel**: Veröffentlichen von Events in Kafka ohne Angabe eines Routing-Schlüssels, wodurch Nachrichten zufällig verteilt werden und die Reihenfolgegarantien für zusammengehörige Datensätze verloren gehen.
4. **Behandlung von Pub/Sub als persistente Queue**: Verwendung von Redis Pub/Sub für Hintergrundaufgaben in der Annahme, dass Nachrichten gepuffert werden, wenn Consumer offline sind.

---

<a id="checklist"></a>
## Checkliste

1. **Prüfen Sie Ihr Volumen:** Unter 10.000 Nachrichten/Sek.? Überspringen Sie Kafka; starten Sie mit Redis oder RabbitMQ.
2. **Definieren Sie die Persistenz:** Können Sie es sich leisten, eine Nachricht bei einem Serverabsturz zu verlieren? Wenn nicht, vermeiden Sie reine In-Memory Redis-Listen.
3. **Prüfen Sie die Routing-Anforderungen:** Benötigen Sie komplexe Routing-Muster (z. B. Topic-Routing)? Wählen Sie RabbitMQ.
4. **Ermitteln Sie die Grenzen der Reihenfolge:** Benötigen Sie eine strikte Reihenfolge pro Entität über parallele Worker hinweg? Wählen Sie Kafka mit Partitionsschlüsseln.
5. **Berücksichtigen Sie die Betriebskosten:** Hat Ihr Team die Bandbreite, um KRaft/ZooKeeper zu warten? Wenn nicht, wählen Sie einen Managed Service.

---

## Zusammenfassung

Das richtige Werkzeug passt sich der Form Ihrer Daten an. Verwenden Sie **Redis** für schnelle Task-Queues, **RabbitMQ** bei komplexer Routing-Logik und **Kafka**, wenn Sie ein Commit-Log mit hohem Durchsatz benötigen.

---

<a id="self-test-quiz"></a>
## Selbsttest-Quiz

### Frage 1: Was passiert mit einer Nachricht in Redis Pub/Sub, wenn zum Zeitpunkt der Veröffentlichung keine aktiven Abonnenten verbunden sind?
- A) Sie wird im Speicher in eine Warteschlange eingereiht, bis sich ein Abonnent verbindet.
- B) Sie wird verworfen und geht dauerhaft verloren.
- C) Sie wird in die RDB-Snapshot-Datei geschrieben.

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort: B**
Redis Pub/Sub ist ein Fire-and-Forget-Broadcasting-Mechanismus. Es puffert keine Nachrichten für getrennte Clients; sie gehen sofort verloren, wenn keine Abonnenten aktiv sind.
</details>

---

### Frage 2: Wie stellen Sie in Apache Kafka sicher, dass alle Statusaktualisierungen für einen bestimmten Benutzer in der genauen Reihenfolge ihres Auftretens verarbeitet werden?
- A) Betreiben Sie nur einen einzigen Broker.
- B) Verwenden Sie die `user_id` als Partitionsschlüssel, sodass alle Events für diesen Benutzer in derselben Partition landen.
- C) Setzen Sie die Log-Aufbewahrung (Retention) auf unendlich.

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort: B**
Kafka garantiert die Nachrichtenreihenfolge nur innerhalb einer einzelnen Partition. Indem Sie die `user_id` als Partitionsschlüssel verwenden, leitet Kafka alle Events für diesen Benutzer an dieselbe Partition weiter und behält so die Ausführungsreihenfolge bei.
</details>
