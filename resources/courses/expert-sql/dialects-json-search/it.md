# Dialetti, JSON e ricerca JSON

Le applicazioni moderne gestiscono di frequente dati semistrutturati, come payload di API, impostazioni utente dinamiche o attributi polimorfi. Tradizionalmente, ciò richiedeva l'adozione dell'anti-pattern EAV (Entity-Attribute-Value) o il passaggio a database NoSQL. Oggi i database relazionali supportano nativamente il formato JSON con formati di memorizzazione binari, indicizzazione e ricche funzionalità di interrogazione. Tuttavia, PostgreSQL e MySQL affrontano in modo diverso la rappresentazione di JSON, la sintassi dei percorsi (path) e i meccanismi di indicizzazione.

---

## 1. Funzionamento interno dello storage: JSON testuale vs. binario

Il modo in cui il JSON viene memorizzato influisce sulle prestazioni delle query e sulla fattibilità dell'indicizzazione.

### PostgreSQL: `json` vs. `jsonb`
PostgreSQL offre due tipi di dati JSON:
* **`json`**: Memorizza i dati come una copia esatta in testo semplice del JSON di input. Preserva gli spazi bianchi, la formattazione e le chiavi duplicate. Richiede tuttavia l'analisi (parsing) del testo a ogni operazione di lettura, rallentando le query di ricerca.
* **`jsonb`**: Decompone il JSON in un formato binario già analizzato. Rimuove gli spazi bianchi, elimina le chiavi duplicate (mantenendo l'ultima) e ordina le chiavi degli oggetti per velocizzare le ricerche. L'analisi avviene una sola volta in fase di scrittura, rendendo l'indicizzazione e la ricerca estremamente veloci.

### MySQL: Il tipo di dati `JSON`
MySQL fornisce un unico tipo `JSON`. A livello interno, MySQL memorizza il JSON in un formato binario simile al `jsonb` di PostgreSQL. Convalida la sintassi JSON all'inserimento e consente un rapido accesso in lettura agli elementi del documento senza dover analizzare nuovamente il testo grezzo.

---

## 2. Interrogare il JSON: sintassi ed espressioni di percorso (Path)

L'estrazione di dati da oggetti JSON richiede operatori e sintassi di percorso specifici.

### Operatori JSON in PostgreSQL
PostgreSQL fornisce operatori per estrarre dati e verificare la struttura del documento:
* `->` restituisce un oggetto JSON o un elemento di un array (mantenendo il tipo `jsonb`).
* `->>` restituisce l'elemento come stringa in testo semplice.
* `#>` e `#>>` estraggono oggetti nidificati utilizzando un array di percorsi.
* `@>` verifica il contenimento (se il JSON a sinistra contiene il JSON a destra).

```sql
-- PostgreSQL: Querying jsonb
SELECT 
    data -> 'user' ->> 'name' AS username,
    data #>> '{user, profile, age}' AS age
FROM app_logs
WHERE data @> '{"status": "error"}';
```

### Funzioni e operatori JSON in MySQL
MySQL utilizza funzioni standard o operatori inline tramite la sintassi di percorso `$`:
* `JSON_EXTRACT(col, 'path')` estrae i dati.
* `->` funge da alias per `JSON_EXTRACT`.
* `->>` (operatore di percorso inline) estrae i dati e rimuove le virgolette dal risultato (equivalente a `JSON_UNQUOTE(JSON_EXTRACT(...))`).
* `JSON_CONTAINS(target, candidate, [path])` verifica se un documento ne contiene un altro.

```sql
-- MySQL: Querying JSON
SELECT 
    data->'$.user.name' AS username,
    data->>'$.user.profile.age' AS age
FROM app_logs
WHERE JSON_CONTAINS(data, '"error"', '$.status');
```

---

## 3. Indicizzare i documenti JSON

La scansione di ogni singolo documento JSON in una tabella da un milione di righe distrugge le prestazioni. È indispensabile indicizzare i dati.

### PostgreSQL: GIN (Generalized Inverted Indexes)
Il tipo `jsonb` di PostgreSQL si integra completamente con gli **indici GIN**, che indicizzano ogni chiave e valore all'interno del documento JSON.
* **GIN predefinito** (`jsonb_ops`): Indicizza chiavi, valori e percorsi. Supporta query contenenti gli operatori `@>`, `?`, `?|` e `?&`.
* **GIN specifico per percorso** (`jsonb_path_ops`): Indicizza solo coppie percorso-valore. Crea file di indice più piccoli ed è più veloce per query di contenimento (`@>`), ma non supporta le verifiche di esistenza delle chiavi (`?`).

```sql
-- PostgreSQL: Creating GIN Indexes
CREATE INDEX idx_logs_data ON app_logs USING gin (data);
CREATE INDEX idx_logs_data_path ON app_logs USING gin (data jsonb_path_ops);
```

### MySQL: colonne virtuali e indici multivalore
MySQL non supporta l'indicizzazione diretta dell'intero documento JSON. Si affida invece a due tecniche:
1. **Colonne generate (virtuali) + B-Tree**: Estraggono una specifica chiave JSON in una colonna virtuale e creano un indice su tale colonna.
2. **Indici multivalore (Multi-Valued Indexes)**: Introdotti in MySQL 8.0.17, consentono di indicizzare array all'interno di un documento JSON, supportando le ricerche tramite `MEMBER OF()`, `JSON_CONTAINS()` e `JSON_OVERLAPS()`.

```sql
-- MySQL: Generated Column Indexing
ALTER TABLE app_logs ADD COLUMN log_status VARCHAR(50) 
    GENERATED ALWAYS AS (data->>'$.status') VIRTUAL;
CREATE INDEX idx_logs_status ON app_logs(log_status);

-- MySQL: Multi-Valued Index on JSON Array
-- If data contains: {"tags": ["admin", "system", "web"]}
CREATE INDEX idx_logs_tags ON app_logs( (CAST(data->'$.tags' AS UNSIGNED ARRAY)) );

-- Query using Multi-Valued Index
SELECT * FROM app_logs WHERE 3 MEMBER OF (data->'$.tags');
```

> [!WARNING]
> **Trappole sui tipi di dati con le colonne generate**
> Quando crei colonne virtuali in MySQL, fai sempre corrispondere il tipo di dati estratto. Se il tuo campo JSON contiene numeri interi, esegui il cast della colonna virtuale o utilizza il tipo corretto. Eventuali discrepanze impediscono all'ottimizzatore di utilizzare l'indice durante l'esecuzione della query.

---

## 4. Matrice di confronto delle funzionalità

| Funzionalità | PostgreSQL (`jsonb`) | MySQL (`JSON`) |
| :--- | :--- | :--- |
| **Formato di storage** | Rappresentazione binaria ordinata | Rappresentazione binaria nativa |
| **Operatore di percorso (senza virgolette)** | `->>` o `#>>` | `->>` |
| **Linguaggio di percorso standard** | SQL/JSON Path (PostgreSQL 12+) | Sintassi JSONPath (`$.key`) |
| **Verifica di contenimento** | Operatore `@>` | Funzione `JSON_CONTAINS()` |
| **Indicizzazione dell'intero documento** | Sì (tramite indici GIN) | No (richiede col. generate / indici funzionali) |
| **Indicizzazione di Array** | Sì (GIN integrati) | Sì (indici multivalore, MySQL 8.0.17+) |

---

## 5. Riepilogo e buone pratiche

1. **Usa sempre tipi binari**: In PostgreSQL, scegli sempre `jsonb` rispetto a `json`, a meno che tu non debba solo memorizzare e recuperare i dati senza effettuare query o modifiche.
2. **Progetta in funzione degli indici**: In PostgreSQL, usa gli indici GIN per ricerche flessibili. In MySQL, definisci colonne generate virtuali per le chiavi nidificate interrogate più di frequente.
3. **Gestisci correttamente le virgolette**: Presta attenzione alla differenza tra `->` e `->>` (o `JSON_EXTRACT` e `JSON_UNQUOTE`). L'uso dell'operatore errato lascia le virgolette intorno ai valori stringa, facendo fallire i controlli di confronto.
