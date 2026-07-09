---
title: "Vulnérabilités d'upload de fichiers : Contourner les filtres vers l'exécution de code à distance (RCE) | DevSense"
description: "Maîtrisez les mécanismes de téléchargement de fichiers sécurisé. Découvrez comment les attaquants contournent les vérifications côté client, exploitent les listes noires d'extensions, écrasent les configurations du serveur web, et comment écrire un code Laravel sécurisé."
published: 2026-07-09
faq:
  - question: "Pourquoi une liste blanche d'extensions est-elle plus sûre qu'une liste noire ?"
    answer: "Une liste noire tente de bloquer les extensions dangereuses connues (ex. .php, .exe) mais oublie souvent des extensions exécutables alternatives (ex. .phtml, .phar, .php5) ou des astuces de nommage propres au système d'exploitation. Une liste blanche définit strictement les formats autorisés et sûrs (ex. .jpg, .pdf) et rejette par défaut tout le reste."
  - question: "Comment fonctionne le contournement des flux de données alternatifs (ADS) NTFS sous Windows ?"
    answer: "Sur les systèmes Windows, ajouter '::$DATA' à la fin d'un nom de fichier (comme 'shell.php::$DATA') indique à NTFS d'écrire les données dans le flux par défaut de 'shell.php'. Lors de la sauvegarde, Windows supprime le suffixe '::$DATA', laissant un fichier 'shell.php' entièrement exécutable sur le disque, contournant ainsi les filtres naïfs de liste noire."
  - question: "Comment l'écrasement d'un fichier .user.ini mène-t-il à l'exécution de code ?"
    answer: "Dans les environnements PHP CGI/FastCGI, téléverser un fichier '.user.ini' personnalisé dans un répertoire d'upload permet à un attaquant de redéfinir les directives de configuration PHP pour ce répertoire. En définissant 'auto_prepend_file=image.png', le moteur PHP exécute automatiquement le code PHP intégré dans 'image.png' dès qu'un script PHP de ce répertoire est accédé."
---

# Vulnérabilités d'upload de fichiers : Contourner les filtres vers l'exécution de code à distance (RCE)

Les formulaires d'upload de fichiers font partie des fonctionnalités les plus exposées dans les applications web. Lorsqu'une application permet aux utilisateurs de téléverser des fichiers, elle ouvre une passerelle directe vers le système de fichiers du serveur. Si le backend ne valide pas correctement les fichiers téléversés, un attaquant peut envoyer un shell web, contourner les restrictions d'exécution et obtenir une **exécution de code à distance (RCE)**.

Dans ce guide, nous analyserons les mécanismes techniques des uploads de fichiers, les techniques de contournement et la manière de concevoir un pipeline de validation de fichiers robuste en PHP et Laravel.

---

## Sommaire

