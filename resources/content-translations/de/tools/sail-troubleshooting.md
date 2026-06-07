---
title: "Laravel Sail: Fehlerbehebung für WSL2, Berechtigungen, Ports, Rebuilds, OPcache & Vite | DevSense"
description: "Beheben Sie häufige Probleme mit Laravel Sail: WSL2-Dateisynchronisierung, UID/GID- und Speicherberechtigungen, FORWARD_*-Portkonflikte, veraltete Docker-Layer, OPcache und Xdebug in der Entwicklung, npm/Vite-Aufteilung von Host und Container sowie sichere Volume-Resets."
faq:
  - question: "Warum ist die Vite-Asset-Kompilierung auf Windows mit WSL2 extrem langsam?"
    answer: "Dies wird normalerweise dadurch verursacht, dass Ihre Projektdateien auf dem Windows-Dateisystem (/mnt/c/...) liegen. Um dies zu beheben, verschieben Sie Ihren Projektordner in das native Linux-Dateisystem (z. B. ~/projects/) innerhalb von WSL2."
  - question: "Wie behebe ich Fehler wegen fehlender Berechtigungen (permission denied) im storage-Verzeichnis?"
    answer: "Führen Sie 'sail artisan cache:clear' aus und konfigurieren Sie die Umgebungsvariablen WWWUSER und WWWGROUP in Ihrer .env-Datei entsprechend der Benutzer-ID Ihres Host-Systems, damit von CLI-Tools in Containern erstellte Dateien die korrekten Besitzrechte behalten."
  - question: "Warum werden meine Codeänderungen im laufenden Container nicht aktualisiert?"
    answer: "Wenn es sich um eine Web-Änderung handelt, ist möglicherweise OPcache in der PHP-Konfiguration aktiviert. Wenn es sich um einen Queue-Worker handelt, hat dieser den Bootstrap im Speicher gecacht. Wenn es sich um eine Änderung der Dockerfile-Konfiguration handelt, müssen Sie 'sail build --no-cache' ausführen und die Container neu starten."
  - question: "Wie überprüfe ich, welche Erweiterungen in meiner Sail PHP-Umgebung geladen sind?"
    answer: "Führen Sie den Befehl 'sail exec laravel.test php -m' aus, um alle aktiven PHP-Module aufzulisten, die in der Container-Laufzeitumgebung ausgeführt werden, da sich diese von Ihrem Host-System unterscheiden können."
---

# Sail: Fehlerbehebung & Performance

Ihre Anwendung lief gestern noch einwandfrei, aber heute wirft `sail up` einen Portbelegungskonflikt, Assets lassen sich über Vite nicht kompilieren und Datenbank-Schreibvorgänge stürzen mit Berechtigungsfehlern ab. Wenn Sail fehlschlägt, ist dies selten ein Fehler von Laravel selbst; es handelt sich fast immer um einen Reibungspunkt zwischen der virtualisierten Container-Umgebung und dem Dateisystem, dem Netzwerk oder dem Prozessstatus Ihres Host-Rechners.

Virtualisierungsebenen (insbesondere WSL2 unter Windows) und Container-Netzwerk-Namespaces bringen einzigartige Eigenheiten mit sich. Das Verständnis dafür, wie man diese Blockaden diagnostiziert und umgeht, ist entscheidend, um einen schnellen, nahtlosen Entwicklungs-Workflow aufrechtzuerhalten.

In diesem Leitfaden sammeln wir die häufigsten Sail-Symptome, erklären, warum sie auftreten, und liefern direkte Befehle, um sie zu beheben.

