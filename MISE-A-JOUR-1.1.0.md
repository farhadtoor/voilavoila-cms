# Mise à jour 1.0 → 1.1

Cette version remplace le stockage éditorial JSON par SQLite, sépare les journaux techniques et conserve les formats d’origine des nouveaux médias.

## Avant la copie

1. Dans votre CMS 1.0, téléchargez une sauvegarde ZIP depuis Outils & sauvegardes.
2. Faites aussi une copie complète du dossier du CMS et de son stockage par SFTP, dans un emplacement privé. Ce dossier complet inclut votre accès propriétaire, contrairement à la sauvegarde portable.
3. Activez la maintenance et fermez les autres onglets d’édition. N’effectuez aucune écriture pendant le remplacement du moteur et la migration.
4. Activez **pdo_sqlite** dans PHP. PHP 8.3+, DOM, mbstring et fileinfo restent nécessaires ; GD valide les images, ZIP gère les archives.

## Appliquer la mise à jour

1. Décompressez le pack 1.1.0.
2. Remplacez les fichiers du moteur avec ceux du dossier `voila-cms`, sans remplacer votre dossier `storage` ni supprimer vos modules personnalisés. Les modules natifs fournis peuvent être mis à jour avec les fichiers du pack. Le pack ne contient aucun compte, média ou fichier de base de données prérempli.
3. Conservez les règles de refus d’accès direct à `storage`. Apache : `.htaccess`. Nginx : configuration `docs/nginx.conf`, adaptée à votre racine/sous-dossier. Si `VV_STORAGE_PATH` est déjà défini, gardez le même chemin.
4. Ouvrez `admin.php`. L’initialisation migre automatiquement l’ancienne base. Vous pouvez aussi lancer `php bin/console.php migrate` depuis le dossier du CMS, avec le même `VV_STORAGE_PATH` que le site.
5. Connectez-vous avec votre accès habituel, vérifiez le profil, les contenus et médias, puis désactivez la maintenance. **Ne relancez pas une installation vierge.**

## Ce qui est conservé

- Identifiants, slugs, statuts, textes FR/EN, dates, tags, couvertures, ordre, navigation, programmation et révisions des articles/pages.
- Compte propriétaire, mot de passe haché, code de récupération haché, réglages et données des extensions natives.
- Identifiants et liens `media.php?id=...`, visibilité publique/privée et textes alternatifs.
- Anciennes sauvegardes ; un ZIP de la version 1.0 reste importable.

## Nouveaux fichiers

| Fichier | Contenu |
| --- | --- |
| `storage/cms.sqlite` | Table `contents` pour pages/articles, `revisions` pour leurs versions et `cms_state` pour les autres données applicatives |
| `storage/media/<id>.jpg`, `.jpeg`, `.png`, `.webp`, `.pdf` | Binaires réellement au format indiqué, sans préfixe PHP |
| `storage/logs/access.txt` | Accès des requêtes PHP du CMS : date, méthode, chemin sans paramètres, statut, IP directe et durée |
| `storage/logs/error.txt` | Erreurs PHP et erreurs applicatives capturées |
| `storage/legacy/state-v1-before-migration.php` | Copie protégée de l’ancienne base, créée avant validation de la migration |
| `storage/legacy/media/<id>.php` | Anciennes enveloppes média, archivées après validation |

L’ancien `storage/state.php` est gardé comme source historique ; le moteur 1.1 utilise ensuite SQLite et ne le réimporte pas à chaque visite. Les nouvelles écritures passent par la base SQLite. Les requêtes utilisent des paramètres et une transaction. En cas d’échec, les données sources restent conservées et la migration peut être réessayée après correction de la cause.

## Médias originaux

Les nouveaux imports gardent leurs octets, format, dimensions et extension. Aucune reconstruction PNG ou réduction automatique n’est appliquée. Un fichier nommé `Photo.jpeg` est conservé sous une clé interne `<id>.jpeg`, avec son nom d’origine proposé au téléchargement. Les JPG, JPEG, PNG, WebP et PDF sont acceptés selon leurs MIME réels. Les limites de 8 Mo, 8 mégapixels et 6 000 px maximum restent applicables.

Une photo déjà convertie en PNG par la version 1.0 reste un PNG lors de la migration. Le CMS retire son enveloppe PHP et conserve les octets existants. Pour retrouver son JPEG antérieur, réimportez le fichier JPEG original que vous possédez.

Les fichiers originaux restent dans le stockage privé. Le contrôle public/privé continue de se faire via `media.php`, sans changer les liens. Les métadonnées EXIF des nouveaux originaux sont conservées.

## Journaux

Téléchargez chaque journal depuis **Sécurité → Journaux texte séparés**. Les paramètres d’URL, corps de formulaire, mots de passe et cookies ne sont pas copiés dans le journal d’accès. Les fichiers sont du texte ordinaire, sans enveloppe PHP.

La rotation conserve le fichier courant plus trois archives de chaque type (`access.1.txt`, etc.), avec rotation à 5 Mo. Les ressources statiques et requêtes bloquées avant le démarrage du moteur relèvent des journaux Apache/Nginx de votre hébergeur. Les journaux techniques ne sont pas ajoutés au ZIP portable.

## Retour à la version précédente

Restaurez ensemble le moteur 1.0 et sa copie complète du stockage, pendant une maintenance sans écriture. Remettre uniquement le code 1.0 après avoir publié de nouveaux contenus en 1.1 ne transfère pas ces nouveaux contenus vers l’ancienne base JSON.

La configuration Docker incluse conserve les volumes existants. Son déploiement n’a pas été exécuté dans l’environnement de validation ; le chemin PHP/SFTP et la migration ont été testés dans une installation isolée.
