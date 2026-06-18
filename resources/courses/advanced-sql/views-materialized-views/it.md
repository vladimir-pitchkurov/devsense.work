# Viste e viste materializzate: astrazione vs. caching

Nella progettazione di database ad alte prestazioni ci si trova spesso ad affrontare due problemi contrapposti:
1. **Complessità delle query**: Scrittura e manutenzione di query lunghe e nidificate che si estendono su decine di join.
2. **Latenza di esecuzione**: Esecuzione di query con pesanti funzioni di aggregazione (come i report sulle vendite) su milioni di righe a ogni caricamento di pagina.

SQL affronta queste sfide con le **Viste (Views)** e le **Viste materializzate (Materialized Views)**. Sebbene abbiano nomi simili, i loro modelli di esecuzione sottostanti sono completamente diversi: una è una scorciatoia logica (astrazione), l'altra è una cache fisica sotto forma di tabella.

---

## 1. Viste standard (virtuali)

Una **Vista** standard è la definizione di una query salvata. Si tratta di una tabella virtuale: non memorizza dati fisici sul disco. Quando si interroga una vista, il motore di database unisce la definizione della query della vista alla query principale e le esegue insieme.

### Sintassi di base
```sql
-- MySQL & PostgreSQL
CREATE VIEW active_customer_summary AS
SELECT c.id, c.name, COUNT(o.id) AS total_orders
FROM customers c
LEFT JOIN orders o ON c.id = o.customer_id
WHERE c.status = 'active'
GROUP BY c.id, c.name;
```

### Viste aggiornabili
È possibile eseguire istruzioni `INSERT`, `UPDATE` o `DELETE` su una vista? Sì, a condizioni rigorose. Una vista è aggiornabile solo se il motore del database può mappare le operazioni di scrittura direttamente a una singola tabella fisica sottostante.
* **Regole**: La vista non deve contenere:
  - Funzioni di aggregazione (`SUM`, `COUNT`, `AVG`).
  - Clausole `GROUP BY`, `HAVING` o `DISTINCT`.
  - Operatori sui set (`UNION`, `INTERSECT`, `EXCEPT`).
  - Funzioni finestra.

> [!WARNING]
> **La clausola `WITH CHECK OPTION`**
> Quando si aggiornano i dati tramite una vista, si rischia accidentalmente di scrivere dati che fanno sparire la riga modificata dalla vista stessa!
> ```sql
> CREATE VIEW premium_customers AS 
> SELECT * FROM customers WHERE balance > 1000;
> ```
> Se si esegue `UPDATE premium_customers SET balance = 500 WHERE id = 1`, l'aggiornamento va a buon fine, ma il cliente scompare dalla vista. Per evitare questo comportamento, aggiungi `WITH CHECK OPTION` alla definizione della vista. Ciò costringe il database a rifiutare qualsiasi inserimento o aggiornamento che violi la clausola `WHERE` della vista stessa.

---

## 2. Viste materializzate (PostgreSQL)

A differenza delle viste standard, una **Vista materializzata** memorizza fisicamente i risultati della query sul disco, comportandosi come una normale tabella. Interrogare una vista materializzata è estremamente veloce perché evita l'esecuzione di join e calcoli di aggregazione. Tuttavia, i dati possono diventare obsoleti.

### Sintassi e aggiornamento (Refresh)
```sql
-- PostgreSQL Only
CREATE MATERIALIZED VIEW monthly_revenue_report AS
SELECT extract(year from order_date) as year, extract(month from order_date) as month, SUM(total_amount) as revenue
FROM orders
GROUP BY 1, 2;
```

Per aggiornare i dati, è necessario avviare manualmente un aggiornamento:
```sql
REFRESH MATERIALIZED VIEW monthly_revenue_report;
```

### Aggiornamenti non bloccanti: `CONCURRENTLY`
Per impostazione predefinita, il comando `REFRESH MATERIALIZED VIEW` applica un lock esclusivo sulla vista, bloccando tutte le operazioni di lettura (`SELECT`) fino al completamento dell'aggiornamento. Per aggiornare la vista senza bloccare gli utenti, si utilizza l'opzione `CONCURRENTLY`.

