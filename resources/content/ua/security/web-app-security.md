---
title: "Вразливості вебдодатків та їх усунення: SQLi, ін'єкція команд, XSS, CSRF та IDOR | DevSense"
description: "Захистіть свої PHP-додатки від поширених вразливостей вебдодатків. Дізнайтеся, як запобігти SQL-ін'єкціям, ін'єкціям команд, XSS, CSRF та IDOR за допомогою прикладів безпечного коду."
published: 2026-06-19
faq:
  - question: "Чому підготовлені запити (prepared statements) захищають від SQL-ін'єкцій?"
    answer: "Підготовлені запити надсилають шаблон SQL-запиту та дані параметрів до рушія бази даних окремо. Рушій спочатку компілює SQL-запит, забезпечуючи, що дані параметрів ніколи не аналізуються і не виконуються як SQL-команди, незалежно від того, які символи вони містять."
  - question: "Коли безпечно використовувати Blade-директиву Laravel {!! $var !!} для виведення неекранованих даних?"
    answer: "Використовувати `{!! $var !!}` безпечно лише тоді, коли змінна містить чистий HTML, який ви згенерували самі або очистили за допомогою надійної бібліотеки очищення HTML, як-от HTMLPurifier. Ніколи не виводьте необроблені дані користувача за допомогою цієї директиви."
  - question: "Як пошук з обмеженням за зв'язком (relation-scoped lookup) запобігає IDOR?"
    answer: "Пошук з обмеженням за зв'язком (наприклад, `$user->orders()->findOrFail($id)`) гарантує, що запит до бази даних природним чином фільтрує результати за ідентифікатором автентифікованого користувача. Зловмисник, який намагається отримати доступ до ідентифікатора іншого користувача, отримає помилку 404 Not Found, оскільки запис не існує в межах цього обмеженого зв'язку."
---

# Вразливості вебдодатків та їх усунення: SQLi, ін'єкція команд, XSS, CSRF та IDOR

Створення безпечних вебдодатків полягає не в додаванні безпекових рішень наприкінці розробки. Воно вимагає розуміння того, як вразливості виникають на рівні коду, та проєктування меж для їхнього запобігання. 

У цьому посібнику ми проаналізуємо п'ять критичних вразливостей вебдодатків (SQL-ін'єкція, ін'єкція команд, міжсайтовий скриптинг (XSS), CSRF та IDOR) у PHP та Laravel, розглянемо, як їх експлуатують, та впровадимо надійні методи їхнього усунення.

**Супутні посібники:** [Архітектура від моноліту до мікросервісів](monolith-to-microservices-architecture) · [Спостережуваність та моніторинг](observability-monitoring-laravel)

## Зміст

