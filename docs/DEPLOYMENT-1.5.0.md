# Déployer VoilaVoila CMS 1.5 / Deploying 1.5

## Prérequis

PHP 8.3+ avec DOM, mbstring, fileinfo, tokenizer, PDO et le pilote sélectionné ; GD pour valider les images ; ZIP pour les modules et sauvegardes ; OpenSSL pour OTP. SQLite est activé par défaut. Recommandation de configuration : mémoire 256M, upload 64M, POST 70M. Le cœur n’exige ni Node ni Composer en production.

## Première installation

Le profil de production refuse `install.php`. Deux parcours sont disponibles :

1. **CLI** : créez hors du répertoire public un JSON privé avec `name`, `email` et `password`, droits 0600. Depuis le dossier du CMS : `php bin/console.php install < /chemin/prive/installation.json`. Conservez le code de récupération affiché, retirez le fichier de mots de passe, puis connectez-vous sur `login.php` en HTTPS.
2. **Web temporaire** : sous Apache, remplacez provisoirement `.htaccess` par `docs/apache-install.htaccess`. Sous Nginx, utilisez temporairement `docs/nginx-install.conf`, adapté à votre domaine, racine, TLS et PHP-FPM. Ces profils autorisent `install.php` et fixent `VV_ALLOW_WEB_INSTALL=1`. Après installation, rétablissez le profil de production et rechargez le serveur. Le verrou applicatif d’installation reste actif après création du propriétaire.

Pour un sous-dossier, le `.htaccess` emploie des règles relatives et ne compare pas une allowlist à une URL supposée à la racine. Pour Nginx : adaptez `docs/nginx-subdirectory.conf` ou générez un profil avec `python3 tools/web_config.py --root /var/www --mount /cms --server exemple.fr --socket /run/php/php8.3-fpm.sock`. Ici `/var/www` est le DocumentRoot parent contenant `/cms`. Le générateur accepte `--install` pour un profil temporaire. N’exposez pas le routeur de développement en production.

## Points d’entrée et fichiers statiques

Seuls `index.php`, `admin.php`, `login.php` et `media.php` sont exécutables via HTTP. Les paramètres d’URL, routes d’extensions et le sitemap restent gérés par ces contrôleurs. Les modules doivent acheminer leurs actions publiques par `public_route`/`index.php` ; un ancien endpoint PHP direct doit être adapté avant mise à jour. `wp.php`, `wp-assets.php`, `router.php` et l’installateur permanent ne sont pas publics. Les dossiers internes, les sous-dossiers techniques des modules, les manifestes, fichiers cachés, bases et archives sont refusés. Les ressources statiques autorisées sont servies sous `assets/` et les dossiers de thèmes/extensions ; `robots.txt`, `security.txt` et les challenges ACME disposent d’exceptions ciblées.

Apache doit autoriser `mod_rewrite`, les règles d’accès et les en-têtes du profil. Si `mod_rewrite` manque, le profil refuse l’accès au lieu de laisser servir les fichiers bruts. Le stockage dispose aussi d’un `.htaccess` de refus et ne dépend pas de cette seule protection. Nginx ignore `.htaccess` : son VirtualHost est indispensable. Vérifiez la configuration avant rechargement (`apachectl -t` / `nginx -t`). Ne remplacez pas aveuglément les paramètres TLS de l’hébergeur.

Les en-têtes de sécurité globaux sont conservés dans le bloc SVG Nginx, qui ajoute sa CSP sandbox. Le profil conserve `X-Frame-Options: DENY`. Les directives CGI évitent PATH_INFO arbitraire et annulent `HTTP_PROXY`. Les erreurs applicatives 400/403/409/429/503 restent produites par le CMS (`fastcgi_intercept_errors off`). Les changements de code des modules invalident leurs fichiers PHP dans OPcache lorsque cette fonction est disponible. Après une mise à jour complète du cœur, rechargez PHP/OPcache selon les outils de l’hébergeur, surtout si la vérification des horodatages est désactivée. Les CSS/JS ont un cache court et une version d’URL lorsqu’ils sont chargés par le cœur.

## Stockage privé et quarantaine

Placez idéalement `VV_STORAGE_PATH` **hors du DocumentRoot**. Si vous conservez `storage/` sous la racine, appliquez et vérifiez les règles de refus. Le CMS n’active pas de binaire reçu et ne donne pas de lien public vers les dépôts. La quarantaine couvre les dépôts reçus par les contrôleurs du CMS. Une copie directe par SFTP/SSH ou un module PHP de confiance qui écrit lui-même des fichiers ne passe pas automatiquement par ce parcours. Les enveloppes de quarantaine commencent par une terminaison PHP ; les fichiers libérés sont conservés dans le stockage privé et servis par le contrôleur média.

