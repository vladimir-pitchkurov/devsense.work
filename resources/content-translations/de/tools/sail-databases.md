---
title: "Laravel Sail: Datenbanken, Redis, Postgres, MongoDB, RabbitMQ in Docker Compose | DevSense"
description: "Rezepte für Laravel Sail: Redis hinzufügen, zu PostgreSQL wechseln, MongoDB mit PHP-Erweiterung ausführen, RabbitMQ-Sidecar, Mailpit- und Meilisearch-Anbindung, Healthchecks und Named Volumes."
faq:
  - question: "Warum schlägt die Verbindung meiner Laravel-App zu MySQL/PostgreSQL in Sail fehl?"
    answer: "Dies liegt in der Regel daran, dass DB_HOST in der .env auf 127.0.0.1 oder localhost eingestellt ist. Innerhalb des Docker-Netzwerks muss laravel.test den Compose-Servicenamen (z. B. mysql oder pgsql) als Hostnamen verwenden."
  - question: "Wie füge ich eine neue PHP-Erweiterung (wie MongoDB) zu Sail hinzu?"
    answer: "Sie müssen die Sail-Dockerfiles mit 'php artisan sail:publish' veröffentlichen, die Dockerfile für Ihre PHP-Version (z. B. pecl install mongodb) bearbeiten und das Image mit 'sail build --no-cache' neu erstellen."
  - question: "Wie bereinige ich die Datenbank, ohne andere Projekt-Volumes zu verlieren?"
    answer: "Anstatt 'sail down -v' auszuführen, was alle Volumes zerstört, ermitteln Sie den spezifischen Volume-Namen mit 'docker volume ls' und entfernen Sie dieses Volume einzeln mit 'docker volume rm <volume_name>'."
  - question: "Wie kann ich Migrationsfehler beim Starten von Sail verhindern?"
    answer: "Konfigurieren Sie Docker-Healthchecks für Ihre Datenbank in der docker-compose.yml und setzen Sie den Dienst 'laravel.test' in Abhängigkeit davon mit 'condition: service_healthy', sodass Migrationen warten, bis die Datenbank bereit ist."
---

# Sail: Datenbanken & Docker-Dienste

Sie führen `sail up -d` aus, geben `php artisan migrate` ein und erhalten eine rote `Connection refused`-Fehlermeldung. Obwohl Docker Desktop anzeigt, dass Ihr Datenbank-Container läuft und fehlerfrei ist, weigert sich die Anwendung darin, mit ihm zu kommunizieren. Dieser Verbindungsabbruch verdeutlicht eine grundlegende Regel der containerisierten Entwicklung: In Docker Compose bezieht sich `127.0.0.1` auf den Container selbst, nicht auf das Netzwerk.

Die Konfiguration externer Dienste (Datenbanken, Caches, Message Broker) für ein Laravel-Projekt kann schnell zu Abweichungen in den Umgebungen der verschiedenen Teams führen. Sail löst dies, indem es Ihre Dienste als unabhängige, reproduzierbare Container behandelt. Sie müssen jedoch deren Netzwerkanbindung, Build-Anforderungen und die Startreihenfolge korrekt konfigurieren.

In diesem Leitfaden zeigen wir Ihnen, wie Sie Datenbanken austauschen und konfigurieren (Postgres, MongoDB), Caches und Broker anbinden (Redis, RabbitMQ) sowie Healthchecks und Volume-Resets sicher handhaben.

