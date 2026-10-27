# VoilaVoila CMS 1.4.0 — Mise à jour / Upgrade

## Français

Cette version regroupe J4 (SEO et portabilité WordPress) et les fonctions techniques de J5, initialement prévues pour la 1.5.0 (santé, rétention, maintenance et kit pilote). Elle conserve le propriétaire unique, SQLite, API 1, les médias originaux et les journaux texte séparés.

### Site existant en 1.3.1

1. Faites une copie complète du code et du stockage privé, avec le même propriétaire système et les mêmes permissions. Gardez notamment la base SQLite, le secret du CMS, les clés OTP et les bases/médias propres aux extensions comme PASS. Le ZIP éditorial seul ne constitue pas cette sauvegarde complète.
2. Activez la maintenance dans Mon profil & site. Suspendez les tâches cron et les écritures des extensions pendant la copie. Si une extension a sa propre base, suivez aussi sa procédure de sauvegarde.
3. Décompressez `VoilaVoila-CMS-1.3.1-to-1.4.0-Update.zip`. Comparez les fichiers de configuration personnalisés, notamment `.htaccess`, avant de les fusionner.
4. Par SFTP, copiez les fichiers du correctif à la racine du CMS. Gardez le stockage existant et les autres thèmes/extensions : aucun de ces dossiers personnels n’est à supprimer. Le correctif contient les nouveaux fichiers du noyau et la mise à jour de l’extension SEO livrée avec le CMS.
5. Connectez-vous, vérifiez la version 1.4.0, puis ouvrez Santé & maintenance. Confirmez les chemins privés en visiteur, testez un article, un média, une recherche, Forms et vos extensions actives.
6. Vérifiez le dégradé VoilaVoila ! et le bouton de repli en haut de l’administration. Le choix de repli est mémorisé dans votre navigateur, par dossier d’installation.
7. Désactivez la maintenance et reprenez les tâches cron. Le stockage SQLite reste au schéma 2 ; les nouveaux réglages utilisent des valeurs par défaut et des métadonnées compatibles.

Pour une première installation, utilisez `VoilaVoila-CMS-1.4.0.zip`, copiez son contenu à la racine du site et suivez `INSTALLATION.html`.

### SEO et WordPress

- Configurez l’URL HTTPS du site dans Mon profil & site. Le menu Référencement SEO contient les valeurs de l’accueil et les données structurées. Chaque éditeur d’article/page contient un volet Référencement & partage en français et anglais.
- Dans Outils & sauvegardes, un export WordPress est maintenant analysé avant confirmation. Le rapport montre les créations, les contenus déjà présents, les collisions de slugs et les médias manquants ou refusés. Les fichiers privés de prévisualisation expirent après une heure ; deux analyses au maximum sont conservées. Une modification du site impose de refaire l’analyse.
- Après l’import, le menu Redirections permet d’examiner puis d’approuver séparément les chemins proposés. Les cibles sont des contenus natifs publics ; un contenu rendu privé cesse immédiatement de recevoir ces redirections.
- Les permaliens historiques nécessitent le routage Apache fourni ou l’adaptation Nginx documentée. Les liens `?p=…` fonctionnent par le point d’entrée natif. Les anciens scripts `.php` qui seraient capturés par une règle PHP du serveur peuvent nécessiter une règle d’hébergement spécifique.
- Un import répété conserve les modifications locales. WXR n’importe pas les comptes, les réglages d’un thème ou les bases métier des plugins. Les moteurs/convertisseurs WordPress restent facultatifs et leurs limites restent documentées.

### Maintenance et conservation

Dans Santé & maintenance, choisissez la durée des journaux, la durée et le nombre maximal de sauvegardes, et les options de traitement. Les sauvegardes automatiques et les notifications sont désactivées par défaut. Les deux points les plus récents restent conservés, dans la limite des points existants. Forms conserve sa politique de rétention par formulaire.

Planifiez toutes les 5 minutes, avec le PHP de l’hébergement et **le même stockage privé que le site** :

```bash
VV_STORAGE_PATH=/chemin/prive/voilavoila php /chemin/site/bin/console.php maintenance:run --budget=10 --limit=3
```

Le verrou empêche deux passages simultanés. Le budget est coopératif, vérifié entre les tâches ; un transport mail ou une opération disque en cours peut le dépasser. Un envoi de résultat ambigu devient `unknown` et n’est pas renvoyé automatiquement. Une acceptation par le transport ne prouve pas la réception du mail.