**Navigation:** [Alle Tools](../) · [Sail-Übersicht](sail#what-sail-is) · [Datenbanken](sail-databases#networking) · [Queues](sail-queues#connections) · [Umgebung & Deployment](sail-env-deploy#forward-ports)

## Inhalt

* [WSL2, Docker Desktop und Dateisynchronisierung](#wsl-filesync)
* [Berechtigungen, `storage` und `vendor`](#permissions)
* [Port bereits vergeben / `FORWARD_*`](#ports)
* [Diskrepanzen zwischen Host und Container](#host-vs-container)
* [Veralteter Code: Rebuilds und Layer](#rebuild)
* [OPcache und Autoload in der lokalen Entwicklung](#opcache)
* [Xdebug fühlt sich langsam an](#xdebug)
* [Vite, npm und Node innerhalb vs. außerhalb von Sail](#frontend)
* [Logs und schnelle Diagnosen](#logs)
* [Wann Volumes zurückgesetzt werden sollten (Datenverlust)](#volumes)
* [Häufige Fehler](#common-mistakes)
* [Selbsttest-Fragen](#self-check)

---

<a id="wsl-filesync"></a>
## WSL2, Docker Desktop und Dateisynchronisierung

Unter Windows + WSL2 führt das Mounten von Dateien aus der Windows-Partition (`/mnt/c/...`) in Linux-Container zu massiven Engpässen beim Festplatten-I/O. Vite- und Webpack-HMR-Dateiwächter (Hot-Module Replacement) erhalten dann oft keine Benachrichtigungen über Dateiänderungen.

Speichern Sie Ihre Projektordner vollständig **im nativen WSL-Dateisystem** (z. B. `~/projects/...`). Öffnen Sie sie in VS Code mithilfe der **WSL-Erweiterung**, um sicherzustellen, dass Dateisynchronisierungen und Kompilierungen Millisekunden statt Sekunden dauern.

---

<a id="permissions"></a>
## Berechtigungen, `storage` und `vendor`

Laravel benötigt Schreibrechte für `storage/` und `bootstrap/cache/`. Wenn Docker als Root-Benutzer ausgeführt wird, werden im Container erstellte Dateien auf Ihrem Host-System schreibgeschützt.

Überprüfen Sie die Berechtigungen innerhalb des Containers:

```bash
# Terminal
sail exec laravel.test ls -la storage bootstrap/cache
```

> [!NOTE]
> **UID- und GID-Mapping**
> Stimmen Sie bei Linux-Hosts Ihre Host-UID und -GID ab, indem Sie die Variablen `WWWUSER` und `WWWGROUP` in Ihrer Umgebung definieren:
> ```dotenv
> # .env
> WWWUSER=1000
> WWWGROUP=1000
> ```
> Starten Sie Sail nach Änderungen neu.

---

<a id="ports"></a>
## Port bereits vergeben / `FORWARD_*`

Wenn ein anderer Dienst (wie eine lokale Datenbank oder eine andere laufende Sail-Instanz) bereits Ports wie `3306` oder `80` belegt, erhalten Sie einen `address already in use`-Fehler.

Definieren Sie alternative Ports in Ihrer `.env`:

```dotenv
# .env
APP_PORT=8081
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
```

Führen Sie `sail down && sail up -d` aus, um die Änderungen anzuwenden.

---

<a id="host-vs-container"></a>
## Diskrepanzen zwischen Host und Container

Ein häufiger Fehler besteht darin, Befehle wie `php artisan migrate` oder `composer install` direkt auf dem Host-System auszuführen. Wenn Ihr Host eine andere PHP-Version verwendet oder Erweiterungen (wie `redis` oder `mongodb`) fehlen, schlägt der Befehl fehl oder beschädigt Ihre Lock-Datei.

- **Host (Schlecht):** `composer install`
- **Sail (Gut):** `sail composer install`

---

<a id="rebuild"></a>
## Veralteter Code: Rebuilds und Layer

Wenn Sie Abhängigkeiten, PHP-Laufzeitumgebungen oder benutzerdefinierte Pakete in Ihren veröffentlichten Dockerfiles ändern, erstellt Docker Ihre Images nicht automatisch neu.

Erzwingen Sie einen vollständigen Image-Rebuild ohne Verwendung gecachter Layer:

```bash
# Terminal
sail build --no-cache
sail up -d
```

---

<a id="opcache"></a>
## OPcache und Autoload in der lokalen Entwicklung

Wenn Codeänderungen nicht im Browser angezeigt werden, prüfen Sie, ob OPcache im Container aktiv ist. In der Entwicklung sollte OPcache bei jedem Request die Zeitstempel validieren.

Stellen Sie sicher, dass Folgendes in der PHP-Konfiguration Ihres Containers eingestellt ist:

```ini
# php.ini
opcache.validate_timestamps=1
opcache.revalidate_freq=0
```

Wenn Sie neue Klassen hinzufügen und Namespace-Fehler erhalten, führen Sie `sail composer dump-autoload` aus.

---

<a id="xdebug"></a>
## Xdebug fühlt sich langsam an

Xdebug fügt jedem Request einen Verarbeitungs-Overhead hinzu. Deaktivieren Sie es, wenn Sie nicht aktiv debuggen, indem Sie Folgendes einstellen:

```dotenv
# .env
SAIL_XDEBUG_MODE=off
```

Starten Sie Sail mit `sail down && sail up -d` neu.

---

<a id="frontend"></a>
## Vite, npm und Node innerhalb vs. außerhalb von Sail

Sie können Frontend-Assets entweder auf dem Host-System oder innerhalb von Sail kompilieren.

- **Host-Kompilierung (Am schnellsten):** Führen Sie `npm run dev` in Ihrem lokalen Terminal aus.
- **Container-Kompilierung (Keine Einrichtung erforderlich):** Führen Sie `sail npm run dev` aus.

Stellen Sie sicher, dass Ihre `APP_URL` und die Vite-Ports übereinstimmen, da es sonst zu HMR-Verbindungsfehlern in Ihrer Konsole kommt.

---

<a id="logs"></a>
## Logs und schnelle Diagnosen

Führen Sie diese Befehle aus, um den Zustand der Container und die PHP-Status zu überprüfen:

```bash
# Terminal
sail logs -f laravel.test
docker compose ps
sail exec laravel.test php -v
sail exec laravel.test php -m
```

---

<a id="volumes"></a>
## Wann Volumes zurückgesetzt werden sollten (Datenverlust)

Wenn Ihre lokale Datenbank beschädigt wird oder Schema-Updates Probleme verursachen, setzen Sie das Volume zurück. Achtung: **`sail down -v` löscht alle Datenbanktabellen und Redis-Cache-Keys.**

So setzen Sie nur ein bestimmtes Datenbank-Volume sicher zurück:
1. Volumes auflisten: `docker volume ls`
2. Ziel-Volume entfernen: `docker volume rm <volume_name>`

---

## ⚠️ Häufige Fehler

**1. Mounten von Dateien über Windows-Partitionsgrenzen hinweg**
Speichern Ihres Projektordners in `C:/Users/...` und Verwenden von WSL2 zum Ausführen von Sail. Dies verringert die I/O-Geschwindigkeit um das bis zu 10-Fache.
*Lösung:* Verschieben Sie die Dateien in das native Linux-Verzeichnis, wie z. B. `\\wsl$\Ubuntu\home\ubuntu\projects\`.

**2. Fehlende PHP-Erweiterungen in Containern**
Ausführen einer Datenbankmigration, die PostgreSQL verwendet, während Ihre Container-Laufzeitumgebung nicht mit dem PostgreSQL-Treiber kompiliert wurde.
*Lösung:* Überprüfen Sie die laufenden Module mit `sail exec laravel.test php -m`. Veröffentlichen Sie die Dockerfiles und bauen Sie sie neu auf, falls eine Erweiterung fehlt.

**3. Port-Kollision auf localhost**
Gleichzeitiges Ausführen von zwei Sail-Anwendungen, ohne den `APP_PORT` in einer der `.env`-Konfigurationen anzupassen.
*Lösung:* Passen Sie `APP_PORT` und die `FORWARD_*`-Portvariablen in der `.env` des zweiten Projekts an.

---

## 🧠 Selbsttest-Fragen

1. **Warum sollten Projektdateien in WSL2 nicht im Pfad `/mnt/c/` gespeichert werden?**
2. **Welchen Befehl führen Sie aus, um Ihre Sail-Images nach dem Ändern einer veröffentlichten Dockerfile neu zu erstellen?**
3. **Richtig oder Falsch? Das Ausführen von `composer install` im Host-Terminal ist immer sicher, wenn Sie dieselbe PHP-Version wie Sail installiert haben.**
4. **Wie deaktivieren Sie Xdebug in Sail, wenn Sie es nicht verwenden, um Verlangsamungen zu vermeiden?**

<details>
<summary><b>Antworten anzeigen</b></summary>

1. Der Zugriff auf Dateien über die Windows-Dateisystemgrenze (`/mnt/c/`) hinweg führt zu schweren Virtualisierungs-Übersetzungsschichten, was Festplattenoperationen verlangsamt und Dateiänderungs-Listener stört.
2. Führen Sie `sail build --no-cache` gefolgt von `sail up -d` aus.
3. **Falsch.** Selbst wenn die PHP-Minor-Version übereinstimmt, fehlen auf Ihrem Host-System möglicherweise erforderliche Kompilierbibliotheken oder PHP-Erweiterungen, die im PHP des Containers vorhanden sind. Führen Sie Befehle immer über Sail aus.
4. Setzen Sie `SAIL_XDEBUG_MODE=off` in Ihrer `.env`-Datei und starten Sie die Sail-Container neu.
</details>
