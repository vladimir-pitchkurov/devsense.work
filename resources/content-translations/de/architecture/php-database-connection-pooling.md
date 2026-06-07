---
title: "PHP-Anwendungen und der Engpass bei Datenbank-Verbindungspools | DevSense"
description: "Warum PHP-FPM und Worker Datenbank-Sitzungen vervielfachen, wie Middle-Tier-Pooler und Proxys echte Serververbindungen teilen und was Laravel-Teams über PgBouncer-Modi, ProxySQL und Prepared Statements wissen sollten."
published: 2026-04-14
faq:
  - question: "Was ist der Hauptunterschied zwischen Session-Pooling und Transaction-Pooling in PgBouncer?"
    answer: "Session-Pooling weist einem Client eine Serververbindung für die gesamte Dauer seiner Verbindung zu und gibt sie erst frei, wenn der Client die Verbindung trennt. Transaction-Pooling gibt die Serververbindung unmittelbar nach jeder Transaktion (`COMMIT` oder `ROLLBACK`) an den Pool zurück. Transaction-Pooling ermöglicht wesentlich höhere Client-zu-Server-Verhältnisse, bricht jedoch den Status auf Sitzungsebene wie temporäre Tabellen, Advisory Locks und dauerhafte Einstellungen."
  - question: "Warum schlagen Prepared Statements manchmal fehl, wenn Transaction-Pooling verwendet wird?"
    answer: "Unter Transaction-Pooling können aufeinanderfolgende Abfragen in derselben Client-Sitzung an unterschiedliche Datenbank-Server-Backends geleitet werden. Wenn die Client-Abfrage A ein Statement auf Backend 1 vorbereitet (prepares) und Abfrage B versucht, dieses Statement auf Backend 2 auszuführen, schlägt die Ausführung fehl, da Backend 2 keine Kenntnis davon hat."
  - question: "Wie wirkt sich das Prozessmodell von PHP-FPM auf Datenbankverbindungen aus im Vergleich zu Node.js oder Go?"
    answer: "PHP-FPM arbeitet mit einem Prozess-pro-Anfrage-Modell, bei dem jeder Child-Prozess jeweils eine Anfrage verarbeitet und Ressourcen normalerweise am Ende der Anfrage schließt. In Systemen mit hohem Datenverkehr führt dies zu einem 'Verbindungssturm' (wiederholte TCP-Handshakes und Authentifizierungen). Node.js und Go hingegen verwenden asynchrone Single-Prozess-Raufzeitumgebungen, die einen einzigen, langlebigen Pool von Datenbankverbindungen verwalten, der von Tausenden von gleichzeitigen Anfragen gemeinsam genutzt wird."
  - question: "Behebt ein Verbindungspooling langsame Datenbankabfragen?"
    answer: "Nein. Verbindungspooling löst nur den Overhead für den Aufbau von Verbindungen und verhindert das Überschreiten von Verbindungslimits auf dem Datenbankserver. Es beschleunigt weder die langsame SQL-Ausführung noch behebt es fehlende Indizes oder reduziert die CPU-/Festplattenlast, die durch nicht optimierte Abfragen verursacht wird."
---

# PHP-Anwendungen und der Datenbank-Verbindungsengpass: Pooler, Proxys und die Realität

In vielen Stacks ist die Datenbank schnell genug und die Abfragen sind vernünftig – und dennoch stößt die Produktion auf Fehler wie **`too many connections`**, **`remaining connection slots are reserved`** oder **mysteriöse Verzögerungen** direkt nach einem Deployment. Der Übeltäter ist oft nicht langsames SQL, sondern die **Verbindungs-Arithmetik**: Das Request-Modell von PHP erzeugt **Stoßzeiten von Connect + Auth + TLS**, und die Datenbank hat eine **harte Obergrenze** für gleichzeitige Backends. Middle-Tier-**Pooler** und **Managed Proxys** existieren genau deshalb, um eine **kleine, stabile Gruppe von serverseitigen Sitzungen** hinter einer **großen Anzahl kurzlebiger PHP-Clients** bereitzustellen.

**Verwandte Leitfäden:** [Databases under load: queries & scaling](database-performance-and-scaling) · [Observability and monitoring](observability-monitoring-laravel)

## Inhalt

