---
title: "Уязвимости веб-приложений и методы их устранения: SQLi, внедрение команд, XSS, CSRF и IDOR | DevSense"
description: "Защитите свои PHP-приложения от распространенных уязвимостей. Узнайте, как предотвратить SQL-инъекции, внедрение команд, XSS, CSRF и IDOR с помощью примеров безопасного кода."
published: 2026-06-19
faq:
  - question: "Почему подготовленные выражения безопасны против SQL-инъекций?"
    answer: "Подготовленные выражения отправляют шаблон SQL-запроса и данные параметров в СУБД отдельно друг от друга. СУБД сначала компилирует SQL-запрос, гарантируя, что данные параметров никогда не будут синтаксически разобраны или выполнены как SQL-команд, независимо от того, какие символы они содержат."
  - question: "Когда безопасно использовать неэкранированную директиву Blade {!! $var !!} в Laravel?"
    answer: "Использовать `{!! $var !!}` безопасно только тогда, когда переменная содержит необработанный HTML-код, который вы сгенерировали сами или очистили с помощью надежной библиотеки очистки HTML (например, HTMLPurifier). Вы никогда не должны выводить необработанный пользовательский ввод с помощью этой директивы."
  - question: "Как выборка через связь предотвращает IDOR?"
    answer: "Выборка через связь (например, `$user->orders()->findOrFail($id)`) гарантирует, что запрос к базе данных естественным образом фильтрует результаты по идентификатору аутентифицированного пользователя. Злоумышленник, пытающийся получить доступ к чужому ID, получит ошибку 404 Not Found, так как запись не существует в рамках связанной модели."
---

# Уязвимости веб-приложений и методы их устранения: SQLi, внедрение команд, XSS, CSRF и IDOR

Разработка безопасных веб-приложений заключается не в добавлении механизмов безопасности в самом конце разработки. Она требует понимания того, как уязвимости возникают на уровне кода, и проектирования защитных барьеров для их предотвращения.

В этом руководстве мы разберем пять критических уязвимостей веб-приложений (SQL-инъекции, внедрение команд, межсайтовый скриптинг, CSRF и IDOR) на примере PHP и Laravel, изучим способы их эксплуатации и реализуем безопасные методы защиты.

**Сопутствующие руководства:** [Переход от монолита к микросервисной архитектуре](monolith-to-microservices-architecture) · [Наблюдаемость и мониторинг в Laravel](observability-monitoring-laravel)

## Содержание

