---
title: "Laravel Sail: .env-Layout, Port-Weiterleitungen, CI und lokal vs. Produktion | DevSense"
description: "Trennen Sie die Konfiguration von Laravel Sail und dem Host: .env.example, FORWARD_*-Ports, APP_URL in Docker, optionale env_file und GitHub Actions mit Docker Compose sowie Checklisten, wenn Sail nicht Ihr Server ist."
faq:
  - question: "Warum erhalte ich beim Ausführen von 'sail up' die Fehlermeldung 'Port bereits belegt'?"
    answer: "Dies tritt auf, wenn ein anderer Dienst auf Ihrem Host-Rechner (wie eine lokale MySQL-Instanz oder eine andere Sail-Instanz) denselben Port verwendet. Sie können dies beheben, indem Sie FORWARD_DB_PORT oder APP_PORT in Ihrer .env-Datei ändern."
  - question: "Sollte ich meine .env-Datei in Git einchecken?"
    answer: "Nein, Sie dürfen die .env-Datei niemals einchecken. Sie enthält vertrauliche Daten und entwicklerspezifische Einstellungen. Pflegen und checken Sie stattdessen sichere Standardwerte in der .env.example ein."
  - question: "Wie lade ich entwicklerspezifische Container-Einstellungen, ohne die gemeinsame Konfiguration zu ändern?"
    answer: "Sie können eine 'env_file'-Liste unter Ihrem Dienst in der docker-compose.yml angeben, um eine sekundäre lokale Datei (z. B. .env.docker.local) zu laden, die lokale Überschreibungen enthält."
  - question: "Kann ich Laravel Sail auf einem Produktionsserver ausführen?"
    answer: "Nein. Sail ist für die Ergonomie von Entwicklern konzipiert und bietet keine produktionsreife Sicherheit, TLS-Terminierung, Backup-Systeme oder Skalierungsoptimierungen. Führen Sie Deployments mit speziellen Konfigurationen durch (z. B. Kubernetes, Ansible oder Laravel Forge)."
---

# Sail: Umgebungen & Deployment

Sie checken eine lokale `.env`-Datei mit Datenbankpasswörtern in GitHub ein und Ihre Sicherheits-Scanner schlagen sofort Alarm. Oder ein Teammitglied zieht Ihr Repository, führt `sail up` aus und sieht den Prozess abstürzen, weil der lokale MySQL-Port `3306` bereits durch ein anderes Projekt belegt ist. Umgebungsmanagement bedeutet nicht nur das Definieren von Schlüssel-Wert-Paaren; es geht darum, eine strikte Grenze zwischen lokaler Ergonomie, CI/CD-Tests und abgesicherten Produktions-Deployments zu ziehen.

Die containerisierte Entwicklung verändert das Verhalten von Umgebungsvariablen. Da PHP innerhalb von `laravel.test` läuft, während Datenbanken an Host-Ports gebunden sind, müssen Pfade, URLs und Port-Mappings angepasst werden – je nachdem, ob auf sie vom Host-System aus oder innerhalb des Docker-Compose-Netzwerks zugegriffen wird.

In diesem Leitfaden zeigen wir Ihnen, wie Sie `.env`-Dateien strukturieren, Host-Port-Kollisionen vermeiden, Docker-basierte CI-Pipelines einrichten und Ihre Konfiguration vor dem Go-live in der Produktion auditieren.

