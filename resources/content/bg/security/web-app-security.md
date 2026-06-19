---
title: "Уязвимости на уеб приложения и предотвратяване: SQLi, командна инжекция, XSS, CSRF и IDOR | DevSense"
description: "Защитете вашите PHP приложения от често срещани уеб уязвимости. Научете как да предотвратите SQL инжекция, командна инжекция, XSS, CSRF и IDOR с примери за сигурен код."
published: 2026-06-19
faq:
  - question: "Защо подготвените заявки (prepared statements) са защитени срещу SQL инжекция?"
    answer: "Подготвените заявки изпращат шаблона на SQL заявката и данните за параметрите отделно към базата данни. Системата за управление на бази данни (СУБД) първо компилира SQL заявката, което гарантира, че данните за параметрите никога няма да бъдат парснати или изпълнени като SQL команди, независимо какви знаци съдържат."
  - question: "Кога е безопасно да се използва неекранираната Blade директива на Laravel {!! $var !!}?"
    answer: "Безопасно е да използвате `{!! $var !!}` само когато променливата съдържа чист HTML, който сте генерирали самите вие или сте пречистили (purified) с библиотека за сигурно пречистване на HTML като HTMLPurifier. Никога не трябва да отпечатвате суров потребителски вход с тази директива."
  - question: "Как търсенето, ограничено до релация (relation-scoped lookup), предотвратява IDOR?"
    answer: "Търсенето, ограничено до релация (напр. `$user->orders()->findOrFail($id)`), гарантира, че заявката към базата данни естествено филтрира резултатите по ID на удостоверения потребител. Нападател, който се опитва да достъпи ID на друг потребител, ще получи грешка 404 Not Found, тъй като записът не съществува в рамките на ограничената релация."
---

# Уязвимости на уеб приложения и предотвратяване: SQLi, командна инжекция, XSS, CSRF и IDOR

Създаването на сигурни уеб приложения не се състои в добавяне на сигурност в края на разработката. То изисква разбиране на това как възникват уязвимостите на кодово ниво и проектиране на граници за тяхното предотвратяване.

В това ръководство ще анализираме пет критични уязвимости в уеб приложенията (SQL инжекция, командна инжекция, междусайтов скриптинг, CSRF и IDOR) в PHP и Laravel, ще разгледаме как се експлоатират те и ще внедрим надеждни методи за тяхното предотвратяване.

**Свързани ръководства:** [Архитектура от монолит към микроуслуги](monolith-to-microservices-architecture) · [Наблюдаемост и мониторинг](observability-monitoring-laravel)

## Съдържание

