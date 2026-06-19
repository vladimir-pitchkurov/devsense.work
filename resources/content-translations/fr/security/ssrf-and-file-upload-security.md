---
title: "SSRF & transferts de fichiers sécurisés : Prévenir le Server-Side Request Forgery et l'exécution de code à distance (RCE) | DevSense"
description: "Sécurisez votre infrastructure backend. Apprenez à prévenir le Server-Side Request Forgery (SSRF) et les transferts de fichiers malveillants menant à l'exécution de code à distance (RCE)."
published: 2026-06-19
faq:
  - question: "Pourquoi la résolution de domaine est-elle essentielle pour prévenir le SSRF ?"
    answer: "Les attaquants peuvent contourner les filtres de nom d'hôte (hostname) à l'aide de techniques de DNS Rebinding ou de redirection. Pour éviter cela, vous devez résoudre le domaine en son adresse IP, vérifier que l'IP n'appartient pas à des sous-réseaux locaux (loopback) ou privés, puis effectuer la requête HTTP directement vers cette adresse IP validée."
  - question: "Comment un attaquant exploite-t-il des transferts de fichiers non sécurisés pour obtenir une RCE ?"
    answer: "Si l'application stocke les fichiers transférés dans un dossier publiquement accessible (racine web) et conserve leur extension PHP d'origine (par exemple, `shell.php`), l'attaquant peut exécuter le fichier directement en accédant à son URL, déclenchant ainsi l'exécution de code arbitraire sur le serveur."
  - question: "Qu'est-ce qu'une attaque XSS par SVG ?"
    answer: "Les fichiers SVG sont des documents XML qui peuvent intégrer du HTML et du JavaScript en ligne. Si une application autorise les utilisateurs à charger des fichiers SVG et les affiche directement dans le navigateur, tout code JavaScript intégré dans le SVG s'exécutera sous le domaine de l'application, entraînant un XSS stocké (Stored XSS)."
---

# SSRF & transferts de fichiers sécurisés : Prévenir le Server-Side Request Forgery et l'exécution de code à distance (RCE)

Lorsque les applications backend se connectent à des API externes et permettent aux utilisateurs de charger des médias, elles exposent des voies de communication directes avec les serveurs internes et le système de fichiers. Ne pas sécuriser ces limites conduit au Server-Side Request Forgery (SSRF) et à l'exécution de code à distance (RCE).

Dans ce guide, nous analyserons comment les vulnérabilités SSRF et de transfert de fichiers se produisent en PHP et Laravel, et nous construirons des filtres robustes pour les sécuriser.

**Guides connexes :** [Web Application Vulnerabilities & Mitigations](web-app-security) · [Observability & monitoring](observability-monitoring-laravel)

## Sommaire

