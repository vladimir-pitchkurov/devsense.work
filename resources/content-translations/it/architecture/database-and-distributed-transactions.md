---
title: "Database e transazioni distribuite: isolamento, blocchi e modelli di consenso"
description: "Una guida completa su transazioni nei database, race condition, blocco ottimista vs pessimista, blocchi distribuiti basati su Redis, 2PC, orchestrazione/coreografia di Saga e il pattern Transactional Outbox con broker di messaggi."
published: 2026-06-14
faq:
  - question: "Qual è la differenza tra blocco ottimista e pessimista?"
    answer: "Il blocco pessimista previene i problemi di concorrenza bloccando le righe del database (usando SELECT FOR UPDATE), bloccando altre transazioni fino al rilascio del blocco. Il blocco ottimista presuppone che i conflitti siano rari; consente operazioni concorrenti ma verifica una colonna di versione o timestamp durante gli aggiornamenti. Se nel frattempo un'altra transazione ha modificato la riga, l'aggiornamento fallisce e l'applicazione deve riprovare."
  - question: "Perché il commit a due fasi (2PC) viene usato raramente nei microservizi moderni?"
    answer: "Il commit a due fasi è un protocollo sincrono e bloccante. Richiede che tutti i servizi partecipanti blocchino le risorse durante entrambe le fasi. Se un servizio rallenta o va offline, tutti gli altri rimangono bloccati, creando un singolo punto di guasto e limitando fortemente la scalabilità e la disponibilità del sistema."
  - question: "In che modo il pattern Transactional Outbox garantisce la consegna 'almeno una volta' (at-least-once)?"
    answer: "Invece di pubblicare un messaggio direttamente su un broker di messaggi durante una transazione del database (cosa che potrebbe fallire se il broker è offline), il servizio scrive sia i dati di business che il corpo del messaggio in una tabella 'outbox' all'interno della stessa transazione locale. Un processo in background indipendente interroga questa tabella in modo asincrono, invia i messaggi al broker e li contrassegna come elaborati, garantendo che nessun messaggio vada perduto."
---

# Database e transazioni distribuite: isolamento, blocchi e modelli di consenso

Immaginate un utente che cerca di prenotare l'ultimo posto disponibile su un volo. Due richieste arrivano ai server delle applicazioni esattamente nello stesso millisecondo. Se il sistema non è progettato per gestire la concorrenza, entrambe le richieste leggeranno lo stato del posto come \"disponibile\", addebiteranno il pagamento ed emetteranno due biglietti per lo stesso posto. Questo classico problema della doppia prenotazione è un incubo sia per gli sviluppatori che per le aziende. In un sistema monolitico con un unico database, le transazioni SQL possono facilmente evitarlo. Ma in un sistema distribuito, dove il servizio di prenotazione dei posti, il gateway di pagamento e il sistema di notifica sono microservizi isolati, una semplice transazione del database non è più sufficiente.

La progettazione di sistemi transazionali robusti richiede di adattare la granularità del blocco alla scala dell'architettura, passando da semplici blocchi SQL locali a modelli di consenso distribuiti per la coerenza tra servizi.

