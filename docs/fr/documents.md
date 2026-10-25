# Documents téléchargeables — 1.2.0

## Utiliser les pièces jointes

1. Ouvrez **Médias**, choisissez un PDF, DOCX, ZIP ou CSV et importez-le.
2. Un fichier destiné aux visiteurs doit être **public**. Un document privé reste réservé au propriétaire, même lorsqu’un lien est placé dans un article public.
3. Ouvrez une page ou un article. Sous le texte, sélectionnez le document dans **Joindre un document public**.
4. Cliquez sur **Insérer le téléchargement**, puis **Enregistrer**. Le lien est inséré dans la langue active.
5. Vérifiez le téléchargement dans une fenêtre privée.

Le CMS conserve les octets d’origine et l’extension. Le stockage utilise un identifiant interne, par exemple `storage/media/<id>.docx`. Le nom d’envoi reste proposé au téléchargement. Les fichiers passent par `media.php`.

## Contrôles appliqués

| Format | Validation | Réponse HTTP |
| --- | --- | --- |
| PDF | Extension, MIME réel `application/pdf` et signature `%PDF-` | Téléchargement forcé |
| DOCX | MIME OOXML ou ZIP reconnu, structure OOXML contrôlée, XML sans DTD/entités, relations internes existantes | Téléchargement forcé |
| ZIP de documents | Signature, structure, CRC, chemins, doublons, types d’entrées, limites ; aucune extraction serveur | Téléchargement forcé |
| CSV | MIME texte/CSV reconnu, UTF-8 avec BOM facultatif, sans octets binaires ni balisage actif identifié | Téléchargement forcé |
| JPG/JPEG, PNG, WebP | Extension/MIME, dimensions et décodage GD | Image, ou téléchargement demandé |

Le type annoncé par le navigateur n’est pas une preuve. `Media::metadata()` contrôle les octets avec `fileinfo` et les validateurs du format. L’envoi, l’import WordPress et la restauration partagent ces contrôles. L’adaptation des anciens noms est réservée aux sauvegardes/médias 1.0.

Les documents sont servis avec `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff` et une CSP `sandbox; default-src 'none'`. Aucun document n’est inclus comme du PHP ou exécuté.

## Politique et limites

- **8 Mio par fichier** ; originaux conservés. Images : 8 mégapixels et 6 000 px maximum.
- DOCX/ZIP : 200 entrées, 32 Mio décompressés, 8 Mio par entrée ; refus des taux de compression extrêmes, liens, fichiers spéciaux, archives chiffrées et CRC incorrects.
- ZIP de documents : uniquement JPG/JPEG/PNG/WebP/PDF/CSV/TXT/MD ; aucun programme, fichier caché ou ZIP/DOCX imbriqué. Les modules utilisent leur installateur distinct.
- DOCX : macros, ActiveX, OLE, fichiers binaires incorporés, `altChunk`, champs DDE/liaisons externes et relations externes refusés. Chaque partie XML est limitée à 1 Mio. Cette politique peut refuser des DOCX légitimes avec liens externes, polices incorporées ou formats d’images non admis. Retirez ces éléments ou exportez en PDF.
- CSV : UTF-8, 100 000 lignes et 65 536 octets par ligne. Les formules ne sont pas réécrites : soyez prudent en ouvrant un CSV non fiable dans un tableur.
- Aucun antivirus, désarmement PDF ou certificat d’innocuité n’est fourni. Les contrôles concernent notamment l’exécution serveur, l’accès et la réponse HTTP.
- Les originaux peuvent contenir des métadonnées personnelles, notamment EXIF.

## Déploiement et sauvegarde

Conservez `.htaccess` et `storage/.htaccess`. Après la première visite, l’accès direct à `storage/cms.sqlite`, aux journaux et à un média existant doit retourner 403/404 sans exposer leurs octets. Nginx doit appliquer les règles de `docs/nginx.conf`. `VV_STORAGE_PATH` permet un stockage hors racine Web selon l’hébergement.

Les sauvegardes incluent ces documents sous leur extension d’origine, ainsi que les brouillons et notes : gardez-les privées. Restaurez les nouveaux formats avec **1.2.0 ou ultérieur**. Un retour intégral à une ancienne version demande sa copie cohérente du stockage complet.

[Commentaires](commentaires.md) · [WordPress](wordpress.md) · [Sécurité](securite.md)
