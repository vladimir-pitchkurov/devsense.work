---
title: "Webangriffe und Abwehrmassnahmen: XSS, CSRF, SQLi, SSRF, IDOR und Dateiuploads | DevSense"
description: "Die am häufigsten anzutreffenden Webangriffe: Injections (SQL/Command), XSS, CSRF, IDOR/Rechteprüfungsfehler, SSRF, unsichere Dateiuploads, Clickjacking und Konfigurationsfallen. Praktische Abwehrmethoden, Header und Checklisten."
published: 2026-04-14
faq:
  - question: "Warum reicht die Bereinigung von HTML-Eingaben nicht aus, um SQL-Injection zu verhindern?"
    answer: "SQL-Injection tritt auf, wenn nicht vertrauenswürdige Daten in Datenbankabfragen verkettet werden. Die Bereinigung von HTML entfernt zwar Tags wie `<script>`, verhindert jedoch nicht, dass Zeichen wie Anführungszeichen oder Semikolons die Struktur einer SQL-Abfrage verändern. Die einzige robuste Lösung ist die Verwendung parametrisierter Abfragen (Prepared Statements)."
  - question: "Können SameSite-Cookies CSRF-Token vollständig ersetzen?"
    answer: "Obwohl `SameSite=Lax` oder `Strict` in modernen Browsern vor cross-site Anfragen schützt, bieten sie keine vollständige Sicherheitsgarantie. Ältere Browser unterstützen SameSite möglicherweise nicht, und Fehler oder GET-basierte Zustandsänderungen können dies umgehen. CSRF-Token bleiben für eine tiefgestaffelte Verteidigung (Defense-in-Depth) unerlässlich."
  - question: "Wie unterscheidet sich SSRF von CSRF?"
    answer: "CSRF zwingt den Browser eines Benutzers, Anfragen an eine Anwendung zu senden, bei der er authentifiziert ist. SSRF zwingt den *Server* der Anwendung, nicht autorisierte Anfragen an interne Ressourcen (wie Metadaten-Endpunkte oder internes Redis) zu senden, die aus dem öffentlichen Internet nicht erreichbar sind."
  - question: "Warum ist die reine Überprüfung der Dateiendung bei Dateiuploads unsicher?"
    answer: "Angreifer können Überprüfungen von Dateiendungen leicht umgehen (z. B. durch doppelte Endungen oder Umbenennen von Dateien) oder Sicherheitslücken ausnutzen, bei denen der Webserver Dateien mit korrekten Endungen, aber schädlichem Inhalt ausführt (wie in PNG eingebettetes PHP). Sie müssen den tatsächlichen Dateiinhalt (MIME-Type) validieren und Uploads außerhalb des Web-Roots speichern."
---

# Webangriffe und Abwehrmassnahmen, die jeder Entwickler kennen sollte

Ihre Anwendung läuft reibungslos, der Datenverkehr wächst und das jüngste Deployment verlief ohne Probleme. Dann löscht eine einzige fehlerhafte Abfrage eines nicht authentifizierten Benutzers eine Datenbanktabelle, oder ein interner Mikrodienst leckt Kundendaten ins öffentliche Internet, weil ein HTTP-Client ungeschützt offengelegt wurde. Sicherheit versagt meistens **an den Schnittstellen**: Eine Validierung existiert, aber nicht an der Systemgrenze; Autorisierung ist implementiert, fehlt jedoch an einem bestimmten Endpunkt; die Ausgabe wird maskiert, aber ein Template verwendet rohes HTML. Die gute Nachricht: Die meisten realen Vorfälle lassen sich in wiederkehrende Klassen von Fehlern einteilen – sodass Sie sie systematisch beheben können.

**Verwandte Leitfäden:** [Observability & monitoring](observability-monitoring-laravel) · [Databases under load](database-performance-and-scaling)

## Inhalt