**Navigation:** [Alle Tools](../) · [Sail-Übersicht](sail#what-sail-is) · [Datenbanken](sail-databases#networking) · [Queues](sail-queues#connections) · [Fehlerbehebung](sail-troubleshooting#wsl-filesync)

## Inhalt

* [`.env`, `.env.example` und Secrets](#env-files)
* [`FORWARD_*`-Ports und Kollisionen](#forward-ports)
* [`APP_URL` und Trusted Proxies](#app-url)
* [Optionale `env_file` in Compose](#compose-env-file)
* [CI: GitHub Actions-Pattern](#ci-example)
* [Sail vs. Dev-/Staging-/Produktionsserver](#not-production)
* [Checkliste vor dem Go-live](#checklist)
* [Häufige Fehler](#common-mistakes)
* [Selbsttest-Fragen](#self-check)

---

<a id="env-files"></a>
## `.env`, `.env.example` und Secrets

- **`.env`**: Speichert lokale Secrets und umgebungsspezifische Details. **Checken Sie diese Datei niemals in die Versionsverwaltung ein.**
- **`.env.example`**: Wird in Git eingecheckt. Die Standardwerte sollten sichere lokale Standardwerte darstellen, sodass ein neuer Entwickler die Datei nach `.env` kopieren und sofort `sail up` ausführen kann.

```dotenv
# .env.example
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306

# Sail-spezifische Überschreibungen
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
```

---

<a id="forward-ports"></a>
## `FORWARD_*` ports and collisions

Standardmäßig bindet Sail Datenbank- und Anwendungsports an localhost. Wenn Sie mehrere Projekte ausführen, kollidieren Ports wie `3306` (MySQL) oder `80` (HTTP).

Um dies zu beheben, ändern Sie die weitergeleiteten Ports in der `.env`:

```dotenv
# .env
APP_PORT=8080
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
```

> [!NOTE]
> **Interne vs. externe Ports**
> Das Ändern von `FORWARD_DB_PORT` ändert den auf Ihrem Host-System freigegebenen Port. Innerhalb des Docker-Netzwerks kommuniziert MySQL weiterhin auf dem Standardport `3306`. Ihre Laravel-Konfiguration muss ihren `DB_PORT` nicht ändern.

---

<a id="app-url"></a>
## `APP_URL` und Trusted Proxies

Laravel verwendet `APP_URL`, um signierte URLs und Routen-Weiterleitungen zu generieren.

```dotenv
# .env
APP_URL=http://localhost:8080
```

Stellen Sie sicher, dass dies mit dem an Ihren Host gemappten Port übereinstimmt. Wenn Sie einen Load Balancer oder Reverse Proxy (wie Nginx oder Traefik) vor Ihre Produktionsanwendung schalten, konfigurieren Sie `TrustProxies` in Laravel, um die ursprüngliche IP und das Protokoll (HTTPS) des Clients korrekt auszulesen.

---

<a id="compose-env-file"></a>
## Optionale `env_file` in Compose

Wenn Sie Umgebungsvariablen haben, die nur für die Docker-Umgebung gelten, laden Sie diese in der `docker-compose.yml`:

```yaml
# docker-compose.yml
services:
    laravel.test:
        env_file:
            - .env
            - .env.docker.local
```

Dies verhindert, dass die gemeinsame `.env`-Datei mit Umgebungsvariablen überladen wird, die nur innerhalb von Containern eine Rolle spielen.

---

<a id="ci-example"></a>
## CI: GitHub Actions-Pattern

Sie können die Docker-Compose-Dienste von Sail innerhalb Ihrer CI/CD-Pipeline verwenden, um automatisierte Tests in einer sauberen Umgebung auszuführen.

```yaml
# .github/workflows/tests.yml
name: Run Tests
on: [push]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Copy CI Env
        run: cp .env.ci .env
      - name: Start Sail
        run: docker compose up -d
      - name: Run Pest/PHPUnit
        run: docker compose exec -T laravel.test php artisan test
```

---

<a id="not-production"></a>
## Sail vs. Dev-/Staging-/Produktionsserver

- **Sail** ist für die lokale Entwicklungsgeschwindigkeit optimiert (enthält Tools wie Mailpit, Meilisearch und Runtimes mit File-Watching).
- **Staging-/Produktionsumgebungen** erfordern Sicherheitsabsicherung, Log-Aggregation, automatisierte Backup-Routinen, SSL/TLS-Zertifikate und separate Datenbankserver (z. B. AWS RDS).

> [!NOTE]
> Versuchen Sie nicht, `sail up` auf einem öffentlich zugänglichen Cloud-Server auszuführen. Es gibt Entwicklungsdienste frei und verwendet Konfigurations-Stubs, die äußerst unsicher sind.

---

<a id="checklist"></a>
## Checkliste vor dem Go-live

- [ ] `APP_ENV=production` und `APP_DEBUG=false` in der Produktionsumgebung.
- [ ] Generieren Sie einen sicheren `APP_KEY` mit `php artisan key:generate`.
- [ ] Verbinden Sie sich mit verwalteten Datenbankinstanzen, nicht mit lokalen Containern.
- [ ] Setzen Sie `QUEUE_CONNECTION=redis` oder `sqs` mit überwachten Worker-Systemen.
- [ ] Aktivieren Sie die TLS/HTTPS-Terminierung.
- [ ] Konfigurieren Sie zentrale Log-Handler (z. B. Bugsnag, Sentry oder AWS CloudWatch).

---

## ⚠️ Häufige Fehler

**1. Einchecken der `.env`-Datei in Git**
Das Offenlegen von API-Schlüsseln, Datenbank-Anmeldedaten oder Anwendungspasswörtern in der Git-Historie.
*Lösung:* Überprüfen Sie vor dem Commit, ob `.env` in Ihrer `.gitignore`-Datei aufgeführt ist.

**2. Ändern von `DB_PORT` anstelle von `FORWARD_DB_PORT` bei lokalen Konflikten**
Das Ändern von `DB_PORT=3307` in Ihrer `.env` ohne Änderung der Compose-Konfiguration von Sail bricht die interne Verbindung, da die Container-Datenbank weiterhin auf `3306` lauscht.
*Lösung:* Belassen Sie `DB_PORT=3306` (für die interne Container-Verbindung) und setzen Sie `FORWARD_DB_PORT=3307` (um den Port des Host-Rechners zu ändern).

**3. Direktes Deployment der `docker-compose.yml` von Sail in der Produktion**
Das Starten von Datenbanken neben dem App-Container ohne persistente Replikation, Backups oder sichere Zugriffsrichtlinien.
*Lösung:* Die Compose-Datei von Sail ist ein Werkzeug für die Entwicklung, kein Deployment-Manifest.

---

## 🧠 Selbsttest-Fragen

1. **Warum bricht das Ändern von `DB_PORT=3307` in der `.env` Migrationen in Sail ab, während das Ändern von `FORWARD_DB_PORT=3307` dies nicht tut?**
2. **Welches Sicherheitsrisiko besteht, wenn Sie die Compose-Datei von Sail direkt auf einem öffentlichen Produktionsserver bereitstellen?**
3. **Wie hilft der `env_file`-Block in der `docker-compose.yml`, Umgebungsausnahmen zu strukturieren?**
4. **Welche Datei sollten Sie aktualisieren, um sicherzustellen, dass Teammitglieder eine Liste aller erforderlichen API-Schlüssel für ein neues Feature haben?**

<details>
<summary><b>Antworten anzeigen</b></summary>

1. Das Ändern von `DB_PORT` weist Laravel an, nach der Datenbank auf Port `3307` innerhalb des Container-Netzwerks zu suchen, wo der Datenbank-Container weiterhin auf `3306` lauscht. Das Ändern von `FORWARD_DB_PORT` ändert nur den externen Port, der für Ihr Host-System freigegeben wird, während das interne Container-Netzwerk unberührt bleibt.
2. Es legt reine Entwicklungstools (wie Mailpit und Meilisearch) im öffentlichen Internet offen, führt Container mit Entwicklerprivilegien aus und setzt Datenbanken ohne Replikation oder Produktions-Backups aus.
3. Es ermöglicht Ihnen, Ihre Variablen auf mehrere Dateien aufzuteilen (wie `.env` und `.env.docker.local`) und Docker-spezifische Überschreibungen zu laden, ohne die Hauptkonfigurationsdatei zu überladen.
4. Sie sollten die Datei `.env.example` mit leeren Werten oder sicheren Platzhaltern aktualisieren und deren Verwendung dokumentieren.
</details>