Limites de quarantaine : 100 dépôts en attente, 128 Mio cumulés, 500 fiches de dépôt au maximum, expiration après 7 jours. La maintenance retire les dépôts expirés ; leur expiration ne garantit pas une suppression immédiate sans cron. Les sauvegardes privées de modules sont gardées au maximum 30 jours et dix points globaux via maintenance. Les contenus et médias existants ne sont pas brusquement mis hors ligne lors de la mise à jour.

Configurez ClamAV côté hébergement puis `VV_CLAMAV_SOCKET=/chemin/clamd.sock` ou `VV_CLAMAV_SOCKET=tcp://127.0.0.1:3310`. Aucun socket TCP distant ou adressage venant d’une requête HTTP n’est accepté. Réglez `StreamMaxLength` de clamd pour accepter les dépôts autorisés (jusqu’à 64 Mio pour une sauvegarde/import) et ses limites de décompression. Activez `VV_QUARANTINE_REQUIRE_AV=1` pour exiger une analyse avant tout dépôt libérable. Une panne ou détection bloque ; aucune exception manuelle ne contourne un scanner configuré défaillant.

**ClamAV n’est pas livré, installé ou mis à jour par le CMS.** Sans scanner et sans le mode obligatoire, la validation structurelle et l’approbation propriétaire restent disponibles, avec le statut « Antivirus non configuré ». Cela ne certifie pas un fichier sans virus. Les contrôles PDF refusent notamment les noms d’actions/scripts connus, y compris les noms encodés ; ils ne constituent pas un désarmement complet des PDF compressés. Les octets originaux restent préservés. Un module PHP approuvé reste du code serveur de confiance, capable d’utiliser les droits de son hébergement ; l’allowlist HTTP ne transforme pas ce code en sandbox.

## Exploitation

Configurez le cron : `php bin/console.php maintenance:run --budget=10 --limit=3`. Il applique la rétention, le nettoyage de quarantaine et les tâches natives. Les fonctions d’envoi de Forms restent désactivées par défaut jusqu’à configuration et vérification de la délivrabilité.

Une sauvegarde éditoriale contient les contenus, médias et données natives prises en charge ; elle ne contient pas tous les secrets du propriétaire, le code, les fichiers OTP, la quarantaine ou les bases propres aux plugins. Sauvegardez séparément le code, l’ensemble du stockage privé et les bases externes. Vérifiez une restauration sur une copie avant usage réel. Si un ancien Runtime WordPress utilisait un VirtualHost et une base séparés, arrêtez aussi cette infrastructure : la suppression de ses passerelles dans le CMS ne peut pas désactiver un autre serveur distant.

## Sources techniques

- Apache, règles par répertoire : https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html
- Nginx, ordre des locations : https://nginx.org/en/docs/http/request_processing.html
- Nginx, héritage des en-têtes : https://nginx.org/en/docs/http/ngx_http_headers_module.html
- OWASP, gestion des fichiers reçus : https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
- ClamAV, protocole INSTREAM : https://docs.clamav.net/manual/Usage/ClamdProtocol.html

## English deployment summary

Production exposes four native PHP controllers only. Use CLI installation, or a temporary installer profile and then restore the production profile. Configure HTTPS and PHP-FPM on the actual host. Prefer private storage outside the DocumentRoot. New uploads are inert, private and reviewed before release; a configured but failing antivirus blocks release. Require ClamAV explicitly with `VV_QUARANTINE_REQUIRE_AV=1`; no antivirus engine or signatures are bundled. Keep full code/storage/business-database backups and qualify email, cron and hosting settings on your infrastructure.

## Docker

Le Dockerfile décrit un service Apache/PHP natif ; Compose publie seulement `127.0.0.1:8787` par défaut. L’image et la chaîne CI distante sont fournies mais n’ont pas été construites/exécutées dans cet environnement de validation. Pour installer dans un conteneur, utilisez le même utilisateur que PHP (`docker compose exec --user www-data cms php bin/console.php install` avec entrée JSON privée), ou le profil temporaire. Configurez un frontal TLS avant toute exposition réseau. Les volumes conservent stockage et modules ; revoyez leur contenu lors d’une mise à jour d’image, car un volume de modules existant masque les fichiers de thèmes/extensions du nouveau conteneur.
