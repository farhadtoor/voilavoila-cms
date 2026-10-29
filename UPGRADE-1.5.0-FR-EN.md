# Mise à jour 1.4.0 → 1.5.0 / Upgrade

## Préparer

Cette mise à jour cible **exactement 1.4.0**. Elle conserve le schéma SQLite, le propriétaire, les clés OTP, les contenus, médias et modules natifs personnalisés. Elle retire les sources distribuées du Runtime/portage de code WordPress et leurs passerelles. Si vous avez modifié un fichier du cœur ou un thème fourni, comparez-le avant remplacement.

Sauvegardez le code, tout `VV_STORAGE_PATH`, les bases métier séparées et votre configuration serveur. Arrêtez le cron et les écritures externes. Passez en maintenance. Les ressources hors du site, dont un ancien VirtualHost WordPress distinct, doivent être arrêtées séparément.

## Avec le paquet de mise à jour

1. Extrayez `VoilaVoila-CMS-1.4.0-to-1.5.0-Update.zip` **hors du dossier public du site**.
2. Vérifiez les empreintes du paquet, PHP 8.3+ et ZIP. Depuis le dossier extrait : `php APPLY-UPDATE.php --site=/chemin/absolu/du/cms --confirm=METTRE-A-JOUR`.
3. Le programme valide chaque fichier, verrouille le stockage 1.4, crée une sauvegarde complète privée du code et du stockage (limite 1 Go/20 000 fichiers, sauvegardes de mise à jour précédentes exclues), remplace seulement les fichiers distribués et retire la liste explicite des sources obsolètes. Les dossiers/fichiers personnalisés non listés sont conservés. Un échec pendant le remplacement rétablit le code modifié ; le site reste en maintenance pour examen.
4. Adaptez la configuration Nginx, ou comparez le nouveau `.htaccess` avec vos particularités d’hébergement. Le programme ne recharge pas le serveur et ne modifie pas ses VirtualHosts/TLS.
5. Rechargez PHP/OPcache selon les outils de l’hébergeur. Vérifiez l’administration, les médias, la clé OTP, les modules actifs, la quarantaine, les règles HTTP et le cron. Réactivez le cron et quittez la maintenance après ces vérifications.

Le paquet complet sert à une installation neuve ; il n’est pas un ZIP à importer dans Extensions. Si vous n’avez pas SSH/CLI, transférez le contenu de `payload/` par SFTP après sauvegarde et retirez **tous** les fichiers de `UPDATE-INFO.json → removed`. Ne remplacez pas le stockage privé par un dossier vide et ne supprimez pas vos modules personnalisés.

## Retour à 1.4.0

La sauvegarde complète créée par l’outil est sous `VV_STORAGE_PATH/upgrade-backups/DATE.php`. C’est un ZIP précédé de l’enveloppe PHP de refus du CMS ; copiez-le hors du site et retirez cette première ligne pour l’ouvrir. Restaurez ensemble le code et le stockage de ce point, ainsi que les éventuelles bases métier sauvegardées séparément. Les sessions doivent être invalidées selon vos procédures si nécessaire. Vérifiez l’état de maintenance avant réouverture. Ne restaurez pas seulement l’ancien code après des écritures 1.5, notamment des mises à jour de modules.

## Changements à connaître

- `install.php` est accessible par HTTP en local ou HTTPS sur le site, puis verrouillé après l’installation unique. `wp.php`, `wp-assets.php` et `router.php` restent refusés en production ; les passerelles WordPress sont supprimées.
- Les nouveaux médias et ZIP de modules restent en quarantaine jusqu’à examen séparé. Une extension qui appelait `Media::upload` doit traiter l’identifiant retourné comme un dépôt en attente.
- Les fichiers déjà publiés restent disponibles. Leurs octets ne sont pas réencodés.
- Les points de retour de modules contiennent le code et les données `plugin_data` de ce module, pas ses bases/fichiers privés séparés.
- L’import WXR reste un import éditorial hors ligne, avec prévisualisation et confirmation ; les anciennes prévisualisations doivent être recréées avec le parcours 1.5.
- Changer de base est une opération séparée, volontaire et documentée. La mise à jour conserve SQLite par défaut.

English: extract the update outside the live site, make complete backups, pause cron/external writers, then run the included CLI tool against an installed 1.4.0 site. It verifies payloads, creates a guarded private backup, preserves custom files and removes explicitly retired source files. Review actual server/TLS configuration and functional checks before leaving maintenance. Roll back code and private state together. Database switching is a separate operation.
