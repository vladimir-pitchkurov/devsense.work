# Concorrenza, MVCC e table bloat

In un database ad alta velocità di elaborazione, migliaia di transazioni leggono e scrivono dati simultaneamente. Se ogni operazione di lettura bloccasse la riga interessata, i database si arresterebbero. Per risolvere questo problema, i moderni database relazionali utilizzano il **Multi-Version Concurrency Control (MVCC)**. La filosofia alla base del sistema MVCC è semplice: *i lettori non bloccano gli scrittori e gli scrittori non bloccano i lettori*. Tuttavia, MySQL e PostgreSQL implementano questa filosofia in modi fondamentalmente diversi, il che si traduce in profili prestazionali, requisiti di manutenzione e strategie di ottimizzazione completamente differenti.

---

## 1. Come funziona il sistema MVCC a livello interno

Quando una transazione aggiorna una riga, il database non sovrascrive immediatamente i vecchi dati. Al contrario, mantiene più versioni di quella riga, consentendo alle transazioni attive di visualizzare uno snapshot coerente dei dati in base al loro livello di isolamento.

### PostgreSQL: Versionamento delle tuple (In-Heap)
PostgreSQL memorizza tutte le versioni delle righe (chiamate "tuple") direttamente nell'heap della tabella.
* **`xmin`**: L'ID della transazione (TxID) che ha inserito la riga.
* **`xmax`**: L'ID della transazione che ha eliminato o aggiornato la riga (inizialmente impostato a `0` o null).

Quando si aggiorna una riga in Postgres, il database esegue un `DELETE` logico seguito da un `INSERT`. Contrassegna il valore `xmax` della riga esistente con il TxID corrente e inserisce una nuova riga con `xmin` pari al TxID corrente.

### MySQL (InnoDB): Undo Log e segmenti di rollback
Il motore InnoDB di MySQL adotta un approccio diverso. Esegue gli aggiornamenti sul posto (in-place) all'interno del tablespace, ma scrive la vecchia versione della riga in una struttura dedicata chiamata **Undo Log**.
* L'intestazione di ogni riga contiene un campo `DB_TRX_ID` (l'ID dell'ultima transazione che ha modificato la riga) e un campo `DB_ROLL_PTR` (un puntatore di rollback che rimanda al record dell'undo log contenente la versione precedente).
* Quando un lettore ha bisogno di uno snapshot precedente, InnoDB inizia dalla riga attiva e risale la catena di rollback a ritroso utilizzando il puntatore di rollback per ricostruire i dati in tempo reale.

---

## 2. Table bloat, VACUUM e aggiornamenti HOT in PostgreSQL

Poiché PostgreSQL memorizza le tuple eliminate o non più attive (vecchie versioni di righe aggiornate/eliminate, dette "tuple morte") direttamente nelle pagine della tabella, le tabelle tendono a crescere naturalmente di dimensioni nel tempo. Questo fenomeno è noto come **table bloat** (gonfiore della tabella).

```
PostgreSQL Heap Page Update:
+-------------------------------------------------------------+
| [Tuple v1 (xmin: 100, xmax: 101)] -> Tuple morta (Bloat)    |
| [Tuple v2 (xmin: 101, xmax: 0)]   -> Tuple viva             |
+-------------------------------------------------------------+
```

### Il ruolo del comando VACUUM
Per recuperare lo spazio occupato dalle tuple morte, PostgreSQL esegue un processo in background chiamato **Autovacuum**.
* **VACUUM standard**: Scansiona le pagine, contrassegnando le tuple morte come riutilizzabili per inserimenti futuri. *Non* restituisce lo spazio al sistema operativo (a meno che le pagine alla fine della tabella non siano completamente vuote).
* **VACUUM FULL**: Ricostruisce interamente la tabella, riducendola e restituendo lo spazio libero al sistema operativo. **Attenzione**: Questa operazione acquisisce un lock esclusivo sulla tabella (`ACCESS EXCLUSIVE`), bloccando qualsiasi lettura e scrittura.

