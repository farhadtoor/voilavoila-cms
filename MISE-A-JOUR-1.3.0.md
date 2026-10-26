# Mise à jour vers 1.3.0

La version 1.3 ajoute Forms, la signature VoilaVoila avec le logo PNG original et le drapeau français, et le connecteur WordPress Runtime facultatif/inactif. Pages, articles, révisions et médias restent compatibles avec le schéma SQLite existant. Les notifications Forms et le moteur WordPress ne s’activent pas automatiquement.

1. Testez sur une copie privée avant production. Téléchargez une sauvegarde native avec votre mot de passe actuel et copiez **aussi le code complet et tout le stockage privé** par SFTP/SSH. Notez la version PHP et `VV_STORAGE_PATH`. Sauvegardez séparément tout moteur WordPress déjà exploité.
2. Vérifiez la somme SHA-256 du ZIP reçu avec `sha256sum` (Linux), `shasum -a 256` (macOS) ou `Get-FileHash` (PowerShell). L’archive est plate : `admin.php`, `core/`, `content/` sont directement à sa racine.
3. Activez la maintenance, puis remplacez les fichiers d’application par ceux du ZIP. Conservez votre stockage réel, vos variables d’environnement et vos thèmes/extensions personnalisés. N’effacez pas un répertoire entier de modules pour appliquer une mise à jour. Ne copiez jamais une base de test dans votre site. Sur Nginx, adaptez les interdictions HTTP du dossier de modèles du moteur ; les nouvelles règles sont dans `.htaccess` pour Apache.
4. Exécutez `php bin/console.php doctor`, puis `php bin/console.php status`. Le numéro affiché doit être 1.3.0. Vérifiez connexion propriétaire, pages, médias originaux, commentaires et export de sauvegarde. Si vous activez Forms : créez un formulaire, envoyez une demande de test et vérifiez la boîte privée avant de configurer un transport e-mail réel.
5. Désactivez la maintenance. Pour WordPress facultatif, suivez [le guide dédié](docs/WP-RUNTIME.fr.md) : autre origine HTTPS, stockage et processus séparés, base dédiée. Une mise à jour CMS ne prépare pas WordPress.

Retour arrière : remettez **ensemble** votre ancien code et votre copie complète du stockage d’avant mise à jour. Des données créées depuis seront absentes de cette copie ; exportez-les avant de revenir en arrière. Ne réutilisez pas simplement une sauvegarde éditoriale comme remplacement du stockage propriétaire complet. Le moteur WordPress possède son propre retour arrière fichiers + SQL + configuration.

L’export et le téléchargement des sauvegardes demandent désormais le mot de passe actuel, car les demandes privées Forms peuvent y figurer. Les sauvegardes héritées restent lisibles. Les shortcodes natifs API 1 gardent leurs callbacks sans argument. Aucune migration automatique du contenu natif vers la base WordPress n’est faite.

[English](UPGRADE-1.3.0.md) · [Forms](docs/FORMS.fr.md) · [GitHub](GITHUB-v1.3.0-FR-EN.md)
