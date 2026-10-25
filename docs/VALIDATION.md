# Validation de VoilaVoila CMS 1.1.0

Tests du 24 octobre 2026 sur une installation isolée, PHP 8.3.6 avec PDO SQLite, DOM, mbstring, fileinfo, GD et ZIP. Aucun site ou événement en ligne n’a été modifié.

## Résultats

| Ensemble | Résultat |
| --- | --- |
| Parcours HTTP existants, adaptés à SQLite | 50 contrôles réussis |
| Migration, fichiers originaux, journaux et transactions | 35 contrôles réussis |
| Échec de migration, annulation puis reprise | 2 contrôles réussis |
| Interface navigateur ordinateur/mobile | 29 contrôles réussis, aucune erreur JavaScript |

Les rapports JSON sont fournis dans ce dossier. Les tests HTTP couvrent installation, accès propriétaire, CSRF, origine, brouillons, aperçus, publication, programmation, révisions, conflit entre onglets, médias privés, refus de fichiers déguisés, thèmes/extensions, sauvegardes, import WordPress, récupération et révocation des sessions.

## Vérifications propres à 1.1

- Migration depuis une base 1.0 avec contenus dans les différents statuts, révision antérieure et média enveloppé en PHP. Comparaison des identifiants, champs, statuts, dates, versions et révisions après lecture des tables SQLite.
- Vérification que les corps des articles/pages sont des colonnes de `contents`, et non une simple copie du JSON des réglages. Révisions dans une table liée. `PRAGMA integrity_check` réussi.
- Conservation du propriétaire, réglages et données d’extensions ; copie de l’ancienne base ; archivage des anciennes enveloppes média ; absence de réimport des anciennes données après initialisation.
- Envoi de JPEG, PNG et WebP réels. Comparaison octet par octet et SHA-256 entre fichier envoyé, fichier enregistré avec sa véritable extension et réponse de `media.php`.
- Vérification des liens hérités, de la confidentialité d’un JPEG privé et du refus d’accès direct aux originaux et au fichier SQLite par le routeur du serveur local.
- Erreur contrôlée d’une extension : message dans `error.txt`, absence dans `access.txt` et dans la page publique. Journal d’accès sans query string ni valeur secrète transmise en paramètre de test.
- Téléchargement authentifié de chaque fichier TXT, refus de lecture directe et rotation séparée du journal d’erreur à 5 Mo.
- Transaction provoquant volontairement un doublon de slug : retour intégral à l’état précédent des contenus et des réglages.
- Import d’une sauvegarde 1.0 avec fichiers `.bin` vers des fichiers aux extensions réelles, puis initialisation répétée sans écrasement des données SQLite récentes.
- Média manquant dans une ancienne installation : commande de migration en échec avec code de sortie non nul, base non initialisée, source intacte. Après restitution du fichier manquant, migration réussie.

## Interface et paquet

L’administration et le profil ont été parcourus à 1 440 px et 390 px : menus, palette Ctrl/⌘ K, onglets FR/EN, aperçu serveur, enregistrement et conservation du texte après expiration de session. Les pages Sécurité et Médias sont incluses dans le contrôle de débordement horizontal.

Tous les PHP livrés passent l’analyse syntaxique, ainsi que les JS natifs. Le paquet est contrôlé par CRC ZIP et manifeste SHA-256. Il ne contient aucun compte, session, journal, média de test ou base SQLite préremplie.

## Limites

Le déploiement sur l’hébergement réel, les configurations Apache/Nginx avec certificat réel, Docker et le double-clic du lanceur macOS n’ont pas été exécutés dans cet environnement. Les règles de refus d’accès direct à `storage` doivent être appliquées sur le serveur cible. Ces vérifications ne constituent pas un audit externe de sécurité.
