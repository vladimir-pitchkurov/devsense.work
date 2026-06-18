# Indici: analisi approfondita di B-Tree, GIN, BRIN e indici cluster

Gli indici sono lo strumento principale per accelerare le prestazioni delle query. Tuttavia, l'applicazione cieca degli indici può compromettere le prestazioni di scrittura e consumare enormi quantità di spazio sul disco. I database relazionali supportano molteplici tipi di indici, ciascuno progettato per specifiche distribuzioni di dati, pattern di query e requisiti di storage. La comprensione del funzionamento interno dei B-Tree, del clustering delle chiavi primarie in MySQL e dei tipi di indici avanzati in PostgreSQL è fondamentale per l'ottimizzazione professionale dei database.

---

## 1. Funzionamento dei B-Tree e comportamenti di clustering

Il **B-Tree** (Balanced Tree) è il tipo di indice predefinito in quasi tutti i database relazionali. Mantiene i dati ordinati e consente ricerche, accessi sequenziali, inserimenti ed eliminazioni in tempo logaritmico ($O(\log n)$).

### MySQL InnoDB: indici cluster vs. indici secondari
Nel motore InnoDB di MySQL, tutte le tabelle sono organizzate fisicamente attorno a un **Indice cluster (Clustered Index)**.
* **Indice cluster**: I nodi foglia dell'indice contengono i dati reali della riga. Per impostazione predefinita, corrisponde alla `PRIMARY KEY` della tabella. Se non viene definita alcuna chiave primaria, InnoDB seleziona il primo indice `UNIQUE` contenente solo colonne non-null. Se non ne esiste alcuno, InnoDB genera un ID riga nascosto a 6 byte.
* **Indice secondario**: I nodi foglia di qualsiasi indice secondario *non* contengono puntatori ai dati. Memorizzano invece il **valore della chiave primaria** della riga.
* **Bookmark Lookup**: Quando esegui una query utilizzando un indice secondario, MySQL cerca prima nell'indice secondario per trovare la chiave primaria, quindi esegue una seconda ricerca nell'indice cluster per recuperare la riga.

```
MySQL InnoDB Index Lookup:
[Ricerca indice secondario] ---> Restituisce il valore PK (ad es., ID: 42)
                                      |
                                      v
[Ricerca indice cluster]    ---> Restituisce i dati della riga (Nome, Email, ecc.)
```

### PostgreSQL: indicizzazione basata su Heap
A differenza di MySQL, PostgreSQL non utilizza tabelle clustered per impostazione predefinita. Le tabelle sono organizzate come un "heap" di pagine.
* Tutti gli indici (incluso l'indice della chiave primaria) sono **indici secondari**.
* I nodi foglia di un indice di PostgreSQL puntano direttamente all'indirizzo fisico (TID - Tuple ID, composto dal numero di pagina e dall'offset) della riga nell'heap della tabella.
* **Nessun Bookmark Lookup**: Postgres passa direttamente dal nodo foglia dell'indice alla pagina dell'heap. Tuttavia, l'aggiornamento di una riga in Postgres ne modifica l'indirizzo fisico, richiedendo l'aggiornamento di tutti gli indici (a meno che non si verifichi un aggiornamento HOT).

---

## 2. Tipi di indici avanzati in PostgreSQL

PostgreSQL offre tipi di indici specializzati che non hanno un equivalente nativo in MySQL.

