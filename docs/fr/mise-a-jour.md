# Mise à jour 1.0.0 → 1.1.0

[English](../en/upgrade.md) · [Comparatif et architecture](architecture.md)

## Avant de remplacer les fichiers

Exportez un ZIP portable depuis l’administration 1.0. Faites aussi une copie privée complète du moteur et de tout le stockage : elle seule comprend l’accès propriétaire nécessaire à un retour complet. Activez la maintenance, fermez les onglets d’édition et interrompez les écritures pendant la mise à jour. Activez PDO SQLite dans le PHP du site et de la console. DOM, mbstring et fileinfo restent nécessaires ; GD et ZIP conservent leurs fonctions.

## Appliquer

1. Décompressez le ZIP d’installation 1.1.0.
2. Remplacez les fichiers du moteur depuis `voila-cms`, en **conservant le stockage existant**, vos thèmes/extensions personnalisés et les volumes. Actualisez les modules natifs fournis si nécessaire. N’écrasez pas le site avec un stockage de démonstration.
3. Gardez le refus d’accès HTTP direct au stockage. Conservez `VV_STORAGE_PATH` et utilisez le même chemin pour la migration CLI.
4. Ouvrez `admin.php` ; l’initialisation effectue la migration. Autre méthode : `php bin/console.php migrate`, depuis le CMS avec l’environnement de stockage du site.
5. Connectez-vous avec l’accès habituel. Vérifiez profil, pages/articles, révisions, médias et téléchargements des journaux. Quittez la maintenance après vérification. Ne relancez pas d’installation vierge.

La migration conserve identifiants, slugs, statuts, textes FR/EN, dates, tags, couvertures, ordre, programmation et révisions ; hachages du mot de passe et du code de récupération, paramètres et données des extensions natives ; identifiants et liens `media.php?id=...`, visibilité et textes alternatifs.

## Changements de stockage

| 1.0.0 | 1.1.0 |
| --- | --- |
| JSON protégé dans `storage/state.php` | `storage/cms.sqlite` : `contents`, `revisions`, `cms_state` |
| Médias enveloppés dans `<id>.php` | Fichiers bruts `<id>.jpg`, `.jpeg`, `.png`, `.webp` ou `.pdf` |
| Journal d’événements limité dans l’état | Journal conservé ; ajout de `logs/access.txt` et `logs/error.txt` séparés |

Le fichier d’état original est gardé. `storage/legacy/state-v1-before-migration.php` contient une copie de migration. Les médias enveloppés sont déplacés dans `storage/legacy/media` après validation. SQLite devient la source courante ; le JSON ancien n’est pas réimporté aux visites suivantes. Un échec annule les modifications SQL et conserve les sources, pour pouvoir réessayer après correction.

Les nouveaux médias conservent octets, format, dimensions, extension et métadonnées. Une image déjà convertie en PNG par la 1.0 reste PNG : le JPEG antérieur ne peut pas être recréé. Réimportez votre original si nécessaire. Les liens des médias restent stables. Les originaux passent toujours par le contrôle de `media.php` ; le serveur doit interdire l’accès brut au stockage.

Les ZIP portables 1.0 restent importables. Les exports actuels excluent journaux techniques, secrets propriétaire et modules exécutables. Chaque journal tourne à 5 Mio, avec le fichier courant et trois archives. Le CMS journalise ses requêtes PHP ; les ressources statiques et requêtes refusées avant PHP relèvent des journaux de l’hébergement.

## Retour arrière

Interrompez les écritures et restaurez **ensemble le moteur 1.0 et sa copie complète de stockage avant mise à jour**. Remettre seulement le code 1.0 ne transfère pas les modifications SQLite vers le JSON. Gardez une copie privée des données créées après migration avant tout retour. Conservez les fichiers hérités jusqu’à vérification de la migration et d’une sauvegarde indépendante.
