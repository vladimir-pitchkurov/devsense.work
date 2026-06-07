---
title: "Laravel Sail: Queue-Worker, Horizon, Redis, RabbitMQ & fehlgeschlagene Jobs | DevSense"
description: "Führen Sie Laravel-Queues in Sail aus: Sync vs. Redis vs. Datenbank, queue:work und Horizon lokal, RabbitMQ mit Community-Treibern, failed_jobs, queue:restart und wie sich die Produktion unterscheidet."
faq:
  - question: "Warum werden meine Hintergrundjobs in Sail nicht ausgeführt?"
    answer: "Im Gegensatz zum 'sync'-Treiber erfordern Treiber wie 'database' oder 'redis' einen aktiven Worker-Prozess. Sie müssen einen Worker-Container starten oder 'sail artisan queue:work' in Ihrem Terminal ausführen."
  - question: "Warum werden meine Änderungen in Job-Klassen nicht in der Queue angewendet?"
    answer: "Laravel-Queue-Worker laden den Anwendungscode nur einmal in den Speicher. Wenn Sie Codeänderungen vornehmen, müssen Sie 'sail artisan queue:restart' ausführen, um die Worker zum Neuladen zu zwingen."
  - question: "Wie führe ich den Scheduler in Laravel Sail ohne einen Host-Cron-Job aus?"
    answer: "Sie können 'sail run --rm laravel.test php artisan schedule:work' in einem separaten Terminalfenster ausführen. Dies fragt Aufgaben lokal ab und führt sie jede Minute aus."
  - question: "Wie unterscheidet sich das Queue-Management in Sail von der Produktion?"
    answer: "In Sail laufen Worker im Vordergrund Ihres Terminals und stoppen, wenn Sie es schließen. In der Produktion sorgen Prozessmanager wie Supervisor oder Kubernetes dafür, dass Worker dauerhaft laufen."
---

# Sail: Queues & Worker

Sie lösen eine Hintergrundaufgabe in Ihrer Laravel-App aus, aber nichts passiert. Die Jobseite zeigt „Ausstehend“ (Pending) an und Ihre Datenbankeinträge bleiben unverändert. Dann wird es Ihnen klar: In einer containerisierten Umgebung laufen Hintergrundjobs nicht von selbst. Ohne einen aktiven Worker-Prozess, der innerhalb des Sail-Containernetzwerks lauscht, sind Ihre Queues nur tote Dateien oder stumme Redis-Listen.

Das Ausführen von Queue-Workern und Task-Schedulern auf Ihrem Host-Betriebssystem schlägt fehl, da diese keinen Zugriff auf die containerisierten Datenbanken, Umgebungsvariablen und PHP-Erweiterungen von Sail haben. Um die Ausführung in der Produktion widerzuspiegeln, müssen Sie Ihre Worker-Runtimes innerhalb des Compose-Netzwerks ausführen und verwalten.

In diesem Leitfaden zeigen wir Ihnen, wie Sie Queue-Verbindungen konfigurieren, Worker und Horizon ausführen, Job-Fehlschläge behandeln und Code-Updates verwalten, ohne dass veraltete Worker-Caches aktiv bleiben.

