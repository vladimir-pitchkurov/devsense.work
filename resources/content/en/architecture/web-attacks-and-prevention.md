---
title: "Web attacks & defenses: XSS, CSRF, SQLi, SSRF, IDOR, and file uploads | DevSense"
description: "The web attacks you’ll meet most often: injections (SQL/command), XSS, CSRF, IDOR/access-control bugs, SSRF, unsafe file uploads, clickjacking, and configuration pitfalls. Practical mitigations, headers, and checklists."
published: 2026-04-14
---

# Web attacks and defenses every developer should know

Security tends to fail **at the seams**: validation exists, but not at the boundary; authorization is implemented, but one endpoint is missing it; output is escaped, but one template uses raw HTML. The good news: most real incidents fall into repeatable classes of mistakes—so you can fix them systematically.

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
* [Release checklist](#checklist)

---

<a id="threat-model"></a>
## Threat modeling: what you protect and from whom

Before techniques—set the frame:

- **Attacker**: anonymous user, logged-in user, partner with an API key, someone inside the VPN, an actor with CI/CD access.
- **Assets**: money, PII, accounts, admin panels, integrations, secrets, internal network access.
- **Attack surface**: forms and APIs, redirects, webhooks, imports/exports, file uploads, third-party integrations (S3, SMTP, payments).

Practical rule: **all input is untrusted** (including headers, cookies, webhook payloads, URL parameters, queue messages).

---

<a id="injection"></a>
## Injections: SQLi, command injection, template injection

### SQL Injection (SQLi)

SQLi usually starts as “just one raw SQL snippet”. The fix is not “escape”, but **parameterize**.

**Bad** (string concatenation):

```php
$rows = DB::select("SELECT * FROM users WHERE email = '{$email}'");
```

**Better** (placeholders):

```php
$rows = DB::select('SELECT * FROM users WHERE email = ?', [$email]);
```

Another common trap is **dynamic column names / sorting**. Parameters don’t apply to identifiers, so use an allowlist:

```php
$allowed = ['created_at', 'email', 'id'];
$sort = in_array($request->get('sort'), $allowed, true) ? $request->get('sort') : 'created_at';

$users = User::query()->orderBy($sort, 'desc')->paginate();
```

### Command injection

If you call the shell, the rule is: **never pass user input into a command line**. Even `escapeshellarg()` is a last-resort guardrail, not a design.

If you must run CLI tooling (convert files, generate previews):

- prefer a library over `exec()`;
- if CLI is unavoidable, pin binaries/args/dirs and run in a restricted environment (container/queue/worker under a dedicated user).

### Template injection

The dangerous case is letting users provide templates that are executed as Blade/Twig/Smarty.

Rule: **user templates should be treated as data**, ideally rendered through a limited, non–Turing complete format (e.g., Markdown with a strict allowlist and safe rendering).

---

<a id="xss"></a>
## XSS: reflected, stored, DOM-based

XSS is running JavaScript in your origin. Typical sources:

- raw output (`{!! ... !!}` in Blade, `innerHTML` on the frontend);
- rich-text fields stored without sanitization;
- injecting values into `<script>` or HTML attributes without proper encoding.

### Core mitigations

- **Escape by default**: in Blade use `{{ $value }}`; avoid raw output unless justified.
- **Respect contexts**: HTML vs attributes vs JS strings vs URLs—different encoding rules.
- **Sanitize HTML input**: if you allow formatting, use an allowlist (tags/attrs) and strip `on*` handlers, `javascript:` URLs, etc.
- **CSP**: not a logic fix, but a major damage limiter.

Starter CSP (ideally with nonces and without `'unsafe-inline'`):

```http
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

- weak passwords + missing rate limits → credential stuffing;
- session theft via XSS; session fixation; missing invalidation;
- long-lived tokens without rotation and device binding.

Baseline controls:

- **Rate limit** login/OTP/password reset.
- **Hash passwords** with `password_hash()` (bcrypt/argon2). No custom crypto.
- Session cookies: `HttpOnly`, `Secure`, `SameSite`, keep lifetime short where you can.
- **Rotate session IDs** after login and privilege changes.

---

<a id="idor"></a>
## Access control & IDOR: “it’s just an id”

IDOR is when a user can guess/iterate identifiers and read or mutate someone else’s data.

Common causes:

- checking “logged in” instead of ownership/role;
- auth exists in the UI but not on the API;
- “admin” behavior controlled by a request parameter.

Mitigations:

- **Policies/gates** for each resource and action.
- For multi-tenant apps, always filter by `tenant_id`/`account_id` at the query level.
- Don’t rely on “hiding” as security.
- Audit logs for sensitive reads and writes.

Example idea: prefer `auth()->user()->orders()->findOrFail($id)` over `Order::findOrFail($id)`.

---

<a id="uploads"></a>
## File uploads & path: uploads, traversal, nearby RCE

Uploads are a classic “RCE next door” area.

### What to enforce

- validate **content**, not just extension; browser MIME is untrusted;
- limits on size and count;
- store outside web-root; serve via a controller or a dedicated domain/CDN;
- random filenames (don’t trust original names as paths);
- disable execution in upload directories at the web server level.

Path traversal often appears as “download file by name”:

- reject `../`, `\`, null bytes;
- use an allowlist of real files/IDs, not a path from the request.

---

<a id="ssrf"></a>
## SSRF: when your server fetches “the wrong place”

SSRF appears when you accept a user URL and **fetch it from the server** (avatar import, webhook tester, link preview, PDF fetcher).

Risks: access to **internal services** (cloud metadata endpoints, Redis/Consul, admin panels), bypassing network boundaries.

Mitigations:

- **allowlist hosts/domains**, not a blacklist;
- block private IP ranges (127.0.0.0/8, 10.0.0.0/8, 169.254.0.0/16, 172.16/12, 192.168/16, ::1, fc00::/7);
- disable redirects or re-validate each hop;
- timeouts, response size limits, protocol restrictions (https only).

---

<a id="deserialization"></a>
## Unsafe deserialization and object spoofing

If you deserialize attacker-controlled data (cookies, hidden fields, external queue payloads, unsigned caches), you risk:

- role/field spoofing;
- gadget chains and RCE where applicable.

Rule: **don’t store serialized objects in untrusted places**. For tokens use signatures (HMAC). For structured payloads use JSON + strict schema + server-side validation.

---

<a id="browser"></a>
## Browser defenses: clickjacking, CORS, headers

### Clickjacking

Block framing:

```http
X-Frame-Options: DENY
Content-Security-Policy: frame-ancestors 'none'
```

### CORS

CORS is not “request blocking”—it’s a browser rule for reading responses. Common mistakes:

- `Access-Control-Allow-Origin: *` with cookies/credentials;
- overly broad allowed methods/headers;
- trusting `Origin` without a strict list.

### Baseline security headers

```http
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

---

<a id="dos"></a>
## DoS & abuse: rate limits, brute force, expensive work

In business apps, the most common “mini-DoS” is not bandwidth—it’s cheap-for-them, expensive-for-you endpoints:

- unindexed searches, giant exports, PDF generation;
- missing rate limits (login, email/OTP sending, “promo code check”);
- expensive validation/regexes over huge strings.

Controls:

- rate limiting by IP + account + API key (scenario-dependent);
- queue expensive work;
- timeouts, size limits, JSON depth limits;
- cache heavy public responses (mind personalization).

---

<a id="checklist"></a>
## Release checklist

### Input & data

- [ ] Validate at the boundary (forms/APIs/webhooks), normalize types.
- [ ] Allowlist for sorting/filtering/fields that become identifiers.
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

If you remember one principle: **security is boundary invariants**. Validate input once (correctly), escape output by default, enforce authorization on the server, sign external integrations, and rate-limit anything costly.
