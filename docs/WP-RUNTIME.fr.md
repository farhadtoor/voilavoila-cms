# WordPress Engine 0.2 — thèmes et plugins

Le moteur optionnel exécute **le vrai WordPress**, ses thèmes et ses plugins, dans un processus PHP-FPM séparé. Le petit module CMS garde l’identifiant `wp-runtime` pour mettre à jour 0.1. Le pack hors ligne complet inclut WordPress 7.1.3 et WooCommerce 11.2.1 officiels, leurs sommes de contrôle et licences. Le CMS natif reste PHP + SQLite ; aucun moteur n’est installé ni activé sans votre choix.

La gestion éditoriale, le propriétaire, les médias natifs et les autres fonctions restent dans le CMS. Les thèmes WordPress lisent une **copie** des pages/articles publiés. Les données métier propres aux plugins, dont les produits, paniers, clients et commandes WooCommerce, restent dans une base WordPress dédiée : ces API et tables sont nécessaires au fonctionnement des modules. Une émulation de quelques fonctions PHP ne suffirait pas.

## Installation

1. Sauvegardez le code, le stockage natif et, si existant, la base et les fichiers WordPress. La mise à jour native 1.3.1 n’est nécessaire que pour l’intégration OTP ; Engine 0.2 peut fonctionner sur 1.3.0.
2. Installez le **petit** `VoilaVoila-WP-Engine-0.2.0.zip` dans Extensions. Si le dossier `content/plugins/wp-runtime/` existe déjà, mettez à jour ses fichiers par SFTP, sans supprimer son moteur privé. Le pack hors ligne volumineux n’est pas un ZIP de module natif à envoyer dans ce gestionnaire.
3. Prévoyez deux origines HTTPS sous votre contrôle : `https://cms.example.com` pour le CMS et `https://legacy.example.com/compat` pour le moteur. Les hostnames de l’administration native et de WordPress doivent être distincts en production. Les cookies CMS ne sont jamais relayés au worker.
4. Créez deux arbres privés **distincts**, hors webroot : par exemple `/srv/voilavoila-private/native` et `/srv/voilavoila-private/engine`. Créez une base MySQL/MariaDB vide et dédiée, avec un compte SQL limité à cette base. Installez PHP-FPM et les modules WordPress dont `mysqli`. Prévoir au moins 256 Mio pour WordPress/WooCommerce. Les prérequis actuels de WordPress et de chaque module restent applicables.
5. Depuis le pack lourd, transférez `private-inputs/wordpress-7.1.3.zip` et `private-inputs/wordpress-verification.php` dans un dossier privé de votre serveur. Le module épingle leurs SHA-256 ; ces fichiers précis peuvent être utilisés hors ligne sans la limite de fraîcheur de 24 h des nouveaux téléchargements. Cela vérifie la cohérence avec le paquet préparé, pas l’absence de correctifs WordPress plus récents. Vérifiez les mises à jour de sécurité avant production.
6. Copiez [wp-connector.php.example](examples/wp-connector.php.example) hors webroot en `wp-connector.php`. C’est du JSON précédé d’une garde PHP, jamais un fichier à inclure avec `require`. Adaptez chemins, URL et socket ; générez un secret avec `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`. Réglez `verification_bundle` sur le fichier privé transféré. Configurez le pool CMS et la CLI :

```sh
export VV_STORAGE_PATH=/srv/voilavoila-private/native
export VV_WP_CONNECTOR=/srv/voilavoila-private/wp-connector.php
export VV_CANONICAL_ORIGIN=https://cms.example.com
```

7. Activez Engine et préparez WordPress depuis le panneau avec le ZIP, les identifiants SQL et `INSTALL`. Variante CLI :

```sh
php content/plugins/wp-runtime/bin/engine.php prepare /private/wordpress-7.1.3.zip < /private/db-input.json
php content/plugins/wp-runtime/bin/engine.php status
```

Le JSON privé contient `db_host`, `db_name`, `db_user`, `db_password`. Ne passez pas de mot de passe dans les arguments shell. La préparation refuse d’écraser un moteur existant. Le CMS ne se connecte pas lui-même à SQL.

