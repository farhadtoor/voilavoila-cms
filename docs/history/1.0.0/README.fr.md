# VoilaVoila CMS 1.0.0

Un CMS personnel autonome, en PHP, JavaScript et CSS. Un seul compte propriétaire, un profil public, des pages et des articles, des médias, des thèmes et des extensions. Interface sombre, responsive, en français et en anglais. Aucun abonnement, CDN, Composer, Node ou serveur de base de données nécessaire.

**Commencez par ouvrir INSTALLATION.html.**

## Installation sur un hébergement

1. Activez PHP **8.3 ou supérieur**, DOM, mbstring et fileinfo. Activez aussi **GD** pour les images et **ZIP** pour les sauvegardes et modules. Mémoire recommandée : 256 Mo. Choisissez une branche PHP encore maintenue et ses correctifs disponibles.
2. Décompressez le pack. Copiez **le contenu du dossier `voila-cms`**, fichiers commençant par un point compris, dans un dossier Web vide. Le CMS peut être installé dans un sous-dossier. Il utilise des liens `index.php?p=...`, sans réécriture d’URL obligatoire.
3. Donnez au processus PHP le droit d’écrire dans `storage`. Le dossier `content/themes` et `content/plugins` doit être accessible en écriture pour installer des ZIP depuis l’administration. Sur un hébergement mutualisé, 755 pour les dossiers et 644 pour les fichiers suffisent souvent ; cela dépend du propriétaire Unix. N’utilisez pas 777.
4. Ouvrez `https://votre-site.fr/install.php`. La première visite génère `storage/setup-key.php`.
5. Téléchargez ce fichier **par SFTP**, ouvrez-le comme du texte et copiez la clé située après la première ligne PHP. Collez-la dans l’installateur. Créez votre nom de site, votre e-mail de gestion privé et votre mot de passe personnel (14 à 72 octets).
6. Conservez **hors ligne** le code de récupération affiché une seule fois. La clé d’installation est supprimée, l’installateur se verrouille et votre espace s’ouvre.
7. Dans « Mon profil & site », ajoutez votre bio, vos images et l’URL canonique HTTPS. Créez vos pages de mentions légales avec vos vraies informations.

L’administration distante refuse HTTP. Le site public doit lui aussi être servi en HTTPS au niveau de l’hébergeur. Les cookies, sessions et mots de passe ne doivent pas transiter en HTTP.

### Apache / OVH / hébergement mutualisé

Le fichier `.htaccess` protège les dossiers internes. Les données privées bénéficient aussi d’un préfixe PHP qui termine la requête avant leur contenu. Les données sont du JSON, lues avec `json_decode`, jamais exécutées par le CMS. `.user.ini` propose les limites d’envoi ; vérifiez leur prise en compte dans votre hébergement. PHP ZIP et GD doivent être activés dans votre offre, sinon leurs fonctions restent indisponibles avec un message explicite.

### Nginx

Adaptez `docs/nginx.conf` : domaine, chemin, socket PHP-FPM et certificats réels. Si l’installation est dans un sous-dossier, adaptez les règles de protection à ce préfixe. Nginx ignore les `.htaccess`. Ne servez jamais ce pack avec un serveur de fichiers statiques.

### Données hors de la racine Web

Vous pouvez définir `VV_STORAGE_PATH` sur un chemin privé absolu, accessible à PHP. Déplacez **tout** le contenu de `storage` avant de changer le chemin. Exemple de configuration serveur : `VV_STORAGE_PATH=/srv/private/voilavoila`. La clé d’installation, les médias et les sessions sont alors créés dans ce dossier. Le CMS ne déduit pas HTTPS d’un en-tête proxy non fiable. Derrière un proxy de confiance, définissez `VV_TRUSTED_PROXIES` avec ses adresses IP exactes, séparées par des virgules, et faites remplacer `X-Forwarded-Proto` par ce proxy.

## Sur Mac

Ce pack contient le code source et un lanceur, pas un exécutable PHP universel. macOS n’inclut pas systématiquement PHP. Deux méthodes :