### BRIN (Block Range Index)
* **Come funziona**: Invece di indicizzare ogni singola riga, un indice BRIN divide la tabella in intervalli di blocchi fisici (l'impostazione predefinita è 128 pagine o 1MB di dati) e memorizza solo il valore **minimo** e **massimo** per ciascun intervallo.
* **Quando usarlo**: Tabelle estremamente grandi (centinaia di gigabyte) in cui i dati sono ordinati naturalmente sul disco (ad es., ID auto-incrementali, timestamp `created_at`).
* **Vantaggio**: Dimensioni incredibilmente ridotte. Un indice B-Tree da 10GB può spesso essere sostituito da un indice BRIN da soli 10MB.

```sql
-- PostgreSQL: Creating a BRIN index
CREATE INDEX idx_orders_date_brin ON orders USING brin (created_at);
```

### GIN (Generalized Inverted Index)
* **Come funziona**: Mappa i valori (come elementi di array, parole all'interno di testi o chiavi JSON) alle righe in cui compaiono.
* **Quando usarlo**: Indicizzazione di array, documenti JSONB o colonne destinate alla ricerca full-text.

```sql
-- PostgreSQL: GIN index for arrays
CREATE INDEX idx_user_tags ON users USING gin (tags);
```

### GiST (Generalized Search Tree)
* **Come funziona**: Un modello per la creazione di strutture B-Tree personalizzate. Viene utilizzato per l'indicizzazione di coordinate geometriche, tipi intervallo e indirizzi di rete.

---

## 3. Indici di copertura, parziali e funzionali

### Indici di copertura (ottimizzazione dell'Index-Only Scan)
Un indice di copertura (covering index) contiene tutte le colonne richieste da una query. È possibile aggiungere ulteriori colonne di payload a un nodo foglia dell'indice utilizzando la clausola `INCLUDE`.
* **Sintassi PostgreSQL e MySQL**:
  ```sql
  -- PostgreSQL (using INCLUDE)
  CREATE INDEX idx_users_email_include ON users (email) INCLUDE (username, status);

  -- MySQL (using Composite Index - columns must be ordered)
  CREATE INDEX idx_users_email_cover ON users (email, username, status);
  ```

### Indici parziali
Indicizzano solo un sottoinsieme di righe che soddisfano una specifica condizione di filtraggio. Ciò riduce le dimensioni dell'indice e il sovraccarico di scrittura.
* **Solo PostgreSQL**:
  ```sql
  -- Index only active accounts
  CREATE INDEX idx_users_active_email ON users (email) WHERE status = 'active';
  ```
* *MySQL non supporta nativamente gli indici parziali. È necessario utilizzare indici funzionali con `CASE WHEN` per simulare questo comportamento.*

### Indici funzionali (di espressione)
Indicizzano il risultato di una funzione o di un'espressione anziché i valori grezzi delle colonne.
* **Sintassi MySQL e PostgreSQL**:
  ```sql
  -- PostgreSQL
  CREATE INDEX idx_users_lower_email ON users (LOWER(email));

  -- MySQL 8.0+
  CREATE INDEX idx_users_lower_email ON users ((LOWER(email)));
  ```

> [!WARNING]
> **Trappole sintattiche degli indici funzionali**
> In MySQL 8.0, gli indici di espressione DEVONO essere racchiusi tra doppie parentesi: `((espressione))`. L'omissione delle parentesi esterne causa un errore di sintassi.

> [!TIP]
> **Unione di indici (Index Merge)**
> Quando il filtro di una query contiene condizioni `AND` o `OR` su più colonne, i database possono eseguire un **Index Merge**. Questo processo scansiona più indici a singola colonna e interseca o unisce le mappe di bit risultanti. Tuttavia, un singolo indice composto (multicolonna) è quasi sempre più veloce rispetto all'unione di indici separati.

---

## 4. Matrice di confronto delle funzionalità di indicizzazione

| Funzionalità | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Layout tabella con indici cluster** | No (Tabelle basate su Heap) | Sì (Tabella organizzata attorno al B-Tree della PK) |
| **Ricerca chiave primaria** | Indice -> Pagina Heap | Direttamente sul nodo foglia del B-Tree del cluster |
| **Indici parziali** | Sì (clausola `WHERE`) | No (Soluzione alternativa tramite indice funzionale) |
| **Clausola indice di copertura** | Sì (`INCLUDE`) | No (Deve essere definito come chiave composta) |
| **Indici Block Range (BRIN)** | Sì | No |
| **Indici invertiti (GIN)** | Sì | No |

---

## 5. Riepilogo e buone pratiche

1. **Evita il sovraccarico delle PK auto-incrementali in MySQL**: Poiché InnoDB organizza le tabelle attorno alla chiave primaria, l'inserimento di UUID casuali come chiavi primarie causa un forte split delle pagine e frammentazione. Utilizza ID sequenziali o UUID ordinati.
2. **Sfrutta BRIN per grandi quantità di serie storiche (time-series)**: Se hai una tabella di log da molti gigabyte ordinata per timestamp, usa un indice BRIN. Risparmierai gigabyte di memoria rispetto a un B-Tree standard.
3. **Usa gli indici parziali per colonne sparse**: Se interroghi frequentemente una tabella cercando uno stato raro (ad es., `WHERE status = 'retry'`), crea un indice parziale su tale condizione per mantenere l'indice estremamente ridotto.
