# VoilaVoila CMS 1.2.1 — mise à jour / upgrade

Préparé le 26 octobre 2026. PHP 8.3+, SQLite PDO, API native 1. Aucun changement de schéma SQLite.

## Français

### Ce qui change

- Retrait des onze copies de correctif sous `files/` ; ces chemins sont désormais aussi bloqués par HTTP.
- Archive complète avec les fichiers du CMS directement à la racine du ZIP, manifeste SHA-256 complet et identifiant du commit de fabrication.
- Vérification du ZIP avant extraction, puis vérification facultative des fichiers installés.
- Diagnostic d'hébergement dans **Administration → Outils** et en console avec `doctor`.
- Nettoyage des appels GD devenus obsolètes sous PHP 8.5 ; images et documents conservent leurs octets et extensions.
- CI étendue : archive partagée, trois versions PHP, mises à jour/retours arrière et véritable parcours Chromium en sous-dossier.

Les contenus, compte propriétaire, réglages, activation des extensions et révisions sont conservés. Les fichiers personnels de `storage/` ne font pas partie de la distribution.

### Nouvelle installation

1. Vérifier le ZIP et sa somme de contrôle. Sous macOS, dans le dossier contenant les deux fichiers :

```bash
shasum -a 256 -c VoilaVoila-CMS-1.2.1.sha256
```

2. Extraire le ZIP. Placer son contenu à la racine choisie pour le CMS : `index.php`, `core/`, `content/`, `assets/` et `.htaccess` se trouvent au même niveau. Afficher les fichiers cachés dans le client SFTP.
3. Choisir PHP 8.3 ou une version ultérieure prise en charge ; vérifier DOM, mbstring, fileinfo, PDO SQLite, GD JPEG/PNG/WebP et ZIP.
4. Activer HTTPS, envoyer les fichiers et autoriser l'écriture dans le stockage privé. Ne pas utiliser des permissions générales `777`.
5. Ouvrir `install.php`. Lire la clé générée dans `storage/setup-key.php` via SFTP ou le gestionnaire de fichiers privé, puis créer le propriétaire.
6. Conserver le code de récupération hors ligne. Contrôler l'hébergement depuis Outils.
7. En visiteur déconnecté, vérifier le refus HTTP 403/404 pour `storage/cms.sqlite`, `core/bootstrap.php` et `files/core/bootstrap.php`.

Un hébergement PHP suffit : Node, Python, Docker et Composer ne sont pas requis sur le serveur du site. Ils servent aux contrôles de développement.

### Mettre à jour un site existant

1. Préparer une copie de test et activer la maintenance. Sauvegarder le code et les données privées.
2. Obtenir une copie cohérente de SQLite : utiliser une sauvegarde SQLite adaptée, ou suspendre tous les processus pouvant écrire avant la copie des fichiers. Le seul affichage d'une page de maintenance ne verrouille pas toute la base. Conserver le dossier média correspondant au même état.
3. La sauvegarde ZIP du menu Outils est utile pour les contenus, médias et données des extensions. Elle exclut les identifiants et le code : conserver aussi une sauvegarde privée complète pour un retour arrière.
4. Copier les fichiers de code approuvés en fusionnant les dossiers. Conserver `storage/` et les modules personnalisés ; examiner les modifications locales avant de remplacer un fichier de code personnalisé.
5. Retirer uniquement les copies obsolètes suivantes si elles proviennent du correctif précédent : `files/core/AdminViews.php`, `App.php`, `Content.php`, `Media.php`, `Security.php`, `Store.php`, `Tools.php`, `bootstrap.php`, ainsi que `files/docs/nginx.conf` et `files/tests/integration.py` / `run.py`. Dans cette liste, les noms PHP abrégés sont tous sous `files/core/`.
6. Transférer aussi `.htaccess` et `.user.ini`. Pour Nginx, adapter et appliquer les règles fournies à la véritable racine ou au préfixe du site.
7. Vérifier `1.2.1` dans l'administration, la connexion, un article, une image et un document public/privé. Lire les diagnostics et les journaux privés.
8. Remettre le site en service lorsque les contrôles réussissent.

Sur un clone Git propre, utiliser de préférence le patch fourni : sa vérification refuse les conflits et les suppressions ne concernent que les fichiers versionnés identifiés. [Procédure GitHub](GITHUB-v1.2.1-FR-EN.md).

### Vérifier le code et l'environnement

Avant installation ou sur un site existant, le diagnostic suivant ne crée pas de stockage et n'ouvre pas la base :

