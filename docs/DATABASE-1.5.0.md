# Base interchangeable / Interchangeable database

Le cœur utilise `Store::read()` et `Store::change()`. `Store` délègue ses transactions et enregistrements au contrat `DatabaseDriver` version 1. Le SQL, les différences de schéma et l’ouverture PDO se trouvent dans `core/Database.php`, sans dépendance SQL dans les écrans ou les extensions natives. Le schéma d’état reste 2 ; les contenus et révisions restent des lignes normalisées, avec champs d’extension conservés dans `extra`.

## Configuration

| Variable serveur | Valeur |
| --- | --- |
| `VV_DB_DRIVER` | `sqlite` par défaut ; `mysql` ou `pgsql` pour un autre adaptateur |
| `VV_DB_DSN` | DSN PDO du moteur choisi ; non utilisé pour SQLite |
| `VV_DB_USER` | Utilisateur de la base distante |
| `VV_DB_PASSWORD` | Mot de passe de cet utilisateur |
| `VV_STORAGE_PATH` | Stockage privé des médias, sessions, clés et sauvegardes ; toujours nécessaire |

Exemples de DSN : `mysql:host=127.0.0.1;port=3306;dbname=voilavoila;charset=utf8mb4` ou `pgsql:host=127.0.0.1;port=5432;dbname=voilavoila`. Utilisez une base/un schéma **dédié**, des identifiants serveur hors des fichiers publics et le module PDO approprié. Pour une connexion réseau distante, configurez le chiffrement et la vérification du certificat selon le pilote/hébergeur. La présence d’un adaptateur n’est pas une qualification de toutes les versions de serveurs : consultez `VALIDATION-1.5.0.md`.

La création d’une base non SQLite exige qu’elle soit vide. Le CMS refuse de mêler ses tables à celles d’un autre produit. MySQL emploie InnoDB/utf8mb4 ; PostgreSQL utilise UTF-8. Le DDL est séparé des transactions éditoriales. Les lectures et écritures restent transactionnelles ; le verrou de fichier conserve le fonctionnement mono-instance du CMS.

## Changer de moteur sans recréer les contenus

1. Mettez le site en maintenance et arrêtez le cron et les écritures métier. Sauvegardez le code, tout le stockage privé et chaque base propre à une extension.
2. Sur la configuration source : `php bin/console.php db:export /chemin/prive/transfert.php`.
3. Préparez une **base cible neuve, non installée**, un stockage privé cible et le pilote PDO choisi. Copiez les médias, la clé OTP et les fichiers privés nécessaires. Ne copiez pas `cms.sqlite` pour le transformer en une base MySQL/PostgreSQL.
4. Configurez les variables de la cible, puis : `php bin/console.php db:import /chemin/prive/transfert.php --confirm=IMPORTER`.
5. Vérifiez `php bin/console.php db:info`, les nombres de contenus/révisions, une connexion propriétaire avec OTP, les médias et les modules. Ouvrez seulement ensuite le nouveau site. Gardez la source et ses sauvegardes jusqu’à validation.

Le transfert est un export **complet et privé de la base**, incluant les mots de passe hachés, secrets et paramètres OTP ; ce n’est pas une sauvegarde éditoriale à partager. Il utilise une enveloppe de refus PHP, un fichier 0600 et une empreinte d’intégrité. L’import refuse une cible déjà installée. Le fichier ne contient pas les octets médias, la clé de chiffrement OTP stockée à part, les sessions physiques, le code ou les bases propres aux plugins. Copiez ces éléments séparément. Une empreinte d’intégrité ne rend pas digne de confiance un fichier provenant d’un tiers.

## Extension future Pro

Une autre implémentation de `DatabaseDriver` peut remplacer PDO via la fabrique, sans modifier les écrans ou l’API des modules. Le contrat porte sur `initialized/begin/commit/rollback/read/write/location/info`, avec atomicité de `change` et conservation des champs inconnus. Il ne constitue pas une couche multitenant ou une promesse de déploiement actif sur plusieurs serveurs. Une base dédiée à PASS ou à un autre module reste sous la responsabilité de ce module ; elle ne migre pas automatiquement en changeant `VV_DB_DRIVER`.

English: SQLite remains the default. SQL is isolated behind a versioned driver contract with PDO adapters and a complete private transfer command. Changing drivers requires an empty destination, controlled transfer and separate copying of media/OTP keys/module databases. This prepares future Pro work without adding public accounts, multitenancy or distributed deployment guarantees.
