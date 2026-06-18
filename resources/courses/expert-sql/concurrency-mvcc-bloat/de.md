# Parallelität, MVCC und Tabellen-Bloat

In einer Datenbank mit hohem Durchsatz lesen und schreiben Tausende von Transaktionen gleichzeitig Daten. Wenn jeder Lesevorgang die Zeile sperren würde, kämen Datenbanken zum Stillstand. Um dies zu lösen, verwenden moderne relationale Datenbanken die **Multi-Version Concurrency Control (MVCC)**. Die Kernphilosophie von MVCC ist einfach: *Leser blockieren keine Schreiber, und Schreiber blockieren keine Leser*. MySQL und PostgreSQL implementieren diese Philosophie jedoch auf grundlegend unterschiedliche Weise, was zu völlig unterschiedlichen Performance-Profilen, Wartungsanforderungen und Optimierungsstrategien führt.

---

## 1. Wie MVCC unter der Haube funktioniert

Wenn eine Transaktion eine Zeile aktualisiert, überschreibt die Datenbank die alten Daten nicht sofort. Stattdessen verwaltet sie mehrere Versionen dieser Zeile, sodass aktive Transaktionen einen konsistenten Snapshot der Daten basierend auf ihrem Isolationslevel sehen können.

### PostgreSQL: Tupel-Versionierung (In-Heap)
PostgreSQL hält alle Zeilenversionen (als „Tupel“ bezeichnet) direkt im Tabellen-Heap.
* **xmin**: Die Transaktions-ID (TxID) der Transaktion, die die Zeile eingefügt hat.
* **xmax**: Die Transaktions-ID der Transaktion, die die Zeile gelöscht oder aktualisiert hat (anfangs `0` oder null).

Wenn Sie eine Zeile in Postgres aktualisieren, führt dies ein logisches `DELETE`, gefolgt von einem `INSERT` aus. Es markiert das `xmax` der bestehenden Zeile mit der aktuellen TxID und fügt eine neue Zeile ein, deren `xmin` der aktuellen TxID entspricht.

### MySQL (InnoDB): Undo Logs & Rollback-Segmente
Die InnoDB-Engine von MySQL verfolgt einen anderen Ansatz. Sie führt Aktualisierungen direkt an Ort und Stelle (in-place) im Tablespace durch, schreibt jedoch die alte Version der Zeile in eine dedizierte Struktur namens **Undo Log**.
* Jeder Zeilen-Header enthält eine `DB_TRX_ID` (die Transaktions-ID, die die Zeile zuletzt geändert hat) und einen `DB_ROLL_PTR` (einen Roll-Pointer, der auf den Undo-Log-Datensatz zeigt, der die vorherige Version enthält).
* Wenn ein Lesevorgang einen älteren Snapshot benötigt, beginnt InnoDB mit der aktiven Zeile und durchläuft die Undo-Kette mithilfe des Roll-Pointers rückwärts, um die Daten dynamisch zu rekonstruieren.

---

## 2. PostgreSQL-Bloat, VACUUM und HOT-Updates

Da PostgreSQL tote Tupel (alte Versionen aktualisierter/gelöschter Zeilen) direkt in den Tabellenseiten speichert, wachsen Tabellen im Laufe der Zeit natürlicherweise an. Dieses Phänomen ist als **Tabellen-Bloat** (Tabellenaufblähung) bekannt.

```
PostgreSQL Heap-Page-Update:
+-------------------------------------------------------------+
| [Tupel v1 (xmin: 100, xmax: 101)] -> Totes Tupel (Bloat)     |
| [Tupel v2 (xmin: 101, xmax: 0)]   -> Lebendes Tupel          |
+-------------------------------------------------------------+
```

### Die Rolle von VACUUM
Um durch tote Tupel belegten Speicherplatz zurückzugewinnen, führt PostgreSQL einen Hintergrundprozess namens **Autovacuum** aus.
* **Standard-VACUUM**: Scannt Seiten und markiert tote Tupel als wiederverwendbar für zukünftige Inserts. Es gibt den Speicherplatz *nicht* an das Betriebssystem zurück (es sei denn, Seiten ganz am Ende der Tabelle sind völlig leer).
* **VACUUM FULL**: Baut die gesamte Tabelle neu auf, schrumpft sie und gibt den Speicherplatz an das Betriebssystem zurück. **Warnung**: Dies sperrt die Tabelle exklusiv (`ACCESS EXCLUSIVE`), wodurch alle Lese- und Schreibvorgänge blockiert werden.

