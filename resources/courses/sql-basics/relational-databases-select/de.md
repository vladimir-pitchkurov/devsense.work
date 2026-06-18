# Relationale Datenbanken & Die SELECT-Anweisung

Das Herzstück der modernen Datenspeicherung ist das relationale Datenbankmodell, das 1970 erstmals von Edgar F. Codd vorgeschlagen wurde. Anstatt Daten in unstrukturierten Textdateien oder starren hierarchischen Bäumen zu speichern, organisiert eine relationale Datenbank Informationen in strukturierten Tabellen, die mithilfe der Structured Query Language (SQL) dynamisch verknüpft und abgefragt werden können. Unabhängig davon, ob Sie MySQL oder PostgreSQL verwenden, ist das Verständnis der grundlegenden Konzepte des relationalen Entwurfs und der effizienten Datenabfrage von entscheidender Bedeutung.

---

## 1. Das relationale Modell: Tabellen, Spalten und Schlüssel

In einer relationalen Datenbank werden Daten als eine Sammlung von relationalen Tabellen dargestellt. Jede Tabelle besteht aus:
* **Spalten (Attribute)**: Definieren den Datentyp und die Eigenschaften der gespeicherten Informationen (z. B. `user_id` als Ganzzahl, `email` als Zeichenkette).
* **Zeilen (Datensätze/Tupel)**: Repräsentieren einzelne Instanzen von Daten (z. B. den Datensatz eines bestimmten Benutzers).

Um die Integrität zu wahren, stützen sich Tabellen auf zwei wesentliche Konzepte:
1. **Primärschlüssel (Primary Key - PK)**: Eine Spalte (oder ein Satz von Spalten), die jede Zeile in einer Tabelle eindeutig identifiziert. Sie darf keine `NULL`-Werte enthalten.
2. **Fremdschlüssel (Foreign Key - FK)**: Eine Spalte in einer Tabelle, die auf den Primärschlüssel einer anderen Tabelle verweist und so eine Beziehung zwischen ihnen herstellt.

| Konzept | Beschreibung | Analogie |
| :--- | :--- | :--- |
| **Tabelle** | Ein strukturiertes Gitter aus Spalten und Zeilen. | Ein Tabellenblatt. |
| **Zeile** | Ein einzelner Datensatz. | Eine einzelne Zeile in einem Tabellenblatt. |
| **Primärschlüssel** | Eindeutiger Bezeichner für eine Zeile. | Reisepassnummer. |
| **Fremdschlüssel** | Verweis auf den Primärschlüssel einer anderen Tabelle. | Die Abteilungs-ID eines Mitarbeiters. |

---

## 2. Daten abfragen mit `SELECT`

Die grundlegendste Operation in SQL ist das Abfragen von Daten mit der `SELECT`-Anweisung. In ihrer einfachsten Form erfordert eine Abfrage eine `SELECT`-Klausel (welche Spalten abgefragt werden sollen) und eine `FROM`-Klausel (welche Tabelle abgefragt werden soll).

### Alle Spalten vs. spezifische Spalten auswählen
Sie können alle Spalten mithilfe des Sternchen-Wildcards (`*`) abfragen oder nur die Spalten angeben, die Sie benötigen.
```sql
-- MySQL & PostgreSQL
-- Retrieve all columns (not recommended for production)
SELECT * FROM users;

-- Retrieve specific columns (recommended)
SELECT id, email, first_name FROM users;
```

> [!TIP]
> **Wussten Sie schon?**
> Die Verwendung von `SELECT *` in der Produktionsumgebung gilt als Anti-Pattern. Es zwingt die Datenbank-Engine zu zusätzlichen Festplatten-I/O-Operationen, um Spalten zu lesen, die Sie gar nicht benötigen, verbraucht unnötige Netzwerkbandbreite und kann Ihre Anwendung beschädigen, wenn eine neue Spalte hinzugefügt oder eine alte aus der Tabelle entfernt wird.

### Spalten-Aliase (`AS`)
Aliase ermöglichen es Ihnen, Spalten in Ihrer Abfrageergebnisliste umzubenennen, um die Lesbarkeit zu verbessern oder den Anforderungen der Anwendung gerecht zu werden.
```sql
-- MySQL & PostgreSQL
SELECT first_name AS given_name, last_name AS surname FROM users;
```

---

## 3. Ergebnisse filtern mit `WHERE`

Um nur bestimmte Zeilen abzufragen, verwenden Sie die `WHERE`-Klausel. Die `WHERE`-Klausel wertet eine boolesche Bedingung für jede Zeile aus und gibt nur diejenigen zurück, die `true` (wahr) ergeben.

### Standard-Operatoren
SQL unterstützt die standardmäßigen Vergleichsoperatoren:
* `=` (gleich)
* `<>` oder `!=` (ungleich)
* `>` (größer als), `<` (kleiner als)
* `>=` (größer oder gleich), `<=` (kleiner oder gleich)

```sql
-- Retrieve active users registered after a specific ID
SELECT email FROM users WHERE is_active = true AND id > 100;
```

