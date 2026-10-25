# Architecture et historique des versions

[English](../en/architecture.md) · [SDK natif](../../sdk/API.md)

Le moteur gère les données, autorisations et routes. Le thème rend un contexte de lecture. Les extensions ajoutent des comportements via des points explicites. Un propriétaire unique, aucune inscription ni API publique d’écriture. PHP, JavaScript et CSS sont fournis directement, sans compilation.

| Élément | Responsabilité |
| --- | --- |
| `index.php` | Profil/fil public, recherche, pages/articles, maintenance, aperçus et vrai 404 |
| `admin.php` | Écritures POST authentifiées et routage de l’administration |
| `install.php`, `login.php` | Installation unique avec clé serveur, connexion et récupération |
| `media.php` | Autorisation public/privé et diffusion des médias |
| `core/bootstrap.php`, `Security.php` | Prérequis, en-têtes, sessions, propriétaire, CSRF, origine et limites |
| `core/Store.php` | Schéma PDO SQLite, façade d’état, transactions, verrous et migration |
| `core/Content.php`, `Media.php` | Validation éditoriale, HTML autorisé, versions et originaux |
| `core/App.php`, `AdminViews.php` | Modules natifs, points d’accroche et interface |
| `core/Logger.php`, `Tools.php` | Rotation des journaux ; ZIP, sauvegarde/restauration et WXR |
| `content/themes`, `content/plugins` | Modules natifs avec manifeste `cms.json` API 1 |
| `storage` ou `VV_STORAGE_PATH` | Données privées modifiables de l’installation, exclues de Git |

## Lecture et écriture

La lecture publique sélectionne les contenus autorisés dont la date de publication est atteinte, puis le modèle `home`, `page`, `single` ou `404`, avec repli sur `index.php` du thème. Les aperçus privés exigent la session propriétaire. La programmation est évaluée à la lecture, sans tâche mail ou cron obligatoire.

Les écritures exigent propriétaire, HTTPS sauf localhost, POST, CSRF et contrôle d’origine. Les opérations sensibles redemandent le mot de passe. `Store::change()` verrouille, relit l’état, utilise `BEGIN IMMEDIATE`, applique les changements puis valide. Seules les lignes modifiées et leurs révisions sont réécrites. Un échec annule ensemble paramètres et contenus. SQLite utilise DELETE, synchronisation FULL, clés étrangères et attente maximale de cinq secondes. La façade d’état reste compatible avec les extensions natives ; aucune transaction imbriquée.

## Données

`contents` contient des colonnes pour le type, slug unique, statut, titres/résumés/corps FR/EN, dates, couverture, indicateurs, ordre, version et tags/métadonnées JSON. `revisions` référence les contenus avec suppression en cascade et conserve jusqu’à 15 instantanés. `cms_state` contient en JSON propriétaire, paramètres, métadonnées médias, extensions, données d’extensions, journal limité et restrictions ; les corps des contenus sont hors de cette ligne. Les identifiants sont aléatoires sur 96 bits.

Les médias sont des fichiers originaux sous identifiants générés et extension validée. Sessions et instantanés internes gardent le préfixe PHP de terminaison. Les journaux et SQLite n’ont pas cette enveloppe : permissions et refus d’accès serveur sont essentiels. Préférez un stockage privé hors racine Web.

## Comparatif

| Composant | 1.0.0 | 1.1.0 |
| --- | --- | --- |
| Données éditoriales | JSON protégé `state.php`, remplacement atomique | Tables PDO SQLite et état transactionnel |
| Images | PNG reconstruit, réduction éventuelle, enveloppe PHP | Octets JPG/JPEG/PNG/WebP originaux validés et extensions réelles |
| Journaux techniques | Pas de fichiers accès/erreur distincts | Fichiers texte accès/erreur et rotation indépendante |
| Modules | API native 1 | Même API native 1 |
| Transition | Première installation | Migration automatique et sources conservées |

Les deux archives originales sont gardées. Les commits importés décrivent des instantanés, pas la chronologie du développement ni les dates de publication d’origine. Préférez la version courante pour une installation neuve.

## Limites

Un seul hébergement PHP et trafic modéré ; l’état reste reconstruit en mémoire. Ne partagez pas le stockage entre serveurs avec des verrous réseau non fiables. Médias : 8 Mio par fichier, 8 000 000 pixels, 6 000 px par dimension et 2 000 fichiers. Sauvegarde portable : notamment 8 Mio de JSON éditorial, 64 Mio décompressés et 10 000 contenus restaurés. Relisez les validateurs avant d’augmenter ces plafonds.

Deux thèmes : Profil Or et Profil Graphite. Extensions : Atelier, SEO et Connexion PASS, limitée à un lien externe. L’import WXR 1.2 ajoute les pages/articles, par défaut en brouillon, évite les doublons d’un même export et ne récupère pas de fichiers distants. Aucun paiement, commande, compte client, compatibilité d’exécution WordPress, synchronisation cloud ou mise à jour distante automatique.
