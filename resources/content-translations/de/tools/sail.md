---
title: "Laravel Sail: Docker Local Stack, PHP-Versionen, Redis, Postgres, Queues & Deployment-Hinweise | DevSense"
description: "Praktischer Leitfaden zu Laravel Sail: PHP-Version ändern, Redis oder RabbitMQ hinzufügen, von MySQL zu PostgreSQL wechseln, MongoDB-Hinweise, Queue-Worker in Containern, Trennung von Umgebungen und wie sich Sail von Dev-/Staging-/Produktionsservern unterscheidet."
faq:
  - question: "Was ist Laravel Sail?"
    answer: "Laravel Sail ist ein leichtgewichtiges Command-Line-Interface (CLI) zur Interaktion mit Laravels Standard-Docker-Entwicklungsumgebung, basierend auf Docker Compose."
  - question: "Wie ändere ich die PHP-Version in Laravel Sail?"
    answer: "Ändern Sie das Build-Argument PHP_VERSION in Ihrer docker-compose.yml-Datei unter dem Service laravel.test und bauen Sie den Container anschließend mit sail build --no-cache neu."
  - question: "Warum erhalte ich Verbindungsfehler (connection refused) bei Redis oder MySQL?"
    answer: "Weil Sie versuchen, sich über 127.0.0.1 zu verbinden. Innerhalb des Sail-Containernetzwerks müssen Sie den Servicenamen (z. B. mysql oder redis) als Hostnamen verwenden."
  - question: "Kann ich Laravel Sail in der Produktion verwenden?"
    answer: "Nein, Laravel Sail ist ausschließlich für die lokale Entwicklung konzipiert. Für die Produktion sollten Sie optimierte Docker-Images erstellen und diese mit einer geeigneten Orchestrierung wie Kubernetes, ECS oder Docker Swarm bereitstellen."
---

# Laravel Sail: Der ultimative Leitfaden für den Local Stack

Laravel Sail ist ein eleganter Wrapper für **Docker Compose**, der entwickelt wurde, um eine vollständige Entwicklungsumgebung (PHP-FPM, MySQL, Redis, Meilisearch, Mailpit usw.) bereitzustellen, ohne dass Sie Serverkonfigurationen auf Ihrem Host-Rechner verwalten müssen.

Sail ist jedoch **keine** Produktionsumgebung. Das Verständnis der Grenze zwischen lokalem Sail und einer echten Produktionsumgebung ist der Schlüssel zur Vermeidung des gefürchteten „Auf meinem Computer funktioniert es“-Syndroms. Lassen Sie uns Sail gemeinsam meistern!

---