8. Adaptez [wp-fpm-pool.conf](examples/wp-fpm-pool.conf). Le worker doit avoir un UID distinct du CMS ou un conteneur restreint. Le code et la configuration sont lisibles mais non modifiables par ce worker ; seuls `vv-data/` et `wp-content/uploads/` lui sont accessibles en écriture. Accordez au CMS la lecture des uploads par groupe/ACL. Le socket Unix est privé et réservé au CMS.
9. Ajoutez les réécritures `/compat/` et `/assets/{ui,mod,core,admin,media}/`. Apache : `.htaccess` fourni ; Nginx : [wp-nginx.conf](examples/wp-nginx.conf) et les interdictions du [vhost natif](nginx.conf). Les paramètres `VV_*` doivent également être fournis au handler PHP natif. Aucun vhost public ne doit servir directement le dossier WordPress ou son socket. Vérifiez les interdictions par HTTP.
10. Ouvrez le ticket propriétaire depuis **Thèmes et plugins WP**. Le premier accès initialise la base dédiée vide. L’administration WordPress présente les thèmes, plugins et écrans métier des plugins ; ses menus éditoriaux/propriétaire ordinaires sont masqués pour garder le CMS comme interface principale.

Les chemins `/private/...` sont des exemples à remplacer. Les ZIP officiels et les données installées restent hors du dépôt Git.

## Installer les thèmes et plugins

Dans le panneau Engine : choisissez le type de module, son ZIP de confiance et confirmez `INSTALL`, puis activez-le depuis le vrai panneau WordPress. Variante CLI :

```sh
php content/plugins/wp-runtime/bin/engine.php theme /private/theme.zip
php content/plugins/wp-runtime/bin/engine.php plugin /private/woocommerce-11.2.1.zip
```

Le ZIP doit contenir un seul dossier en minuscules. L’installateur contrôle chemins, liens, doublons, taille et type d’archive, puis enregistre un manifeste SHA-256. La lecture se fait fichier par fichier : WooCommerce n’est plus limité aux 1 200 entrées du prototype. Limites internes : 20 000 entrées, ZIP de 100 Mio, 384 Mio décompressés, 16 Mio par fichier ; le panneau limite son upload à 64 Mio et reste soumis aux limites PHP de l’hébergement. Pas d’écrasement automatique d’un module existant. Déployez les mises à jour avec sauvegarde et intervention du déployeur.

## Deux profils

**Strict**, par défaut : seules les actions publiques AJAX/REST explicitement déclarées et typées dans les manifestes sont relayées, comme en 0.1. Pour les petits modules ayant peu d’actions publiques.

**Compatibilité WordPress complète**, à activer avec réauthentification : restitue les méthodes REST, corps JSON, AJAX, `admin-post.php`, sessions clients, cookies et en-têtes de panier/nonce requis par des plugins tels que WooCommerce. Les permissions, nonces, signatures et validations métier sont ceux de WordPress et du plugin. Le CMS ne remplace pas ces contrôles par des stubs. L’administration native, les fichiers du cœur, XML-RPC et le cron public restent fermés. La connexion administrative WordPress interactive exige le ticket propriétaire CMS ; les cookies privilégiés périmés ne conservent pas un accès REST administratif après révocation native. Les clients boutique et identifiants API de services ont leur authentification WordPress propre.

Le profil complet expose les fonctionnalités publiques des plugins installés ; il demande des modules fiables et à jour. L’isolation du processus protège le CMS, elle ne corrige pas une vulnérabilité interne à un plugin ou une configuration de paiement.

Les cookies relayés sont ramenés à l’hôte WordPress ; les noms réservés au CMS sont refusés. Les statuts HTTP sont conservés, y compris les `201` de création. Les ressources statiques passent par une liste d’extensions, des chemins validés et une limite de 16 Mio, sans exécution PHP. Les requêtes applicatives et uploads relayés sont limités à 8 Mio, les réponses dynamiques à 16 Mio. Les gros médias, téléchargements numériques, routes PHP directes ou extensions nécessitant d’autres protocoles doivent être qualifiés et éventuellement adaptés.

## Contenus natifs dans les thèmes WP

```sh
php content/plugins/wp-runtime/bin/engine.php sync
```

Le bouton **Synchroniser les contenus publiés** fournit la même opération. Elle copie titre, slug, texte HTML, résumé et date dans des posts/pages WP identifiés par l’ID natif. Les liens médias relatifs deviennent des liens vers le CMS. Une seconde synchronisation est idempotente. Les copies d’éléments devenus non publics sont remises en brouillon. Produits, commandes et autres types métier ne sont pas touchés.

Relancez après publication, ou planifiez cette commande dans le cron de l’hébergeur avec les mêmes variables d’environnement. La copie n’est pas une synchronisation bidirectionnelle : modifier un miroir dans WP ne modifie pas SQLite et sera remplacé à la prochaine synchronisation. Les shortcodes natifs, traductions complexes, révisions, menus personnalisés et métadonnées de constructeurs de pages ne sont pas automatiquement convertis. Conservez leurs pages natives ou préparez une migration dédiée. L’import WXR reste disponible pour basculer progressivement du WordPress d’origine vers le CMS.

## WooCommerce et paiement

