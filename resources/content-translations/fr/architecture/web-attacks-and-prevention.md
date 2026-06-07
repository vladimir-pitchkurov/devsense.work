---
title: "Attaques et défenses web : XSS, CSRF, SQLi, SSRF, IDOR et téléversements de fichiers | DevSense"
description: "Les attaques web que vous rencontrerez le plus souvent : injections (SQL/commande), XSS, CSRF, failles IDOR/contrôle d'accès, SSRF, téléversements de fichiers non sécurisés, clickjacking et pièges de configuration. Atténuations pratiques, en-têtes et listes de contrôle."
published: 2026-04-14
faq:
  - question: "Pourquoi la désinfection des entrées HTML ne suffit-elle pas à empêcher l'injection SQL ?"
    answer: "L'injection SQL se produit lorsque des données non fiables sont concaténées dans des requêtes de base de données. La désinfection du HTML supprime les balises comme `<script>`, mais n'empêche pas les caractères comme les guillemets ou les points-virgules de modifier la structure d'une requête SQL. La seule solution robuste consiste à utiliser des requêtes paramétrées (requêtes préparées)."
  - question: "Les cookies SameSite peuvent-ils remplacer complètement les jetons CSRF ?"
    answer: "Bien que `SameSite=Lax` ou `Strict` protège contre les requêtes intersites dans les navigateurs modernes, ils ne constituent pas une garantie de sécurité complète. Les navigateurs plus anciens peuvent ne pas prendre en charge SameSite, et des bogues ou des modifications d'état via des requêtes GET peuvent le contourner. Les jetons CSRF restent essentiels pour une défense en profondeur."
  - question: "En quoi la faille SSRF diffère-t-elle de la faille CSRF ?"
    answer: "La faille CSRF force le navigateur d'un utilisateur à envoyer des requêtes à une application sur laquelle il est authentifié. La faille SSRF force le *serveur* de l'application à effectuer des requêtes non autorisées vers des ressources internes (comme des points de terminaison de métadonnées ou un Redis interne) qui sont inaccessibles depuis l'internet public."
  - question: "Pourquoi la vérification de la seule extension de fichier est-elle insuffisante pour sécuriser les téléversements ?"
    answer: "Les attaquants peuvent facilement contourner les vérifications d'extension (par exemple, en utilisant des doubles extensions ou en renommant des fichiers) ou exploiter des vulnérabilités où le serveur web exécute des fichiers ayant les bonnes extensions mais un contenu malveillant (comme du code PHP intégré dans un PNG). Vous devez valider le contenu du fichier (type MIME) et stocker les téléversements en dehors de la racine web."
---

# Attaques et défenses web que chaque développeur devrait connaître

Votre application fonctionne correctement, le trafic augmente et le dernier déploiement s'est déroulé sans accroc. Puis, une seule requête malformée provenant d'un utilisateur non authentifié efface une table de base de données, ou un microservice interne divulgue des enregistrements clients sur l'internet public à cause d'un client HTTP exposé. La sécurité a tendance à échouer **au niveau des liaisons** : la validation existe, mais pas à la frontière du système ; l'autorisation est implémentée, mais un point de terminaison en est dépourvu ; la sortie est échappée, mais un modèle utilise du HTML brut. La bonne nouvelle : la plupart des incidents réels relèvent de classes d'erreurs reproductibles — vous pouvez donc les corriger systématiquement.

**Guides associés :** [Observability & monitoring](observability-monitoring-laravel) · [Databases under load](database-performance-and-scaling)

## Table des matières