```bash
php bin/console.php doctor
php bin/console.php doctor --json
```

Il indique les modules et limites PHP. Les permissions du PHP CLI peuvent différer de celles du processus web ; le contrôle de protection HTTP reste manuel.

Avec Python disponible, vérifier une archive puis l'extraire dans un dossier neuf :

```bash
python3 tools/verify_release.py VoilaVoila-CMS-1.2.1.zip --expected-version 1.2.1 --extract test-results/installation
```

Pour vérifier les fichiers déclarés du paquet après transfert :

```bash
python3 tools/verify_release.py --directory /chemin/vers/installation --expected-version 1.2.1
```

Cette vérification utilise `MANIFEST-SHA256.txt` et `BUILD-INFO.json`. Les données d'exécution et les modules personnalisés ajoutés ne sont pas attestés par ce manifeste. Les métadonnées de fabrication et le manifeste sont régénérés à chaque construction. Une somme de contrôle n'est pas une signature d'auteur ; télécharger le code depuis une source de confiance.

### Retour arrière

Suspendre les écritures, conserver l'installation défaillante à part, puis restaurer **le code et son état privé compatible** depuis la sauvegarde. Restaurer le stockage à l'emplacement configuré ; vérifier propriétaire, contenus, médias et droits d'accès avant réouverture. Ne pas réutiliser une base plus récente avec un ancien moteur en supposant sa compatibilité.

La mise à jour 1.2.1 ne modifie pas le schéma de la base, mais les nouvelles écritures utilisateur effectuées après la sauvegarde sont perdues lors d'un retour à cette sauvegarde. Les exporter ou les rapprocher avant de restaurer si elles doivent être conservées.

### Hébergeurs et preuves

Utiliser la [fiche de pilote FR/EN](docs/HOSTING-PILOT-FR-EN.md). Les tests locaux et les scripts de CI ne constituent pas une exécution chez OVH ou Infomaniak. Ces deux pilotes restent à effectuer sur les offres réelles.

## English

### Changes

1.2.1 removes the eleven inactive correction copies and denies their HTTP paths. The flat ZIP contains a complete SHA-256 manifest and build-commit metadata. New tools validate before extraction and can check manifest-declared installed files. Tools and `doctor` provide a read-only hosting preflight. Obsolete GD calls are removed for PHP 8.5, without changing media bytes. CI tests the extracted package, upgrades/rollback and the actual Chromium journey.

The owner, content, settings, activation choices and revisions retain their existing format. There is no SQLite schema migration.

### Fresh installation

Verify the ZIP against its checksum, extract it and copy its contents into the chosen CMS root, including hidden configuration files. Configure HTTPS, PHP 8.3+ and the required modules. Open `install.php`, retrieve the private `storage/setup-key.php` through SFTP, create the owner and store the recovery code offline. Review Tools and confirm private paths return HTTP 403/404 to a signed-out visitor.

The hosted CMS does not require Node, Python, Docker or Composer.

### Existing installation

Back up code and a consistent copy of private state, enable maintenance, and stop all writers when taking a filesystem copy of SQLite. Alternatively use an appropriate SQLite backup mechanism. Keep the corresponding original media. The portable CMS backup excludes credentials and executable code, so retain a separate private rollback backup.

Merge approved code files while retaining `storage/` and custom modules. Review local code changes before overwriting them. Remove only the eleven obsolete correction copies listed above, preserve unrelated files, update the hidden rules and apply the appropriate Nginx rules when relevant.

Confirm version 1.2.1, owner sign-in, content, public/private media, diagnostics and private logs before reopening. For a clean Git clone, use the supplied patch and the [GitHub instructions](GITHUB-v1.2.1-FR-EN.md); patch checking safely detects conflicting local changes.

### Verification and rollback

The commands in the French section are shared. `doctor` does not open the database or create storage. It cannot prove HTTP privacy, and CLI permissions may differ from web-process permissions. `verify_release.py` verifies ZIP paths/bytes before extraction into an empty directory; `--directory` checks declared installed code. Runtime data and additional custom modules are outside the release manifest. Checksums do not authenticate an author's identity.

For rollback, stop writers and restore both old code and its compatible private state. Check owner/content/media/access before reopening. Restoring a backup discards later writes unless they were separately exported or reconciled.

OVH and Infomaniak pilots require the actual plans and environments; use the [shared pilot checklist](docs/HOSTING-PILOT-FR-EN.md). Their execution is not claimed by this release preparation.
