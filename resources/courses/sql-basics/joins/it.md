# Unire le tabelle (JOIN)

In un database relazionale normalizzato, i dati vengono suddivisi in più tabelle per eliminare la ridondanza e mantenere l'integrità. Ad esempio, invece di ripetere i dettagli del dipartimento per ogni dipendente, i dipendenti vengono memorizzati in una tabella e i dipartimenti in un'altra, collegandoli tramite una chiave esterna (Foreign Key).

Per ricostruire una vista unificata dei dati, si utilizzano i **Join**. Un join combina colonne provenienti da due o più tabelle sulla base di una colonna correlata comune. In questo capitolo impareremo a conoscere i diversi tipi di join, le loro condizioni e vedremo come PostgreSQL e MySQL li eseguono a livello interno.

---

## 1. Visualizzazione dei tipi di Join

SQL offre diversi modi per unire le tabelle, ciascuno dei quali segue una logica differente:

| Tipo di Join | Descrizione |
| :--- | :--- |
| **`INNER JOIN`** | Restituisce i record che presentano valori corrispondenti in entrambe le tabelle. |
| **`LEFT JOIN`** | Restituisce tutti i record della tabella di sinistra e i record corrispondenti di quella di destra. Restituisce `NULL` per la tabella di destra se non c'è corrispondenza. |
| **`RIGHT JOIN`** | Restituisce tutti i record della tabella di destra e i record corrispondenti di quella di sinistra. Restituisce `NULL` per la tabella di sinistra se non c'è corrispondenza. |
| **`FULL JOIN`** | Restituisce tutti i record quando è presente una corrispondenza nella tabella di sinistra o in quella di destra. |
| **`CROSS JOIN`** | Restituisce il prodotto cartesiano di entrambe le tabelle (ogni riga della tabella A viene associata a ogni riga della tabella B). |

---

## 2. Sintassi ed esempi

Ipotizziamo di avere due tabelle: `employees` (con una colonna `department_id`) e `departments` (con una colonna `id`).

### Inner Join
Recupera solo i dipendenti che appartengono a un dipartimento e solo i dipartimenti che hanno dipendenti.
```sql
-- MySQL & PostgreSQL
SELECT e.name AS employee_name, d.name AS department_name
FROM employees e
INNER JOIN departments d ON e.department_id = d.id;
```

### Left Join (il tipo più comune di Outer Join)
Recupera tutti i dipendenti, inclusi quelli che non appartengono a nessun dipartimento (il loro `department_name` verrà restituito come `NULL`).
```sql
-- MySQL & PostgreSQL
SELECT e.name AS employee_name, d.name AS department_name
FROM employees e
LEFT JOIN departments d ON e.department_id = d.id;
```

### Condizioni di Join: `ON` vs. `USING`
Quando le colonne utilizzate per il join hanno lo stesso identico nome in entrambe le tabelle (ad es., `department_id` sia in `employees` che in `departments`), si può utilizzare la sintassi abbreviata e più pulita `USING` al posto di `ON`.
```sql
-- MySQL & PostgreSQL
SELECT e.name, d.name
FROM employees e
INNER JOIN departments d USING (department_id);
```

---

## 3. Differenze principali: MySQL vs. PostgreSQL

### Supporto nativo per `FULL OUTER JOIN`
* **PostgreSQL** supporta nativamente lo standard `FULL OUTER JOIN` (o semplicemente `FULL JOIN`).
* **MySQL** NON supporta il `FULL JOIN`. Se si tenta di utilizzarlo, MySQL restituirà un errore di sintassi.

#### Soluzione alternativa in MySQL per il FULL JOIN
Per ottenere un Full Outer Join in MySQL, è necessario scrivere un `LEFT JOIN` e un `RIGHT JOIN` sulle stesse tabelle e combinare i risultati utilizzando l'operatore `UNION` (che rimuove automaticamente le righe duplicate).

```sql
-- PostgreSQL (Native syntax)
SELECT e.name, d.name
FROM employees e
FULL JOIN departments d ON e.department_id = d.id;

-- MySQL Workaround (Emulation)
SELECT e.name, d.name
FROM employees e
LEFT JOIN departments d ON e.department_id = d.id
UNION
SELECT e.name, d.name
FROM employees e
RIGHT JOIN departments d ON e.department_id = d.id;
```

### Algoritmi di Join e prestazioni interne
In che modo i motori di database calcolano i join? Utilizzano diversi algoritmi interni:
* **PostgreSQL**: Dispone di un ottimizzatore estremamente sofisticato che supporta da decenni i join di tipo **Nested Loop**, **Hash Join** e **Merge Join**. Seleziona l'algoritmo migliore in base alle dimensioni delle tabelle e alla presenza di indici.
* **MySQL**: Storicamente, MySQL supportava solo i join di tipo **Nested Loop** (che sono lenti per tabelle di grandi dimensioni poiché richiedono la scansione ciclica della seconda tabella per ogni riga della prima). MySQL 8.0 ha introdotto gli **Hash Join** per ottimizzare le operazioni di join su colonne di grandi dimensioni non indicizzate, allineando le prestazioni a quelle di PostgreSQL.

> [!WARNING]
> **La trappola del prodotto cartesiano!**
> L'esecuzione di un `CROSS JOIN` (o la dimenticanza di una condizione di join nella vecchia sintassi SQL come `FROM tabella_a, tabella_b`) crea un prodotto cartesiano. Se la Tabella A ha 10.000 righe e la Tabella B ha 10.000 righe, un CROSS JOIN genererà **100.000.000 (100 milioni) di righe**, esaurendo istantaneamente la memoria del server e bloccando il database.

> [!TIP]
> **Lo sapevi?**
> Quando si filtrano le colonne in un `LEFT JOIN`, l'inserimento del filtro nella clausola `ON` rispetto alla clausola `WHERE` cambia completamente il risultato.
> - **In `ON`**: Filtra la tabella di destra *prima* del join. La tabella di sinistra restituisce comunque tutte le sue righe.
> - **In `WHERE`**: Filtra il risultato *dopo* il join, trasformando di fatto il `LEFT JOIN` in un restrittivo `INNER JOIN` poiché verifica la presenza di valori in una colonna che potrebbe essere diventata `NULL`.

---

## 4. Riepilogo e buone pratiche

1. **Preferisci `LEFT JOIN` rispetto a `RIGHT JOIN`**: I left join sono molto più facili da leggere e visualizzare poiché le query SQL si leggono da sinistra a destra (e dall'alto in basso).
2. **Presta attenzione a `WHERE` sui Join esterni**: Non filtrare le colonne della tabella di destra nella clausola `WHERE` a meno che tu non stia verificando se sia `IS NULL` per trovare le righe che non corrispondono.
3. **Ricorda il limite del `FULL JOIN` in MySQL**: Usa la soluzione alternativa `LEFT JOIN UNION RIGHT JOIN` in MySQL.
4. **Usa la sintassi di Join esplicita**: Utilizza sempre le parole chiave di join esplicite (`INNER JOIN`, `LEFT JOIN`) invece delle tabelle separate da virgole nella clausola `FROM` (`FROM tabella_a, tabella_b`) per evitare prodotti cartesiani accidentali.