**Navigation:** [Alle Tools](../) · [Sail-Übersicht](sail#what-sail-is) · [Datenbanken](sail-databases#networking) · [Umgebung & Deployment](sail-env-deploy#forward-ports) · [Fehlerbehebung](sail-troubleshooting#wsl-filesync)

## Inhalt

* [Verbindungen im Überblick](#connections)
* [Ausführen von `queue:work` in Sail](#queue-work)
* [Horizon (Redis)](#horizon)
* [RabbitMQ und AMQP-Pakete](#rabbitmq)
* [Fehlgeschlagene Jobs und Wiederholungen](#failed-jobs)
* [Codeänderungen und `queue:restart`](#restart)
* [Scheduler (`schedule:run`)](#scheduler)
* [Unterschiede zur Produktion](#production)
* [Häufige Fehler](#common-mistakes)
* [Selbsttest-Fragen](#self-check)

---

<a id="connections"></a>
## Verbindungen im Überblick

Wählen Sie Ihren Queue-Treiber in der `.env`:

```dotenv
# .env
QUEUE_CONNECTION=redis
```

| Treiber | Anwendungsfall in Sail |
|--------|-------------------|
| **`sync`** | Debuggen der Job-Logik synchron, ohne einen Worker zu starten. |
| **`database`** | Einfache asynchrone Queue; erfordert das Ausführen von `php artisan queue:table` und einen Worker. |
| **`redis`** | Produktionsstandard-Treiber; lässt sich in Horizon integrieren; erfordert einen Redis-Dienst. |
| **`rabbitmq`** | AMQP-Exchanges; erfordert einen RabbitMQ-Sidecar-Container und ein Community-Paket. |

> [!NOTE]
> **Konfigurations-Caching**
> Wenn Sie `QUEUE_CONNECTION` oder andere Queue-Umgebungsvariablen ändern, führen Sie `sail artisan config:clear` oder `sail artisan config:cache` aus, um die Änderungen auf Ihre laufenden Container anzuwenden.

---

<a id="queue-work"></a>
## Ausführen von `queue:work` in Sail

Um Jobs zu verarbeiten, öffnen Sie ein dediziertes Terminalfenster und führen Sie Folgendes aus:

```bash
# Terminal
sail artisan queue:work
```

Für erweiterte Kontrollen können Sie bestimmte Verbindungen und Optionen ansprechen:

```bash
# Terminal
sail artisan queue:work redis --queue=high,default --tries=3 --timeout=90
```

Um einen einzelnen Job auszuführen und den Prozess zu beenden (nützlich für das Debugging):

```bash
# Terminal
sail artisan queue:work --once
```

---

<a id="horizon"></a>
## Horizon (Redis)

Laravel Horizon bietet ein schönes Dashboard und eine codebasierte Konfiguration für Redis-Queues.

1. Installieren Sie Horizon über Composer:
   ```bash
   sail composer require laravel/horizon
   sail artisan horizon:install
   ```
2. Starten Sie Horizon in Ihrem Container:

```bash
# Terminal
sail artisan horizon
```

Greifen Sie unter `http://localhost/horizon` auf das Dashboard zu. In der Entwicklung wird Horizon gestoppt, wenn Sie `Ctrl+C` drücken.

---

<a id="rabbitmq"></a>
## RabbitMQ und AMQP-Pakete

Laravel enthält keinen nativen RabbitMQ-Treiber. So verwenden Sie AMQP:

1. Fügen Sie den RabbitMQ-Dienst zur `docker-compose.yml` hinzu ([siehe Datenbank-Rezepte](sail-databases#rabbitmq-sidecar)).
2. Installieren Sie ein von der Community gepflegtes Paket (z. B. `vyuldashev/laravel-queue-rabbitmq`).
3. Konfigurieren Sie Ihre Verbindungsdetails:

```dotenv
# .env
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
```

4. Starten Sie die Verarbeitung:
   ```bash
   sail artisan queue:work rabbitmq
   ```

---

<a id="failed-jobs"></a>
## Fehlgeschlagene Jobs und Wiederholungen

Wenn ein Hintergrundjob eine Exception wirft, protokolliert Laravel diese in der Tabelle `failed_jobs`.

- Fehlgeschlagene Jobs auflisten:
  ```bash
  sail artisan queue:failed
  ```
- Einen bestimmten Job erneut versuchen:
  ```bash
  sail artisan queue:retry 5
  ```
- Alle fehlgeschlagenen Jobs löschen:
  ```bash
  sail artisan queue:flush
  ```

---

<a id="restart"></a>
## Codeänderungen und `queue:restart`

Laravel-Worker starten das Anwendungs-Framework einmal und verarbeiten Jobs in einer kontinuierlichen Schleife. Wenn Sie eine Job-Datei bearbeiten, führt der laufende Worker weiterhin den alten, im Speicher gecachten Code aus.

Wann immer Sie Änderungen an Ihrer Codebasis speichern, starten Sie die Worker neu:

```bash
# Terminal
sail artisan queue:restart
```

> [!NOTE]
> **Autoreloading in der Entwicklung**
> Um zu vermeiden, dass Sie während der schnellen lokalen Entwicklung ständig `queue:restart` eingeben müssen, können Sie den Worker mit den Flags `--max-jobs=1` oder `--once` ausführen.

---

<a id="scheduler"></a>
## Scheduler (`schedule:run`)

Sail führt standardmäßig keinen Cron-Daemon im System aus. Um geplante Aufgaben in der Entwicklung auszuführen, haben Sie zwei Möglichkeiten:

1. Führen Sie den Scheduler manuell aus:
   ```bash
   sail artisan schedule:run
   ```
2. Führen Sie eine kontinuierliche Abfrageschleife im Hintergrund aus:

```bash
# Terminal
sail run --rm laravel.test php artisan schedule:work
```

---

<a id="production"></a>
## Unterschiede zur Produktion

| Thema | Sail (Lokale Entwicklung) | Produktionsumgebung |
|-------|------------------|------------------------|
| **Worker-Manager** | Manuell im Vordergrund des Terminals | **Supervisor** oder systemd-Daemon |
| **Skalierung** | Einzelner Docker-Container | Mehrere Container / Autoscaling in Kubernetes |
| **Failover** | Manueller Container-Neustart | Redundante Nodes und Clustering |

---

## ⚠️ Häufige Fehler

**1. Ändern des Job-Codes ohne Neustart des Workers**
Sie beheben einen Fehler in Ihrer Job-Klasse, aber der Queue-Runner wirft weiterhin denselben Fehler.
*Lösung:* Der Worker hat die alten PHP-Dateien im Speicher gecached. Führen Sie `sail artisan queue:restart` aus.

**2. Ausführen von `php artisan queue:work` auf Ihrem Host-Terminal**
Das direkte Ausführen des Befehls auf Ihrem Host-System führt aufgrund fehlender Datenbanktreiber oder der Unfähigkeit, interne Hostnamen wie `redis` oder `mysql` aufzulösen, zum Absturz.
*Lösung:* Stellen Sie immer `sail` voran, um den Befehl in der Container-Umgebung auszuführen: `sail artisan queue:work`.

**3. Missverständnis des `sync`-Queue-Treibers**
Die Verwendung von `QUEUE_CONNECTION=sync` führt Jobs sofort innerhalb des HTTP-Request-Lebenszyklus aus. Dies verbirgt Nebenläufigkeitsprobleme, Timeouts und Serialisierungsfehler, die erst in der Produktion auftreten würden.
*Lösung:* Verwenden Sie lokal `database` oder `redis`, um das Verhalten der Produktion widerzuspiegeln.

---

## 🧠 Selbsttest-Fragen

1. **Warum führt ein laufender Queue-Worker neu gespeicherte Codeänderungen nicht aus?**
2. **Welcher Hostname sollte in Ihrer `.env` für RabbitMQ eingetragen werden, wenn es innerhalb von Sail läuft?**
3. **Wie halten Sie den Laravel-Task-Scheduler kontinuierlich am Laufen, ohne Host-seitige Cron-Jobs einzurichten?**
4. **Welchen Artisan-Befehl führen Sie aus, um einen Job, der bei der Ausführung fehlgeschlagen ist, erneut in die Queue einzustellen?**

<details>
<summary><b>Antworten anzeigen</b></summary>

1. PHP-CLI-Worker starten das gesamte Anwendungs-Framework einmal und halten es im Speicher. Sie lesen Dateien für nachfolgende Jobs nicht erneut von der Festplatte. Sie müssen `sail artisan queue:restart` ausführen.
2. Sie müssen den Compose-Servicenamen verwenden, welcher `rabbitmq` lautet.
3. Führen Sie `sail run --rm laravel.test php artisan schedule:work` aus. Dies startet einen langlebigen Prozess, der den Scheduler jede Minute ausführt.
4. Führen Sie `sail artisan queue:retry {id}` aus, wobei `{id}` die Kennung aus der Tabelle `failed_jobs` ist, oder `all`, um alle fehlgeschlagenen Jobs zu wiederholen.
</details>