- **PHP déjà disponible** : dans le dossier du CMS, exécutez `php bin/console.php status`, puis `php bin/console.php install-key`. Lancez `php -d memory_limit=256M -d upload_max_filesize=64M -d post_max_size=70M -S 127.0.0.1:8787 router.php`, puis ouvrez `http://127.0.0.1:8787`. Le fichier `Lancer-Mac.command` fait la même chose ; rendez-le exécutable avec `chmod +x Lancer-Mac.command` si nécessaire.
- **Docker déjà disponible** : dans le dossier du CMS, exécutez `docker compose up -d --build`, puis `docker compose exec cms php bin/console.php install-key`. Ouvrez `http://127.0.0.1:8787`. Les données et modules sont conservés dans deux volumes Docker. `docker compose stop` arrête le CMS sans supprimer ces volumes.

Le serveur PHP intégré est réservé aux essais locaux. En Docker, le port est lié à 127.0.0.1. Pour héberger en ligne, configurez un serveur HTTPS et un proxy de confiance. Le chemin Docker est fourni mais n’a pas été exécuté dans l’environnement de validation de ce pack.

## Fonctions livrées

| Espace | Fonctions |
| --- | --- |
| Vue d’ensemble | Compteurs calculés, contenus récents, état de l’installation, recherche Ctrl/⌘ K |
| Articles et pages | Création, édition HTML simple, aperçu serveur, FR/EN avec repli, slugs, tags, résumés, images, brouillons, publication, programmation UTC, contenus privés, épinglage, menu, ordre, duplication, corbeille |
| Révisions | 15 versions antérieures par contenu ; protection contre l’écrasement d’une version modifiée dans un autre onglet |
| Profil public | Couverture, avatar, nom, bio, liens, localisation, contact, navigation et fil, recherche et partage |
| Médias | Import JPG/PNG/WebP/PDF, reconstruction des images en PNG, texte alternatif, accès public ou privé, lien, téléchargement et suppression des médias non référencés |
| Thèmes | Profil Or et Profil Graphite ; activation, installation ZIP au format natif et suppression des thèmes inactifs |
| Extensions | Activation/désactivation, suppression des modules inactifs sans effacer leurs données, installation ZIP sans activation ; API actions, filtres, shortcodes et pages d’administration |
| Atelier personnel | Notes privées, tâches textuelles `[ ]` / `[x]`, raccourcis, widget sur le tableau de bord |
| Connexion PASS | URL HTTPS configurable, bouton de profil et shortcode `[pass]` ; logo de votre archive PASS |
| Référencement | Méta-description, canonique, Open Graph, sitemap XML et RSS quand l’extension SEO est active et l’URL canonique renseignée |
| Sécurité | Un seul propriétaire, pas d’inscription, mots de passe hachés, sessions privées expirantes, CSRF, contrôle d’origine, limitations de connexion, récupération, journal limité à 250 événements |
| Outils | Sauvegarde ZIP portable avec médias, restauration avec confirmation et mot de passe, trois points de restauration, import WordPress WXR 1.2 |

La programmation est évaluée à chaque lecture : un article publié avec une date future devient visible à cette date, sans tâche cron ni envoi d’e-mail.

Les médias marqués publics sont accessibles à toute personne disposant du lien, même s’ils sont utilisés dans un brouillon. Choisissez « privé » pour un document de travail ; ne l’utilisez pas comme couverture publique. Un média privé demande une session propriétaire. Les PDF sont téléchargés en pièce jointe ; ils ne sont pas rendus dans une fenêtre PDF intégrée.

## Sauvegarde et transfert

« Outils & sauvegardes » exporte les contenus, le profil, les médias et les données des extensions. Les identifiants, sessions, secrets serveur et fichiers exécutables ne sont pas exportés. Les sauvegardes contiennent des brouillons et notes privées : conservez-les dans un lieu privé.

Limites : 8 Mo de données éditoriales par ZIP, 64 Mo de données décompressées au total, 2 000 médias et 10 000 contenus restaurés. Pour un site plus grand, sauvegardez le dossier du CMS et son stockage complet par SFTP, idéalement pendant une maintenance. La base de données éditoriale est plafonnée à 32 Mo et réécrite atomiquement à chaque modification : ce moteur vise un profil personnel et un trafic modéré.