Le pack fournit le WooCommerce officiel en option ; il n’est pas activé automatiquement. Installez-le, ouvrez son assistant, puis configurez produits, taxes, livraison et paiement dans ses écrans métier. Passez sa visibilité de « Coming soon » à « Live » lorsque vous souhaitez ouvrir la boutique. Vérifiez aussi son réglage d’indexation avant production. Activez le profil complet. Les commandes et stocks demeurent dans SQL côté moteur, pas dans SQLite natif.

Le test livré couvre WooCommerce 11.2.1 : activation, produit réel, API Store, refus d’une mutation sans nonce, panier par cookie et Cart-Token, commande avec paiement hors ligne, persistance et diminution du stock. Les e-mails y sont interceptés et aucun paiement réel n’est effectué. Cela ne qualifie pas Stripe, PayPal, SumUp, la fiscalité, les abonnements, la livraison ou tout module tiers associé.

Pour un paiement externe, configurez ses clés et ses webhooks sur l’origine WordPress, testez son mode sandbox et autorisez les services nécessaires :

- `outbound_hosts` dans le fichier privé `engine-config.php` autorise l’API HTTP WordPress ; complétez les règles réseau du système/conteneur.
- `browser_origins` dans le connecteur autorise des **origines HTTPS exactes** dans le CSP de compatibilité (scripts, connexions et frames).
- `redirect_origins` autorise des origines HTTPS exactes de redirection, par exemple la page de paiement hébergée réellement utilisée par votre prestataire.

Les listes sont vides par défaut ; pas de joker. Ne copiez pas une liste arbitraire de domaines : utilisez ceux de la documentation de votre intégration. Le CSP assoupli (inline/`eval`) ne s’applique qu’à l’origine WordPress ; le CSP CMS reste strict. Aucun cache HTML partagé du moteur n’est ajouté, afin de conserver les nonces et sessions.

## Mettre à jour un moteur 0.1

Sauvegardez SQL, moteur complet, uploads, configuration et secrets. Arrêtez le pool privé, déployez les nouveaux fichiers du petit module CMS, puis actualisez les modèles du pont :

```sh
php content/plugins/wp-runtime/bin/engine.php refresh --confirm=REFRESH
```

Cette commande remplace les fichiers `vv-*` du pont et le modèle `wp-config.php`, en conservant les identifiants/salts stockés dans `engine-config.php`. Elle ne remplace pas le cœur WordPress, les modules ou SQL. Redémarrez le pool. Les constantes de code doivent être relues après les changements privés ; ne vous reposez pas sur un cache OPcache ancien. Le panneau propose la même action avec `REFRESH`.

Les mises à jour du cœur WordPress se font séparément par déploiement contrôlé et sauvegarde : elles ne sont pas automatisées par 0.2. Pour une autre version officielle, utilisez `tools/fetch_wordpress_fixture.py` et son bundle frais (24 h). Les versions épinglées du pack ne signifient pas qu’aucun correctif ultérieur n’existe.

## Exploitation, portabilité et limites

Sauvegardez **séparément** la base SQL, tout le moteur, uploads, configuration et secrets ; le ZIP éditorial natif n’en contient aucun. Pour cloner un WordPress existant, conservez son préfixe, liez l’administrateur à `owner_login`, remplacez les salts et adaptez les URL sérialisées avec `wp search-replace --dry-run`. Arrêtez/redémarrez le pool et testez sur copie. Les données commerce ne sont pas automatiquement converties en données natives.

Les helpers privés `vv-Console.php version`, `upgrade` et `cron` restent disponibles, à exécuter comme utilisateur restreint. `upgrade` met à jour le schéma SQL, pas le code. Programmez cron explicitement.

`open_basedir` et les constantes HTTP WordPress ne sont pas une sandbox complète. Une quarantaine stricte exige des UID/montages restreints et un filtrage réseau système ; du PHP tiers peut utiliser d’autres fonctions réseau. Sur un mutualisé ne permettant pas cette séparation, gardez le CMS natif ou déportez l’infrastructure du moteur. Aucune qualification OVH/Infomaniak, UID/conteneur ou TLS réel n’est revendiquée par les tests locaux en loopback.

Il s’agit d’un moteur de compatibilité expérimental, pas d’une garantie sur l’ensemble des thèmes/plugins WordPress ou d’un gain de vitesse chiffré.

[English](WP-RUNTIME.md) · [WordPress](https://wordpress.org/about/requirements/) · [WooCommerce](https://woocommerce.com/document/server-requirements/) · [Nonces Store API](https://developer.woocommerce.com/docs/apis/store-api/nonce-tokens/) · [Limites PHP](https://www.php.net/manual/en/ini.core.php#ini.open-basedir)
