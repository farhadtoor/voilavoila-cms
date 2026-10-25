# Protections et confidentialité

[English](../en/security.md) · [Signalement privé](../../SECURITY.md)

## Accès propriétaire

L’installation exige une clé aléatoire dans un fichier serveur, sans mot de passe par défaut. Un propriétaire, aucune inscription. Hachage Argon2id si disponible, sinon algorithme PHP par défaut ; mot de passe de 14 à 72 octets. Code de récupération aléatoire, haché, à usage unique et remplacé. Aucun e-mail automatique de récupération.

Sessions strictes par cookie, HttpOnly, SameSite=Strict et Secure en HTTPS ; expiration après 30 minutes d’inactivité ou 12 heures au total. Les changements d’accès révoquent les autres sessions par une époque serveur. Écritures POST avec propriétaire, CSRF et contrôle d’origine/contexte intersite. Réauthentification des actions sensibles et limites de connexion/récupération. Le protocole HTTPS transmis n’est accepté que depuis les IP exactes des proxys déclarés fiables.

## Contenus, fichiers et extensions

Le HTML éditorial passe par DOM et une liste autorisée ; scripts, événements, formulaires, CSS inline et images distantes arbitraires sont retirés. Les textes sont échappés. CSP, anti-framing et `nosniff` protègent les pages natives. Ces mesures réduisent des risques précis sans certifier les sorties d’une extension quelconque.

Les imports vérifient noms, MIME, dimensions/pixels, limites et décodage GD. La 1.1 conserve les originaux et EXIF : elle ne retire pas les coordonnées GPS, ne désarme pas tout contenu possible et ne prétend pas analyser les fichiers par antivirus. Les PDF sont téléchargés sans désarmement. Un média public est accessible par URL et peut être en cache ; choisissez privé pour un brouillon confidentiel.

SQLite, journaux et médias bruts n’ont **aucun préfixe de protection PHP**. Le dossier privé doit être refusé par Apache/Nginx ou déplacé hors racine Web avec `VV_STORAGE_PATH`. Le refus par le routeur local ne prouve pas la configuration du serveur de production. Vérifiez sur l’hébergement réel.

Thèmes et extensions PHP sont du code de confiance avec les droits du processus. Les contrôles manifeste/API/ZIP ne sont ni un bac à sable ni une signature d’auteur. Installez des sources relues. N’intégrez jamais jeton de déploiement, base ou code de récupération dans un module. Aucun éditeur PHP en ligne ou mise à jour distante automatique.

## Journaux et sauvegardes

`access.txt` contient date UTC, identifiant de requête, méthode, chemin sans paramètres, statut, IP directe et durée. Les valeurs de formulaire/URL et cookies sont exclus, mais chemins et IP peuvent rester sensibles. `error.txt` contient les erreurs PHP/applicatives capturées ; les messages tiers peuvent contenir des valeurs sensibles. Rotation à 5 Mio avec trois archives de chaque type. Adaptez conservation, permissions et anonymisation à votre site ; ne publiez pas les journaux complets.

Les ZIP portables excluent identifiants propriétaire, code exécutable, secrets serveur et journaux techniques ; ils incluent articles privés et notes d’extensions. Gardez-les privés. Les copies complètes du stockage contiennent aussi accès et secrets. Testez la récupération sur une installation jetable. Les points sur le même serveur ne remplacent pas une copie indépendante.

## Vérifications et limites

Le [guide de tests](tests.md) distingue rapports historiques et contrôles isolés actuels. Le routeur local bloque le stockage brut ; Apache/Nginx HTTPS réels, Docker de déploiement et lanceur macOS ne sont pas validés ici. Aucun test d’intrusion externe, audit indépendant ou garantie d’absence de défaut n’est annoncé.
