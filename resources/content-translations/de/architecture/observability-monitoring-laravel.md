---
title: "Observability: Logs, Metriken und Health für Laravel und Mikroschnittstellen | DevSense"
description: "Wie Sie den Anwendungszustand über Umgebungen und Last hinweg überwachen: strukturierte Protokollierung, Metriken, Traces, Korrelations-IDs über Dienste hinweg und das praktische Spektrum von Tools von Syslog bis Prometheus, Loki, OpenTelemetry und SaaS-APM."
published: 2026-04-15
faq:
  - question: "Was ist der Unterschied zwischen Log-Level und Log-Kontext?"
    answer: "Das Log-Level (z. B. DEBUG, INFO, WARNING, ERROR) kategorisiert die Schwere einer Nachricht und ermöglicht so die Filterung in der Produktion. Der Log-Kontext sind strukturierte Metadaten (Arrays mit Benutzer-IDs, Request-IDs, Ausführungszeiten), die an den Log-Eintrag angehängt werden, um eine automatisierte Indizierung und Korrelation ohne das Parsen von unstrukturiertem Text zu ermöglichen."
  - question: "Warum sind Labels mit hoher Kardinalität schlecht für Metrik-Systeme wie Prometheus?"
    answer: "Prometheus speichert Metriken als Einträge in einer Time-Series-Datenbank. Jede eindeutige Kombination von Labels erzeugt eine neue Zeitreihe. Das Speichern von Parametern mit hoher Kardinalität (wie Benutzer-IDs oder vollständige dynamische URLs) als Labels führt zu einer 'Series Explosion', die den Speicher von Prometheus erschöpft und den Metrik-Server zum Absturz bringt."
  - question: "Wie verbreitet Distributed Tracing die Korrelation über Mikrodienste hinweg?"
    answer: "Distributed Tracing verwendet HTTP-Header (wie `X-Request-Id` oder W3C `traceparent`), um eine eindeutige Trace-ID und Span-ID entlang der Anfragenkette zu übergeben. Jeder Mikrodienst im Anfragenfluss extrahiert diesen Header, fügt ihn in seine eigenen Logs ein und injiziert ihn in ausgehende HTTP-Client-Anfragen oder Queue-Nachrichten-Header."
  - question: "Warum sollte Laravel Telescope in der Produktion deaktiviert werden?"
    answer: "Laravel Telescope erfasst detaillierten Abfragekontext, Datenbankabfragen und Protokolleinträge und speichert diese in der Datenbank. In einer Produktionsumgebung mit hohem Datenverkehr beeinträchtigt dieser Abfrage- und Speicher-Overhead den Durchsatz der Anwendung, erhöht die Datenbanklast und kann übermäßig viel Speicherplatz verbrauchen."
---

# Observability: Logs, Metriken und Health in Laravel-Monolithen und Mikrodiensten

„Läuft auf meinem Rechner“ ist keine Monitoring-Strategie. Die Produktion bricht auf **spezifische Weise** zusammen: Die Festplatte läuft voll, die Latenz in den Warteschlangen schnellt in die Höhe, eine Abhängigkeit läuft in ein Timeout oder ein Replikat liefert veraltete Daten (Stale Reads). Gute Observability ermöglicht es Ihnen zu beantworten, **was sich geändert hat**, **für wen** und **welcher Zwischenschritt (Hop) fehlgeschlagen ist** – ohne dass Sie sich per SSH auf jedem Server einloggen müssen. Die gleichen Prinzipien gelten sowohl für einen **Laravel-Monolithen** als auch für eine **Flotte von Mikrodiensten**; lediglich die **Infrastruktur** und die **Kardinalität** werden komplexer, je weiter Sie die Systemgrenzen aufteilen.

**Verwandte Leitfäden:** [PHP database connection pooling](php-database-connection-pooling) · [Databases under load](database-performance-and-scaling) · [API gateway & messaging](../microservices/api-gateway)

## Inhalt