* [Modélisation des menaces : ce que vous protégez et contre qui](#threat-model)
* [Injections : SQLi, injection de commandes, injection de modèles](#injection)
* [XSS : réfléchi, stocké, basé sur le DOM](#xss)
* [CSRF : pourquoi « mais c'est du GET » n'est pas une défense](#csrf)
* [Authentification et sessions : vol de jetons, fixation, cookies](#auth-sessions)
* [Contrôle d'accès et IDOR : « c'est juste un identifiant »](#idor)
* [Téléversements et chemins : fichiers, traversée, exécution de code à proximité](#uploads)
* [SSRF : quand votre serveur récupère « le mauvais emplacement »](#ssrf)
* [Désérialisation non sécurisée et usurpation d'objets](#deserialization)
* [Défenses du navigateur : clickjacking, CORS, en-têtes](#browser)
* [DoS et abus : limites de débit, force brute, traitements coûteux](#dos)
* [Erreurs courantes](#common-mistakes)
* [Liste de contrôle de publication](#checklist)
* [Quiz d'auto-évaluation](#self-test-quiz)

---

<a id="threat-model"></a>
## Modélisation des menaces : ce que vous protégez et contre qui

Avant d'écrire une logique de sécurité, vous devez définir les paramètres de la menace :

- **Attaquant** : utilisateur anonyme, utilisateur connecté, partenaire disposant d'une clé d'API, personne au sein du VPN, acteur ayant un accès CI/CD.
- **Actifs** : argent, données personnelles (PII), comptes, panneaux d'administration, intégrations, secrets, accès au réseau interne.
- **Surface d'attaque** : formulaires et API, redirections, webhooks, importations/exportations, téléversements de fichiers, intégrations tierces (S3, SMTP, paiements).

> [!NOTE]
> **Règle de sécurité pragmatique**
> Partez toujours du principe que toutes les entrées sont malveillantes. Cela inclut les en-têtes, les cookies, les charges utiles des webhooks, les paramètres d'URL et les messages lus à partir des files d'attente.

---

<a id="injection"></a>
## Injections : SQLi, injection de commandes, injection de modèles

### Injection SQL (SQLi)

L'injection SQL commence généralement par « juste un fragment de SQL brut ». La correction n'est pas « l'échappement », mais la **paramétrisation**.

**Mauvais** (concaténation de chaînes) :
```php
// app/Http/Controllers/UserController.php
$rows = DB::select("SELECT * FROM users WHERE email = '{$email}'");
```

**Meilleur** (placeholders) :
```php
// app/Http/Controllers/UserController.php
$rows = DB::select('SELECT * FROM users WHERE email = ?', [$email]);
```

Un autre piège courant concerne **les noms de colonnes / tris dynamiques**. Les paramètres ne s'appliquent pas aux identificateurs SQL, vous devez donc utiliser une liste d'autorisation stricte :
```php
// app/Http/Controllers/UserController.php
$allowed = ['created_at', 'email', 'id'];
$sort = in_array($request->get('sort'), $allowed, true) ? $request->get('sort') : 'created_at';

$users = User::query()->orderBy($sort, 'desc')->paginate();
```

### Injection de commandes

Si vous appelez le shell, la règle est : **ne passez jamais d'entrée utilisateur dans une ligne de commande**. Même `escapeshellarg()` est un garde-fou de dernier recours, pas une conception sécurisée.

Si vous devez exécuter des outils en ligne de commande (convertir des fichiers, générer des aperçus) :
- Préférez une bibliothèque à `exec()`.
- Si la CLI est inévitable, figez les exécutables/arguments/dossiers et exécutez-les dans un environnement restreint (conteneur/file d'attente/worker sous un utilisateur dédié).

### Injection de modèles (Template injection)

Le cas dangereux est de laisser les utilisateurs fournir des modèles qui sont exécutés en tant que Blade/Twig/Smarty.
- **Règle** : Les modèles utilisateur doivent être traités comme des données, idéalement rendus via un format limité et non complet au sens de Turing (par exemple, du Markdown avec une liste d'autorisation stricte et un rendu sécurisé).

---

<a id="xss"></a>
## XSS : réfléchi, stocké, basé sur le DOM

Le XSS consiste à exécuter du JavaScript dans votre origine. Sources typiques :
- Sortie brute (`{!! ... !!}` dans Blade, `innerHTML` sur le frontend).
- Champs de texte enrichi stockés sans désinfection.
- Injection de valeurs dans des balises `<script>` ou des attributs HTML sans encodage approprié.

### Atténuations principales

- **Échapper par défaut** : dans Blade, utilisez `{{ $value }}` ; évitez les sorties brutes à moins d'avoir une justification solide.
- **Respecter les contextes** : HTML vs attributs vs chaînes JS vs URL — différentes règles d'encodage s'appliquent.
- **Désinfecter les entrées HTML** : si vous autorisez le formatage, utilisez une liste d'autorisation (balises/attributs) et supprimez les gestionnaires `on*`, les URL `javascript:`, etc.
- **CSP** : pas un correctif logique, mais un limiteur de dégâts majeur.

CSP de départ (idéalement avec des nonces et sans `'unsafe-inline'`) :
```http
# /etc/nginx/nginx.conf
Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'
```

---

<a id="csrf"></a>
## CSRF : pourquoi « mais c'est du GET » n'est pas une défense

La faille CSRF se produit lorsque le navigateur d'un utilisateur envoie une requête à votre site avec ses cookies/session parce qu'un autre site la déclenche (formulaires, images, JS).

### Atténuations

- **Jetons CSRF** pour les requêtes modifiant l'état (POST/PUT/PATCH/DELETE). Laravel le fait nativement.
- **Cookies `SameSite`** : au moins `Lax` ; envisagez `Strict` pour les flux sensibles ; pour les cas intersites, utilisez `None; Secure`.
- **Vérifications de l'origine (Origin/Referer)** pour les actions particulièrement sensibles (changement d'e-mail, paiements).
- **Ne modifiez jamais d'état via une requête GET**.

---

<a id="auth-sessions"></a>
## Authentification et sessions : vol de jetons, fixation, cookies

Ce qui casse généralement :
- Mots de passe faibles + absence de limite de débit → credential stuffing (bourrage d'identifiants).
- Vol de session via XSS ; fixation de session ; absence d'invalidation.
- Jetons à longue durée de vie sans rotation et sans liaison à l'appareil.

### Contrôles de base

- **Limiter le débit** des connexions/OTP/réinitialisations de mot de passe.
- **Hacher les mots de passe** avec `password_hash` en utilisant bcrypt ou argon2. Pas de cryptographie personnalisée.
- Cookies de session : `HttpOnly`, `Secure`, `SameSite`, maintenir la durée de vie courte.
- **Changer les identifiants de session** après la connexion et les modifications de privilèges.

---

<a id="idor"></a>
## Contrôle d'accès et IDOR : « c'est juste un identifiant »

L'IDOR (Insecure Direct Object Reference) se produit lorsqu'un utilisateur peut deviner/itérer des identifiants et lire ou modifier les données de quelqu'un d'autre.

Causes communes :
- Vérifier « connecté » au lieu de la propriété/du rôle.
- L'autorisation existe dans l'interface utilisateur mais pas sur l'API.
- Comportement « Admin » contrôlé par un paramètre de requête.

### Atténuations

- **Politiques/portes (policies/gates)** pour chaque ressource et action.
- Pour les applications multi-locataires (multi-tenant), filtrez toujours par `tenant_id`/`account_id` au niveau de la requête SQL.
- Ne comptez pas sur le fait de « masquer » pour assurer la sécurité.

```php
// app/Http/Controllers/OrderController.php
// Préférer limiter la requête à l'utilisateur :
$order = auth()->user()->orders()->findOrFail($id);

// Plutôt qu'une recherche directe :
$order = Order::findOrFail($id);
```

---

<a id="uploads"></a>
## Téléversements et chemins : fichiers, traversée, exécution de code à proximité

Les téléversements sont un domaine classique de type « exécution de code à distance (RCE) à portée de main ».

### Ce qu'il faut appliquer

- Validez le **contenu**, pas seulement l'extension ; le type MIME envoyé par le navigateur n'est pas fiable.
- Limites sur la taille et le nombre de fichiers.
- Stockez en dehors de la racine web ; servez via un contrôleur ou un domaine/CDN dédié.
- Noms de fichiers aléatoires (ne faites pas confiance aux noms d'origine pour construire des chemins).
- Désactivez l'exécution dans les répertoires de téléversement au niveau du serveur web.

```nginx
# /etc/nginx/conf.d/uploads.conf
location /uploads {
    location ~ \.php$ {
        deny all;
    }
}
```

La traversée de chemin (path traversal) apparaît souvent sous la forme « télécharger le fichier par son nom » :
- Rejetez les séquences `../`, `\`, et les octets nuls.
- Utilisez une liste d'autorisation de fichiers/identifiants réels, pas un chemin provenant de la requête.

---

<a id="ssrf"></a>
## SSRF : quand votre serveur récupère « le mauvais emplacement »

La faille SSRF apparaît lorsque vous acceptez une URL utilisateur et **la récupérez depuis le serveur** (importation d'avatar, testeur de webhook, aperçu de lien, générateur de PDF).

Risques : accès à des **services internes** (points de terminaison de métadonnées cloud, Redis/Consul, panneaux d'administration), contournement des frontières réseau.

### Atténuations

- **Liste d'autorisation (allowlist) d'hôtes/domaines**, pas de liste noire.
- Bloquez les plages d'adresses IP privées (127.0.0.0/8, 10.0.0.0/8, 169.254.0.0/16, 172.16.0.0/12, 192.168.0.0/16, ::1, fc00::/7).
- Désactivez les redirections ou re-validez chaque saut.
- Délais d'attente (timeouts), limites de taille de réponse, restrictions de protocole (https uniquement).

---

<a id="deserialization"></a>
## Désérialisation non sécurisée et usurpation d'objets

Si vous désérialisez des données contrôlées par l'attaquant (cookies, champs cachés, charges utiles de files d'attente externes, caches non signés), vous risquez :
- L'usurpation de rôle/champ.
- Des chaînes de gadgets (gadget chains) et des exécutions de code là où c'est applicable.

> [!NOTE]
> **Intégrité des données**
> Ne stockez jamais d'objets PHP/JS sérialisés dans des endroits non fiables. Pour les jetons, utilisez des structures signées comme JWT ou HMAC. Pour les charges utiles structurées, utilisez JSON + une validation stricte du schéma.

---

<a id="browser"></a>
## Défenses du navigateur : clickjacking, CORS, en-têtes

### Clickjacking

Bloquer l'intégration dans des cadres (framing) :
```http
# /etc/nginx/nginx.conf
X-Frame-Options: DENY
Content-Security-Policy: frame-ancestors 'none'
```

### CORS

Le mécanisme CORS ne bloque pas les requêtes — c'est une règle de navigateur pour la lecture des réponses. Erreurs courantes :
- `Access-Control-Allow-Origin: *` avec des cookies/identifiants.
- Méthodes/en-têtes autorisés trop larges.
- Faire confiance à l'en-tête `Origin` sans liste stricte.

### En-têtes de sécurité de base
```http
# /etc/nginx/nginx.conf
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

---

<a id="dos"></a>
## DoS et abus : limites de débit, force brute, traitements coûteux

Dans les applications métiers, le « mini-DoS » le plus courant n'est pas la bande passante — ce sont les points de terminaison peu coûteux à appeler pour eux, mais coûteux à traiter pour vous :
- Recherches non indexées, exports géants, génération de PDF.
- Absence de limites de débit (connexion, envoi d'e-mail/OTP, « vérification du code promo »).
- Validations/expressions régulières coûteuses sur d'immenses chaînes.

### Contrôles
- Limitation du débit par IP + compte + clé d'API.
- Mise en file d'attente des travaux coûteux.
- Délais d'attente (timeouts), limites de taille, limites de profondeur JSON.
- Mise en cache des réponses publiques lourdes.

---

<a id="common-mistakes"></a>
## Erreurs courantes

1. **Se fier à la validation côté client** : Implémenter des validations sur les formulaires du frontend mais les négliger sur le backend, permettant aux utilisateurs d'envoyer des charges utiles de contournement directement aux points de terminaison de l'API.
2. **SQL brut pour les paramètres de tri / Order by** : Les espaces réservés (placeholders) ne pouvant pas représenter des noms de colonnes, les développeurs concatènent souvent les entrées utilisateur dans les instructions `ORDER BY`, ouvrant ainsi des vulnérabilités de type SQLi.
3. **Mettre sur liste noire des IP privées dans le cas d'une faille SSRF** : Tenter de bloquer `127.0.0.1` et `10.0.0.1` mais oublier les encodages hexadécimaux (`0x7f.0.0.1`), les représentations octales ou les attaques par rebond DNS (DNS rebinding).
4. **Sérialisation PHP incorrecte** : Stocker des objets PHP sérialisés dans des cookies, en pensant que c'est sûr parce que les données sont « opaques », ce qui permet l'injection d'objets PHP.

---

<a id="checklist"></a>
## Liste de contrôle de publication

### Entrées & données
- [ ] Valider à la frontière (formulaires/API/webhooks), normaliser les types.
- [ ] Liste d'autorisation pour le tri/filtrage/champs qui deviennent des identificateurs SQL.
- [ ] Pas de changement d'état via GET.

### Rendu & navigateur
- [ ] Échapper par défaut, pas de HTML brut injustifié.
- [ ] CSP/`frame-ancestors`, protection contre le clickjacking.
- [ ] HSTS, `nosniff`, `Referrer-Policy` sensée.

### Accès
- [ ] Autorisation côté serveur pour chaque action (policy/gate), pas seulement sur l'interface utilisateur.
- [ ] Pas d'IDOR : requêtes limitées au propriétaire/locataire.
- [ ] Journaux d'audit pour les actions sensibles.

### Intégrations
- [ ] Contrôles SSRF : liste d'autorisation, blocs d'adresses IP privées, délais d'attente/limites.
- [ ] Webhooks : vérification de la signature, protection contre le rejeu, idempotence.

### Téléversements
- [ ] Vérifications du contenu/taille, stockage hors de la racine web, désactivation de l'exécution.
- [ ] Noms aléatoires, pas de chemins fournis par l'utilisateur.

---

## Résumé

La sécurité n'est pas un produit final ; c'est un ensemble d'invariants aux frontières du système. Validez l'entrée une fois à la frontière, échappez la sortie dans les modèles, appliquez l'autorisation sur le serveur, signez les intégrations externes et limitez le débit de tout traitement coûteux.

---

<a id="self-test-quiz"></a>
## Quiz d'auto-évaluation

### Question 1 : Quel est le principal problème de sécurité avec le code suivant ?
```php
// app/Http/Controllers/DownloadController.php
return response()->download(storage_path('reports/' . $request->get('file')));
```
- A) Injection SQL.
- B) Script intersite (XSS).
- C) Traversée de chemin (téléchargement de fichier arbitraire).

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse : C**
Comme l'entrée `$request->get('file')` est concaténée directement dans le chemin sans vérifier la présence de `../`, un attaquant peut passer `file=../../.env` pour lire les secrets de configuration sensibles.
</details>

---

### Question 2 : Pourquoi les jetons CSRF sont-ils requis pour les requêtes POST si le cookie de session est déjà marqué comme `SameSite=Lax` ?
- A) `SameSite=Lax` n'empêche pas les requêtes POST déclenchées par des navigations de premier niveau ou des scripts sur les navigateurs plus anciens.
- B) Les jetons CSRF empêchent l'injection SQL.
- C) Sans jeton CSRF, le cookie de session ne peut pas être lu.

<details>
<summary><b>Afficher les réponses</b></summary>

**Réponse : A**
Les attributs SameSite sont une défense utile, mais ils n'offrent pas une couverture de sécurité à 100 % en raison de problèmes de compatibilité avec les navigateurs, de vulnérabilités par prise de contrôle de sous-domaines ou de modifications d'état basées sur les requêtes GET.
</details>