## Inhalt
* [Mentales Modell & Voraussetzungen](#mental-model)
* [Wichtige CLI-Shortcuts](#shortcuts)
* [PHP-Versionen ändern](#php-version)
* [Dienste anpassen (Redis, RabbitMQ, PostgreSQL)](#customizing-services)
* [Debugging mit Mailpit & Xdebug](#debugging)
* [WSL2-Leistungsoptimierung](#performance)
* [Vergleich: Sail (Lokal) vs. Produktion](#local-vs-deploy)
* [🧠 Selbsttest-Fragen](#self-check)

---

<a id="mental-model"></a>
## Mentales Modell & Voraussetzungen

Sail installiert weder PHP noch Node auf Ihrem Host-Rechner. Stattdessen führt es Befehle **innerhalb containerisierter Runtimes** aus.

Wenn Sie Windows nutzen, **MÜSSEN** Sie Docker innerhalb von **WSL2** (Windows-Subsystem für Linux) ausführen und Ihren Projektordner im Linux-Dateisystem (z. B. `~/Development/my-app`) ablegen, nicht auf der Windows-Partition `C:\`. Das Arbeiten über die Windows-Linux-Mount-Grenze hinweg führt zu extremen Engpässen bei den Festplatten-I/O-Operationen.

---

<a id="shortcuts"></a>
## Wichtige CLI-Shortcuts

Sind Sie es leid, jedes Mal `./vendor/bin/sail` einzutippen? Fügen Sie einen Shell-Alias hinzu!

```bash
# ~/.zshrc oder ~/.bashrc
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Jetzt können Sie alltägliche Befehle effizient ausführen:
- `sail up -d` — Startet alle Dienste im Hintergrund.
- `sail down` — Stoppt die Dienste.
- `sail artisan migrate` — Führt Datenbank-Migrationen aus.
- `sail npm run dev` — Führt Vite-Assets lokal aus.

---

<a id="php-version"></a>
## PHP-Versionen ändern

Um Ihre PHP-Version in Sail zu aktualisieren oder herabzustufen, müssen Sie Ihre Compose-Konfiguration anpassen.

```yaml
# docker-compose.yml
services:
    laravel.test:
        build:
            context: ./vendor/laravel/sail/runtimes/8.4
            dockerfile: Dockerfile
            args:
                WWWGROUP: '${WWWGROUP}'
                # Ändern Sie dieses Build-Argument auf die gewünschte Minor-Version:
                PHP_VERSION: '8.4'
```

Nachdem Sie die Datei geändert haben, bauen Sie die Container-Layer ohne Cache neu:

```bash
# Terminal
sail build --no-cache
sail up -d
```

---

<a id="customizing-services"></a>
## Dienste anpassen

### 1. Verbindung zu Redis herstellen
Wenn Sie Redis bei der Installation übersprungen haben, können Sie es hinzufügen, indem Sie Ihre Compose-Konfiguration bearbeiten:

```yaml
# docker-compose.yml
services:
    redis:
        image: 'redis:alpine'
        ports:
            - '${FORWARD_REDIS_PORT:-6379}:6379'
        volumes:
            - 'sail-redis:/data'
        networks:
            - sail
```

Stellen Sie sicher, dass Ihre Umgebungsdatei dem entspricht:

```dotenv
# .env
REDIS_HOST=redis
REDIS_PORT=6379
```

> [!NOTE]
> **Wussten Sie schon?**
> Innerhalb des Sail-Netzwerks kommunizieren Container untereinander über ihre Servicenamen (wie `redis` oder `mysql`) als Hostnamen. Die Verwendung von `127.0.0.1` schlägt fehl, da diese IP auf den Anwendungscontainer selbst verweist!

### 2. Wechsel von MySQL zu PostgreSQL
Um die Datenbank-Engine zu tauschen, ersetzen Sie den `mysql`-Block in Ihrer `docker-compose.yml` durch `pgsql`:

```yaml
# docker-compose.yml
services:
    pgsql:
        image: 'postgres:15-alpine'
        ports:
            - '${FORWARD_DB_PORT:-5432}:5432'
        environment:
            POSTGRES_DB: '${DB_DATABASE}'
            POSTGRES_USER: '${DB_USERNAME}'
            POSTGRES_PASSWORD: '${DB_PASSWORD}'
        volumes:
            - 'sail-pgsql:/var/lib/postgresql/data'
        networks:
            - sail
```

Und aktualisieren Sie Ihre `.env`:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
```

---

<a id="debugging"></a>
## Debugging mit Mailpit & Xdebug

### Mailpit (Virtueller Mailserver)
Sail leitet ausgehende E-Mails automatisch an Mailpit weiter. Es fängt E-Mails ab, damit Sie nicht versehentlich Testnachrichten an echte Kunden senden. Konfigurieren Sie SMTP in Ihrer `.env`:

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```
Sie können die abgefangenen E-Mails einsehen, indem Sie das Web-UI unter `http://localhost:8025` öffnen.

### Xdebug
Um das Step-by-Step-Debugging zu aktivieren, setzen Sie einfach den Xdebug-Modus in Ihrer Umgebung:

```dotenv
# .env
SAIL_XDEBUG_MODE=develop,debug
```
Starten Sie Sail neu (`sail down && sail up -d`), um die Konfiguration anzuwenden.

---

<a id="local-vs-deploy"></a>
## Vergleich: Sail (Lokal) vs. Produktion

| Feature | Laravel Sail (Lokale Entwicklung) | Produktionsumgebung |
|---------|-----------------------------------|---------------------|
| **HTTP-Server** | Eingebauter PHP-Server | Nginx / Apache + PHP-FPM oder Octane |
| **Queues** | Ausführung über Terminal `sail artisan queue:work` | Verwaltet via Supervisor oder Systemd |
| **SSL / HTTPS** | Einfaches HTTP auf localhost | TLS terminiert auf Nginx, Cloudflare oder Load Balancer |
| **Cache & Sessions** | Dateispeicher oder lokaler Redis-Container | Geclustertes Redis oder verwaltetes Memcached |
| **Datenbank** | Ephemerer Container | Verwaltete DB (RDS, Cloud SQL) mit Backups |

---

## ⚠️ Häufige Fehler

**1. Verbindung zu `127.0.0.1` für Redis/Datenbank innerhalb von Sail**
Wenn Ihre `DB_HOST` in der `.env` auf `127.0.0.1` gesetzt ist, kann Ihre App den Datenbank-Container nicht finden. Verwenden Sie stattdessen den Servicenamen (`mysql` oder `pgsql`).

**2. Befehle auf dem Host statt im Container ausführen**
Das Ausführen von `composer install` oder `php artisan migrate` in Ihrem lokalen Terminal kann zu Versionskonflikten oder Fehlern aufgrund fehlender PHP-Erweiterungen führen. Führen Sie diese Befehle immer über Sail aus:
```bash
# Terminal
sail composer install
sail artisan migrate
```

---

<a id="self-check"></a>
## 🧠 Selbsttest-Fragen

1. **Warum ist es wichtig, WSL2-Dateisystempfade (wie `~/Development`) zu verwenden, anstatt Verzeichnisse direkt von Windows-Partitionen (`/mnt/c/...`) zu mounten?**
2. **Richtig oder Falsch?** Um PostgreSQL zu aktivieren, müssen Sie den `pdo_pgsql`-Treiber im Sail-Container manuell herunterladen und kompilieren.
3. **Welchen Hostnamen sollten Sie in der `.env` verwenden, um E-Mails über Mailpit in Sail zu versenden?**
4. **Wie starten Sie einen Queue-Worker in Sail neu, damit dieser aktualisierte Codeänderungen lädt?**

<details>
<summary><b>Antworten anzeigen</b></summary>

1. Das Mounten von Windows-Laufwerken (`C:\`) in einen Linux-Docker-Container führt zu erheblichen Übersetzungs- und Virtualisierungsschichten, was Dateioperationen und die Asset-Kompilierung (Vite/Mix) um das bis zu 10-Fache verlangsamt.
2. **Falsch.** Die vorab erstellten PHP-Runtimes von Sail enthalten bereits Standard-Erweiterungen, einschließlich `pdo_pgsql`. Sie müssen lediglich den Dienst in der `docker-compose.yml` und der `.env` austauschen.
3. Sie müssen `mailpit` als Host verwenden.
4. Führen Sie `sail artisan queue:restart` aus oder starten Sie den Worker während der Entwicklung mit dem Flag `--max-jobs=1`.
</details>
