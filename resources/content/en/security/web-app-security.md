---
title: "Web Application Vulnerabilities & Mitigations: SQLi, Command Injection, XSS, CSRF & IDOR | DevSense"
description: "Protect your PHP applications from common web vulnerabilities. Learn how to prevent SQL Injection, Command Injection, XSS, CSRF, and IDOR with secure code examples."
published: 2026-06-19
faq:
  - question: "Why are prepared statements secure against SQL injection?"
    answer: "Prepared statements send the SQL query template and the parameter data separately to the database engine. The engine compiles the SQL query first, ensuring that parameter data is never parsed or executed as SQL commands, regardless of what characters it contains."
  - question: "When is it safe to use Laravel's unescaped blade directive {!! $var !!}?"
    answer: "It is only safe to use `{!! $var !!}` when the variable contains raw HTML that you have generated yourself or purified using a secure HTML sanitization library like HTMLPurifier. You should never output raw user input using this directive."
  - question: "How does relation-scoped lookup prevent IDOR?"
    answer: "Relation-scoped lookup (e.g., `$user->orders()->findOrFail($id)`) ensures that the database query naturally filters results by the authenticated user's ID. An attacker trying to access another user's ID will receive a 404 Not Found error because the record does not exist within the scoped relationship."
---

# Web Application Vulnerabilities & Mitigations: SQLi, Command Injection, XSS, CSRF & IDOR

Building secure web applications is not about adding security at the end of development. It requires understanding how vulnerabilities arise at the code level and designing boundaries to prevent them. 

In this guide, we will analyze five critical web application vulnerabilities (SQL Injection, Command Injection, Cross-Site Scripting, CSRF, and IDOR) in PHP and Laravel, see how they are exploited, and implement secure mitigations.

**Related guides:** [Monolith to microservices architecture](monolith-to-microservices-architecture) · [Observability & monitoring](observability-monitoring-laravel)

## Contents