### Ottimizzazione HOT (Heap-Only Tuple)
Per evitare di aggiornare i puntatori degli indici a ogni modifica della versione di una riga, PostgreSQL utilizza gli **aggiornamenti HOT**. Se un aggiornamento non modifica colonne indicizzate e la pagina dispone di sufficiente spazio libero:
1. Postgres inserisce la nuova tupla nella stessa pagina.
2. Collega la vecchia tupla alla nuova tupla.
3. Gli indici continuano a puntare alla vecchia tupla; i lettori seguono la catena di collegamento.

> [!WARNING]
> **L'ottimizzazione dell'Autovacuum è obbligatoria!**
> Le impostazioni predefinite dell'autovacuum di PostgreSQL sono notoriamente molto conservative. Sulle tabelle ad alta intensità di scrittura, ciò comporta un bloat incontrollato, che causa il degrado delle query poiché il database deve scansionare le tuple morte. Ricorda sempre di regolare il parametro `autovacuum_vacuum_scale_factor` (valore predefinito 0.20 o 20% di righe modificate) riducendolo a `0.05` o meno per le tabelle di grandi dimensioni.

---

## 3. MySQL InnoDB: purga e troncamento dell'Undo Log

In MySQL il table bloat rappresenta un problema minore, poiché le vecchie versioni delle righe vengono memorizzate nei log di undo anziché nello spazio della tabella. Tuttavia, InnoDB presenta colli di bottiglia specifici legati alla concorrenza.

### I thread di purge di InnoDB
Una volta che la transazione attiva più vecchia non ha più bisogno del record dell'undo log, un processo in background chiamato **Purge Thread** pulisce le pagine dell'undo log.
* Se una transazione rimane aperta per ore (ad es., una query di reportistica a lungo termine), InnoDB non può eliminare i log di undo creati a partire dall'avvio di quella transazione.
* Ciò causa un rigonfiamento dell'**Undo Tablespace**. Nelle versioni precedenti di MySQL, gli undo tablespace non potevano ridursi di dimensioni, richiedendo una ricostruzione completa del database per recuperare lo spazio su disco.

```sql
-- MySQL: Inspecting InnoDB Undo Log & Transaction States
SELECT 
    trx_id, trx_state, trx_started, 
    TIMESTAMPDIFF(SECOND, trx_started, NOW()) AS duration_sec
FROM information_schema.innodb_trx
ORDER BY trx_started ASC;
```

> [!TIP]
> **Troncamento automatico dell'Undo**
> In MySQL 8.0, InnoDB esegue il troncamento degli undo tablespace in modo automatico per impostazione predefinita. Assicurati che i parametri `innodb_undo_log_truncate = ON` e `innodb_max_undo_log_size` (in genere pari a 1GB) siano configurati per recuperare automaticamente lo spazio su disco dell'undo tablespace.

---

## 4. Matrice di confronto MVCC

| Funzionalità | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Storage delle vecchie versioni** | In-Heap (direttamente nei file della tabella) | Undo Log (segmenti di rollback separati) |
| **Meccanismo di aggiornamento** | Delete + Insert (crea una nuova tupla) | Aggiornamento in loco + scrittura della vecchia versione nell'Undo |
| **Aggiornamenti degli indici** | Richiede l'aggiornamento di tutti gli indici (salvo HOT) | Aggiorna l'indice solo se la colonna indicizzata è cambiata |
| **Pulizia dello spazio su disco** | Vacuum / Autovacuum (recupera gli slot delle pagine) | Purge Thread (pulisce le pagine dell'Undo log) |
| **Rischio di Bloat** | Elevato bloat di tabelle e indici | Elevato bloat dell'Undo log con transazioni lunghe |

---

## 5. Riepilogo e buone pratiche

1. **Mantieni le transazioni brevi**: Entrambi i motori risentono delle transazioni lasciate aperte troppo a lungo. In Postgres, ciò impedisce all'autovacuum di ripulire le tuple morte; in MySQL, impedisce ai thread di purge di ripulire i log di undo.
2. **Configura il Fillfactor di PostgreSQL**: Per le tabelle con elevati volumi di aggiornamenti, riduci il parametro `fillfactor` della tabella (ad esempio, a 80 o 90) per lasciare spazio libero in ciascuna pagina per gli aggiornamenti HOT.
3. **Monitora lo spazio dell'Undo in MySQL**: Imposta avvisi (alert) per le transazioni attive a lungo termine per evitare l'esplosione dello spazio occupato dall'undo.