* [SQL-ін'єкція (SQLi)](#sql-injection)
* [Ін'єкція команд](#command-injection)
* [Міжсайтовий скриптинг (XSS)](#xss)
* [Підробка міжсайтових запитів (CSRF)](#csrf)
* [Небезпечне пряме посилання на об'єкт (IDOR)](#idor)
* [Типові помилки](#common-mistakes)
* [Контрольний список](#checklist)
* [Підсумок](#summary)
* [Тест для самоперевірки](#self-test-quiz)

---

<a id="sql-injection"></a>
## SQL-ін'єкція (SQLi)

**SQL-ін'єкція (SQL Injection)** виникає, коли ненадійне введення користувача конкатенується безпосередньо в рядки SQL-запитів. Це дозволяє зловмисникам маніпулювати структурою запиту, обходити автентифікацію, читати конфіденційний вміст бази даних або видаляти записи.

### Неправильний шлях: конкатенація рядків та небезпечне сортування

Тут розробник конкатенує змінні введення безпосередньо в запит і використовує неочищене введення для визначення стовпця сортування.

```php
// app/Http/Controllers/ProductController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController
{
    public function search(Request $request): array
    {
        $search = $request->input('q');
        $sortBy = $request->input('sort', 'id');

        // VULNERABLE: SQL Injection via both search input and sorting column
        $sql = "SELECT * FROM products WHERE name LIKE '%{$search}%' ORDER BY {$sortBy} ASC";
        return DB::select($sql);
    }
}
```

### Правильний шлях: підготовлені запити та білий список стовпців

Щоб виправити це, ми повинні використовувати **підготовлені запити (зв'язування параметрів)** для значень даних запиту. Оскільки стовпці сортування не можуть бути параметризовані, ми повинні валідувати їх за суворим **білим списком (allowlist)**.

```php
// app/Http/Controllers/ProductController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController
{
    private const ALLOWED_SORT_COLUMNS = ['id', 'name', 'price', 'created_at'];

    public function search(Request $request): array
    {
        $search = $request->input('q', '');
        
        // Strict sorting column allowlist fallback
        $sortBy = in_array($request->input('sort'), self::ALLOWED_SORT_COLUMNS, true) 
            ? $request->input('sort') 
            : 'id';

        // SECURE: Parameter binding for data, strict validation for identifiers
        return DB::select(
            "SELECT * FROM products WHERE name LIKE :search ORDER BY {$sortBy} ASC",
            ['search' => "%{$search}%"]
        );
    }
}
```

---

<a id="command-injection"></a>
## Ін'єкція команд

**Ін'єкція команд (Command Injection)** виникає, коли введення користувача передається безпосередньо у функції системної оболонки (shell), такі як `exec()`, `shell_exec()` або `system()`. Це дозволяє зловмисникам виконувати довільні команди оболонки на сервері з правами користувача вебсервера.

### Неправильний шлях: виконання команди в оболонці через конкатенацію рядків

У цьому прикладі ми намагаємося перетворити PDF-файл на мініатюру PNG за допомогою утиліти командного рядка, але передаємо ім'я файлу безпосередньо в оболонку.

```php
// app/Services/ThumbnailGenerator.php
declare(strict_types=1);

namespace App\Services;

class ThumbnailGenerator
{
    public function generate(string $filename): string
    {
        $outputPath = "/tmp/" . uniqid('thumb_', true) . ".png";
        
        // VULNERABLE: Command injection if filename contains shell operators (e.g. "; rm -rf /;")
        $cmd = "pdftoppm -png -r 150 {$filename} {$outputPath}";
        shell_exec($cmd);

        return $outputPath;
    }
}
```

### Правильний шлях: компонент Process із масивами аргументів

Ніколи не викликайте оболонку безпосередньо та не передавайте конкатеновані аргументи. Використовуйте бібліотеки-обгортки процесів (наприклад, Symfony Process), які безпечно обробляють екранування аргументів та виконання без використання середовища оболонки.

```php
// app/Services/ThumbnailGenerator.php
declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ThumbnailGenerator
{
    public function generate(string $filePath): string
    {
        $outputPath = "/tmp/" . uniqid('thumb_', true) . ".png";
        
        // SECURE: Arguments are passed as an array, bypassing the shell shell interpreter
        $process = new Process(['pdftoppm', '-png', '-r', '150', $filePath, $outputPath]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $outputPath;
    }
}
```

---

<a id="xss"></a>
## Міжсайтовий скриптинг (XSS)

**Міжсайтовий скриптинг (Cross-Site Scripting, XSS)** виникає, коли додаток включає ненадійні дані користувача у вебсторінку без належного екранування. Браузер зловмисника виконує шкідливий JavaScript, який може викрасти сесійні файли cookie, перехоплювати введення (клавіатурне шпигунство) або перенаправляти користувачів.

Існує три типи XSS:
- **Відбитий XSS (Reflected XSS):** Скрипт є частиною корисного навантаження запиту та негайно відображається у відповіді.
- **Накопичений XSS (Stored XSS):** Скрипт зберігається в базі даних (наприклад, текст коментаря) і виконується, коли інші користувачі переглядають сторінку.
- **XSS на основі DOM (DOM-based XSS):** Вразливість існує виключно в клієнтському JavaScript, який обробляє ненадійні змінні DOM.

### Неправильний шлях: виведення неекранованого вмісту користувача

Конструкція `{!! $var !!}` у Laravel виводить необроблений HTML, обходячи стандартне екранування XSS у Blade.

```blade
<!-- resources/views/profile.blade.php -->
<div class="user-bio">
    <!-- VULNERABLE: Stored XSS if bio contains <script>alert('xss')</script> -->
    {!! $user->bio !!}
</div>
```

### Правильний шлях: екранування за замовчуванням та очищення форматизованого тексту (Rich Text)

Завжди використовуйте `{{ $var }}`, яка автоматично запускає `e()` (`htmlspecialchars` у PHP) для екранування виведення. Якщо вам потрібно вивести форматизований текст, наданий користувачем, пропустіть його через надійну бібліотеку очищення HTML (наприклад, HTMLPurifier).

```blade
<!-- resources/views/profile.blade.php -->
<div class="user-bio">
    <!-- SECURE: Automatically escaped by Blade -->
    {{ $user->bio }}
</div>

<div class="user-rich-content">
    <!-- SECURE: Outputting raw html only AFTER strict HTML purification -->
    {!! clean($user->rich_description) !!}
</div>
```

---

<a id="csrf"></a>
## Підробка міжсайтових запитів (CSRF)

**Підробка міжсайтових запитів (Cross-Site Request Forgery, CSRF)** змушує браузер автентифікованого користувача виконувати дії, що змінюють стан (наприклад, зміна пароля або здійснення платежів), у додатку, в який користувач наразі увійшов. Браузер автоматично додає сесійні файли cookie до міжсайтових запитів, що підтверджує легітимність запиту.

### Неправильний шлях: дії, що змінюють стан, у GET-запитах

GET-запити завжди мають бути **ідемпотентними** (безпечними для багаторазового виконання без зміни стану). Виконання оновлень на GET-маршрутах робить CSRF-атаку тривіальною.

```php
// routes/web.php
// VULNERABLE: Anyone can link to /profile/delete in an img tag to delete an account
Route::get('/profile/delete', [ProfileController::class, 'destroy']);
```

### Правильний шлях: маршрути POST/DELETE з токенами CSRF

Завжди використовуйте HTTP-методи для зміни стану (POST, PUT, DELETE) і вимагайте секретний, криптографічно стійкий токен, який не може бути вгаданий сторонніми вебсайтами.

```blade
<!-- resources/views/profile.blade.php -->
<!-- SECURE: Form triggers a POST request and generates a hidden CSRF token -->
<form action="{{ route('profile.destroy') }}" method="POST">
    @csrf
    @method('DELETE')
    <button type="submit">Delete Account</button>
</form>
```

---

<a id="idor"></a>
## Небезпечне пряме посилання на об'єкт (IDOR)

**IDOR (Insecure Direct Object Reference)** виникає, коли додаток надає користувачам безпосередній доступ до ключів бази даних або ідентифікаторів ресурсів, але не перевіряє, чи має користувач, який робить запит, дозвіл на доступ до цього конкретного ресурсу.

### Неправильний шлях: глобальний пошук запиту без авторизації

У цьому прикладі будь-який автентифікований користувач може отримати доступ до деталей рахунку іншого користувача, просто змінивши `{id}` у параметрі URL.

```php
// app/Http/Controllers/InvoiceController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController
{
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        // VULNERABLE: Retrieves invoice without checking user relationship or authorization
        $invoice = Invoice::findOrFail($id);
        
        return response()->json($invoice);
    }
}
```

### Правильний шлях: запити, обмежені зв'язками, та авторизація через Gate

Відокремте отримання ресурсів, завантажуючи їх через область дії зв'язаної моделі користувача (relationship scope), або перевіряйте дозволи за допомогою механізмів авторизації Gates/Policies у Laravel.

```php
// app/Http/Controllers/InvoiceController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvoiceController
{
    public function show(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        // SECURE Method A: Query scoped directly to the authenticated user
        $invoice = $request->user()->invoices()->findOrFail($id);

        // SECURE Method B: Global lookup but validated with a Laravel Policy
        // $invoice = Invoice::findOrFail($id);
        // Gate::authorize('view', $invoice);
        
        return response()->json($invoice);
    }
}
```

---

## Типові помилки

1. **Екранування замість параметризації:** Думка про те, що використання `addslashes()` або простих регулярних виразів для змінних захищає запити. Завжди параметризуйте.
2. **Зміна стану через GET-запити:** Створення API чи вебендпоінтів, які виконують дії видалення або оновлення за допомогою методу HTTP GET.
3. **Універсальні шлюзи для валідації:** Перекладання обов'язків валідації XSS/SQLi на брандмауери вебдодатків (WAF) або мережеві шлюзи замість написання надійної валідації безпосередньо в контролері додатка.
4. **IDOR в AJAX-запитах:** Захист основних маршрутів HTML-сторінок, але відкриття неавторизованого доступу до ідентифікаторів ресурсів в AJAX/API JSON-ендпоінтах.

---

## Контрольний список

1. **Безпека запитів:** Чи конкатенуєте ви змінні всередині необроблених виразів `DB::select` або `whereRaw`?
2. **Виконання в оболонці (Shell):** Чи можете ви замінити команди оболонки стандартними бібліотеками PHP? Якщо ні, чи передаються аргументи у вигляді масиву?
3. **Виведення в Blade:** Чи використовуєте ви конструкцію `{!! !!}` де-небудь у Blade-шаблонах без очищення через HTMLPurifier?
4. **Перевірка авторизації:** Чи перевіряє кожен метод контролера, що завантажує ресурс, чи належить цей ресурс поточному користувачу?

---

## Підсумок

Безпечні вебдодатки суворо валідують введення та застосовують багаторівневий захист. Запобігайте **SQL-ін'єкціям**, використовуючи підготовлені запити та білі списки стовпців. Уникайте **ін'єкцій команд**, використовуючи масиви аргументів всередині обгорток процесів. Блокуйте **XSS**, екрануючи виведення змінних за допомогою шаблонізаторів. Зупиняйте **CSRF**, обмежуючи зміну стану запитами POST/DELETE, захищеними токенами. Долайте **IDOR**, обмежуючи пошук зв'язками автентифікованого користувача або виконуючи перевірки політик безпеки.

---

<a id="self-test-quiz"></a>
## Тест для самоперевірки

### Запитання 1: Яка основна різниця між екрануванням та параметризацією для SQL-запитів?
- A) Екранування виконується на сервері бази даних, тоді як параметризація обробляється всередині рушія PHP.
- B) Екранування змінює спеціальні символи всередині рядків запиту, щоб зробити їх безпечними, тоді як параметризація надсилає структуру запиту та параметри окремо до рушія бази даних.
- C) Параметризація сумісна лише з базами даних PostgreSQL.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: B**
Екранування — це процес маніпуляції рядками, який схильний до помилок аналізатора (parser bugs) та обходу захисту. Параметризація — це функція протоколу, за якої структура SQL та значення даних надсилаються незалежно, що гарантує неможливість зміни шаблону запиту під впливом даних.
</details>

### Запитання 2: Чому GET-запити вразливі до CSRF, навіть якщо вони захищені токенами?
- A) Браузери не підтримують форми з методом GET.
- B) GET-запити розкривають токени в історії URL-адрес, журналах браузера та заголовках Referer, і в будь-якому разі ніколи не повинні використовуватися для операцій, що змінюють стан.
- C) GET-запити автоматично обходять посередник (middleware) перевірки токенів CSRF у Laravel.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: B**
GET-запити призначені для ідемпотентності (безпечного читання). Якщо GET-запит змінює стан, зловмисники можуть вбудувати цільову URL-адресу в міжсайтові посилання або теги зображень, автоматично ініціюючи зміну стану без згоди користувача.
</details>

### Запитання 3: Як обмеження запиту до бази даних зв'язком користувача запобігає IDOR?
- A) Це шифрує первинні ключі бази даних.
- B) Це блокує запит на рівні брандмауера.
- C) Це гарантує, що SQL-запит природним чином фільтрує записи, використовуючи ідентифікатор автентифікованого користувача як обмеження пошуку, що робить записи інших користувачів недосяжними.

<details>
<summary>Натисніть, щоб переглянути відповідь</summary>

**Відповідь: C**
Обмеження запитів (наприклад, `$user->invoices()->findOrFail($id)`) додає умову `WHERE user_id = ?` до SQL-запиту. Якщо зловмисник спробує отримати ідентифікатор ресурсу, який належить комусь іншому, запит поверне нуль записів, що призведе до безпечної помилки 404.
</details>