* [SQL Injection (SQLi)](#sql-injection)
* [Command Injection](#command-injection)
* [Cross-Site Scripting (XSS)](#xss)
* [Cross-Site Request Forgery (CSRF)](#csrf)
* [Insecure Direct Object References (IDOR)](#idor)
* [Common Mistakes](#common-mistakes)
* [Checklist](#checklist)
* [Summary](#summary)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="sql-injection"></a>
## SQL Injection (SQLi)

**SQL Injection** occurs when untrusted user input is concatenated directly into SQL query strings. This allows attackers to manipulate the query structure, bypass authentication, read sensitive database contents, or delete records.

### The Bad Way: String Concatenation and Unsafe Ordering

Here, the developer concatenates input variables directly into the query, and uses unsanitized input to define the sorting column.

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

### The Good Way: Prepared Statements and Column Allowlist

To fix this, we must use **Prepared Statements (parameter binding)** for query data values. Because sorting columns cannot be parameterized, we must validate them against a strict **allowlist**.

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
## Command Injection

**Command Injection** occurs when user input is passed directly into system shell functions like `exec()`, `shell_exec()`, or `system()`. This allows attackers to execute arbitrary shell commands on the server under the web server's user permissions.

### The Bad Way: Shell Exec with String Concatenation

In this example, we attempt to convert a PDF file to a PNG thumbnail using a command-line tool, but pass the filename directly to the shell.

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

### The Good Way: Process Component with Argument Arrays

Never invoke the shell directly or pass concatenated arguments. Use process wrapper libraries (like Symfony Process) that handle argument escaping and execution safely without using a shell environment.

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
## Cross-Site Scripting (XSS)

**Cross-Site Scripting (XSS)** occurs when an application includes untrusted user data in a web page without proper escaping. The attacker's browser executes malicious JavaScript, which can steal session cookies, capture input (keylogging), or redirect users.

There are three types of XSS:
- **Reflected XSS:** The script is part of the request payload and is reflected back immediately in the response.
- **Stored XSS:** The script is saved to the database (e.g., comment text) and executed when other users view the page.
- **DOM-based XSS:** The vulnerability exists purely in client-side JavaScript executing untrusted DOM variables.

### The Bad Way: Printing Unescaped User Content

Laravel's `{!! $var !!}` outputs raw HTML, bypassing Blade's default XSS escaping.

```blade
<!-- resources/views/profile.blade.php -->
<div class="user-bio">
    <!-- VULNERABLE: Stored XSS if bio contains <script>alert('xss')</script> -->
    {!! $user->bio !!}
</div>
```

### The Good Way: Escape by Default and Purify Rich Text

Always use `{{ $var }}` which automatically runs `e()` (PHP's `htmlspecialchars`) to escape output. If you must output user-supplied rich text, run it through a secure HTML sanitization library (like HTMLPurifier).

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
## Cross-Site Request Forgery (CSRF)

**Cross-Site Request Forgery (CSRF)** forces an authenticated user's browser to execute state-changing actions (like modifying password or initiating payments) on an application they are currently logged into. The browser automatically attaches session cookies to cross-site requests, validating the request.

### The Bad Way: State-Changing Actions on GET Requests

GET requests must always be **idempotent** (safe to execute multiple times without modifying state). Running updates on GET routes makes CSRF trivial.

```php
// routes/web.php
// VULNERABLE: Anyone can link to /profile/delete in an img tag to delete an account
Route::get('/profile/delete', [ProfileController::class, 'destroy']);
```

### The Good Way: POST/DELETE Routes with CSRF Tokens

Always use state-changing HTTP methods (POST, PUT, DELETE) and require a secret, cryptographically secure token that cannot be guessed by external websites.

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
## Insecure Direct Object References (IDOR)

**IDOR (Insecure Direct Object Reference)** occurs when an application exposes a database key or resource ID directly to users, and fails to verify if the requesting user has permission to access that specific resource.

### The Bad Way: Global Query Lookup Without Authorization

In this example, any logged-in user can access another user's invoice details simply by changing the `{id}` in the URL parameter.

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

### The Good Way: Relation-Scoped Queries and Gate Authorization

Decouple resource retrieval by loading them through the user's relationship model scope, or check permissions using authorization Gates/Policies.

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
## Common Mistakes

1. **Escaping instead of Parameterizing:** Thinking that running `addslashes()` or simple regex on variables protects queries. Always parameterize.
2. **GET State Alterations:** Building API/web endpoints that perform delete or update actions using HTTP GET.
3. **Mega-Gateways containing validation:** Leaving XSS/SQLi validation to WAFs or Gateways instead of coding robust validation directly in the application controller.
4. **IDOR on AJAX requests:** Securing primary HTML page routes but exposing un-authorized resource IDs in AJAX/API JSON endpoints.

---

<a id="checklist"></a>
## Checklist

1. **Query Security:** Are you concatenating variables inside raw `DB::select` or `whereRaw` statements?
2. **Shell Execution:** Can you replace shell commands with standard PHP libraries? If not, are arguments passed in an array?
3. **Blade output:** Are you using `{!! !!}` anywhere in Blade templates without HTMLPurifier validation?
4. **Authorization check:** Does every controller method loading a resource check if the resource belongs to the current user?

---

<a id="summary"></a>
## Summary

Secure web applications validate inputs strictly and apply defense-in-depth. Prevent **SQL Injection** using prepared statements and column allowlists. Avoid **Command Injection** by using array arguments inside process wrappers. Block **XSS** by escaping variable outputs using template engines. Stop **CSRF** by isolating state mutations to POST/DELETE requests protected with tokens. Defeat **IDOR** by scoping lookups to authenticated user relations or executing policy checks.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: What is the primary difference between escaping and parameterization for SQL queries?
- A) Escaping runs on the database server, while parameterization is handled inside the PHP engine.
- B) Escaping changes special characters inside query strings to make them safe, while parameterization sends query structure and parameters separately to the database engine.
- C) Parameterization is only compatible with PostgreSQL databases.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
Escaping is a string manipulation process that is prone to parser bugs and bypasses. Parameterization is a protocol feature where SQL structure and data values are sent independently, guaranteeing that data can never alter the query template.
</details>

### Question 2: Why are GET requests vulnerable to CSRF even if protected by tokens?
- A) Browsers do not support GET forms.
- B) GET requests expose tokens in URL history, browser logs, and Referer headers, and should never be used for state-changing operations anyway.
- C) GET requests automatically bypass Laravel's CSRF token verification middleware.

<details>
<summary>Click to view the answer</summary>

**Answer: B**
GET requests are designed to be idempotent (safe reads). If a GET request changes state, attackers can embed the target URL in cross-site links or image tags, triggering the state change automatically without the user's consent.
</details>

### Question 3: How does scoping a database query to a user relation prevent IDOR?
- A) It encrypts database primary keys.
- B) It blocks the request at the firewall level.
- C) It guarantees that the SQL query naturally filters records using the authenticated user's ID as a search constraint, making other users' records unreachable.

<details>
<summary>Click to view the answer</summary>

**Answer: C**
Scoping queries (e.g., `$user->invoices()->findOrFail($id)`) adds a `WHERE user_id = ?` clause to the SQL query. If an attacker attempts to fetch a resource ID belonging to someone else, the query will return zero records, yielding a safe 404 error.
</details>
