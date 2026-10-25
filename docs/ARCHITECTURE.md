# Architecture du CMS

Le moteur fournit les données et les autorisations. Le thème reçoit un contexte de lecture. Les extensions ajoutent des comportements via des points explicites. Il n’existe qu’un propriétaire : aucune table d’utilisateurs, inscription publique, matrice de rôles ou API publique d’écriture.

| Dossier / fichier | Responsabilité |
| --- | --- |
| `index.php` | Lecture publique, profil, fil, recherche, pages/articles, vrai statut 404, aperçu privé, maintenance |
| `admin.php` | Authentification requise, contrôleur des écritures POST, routage des écrans, palette de recherche |
| `install.php` | Installation unique avec preuve d’accès SFTP/serveur et génération du propriétaire |
| `login.php` | Connexion et récupération à usage unique |
| `media.php` | Contrôle de visibilité et diffusion des fichiers |
| `core/bootstrap.php` | Prérequis, erreurs, en-têtes, fonctions d’extension, initialisation |
| `core/Store.php` | JSON, verrou interprocessus, remplacement atomique, quotas et journal |
| `core/Security.php` | Sessions privées, CSRF, origine, hachage, limitation et contrôle propriétaire |
| `core/Content.php` | Validation éditoriale, nettoyage HTML, slugs, versions et images |
| `core/App.php` | Thèmes, extensions, points d’accroche et contexte public |
| `core/Tools.php` | ZIP, contrôle des chemins, sauvegarde/restauration, import WXR |
| `core/AdminViews.php` | Interface de gestion |
| `content/themes/<id>` | `cms.json`, `index.php`, `style.css` et modèles facultatifs |
| `content/plugins/<id>` | `cms.json`, `plugin.php` et ressources facultatives |
| `storage/state.php` | Données privées protégées par un préfixe PHP de terminaison |
| `storage/media/<id>.php` | Binaire précédé du même préfixe ; lecture par `media.php` |
| `storage/sessions/<id>.php` | Session privée verrouillée avec préfixe PHP |
| `storage/backups/<date-id>.php` | Trois dernières sauvegardes complètes privées |
| `assets` | CSS, JS, icônes SVG et logo PASS fournis localement |
| `bin/console.php` | Récupération locale, état et mode de dépannage |

## Parcours de lecture

`index.php` ouvre l’état, charge les extensions actives, sélectionne un contenu publié et arrivé à échéance, puis choisit un modèle du thème. Le modèle demandé (`home`, `page`, `single`, `404`) retombe sur `index.php` s’il manque. Les pages privées et brouillons ne sont accessibles qu’en aperçu propriétaire authentifié. Il n’existe pas de compteur de visiteurs inventé ni de suivi tiers.

## Parcours d’écriture

`admin.php` vérifie la session, HTTPS ou localhost, la méthode POST, l’origine et le CSRF. Les opérations sensibles redemandent le mot de passe actuel. Les validateurs nettoient les données avant la transaction. `Store::change` prend un verrou exclusif, relit l’état courant, applique la modification et publie un fichier temporaire complet par renommage. Les versions des articles empêchent l’écrasement d’un onglet récent par un ancien. Les notes de l’Atelier disposent d’un contrôle de révision distinct.

## Modèle de données

L’état comprend `owner`, `settings`, `contents`, `media`, `plugins`, `plugin_data`, `audit`, `limits`, une version de schéma et un secret serveur. Les identifiants de contenus et médias sont aléatoires sur 96 bits. Les slugs sont uniques. Les révisions sont limitées à quinze par contenu, le journal à 250 entrées. Les limites de connexion utilisent des identifiants HMAC plutôt que des adresses IP en clair.

Le stockage par fichiers convient à ce CMS personnel et à un trafic modéré. Les appels concurrents d’écriture sont sérialisés. Ce n’est pas un moteur transactionnel multi-instance : ne partagez pas ce dossier entre plusieurs serveurs avec des verrous réseau non fiables. Pour une billetterie volumineuse, conservez le moteur PASS transactionnel dédié.

## Choix de déploiement

La version mutualisée ne dépend ni de MySQL ni de SQLite. Elle fonctionne sans réécriture des liens. Le dossier de données peut sortir de la racine Web avec `VV_STORAGE_PATH`. Les modules et thèmes ne contiennent pas les données éditoriales. Les sauvegardes portables transportent les données sans exécuter de PHP, et conservent l’identité du propriétaire de la destination.
