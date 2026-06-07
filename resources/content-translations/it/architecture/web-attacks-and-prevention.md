---
title: "Attacchi e difese web: XSS, CSRF, SQLi, SSRF, IDOR e caricamento di file | DevSense"
description: "Gli attacchi web che incontrerai più spesso: iniezioni (SQL/comandi), XSS, CSRF, bug IDOR/controllo degli accessi, SSRF, caricamenti di file non sicuri, clickjacking e trappole di configurazione. Mitigazioni pratiche, header e checklist."
published: 2026-04-14
faq:
  - question: "Perché la sanificazione dell'input HTML non è sufficiente per prevenire la SQL injection?"
    answer: "La SQL injection si verifica quando dati non attendibili vengono concatenati all'interno di query di database. La sanificazione dell'HTML rimuove tag come `<script>`, ma non impedisce a caratteri come virgolette o punti e virgola di alterare la struttura di una query SQL. L'unica soluzione robusta consiste nell'utilizzare query parametrizzate (prepared statement)."
  - question: "I cookie SameSite possono sostituire completamente i token CSRF?"
    answer: "Sebbene `SameSite=Lax` o `Strict` proteggano dalle richieste cross-site nei browser moderni, non rappresentano una garanzia di sicurezza assoluta. I browser meno recenti potrebbero non supportare SameSite, e bug o modifiche dello stato tramite richieste GET possono aggirarlo. I token CSRF rimangono essenziali per una difesa approfondita (defense-in-depth)."
  - question: "In che modo l'SSRF differisce dal CSRF?"
    answer: "Il CSRF costringe il browser di un utente a inviare richieste a un'applicazione presso cui è autenticato. L'SSRF costringe il *server* dell'applicazione a effettuare richieste non autorizzate a risorse interne (come endpoint di metadati o istanze Redis interne) non raggiungibili dall'internet pubblico."
  - question: "Perché controllare solo l'estensione del file è insicuro per il caricamento dei file?"
    answer: "Gli aggressori possono facilmente aggirare i controlli sulle estensioni (ad esempio, utilizzando doppie estensioni o rinominando i file) o sfruttare vulnerabilità in cui il server web esegue file con estensioni corrette ma con contenuti dannosi (come codice PHP incorporato in un PNG). È necessario convalidare il contenuto effettivo del file (tipo MIME) e memorizzare i file caricati al di fuori della root web."
---

# Attacchi e difese web che ogni sviluppatore dovrebbe conoscere

La tua applicazione funziona senza problemi, il traffico cresce e l'ultimo deployment è andato a buon fine. Poi, una singola query malformata da parte di un utente non autenticato cancella una tabella del database, oppure un microservizio interno espone i dati dei clienti all'internet pubblico a causa di un client HTTP non protetto. La sicurezza tende a fallire **nelle giunzioni**: la convalida esiste, ma non al confine del sistema; l'autorizzazione è implementata, ma manca su un endpoint; l'output viene convertito (escape), ma un template utilizza HTML non elaborato. La buona notizia: la maggior parte degli incidenti reali rientra in classi ripetitive di errori — pertanto è possibile risolverli sistematicamente.

**Guide correlate:** [Observability & monitoring](observability-monitoring-laravel) · [Databases under load](database-performance-and-scaling)

## Indice