* [SQL-инъекции (SQLi)](#sql-injection)
* [Внедрение команд (Command Injection)](#command-injection)
* [Межсайтовый скриптинг (XSS)](#xss)
* [Межсайтовая подделка запроса (CSRF)](#csrf)
* [Небезопасные прямые ссылки на объекты (IDOR)](#idor)
* [Распространенные ошибки](#common-mistakes)
* [Контрольный список](#checklist)
* [Резюме](#summary)
* [Тест для самопроверки](#self-test-quiz)

---

<a id="sql-injection"></a>
## SQL-инъекции (SQLi)

**SQL-инъекции (SQL Injection)** возникают, когда ненадежные пользовательские данные конкатенируются напрямую со строкой SQL-запроса. Это позволяет злоумышленникам изменять структуру запроса, обходить аутентификацию, считывать конфиденциальные данные из базы данных или удалять записи.

### Плохой способ: конкатенация строк и unsafe-сортировка

В данном примере разработчик конкатенирует переменные ввода напрямую в запрос, а также использует неотфильтрованные данные для определения столбца сортировки.

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

### Хороший способ: подготовленные выражения и белый список столбцов

Чтобы исправить это, мы должны использовать **подготовленные выражения (привязку параметров)** для передаваемых в запрос данных. Поскольку столбцы сортировки не могут быть параметризованы, мы должны валидировать их по строгому **белому списку (allowlist)**.

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
## Внедрение команд

**Внедрение команд (Command Injection)** происходит, когда пользовательский ввод передается напрямую в функции системной оболочки, такие как `exec()`, `shell_exec()` или `system()`. Это позволяет злоумышленникам выполнять произвольные shell-команды на сервере с правами пользователя, от имени которого запущен веб-сервер.

### Плохой способ: выполнение shell-команд через конкатенацию строк

В этом примере мы пытаемся преобразовать PDF-файл в миниатюру формата PNG с помощью утилиты командной строки, но передаем имя файла напрямую в shell.

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

### Хороший способ: компонент Process с передачей аргументов в виде массива

Никогда не вызывайте командную оболочку напрямую и не передавайте конкатенированные аргументы. Используйте специализированные библиотеки-обертки для управления процессами (такие как Symfony Process), которые осуществляют экранирование аргументов и их запуск без использования среды командной оболочки.

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
## Межсайтовый скриптинг (XSS)

**Межсайтовый скриптинг (Cross-Site Scripting, XSS)** возникает, когда приложение выводит на веб-странице неотфильтрованные данные пользователя без надлежащего экранирования. Браузер жертвы выполняет вредоносный JavaScript-код, который может украсть сессионные файлы cookie, перехватить ввод (кейлоггинг) или перенаправить пользователя.

Существует три типа XSS:
- **Отраженная XSS (Reflected XSS):** Скрипт является частью отправляемого запроса и сразу же возвращается в ответе сервера.
- **Хранимая XSS (Stored XSS):** Скрипт сохраняется в базе данных (например, текст комментария) и выполняется каждый раз, когда другие пользователи просматривают страницу.
- **XSS на основе DOM (DOM-based XSS):** Уязвимость существует исключительно в клиентском JavaScript-коде, который выполняет недоверенные DOM-переменные.

### Плохой способ: вывод неэкранированного пользовательского контента

Директива Blade `{!! $var !!}` в Laravel выводит необработанный HTML, обходя стандартное экранирование XSS в Blade.

```blade
<!-- resources/views/profile.blade.php -->
<div class="user-bio">
    <!-- VULNERABLE: Stored XSS if bio contains <script>alert('xss')</script> -->
    {!! $user->bio !!}
</div>
```

### Хороший способ: экранирование по умолчанию и очистка форматированного текста

Всегда используйте конструкцию `{{ $var }}`, которая автоматически вызывает функцию `e()` (функцию `htmlspecialchars` в PHP) для экранирования вывода. Если вам необходимо вывести форматированный текст, предоставленный пользователем, пропустите его через надежную библиотеку очистки HTML (например, HTMLPurifier).

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
## Межсайтовая подделка запроса (CSRF)

**Межсайтовая подделка запроса (Cross-Site Request Forgery, CSRF)** заставляет браузер авторизованного пользователя выполнять действия, изменяющие состояние (например, изменение пароля или совершение платежей), на веб-ресурсе, в котором пользователь в данный момент аутентифицирован. Браузер автоматически прикрепляет сессионные файлы cookie к межсайтовым запросам, подтверждая их подлинность.

### Плохой способ: изменение состояния через GET-запросы

GET-запросы всегда должны быть **идемпотентными** (безопасными для многократного выполнения без изменения состояния). Выполнение обновлений данных в GET-маршрутах делает CSRF-атаки тривиальными.

```php
// routes/web.php
// VULNERABLE: Anyone can link to /profile/delete in an img tag to delete an account
Route::get('/profile/delete', [ProfileController::class, 'destroy']);
```

### Хороший способ: маршруты POST/DELETE с CSRF-токенами

Всегда используйте HTTP-методы, изменяющие состояние (POST, PUT, DELETE), и требуйте секретный, криптографически стойкий токен, который не может быть угадан сторонними сайтами.

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
## Небезопасные прямые ссылки на объекты (IDOR)

**IDOR (Insecure Direct Object Reference)** возникает, когда приложение предоставляет доступ к ключу базы данных или идентификатору ресурса напрямую пользователям, не проверяя, имеет ли запрашивающий пользователь права на доступ к этому конкретному ресурсу.

### Плохой способ: глобальный запрос без проверки авторизации

В этом примере любой авторизованный пользователь может получить доступ к данным чужого счета, просто изменив параметр `{id}` в URL.

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

### Хороший способ: запросы с ограничением области видимости (связей) и авторизация через Gate

Разграничивайте получение ресурсов, загружая их через область видимости связей (relationship scope) модели пользователя, или проверяйте права доступа с помощью механизмов авторизации (Gates/Policies).

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
## Распространенные ошибки

1. **Экранирование вместо параметризации:** Заблуждение о том, что использование `addslashes()` или простых регулярных выражений для переменных защищает запросы. Всегда используйте параметризацию.
2. **Изменение состояния в GET-запросах:** Создание API/веб-эндпоинтов, выполняющих действия по удалению или обновлению данных с использованием метода HTTP GET.
3. **Мега-шлюзы для валидации:** Перекладывание валидации на предмет XSS/SQLi на плечи WAF или шлюзов безопасности вместо написания надежной валидации непосредственно в контроллере приложения.
4. **IDOR в AJAX-запросах:** Обеспечение безопасности основных маршрутов HTML-страниц, но раскрытие неавторизованных идентификаторов ресурсов в конечных точках API / AJAX (JSON).

---

<a id="checklist"></a>
## Контрольный список

1. **Безопасность запросов:** Используете ли вы конкатенацию переменных внутри сырых выражений `DB::select` or `whereRaw`?
2. **Выполнение команд оболочки:** Можете ли вы заменить shell-команды стандартными библиотеками PHP? Если нет, передаются ли аргументы в виде массива?
3. **Вывод Blade:** Используете ли вы `{!! !!}` в шаблонах Blade без предварительной очистки с помощью HTMLPurifier?
4. **Проверка авторизации:** Проверяет ли каждый метод контроллера, загружающий ресурс, принадлежит ли этот ресурс текущему пользователю?

---

<a id="summary"></a>
## Резюме

Безопасные веб-приложения строго валидируют входные данные и используют стратегию глубокой защиты (defense-in-depth). Предотвращайте **SQL-инъекции** с помощью подготовленных выражений и белых списков столбцов. Избегайте **внедрения команд**, используя массивы аргументов внутри процессов-оберток. Блокируйте **XSS**, экранируя выводимые переменные средствами шаблонизатора. Пресекайте **CSRF**, изолируя изменения состояния в запросах POST/DELETE, защищенных токенами. Устраняйте **IDOR**, ограничивая выборку рамками связей аутентифицированного пользователя или выполняя проверки политик авторизации.

---

<a id="self-test-quiz"></a>
## Тест для самопроверки

### Вопрос 1: В чем основное различие между экранированием и параметризацией для SQL-запросов?
- A) Экранирование выполняется на сервере базы данных, а параметризация обрабатывается внутри движка PHP.
- B) Экранирование изменяет специальные символы внутри строк запроса, чтобы сделать их безопасными, тогда как параметризация отправляет структуру запроса и параметры в СУБД отдельно друг от друга.
- C) Параметризация совместима только с базами данных PostgreSQL.

<details>
<summary>Нажмите, чтобы увидеть ответ</summary>

**Ответ: B**
Экранирование представляет собой манипуляцию со строками, которая подвержена ошибкам парсера и методам обхода. Параметризация — это функция протокола, при которой структура SQL и значения данных отправляются независимо друг от друга, что гарантирует невозможность изменения шаблона запроса передаваемыми данными.
</details>

### Вопрос 2: Почему GET-запросы уязвимы для CSRF, даже если они защищены токенами?
- A) Браузеры не поддерживают GET-формы.
- B) GET-запросы раскрывают токены в истории URL, логах браузера и заголовках Referer, и в любом случае никогда не должны использоваться для операций, изменяющих состояние.
- C) GET-запросы автоматически обходят посредник (middleware) проверки CSRF-токенов в Laravel.

<details>
<summary>Нажмите, чтобы увидеть ответ</summary>

**Ответ: B**
GET-запросы предназначены для идемпотентного использования (безопасного чтения данных). Если GET-запрос изменяет состояние, злоумышленники могут внедрить целевой URL-адрес в межсайтовые ссылки или теги изображений, автоматически инициируя изменение состояния без согласия пользователя.
</details>

### Вопрос 3: Как ограничение запроса к базе данных связью с пользователем предотвращает IDOR?
- A) Это шифрует первичные ключи базы данных.
- B) Это блокирует запрос на уровне брандмауэра.
- C) Это гарантирует, что SQL-запрос естественным образом фильтрует записи, используя ID аутентифицированного пользователя в качестве поискового ограничения, делая записи других пользователей недосягаемыми.

<details>
<summary>Нажмите, чтобы увидеть ответ</summary>

**Ответ: C**
Ограничение запросов (например, `$user->invoices()->findOrFail($id)`) добавляет условие `WHERE user_id = ?` к SQL-запросу. Если злоумышленник попытается получить идентификатор ресурса, принадлежащий кому-то другому, запрос не вернет никаких записей, что приведет к безопасной ошибке 404.
</details>