* [Server-Side Request Forgery (SSRF)](#ssrf)
* [Atténuation du SSRF en PHP](#ssrf-mitigation)
* [Vulnérabilités de transfert de fichiers](#file-upload-vulnerabilities)
* [Implémentation de transferts de fichiers sécurisés](#secure-file-upload)
* [Erreurs courantes](#common-mistakes)
* [Checklist](#checklist)
* [Résumé](#summary)
* [Quiz d'auto-évaluation](#self-test-quiz)

---

<a id="ssrf"></a>
## Server-Side Request Forgery (SSRF)

Le **Server-Side Request Forgery (SSRF)** se produit lorsqu'une application récupère une URL distante fournie par l'utilisateur sans valider la destination cible. 

Comme la requête provient du serveur backend, l'attaquant peut utiliser le serveur comme proxy pour :
- Scanner les réseaux internes (par exemple `http://10.0.0.5:80`).
- Accéder à des microservices internes qui ne disposent pas d'authentification (par exemple Redis sur `http://127.0.0.1:6379`).
- Accéder aux points de terminaison de métadonnées cloud (par exemple `http://169.254.169.254/latest/meta-data/` sur AWS/OpenStack) pour récupérer des identifiants temporaires de sécurité IAM.

---

<a id="ssrf-mitigation"></a>
## Atténuation du SSRF en PHP

Pour prévenir le SSRF, nous devons valider à la fois le protocole (scheme) et l'adresse IP résolue afin de s'assurer qu'ils ne pointent pas vers des réseaux locaux (loopback) ou privés.

```php
// app/Security/SafeHttpClient.php
declare(strict_types=1);

namespace App\Security;

use InvalidArgumentException;
use RuntimeException;

class SafeHttpClient
{
    public function fetch(string $url): string
    {
        $parsedUrl = parse_url($url);
        
        // 1. Restrict scheme to HTTP/HTTPS only
        $scheme = $parsedUrl['scheme'] ?? null;
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("Invalid URL scheme. Only HTTP and HTTPS are allowed.");
        }

        $host = $parsedUrl['host'] ?? null;
        if ($host === null) {
            throw new InvalidArgumentException("Invalid URL structure.");
        }

        // 2. Resolve hostname to IP address
        $ip = gethostbyname($host);
        if ($ip === $host) {
            throw new RuntimeException("Could not resolve host: {$host}");
        }

        // 3. Block private and loopback IP ranges
        if ($this->isPrivateIp($ip)) {
            throw new InvalidArgumentException("Access to internal IP range is restricted.");
        }

        // 4. Safe HTTP Request execution (using the resolved IP directly to prevent DNS rebinding)
        $port = $parsedUrl['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path = $parsedUrl['path'] ?? '/';
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$scheme}://{$ip}{$path}{$query}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Host: {$host}"]); // Restore Host header for routing
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $result = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new RuntimeException("HTTP Request failed: " . curl_error($ch));
        }
        
        curl_close($ch);
        return (string)$result;
    }

    private function isPrivateIp(string $ip): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return true; // Block invalid IP addresses
        }

        // Loopback: 127.0.0.0/8
        if (($ipLong & 0xFF000000) === 0x7F000000) {
            return true;
        }

        // Private IPv4 ranges (RFC 1918)
        // 10.0.0.0/8
        if (($ipLong & 0xFF000000) === 0x0A000000) {
            return true;
        }
        // 172.16.0.0/12
        if (($ipLong & 0xFFF00000) === 0xAC100000) {
            return true;
        }
        // 192.168.0.0/16
        if (($ipLong & 0xFFFF0000) === 0xC0A80000) {
            return true;
        }

        // Link-local: 169.254.0.0/16 (AWS / Cloud metadata)
        if (($ipLong & 0xFFFF0000) === 0xA9FE0000) {
            return true;
        }

        // Unspecified/Shared: 0.0.0.0/8, 100.64.0.0/10
        if (($ipLong & 0xFF000000) === 0x00000000) {
            return true;
        }

        return false;
    }
}
```

---

<a id="file-upload-vulnerabilities"></a>
## Vulnérabilités de transfert de fichiers

Autoriser les utilisateurs à charger des fichiers crée des risques de sécurité importants si le processus de transfert n'est pas sécurisé :
1. **Exécution de code à distance (RCE) :** L'attaquant téléverse un script PHP (par exemple `backdoor.php`), accède directement au chemin du fichier via le navigateur et exécute des commandes système.
2. **Traversée de répertoire (Directory Traversal) :** Utiliser un nom de fichier comme `../../index.php` pour écraser des fichiers de code de l'application.
3. **Usurpation de type MIME (MIME Spoofing) :** Modifier l'extension de fichier d'un script en `.jpg` ou `.png`, tout en conservant le contenu (payload) PHP interne.
4. **XSS par SVG :** Téléverser un fichier `.svg` contenant du code JavaScript en ligne malveillant. Lorsqu'il est affiché en ligne, le navigateur exécute le script dans le contexte d'origine de l'application.

---

<a id="secure-file-upload"></a>
## Implémentation de transferts de fichiers sécurisés

Pour sécuriser les transferts de fichiers dans Laravel, nous devons imposer des règles de validation strictes :
- Valider les fichiers à l'aide de leurs **types MIME** plutôt que de leurs extensions.
- Générer un **nom de fichier aléatoire** (par exemple, un UUID ou un Hash) pour éviter les traversées de répertoires et les conflits d'exécution.
- Stocker le fichier **en dehors de la racine web publique (public web root)** en utilisant un espace de stockage cloud isolé (comme Amazon S3 ou des dossiers privés).

```php
// app/Http/Controllers/UserMediaController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UserMediaController
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'application/pdf'
    ];

    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

    public function upload(Request $request): JsonResponse
    {
        $file = $request->file('avatar');

        if ($file === null || !$file->isValid()) {
            return response()->json(['error' => 'No valid file uploaded.'], 400);
        }

        // 1. Strict size check
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return response()->json(['error' => 'File size exceeds 5MB limit.'], 400);
        }

        // 2. Strict MIME type content analysis (bypasses extension spoofing)
        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            return response()->json(['error' => 'Invalid file format.'], 400);
        }

        // 3. Generate secure, random filename (prevents Directory Traversal)
        $extension = $file->getClientOriginalExtension();
        
        // Safety: Enforce safe extensions matching the MIME type
        $safeExtension = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => throw new InvalidArgumentException("Unsupported MIME type.")
        };

        $secureFilename = Str::uuid()->toString() . '.' . $safeExtension;

        // 4. Store outside the public web root (using private local or cloud storage disk)
        // This ensures the web server (Apache/Nginx) never executes the file
        $path = $file->storeAs('avatars/private', $secureFilename, 'local');

        return response()->json([
            'status' => 'success',
            'filename' => $secureFilename,
            'path' => $path
        ]);
    }
}
```

---

## Erreurs courantes

1. **Faire confiance à `getClientOriginalExtension` :** Utiliser directement les extensions d'origine fournies par l'utilisateur, ce qui permet à des attaquants de téléverser des fichiers nommés `script.php.png` ou `script.php`.
2. **Validation DNS faible :** Valider le nom d'hôte avec une expression régulière (regex) au lieu de résoudre son adresse IP, rendant l'application vulnérable au DNS Rebinding.
3. **Répertoires de téléversement publics :** Stocker les fichiers transférés dans `public/uploads` et autoriser l'exécution HTTP directe de scripts PHP dans ces dossiers.
4. **Autoriser le transfert de fichiers SVG comme images :** Autoriser les téléversements de SVG sans appliquer de filtres de nettoyage stricts, créant ainsi un vecteur de XSS stocké.

---

## Checklist

1. **Vérification de l'hôte :** Votre classe de récupération d'URL vérifie-t-elle l'adresse IP résolue, ou valide-t-elle uniquement la structure de la chaîne ?
2. **Isolation du stockage :** Vos téléversements sont-ils enregistrés dans un répertoire exécutable situé sous la racine web, ou sont-ils stockés en dehors du dossier public ou sur S3 ?
3. **Analyse du type MIME :** Vérifiez-vous le type de fichier à l'aide de `finfo` de PHP ou du `getMimeType()` de Laravel, ou lisez-vous simplement l'extension d'origine ?
4. **Nettoyage du nom de fichier :** Conservez-vous les noms de fichiers d'origine ou générez-vous des chaînes UUID ou de hachage aléatoires ?

---

## Résumé

Le SSRF et les transferts de fichiers non sécurisés font peser un risque de compromission au niveau du serveur. Atténuez le **SSRF** en limitant les protocoles à HTTP/HTTPS et en vérifiant que les adresses IP résolues ne pointent pas vers des réseaux privés (RFC 1918). Sécurisez les **téléversements de fichiers** en analysant le type MIME du contenu, en renommant les fichiers avec des hachages aléatoires et en les stockant en dehors de la racine web publique afin de prévenir toute exécution de code à distance.

---

## Quiz d'auto-évaluation

### Question 1 : Pourquoi est-il crucial de résoudre un nom d'hôte en son adresse IP avant de le valider pour prévenir le SSRF ?
- A) Parce que les adresses IP sont plus rapides à récupérer que les domaines.
- B) Pour prévenir les attaques de DNS Rebinding, où un attaquant modifie l'enregistrement de résolution DNS du domaine pour pointer vers une IP interne (comme `127.0.0.1`) une fois la validation passée.
- C) Parce que l'extension Curl de PHP ne prend pas en charge les domaines.

<details>
<summary>Cliquez pour voir la réponse</summary>

**Réponse : B**
Dans une attaque de DNS Rebinding, le domaine est d'abord résolu en une IP publique pour passer la validation de l'hôte. Lors de l'exécution, l'attaquant met à jour l'enregistrement DNS pour qu'il soit résolu en une IP interne (comme localhost ou des services de métadonnées). Résoudre l'adresse IP une seule fois et interroger directement cette IP supprime cette fenêtre de vulnérabilité.
</details>

### Question 2 : Quel est le risque de sauvegarder les téléversements des utilisateurs dans le répertoire `public` de Laravel sans désactiver l'exécution de PHP dans la configuration de Nginx/Apache ?
- A) Les fichiers seront automatiquement corrompus.
- B) Un attaquant peut téléverser un script `.php` malveillant et l'exécuter directement en chargeant son URL dans le navigateur, entraînant ainsi une exécution de code à distance (RCE).
- C) Cela déclenche des erreurs de verrouillage de la base de données.

<details>
<summary>Cliquez pour voir la réponse</summary>

**Réponse : B**
Par défaut, les serveurs web sont configurés pour analyser et exécuter tout fichier se terminant par `.php`. Si un fichier téléversé reste dans la racine web publique, n'importe qui peut y accéder directement, ce qui conduit le serveur web à exécuter le code.
</details>

### Question 3 : Quel attribut de fichier doit être utilisé pour valider le type de fichier de manière sécurisée ?
- A) Le type MIME du fichier déterminé par l'analyse de son contenu (via `finfo` ou l'extension fileinfo de PHP).
- B) L'extension de fichier obtenue avec `getClientOriginalExtension()`.
- C) La taille du fichier en octets.

<details>
<summary>Cliquez pour voir la réponse</summary>

**Réponse : A**
Les noms de fichiers et leurs extensions sont des en-têtes envoyés par le client qui peuvent être facilement usurpés. L'analyse des octets réels du fichier (signature du fichier/octets magiques) est le seul moyen sécurisé d'identifier son format réel.
</details>
