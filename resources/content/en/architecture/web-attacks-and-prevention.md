---
title: "Web attacks & defenses: XSS, CSRF, SQLi, SSRF, IDOR, and file uploads | DevSense"
description: "The web attacks you’ll meet most often: injections (SQL/command), XSS, CSRF, IDOR/access-control bugs, SSRF, unsafe file uploads, clickjacking, and configuration pitfalls. Practical mitigations, headers, and checklists."
published: 2026-04-14
faq:
  - question: "Why is sanitizing HTML input not enough to prevent SQL injection?"
    answer: "SQL injection occurs when untrusted data is concatenated into database queries. Sanitizing HTML removes tags like `<script>`, but does not prevent characters like quotes or semicolons from altering the structure of an SQL query. The only robust solution is to use parameterized queries (prepared statements)."
  - question: "Can SameSite cookies completely replace CSRF tokens?"
    answer: "While `SameSite=Lax` or `Strict` protects against cross-site requests in modern browsers, they are not a complete security guarantee. Legacy browsers may not support SameSite, and bugs or GET state-changes can bypass it. CSRF tokens remain essential defense-in-depth."
  - question: "How does SSRF differ from CSRF?"
    answer: "CSRF forces a user's browser to send requests to an application they are authenticated to. SSRF forces the application's *server* to make unauthorized requests to internal resources (like metadata endpoints or internal Redis) that are unreachable from the public internet."
  - question: "Why is checking only the file extension insecure for file uploads?"
    answer: "Attackers can easily bypass extension checks (e.g., using double extensions or renaming files) or exploit vulnerabilities where the web server executes files with correct extensions but malicious content (like PHP embedded in PNG). You must validate the file content (MIME type) and store uploads outside the web root."
---

# Web attacks and defenses every developer should know

Your application is running smoothly, traffic is growing, and the latest deployment went off without a hitch. Then, a single malformed query from an unauthenticated user wipes out a database table, or an internal microservice leaks customer records to the public internet because of an exposed HTTP client. Security tends to fail **at the seams**: validation exists, but not at the boundary; authorization is implemented, but one endpoint is missing it; output is escaped, but one template uses raw HTML. The good news: most real incidents fall into repeatable classes of mistakes—so you can fix them systematically.

**Related guides:** [Observability & monitoring](observability-monitoring-laravel) · [Databases under load](database-performance-and-scaling)

## Contents