Les prévisualisations expirées sont nettoyées lors de la maintenance ou d’une nouvelle analyse. Les fichiers ZIP temporaires sont supprimés après leur téléchargement ; les copies téléchargées restent sous votre responsabilité. Aucun cron n’est créé sur votre hébergement, aucune télémétrie n’est ajoutée et aucune tâche ne part sur une visite publique.

```bash
php bin/console.php doctor --json
php bin/console.php health
php bin/console.php maintenance:run --budget=10 --limit=3
php bin/console.php benchmark --contents=500 --iterations=10
```

Le benchmark utilise une base temporaire synthétique et la supprime ensuite. Il ne modifie pas la base du site et ne promet pas un temps de réponse sur un hébergeur réel.

### Retour arrière

Réactivez la maintenance, suspendez les tâches et remettez **ensemble** le code 1.3.1 et la copie de stockage privé réalisée avant la mise à jour. Une restauration éditoriale ne rétablit pas les clés d’accès, les fichiers des modules ou leurs bases séparées. Vérifiez le propriétaire, l’OTP, les articles, les médias et les extensions avant réouverture.

## English

Version 1.4.0 combines J4 (SEO and WordPress editorial portability) with J5’s technical work originally planned for 1.5.0: health, retention, scheduled maintenance and a pilot kit. Single-owner administration, SQLite, API 1, original media formats and separate text logs remain.

### Upgrade an existing 1.3.1 site

1. Back up all code and private storage, preserving filesystem ownership and permissions. Include SQLite, the CMS secret, OTP keys and separate plugin databases/media such as PASS. An editorial ZIP alone is not a full site backup.
2. Enable maintenance. Pause cron and plugin writes during the copy; follow each plugin’s backup procedure for separate databases.
3. Extract `VoilaVoila-CMS-1.3.1-to-1.4.0-Update.zip`. Compare and merge locally customized configuration files, especially `.htaccess`.
4. Upload the update files to the CMS root using SFTP. Retain existing storage and custom themes/plugins. The patch includes new core files and the bundled SEO plugin update.
5. Sign in, check version 1.4.0 and open Health & maintenance. Test private-path protection while signed out, a post, media, search, Forms and your active plugins.
6. Check the VoilaVoila ! gradient and the desktop sidebar button. Collapse preference is browser-local and scoped to the installation folder.
7. Disable maintenance and resume cron. SQLite remains at schema 2; new settings have defaults and additional content metadata.

For a new installation, extract `VoilaVoila-CMS-1.4.0.zip` into your site root and follow `INSTALLATION.html`.

### SEO, migration and operations

Configure the HTTPS site URL in My profile & site. SEO settings provide homepage overrides and structured data; each content editor has French/English SEO and sharing fields. Core head normalization prevents duplicate titles, descriptions, canonicals and supported social tags, including existing API 1 themes.

Tools & backups now previews WXR imports before confirmation. It reports creations, preserved imports, slug collisions and missing/rejected media. Two private reviews are retained for one hour. Content/media changes invalidate the review. After importing, separately approve proposed legacy URLs in Redirects. Targets are native public content IDs; no arbitrary external targets or chains are accepted. Private targets stop redirecting immediately. Pretty legacy URLs require the supplied Apache routing or adapted Nginx configuration. Legacy `.php` paths may require host-specific rules.

Repeated imports preserve local edits. WXR does not migrate accounts, theme settings or plugin business databases. WordPress engines and conversion tools remain optional, with their documented limitations.

Health & maintenance controls log and backup retention, due editorial backups and Forms notification processing. Automatic backup creation and mail processing default to off. The two newest existing restore points are retained. Forms uses its own per-form retention policy. Expired import reviews are cleaned by maintenance or a new review. Temporary export ZIPs are deleted after download; downloaded copies remain the owner’s responsibility.

Schedule the command shown above every five minutes using your hosting PHP and the site’s exact private storage. Work uses a non-overlapping lock and a cooperative budget checked between tasks. Active mail/disk operations may exceed it. Ambiguous notification results become `unknown` and are not blindly resent. Transport acceptance is not proof of email delivery. No hosting cron is installed, no telemetry is added and public visits do not trigger maintenance.

The command examples above also provide preflight, safe local health reporting and a synthetic benchmark. The benchmark uses and removes a temporary database; it does not change the site database or promise production hosting latency.

### Rollback

Enable maintenance, pause workers, and restore **both** pre-upgrade 1.3.1 code and compatible private storage. Editorial restoration does not restore access keys, module code or separate plugin databases. Verify owner login, OTP, posts, media and plugins before reopening.

The release includes a pilot protocol; live OVH/Infomaniak qualification and five volunteer sites remain real-world work to record, not claimed deployment results.