## Tabella dei contenuti
* [Transazioni locali: ACID e livelli di isolamento](#acid-isolation)
* [Race condition: blocco pessimista vs ottimista](#locking-strategies)
* [Blocchi distribuiti su larga scala: soluzioni basate su Redis](#redis-locks)
* [Transazioni distribuite: la caduta del commit a due fasi (2PC)](#distributed-2pc)
* [Coerenza finale: Saga e Transactional Outbox](#saga-outbox)
* [Dimostrazione pratica di codice in PHP & Laravel](#code-demo)
* [Limitazioni e compromessi](#limitations)
* [Consigli pratici](#takeaways)

---

<a id="acid-isolation"></a>
## Transazioni locali: ACID e livelli di isolamento

Al centro dell'integrità dei dati c'è il modello ACID: atomicità (Atomicity), coerenza (Consistency), isolamento (Isolation) e durabilità (Durability). Mentre l'atomicità (\"tutto o niente\") e la durabilità (archiviazione permanente) sono semplici, l'isolamento è il punto in cui prestazioni e correttezza si scontrano.

I database offrono quattro livelli di isolamento standard per gestire gli accessi concorrenti, ognuno dei quali evita diverse anomalie:

1. **Read Uncommitted (Lettura non confermata)**: Il livello più basso. Una transazione può leggere modifiche non confermate di un'altra transazione (Dirty Reads o letture sporche).
2. **Read Committed (Lettura confermata)**: Evita le letture sporche. Una query legge solo i dati confermati prima dell'inizio della query stessa. Tuttavia, se esegue nuovamente la query, potrebbe vedere modifiche confermate da altre transazioni nel frattempo (Non-repeatable Reads o letture non ripetibili).
3. **Repeatable Read (Lettura ripetibile)**: Evita le letture non ripetibili. I dati letti durante la transazione rimangono coerenti. In alcuni database, ciò non impedisce l'inserimento di nuove righe da parte di altre transazioni (Phantom Reads o letture fantasma).
4. **Serializable (Serializzabile)**: Il livello più alto. Le transazioni vengono eseguite in modo da produrre lo stesso risultato che si otterrebbe se venissero eseguite in sequenza, eliminando tutte le anomalie di concorrenza al costo di gravi limiti di prestazioni.

La maggior parte dei database relazionali (come PostgreSQL e MySQL) utilizza come impostazione predefinita **Read Committed** o **Repeatable Read**. Per ottenere la massima sicurezza senza sacrificare le prestazioni, gli sviluppatori devono controllare esplicitamente i blocchi piuttosto que affidarsi unicamente al livello di isolamento globale del database.

---

<a id="locking-strategies"></a>
## Race condition: blocco pessimista vs ottimista

Quando più client tentano di modificare contemporaneamente la stessa riga del database, dobbiamo scegliere una strategia di blocco.

### Blocco pessimista (Pessimistic Locking)
* **Punto**: Presuppone che i conflitti siano molto probabili e blocca l'accesso in modo proattivo.
* **Perché è importante**: Previene le race condition direttamente a livello di database utilizzando i meccanismi di blocco interni del database.
* **Esempio**: L'esecuzione di `SELECT ... FOR UPDATE` blocca le righe selezionate. Qualsiasi altra transazione che tenti di leggere quelle righe con `FOR UPDATE` o di modificarle sarà bloccata fino al commit o al rollback della prima transazione.
* **Conseguenza**: Sicurezza elevata ma scarsa concorrenza. Se le transazioni richiedono tempo (ad esempio, in attesa di richieste di rete esterne), gli altri thread del database esauriscono rapidamente il pool di connessioni, provocando il crash dell'applicazione.

### Blocco ottimista (Optimistic Locking)
* **Punto**: Presuppone che i conflitti siano rari, consentendo letture concorrenti ma validando in fase di scrittura.
* **Perché è importante**: Evita completamente i blocchi sul database, massimizzando il throughput di lettura e la scalabilità.
* **Esempio**: Ogni riga ha una colonna `version` (intero) o `updated_at` (timestamp). Durante l'aggiornamento, l'istruzione SQL è strutturata come:
  ```sql
  UPDATE inventory SET quantity = quantity - 1, version = version + 1 
  WHERE id = 1 AND version = 3;
  ```
* **Conseguenza**: Se un'altra transazione avesse aggiornato per prima la riga, la versione sarebbe 4 e questa query aggiornerebbe 0 righe. L'applicazione rileva questo scenario e decide se riprovare o fallire. Tuttavia, in presenza di un'elevata contesa di scrittura, i frequenti tentativi possono ridurre le prestazioni.

---

<a id="redis-locks"></a>
## Blocchi distribuiti su larga scala: soluzioni basate su Redis

Nelle applicazioni ad alta concorrenza, il blocco a livello di database può sovraccaricare la CPU del database stesso. Inoltre, se è necessario bloccare un processo di business che non è mappato su una singola riga di tabella, o bloccare operazioni su sistemi diversi, i blocchi del database sono insufficienti.

* **Punto**: Utilizzare una memoria in-memory veloce come Redis per gestire i blocchi al di fuori del database relazionale.
* **Perché è importante**: Le operazioni di Redis sono monothread ed eseguite in modo atomico, il che le rende estremamente veloci (sotto il millisecondo) e libera connessioni sul database.
* **Esempio**: Un processo acquisisce un blocco utilizzando il comando atomico `SET lock_key unique_token NX PX 5000` (imposta se non esiste, scade dopo 5000 ms). Durante il rilascio, uno script Lua verifica che il processo attuale possieda il token unico prima di eliminare la chiave.
* **Conseguenza**: Altamente scalabile. Tuttavia, se Redis si arresta, o in un ambiente cluster in cui un master si guasta prima di replicare il blocco su una replica (split-brain), potrebbero essere concessi blocchi duplicati. L'algoritmo Redlock risolve questo problema acquisendo blocchi su più nodi Redis indipendenti.

---

<a id="distributed-2pc"></a>
## Transazioni distribuite: la caduta del commit a due fasi

Quando si passa a un'architettura a microservizi, i dati vengono divisi tra i confini dei servizi. Una transazione di business può coinvolgere un Servizio Clienti, un Servizio Inventario e un Servizio Pagamenti.

Storicamente, la soluzione standard per le transazioni distribuite era il **Commit a due fasi (2PC)**:
1. **Fase di preparazione (Prepare)**: Un coordinatore chiede a tutti i servizi partecipanti se sono pronti per il commit. I servizi bloccano le risorse locali e rispondono \"sì\".
2. **Fase di commit (Commit)**: Se tutti i servizi hanno risposto \"sì\", il coordinatore ordina loro di eseguire il commit. Se uno ha risposto \"no\", ordina a tutti di eseguire il rollback.

Sebbene concettualmente semplice, il 2PC è un **protocollo sincrono e bloccante**. Se la rete fallisce o un servizio si disconnette durante la fase di commit, le risorse rimangono bloccate a tempo indeterminato. Ciò riduce drasticamente il throughput e viola il principio di disponibilità del teorema CAP. I sistemi distribuiti moderni scambiano la coerenza atomica con la coerenza finale (eventual consistency).

---

<a id="saga-outbox"></a>
## Coerenza finale: Saga e Transactional Outbox

Per garantire l'affidabilità tra più microservizi senza bloccare le risorse, utilizziamo modelli di progettazione asincroni.

### Pattern Saga
Una Saga è una sequenza di transazioni locali. Ciascun servizio aggiorna il proprio database in una transazione locale e pubblica un evento. Altri servizi ascoltano questo evento ed eseguono le loro transazioni locali. Se una transazione fallisce, la Saga esegue **transazioni compensative** all'indietro per annullare le modifiche.

* **Coreografia**: I servizi pubblicano e si iscrivono agli eventi senza un coordinatore centrale. Rapido e disaccoppiato, ma difficile da tracciare in flussi di lavoro complessi.
* **Orchestrazione**: Un servizio centrale orchestra il flusso, chiamando i servizi e gestendo la logica di compensazione. Più facile da modellare, ma introduce un singolo punto di orchestrazione.

### Pattern Transactional Outbox
Un anti-pattern comune consiste nel pubblicare un evento su un broker di messaggi (come RabbitMQ) direttamente all'interno di una transazione del database. Se il broker è offline, la transazione viene annullata. Se il broker ha successo ma il database non esegue il commit, si generano eventi fantasma.

Il pattern **Transactional Outbox** risolve questo problema:
1. Scrivere sia le modifiche al modello di dominio che i dati dell'evento (in una tabella `outbox`) all'interno della stessa transazione del database.
2. Un processo in background indipendente (Message Relay) interroga la tabella `outbox`, pubblica i messaggi sul broker e li contrassegna come inviati.
3. Questo garantisce la **consegna almeno una volta**. Il ricevitore deve implementare l'**idempotenza** (l'elaborazione ripetuta dello stesso messaggio produce lo stesso risultato) per gestire i duplicati in modo sicuro.

---

<a id="code-demo"></a>
## Dimostrazione pratica di codice in PHP & Laravel

Ecco come puoi implementare questi pattern in un'applicazione Laravel.

### 1. Transazione del database con blocco pessimista
```php
// app/Services/BookingService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\FlightSeat;

class BookingService
{
    public function bookSeat(int $seatId, int $userId): bool
    {
        return DB::transaction(function () use ($seatId, $userId) {
            // Blocca la riga per l'aggiornamento. Gli altri thread si bloccano qui.
            $seat = FlightSeat::where('id', $seatId)
                ->lockForUpdate()
                ->first();

            if (!$seat || $seat->is_booked) {
                return false;
            }

            $seat->update([
                'is_booked' => true,
                'user_id' => $userId,
            ]);

            return true;
        });
    }
}
```

### 2. Blocco distribuito con Redis
```php
// app/Services/InventoryService.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class InventoryService
{
    public function decreaseStock(int $productId, int $quantity): bool
    {
        $lockKey = "lock:product:{$productId}";
        
        // Tenta di acquisire il blocco per 10 secondi, attende fino a 3 secondi.
        $lock = Cache::lock($lockKey, 10);

        if ($lock->get()) {
            try {
                // Aggiornamento rapido e non bloccante
                // ... logica di aggiornamento delle scorte
                return true;
            } finally {
                $lock->release();
            }
        }

        return false;
    }
}
```

### 3. Inserimento in Transactional Outbox
```php
// app/Services/OrderService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OutboxMessage;

class OrderService
{
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create($data);

            // Registra l'evento all'interno della stessa transazione
            OutboxMessage::create([
                'event_type' => 'order.created',
                'payload' => json_encode([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'total' => $order->total_amount,
                ]),
                'processed' => false,
            ]);

            return $order;
        });
    }
}
```

---

<a id="limitations"></a>
## Limitazioni e compromessi

* **Blocco ottimista**: Sotto carichi di scrittura concorrenti estremamente elevati, gli aggiornamenti ottimisti falliranno frequentemente, causando errori per le richieste dei client o forzando un uso intensivo della CPU durante i tentativi.
* **Blocchi Redis**: Un blocco è sicuro solo per la durata della sua scadenza (TTL). Se un processo impiega più tempo rispetto alla durata del blocco (ad esempio, a causa di una pausa per la garbage collection), il blocco scade, un altro processo lo acquisisce e si perde l'esclusione mutua.
* **Saga**: Le transazioni compensative non possono annullare fisicamente gli effetti collaterali esterni (come l'invio di un'e-mail o un addebito bancario). Pertanto, il flusso di lavoro aziendale deve essere progettato con stati intermedi (come la prenotazione di fondi o bozze di e-mail).

---

<a id="takeaways"></a>
## Consigli pratici

1. Utilizza il **blocco ottimista** per operazioni con un elevato volume di lettura e conflitti non frequenti (ad esempio, modifica di contenuti o aggiornamenti di profili).
2. Utilizza il **blocco pessimista** (`SELECT ... FOR UPDATE`) per transazioni finanziarie critiche con concorrenza moderata, in cui i conflitti devono essere risolti all'interno del database.
3. Utilizza i **blocchi Redis** per proteggere i flussi di lavoro dell'applicazione ed evitare che più worker eseguano attività identiche contemporaneamente.
4. Per i microservizi, evita transazioni distribuite sincrone (come 2PC). Implementa il **pattern Saga** per l'orchestrazione e il **pattern Transactional Outbox** per garantire una consegna asincrona e affidabile degli eventi.
