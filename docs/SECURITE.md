# Protections et périmètre — 1.1

## Accès et installation

- La première visite ne crée aucun mot de passe par défaut. L’installateur génère une clé aléatoire de 192 bits, accessible uniquement dans un fichier privé par SFTP ou console.
- Un seul propriétaire. Aucun formulaire d’inscription, compte invité, rôle supplémentaire ou endpoint de publication anonyme.
- Le mot de passe est haché avec Argon2id quand PHP le fournit, sinon avec `PASSWORD_DEFAULT`. Limite de 14 à 72 octets pour éviter une troncature bcrypt.
- Le code de récupération est aléatoire, haché, à usage unique et remplacé après utilisation. Il est affiché une seule fois. L’e-mail de gestion n’est pas publié ni utilisé comme mécanisme automatique de réinitialisation.
- Sessions : régénération à la connexion et au changement d’accès, mode strict, cookies uniquement, `HttpOnly`, `SameSite=Strict`, `Secure` en HTTPS, expiration après 30 minutes d’inactivité ou 12 heures absolues. Le changement d’accès invalide les autres sessions via un identifiant d’époque serveur.
- HTTPS obligatoire pour l’administration hors localhost. Le CMS ne fait confiance aux en-têtes de proxy que pour les IP exactes configurées dans `VV_TRUSTED_PROXIES`.

## Écritures et rendu

- Toute action de gestion exige POST, session propriétaire et CSRF. Contrôle d’Origin lorsqu’il est fourni et refus des requêtes `Sec-Fetch-Site: cross-site`.
- Limites par IP et globales pour connexion et récupération. Réauthentification limitée pour les actions sensibles.
- Les suppressions définitives, import/restauration, installation de code et changements d’accès demandent le mot de passe actuel. Une purge éditoriale et une restauration créent un point de sauvegarde avant de modifier l’état.
- Les contenus HTML passent par DOM et une liste de balises/attributs autorisés. Suppression des scripts, CSS, événements, formulaires et intégrations. Les images éditoriales référencent uniquement `media.php`.
- Sorties texte échappées. Aucun `eval` ni inclusion de fichier calculée à partir d’un paramètre public arbitraire.
- CSP sans code JavaScript inline dans les interfaces natives, anti-framing, `nosniff`, permissions navigateur réduites, HSTS sur HTTPS, cache privé désactivé pour les pages administratives.

## Médias et fichiers

- Formats autorisés : JPG/PNG/WebP/PDF, 8 Mo maximum. Vérification extension et MIME réels, noms exécutables refusés.
- Les originaux sont conservés sans conversion ni réduction : format, extension, octets et métadonnées EXIF restent identiques. MIME, dimensions, plafond de pixels et décodage GD sont vérifiés avant publication. Aucune image n’est exécutée ou incluse comme PHP. Les métadonnées de localisation éventuellement présentes dans un original ne sont pas retirées automatiquement.
- Les PDF ne sont pas désarmés ni analysés par antivirus ; ils sont servis en téléchargement avec des en-têtes restrictifs. Le CMS ne prétend pas garantir l’innocuité de leur contenu.
- Les médias privés nécessitent l’accès propriétaire. Les médias publics sont accessibles par leur lien et peuvent être mis en cache cinq minutes. Le retrait d’un fichier ne peut pas retirer les copies déjà téléchargées.
- La base SQLite, les médias originaux et les journaux texte n’ont pas d’enveloppe PHP. Ils résident dans le stockage privé : Apache `.htaccess`, Nginx et le routeur local fourni en refusent l’accès direct. Avec Nginx, appliquez explicitement la configuration fournie avant d’exposer le site. `VV_STORAGE_PATH` permet aussi de placer le stockage hors de la racine Web. Sessions, sauvegardes internes et archives historiques conservent leur enveloppe PHP. Ne servez jamais le pack comme un simple dossier statique.

## Extensions et sauvegardes

- Les ZIP sont contrôlés avant extraction : chemins absolus, remontées `..`, doublons, liens symboliques, entrées chiffrées, fichiers cachés, tailles, nombre de fichiers et formats sont refusés selon le contexte.
- Les modules exigent un manifeste natif API 1 et un contrôle d’accès direct dans leurs fichiers PHP. Ils sont installés sans activation et ne remplacent pas un dossier existant.
- Une extension PHP activée possède les privilèges du processus serveur. Le manifeste et les vérifications du ZIP ne constituent pas un bac à sable, une signature d’auteur ou une analyse antivirus. Aucun plugin WordPress n’est exécuté automatiquement.
- Un échec PHP capturable pendant le chargement d’une extension la désactive. Un plugin qui termine volontairement le processus, boucle ou manipule des fichiers peut nécessiter un retrait SFTP ou la console. Le mode de dépannage saute les plugins ; il ne transforme pas leur code en code fiable.
- Les sauvegardes portables ne transportent pas d’identifiants ni de code. Les contenus sont de nouveau validés à l’import. Les binaires sont vérifiés contre leur empreinte et leur type. L’import WordPress refuse les DTD/entités externes et ne télécharge aucune URL.
- Pas d’éditeur de PHP en ligne, de mise à jour distante automatique ou d’exécution de commande provenant du Web.

## Limites de validation

Le pack est vérifié par des tests fonctionnels et des cas d’entrées invalides décrits dans VALIDATION.md. Il ne revendique ni audit externe, ni certification, ni garantie d’absence de vulnérabilité. La maintenance de PHP, du serveur HTTPS et des extensions ajoutées relève du déploiement réel. Un hébergement et des sauvegardes sains restent nécessaires.

## SQLite, migration et journaux

- Les pages/articles sont des lignes SQL, leurs révisions une table liée. Les écritures utilisent des requêtes préparées et une transaction ; une erreur annule les modifications de la transaction.
- La migration conserve le propriétaire, ses secrets, réglages, contenus, révisions et données d’extensions. L’ancienne source est copiée avant validation, puis les anciennes enveloppes média sont archivées. Les identifiants et liens médias restent stables.
- Les journaux d’accès et d’erreurs sont deux fichiers texte privés distincts. L’accès conserve méthode, chemin sans query string, statut HTTP, IP directe, durée et identifiant de requête. Il ne copie ni corps POST, mot de passe, cookie ou code de récupération. Les erreurs de modules PHP sont consignées côté propriétaire, sans trace publique.
- Les événements métier de gestion, comme une connexion ou un enregistrement, restent dans le journal d’activité SQLite. Ils ne sont pas confondus avec le journal technique d’accès.
- Rotation à 5 Mo et trois archives par type. Lecture/téléchargement depuis Sécurité après authentification ; téléchargement en POST avec CSRF. Les logs n’entrent pas dans la sauvegarde portable.
- Le journal applicatif commence après le chargement de PHP. Les requêtes bloquées par Apache/Nginx, les ressources statiques, et une erreur de syntaxe empêchant le démarrage du moteur relèvent du journal du serveur/hébergeur.