* [Die drei Säulen: Logs, Metriken, Traces](#pillars)
* [Was sich je nach Umgebung unterscheidet](#environments)
* [Laravel-Monolith: Praktische Schichten](#monolith)
* [Mikrodienste: Korrelation und Tracing](#microservices)
* [Das Werkzeug-Spektrum: Von klassisch bis modern](#tools)
* [Unter Last: Sampling, Kardinalität, Kosten](#load)
* [Alarmierung, die Menschen respektieren werden](#alerting)
* [Häufige Fehler](#common-mistakes)
* [Checkliste](#checklist)
* [Selbsttest-Quiz](#self-test-quiz)

---

<a id="pillars"></a>
## Die drei Säulen: Logs, Metriken, Traces

| Säule | Antworten | Typische Fehler |
|--------|---------|------------------|
| **Logs** | *Welche Geschichte hat sich abgespielt?* (Fehler, Audit, Debug-Kontext) | Unstrukturierte Einzeiler, die Sie nicht abfragen können; Logging von Geheimnissen (Secrets); **INFO**-Überflutung in der Produktion |
| **Metriken** | *Wie viel, wie schnell, im Vergleich zu gestern?* (Raten, Histogramme, Sättigung) | Labels mit **hoher Kardinalität** (vollständige URL, Benutzer-ID bei jeder Serie); Diagramme, auf die niemand schaut |
| **Traces** | *Welcher Abschnitt (Span) in der Kette war langsam?* | Fehlende **Kontextverteilung (Context Propagation)**, sodass Mikrodienst B keine Ahnung hat, dass er zu Anfrage A gehört |

Sie verstärken sich gegenseitig: Eine **Spitze in der Fehlerrate** (Metrik) führt Sie zu den **repräsentativen Logs** und einem **Trace** eines langsamen Checkouts. Keine einzelne Säule ersetzt die anderen.

---

<a id="environments"></a>
## Was sich je nach Umgebung unterscheidet

* **Lokal / Dev** – Maximierung der **Entwicklergeschwindigkeit**: `tail`, Debug-UIs im Stil von Telescope, ausführliche Logs (Verbose), Breakpoints. Übertragen Sie diese Ausführlichkeit **nicht** unverändert in die Produktion.
* **Staging / Pre-Prod** – Spiegeln Sie **produktionsähnliche** Log-Ziele (Sinks) und Dashboards wider, wo dies wirtschaftlich ist. Fangen Sie Fehler der Klasse „funktioniert, bis wir JSON nach Loki senden“ ab.
* **Produktion** – Optimieren Sie auf **Signal**, **Aufbewahrungskosten (Retention)** und **sichere Standardwerte**: strukturierte Logs, Schwärzung (Redaction), Sampling auf Debug-Pfaden, **Health Checks**, die reale Abhängigkeiten widerspiegeln.

> [!NOTE]
> **Kompatibilitätsprinzip**
> Das Ziel ist nicht eine identische Tool-Landschaft überall – es sind **kompatible Verträge** (gleiche Korrelations-Feldnamen, gleiche Metrik-Namen), sodass Vorfälle kein Umlernen des Systems erfordern.

---

<a id="monolith"></a>
## Laravel-Monolith: Praktische Schichten

### Anwendungs-Protokollierung (Logging)

* Nutzen Sie die Kanäle in **`config/logging.php`**: `stack`, `daily`, `syslog` oder einen **JSON**-Formatter auf `stdout`, damit Container-Hosts die Daten erfassen können.
* Bevorzugen Sie **strukturierte Felder** (`context`-Arrays) gegenüber dem späteren Parsen von geschriebenen Texten.
* Hängen Sie die **Request-ID**, **Benutzer-ID** (falls datenschutzrechtlich zulässig) und die **Queue-Job-ID** an – alles, was Ihnen hilft, von einer Logzeile auf die restliche Geschichte zu schließen.

Beispiel für strukturierten Kontext:
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

Und Code-Nutzung:
```php
// app/Http/Controllers/OrderController.php
Log::info('Order processed successfully', [
    'order_id' => $order->id,
    'user_id' => auth()->id(),
    'execution_time_ms' => $timeMs,
]);
```

### Request-Lebenszyklus

* Middleware für die **Korrelations-ID**: Akzeptieren Sie eine eingehende `X-Request-Id` oder generieren Sie eine; geben Sie diese in den Antworten zurück und übergeben Sie sie an Jobs und HTTP-Clients.
* Laravels Methode **`Log::withContext()`** hilft dabei, den Kontext auf der Anfrage zu halten, ohne Parameter überallhin durchschleifen zu müssen.

### Queues und Schedules (Warteschlangen und Zeitpläne)

* **Horizon** (Redis) liefert **Queue-Tiefe, Durchsatz, fehlgeschlagene Jobs** – betrachten Sie es als **vollwertiges Monitoring**, nicht als optionale UI.
* Geplante Aufgaben (Scheduled Tasks): Protokollieren Sie **Start/Ende/Dauer**; alarmieren Sie bei **ausgefallenen Ausführungen** (Cron-Monitoring oder externer Heartbeat).

### Tiefgreifende Inspektion (Nicht-Produktion)

* **Telescope** ist für **Lokal/Staging** von unschätzbarem Wert; lassen Sie es in der Produktion **deaktiviert**, es sei denn, Sie haben strenge Hürden (IP-Filter, Auth, Sampling) und akzeptieren den Overhead.
* **Laravel Pulse** zeigt **langsame Abfragen, Ausnahmen, Queues** in einem Dashboard an – achten Sie bei stark frequentierten Apps dennoch auf **Sampling und Datenaufbewahrung (Retention)**.

### Health und Readiness (Gesundheit und Bereitschaft)

* **`/up` (Health)** in Laravel 11+: Unterscheiden Sie zwischen **Liveness** („Prozess läuft“) und **Readiness** („kann mit DB und Cache kommunizieren“). Load Balancer und Kubernetes-Probes benötigen diesen Unterschied.

### Fehler-Tracking

* **Sentry**, **Flare**, **Bugsnag** – gruppierte Stack-Traces, Release-Tracking, Breadcrumbs. Sie ergänzen Logs, ersetzen aber keine **Metriken zur Sättigung**.

---

<a id="microservices"></a>
## Mikrodienste: Korrelation und Tracing

Wenn ein HTTP-Aufruf den Weg **Gateway → Dienst A → Dienst B → Broker → Worker** nimmt, ist ein einfaches Zugriffsprotokoll pro Dienst **unzureichend**.

### Korrelations-ID

* Verbreiten Sie eine stabile ID bei jedem ausgehenden Aufruf (`X-Request-Id` oder **W3C `traceparent`** zusammen mit Ihrer internen ID).
* Protokollieren Sie diese in **jedem** Dienst beim Eintritt; binden Sie sie in **asynchrone** Payloads (Job-`payload`, Nachrichten-Header) ein.

### Distributed Tracing

* **OpenTelemetry** etabliert sich als **anbieterneutraler** Standard zur Ausgabe von Traces. Collectors leiten diese an **Jaeger**, **Tempo**, **Zipkin** oder SaaS-Backends weiter.
* PHP-Ökosysteme variieren in der Reife – prüfen Sie die **Instrumentierung** für Ihren HTTP-Client, DB-Treiber und Ihre Queue-Bibliothek. Ein unvollständiges Tracing ist immer noch besser als gar keines.

### Dienstgrenzen (Service Boundaries)

* Standardisieren Sie Richtlinien für **Timeouts, Retries (Wiederholungen) und Idempotenz**. Observability wird Ihnen **kaskadierende Wiederholungen** aufzeigen, wenn jede Schicht blindlings Retries ausführt.

---

<a id="tools"></a>
## Das Werkzeug-Spektrum: Von klassisch bis modern

### Host- und Netzwerk-Ära

* **syslog**, **rsyslog**, **logrotate** – Zentralisierung einfacher Textdateien; immer noch als **Transport-Stufe** gültig.
* **Nagios**, **Icinga**, **Zabbix** – Host-Prüfungen, Ping, Festplatte, einfache Service-Probes. Weniger geeignet für **App-Traces**, aber immer noch verbreitet für **Infrastruktur-Baselines**.

### Log-Aggregation

* **ELK / Elastic Stack** (Elasticsearch, Logstash/Beats, Kibana) – mächtige Suche; betreiben Sie den Stack selbst oder kaufen Sie Kapazitäten bewusst ein.
* **Graylog**, **Splunk** (Enterprise) – ähnliches Problemfeld.

### Metriken und Dashboards

* **Prometheus-Scrape-Modell** + **Grafana-Dashboards** – de facto Standard für **Kubernetes** und viele Bare-Metal-Umgebungen; **Alertmanager** für das Routing.
* **VictoriaMetrics**, **Mimir**, **Thanos** – langzeitstabile oder hochverfügbare Varianten rund um Prometheus-Protokolle.

### Logs „wie Metriken“

* **Grafana Loki** – Label-basierter Log-Speicher, der sich natürlich in Grafana einfügt; oft günstiger als das Indizieren jedes einzelnen Feldes bei Suchmaschinen.

### Cloud-Native

* **AWS CloudWatch**, **Google Cloud Logging/Monitoring**, **Azure Monitor** – enge Integration, wenn Sie bereits in diesen Abrechnungssystemen leben.

### All-in-One-SaaS

* **Datadog**, **New Relic**, **Honeycomb** – Logs, Metriken, APM, RUM; **schneller Mehrwert**, **Abrechnung nach Volumen** – achten Sie auf die Kardinalität.

### Fehlerverfolgung und APM für PHP

* **Sentry** (Fehler + Leistung), **Scout**, **Tideways** (PHP-fokussiertes Profiling) – starke Verbreitung in der Laravel-Community.

### Die Standardisierungswelle

* **OpenTelemetry (OTel)** – vereinheitlichte SDKs/Exporter; der **Collector** kann Daten an viele Backends gleichzeitig verteilen. **Die Verbreitung nimmt zu**, um genau den Vendor Lock-in pro Signal zu vermeiden.

---

<a id="load"></a>
## Unter Last: Sampling, Kardinalität, Kosten

* Das **Log-Volumen** wächst linear mit dem Datenverkehr; **JSON pro Anfrage** im `debug`-Modus kann die CPU der Anwendung stark belasten. Verwenden Sie **Level** und **Sampled Debug** für hochfrequentierte Pfade.
* **Prometheus-Labels**: Verwenden Sie niemals **unbegrenzte** Werte (rohe URLs mit IDs, E-Mails) als Label-Namen oder hochkardinale Werte – **die Anzahl der Metriken explodiert**.
* **Trace-Sampling**: Behalten Sie **100 %** der Traces für Fehler oder langsame Anfragen; führen Sie ein Sampling für den Rest durch – Backends und Ihr Budget werden es Ihnen danken.
* **Datenaufbewahrung (Retention)**: Definieren Sie **Hot** (Tage) vs. **Cold** (Objektspeicher) vs. **Löschen**; Compliance-Richtlinien erfordern möglicherweise eine längere Aufbewahrung für **Audits** getrennt von den eigentlichen **Debug**-Logs.

---

<a id="alerting"></a>
## Alarmierung, die Menschen respektieren werden

Alarmieren Sie bei **benutzerseitigen** oder **unmittelbar bevorstehenden** Fehlern: **SLO-Burn-Rate**, sprunghafter Anstieg der **Fehlerrate**, **Warteschlangen-Wartezeit** (p95), Erreichen von **Festplatten-Grenzwerten**, Ablauf von **Zertifikaten**.

Vermeiden Sie Paging (Rufbereitschaft) bei **bekanntermaßen unruhigen** Bedingungen, es sei denn, Sie fügen **Playbooks (Runbooks)** bei. „CPU > 80 %“ über fünf Minuten ist oft **kein** Vorfall; ein Abfall der **Zahlungserfolgsrate um das Zehnfache** ist es hingegen definitiv.

---

<a id="common-mistakes"></a>
## Häufige Fehler

1. **Hochkardinale Metrik-Labels**: Hinzufügen von eindeutigen IDs wie `user_id` oder dynamisch generierten `url`-Parametern als Prometheus-Labels, was zur Erschöpfung des Arbeitsspeichers führt.
2. **Weitergabe von Zugangsdaten in Logs**: Protokollieren von `$request->all()` bei Login- oder Passwort-Reset-Endpunkten, was Passwörter, Token und personenbezogene Daten (PII) offenlegt.
3. **Aktiviertes Telescope in der Produktion**: Uneingeschränkte Speicherung von Anwendungsdatenspannen in der Hauptdatenbank, was Abfragen verlangsamt und den Primärspeicher überlastet.
4. **Fehlende Timeouts bei externen Anfragen**: Aufruf von APIs von Drittanbietern ohne Timeout, was dazu führt, dass HTTP-Prozesse hängen bleiben und die verfügbaren FPM-Child-Prozesse aufbrauchen.

---

<a id="checklist"></a>
## Checkliste

1. **Strukturierte Logs** auf stdout oder an einen Log-Shipper; **eine** Korrelations-ID über synchrone und asynchrone Arbeit hinweg.
2. **Golden Signals** pro Dienst: Latenz, Traffic, Fehler, Sättigung (zuzüglich der **Queue-Tiefe** für Laravel-Worker).
3. **Health-Endpunkte**, die **tatsächliche** Abhängigkeiten prüfen; trennen Sie zwischen **Liveness** und **Readiness**, wenn Orchestratoren dies erfordern.
4. **Fehler-Tracker** in der Produktion; **Telescope-ähnliche** Tools auf Nicht-Produktionsumgebungen beschränken.
5. **Kosten- und Kardinalitätsprüfung** vor dem Aktivieren von „alles protokollieren“ oder „alles mit Labels versehen.“

---

## Zusammenfassung

Observability ist **Teil des Produkts**: Derselbe Laravel-Code, der Benutzer bedient, sollte Ihnen – **mit Belegen** – mitteilen, wenn er kurz vor dem Scheitern steht und **wo** Sie zuerst suchen müssen.

---

<a id="self-test-quiz"></a>
## Selbsttest-Quiz

### Frage 1: Was ist das Hauptproblem beim Hinzufügen von E-Mail-Adressen von Benutzern als Labels in einer Prometheus-Metrikdatenbank?
- A) Prometheus unterstützt keine String-Werte.
- B) Es verursacht eine Explosion der Metrik-Kardinalität, was die Metrikdatenbank zum Absturz bringt.
- C) Es verstößt gegen Standard-URL-Formatierungsrichtlinien.

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort: B**
Jedes eindeutige E-Mail-Label erzeugt eine neue Zeitreihe. Wenn Sie Tausende von Benutzern haben, geht Prometheus aufgrund der Kardinalitätsexplosion schnell der Speicher aus.
</details>

---

### Frage 2: Welchen Zweck hat der Readiness Check in einer Kubernetes- oder Load-Balanced-Umgebung?
- A) Zu überprüfen, ob der Serverprozess gestartet wurde.
- B) Zu überprüfen, ob die Anwendung bereit ist, Traffic zu akzeptieren (z. B. ob die Datenbankverbindung steht).
- C) Langsame Abfragen zu verfolgen.

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort: B**
Eine Readiness-Probe prüft, ob der Container bereit ist, eingehenden Web-Traffic zu verarbeiten. Wenn diese fehlschlägt, leitet der Load Balancer keine Anfragen mehr an diesen Knoten weiter.
</details>