### Erweiterte Filteroperatoren
* **`BETWEEN`**: Filtert Werte innerhalb eines bestimmten Bereichs (einschließlich der Grenzwerte).
* **`IN`**: Prüft, ob ein Wert mit einem Wert in einer angegebenen Liste übereinstimmt.
* **`LIKE`**: Führt einen einfachen Musterabgleich mit Wildcards durch:
  - `%` steht für null oder mehr Zeichen.
  - `_` steht für genau ein Zeichen.

```sql
-- Retrieve users with IDs 1, 3, or 5
SELECT email FROM users WHERE id IN (1, 3, 5);

-- Retrieve users registered in a specific range
SELECT email FROM users WHERE id BETWEEN 10 AND 50;

-- Retrieve users whose email starts with 'admin'
SELECT email FROM users WHERE email LIKE 'admin%';
```

---

## 4. Wichtige Unterschiede: MySQL vs. PostgreSQL

Obwohl beide Engines dem SQL-Standard folgen, weichen sie in Syntax und Standardverhalten voneinander ab.

### Bezeichner in Anführungszeichen setzen (Quoting)
Bezeichner (Tabellennamen, Spaltennamen) müssen in Anführungszeichen gesetzt werden, wenn sie mit reservierten SQL-Schlüsselwörtern kollidieren oder Sonderzeichen/Leerzeichen enthalten.
* **MySQL**: Verwendet Backticks (`` ` ``).
* **PostgreSQL**: Verwendet doppelte Anführungszeichen (`"`).

```sql
-- MySQL
SELECT `select`, `group` FROM `my_table`;

-- PostgreSQL
SELECT "select", "group" FROM "my_table";
```

### Groß-/Kleinschreibung beim Musterabgleich (Case Sensitivity)
* **PostgreSQL** unterscheidet bei `LIKE` strikt zwischen Groß- und Kleinschreibung. Um eine Suche durchzuführen, die Groß- und Kleinschreibung ignoriert, müssen Sie den PostgreSQL-spezifischen Operator `ILIKE` verwenden.
* Das `LIKE` von **MySQL** ist bei Standard-Kollationen (z. B. `utf8mb4_0900_ai_ci`) standardmäßig unempfindlich gegenüber Groß-/Kleinschreibung. Um eine case-sensitive Suche zu erzwingen, müssen Sie die Zeichenkette in ein Binärformat umwandeln (casten) oder eine binäre Kollation verwenden.

```sql
-- Case-insensitive search for 'john'
-- PostgreSQL
SELECT email FROM users WHERE email ILIKE 'john%';

-- MySQL
SELECT email FROM users WHERE email LIKE 'john%';
```

### String-Verkettung (Concatenation)
* **PostgreSQL** verwendet den standardmäßigen SQL-Doppelrohr-Operator (`||`).
* **MySQL** unterstützt `||` standardmäßig nicht zur Verkettung (es behandelt `||` als logisches `OR`, es sei denn, der SQL-Modus `PIPES_AS_CONCAT` ist aktiviert). Stattdessen verwendet MySQL die Funktion `CONCAT()`.

```sql
-- PostgreSQL
SELECT first_name || ' ' || last_name AS full_name FROM users;

-- MySQL
SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users;
```

### Boolesche Datentypen
* **PostgreSQL** besitzt einen nativen `BOOLEAN`-Typ, der die Literale `true` und `false` unterstützt.
* **MySQL** hat keinen echten booleschen Typ; es weist `BOOLEAN` als Alias für `TINYINT(1)` zu, wobei `0` für falsch (false) und `1` für wahr (true) steht.

```sql
-- PostgreSQL: Returns true/false
SELECT is_active FROM users;

-- MySQL: Returns 1/0
SELECT is_active FROM users;
```

> [!WARNING]
> **Anführungszeichen-Falle!**
> Verwenden Sie in PostgreSQL niemals doppelte Anführungszeichen (`"`) für String-Literale (Textwerte). PostgreSQL behandelt doppelte Anführungszeichen als Bezeichner-Anführungszeichen (für Spalten- oder Tabellennamen), was zu einem Syntaxfehler `column "value" does not exist` führt. Verwenden Sie sowohl in MySQL als auch in PostgreSQL immer einfache Anführungszeichen (`'`) für String-Literale.

---

## 5. Zusammenfassung & Best Practices

1. **Präzise sein**: Listen Sie Spaltennamen explizit auf, anstatt `SELECT *` zu verwenden, um die Leistung und die Langlebigkeit des Codes zu verbessern.
2. **Anführungszeichen vereinheitlichen**: Verwenden Sie einfache Anführungszeichen (`'`) für String-Literale über alle Datenbank-Engines hinweg.
3. **Operatoren kennen**: Verwenden Sie `ILIKE` in PostgreSQL für Suchen, die Groß- und Kleinschreibung ignoriert, und denken Sie daran, dass die Verkettung mit `||` in Postgres Standard ist, in MySQL jedoch `CONCAT()` erfordert.
4. **Boolesche Prüfungen**: Denken Sie daran, dass MySQL Booleans als `1` or `0` speichert, was Auswirkungen darauf haben kann, wie Ihr Anwendungscode Abfrageergebnisse interpretiert.