* [Mécanismes du format Multipart Form-Data](#multipart-mechanics)
* [Contournement de la validation côté client](#client-side-bypass)
* [Contournement du filtrage par extension](#extension-bypasses)
* [Surcharge de configuration du serveur Web](#config-overrides)
* [Normalisation des noms de fichiers sous Windows](#windows-normalization)
* [Implémentation sécurisée en Laravel & PHP](#secure-implementation)
* [Pratiques courantes de durcissement du serveur](#server-hardening)
* [Liste de contrôle de sécurité](#checklist)

---

<a id="multipart-mechanics"></a>
## Mécanismes du format Multipart Form-Data

Lors du téléversement d'un fichier via HTTP, les navigateurs utilisent le schéma d'encodage `multipart/form-data`, défini dans la spécification **RFC 7578**. Il est essentiel de comprendre cette structure pour appréhender les divergences d'analyse des parsers.

### Structure d'une requête Multipart
Une requête typique d'upload de fichier ressemble à ceci :

```http
POST /upload.php HTTP/1.1
Host: example.com
Content-Type: multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Length: 345

------WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Disposition: form-data; name="avatar"; filename="backdoor.php"
Content-Type: application/x-php

<?php system($_GET['cmd']); ?>
------WebKitFormBoundary7MA4YWxkTrZu0gW--
```

### Éléments critiques du format Multipart :
1. **Paramètre Boundary :** L'attribut `boundary` dans l'en-tête `Content-Type` définit la chaîne de caractères unique utilisée pour séparer les champs du formulaire.
2. **Doubles tirets :** Dans le corps de la requête, chaque délimiteur (boundary) est préfixé par deux tirets (`--`). Le délimiteur final marquant la fin de la charge utile doit également se terminer par deux tirets (`--boundary--`).
3. **Divergences de parsing :** Les différents langages de programmation et serveurs d'applications n'analysent pas les données multipart de la même manière.
   - Certains parsers tolèrent l'absence d'en-têtes ou des tirets mal formés.
   - Par exemple, une vulnérabilité a été découverte dans la bibliothèque d'analyse multipart Node.js populaire **Multer**, où l'omission des doubles tirets finaux (`--`) entraînait un blocage indéfini du parser en attente de données supplémentaires, provoquant un déni de service (DoS).

---

<a id="client-side-bypass"></a>
## Contournement de la validation côté client

De nombreux développeurs implémentent la validation des fichiers exclusivement en JavaScript côté client afin de fournir un retour immédiat à l'utilisateur. Ces vérifications restrictives ressemblent souvent à ceci :

```html
<!-- Restriction côté client -->
<input type="file" id="avatar" accept=".jpg, .png" onchange="validateFile()">
```

### Le mécanisme de contournement
Les vérifications côté client ne sont absolument pas fiables car le client est sous le contrôle total de l'attaquant. Un attaquant peut les contourner de plusieurs manières :
1. **Désactiver le JavaScript :** En utilisant les outils de développement du navigateur pour bloquer complètement l'exécution du JS, ce qui permet de soumettre n'importe quel fichier.
2. **Proxy d'interception (ex. Burp Suite) :**
   - Sélectionnez un fichier légitime (ex. `profile.png`).
   - Envoyez le formulaire.
   - Interceptez la requête sortante dans Burp Suite.
   - Modifiez le paramètre filename en `shell.php`, définissez le `Content-Type` à `application/x-php`, et remplacez le contenu binaire de l'image par le code PHP : `<?php phpinfo(); ?>`.

> [!WARNING]
> Ne comptez jamais sur la validation côté client pour la sécurité. Les vérifications côté client ne servent qu'au confort de l'utilisateur. La sécurité doit impérativement être appliquée sur le serveur backend.

---

<a id="extension-bypasses"></a>
## Contournement du filtrage par extension

Lorsque les développeurs configurent des vérifications côté serveur, ils se basent souvent sur le filtrage des extensions. Cela peut se faire via une **liste noire** (bloquer les extensions dangereuses) ou une **liste blanche** (autoriser uniquement certaines extensions).

### Contournement des listes noires
Les listes noires sont intrinsèquement faibles. Un attaquant peut contourner une liste noire simple bloquant `.php` via plusieurs stratégies :

1. **Extensions exécutables alternatives :**
   Selon la configuration du serveur web, des extensions alternatives compatibles avec PHP peuvent être exécutées :
   - `.phtml`, `.php3`, `.php4`, `.php5`, `.php7`, `.phps`
   - `.phar` (archive PHP, qui peut également déclencher des vecteurs de désérialisation)
   - `.pht`

2. **Exploitation de la casse :**
   Si la logique de filtrage vérifie uniquement `.php` mais que l'environnement serveur traite les fichiers sans tenir compte de la casse, les développeurs peuvent omettre :
   - `.pHp`, `.Php`, `.PHp`, `.pHTML`

3. **Extensions doubles et imbriquées :**
   - **Double extension :** Si le serveur est mal configuré et exécute les fichiers contenant `.php` n'importe où dans leur nom (ex. via `AddHandler` sur Apache), un attaquant peut envoyer `shell.php.jpg`.
   - **Contournement de la suppression :** Si le code tente de nettoyer l'extension `.php` de manière non récursive :
     `shell.p.phphp.hp` -> Supprimer `php` une seule fois produira `shell.php`.

---

<a id="config-overrides"></a>
## Surcharge de configuration du serveur Web

Si une application applique une liste noire stricte mais autorise l'envoi de fichiers de configuration, un attaquant peut modifier les règles d'exécution du serveur pour le répertoire d'upload.

### 1. Surcharge de configuration Apache (`.htaccess`)
Si Apache est configuré avec `AllowOverride All` pour le répertoire de stockage, un attaquant peut téléverser un fichier `.htaccess` personnalisé :

```apache
# Forcer l'exécution des fichiers PNG en tant que PHP dans .htaccess
AddType application/x-httpd-php .png
```
Ou :
```apache
<Files "logo.png">
    ForceType application/x-httpd-php
</Files>
```

Après avoir téléversé ce fichier `.htaccess`, l'attaquant envoie un fichier nommé `logo.png` contenant du code PHP. Apache traitera alors `logo.png` comme un script PHP et l'exécutera lors de son accès.

### 2. Surcharge PHP CGI/FastCGI (`.user.ini`)
Dans les configurations Nginx ou IIS utilisant PHP-FPM ou PHP CGI, les fichiers `.htaccess` ne sont pas pris en compte. Cependant, PHP prend en charge des fichiers de configuration spécifiques au répertoire appelés `.user.ini`.

Un attaquant peut envoyer un fichier `.user.ini` contenant :

```ini
# Exécuter le code PHP intégré dans un fichier PNG
auto_prepend_file=avatar.png
```

Si l'attaquant téléverse ensuite `avatar.png` (contenant la charge utile) et accède à n'importe quel fichier `.php` légitime et existant dans ce répertoire (même un script d'index vide), le moteur PHP exécutera d'abord le contenu de `avatar.png`.

---

<a id="windows-normalization"></a>
## Normalisation des noms de fichiers sous Windows

Lorsque les applications s'exécutent sur un serveur web Windows (IIS ou Apache sur Windows), la création de fichiers est soumise aux règles de normalisation de l'API Win32 et du système NTFS. Cela génère des contournements uniques.

### 1. Points et espaces de fin
Windows supprime automatiquement les points et les espaces situés à la fin des noms de fichiers lors de leur écriture sur le disque.
- Si l'application valide les extensions avec une liste noire (ex. bloquant `.php`), un attaquant peut envoyer un fichier nommé `shell.php.` ou `shell.php `.
- La validation regex vérifie `shell.php.` (qui ne correspond pas à `.php`) et autorise le transfert.
- Le système de fichiers Win32 crée le fichier sur le disque et normalise son nom en `shell.php`, le rendant ainsi exécutable.

### 2. Flux de données alternatifs NTFS (ADS)
NTFS utilise des flux de données alternatifs (ADS) pour stocker les métadonnées. Le flux de données par défaut est désigné par `::$DATA`.
- Un attaquant téléverse un fichier nommé `shell.php::$DATA`.
- La logique de liste noire interprète l'extension comme `.php::$DATA` (ou l'autorise car elle recherche le point final).
- Lors de l'enregistrement du fichier, Windows extrait `shell.php` et écrit le contenu du fichier dans son flux de données principal. Le résultat est un fichier `shell.php` pleinement opérationnel dans le répertoire web.

---

<a id="secure-implementation"></a>
## Implémentation sécurisée en Laravel & PHP

Pour sécuriser les téléchargements de fichiers, vous devez suivre le principe de **défense en profondeur**. Ne vous appuyez pas sur une unique étape de validation.

### Contrôleur d'upload sécurisé dans Laravel

Voici comment implémenter un contrôleur sécurisé dans Laravel en utilisant des listes blanches strictes, la validation du type MIME et le stockage en dehors de la racine web publique :

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SecureUploadController extends Controller
{
    // 1. Liste blanche stricte des extensions autorisées et des types MIME correspondants
    private const ALLOWED_TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'pdf'  => 'application/pdf',
    ];

    public function store(Request $request): JsonResponse
    {
        // Vérifier si le fichier est présent
        if (!$request->hasFile('document')) {
            return response()->json(['error' => 'No file uploaded.'], 400);
        }

        $file = $request->file('document');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return response()->json(['error' => 'Invalid or corrupted file.'], 400);
        }

        // 2. Valider la taille du fichier (ex. Max 5Mo)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json(['error' => 'File size exceeds 5MB limit.'], 400);
        }

        // 3. Inspecter le contenu réel du fichier pour le type MIME (ne pas faire confiance aux en-têtes clients)
        $realMimeType = $file->getMimeType();
        $originalName = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // 4. Valider que l'extension et le type MIME correspondent à la liste blanche
        if (!array_key_exists($extension, self::ALLOWED_TYPES)) {
            return response()->json(['error' => 'Unsupported file extension.'], 400);
        }

        if (self::ALLOWED_TYPES[$extension] !== $realMimeType) {
            return response()->json(['error' => 'MIME type and file extension mismatch.'], 400);
        }

        // 5. Générer un nom de fichier totalement aléatoire (UUID ou Hash cryptographique)
        // Cela neutralise les attaques par traversée de répertoires, les astuces de normalisation Windows et les collisions de noms.
        $safeName = Str::uuid()->toString() . '.' . $extension;

        // 6. Stocker EN DEHORS de la racine web publique (ex. via un compartiment S3 privé ou un disque local privé)
        // Évitez d'utiliser public_path(). Utilisez le stockage privé local à la place.
        $path = $file->storeAs('uploads/secure_docs', $safeName, 'local');

        if ($path === false) {
            return response()->json(['error' => 'Failed to store file.'], 500);
        }

        return response()->json([
            'message'   => 'File uploaded securely.',
            'safe_name' => $safeName
        ], 201);
    }
}
```

---

<a id="server-hardening"></a>
## Pratiques courantes de durcissement du serveur

Même si le code de l'application présente des failles, le durcissement de l'infrastructure de votre serveur peut empêcher l'exécution de scripts.

### 1. Désactiver l'exécution de PHP dans les répertoires d'upload
Pour Nginx, configurez le bloc serveur pour interdire l'exécution de scripts PHP dans le répertoire de destination :

```nginx
# Désactiver l'exécution PHP dans le répertoire d'upload
location ~* ^/uploads/.*\.php$ {
    deny all;
    return 403;
}
```

Pour Apache, ajoutez ce bloc dans la configuration de votre répertoire pour bloquer l'exécution de scripts :

```apache
<Directory "/var/www/html/uploads">
    # Désactiver l'exécution des scripts
    RemoveHandler .php .phtml .php3
    RemoveType .php .phtml .php3
    
    # Ou forcer le traitement en tant que texte brut
    ForceType text/plain
    
    # Désactiver les surcharges htaccess dans ce dossier
    AllowOverride None
</Directory>
```

### 2. Exécuter le serveur Web avec un compte de privilèges minimaux
Assurez-vous que le serveur Web (ex. `www-data`, `nginx`) dispose des droits de lecture et d'écriture *uniquement* dans les répertoires d'upload désignés, sans aucune permission d'écriture sur les autres répertoires système ou les répertoires contenant le code source.

---

<a id="checklist"></a>
## Liste de contrôle de sécurité

| Niveau de sécurité | Points de contrôle |
| :--- | :--- |
| **Listes blanches** | Validez les extensions par rapport à une liste blanche stricte (ne bloquez jamais via une liste noire). |
| **Validation MIME** | Validez le type réel du fichier via des bibliothèques fiables (ex. php `fileinfo`). |
| **Renommage** | Générez des noms aléatoires (UUID ou hash) lors du téléversement. |
| **Lieu de stockage** | Enregistrez les fichiers téléversés en dehors de la racine web publique de l'application. |
| **Config Serveur** | Bloquez les surcharges de configuration de fichiers (`.htaccess`, `.user.ini`) dans ces dossiers. |
| **Blocage d'exécution** | Désactivez explicitement le moteur d'exécution de scripts dans les répertoires d'upload. |