* [Threat modeling: what you protect and from whom](#threat-model)
* [Injections: SQLi, command injection, template injection](#injection)
* [XSS: reflected, stored, DOM-based](#xss)
* [CSRF: why “but it’s GET” is not a defense](#csrf)
* [Auth & sessions: token theft, fixation, cookies](#auth-sessions)
* [Access control & IDOR: “it’s just an id”](#idor)
* [File uploads & path: uploads, traversal, nearby RCE](#uploads)
* [SSRF: when your server fetches “the wrong place”](#ssrf)
* [Unsafe deserialization and object spoofing](#deserialization)
* [Browser defenses: clickjacking, CORS, headers](#browser)
* [DoS & abuse: rate limits, brute force, expensive work](#dos)
* [Common Mistakes](#common-mistakes)
* [Release checklist](#checklist)
* [Self-Test Quiz](#self-test-quiz)

---

<a id="threat-model"></a>
## Threat modeling: what you protect and from whom

Before writing security logic, you must set the threat parameters:

- **Attacker**: anonymous user, logged-in user, partner with an API key, someone inside the VPN, an actor with CI/CD access.
- **Assets**: money, PII, accounts, admin panels, integrations, secrets, internal network access.
- **Attack surface**: forms and APIs, redirects, webhooks, imports/exports, file uploads, third-party integrations (S3, SMTP, payments).

> [!NOTE]
> **Pragmatic Security Rule**
> Always assume all inputs are malicious. This includes headers, cookies, webhook payloads, URL parameters, and messages read from queues.

---

<a id="injection"></a>
## Injections: SQLi, command injection, template injection

### SQL Injection (SQLi)

SQLi usually starts as “just one raw SQL snippet”. The fix is not “escaping”, but **parameterizing**.

**Bad** (string concatenation):
```php
// app/Http/Controllers/UserController.php
$rows = DB::select("SELECT * FROM users WHERE email = '{$email}'");
```

**Better** (placeholders):
```php
// app/Http/Controllers/UserController.php
$rows = DB::select('SELECT * FROM users WHERE email = ?', [$email]);
```

Another common trap is **dynamic column names / sorting**. Parameters don’t apply to SQL identifiers, so you must use a strict allowlist:
```php
// app/Http/Controllers/UserController.php
$allowed = ['created_at', 'email', 'id'];
$sort = in_array($request->get('sort'), $allowed, true) ? $request->get('sort') : 'created_at';

$users = User::query()->orderBy($sort, 'desc')->paginate();
```

### Command injection

If you call the shell, the rule is: **never pass user input into a command line**. Even `escapeshellarg()` is a last-resort guardrail, not a secure design.

If you must run CLI tooling (convert files, generate previews):
- Prefer a library over `exec()`.
- If CLI is unavoidable, pin binaries/args/dirs and run in a restricted environment (container/queue/worker under a dedicated user).

### Template injection

The dangerous case is letting users provide templates that are executed as Blade/Twig/Smarty.
- **Rule**: User templates must be treated as data, ideally rendered through a limited, non–Turing complete format (e.g., Markdown with a strict allowlist and safe rendering).

---

<a id="xss"></a>
## XSS: reflected, stored, DOM-based

XSS is running JavaScript in your origin. Typical sources:
- Raw output (`{!! ... !!}` in Blade, `innerHTML` on the frontend).
- Rich-text fields stored without sanitization.
- Injecting values into `<script>` or HTML attributes without proper encoding.

### Core mitigations

- **Escape by default**: in Blade use `{{ $value }}`; avoid raw output unless justified.
- **Respect contexts**: HTML vs attributes vs JS strings vs URLs—different encoding rules.
- **Sanitize HTML input**: if you allow formatting, use an allowlist (tags/attrs) and strip `on*` handlers, `javascript:` URLs, etc.
- **CSP**: not a logic fix, but a major damage limiter.

Starter CSP (ideally with nonces and without `'unsafe-inline'`):
```http
# /etc/nginx/nginx.conf
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'
```

---

<a id="csrf"></a>
## CSRF: why “but it’s GET” is not a defense

CSRF happens when a user’s browser sends a request to your site with their cookies/session because another site triggers it (forms, images, JS).

### Mitigations

- **CSRF tokens** for state-changing requests (POST/PUT/PATCH/DELETE). Laravel does this out of the box.
- **`SameSite` cookies**: at least `Lax`; consider `Strict` for sensitive flows; for cross-site cases use `None; Secure`.
- **Origin/Referer checks** for especially sensitive actions (change email, payouts).
- **Never change state via GET**.

---

<a id="auth-sessions"></a>
## Auth & sessions: token theft, fixation, cookies

What usually breaks:
- Weak passwords + missing rate limits → credential stuffing.
- Session theft via XSS; session fixation; missing invalidation.
- Long-lived tokens without rotation and device binding.

### Baseline controls

- **Rate limit** login/OTP/password reset.
- **Hash passwords** with `password_hash` using bcrypt or argon2. No custom crypto.
- Session cookies: `HttpOnly`, `Secure`, `SameSite`, keep lifetime short.
- **Rotate session IDs** after login and privilege changes.

---

<a id="idor"></a>
## Access control & IDOR: “it’s just an id”

IDOR is when a user can guess/iterate identifiers and read or mutate someone else’s data.

Common causes:
- Checking “logged in” instead of ownership/role.
- Authorization exists in the UI but not on the API.
- “Admin” behavior controlled by a request parameter.

### Mitigations

- **Policies/gates** for each resource and action.
- For multi-tenant apps, always filter by `tenant_id`/`account_id` at the query level.
- Don’t rely on “hiding” as security.

```php
// app/Http/Controllers/OrderController.php
// Prefer scoping query to the user:
$order = auth()->user()->orders()->findOrFail($id);

// Over a direct lookup:
$order = Order::findOrFail($id);
```

---

<a id="uploads"></a>
## File uploads & path: uploads, traversal, nearby RCE

Uploads are a classic “RCE next door” area.

### What to enforce

- Validate **content**, not just extension; browser MIME is untrusted.
- Limits on size and count.
- Store outside web-root; serve via a controller or a dedicated domain/CDN.
- Random filenames (don’t trust original names as paths).
- Disable execution in upload directories at the web server level.

```nginx
# /etc/nginx/conf.d/uploads.conf
location /uploads {
    location ~ \.php$ {
        deny all;
    }
}
```

Path traversal often appears as “download file by name”:
- Reject `../`, `\`, null bytes.
- Use an allowlist of real files/IDs, not a path from the request.

---

<a id="ssrf"></a>
## SSRF: when your server fetches “the wrong place”

SSRF appears when you accept a user URL and **fetch it from the server** (avatar import, webhook tester, link preview, PDF fetcher).

Risks: access to **internal services** (cloud metadata endpoints, Redis/Consul, admin panels), bypassing network boundaries.

### Mitigations

- **Allowlist hosts/domains**, not a blacklist.
- Block private IP ranges (127.0.0.0/8, 10.0.0.0/8, 169.254.0.0/16, 172.16.0.0/12, 192.168.0.0/16, ::1, fc00::/7).
- Disable redirects or re-validate each hop.
- Timeouts, response size limits, protocol restrictions (https only).

---

<a id="deserialization"></a>
## Unsafe deserialization and object spoofing

If you deserialize attacker-controlled data (cookies, hidden fields, external queue payloads, unsigned caches), you risk:
- Role/field spoofing.
- Gadget chains and RCE where applicable.

> [!NOTE]
> **Data Integrity**
> Never store serialized PHP/JS objects in untrusted places. For tokens, use signed structures like JWT or HMAC. For structured payloads, use JSON + strict schema validation.

---

<a id="browser"></a>
## Browser defenses: clickjacking, CORS, headers

### Clickjacking

Block framing:
```http
# /etc/nginx/nginx.conf
X-Frame-Options: DENY
Content-Security-Policy: frame-ancestors 'none'
```

### CORS

CORS is not “request blocking”—it’s a browser rule for reading responses. Common mistakes:
- `Access-Control-Allow-Origin: *` with cookies/credentials.
- Overly broad allowed methods/headers.
- Trusting `Origin` without a strict list.

### Baseline security headers
```http
# /etc/nginx/nginx.conf
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

---

<a id="dos"></a>
## DoS & abuse: rate limits, brute force, expensive work

In business apps, the most common “mini-DoS” is not bandwidth—it’s cheap-for-them, expensive-for-you endpoints:
- Unindexed searches, giant exports, PDF generation.
- Missing rate limits (login, email/OTP sending, “promo code check”).
- Expensive validation/regexes over huge strings.

### Controls
- Rate limiting by IP + account + API key.
- Queue expensive work.
- Timeouts, size limits, JSON depth limits.
- Cache heavy public responses.

---

<a id="common-mistakes"></a>
## Common Mistakes

1. **Relying on Client-Side Validation**: Implementing validations on front-end forms but neglecting them on the backend, allowing users to submit bypass payloads directly to API endpoints.
2. **Raw SQL for Sort / Order by Parameters**: Since placeholders cannot represent column names, developers often concatenate user inputs into `ORDER BY` statements, opening SQLi vulnerabilities.
3. **Blacklisting Private IPs in SSRF**: Attempting to block `127.0.0.1` and `10.0.0.1` but forgetting hex encodings (`0x7f.0.0.1`), octal representations, or DNS rebinding attacks.
4. **Incorrect PHP Serialization**: Storing serialized PHP objects in cookies, believing it is safe because the data is "opaque", which enables PHP Object Injection.

---

<a id="checklist"></a>
## Release checklist

### Input & data
- [ ] Validate at the boundary (forms/APIs/webhooks), normalize types.
- [ ] Allowlist for sorting/filtering/fields that become SQL identifiers.
- [ ] No state changes via GET.

### Rendering & browser
- [ ] Escape-by-default, no unjustified raw HTML.
- [ ] CSP/`frame-ancestors`, clickjacking protection.
- [ ] HSTS, `nosniff`, sensible `Referrer-Policy`.

### Access
- [ ] Server-side authorization for each action (policy/gate), not just UI.
- [ ] No IDOR: queries scoped to owner/tenant.
- [ ] Audit logs for sensitive actions.

### Integrations
- [ ] SSRF controls: allowlist, private IP blocks, timeouts/limits.
- [ ] Webhooks: signature verification, replay protection, idempotency.

### Uploads
- [ ] Content/size checks, store outside web-root, disable execution.
- [ ] Random names, no user-provided paths.

---

## Summary

Security is not a final product; it is a set of boundary invariants. Validate input once at the edge, escape output in templates, enforce authorization on the server, sign external integrations, and rate-limit anything costly.

---

<a id="self-test-quiz"></a>
## Self-Test Quiz

### Question 1: What is the primary security issue with the following code?
```php
// app/Http/Controllers/DownloadController.php
return response()->download(storage_path('reports/' . $request->get('file')));
```
- A) SQL Injection.
- B) Cross-Site Scripting (XSS).
- C) Path Traversal (Arbitrary File Download).

<details>
<summary>Click to view the answer</summary>

**Answer: C**
Because the input `$request->get('file')` is concatenated directly into the path without checking for `../`, an attacker can pass `file=../../.env` to read sensitive configuration secrets.
</details>

### Question 2: Why are CSRF tokens required for POST requests if the session cookie is already marked as `SameSite=Lax`?
- A) `SameSite=Lax` does not prevent POST requests triggered by top-level navigations or scripts on older browsers.
- B) CSRF tokens prevent SQL injection.
- C) Without a CSRF token, the session cookie cannot be read.

<details>
<summary>Click to view the answer</summary>

**Answer: A**
SameSite attributes are a helpful defense, but they do not provide 100% security coverage due to browser compatibility issues, sub-domain vulnerability takeovers, or GET-based state modifications.
</details>