* [Warum PHP das Problem verstärkt](#why-php)
* [Wodurch Sie tatsächlich eingeschränkt sind](#limits)
* [Middle-Tier-Pooler und Proxys](#middle-tier)
* [PostgreSQL: PgBouncer in der Praxis](#pgbouncer)
* [MySQL und MariaDB: ProxySQL und Alternativen](#proxysql)
* [Managed Proxys (RDS Proxy u.a.)](#managed)
* [Andere Pooler: PgCat, Odyssey, pgpool-II](#other-tools)
* [Laravel-spezifische Hinweise](#laravel)
* [Was Pooler *nicht* beheben](#not-a-cure)
* [Häufige Fehler](#common-mistakes)
* [Checkliste](#checklist)
* [Selbsttest-Quiz](#self-test-quiz)

---

<a id="why-php"></a>
## Warum PHP das Problem verstärkt

Das klassische **PHP-FPM** verarbeitet eine Anfrage, kommuniziert mit Diensten, gibt eine Antwort zurück und baut die für die Anfrage reservierten Ressourcen wieder ab. Sofern Sie keine **dauerhaften Verbindungen (Persistent Connections)** verwenden (und deren Nachteile akzeptieren), öffnet oder prüft jede Anfrage, die die Datenbank berührt, normalerweise eine TCP-Sitzung, **authentifiziert sich**, verhandelt optional **TLS** und führt dann Abfragen aus.

Unter Last:

* **`pm.max_children`** unter FPM definiert, wie viele PHP-Prozesse **gleichzeitig** auf diesem Server laufen können. Wenn die meisten Anfragen die Datenbank kontaktieren, benötigen Sie **bis zu dieser Anzahl** gleichzeitige DB-Sitzungen **pro Worker-Server**.
* **Queue-Worker** (`queue:work`, Horizon) sind **langlebige Prozesse** – jeder aktive Worker hält oft **eine oder mehrere** offene Verbindungen, während Jobs ausgeführt werden.
* **Horizontale Skalierung** vervielfacht alles: Drei Anwendungs-Knoten mit jeweils achtzig Children ergeben **240** potenzielle Sitzungen, noch bevor Worker, Scheduler und einmalige CLI-Tasks gezählt werden.

Die Datenbank sieht **Verbindungsstürme (Connection Storms)** bei Deployments und Traffic-Spitzen: Hunderte von Handshakes in Sekunden. Selbst wenn `max_connections` hoch genug ist, werden der **Speicher pro Backend** (insbesondere bei Postgres) und die **CPU-Leistung für die Authentifizierung** zur tatsächlichen Grenze.

---

<a id="limits"></a>
## Wodurch Sie tatsächlich eingeschränkt sind

* **`max_connections` (Postgres)** / **`max_connections` (MySQL)** – eine globale Obergrenze. Reservierte Slots für Superuser und Replikation können den für Anwendungen verfügbaren Platz verringern.
* **Arbeitsspeicher (RAM)** – Jedes Server-Backend beansprucht Puffer und Statusinformationen; ein einfaches „Erhöhen des Limits“ kann den Server per **OOM** (Out of Memory) abstürzen lassen.
* **Verbindungslatenz** – TLS + Passwortüberprüfung + optionales LDAP fügen jeder Anfrage **Millisekunden bis zu Dutzenden von Millisekunden** hinzu, wenn Sie sich jedes Mal neu verbinden.
* **Thundering Herd** – Nach einem Neustart versucht möglicherweise jeder PHP-Prozess **gleichzeitig** eine Verbindung aufzubauen, was die Accept-Queue oder den Authentifizierungspfad überlastet.

> [!NOTE]
> **Arithmetik der Gesamtlast**
> Faustregel: Zählen Sie **alle** Programme, die SQL sprechen (Web, Worker, Cron, Admin-Tools, BI), nicht nur HTTP. Jede Umgebung trägt zum Gesamt-Datenbank-Footprint bei.

---

<a id="middle-tier"></a>
## Middle-Tier-Pooler und Proxys

Ein **Pooler** sitzt **zwischen** PHP und der Datenbank. PHP öffnet eine ressourcenschonende Verbindung **zum Pooler**; der Pooler hält einen **kleineren Pool** echter Verbindungen zu Postgres/MySQL aufrecht und **verwendet diese über viele Clients hinweg wieder**.

### Vorteile
* Weniger **Server-Backends** und weniger **RAM-Verbrauch** auf dem Datenbank-Host.
* **Multiplexing**: Viele inaktive (idle) PHP-Clients belegen nicht jeweils eine inaktive Server-Sitzung.
* Stabileres Verhalten bei **stoßartigem (spiky)** Traffic.

### Kosten und Fallstricke
* Ein zusätzlicher **Hop** (Latenz, Ausfallrisiko, zusätzliche Konfiguration zur Absicherung und Überwachung).
* **Sitzungssemantiken** ändern sich je nach Pooling-**Modus** – siehe PgBouncer unten.
* Sie müssen den Pooler dennoch so dimensionieren, dass er nicht zum **neuen** Engpass wird (CPU, Dateideskriptoren, Pool-Erschöpfung).

---

<a id="pgbouncer"></a>
## PostgreSQL: PgBouncer in der Praxis

**PgBouncer** ist der De-facto-Standard für Postgres-Pooling in PHP-Stacks.

Häufige **Pool-Modi**:

| Modus | Verhalten | Eignung für PHP / Laravel |
|------|----------|-------------------|
| **Session** | Eine Serververbindung für die gesamte Client-Sitzung bis zur Trennung. | Sicherste Kompatibilität: `SET`, `LISTEN`, Advisory Locks, temporäre Tabellen und Prepared Statements funktionieren. **Geringster Multiplexing-Gewinn**, wenn Clients lange verbunden bleiben (Worker) oder Sie ohnehin pro Anfrage neu verbinden. |
| **Transaction** | Serververbindung wird **nach jeder Transaktion** (COMMIT/ROLLBACK) an den Pool zurückgegeben. | **Starkes Multiplexing** für kurze Web-Anfragen. Bricht **sitzungsbezogene** Funktionen: `SET LOCAL` über mehrere Roundtrips hinweg ohne Transaktion, `LISTEN`, langlebige temporäre Tabellen und einige **Prepared Statement**-Muster, sofern nicht sorgfältig konfiguriert. |
| **Statement** | Serververbindung wird nach **jedem Statement** freigegeben. | Selten für ORMs verwendet; unterbricht Transaktionen mit mehreren Statements. Kein typisches Laravel-Ziel. |

### Prepared Statements und Transaction-Pooling

Viele Treiber bereiten Statements **namentlich** auf der Sitzung vor. Wenn sich die physische Serververbindung unter Ihnen ändert, können **namentliche Prepares** fehlschlagen. In der Produktion genutzte Gegenmaßnahmen:

* Bevorzugen Sie für diesen Hop **namenlose** Prepares / das **Simple Query**-Protokoll, oder
* **Deaktivieren** Sie serverseitige Prepares für die Pooler-Verbindung (treiberspezifisch; oft `PDO::ATTR_EMULATE_PREPARES` oder Framework-Optionen).

```php
// config/database.php
'connections' => [
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '5432'),
        // ...
        'options' => [
            PDO::ATTR_EMULATE_PREPARES => true, // Emuliert Prepared Statements lokal in PHP
        ],
    ],
],
```

---

<a id="proxysql"></a>
## MySQL und MariaDB: ProxySQL und Alternativen

**ProxySQL** ist eine beliebte Middleware für das **MySQL-Protokoll**: Routing, Abfrageregeln, Read/Write-Split und **Verbindungspooling** mit Multiplexing-Regeln, die pro Benutzer/Schema angepasst sind.

Teams nutzen es für Folgendes:
* Begrenzung der **Backend-Verbindungen**, während sich viele PHP-FPM-Children mit ProxySQL verbinden.
* Routing von **Lesevorgängen (Reads)** an Replikate mit expliziten Regeln (beachten Sie dennoch den **Replikations-Lag**).
* Abfangen oder Umschreiben bestimmter Abfragemuster (mit Vorsicht – Logik im Proxy bedeutet zusätzliche **betriebliche Komplexität**).

**MySQL Router** (InnoDB Cluster) und einige **Cloud-Load-Balancer** bieten ein ähnliches Pooling-Verhalten, aber **prüfen Sie die Dokumentation**: Nicht jede Schicht multiplexiert auf dieselbe Weise wie ProxySQL.

**MariaDB MaxScale** kann je nach Edition und Modulen als Router mit Verbindungsverwaltungsfunktionen fungieren – prüfen Sie die **Lizenzierung** und Funktionen für Ihr Deployment.

---

<a id="managed"></a>
## Managed Proxys (RDS Proxy u.a.)

Cloud-Anbieter bieten **verwaltete Verbindungsproxys** vor RDS, Aurora, Cloud SQL usw. an. Diese übernehmen typischerweise:
* **Pooling** und Integration von **IAM- oder Token-Authentifizierung**.
* **Failover-Freundlichkeit** (Wiederherstellung von Backend-Verbindungen, ohne alle PHP-Prozesse gleichzeitig neu verbinden zu müssen).

Sie folgen dennoch den **Semantiken der Datenbank**: Wenn das Produkt aggressiv multiplexiert, stehen Sie vor den gleichen Einschränkungen für **Prepared Statements** und den **Sitzungsstatus** wie beim selbst gehosteten PgBouncer – lesen Sie die **Service-Matrix** für Ihre Engine und Ihren Treiber.

---

<a id="other-tools"></a>
## Andere Pooler: PgCat, Odyssey, pgpool-II

* **PgCat** und **Odyssey** – Postgres-Pooler mit wachsender Anhängerschaft; vergleichen Sie **Pool-Modi**, Metriken und Treibereigenheiten mit PgBouncer, bevor Sie wechseln.
* **pgpool-II** – wird häufig für **Replikation** und Routing ebenso wie für Pooling eingesetzt; **betrieblich aufwendiger** als PgBouncer, wenn Sie nur Multiplexing benötigen.

---

<a id="laravel"></a>
## Laravel-spezifische Hinweise

* **`config/database.php`** – In `connections.*.options` und Treiber-Flags passen Sie das **PDO**-Verhalten an Ihren Pooler an (z. B. Emulation von Prepares, falls erforderlich).
* **Read/Write-Splitting** – Laravel kann Selects an `read`-Hosts senden; stellen Sie in Kombination mit einem Pooler sicher, dass die **Sticky**-Semantiken Ihren Erwartungen entsprechen (Replikat-Lag vs. `sticky`-Konfiguration).
* **Octane / Swoole / FrankenPHP** – **langlebige** Worker verändern die Kalkulation: Dauerhafte Verbindungen können **gut funktionieren**, aber Sie müssen das **Durchsickern** von Verbindungsstatus zwischen Anfragen verhindern und die **Idle-Timeouts** auf dem Server und dem Pooler überwachen.
* **Horizon / `queue:work`** – Gleichzeitigkeit × Worker erhöht die Anzahl der **dauerhaften** Verbindungen; erstellen Sie einen Pool **pro Worker** oder nutzen Sie den **Transaction-Modus** mit kompatiblen Einstellungen.
* **Telescope, Nightwatch und Debug-Bars** in der Produktion können Transaktionen länger offen halten, als Sie denken – schränken Sie diese **nur auf Nicht-Produktionsumgebungen** ein.

Beispiel für eine Umgebungskonfiguration mit einem Pooler:
```env
# .env
# PHP verbindet sich mit PgBouncer auf Port 6432; PgBouncer verbindet sich mit Postgres auf Port 5432
DB_HOST=pgbouncer.internal
DB_PORT=6432
DB_DATABASE=app
DB_USERNAME=app_rw
```

---

<a id="not-a-cure"></a>
## Was Pooler *nicht* beheben

* **N+1-Abfragen** und fehlende Indizes verbrauchen weiterhin **CPU und I/O** auf dem Server – Pooling begrenzt nur, **wie viele Sitzungen** diese Last gleichzeitig verursachen können.
* **Lange Transaktionen** blockieren Server-Backends im Pool – Ihre Vorteile aus dem **Transaction-Modus** verpuffen, wenn der Code Transaktionen während externer HTTP-Aufrufe offen hält.
* **Globale Sperren (Locks) und Migrationen** – Das Ausführen von `migrate` über einen ausgelasteten Pooler kann sich negativ auf **Sperren** auswirken; einige Teams nutzen einen **direkten** Admin-Pfad für DDL (Data Definition Language).

---

<a id="common-mistakes"></a>
## Häufige Fehler

1. **Transaction-Pooling mit Session-Variablen**: Setzen von sitzungsspezifischen Konfigurationen (wie `SET TIMEZONE` oder Verwendung temporärer Tabellen) in einer PgBouncer-Umgebung mit Transaction-Pooling, was zu durchsickernden Einstellungen auf andere Client-Sitzungen führt.
2. **Vergessen der Prepare-Emulation**: Versäumnis, `PDO::ATTR_EMULATE_PREPARES => true` bei der Verwendung von Transaction-Pooling zu setzen, was zu Ausnahmen wie „prepared statement already exists“ oder „prepared statement not found“ führt.
3. **Skalieren der Pooler-Limits über die Datenbank-Grenzen hinaus**: Einstellen von PgBouncers `max_client_conn` und der Backend-Poolgröße auf Werte, die größer als der physische PostgreSQL-Wert `max_connections` sind.
4. **Falsche persistente Verbindungen bei FPM**: Aktivieren von `PDO::ATTR_PERSISTENT` auf Webservern, ohne die Lebensdauer der FPM-Child-Prozesse zu verwalten, wodurch inaktive Verbindungen für immer offen bleiben.

---

<a id="checklist"></a>
## Checkliste

1. **Erfassen** Sie jeden Prozesstyp, der SQL-Verbindungen öffnet (FPM max children × Knoten, Horizon-Worker, Cron, CLI).
2. Vergleichen Sie die Gesamtsummen mit **`max_connections`** und dem **RAM pro Verbindung** auf der DB – entscheiden Sie zuerst über **Pooler vs. mehr Disziplin in der Anwendung**.
3. Wählen Sie den **Pool-Modus** (Postgres) oder die **Multiplexing-Regeln** (MySQL), die zu den Fähigkeiten von **ORM + Treiber** passen.
4. Validieren Sie **Prepared Statements** und **Session-Funktionen** (`SET`, temporäre Tabellen, Advisory Locks) unter Lasttests.
5. Überwachen Sie die **Wartezeit des Poolers (Wait Time)** und die **aktiven Serververbindungen** – wenn die Warteschlange des Poolers wächst, ist immer noch die Datenbank oder die Abfragemischung die Grenze.

---

## Zusammenfassung

Middle-Tier-Pooler sind **Infrastrukturkomponenten, die Sie betreiben** (oder einkaufen). Richtig eingesetzt verwandeln sie „PHP hat 800 Verbindungen geöffnet“ in „Postgres sieht 60 aktive Backends“ – genau die Form, für die die meisten OLTP-Datenbanken konzipiert wurden.

---

<a id="self-test-quiz"></a>
## Selbsttest-Quiz

### Frage 1: Was passiert, wenn Sie versuchen, PostgreSQL Advisory Locks über einen im Transaction-Pooling-Modus laufenden PgBouncer zu verwenden?
- A) Die Sperren funktionieren korrekt, da PgBouncer sie abfängt.
- B) Die Sperren blockieren möglicherweise die falsche Sitzung oder gehen lautlos verloren, wenn die Verbindung das Backend wechselt.
- C) Eine sofortige SQL-Ausnahme wird vom PgBouncer-Parser ausgelöst.

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort: B**
Advisory Locks sind an die physische Backend-Sitzung gebunden. Im Transaktionsmodus wird Ihre nächste Abfrage möglicherweise an eine andere physische Verbindung geleitet, was bedeutet, dass die Sperre auf Ihrer Seite verloren geht, während sie auf dem ursprünglichen Backend aktiv bleibt.
</details>

---

### Frage 2: Warum erzeugt PHP-FPM im Vergleich zu persistenten Worker-Laufzeitumgebungen wie Go oder Node.js Verbindungsstürme?
- A) PHP-FPM-Prozesse unterstützen kein TCP.
- B) PHP-FPM beendet den Request-Status am Ende der Ausführung, wodurch Datenbank-Handles wiederholt geschlossen und neu geöffnet werden.
- C) Node.js und Go verwenden maßgeschneiderte Datenbank-Engines.

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort: B**
Da PHP-FPM auf die Anfrage begrenzt (request-scoped) arbeitet, werden Verbindungen bei jeder Anfrage neu ausgehandelt und abgebaut, es sei denn, persistente Handles sind sorgfältig konfiguriert.
</details>