```sql
-- PostgreSQL Only: Non-blocking refresh
CREATE UNIQUE INDEX idx_monthly_rev ON monthly_revenue_report (year, month);
REFRESH MATERIALIZED VIEW CONCURRENTLY monthly_revenue_report;
```
* **Requisito**: È necessario creare un indice univoco su una o più colonne della vista materializzata prima di poter utilizzare la clausola `CONCURRENTLY`.

---

## 3. Soluzioni alternative in MySQL per le viste materializzate

MySQL **non** supporta nativamente le viste materializzate. Se si ha bisogno di questa funzionalità in MySQL, è necessario simularla utilizzando una delle due soluzioni comuni descritte di seguito.

### Soluzione alternativa 1: Tabella normale + Event Scheduler
Si può creare una tabella normale che funga da cache e scrivere un evento pianificato nel database per aggiornarla periodicamente.

```sql
-- MySQL Only
-- 1. Create the physical table
CREATE TABLE monthly_revenue_report_cache AS
SELECT YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as revenue
FROM orders GROUP BY 1, 2;

-- 2. Create an event to refresh the table every hour
CREATE EVENT refresh_revenue_report
ON SCHEDULE EVERY 1 HOUR
DO
  BEGIN
    TRUNCATE TABLE monthly_revenue_report_cache;
    INSERT INTO monthly_revenue_report_cache
    SELECT YEAR(order_date) as year, MONTH(order_date) as month, SUM(total_amount) as revenue
    FROM orders GROUP BY 1, 2;
  END;
```

### Soluzione alternativa 2: Trigger del database
Se si ha bisogno di viste materializzate in tempo reale in MySQL, si possono scrivere trigger del tipo `AFTER INSERT/UPDATE/DELETE` sulla tabella di origine per aggiornare in modo incrementale la tabella di cache del riepilogo.

---

## 4. Confronto delle funzionalità: MySQL vs. PostgreSQL

| Funzionalità | MySQL 8.0 | PostgreSQL |
| :--- | :--- | :--- |
| Viste virtuali standard | Supportate nativamente | Supportate nativamente |
| Viste aggiornabili | Supportate (con restrizioni) | Supportate (con restrizioni) |
| Viste materializzate native | *Non supportate* | Supportate nativamente |
| Aggiornamento concorrente/non bloccante | *Non supportato* | Supportato nativamente (tramite `CONCURRENTLY`) |
| Sicurezza della vista (DEFINER/INVOKER) | Supportato nativamente | Supportato nativamente |

> [!TIP]
> **Suggerimento per la sicurezza: INVOKER vs. DEFINER**
> Per impostazione predefinita, le viste standard in MySQL e PostgreSQL vengono eseguite con i privilegi dell'utente che ha *creato* la vista (`DEFINER`). Si tratta di una potente funzionalità che consente di concedere agli utenti l'accesso a specifici sottoinsiemi di dati in una tabella (ad esempio, escludendo una colonna password) senza dover concedere loro i permessi di lettura sull'intera tabella sottostante.

---

## 5. Riepilogo e buone pratiche

1. **Usa le viste standard per l'astrazione**: Le viste standard sono eccellenti per semplificare query complesse e implementare ruoli di sicurezza nel database.
2. **Usa le viste materializzate per la cache**: Per calcoli di aggregazione pesanti su sistemi con molte letture, memorizza i dati fisicamente in una cache.
3. **Esegui sempre l'aggiornamento in modo concorrente**: Negli ambienti di produzione PostgreSQL, crea sempre un indice univoco sulle viste materializzate per poterle aggiornare in modo concorrente.
4. **Usa l'Event Scheduler o i Trigger in MySQL**: Se utilizzi MySQL, progetta una tabella di cache utilizzando gli eventi o implementa una cache a livello applicativo per simulare le viste materializzate.