* [Modellazione delle minacce: cosa proteggi e da chi](#threat-model)
* [Iniezioni: SQLi, iniezione di comandi, iniezione di template](#injection)
* [XSS: riflesso, memorizzato, basato su DOM](#xss)
* [CSRF: perché “ma è una GET” non costituisce una difesa](#csrf)
* [Autenticazione e sessioni: furto di token, fissazione, cookie](#auth-sessions)
* [Controllo degli accessi e IDOR: “è solo un ID”](#idor)
* [Caricamento di file e percorsi: caricamenti, traversal, RCE nelle vicinanze](#uploads)
* [SSRF: quando il server interroga il “posto sbagliato”](#ssrf)
* [Deserializzazione non sicura e spoofing di oggetti](#deserialization)
* [Difese del browser: clickjacking, CORS, header](#browser)
* [DoS e abusi: rate limit, brute force, elaborazioni costose](#dos)
* [Errori comuni](#common-mistakes)
* [Checklist per il rilascio](#checklist)
* [Quiz di autovalutazione](#self-test-quiz)

---

<a id="threat-model"></a>
## Modellazione delle minacce: cosa proteggi e da chi

Prima di scrivere la logica di sicurezza, è necessario definire i parametri delle minacce:

- **Aggressore**: utente anonimo, utente registrato, partner dotato di chiave API, utente interno alla VPN, attore con accesso alla CI/CD.
- **Asset**: denaro, dati personali (PII), account, pannelli di amministrazione, integrazioni, segreti, accesso alla rete interna.
- **Superficie di attacco**: moduli e API, reindirizzamenti, webhook, importazioni/esportazioni, caricamenti di file, integrazioni di terze parti (S3, SMTP, pagamenti).

> [!NOTE]
> **Regola pragmatica di sicurezza**
> Presumi sempre che tutti gli input siano dannosi. Ciò include header, cookie, payload dei webhook, parametri URL e messaggi letti dalle code.

---

<a id="injection"></a>
## Iniezioni: SQLi, iniezione di comandi, iniezione di template

### SQL Injection (SQLi)

La SQLi di solito inizia come “solo un piccolo frammento di SQL grezzo”. La soluzione non risiede nella conversione (escaping), ma nella **parametrizzazione**.

**Errato** (concatenazione di stringhe):
```php
// app/Http/Controllers/UserController.php
$rows = DB::select("SELECT * FROM users WHERE email = '{$email}'");
```

**Corretto** (segnaposto):
```php
// app/Http/Controllers/UserController.php
$rows = DB::select('SELECT * FROM users WHERE email = ?', [$email]);
```

Un'altra trappola comune riguarda **l'ordinamento o i nomi delle colonne dinamici**. I parametri non si applicano agli identificatori SQL, pertanto è necessario utilizzare una lista di elementi consentiti (allowlist) rigorosa:
```php
// app/Http/Controllers/UserController.php
$allowed = ['created_at', 'email', 'id'];
$sort = in_array($request->get('sort'), $allowed, true) ? $request->get('sort') : 'created_at';

$users = User::query()->orderBy($sort, 'desc')->paginate();
```

### Iniezione di comandi (Command injection)

Se richiami la shell, la regola è: **non passare mai l'input dell'utente in una riga di comando**. Persino `escapeshellarg()` è una misura di protezione di ultima istanza, non un design sicuro.

Se devi eseguire strumenti CLI (convertire file, generare anteprime):
- Prediligi una libreria rispetto a `exec()`.
- Se la CLI è inevitabile, blocca binari/argomenti/cartelle ed esegui il processo in un ambiente limitato (container/coda/worker con un utente dedicato).

### Iniezione di template (Template injection)

Il caso rischioso si presenta quando si consente agli utenti di fornire template eseguiti come Blade/Twig/Smarty.
- **Regola**: i template dell'utente devono essere trattati come dati, idealmente renderizzati tramite un formato limitato e non Turing-completo (ad esempio, Markdown con una lista di tag consentiti rigorosa e rendering sicuro).

---

<a id="xss"></a>
## XSS: riflesso, memorizzato, basato su DOM

L'XSS consiste nell'esecuzione di JavaScript all'interno del proprio dominio (origin). Origini tipiche:
- Output grezzo (`{!! ... !!}` in Blade, `innerHTML` sul frontend).
- Campi di testo avanzati (rich-text) memorizzati senza sanificazione.
- Iniezione di valori all'interno di tag `<script>` o attributi HTML senza codifica appropriata.

### Mitigazioni principali

- **Escape predefinito**: in Blade usa `{{ $value }}`; evita l'output grezzo a meno che non sia strettamente giustificato.
- **Rispetta i contesti**: HTML vs attributi vs stringhe JS vs URL — a ciascun contesto si applicano regole di codifica differenti.
- **Sanifica l'input HTML**: se consenti la formattazione, utilizza una lista di tag consentiti (tag/attributi) e rimuovi i gestori di eventi `on*`, URL `javascript:`, ecc.
- **CSP (Content Security Policy)**: non è una correzione logica, ma limita enormemente i danni.

CSP di base (preferibilmente con nonce e senza `'unsafe-inline'`):
```http
# /etc/nginx/nginx.conf
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'
```

---

<a id="csrf"></a>
## CSRF: perché “ma è una GET” non costituisce una difesa

Il CSRF si verifica quando il browser di un utente invia una richiesta al tuo sito comprensiva di cookie/sessione perché attivata da un altro sito (tramite moduli, immagini, JS).

### Mitigazioni

- **Token CSRF** per le richieste che modificano lo stato (POST/PUT/PATCH/DELETE). Laravel gestisce questo comportamento in modo predefinito.
- **Cookie `SameSite`**: impostato almeno su `Lax`; considera `Strict` per i flussi sensibili; per scenari cross-site utilizza `None; Secure`.
- **Controlli su Origin/Referer** per azioni particolarmente sensibili (cambio email, pagamenti).
- **Non modificare mai lo stato tramite richieste GET**.

---

<a id="auth-sessions"></a>
## Autenticatione e sessioni: furto di token, fissazione, cookie

Cosa si rompe solitamente:
- Password deboli + mancanza di rate limit → credential stuffing.
- Furto di sessione tramite XSS; fissazione della sessione; mancata invalidazione.
- Token a lungo termine senza rotazione e associazione al dispositivo.

### Controlli di base

- **Rate limit** su tentativi di accesso/OTP/reimpostazione password.
- **Hashing delle password** tramite `password_hash` con bcrypt o argon2. Nessun algoritmo crittografico personalizzato.
- Cookie di sessione: `HttpOnly`, `Secure`, `SameSite`, mantieni una durata breve.
- **Ruota gli ID di sessione** dopo l'accesso e in caso di modifiche dei privilegi.

---

<a id="idor"></a>
## Controllo degli accessi e IDOR: “è solo un ID”

L'IDOR (Insecure Direct Object Reference) si verifica quando un utente può indovinare/iterare gli identificatori e leggere o modificare i dati di qualcun altro.

Cause comuni:
- Verifica dello stato di “utente connesso” anziché della proprietà o del ruolo.
- L'autorizzazione è presente nell'interfaccia utente ma non sulle API.
- Comportamento da “amministratore” controllato da un parametro della richiesta.

### Mitigazioni

- **Policy/gate** per ciascuna risorsa e azione.
- Per le applicazioni multi-tenant, filtra sempre in base a `tenant_id`/`account_id` a livello di query.
- Non fare affidamento sulla pratica di “nascondere” gli elementi come misura di sicurezza.

```php
// app/Http/Controllers/OrderController.php
// Prediligi limitare la query all'utente:
$order = auth()->user()->orders()->findOrFail($id);

// Rispetto a una ricerca diretta:
$order = Order::findOrFail($id);
```

---

<a id="uploads"></a>
## Caricamento di file e percorsi: caricamenti, traversal, RCE nelle vicinanze

I caricamenti di file sono un'area classica per una \"esecuzione di codice remota (RCE) nelle vicinanze\".

### Cosa applicare

- Convalida il **contenuto**, non solo l'estensione; il tipo MIME del browser non è attendibile.
- Limiti sulla dimensione e sul numero dei file.
- Memorizza all'esterno della root web; distribuisci tramite un controller o un dominio/CDN dedicato.
- Nomi di file casuali (non fidarti dei nomi originali come percorsi).
- Disabilita l'esecuzione nelle cartelle di caricamento a livello di server web.

```nginx
# /etc/nginx/conf.d/uploads.conf
location /uploads {
    location ~ \.php$ {
        deny all;
    }
}
```

Il path traversal si presenta spesso sotto forma di “scarica file per nome”:
- Rifiuta `../`, `\`, byte nulli.
- Utilizza un elenco consentito di file/ID reali, non un percorso ricavato dalla richiesta.

---

<a id="ssrf"></a>
## SSRF: quando il server interroga il “posto sbagliato”

L'SSRF (Server-Side Request Forgery) si verifica quando accetti un URL fornito dall'utente e **lo interroghi dal server** (importazione avatar, tester di webhook, anteprima link, generatore di PDF).

Rischi: accesso a **servizi interni** (endpoint di metadati cloud, Redis/Consul, pannelli di amministrazione), aggirando i confini della rete.

### Mitigazioni

- **Lista di host/domini consentiti (allowlist)**, non una lista nera (blacklist).
- Blocca gli intervalli di indirizzi IP privati (127.0.0.0/8, 10.0.0.0/8, 169.254.0.0/16, 172.16.0.0/12, 192.168.0.0/16, ::1, fc00::/7).
- Disabilita i reindirizzamenti o convalida nuovamente ogni passaggio (hop).
- Timeout, limiti sulla dimensione della risposta, restrizioni sui protocolli (solo https).

---

<a id="deserialization"></a>
## Deserializzazione non sicura e spoofing di oggetti

Se esegui la deserializzazione di dati controllati dall'aggressore (cookie, campi nascosti, payload di code esterne, cache non firmate), rischi:
- Spoofing di ruoli/campi.
- Gadget chain e potenziale RCE laddove applicabile.

> [!NOTE]
> **Integrità dei dati**
> Non memorizzare mai oggetti PHP/JS serializzati in posizioni non attendibili. Per i token, utilizza strutture firmate come JWT o HMAC. Per i payload strutturati, utilizza JSON con una convalida rigorosa dello schema.

---

<a id="browser"></a>
## Difese del browser: clickjacking, CORS, header

### Clickjacking

Blocca l'incorporamento in frame:
```http
# /etc/nginx/nginx.conf
X-Frame-Options: DENY
Content-Security-Policy: frame-ancestors 'none'
```

### CORS

Il meccanismo CORS non costituisce un “blocco delle richieste” — è una regola del browser per la lettura delle risposte. Errori comuni:
- `Access-Control-Allow-Origin: *` in combinazione con cookie/credenziali.
- Metodi/header consentiti eccessivamente ampi.
- Considerare attendibile l'header `Origin` senza verificarlo a fronte di una lista rigorosa.

### Header di sicurezza di base
```http
# /etc/nginx/nginx.conf
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

---

<a id="dos"></a>
## DoS e abusi: rate limit, brute force, elaborazioni costose

Nelle applicazioni aziendali, il “mini-DoS” più comune non riguarda la larghezza di banda — bensì endpoint economici da richiamare per l'aggressore ma estremamente costosi da elaborare per te:
- Ricerche non indicizzate, esportazioni massive, generazione di PDF.
- Mancanza di rate limit (accesso, invio email/OTP, “verifica codice promozionale”).
- Convalide/espressioni regolari costose su stringhe di grandi dimensioni.

### Controlli
- Rate limit per IP + account + chiave API.
- Messa in coda delle attività costose.
- Timeout, limiti di dimensione, limiti di profondità dei dati JSON.
- Cache per risposte pubbliche pesanti.

---

<a id="common-mistakes"></a>
## Errori comuni

1. **Fare affidamento sulla convalida lato client**: implementare convalide sui moduli front-end ma trascurarle sul back-end, consentendo agli utenti di trasmettere payload alternativi direttamente agli endpoint API.
2. **SQL grezzo per i parametri di Sort / Order by**: poiché i segnaposto non possono rappresentare i nomi delle colonne, gli sviluppatori spesso concatenano gli input dell'utente all'interno di istruzioni `ORDER BY`, introducendo vulnerabilità SQLi.
3. **Lista nera degli IP privati in scenari SSRF**: tentare di bloccare `127.0.0.1` e `10.0.0.1` ma dimenticare le codifiche esadecimali (`0x7f.0.0.1`), le rappresentazioni ottali o gli attacchi di DNS rebinding.
4. **Deserializzazione PHP errata**: memorizzare oggetti PHP serializzati nei cookie, credendo che sia sicuro perché i dati sono “opachi”, abilitando così la PHP Object Injection.

---

<a id="checklist"></a>
## Checklist per il rilascio

### Input e dati
- [ ] Convalida al confine (moduli/API/webhook), normalizza i tipi.
- [ ] Lista di elementi consentiti per l'ordinamento/filtraggio/campi che diventano identificatori SQL.
- [ ] Nessuna modifica dello stato tramite richieste GET.

### Rendering e browser
- [ ] Conversione (escape) predefinita, nessun HTML grezzo non giustificato.
- [ ] CSP/`frame-ancestors`, protezione da clickjacking.
- [ ] HSTS, `nosniff`, `Referrer-Policy` sensata.

### Accesso
- [ ] Autorizzazione lato server per ciascuna azione (policy/gate), non solo sull'interfaccia utente.
- [ ] Nessun IDOR: query limitate al proprietario/tenant.
- [ ] Log di audit per le azioni sensibili.

### Integrazioni
- [ ] Controlli SSRF: liste consentite, blocchi degli IP privati, timeout/limiti.
- [ ] Webhook: verifica delle firme, protezione dai tentativi di replay, idempotenza.

### Caricamenti
- [ ] Controlli su contenuto/dimensione, memorizzazione al di fuori della root web, esecuzione disabilitata.
- [ ] Nomi casuali, nessun percorso specificato dall'utente.

---

## Riepilogo

La sicurezza non è un prodotto finito; è un insieme di invarianti al confine del sistema. Convalida gli input una volta al confine, converti (escape) gli output nei template, applica l'autorizzazione sul server, firma le integrazioni esterne e limita la frequenza (rate-limit) di tutto ciò che comporta un costo computazionale.

---

<a id="self-test-quiz"></a>
## Quiz di autovalutazione

### Domanda 1: Qual è il problema principale di sicurezza del seguente codice?
```php
// app/Http/Controllers/DownloadController.php
return response()->download(storage_path('reports/' . $request->get('file')));
```
- A) SQL Injection.
- B) Cross-Site Scripting (XSS).
- C) Path Traversal (scaricamento di file arbitrari).

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta: C**
Poiché l'input `$request->get('file')` viene concatenato direttamente nel percorso senza verificare la presenza di sequenze `../`, un aggressore può passare `file=../../.env` per leggere segreti di configurazione sensibili.
</details>

---

### Domanda 2: Perché i token CSRF sono richiesti per le richieste POST se il cookie di sessione è già contrassegnato con `SameSite=Lax`?
- A) `SameSite=Lax` non impedisce le richieste POST avviate da navigazioni di primo livello o script su browser meno recenti.
- B) I token CSRF prevengono la SQL injection.
- C) Senza un token CSRF, non è possibile leggere il cookie di sessione.

<details>
<summary><b>Mostra risposte</b></summary>

**Risposta: A**
Gli attributi SameSite sono un'utile difesa, ma non offrono una copertura di sicurezza del 100% a causa di problemi di compatibilità dei browser, compromissioni di sottodomini vulnerabili o modifiche dello stato basate su richieste GET.
</details>
