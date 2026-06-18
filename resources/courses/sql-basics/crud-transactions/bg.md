# CRUD операции и транзакции

Извличането на данни е само половината от битката. За да изграждате динамични приложения, трябва да записвате, променяте и изтривате записи (операции, общо известни като CRUD: Create, Read, Update, Delete). Освен това, когато изпълнявате множество свързани промени в базата данни (като прехвърляне на пари между две сметки), трябва да гарантирате, че или всички операции ще завършат успешно, или нито една от тях. Тук на помощ идват **Транзакциите**.

В тази последна глава ще се научим да променяме данни, да внедряваме транзакции и да разберем ключовите разлики в начина, по който PostgreSQL и MySQL управляват транзакционните състояния и конфликтите при запис.

---

## 1. Промяна на данни: INSERT, UPDATE и DELETE

### Вмъкване на записи (Inserting Records)
Можете да вмъкнете един ред или да извършите групово вмъкване (bulk insert) в една заявка.
```sql
-- MySQL & PostgreSQL
-- Single insert
INSERT INTO users (email, name) VALUES ('bob@devsense.work', 'Bob');

-- Bulk insert (recommended for performance)
INSERT INTO users (email, name) 
VALUES 
    ('alice@devsense.work', 'Alice'),
    ('charlie@devsense.work', 'Charlie');
```

### Актуализиране на записи (Updating Records)
Променя съществуващи записи. Винаги се уверявайте, че филтрирате актуализацията си с помощта на клауза `WHERE`.
```sql
-- MySQL & PostgreSQL
UPDATE users SET is_active = true WHERE id = 42;
```

### Изтриване на записи (Deleting Records)
Премахва записи за постоянно. Подобно на `UPDATE`, изисква клауза `WHERE`, за да се избегне катастрофална загуба на данни.
```sql
-- MySQL & PostgreSQL
DELETE FROM users WHERE is_active = false;
```

> [!WARNING]
> **Капанът с липсващата клауза `WHERE`!**
> Ако изпълните `UPDATE users SET is_active = true;` или `DELETE FROM users;` без клауза `WHERE`, сървърът ще промени или изтрие **всеки един ред** във вашата таблица. Винаги проверявайте два пъти заявката си преди изпълнение!

---

## 2. Транзакции: Защита на целостта на данните

Транзакцията е поредица от SQL оператори, изпълнявани като единна, неделима единица работа. Транзакциите се придържат към стандартите **ACID**:
* **Atomicity (Атомарност)**: Всички оператори завършват успешно или цялата транзакция се връща в първоначално състояние (всичко или нищо).
* **Consistency (Съгласуваност)**: Гарантира, че базата данни преминава от едно валидно състояние в друго, спазвайки всички ограничения.
* **Isolation (Изолираност)**: Транзакциите, изпълнявани паралелно, не си влияят една на друга.
* **Durability (Трайност)**: След потвърждаване, промените се записват за постоянно на диска и оцеляват при системни сривове.

### Транзакционни команди
* **`BEGIN` / `START TRANSACTION`**: Стартира транзакционния блок.
* **`COMMIT`**: Записва за постоянно всички промени, направени по време на транзакцията.
* **`ROLLBACK`**: Отхвърля всички промени, направени по време на транзакцията, възстановявайки базата данни в състоянието ѝ отпреди транзакцията.

```sql
-- MySQL & PostgreSQL
BEGIN; -- Start transaction

UPDATE accounts SET balance = balance - 100 WHERE id = 1;
UPDATE accounts SET balance = balance + 100 WHERE id = 2;

-- If everything is fine:
COMMIT;

-- If a query failed or we changed our mind:
-- ROLLBACK;
```

---

## 3. Ключови разлики: MySQL срещу PostgreSQL