**Navigation:** [Alle Tools](../) · [Sail-Übersicht](sail#what-sail-is) · [Queues](sail-queues#connections) · [Umgebung & Deployment](sail-env-deploy#forward-ports) · [Fehlerbehebung](sail-troubleshooting#wsl-filesync)

## Inhalt

* [Netzwerk- und Hostnamen-Regeln](#networking)
* [Redis-Dienst (vollständiges Beispiel)](#redis-service)
* [MySQL durch PostgreSQL ersetzen](#postgres-swap)
* [MongoDB + PHP-Erweiterung in Sail](#mongodb)
* [RabbitMQ als Container](#rabbitmq-sidecar)
* [Mailpit (SMTP-Entwicklung)](#mailpit)
* [Meilisearch / Typesense (optionale Suche)](#search-engines)
* [Healthchecks und Startreihenfolge](#healthchecks)
* [Named Volumes und Daten-Reset](#volumes)
* [Häufige Fehler](#common-mistakes)
* [Selbsttest-Fragen](#self-check)

---

<a id="networking"></a>
## Netzwerk- und Hostnamen-Regeln

Alle anwendungsseitigen Hosts in **`.env`** müssen **Compose-Servicenamen** sein (`mysql`, `pgsql`, `redis`, `mongo`), nicht `127.0.0.1`. Dies liegt daran, dass PHP **innerhalb** des `laravel.test`-Containers läuft und über ein isoliertes Docker-Netzwerk kommuniziert.

> [!NOTE]
> **Host-System vs. Container-Netzwerk**
> Von Ihrem Host-System aus (IDE, Terminal, DBeaver) verbinden Sie sich über `127.0.0.1` unter Verwendung des weitergeleiteten Ports (z. B. `FORWARD_DB_PORT`) mit den Datenbanken. Innerhalb der Container müssen sich die Dienste jedoch unter Verwendung ihrer Compose-Servicenamen ansprechen.

---

<a id="redis-service"></a>
## Redis-Dienst (vollständiges Beispiel)

Um Redis zu Ihrer Sail-Umgebung hinzuzufügen, definieren Sie den Dienst und mounten Sie ein Named Volume.

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
        healthcheck:
            test: ["CMD", "redis-cli", "ping"]
            retries: 3
            timeout: 5s
```

Registrieren Sie **`sail-redis`** unter dem übergeordneten Abschnitt **`volumes:`** und deklarieren Sie die Abhängigkeit innerhalb von **`laravel.test`**:

```yaml
# docker-compose.yml
services:
    laravel.test:
        depends_on:
            redis:
                condition: service_healthy
```

```dotenv
# .env
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Führen Sie `sail exec redis redis-cli ping` aus, um die Verbindung zu überprüfen.

---

<a id="postgres-swap"></a>
## MySQL durch PostgreSQL ersetzen

Wenn Ihr Produktionsserver PostgreSQL verwendet, ist die lokale Ausführung von MySQL ein unnötiges Risiko.

1. Kommentieren Sie den `mysql`-Dienst und das zugehörige Volume in der `docker-compose.yml` aus oder löschen Sie diese.
2. Fügen Sie den `pgsql`-Dienstblock unter Verwendung des Standard-Sail-Stubs hinzu:

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
        healthcheck:
            test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME} -d ${DB_DATABASE}"]
            retries: 3
            timeout: 5s
```

3. Aktualisieren Sie das `depends_on`-Array von `laravel.test`, um auf `pgsql` zu verweisen.
4. Aktualisieren Sie die **`.env`**:

```dotenv
# .env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

Bauen und starten Sie die Container mit `sail build --no-cache && sail up -d`.

---

<a id="mongodb"></a>
## MongoDB + PHP-Erweiterung in Sail

MongoDB wird von den Standard-Runtimes von Sail nicht von Haus aus unterstützt und erfordert die Installation der PHP-MongoDB-Erweiterung im Anwendungscontainer.

1. Fügen Sie den MongoDB-Dienst zu Ihrer Compose-Datei hinzu:

```yaml
# docker-compose.yml
services:
    mongo:
        image: 'mongo:7'
        ports:
            - '${FORWARD_MONGO_PORT:-27017}:27017'
        volumes:
            - 'sail-mongo:/data/db'
        networks:
            - sail
```

2. Registrieren Sie das Volume `sail-mongo` unter `volumes:`.
3. Veröffentlichen Sie die Sail-Dockerfiles, falls noch nicht geschehen:
   ```bash
   php artisan sail:publish
   ```
4. Bearbeiten Sie das Dockerfile der Runtime (z. B. `docker/8.3/Dockerfile`), um die PECL-Erweiterung zu installieren:

```dockerfile
# docker/8.3/Dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

5. Bauen Sie Ihre Sail-Images neu und starten Sie:
   ```bash
   sail build --no-cache
   sail up -d
   ```

---

<a id="rabbitmq-sidecar"></a>
## RabbitMQ als Container

Für Projekte, die fortgeschrittene AMQP-Features anstelle von Redis oder datenbankbasierten Queues erfordern, können Sie RabbitMQ als Sidecar-Container ausführen.

```yaml
# docker-compose.yml
services:
    rabbitmq:
        image: 'rabbitmq:3-management-alpine'
        hostname: rabbitmq
        ports:
            - '${FORWARD_RABBITMQ_PORT:-5672}:5672'
            - '${FORWARD_RABBITMQ_MANAGEMENT:-15672}:15672'
        volumes:
            - 'sail-rabbitmq:/var/lib/rabbitmq'
        networks:
            - sail
        environment:
            RABBITMQ_DEFAULT_USER: '${RABBITMQ_USER:-sail}'
            RABBITMQ_DEFAULT_PASS: '${RABBITMQ_PASSWORD:-password}'
```

> [!NOTE]
> Stellen Sie sicher, dass Sie `sail-rabbitmq` in Ihrer übergeordneten `volumes:`-Liste deklarieren. Greifen Sie lokal über `http://localhost:15672` mit Ihren benutzerdefinierten Anmeldedaten (z. B. `sail` / `password`) auf das Management-Dashboard zu.

---

<a id="mailpit"></a>
## Mailpit (SMTP-Entwicklung)

Sail enthält Mailpit, um ausgehende E-Mails abzufangen. Dies verhindert, dass während lokaler Tests versehentlich echte Nachrichten an Benutzer gesendet werden.

```dotenv
# .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

Alle von der Anwendung gesendeten E-Mails können über das Web-Interface unter `http://localhost:8025` gelesen werden.

---

<a id="search-engines"></a>
## Meilisearch / Typesense (optionale Suche)

Um eine blitzschnelle lokale Suche mit Laravel Scout zu aktivieren:

```bash
php artisan sail:install --with=meilisearch
```

Oder kopieren Sie den Meilisearch-Dienst-Stub in Ihre Compose-Konfiguration. Denken Sie daran, Ihre Umgebungsvariablen auf den Servicenamen zu verweisen:

```dotenv
# .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
```

---

<a id="healthchecks"></a>
## Healthchecks und Startreihenfolge

Standardmäßig startet Docker Compose Container parallel. Ein einfaches `depends_on: [mysql]` stellt nur sicher, dass der MySQL-Container *startet*, nicht jedoch, dass die Datenbank-Engine *vollständig bereit* ist, Verbindungen entgegenzunehmen.

Die Implementierung eines `healthcheck` beim Datenbankdienst und das Setzen von `condition: service_healthy` beim Anwendungsdienst stellt sicher, dass Migrationen während der Initialisierung fehlerfrei ausgeführt werden.

---

<a id="volumes"></a>
## Named Volumes und Daten-Reset

Named Volumes fungieren als persistente virtuelle Laufwerke.
- Das Ausführen von `sail down` stoppt Container, lässt Ihre Datenbank-Volumes jedoch unberührt.
- Das Ausführen von `sail down -v` zerstört **alle** Volumes und setzt Ihre lokale Umgebung vollständig zurück.
- Um nur eine Datenbank zurückzusetzen: Suchen Sie das Ziel-Volume mit `docker volume ls` und löschen Sie es mit `docker volume rm <volume_name>`.

---

## ⚠️ Häufige Fehler

**1. Verwendung von `127.0.0.1` oder `localhost` in der `.env` für Container-Verbindungen**
Das Setzen von `DB_HOST=127.0.0.1` führt dazu, dass Laravel die Datenbank im PHP-Container selbst sucht, was zu einem Verbindungsfehler führt.
*Lösung:* Verwenden Sie `DB_HOST=mysql` oder `DB_HOST=pgsql`.

**2. Destruktive Volume-Resets mit `sail down -v`**
Das Ausführen von `sail down -v`, wenn Sie lediglich Ihre Container stoppen möchten, löscht Ihre Datenbanken, Seeding-Daten und Redis-Keys.
*Lösung:* Verwenden Sie `sail down`, um Container sicher zu stoppen. Verwenden Sie `-v` nur, wenn Sie den Zustand vollständig zurücksetzen möchten.

**3. Ändern benutzerdefinierter Dockerfiles ohne Neuerstellung des Images**
Das Bearbeiten der veröffentlichten Runtime-Dockerfiles zur Installation von PHP-Erweiterungen (wie `mongodb` oder `gd`) hat keine Auswirkung, bis Sie einen Neubau erzwingen.
*Lösung:* Führen Sie nach dem Bearbeiten von Dockerfiles immer `sail build --no-cache` aus.

---

## 🧠 Selbsttest-Fragen

1. **Warum schlägt die Einstellung `DB_HOST=127.0.0.1` fehl, wenn Laravel-Migrationen innerhalb von Sail ausgeführt werden?**
2. **Wie unterscheidet sich `depends_on` mit `condition: service_healthy` von einem einfachen `depends_on`-Array?**
3. **Was passiert mit Ihren lokalen PostgreSQL-Daten, wenn Sie `sail down -v` ausführen?**
4. **Warum müssen Sie die Dockerfiles von Sail (über `sail:publish`) veröffentlichen, bevor Sie die MongoDB-Erweiterung verwenden können?**

<details>
<summary><b>Antworten anzeigen</b></summary>

1. Innerhalb von Docker Compose hat jeder Container sein eigenes Loopback-Netzwerkinterface. `127.0.0.1` bezieht sich auf den `laravel.test`-Container selbst, in dem die Datenbank nicht läuft. Sie müssen den Servicenamen des Datenbank-Containers als Hostnamen verwenden.
2. Ein einfaches `depends_on` prüft nur, ob der Container gestartet wurde (in einem `running`-Zustand ist). Der Modifikator `condition: service_healthy` verhindert den Start des abhängigen Containers, bis die Datenbank ihren internen Bereitschafts-Healthcheck besteht (z. B. bereit ist, Socket-Verbindungen anzunehmen).
3. Alle lokalen PostgreSQL-Daten werden dauerhaft gelöscht, da das Volume, das `/var/lib/postgresql/data` speichert, gelöscht wird.
4. Sail verwendet vorkonfigurierte generische Docker-Images. Um benutzerdefinierte PECL-Erweiterungen zu installieren, müssen Sie auf die zugrunde liegende Dockerfile-Konfiguration zugreifen, diese ändern und ein lokales Image erstellen.
</details>