### Heap-Only-Tuple (HOT) Optimierung
Um zu verhindern, dass bei jeder Änderung einer Zeilenversion die Index-Pointer aktualisiert werden müssen, verwendet PostgreSQL **HOT-Updates**. Wenn eine Aktualisierung keine indizierten Spalten ändert und die Seite genügend freien Speicherplatz aufweist:
1. Postgres platziert das neue Tupel auf derselben Seite.
2. Es verkettet das alte Tupel mit dem neuen Tupel.
3. Indizes zeigen weiterhin auf das alte Tupel; Leser folgen der Kette.

> [!WARNING]
> **Autovacuum-Tuning ist Pflicht!**
> Die standardmäßigen Autovacuum-Einstellungen von PostgreSQL sind berüchtigt konservativ. Bei Tabellen mit hoher Schreiblast führt dies zu unkontrolliertem Bloat, was die Abfrageleistung verschlechtert, da die Datenbank tote Tupel scannen muss. Passen Sie `autovacuum_vacuum_scale_factor` (Standardwert 0.20 bzw. 20% geänderte Zeilen) bei großen Tabellen immer auf `0.05` oder weniger an.

---

## 3. MySQL InnoDB: Undo Purging und Truncation

In MySQL ist Tabellen-Bloat ein weitaus geringeres Problem, da alte Zeilenversionen in den Undo Logs statt im Tabellenbereich gespeichert werden. InnoDB hat jedoch seine eigenen Parallelitäts-Engpässe.

### InnoDB Purge Threads (Bereinigungs-Threads)
Sobald die älteste aktive Transaktion einen Undo-Log-Datensatz nicht mehr benötigt, bereinigt ein Hintergrundprozess namens **Purge Thread** die Undo-Log-Seiten.
* Wenn eine Transaktion stundenlang offen bleibt (z. B. bei einer lang laufenden Berichtsabfrage), InnoDB kann keine Undo-Logs bereinigen, die seit dem Start dieser Transaktion erstellt wurden.
* Dies führt dazu, dass der **Undo Tablespace** anschwillt. In älteren MySQL-Versionen konnten Undo-Tablespaces nicht schrumpfen, was einen vollständigen Neuaufbau der Datenbank erforderte, um Festplattenspeicher zurückzugewinnen.

```sql
-- MySQL: Inspecting InnoDB Undo Log & Transaction States
SELECT 
    trx_id, trx_state, trx_started, 
    TIMESTAMPDIFF(SECOND, trx_started, NOW()) AS duration_sec
FROM information_schema.innodb_trx
ORDER BY trx_started ASC;
```

> [!TIP]
> **Automatische Undo-Truncation**
> In MySQL 8.0 schneidet InnoDB Undo-Tablespaces und standardmäßig automatisch ab (Truncation). Stellen Sie sicher, dass `innodb_undo_log_truncate = ON` and `innodb_max_undo_log_size` (typischerweise 1 GB) konfiguriert sind, um den Speicherplatz im Undo-Tablespace automatisch freizugeben.

---

## 4. MVCC-Vergleichsmatrix

| Feature | PostgreSQL | MySQL (InnoDB) |
| :--- | :--- | :--- |
| **Speicherung alter Versionen** | In-Heap (direkt in den Tabellendateien) | Undo Logs (separate Rollback-Segmente) |
| **Update-Mechanismus** | Delete + Insert (erzeugt neues Tupel) | In-place Update + alte Version in Undo schreiben |
| **Index-Updates** | Erfordert Aktualisierung aller Indizes (außer bei HOT) | Aktualisiert Index nur, wenn sich die indizierte Spalte geändert hat |
| **Bereinigung des Festplattenspeichers** | Vacuum / Autovacuum (gibt Seitenslots frei) | Purge Threads (bereinigt Undo-Log-Seiten) |
| **Risiko von Bloat** | Hoher Tabellen- & Index-Bloat | Hoher Undo-Log-Bloat bei lang laufenden Transaktionen |

---

## 5. Zusammenfassung & Best Practices

1. **Transaktionen kurz halten**: Beide Engines leiden darunter, wenn Transaktionen zu lange geöffnet bleiben. In Postgres verhindert dies, dass Autovacuum tote Tupel bereinigt; in MySQL hindert es die Purge-Threads daran, die Undo-Logs zu bereinigen.
2. **PostgreSQL-Fillfactor konfigurieren**: Reduzieren Sie bei Tabellen mit hohem Aktualisierungsvolumen den `fillfactor` der Tabelle (z. B. auf 80 oder 90), um freien Platz auf jeder Seite für HOT-Updates zu lassen.
3. **Undo-Speicher in MySQL überwachen**: Richten Sie Warnmeldungen für lang laufende aktive Transaktionen ein, um eine Explosion des Undo-Speicherplatzes zu verhindern.