### Синтаксис за Upsert (Вмъкване или актуализиране при конфликт на ключ)
Операцията „upsert“ вмъква ред, ако той не съществува, или го актуализира, ако е в конфликт със съществуващ уникален индекс или първичен ключ.
* **MySQL**: Използва клаузата `ON DUPLICATE KEY UPDATE`.
* **PostgreSQL**: Използва клаузата `ON CONFLICT (conflict_column) DO UPDATE`.

```sql
-- MySQL (ON DUPLICATE KEY UPDATE)
INSERT INTO users (id, email, visits) 
VALUES (1, 'user@devsense.work', 1)
ON DUPLICATE KEY UPDATE visits = visits + 1;

-- PostgreSQL (ON CONFLICT DO UPDATE)
INSERT INTO users (id, email, visits) 
VALUES (1, 'user@devsense.work', 1)
ON CONFLICT (id) DO UPDATE SET visits = users.visits + 1;
```

### Транзакционен DDL (Data Definition Language)
Това е една от най-важните структурни разлики между двете бази данни.
* **PostgreSQL** поддържа напълно транзакционен DDL. Можете да изпълнявате команди като `CREATE TABLE`, `DROP TABLE` или `ALTER TABLE` вътре в транзакция и безопасно да ги върнете (roll back), ако нещо се обърка.
* **MySQL** НЕ поддържа транзакционен DDL. Ако изпълните DDL оператор вътре в транзакция в MySQL, това задейства **неявно потвърждаване (implicit commit)** (известно също като автоматично потвърждаване). MySQL незабавно потвърждава транзакцията до тази точка, изпълнява DDL оператора и не можете да върнете предходните промени.

```sql
-- PostgreSQL (This works and will be fully rolled back!)
BEGIN;
DROP TABLE users;
ROLLBACK; -- Table 'users' is safely restored!

-- MySQL (This will fail to roll back!)
START TRANSACTION;
INSERT INTO logs (message) VALUES ('Deleting users table');
DROP TABLE users; -- Triggers implicit commit!
ROLLBACK; -- Does nothing; the insert and DROP are already committed!
```

### Синтаксис за стартиране на транзакция
* **PostgreSQL**: Предпочита стандартната команда `BEGIN` (въпреки че приема и `START TRANSACTION`).
* **MySQL**: Предпочита `START TRANSACTION` (въпреки че приема `BEGIN` в повечето клиентски контексти; в съхранени процедури обаче `BEGIN` е запазена дума за блокови структури, затова там се изисква `START TRANSACTION`).

 > [!TIP]
> **Знаехте ли, че?**
> PostgreSQL поддържа клаузата `RETURNING` за операции по записване. Това ви позволява незабавно да получите генерирания първичен ключ или изчислени колони, без да изпълнявате отделна заявка `SELECT`.
> `INSERT INTO users (email) VALUES ('new@devsense.work') RETURNING id, created_at;`
> *MySQL не поддържа `RETURNING` и изисква клиентските библиотеки да извикват функции като `LAST_INSERT_ID()`, за да получат генерирания ключ.*

---

## 4. Резюме и добри практики

1. **Поддържайте транзакциите кратки**: Дълго изпълняваните транзакции задържат заключвания (locks) върху редове в базата данни, блокирайки други потребители и увеличавайки вероятността от блокировки (deadlocks).
2. **Внимавайте с неявните потвърждавания (implicit commits) в MySQL**: Не смесвайте промени в схемата (DDL като `ALTER TABLE`) с промени в данните (DML като `UPDATE`) в рамките на транзакции, ако планирате да разчитате на връщане на промените (rollbacks).
3. **Използвайте Upserts внимателно**: Съобразете правилния синтаксис за вашата база данни (`ON CONFLICT` за PostgreSQL и `ON DUPLICATE KEY UPDATE` за MySQL), за да се справите грациозно с нарушенията на ограниченията за дублиране на ключове.
4. **Винаги указвайте WHERE**: Защитете се от случайно изтриване или промяна на цели таблици, като се уверявате, че заявките `UPDATE` и `DELETE` съдържат строги клаузи `WHERE`.