* [SQL инжекция (SQLi)](#sql-injection)
* [Командна инжекция](#command-injection)
* [Междусайтов скриптинг (XSS)](#xss)
* [Подправяне на междусайтови заявки (CSRF)](#csrf)
* [Несигурно директно рефериране на обекти (IDOR)](#idor)
* [Често срещани грешки](#common-mistakes)
* [Контролен списък](#checklist)
* [Резюме](#summary)
* [Тест за самопроверка](#self-test-quiz)

---

<a id="sql-injection"></a>
## SQL инжекция (SQLi)

**SQL инжекцията** възниква, когато ненадежден потребителски вход се конкатенира директно в низовете на SQL заявките. Това позволява на нападателите да манипулират структурата на заявката, да заобикалят удостоверяването, да четат чувствително съдържание от базата данни или да изтриват записи.

### Лошият начин: Конкатенация на низове и небезопасно сортиране

Тук разработчикът конкатенира входните променливи директно в заявката и използва непречистен вход за дефиниране на колоната за сортиране.

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

### Добрият начин: Подготвени заявки и списък с разрешени колони (Allowlist)

За да коригираме това, трябва да използваме **подготвени заявки (обвързване на параметри)** за стойностите на данните в заявката. Тъй като колоните за сортиране не могат да бъдат параметризирани, трябва да ги валидираме спрямо строг **списък с разрешени стойности (allowlist)**.

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
## Командна инжекция

**Командната инжекция** възниква, когато потребителският вход се подава директно към системни обвиващи (shell) функции като `exec()`, `shell_exec()` или `system()`. Това позволява на нападателите да изпълняват произволни shell команди на сървъра с правата на потребителя на уеб сървъра.

### Лошият начин: Shell Exec с конкатенация на низове

В този пример се опитваме да конвертираме PDF файл в PNG миниатюра с помощта на инструмент от командния ред, но предаваме името на файла директно към конзолата (shell).

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

### Добрият начин: Компонентът Process с масиви от аргументи

Никога не извиквайте shell-а директно и не предавайте конкатенирани аргументи. Използвайте обвиващи библиотеки за процеси (като Symfony Process), които управляват екранирането на аргументите и изпълнението им по безопасен начин, без да използват shell среда.

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
## Междусайтов скриптинг (XSS)

**Междусайтовият скриптинг (XSS)** възниква, когато приложението включва ненадеждни потребителски данни в уеб страница без подходящо екраниране. Браузърът на нападателя изпълнява зловреден JavaScript, който може да открадне бисквитки на сесията, да прихване въведени данни (keylogging) или да пренасочи потребителите.

Съществуват три вида XSS:
- **Отразен XSS (Reflected XSS):** Скриптът е част от полезния товар на заявката и се отразява обратно веднага в отговора.
- **Съхранен XSS (Stored XSS):** Скриптът се записва в базата данни (напр. текст на коментар) и се изпълнява, когато други потребители заредят страницата.
- **DOM-базиран XSS (DOM-based XSS):** Уязвимостта съществува изцяло в JavaScript от страната на клиента, който изпълнява ненадеждни DOM променливи.

### Лошият начин: Отпечатване на неекранирано потребителско съдържание

Конструкцията `{!! $var !!}` в Laravel извежда суров HTML, като заобикаля стандартното XSS екраниране на Blade.

```blade
<!-- resources/views/profile.blade.php -->
<div class="user-bio">
    <!-- VULNERABLE: Stored XSS if bio contains <script>alert('xss')</script> -->
    {!! $user->bio !!}
</div>
```

### Добрият начин: Екраниране по подразбиране и пречистване на форматиран текст

Винаги използвайте `{{ $var }}`, което автоматично изпълнява `e()` (`htmlspecialchars` в PHP) за екраниране на изхода. Ако трябва да изведете форматиран текст (rich text), предоставен от потребителя, го прекарайте през библиотека за сигурно пречистване на HTML (като HTMLPurifier).

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
## Подправяне на междусайтови заявки (CSRF)

**Подправянето на междусайтови заявки (CSRF)** принуждава браузъра на удостоверен потребител да изпълни действия, променящи състоянието (като промяна на парола или иницииране на плащания), в приложение, в което потребителят е влязъл в момента. Браузърът автоматично прикачва сесийните бисквитки към заявки от външни сайтове, което валидира заявката пред приложението.

### Лошият начин: Действия, променящи състоянието, при GET заявки

GET заявките винаги трябва да бъдат **идемпотентни** (безопасни за изпълнение многократно, без да променят състоянието). Извършването на промени в GET маршрути прави CSRF атаките изключително лесни.

```php
// routes/web.php
// VULNERABLE: Anyone can link to /profile/delete in an img tag to delete an account
Route::get('/profile/delete', [ProfileController::class, 'destroy']);
```

### Добрият начин: POST/DELETE маршрути с CSRF токени

Винаги използвайте HTTP методи, които променят състоянието (POST, PUT, DELETE), и изисквайте таен, криптографски защитен токен, който не може да бъде отгатнат от външни уебсайтове.

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
## Несигурно директно рефериране на обекти (IDOR)

**IDOR (Несигурно директно рефериране на обекти)** възниква, когато дадено приложение излага ключ от базата данни или ID на ресурс директно на потребителите и не проверява дали отправящият заявката потребител има разрешение за достъп до този конкретен ресурс.

### Лошият начин: Глобално търсене на заявка без оторизация

В този пример всеки влязъл в системата потребител може да получи достъп до детайлите на фактурата на друг потребител, просто като промени `{id}` в URL параметъра.

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

### Добрият начин: Заявки, ограничени до релация, и оторизация с Gate

Развържете извличането на ресурси, като ги зареждате през областта (scope) на релацията на потребителския модел, или проверявайте правата чрез оторизационни политики (Gates/Policies).

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

<a id="common-mistakes"></a>
## Често срещани грешки

1. **Екраниране вместо параметризация:** Схващането, че използването на `addslashes()` или прости регулярни изрази върху променливите защитава заявките. Винаги параметризирайте.
2. **Промени на състоянието чрез GET:** Създаване на API или уеб крайни точки, които извършват действия по изтриване или актуализиране с помощта на HTTP GET.
3. **Мега-шлюзове (Gateways) за валидация:** Оставяне на валидацията за XSS/SQLi на WAF (Web Application Firewall) или шлюзове, вместо кодиране на надеждна валидация директно в контролера на приложението.
4. **IDOR при AJAX заявки:** Защита на основните маршрути за HTML страници, но излагане на неоторизирани ID на ресурси в AJAX/API JSON крайни точки.

---

<a id="checklist"></a>
## Контролен списък

1. **Сигурност на заявките:** Конкатенирате ли променливи в сурови конструкции от типа `DB::select` или `whereRaw`?
2. **Изпълнение на shell команди:** Можете ли да замените shell командите със стандартни PHP библиотеки? Ако не, предават ли се аргументите в масив?
3. **Изход през Blade:** Използвате ли `{!! !!}` някъде в Blade шаблоните без валидация с HTMLPurifier?
4. **Проверка за оторизация:** Дали всеки метод в контролерите, който зарежда ресурс, проверява дали този ресурс принадлежи на текущия потребител?

---

<a id="summary"></a>
## Резюме

Сигурните уеб приложения валидират входа стриктно и прилагат многослойна защита (defense-in-depth). Предотвратявайте **SQL инжекциите** с помощта на подготвени заявки и списъци с разрешени колони (allowlists). Избягвайте **командните инжекции**, като използвате масиви от аргументи в обвивките на процеси. Блокирайте **XSS** чрез екраниране на изходните променливи посредством шаблонизиращи системи. Спрете **CSRF**, като ограничите промените на състоянието до POST/DELETE заявки, защитени с токени. Справете се с **IDOR**, като ограничите търсенето до релациите на удостоверения потребител или като извършвате проверки на политики за достъп.

---

<a id="self-test-quiz"></a>
## Тест за самопроверка

### Въпрос 1: Каква е основната разлика между екранирането и параметризацията при SQL заявки?
- A) Екранирането се изпълнява на сървъра на базата данни, докато параметризацията се обработва в PHP енджина.
- B) Екранирането променя специалните знаци в низовете на заявките, за да ги направи безопасни, докато параметризацията изпраща структурата на заявката и параметрите отделно към ядрото на базата данни.
- C) Параметризацията е съвместима само с бази данни PostgreSQL.

<details>
<summary>Кликнете, за да видите отговора</summary>

**Отговор: B**
Екранирането е процес на манипулиране на низове, който е податлив на грешки в парсера и заобикаляне на защитата. Параметризацията е функция на протокола, при която структурата на SQL заявката и стойностите на данните се изпращат независимо, което гарантира, че данните никога не могат да променят шаблона на заявката.
</details>

### Въпрос 2: Защо GET заявките са уязвими на CSRF, дори ако са защитени с токени?
- A) Браузърите не поддържат GET форми.
- B) GET заявките излагат на показ токените в историята на URL адресите, логовете на браузъра и заглавните части Referer, и освен това никога не трябва да се използват за операции, променящи състоянието.
- C) GET заявките автоматично заобикалят междинния софтуер (middleware) на Laravel за проверка на CSRF токени.

<details>
<summary>Кликнете, за да видите отговора</summary>

**Отговор: B**
GET заявките са проектирани да бъдат идемпотентни (безопасно четене). Ако една GET заявка променя състоянието, нападателите могат да вградят целевия URL адрес в междусайтови връзки или тагове за изображения, предизвиквайки промяната на състоянието автоматично без съгласието на потребителя.
</details>

### Въпрос 3: Как ограничаването на заявка към базата данни до релацията на даден потребител предотвратява IDOR?
- A) Това криптира първичните ключове на базата данни.
- B) Това блокира заявката на ниво защитна стена (firewall).
- C) Това гарантира, че SQL заявката по естествен път филтрира записите, използвайки ID на удостоверения потребител като ограничително условие за търсене, което прави записите на други потребители недостъпни.

<details>
<summary>Кликнете, за да видите отговора</summary>

**Отговор: C**
Ограничаването на обхвата на заявките (напр. `$user->invoices()->findOrFail($id)`) добавя клауза `WHERE user_id = ?` към SQL заявката. Ако нападател се опита да извлече ID на ресурс, принадлежащ на някой друг, заявката ще върне нула записа, което ще доведе до безопасна грешка 404.
</details>