* [Bedrohungsanalyse: Was Sie vor wem schützen](#threat-model)
* [Injections: SQLi, Command Injection, Template Injection](#injection)
* [XSS: Reflektiert, gespeichert, DOM-basiert](#xss)
* [CSRF: Warum „aber es ist GET“ keine Abwehr ist](#csrf)
* [Authentifizierung und Sitzungen: Token-Diebstahl, Fixierung, Cookies](#auth-sessions)
* [Zugriffskontrolle und IDOR: „Es ist ja nur eine ID“](#idor)
* [Dateiuploads und Pfade: Uploads, Traversal, RCE im Umfeld](#uploads)
* [SSRF: Wenn Ihr Server die „falsche Adresse“ abruft](#ssrf)
* [Unsichere Deserialisierung und Object Spoofing](#deserialization)
* [Browser-Abwehr: Clickjacking, CORS, Header](#browser)
* [DoS und Missbrauch: Rate-Limits, Brute-Force, rechenintensive Aufgaben](#dos)
* [Häufige Fehler](#common-mistakes)
* [Checkliste für das Release](#checklist)
* [Selbsttest-Quiz](#self-test-quiz)

---

<a id="threat-model"></a>
## Bedrohungsanalyse: Was Sie vor wem schützen

Bevor Sie Sicherheitslogik schreiben, müssen Sie die Bedrohungsparameter festlegen:

- **Angreifer**: anonyme Benutzer, angemeldete Benutzer, Partner mit einem API-Schlüssel, jemand innerhalb des VPNs, ein Akteur mit Zugriff auf CI/CD.
- **Werte (Assets)**: Geld, personenbezogene Daten (PII), Benutzerkonten, Admin-Panels, Integrationen, Geheimnisse (Secrets), Zugriff auf das interne Netzwerk.
- **Angriffsfläche**: Formulare und APIs, Weiterleitungen, Webhooks, Importe/Exporte, Dateiuploads, Integrationen von Drittanbietern (S3, SMTP, Zahlungen).

> [!NOTE]
> **Pragmatische Sicherheitsregel**
> Gehen Sie immer davon aus, dass alle Eingaben böswillig sind. Dies schließt Header, Cookies, Webhook-Payloads, URL-Parameter und aus Warteschlangen gelesene Nachrichten ein.

---

<a id="injection"></a>
## Injections: SQLi, Command Injection, Template Injection

### SQL-Injection (SQLi)

SQLi beginnt meist als „nur ein kleines rohes SQL-Snippet“. Die Lösung ist nicht das Maskieren (Escaping), sondern das **Parametrisieren**.

**Schlecht** (String-Verkettung):
```php
// app/Http/Controllers/UserController.php
$rows = DB::select("SELECT * FROM users WHERE email = '{$email}'");
```

**Besser** (Platzhalter):
```php
// app/Http/Controllers/UserController.php
$rows = DB::select('SELECT * FROM users WHERE email = ?', [$email]);
```

Eine weitere häufige Falle sind **dynamische Spaltennamen / Sortierungen**. Parameter können nicht auf SQL-Identifikatoren angewendet werden, daher müssen Sie eine strikte Whitelist verwenden:
```php
// app/Http/Controllers/UserController.php
$allowed = ['created_at', 'email', 'id'];
$sort = in_array($request->get('sort'), $allowed, true) ? $request->get('sort') : 'created_at';

$users = User::query()->orderBy($sort, 'desc')->paginate();
```

### Command Injection

Wenn Sie die Shell aufrufen, gilt die Regel: **Geben Sie niemals Benutzereingaben direkt an eine Kommandozeile weiter**. Selbst `escapeshellarg()` ist nur eine Notlösung und kein sicheres Design.

Wenn Sie CLI-Werkzeuge ausführen müssen (Dateien konvertieren, Vorschauen generieren):
- Bevorzugen Sie eine Bibliothek gegenüber `exec()`.
- Wenn die CLI unvermeidlich ist, legen Sie Pfade/Argumente/Verzeichnisse fest und führen Sie sie in einer isolierten Umgebung aus (Container/Queue/Worker unter einem dedizierten Benutzer).

### Template Injection

Der gefährliche Fall besteht darin, Benutzern zu erlauben, eigene Templates bereitzustellen, die als Blade/Twig/Smarty ausgeführt werden.
- **Regel**: Benutzer-Templates müssen als Daten behandelt werden, idealerweise gerendert über ein eingeschränktes, nicht Turing-vollständiges Format (z. B. Markdown mit einer strikten Whitelist und sicherem Rendering).

---

<a id="xss"></a>
## XSS: Reflektiert, gespeichert, DOM-basiert

XSS bedeutet das Ausführen von JavaScript im Kontext Ihres Ursprungs (Origin). Typische Quellen:
- Rohe Ausgabe (`{!! ... !!}` in Blade, `innerHTML` im Frontend).
- Rich-Text-Felder, die ohne Bereinigung gespeichert werden.
- Einfügen von Werten in `<script>` oder HTML-Attribute ohne ordnungsgemäße Maskierung.

### Wichtigste Schutzmassnahmen

- **Standardmässig maskieren**: Verwenden Sie in Blade `{{ $value }}`; vermeiden Sie rohe Ausgaben, es sei denn, es ist absolut begründet.
- **Kontexte beachten**: HTML vs. Attribute vs. JS-Strings vs. URLs – überall gelten andere Maskierungsregeln.
- **HTML-Eingaben bereinigen**: Wenn Sie Formatierungen zulassen, verwenden Sie eine Whitelist (Tags/Attribute) und entfernen Sie `on*`-Handler, `javascript:`-Links etc.
- **CSP (Content Security Policy)**: Kein logischer Fix, aber ein massiver Schadensbegrenzer.

Eine einfache CSP (idealerweise mit Nonces und ohne `'unsafe-inline'`):
```http
# /etc/nginx/nginx.conf
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'
```

---

<a id="csrf"></a>
## CSRF: Warum „aber es ist GET“ keine Abwehr ist

CSRF tritt auf, wenn der Browser eines Benutzers eine Anfrage an Ihre Website mit dessen Cookies/Sitzung sendet, weil eine andere Website dies auslöst (Formulare, Bilder, JS).

### Schutzmassnahmen

- **CSRF-Token** für zustandsändernde Anfragen (POST/PUT/PATCH/DELETE). Laravel erledigt dies standardmäßig.
- **`SameSite` für Cookies**: Mindestens `Lax`; ziehen Sie `Strict` für sensible Abläufe in Betracht; für Cross-Site-Fälle nutzen Sie `None; Secure`.
- **Origin/Referer-Prüfungen** für besonders sensible Aktionen (E-Mail ändern, Auszahlungen).
- **Ändern Sie niemals den Zustand über GET-Anfragen**.

---

<a id="auth-sessions"></a>
## Authentifizierung und Sitzungen: Token-Diebstahl, Fixierung, Cookies

Was normalerweise fehlschlägt:
- Schwache Passwörter + fehlende Ratenbegrenzung → Credential Stuffing.
- Sitzungsdiebstahl über XSS; Sitzungsfixierung (Session Fixation); fehlende Invalidierung.
- Langlebige Token ohne Rotation und Gerätebindung.

### Grundlegende Kontrollen

- **Ratenbegrenzung (Rate Limiting)** für Login/OTP/Passwort-Reset.
- **Passwörter hashen** mit `password_hash` unter Verwendung von bcrypt oder argon2. Nutzen Sie keine eigene Kryptografie.
- Sitzungscookies: `HttpOnly`, `Secure`, `SameSite`, halten Sie die Lebensdauer kurz.
- **Sitzungs-IDs rotieren** nach dem Login und bei Berechtigungsänderungen.

---

<a id="idor"></a>
## Zugriffskontrolle und IDOR: „Es ist ja nur eine ID“

IDOR (Insecure Direct Object Reference) liegt vor, wenn ein Benutzer Identifikatoren erraten/durchgehen kann und so Daten anderer liest oder verändert.

Häufige Ursachen:
- Überprüfung auf „angemeldet“ statt auf Eigentümerschaft/Rolle.
- Die Autorisierung existiert in der UI, aber nicht auf der API.
- „Admin“-Verhalten wird über einen Anfrageparameter gesteuert.

### Schutzmassnahmen

- **Policies/Gates** für jede Ressource und Aktion.
- Filtern Sie bei Mandantenfähigkeit (Multi-Tenant) Abfragen immer auf Query-Ebene nach `tenant_id`/`account_id`.
- Verlassen Sie sich nicht auf „Hiding“ (Verstecken) als Sicherheitskonzept.

```php
// app/Http/Controllers/OrderController.php
// Bevorzugen Sie die Einschränkung der Abfrage auf den Benutzer:
$order = auth()->user()->orders()->findOrFail($id);

// Statt einer direkten Suche:
$order = Order::findOrFail($id);
```

---

<a id="uploads"></a>
## Dateiuploads und Pfade: Uploads, Traversal, RCE im Umfeld

Uploads sind ein klassischer Bereich für „RCE (Remote Code Execution) von nebenan“.

### Was durchzusetzen ist

- Validieren Sie den **Inhalt**, nicht nur die Erweiterung; der MIME-Type des Browsers ist nicht vertrauenswürdig.
- Grenzwerte für Dateigröße und -anzahl.
- Speichern Sie außerhalb des Web-Roots; Auslieferung über einen Controller oder eine dedizierte Domain/CDN.
- Zufällige Dateinamen (vertrauen Sie Originalnamen nicht als Pfade).
- Deaktivieren Sie die Ausführung von Skripten in Upload-Verzeichnissen auf Webserver-Ebene.

```nginx
# /etc/nginx/conf.d/uploads.conf
location /uploads {
    location ~ \.php$ {
        deny all;
    }
}
```

Pfadüberschreitung (Path Traversal) tritt oft auf als „Datei nach Name herunterladen“:
- Weisen Sie `../`, `\`, Null-Bytes ab.
- Verwenden Sie eine Whitelist realer Dateien/IDs, keinen Pfad aus der Anfrage.

---

<a id="ssrf"></a>
## SSRF: Wenn Ihr Server die „falsche Adresse“ abruft

SSRF (Server-Side Request Forgery) tritt auf, wenn Sie eine Benutzer-URL akzeptieren und **diese vom Server aus abrufen** (Avatar-Import, Webhook-Tester, Link-Vorschau, PDF-Erstellung).

Risiken: Zugriff auf **interne Dienste** (Cloud-Metadaten-Endpunkte, Redis/Consul, Admin-Panels), Umgehung von Netzwerkgrenzen.

### Schutzmassnahmen

- **Whitelist von Hosts/Domains**, keine Blacklist.
- Blockieren Sie private IP-Bereiche (127.0.0.0/8, 10.0.0.0/8, 169.254.0.0/16, 172.16.0.0/12, 192.168.0.0/16, ::1, fc00::/7).
- Deaktivieren Sie Weiterleitungen oder validieren Sie jeden Zwischenschritt erneut.
- Timeouts, Antwortgrößenbegrenzungen, Protokollbeschränkungen (nur https).

---

<a id="deserialization"></a>
## Unsichere Deserialisierung und Object Spoofing

Wenn Sie vom Angreifer kontrollierte Daten deserialisieren (Cookies, versteckte Felder, externe Queue-Payloads, unsignierte Caches), riskieren Sie:
- Rollen-/Feldmanipulationen.
- Gadget-Chains und ggf. RCE.

> [!NOTE]
> **Datenintegrität**
> Speichern Sie niemals serialisierte PHP/JS-Objekte an Orten, denen Sie nicht vertrauen. Verwenden Sie für Token signierte Strukturen wie JWT oder HMAC. Verwenden Sie für strukturierte Payloads JSON + strikte Schema-Validierung.

---

<a id="browser"></a>
## Browser-Abwehr: Clickjacking, CORS, Header

### Clickjacking

Framing blockieren:
```http
# /etc/nginx/nginx.conf
X-Frame-Options: DENY
Content-Security-Policy: frame-ancestors 'none'
```

### CORS

CORS blockiert keine Anfragen – es ist eine Browserregel für das Lesen von Antworten. Häufige Fehler:
- `Access-Control-Allow-Origin: *` kombiniert mit Cookies/Anmeldedaten.
- Zu weit gefasste erlaubte Methoden/Header.
- Vertrauen in den `Origin`-Header ohne Prüfung gegen eine strikte Liste.

### Grundlegende Sicherheits-Header
```http
# /etc/nginx/nginx.conf
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

---

<a id="dos"></a>
## DoS und Missbrauch: Rate-Limits, Brute-Force, rechenintensive Aufgaben

In Geschäftsanwendungen ist die häufigste Form von „Mini-DoS“ nicht die Bandbreite – es sind für den Angreifer billige, für Sie teure Endpunkte:
- Unindizierte Suchen, riesige Exporte, PDF-Generierung.
- Fehlende Ratenbegrenzung (Login, E-Mail/OTP-Versand, Gutscheincode-Prüfung).
- Rechenintensive Validierung/RegExes über riesige Strings.

### Kontrollen
- Rate-Limiting nach IP + Benutzerkonto + API-Schlüssel.
- Warteschlangen (Queues) für rechenintensive Aufgaben.
- Timeouts, Größenbegrenzungen, JSON-Tiefenbegrenzungen.
- Caching für aufwendige öffentliche Antworten.

---

<a id="common-mistakes"></a>
## Häufige Fehler

1. **Verlassen auf clientseitige Validierung**: Implementierung von Validierungen in Frontend-Formularen unter Vernachlässigung der Backend-Validierung, sodass Angreifer manipulative Payloads direkt an API-Endpunkte senden können.
2. **Rohes SQL für Sortier-/Order-by-Parameter**: Da Platzhalter keine Spaltennamen darstellen können, verketten Entwickler oft Benutzereingaben in `ORDER BY`-Anweisungen, was SQLi-Schwachstellen öffnet.
3. **Blacklisting privater IPs bei SSRF**: Der Versuch, `127.0.0.1` und `10.0.0.1` zu blockieren, wobei Hex-Codierungen (`0x7f.0.0.1`), oktale Darstellungen oder DNS-Rebinding-Angriffe vergessen werden.
4. **Fehlerhafte PHP-Serialisierung**: Speichern serialisierter PHP-Objekte in Cookies in dem Glauben, es sei sicher, weil die Daten „undurchsichtig“ sind, was PHP Object Injection ermöglicht.

---

<a id="checklist"></a>
## Checkliste für das Release

### Eingabe und Daten
- [ ] Validierung an der Systemgrenze (Formulare/APIs/Webhooks), Typen normalisieren.
- [ ] Whitelist für Sortierung/Filterung/Felder, die zu SQL-Identifikatoren werden.
- [ ] Keine Zustandsänderungen über GET-Anfragen.

### Rendering und Browser
- [ ] Standardmäßig maskieren, kein unbegründetes rohes HTML.
- [ ] CSP/`frame-ancestors`, Schutz vor Clickjacking.
- [ ] HSTS, `nosniff`, sinnvolle `Referrer-Policy`.

### Zugriff
- [ ] Serverseitige Autorisierung für jede Aktion (Policy/Gate), nicht nur im UI.
- [ ] Kein IDOR: Abfragen auf Eigentümer/Mandanten einschränken.
- [ ] Audit-Logs für sensible Aktionen.

### Integrationen
- [ ] SSRF-Kontrollen: Whitelist, Sperren privater IPs, Timeouts/Limits.
- [ ] Webhooks: Signaturprüfung, Replay-Schutz, Idempotenz.

### Uploads
- [ ] Inhalts-/Größenprüfungen, Speicherung außerhalb des Web-Roots, Ausführung deaktivieren.
- [ ] Zufällige Namen, keine vom Benutzer bereitgestellten Pfade.

---

## Zusammenfassung

Sicherheit ist kein Endprodukt, sondern eine Reihe von Grenzinvarianten. Validieren Sie Eingaben einmal an der Grenze, maskieren Sie Ausgaben in Templates, erzwingen Sie Autorisierung auf dem Server, signieren Sie externe Integrationen und begrenzen Sie die Rate von allem, was Rechenzeit kostet.

---

<a id="self-test-quiz"></a>
## Selbsttest-Quiz

### Frage 1: Was ist das primäre Sicherheitsproblem im folgenden Code?
```php
// app/Http/Controllers/DownloadController.php
return response()->download(storage_path('reports/' . $request->get('file')));
```
- A) SQL-Injection.
- B) Cross-Site Scripting (XSS).
- C) Path Traversal (Beliebiger Dateidownload).

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort: C**
Da die Eingabe `$request->get('file')` direkt in den Pfad verkettet wird, ohne auf `../` zu prüfen, kann ein Angreifer `file=../../.env` übergeben, um sensible Konfigurationsdaten auszulesen.
</details>

---

### Frage 2: Warum sind CSRF-Token für POST-Anfragen erforderlich, wenn das Session-Cookie bereits als `SameSite=Lax` markiert ist?
- A) `SameSite=Lax` verhindert keine POST-Anfragen, die durch Top-Level-Navigationen oder Skripte in älteren Browsern ausgelöst werden.
- B) CSRF-Token verhindern SQL-Injection.
- C) Ohne ein CSRF-Token kann das Session-Cookie nicht gelesen werden.

<details>
<summary><b>Antworten anzeigen</b></summary>

**Antwort: A**
SameSite-Attribute sind eine hilfreiche Abwehr, bieten jedoch aufgrund von Browserkompatibilitätsproblemen, Sicherheitslücken bei der Übernahme von Subdomains oder GET-basierten Zustandsänderungen keinen 100-prozentigen Schutz.
</details>
