# Installer et exploiter VoilaVoila CMS 1.1

[English](../en/installation.md) · [README](../../README.fr.md)

## Prérequis

PHP 8.3+, DOM, mbstring, fileinfo et PDO SQLite. Les images demandent GD avec les codecs JPEG/PNG/WebP utilisés ; les archives demandent ZIP. Mémoire PHP conseillée : 256 Mio. Aucun MySQL, Composer, Node, CDN ou réécriture d’URL obligatoire. Choisissez une branche PHP maintenue et ses correctifs récents.

L’administration exige HTTPS hors du véritable localhost. Le serveur doit aussi servir le profil public en HTTPS. Le CMS nécessite un hébergement PHP. GitHub héberge le code ; GitHub Pages n’exécute pas cette application PHP.

## Essai local

Depuis le dossier du CMS, exécutez `php bin/console.php status`, puis `php bin/console.php install-key`. Le stockage vide est initialisé et la clé s’affiche. Démarrez :

```sh
php -d memory_limit=256M -d upload_max_filesize=64M -d post_max_size=70M -S 127.0.0.1:8787 router.php
```

Ouvrez [install.php](http://127.0.0.1:8787/install.php), saisissez la clé, le nom du site, l’e-mail privé de gestion et un mot de passe de 14 à 72 octets. Conservez immédiatement le code de récupération hors ligne ; il est affiché une seule fois. Aucun mot de passe par défaut. Gardez le terminal ouvert ; Ctrl+C arrête le serveur.

Sur macOS, `Lancer-Mac.command` est un raccourci facultatif si PHP est déjà installé, pas un exécutable PHP fourni. Sous Windows, utilisez un PHP CLI ou un environnement local avec les extensions nécessaires. Les guides utilisent `python3` ; adaptez en `python` ou `py -3` selon votre installation.

## Hébergement PHP public

1. Téléchargez le ZIP courant dans Releases. Copiez le **contenu** de `voila-cms`, fichiers cachés compris, dans le dossier Web vide choisi. Les sous-dossiers sont pris en charge ; les liens utilisent `index.php?p=...`.
2. Configurez les extensions PHP, HTTPS et les permissions. PHP doit écrire dans `storage`. L’installation de ZIP depuis l’administration demande aussi l’écriture dans `content/themes` et `content/plugins`. Réglez le propriétaire/groupe réel ; n’utilisez pas 777.
3. Préférez un dossier de données absolu hors racine Web avec `VV_STORAGE_PATH`. Pour déplacer une installation, déplacez **tout** le stockage et utilisez la même configuration pour les commandes CLI.
4. Apache doit appliquer le refus d’accès du `.htaccess` de stockage. Sous Nginx, adaptez `docs/nginx.conf` : domaine, racine, sous-dossier, socket PHP-FPM et certificats. Nginx ignore les `.htaccess`.
5. Ouvrez `https://votre-domaine.example/install.php`. Récupérez `storage/setup-key.php` par SFTP ou utilisez `install-key` avec le même chemin de stockage. La clé suit la ligne de protection PHP. Créez le propriétaire unique.
6. Conservez le code hors ligne. Configurez le profil, l’URL canonique HTTPS et le thème. Vérifiez un article public, le refus anonyme d’un média privé et les deux langues.
7. Vérifiez qu’un accès direct à `storage/cms.sqlite`, `storage/logs/access.txt` et à un média brut connu renvoie 403/404 sans divulgation. Si le stockage est hors racine Web, aucune route ne doit l’exposer. Testez une sauvegarde et la récupération avant utilisation réelle.

Derrière un proxy de confiance, renseignez ses IP exactes, séparées par des virgules, dans `VV_TRUSTED_PROXIES`. Le proxy doit remplacer les en-têtes de protocole transmis. Un `X-Forwarded-Proto` fourni librement par un client n’est pas une preuve de HTTPS.

## Docker

Le `compose.yaml` à la racine est une base facultative, liée à localhost. `docker compose up -d --build` la construit ; `docker compose exec cms php bin/console.php install-key` affiche la clé. Stockage et modules sont conservés dans des volumes nommés. `docker compose stop` les garde. N’utilisez `down -v` que pour supprimer volontairement ces données.

Ce déploiement n’a pas été exécuté pendant la préparation des versions ou de ces documents. La configuration des codecs GD du Dockerfile d’origine reste à vérifier pour JPEG/WebP avant d’annoncer leur prise en charge complète. Le `tests/Dockerfile` distinct active explicitement ces codecs pour la CI. HTTPS et proxy réels restent à configurer.

## Utilisation et récupération

Un média public est lisible par toute personne possédant son lien, même s’il appartient à un brouillon. Choisissez privé pour les documents de travail. Les PDF sont téléchargés. Les nouvelles images originales conservent leurs métadonnées EXIF ; retirez les données de localisation ou privées avant import si nécessaire.

Exportez les sauvegardes éditoriales et conservez une copie privée indépendante. Elles excluent l’accès propriétaire, les sessions, secrets serveur et modules exécutables ; elles incluent les contenus privés et notes d’extensions. La restauration portable conserve le propriétaire de destination, remplace les données éditoriales et ne réinjecte pas les révisions. Une copie complète des fichiers et du stockage est nécessaire pour un retour complet. Les points conservés sur le serveur ne couvrent pas la perte de l’hébergement.

En cas de perte d’accès, utilisez le code hors ligne sur la page de connexion ; il est remplacé après usage. Avec accès serveur : `php bin/console.php reset-owner` change le mot de passe et révoque les sessions. `php bin/console.php safe-mode` désactive les extensions et rétablit Profil Or. `admin.php?view=plugins&safe=1` saute les extensions pour cette requête. Ne publiez jamais une sortie de commande contenant une clé ou un code de récupération.