Une restauration remplace la liste des contenus et médias, conserve l’accès propriétaire, ne charge aucun code depuis le ZIP et crée d’abord un point complet. Les versions antérieures des articles ne sont pas réinjectées lors d’une restauration portable ; les corps, statuts, tags et paramètres éditoriaux le sont. Les fichiers de médias non référencés après restauration restent dans le stockage pour éviter une suppression irréversible. Un identifiant média existant ne peut pas recevoir un contenu binaire différent.

Les trois points stockés sur le même serveur facilitent un retour en arrière. Téléchargez aussi une copie indépendante. Ils ne protègent pas contre la perte de l’hébergement.

## WordPress et PASS

Le CMS reprend les concepts de thèmes, extensions, points d’accroche et contenus de WordPress ; **ce n’est pas WordPress ni une couche de compatibilité de son API**. Un ZIP WordPress, les appels `wp_*`, la base `wp_options` ou `$wpdb` demandent un portage. L’installateur de modules refuse les archives sans manifeste natif `cms.json`.

Sources de référence examinées : `voilavoila-pass-theme-1.5.0.zip`, `PASS-WordPress-Swizzonic-v1.12.1.zip` et `vv-admin-pass.zip` (PASS Administration 2.0.0). La présentation sombre et dorée, le profil, les raccourcis, l’atelier et les protections ont servi de base fonctionnelle. Ces archives ne sont pas embarquées comme dépendances.

L’import WXR ajoute uniquement les pages/articles. Le réglage par défaut est « brouillon ». Aucun fichier distant n’est téléchargé. Les pièces jointes, comptes, commentaires, paramètres, modèles, blocs interactifs et données métier des plugins sont exclus. Il faut réimporter les médias manuellement, réinsérer leurs liens et adapter les shortcodes. Les textes légaux présents dans vos pages peuvent ainsi être importés puis relus ; le CMS n’invente pas de mentions à votre place.

Le module natif **Connexion PASS** conserve la billetterie sur son installation actuelle et ouvre son URL. Il n’émet aucun billet, ne gère aucun paiement, ne migre aucune commande et n’ajoute pas de compte client au CMS. Les fonctions de sécurité de PASS liées aux clients, organisateurs ou paiements restent dans PASS.

## Mises à jour et récupération

- Avant une mise à jour, exportez une sauvegarde et copiez les fichiers du CMS.
- Par SFTP, remplacez les fichiers du moteur, sans remplacer `storage`, vos modules personnalisés ou les volumes de données. Ne copiez jamais un dossier `storage` de démonstration sur votre site.
- Pour une mise à jour d’une extension personnalisée, désactivez-la, sauvegardez son dossier, remplacez ses fichiers par SFTP, puis réactivez-la. L’installation ZIP ne remplace pas un module existant.
- Si une extension plante pendant son chargement, elle est désactivée et un événement est journalisé. `admin.php?view=plugins&safe=1` ouvre l’administration en sautant les extensions pour cette requête. Si nécessaire, `php bin/console.php safe-mode` désactive toutes les extensions et rétablit Profil Or.
- Accès perdu : utilisez le code hors ligne depuis la page de connexion. Le code ne fonctionne qu’une fois et un nouveau code est affiché. Avec l’accès serveur : `php bin/console.php reset-owner`. Il révoque toutes les sessions.
- Aucun mécanisme automatique de mise à jour distante ou d’envoi d’e-mail n’est activé.

Voir `docs/ARCHITECTURE.md`, `docs/SECURITE.md`, `sdk/API.md` et `docs/VALIDATION.md`.

## Références officielles

- https://fr.wordpress.org/download/
- https://developer.wordpress.org/plugins/hooks/
- https://developer.wordpress.org/themes/classic-themes/basics/template-hierarchy/
- https://wordpress.org/documentation/article/tools-export-screen/
- https://www.php.net/manual/fr/function.password-hash.php
- https://www.php.net/manual/en/session.security.ini.php
- https://www.php.net/supported-versions.php
