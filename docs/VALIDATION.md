# Vérifications du pack 1.0.0

Date : 24 octobre 2026. Tests exécutés sur une installation isolée avec PHP CLI 8.3.6, DOM, mbstring, fileinfo, GD et ZIP, et un navigateur Chromium piloté automatiquement. Aucune donnée ou commande de la billetterie en ligne n’a été modifiée.

## Tests fonctionnels HTTP : 50 contrôles réussis

- Installateur, preuve d’accès au fichier privé, création du propriétaire, code de récupération, suppression de la clé et verrouillage de l’installation.
- Chargement des modules natifs, absence d’accès anonyme à l’administration, cookie HttpOnly, CSRF et refus d’une origine externe.
- Création d’un brouillon, nettoyage du HTML, confidentialité des brouillons, aperçu propriétaire et refus des aperçus anonymes.
- Publication, absence de bandeau d’aperçu public, révisions, conflit de version, programmation future et restauration d’une révision.
- Import d’une image réelle, reconstruction PNG, diffusion publique, confidentialité d’un média privé, refus d’un fichier exécutable déguisé.
- Changement de thème sans effacement de données, activation et configuration du lien PASS, rendu du bouton.
- Atelier privé : enregistrement de notes et absence de fuite dans le profil public.
- Refus d’un chemin ZIP `../`, installation native inactive, exécution d’un hook, refus de suppression d’un module actif, suppression d’un module inactif.
- Sauvegarde ZIP avec médias, exclusion des secrets du propriétaire, restauration du contenu et des dates, maintien de l’identité du propriétaire, point de restauration préalable.
- Import WXR en brouillon, déduplication d’un même export, refus des déclarations d’entités externes XML.
- Rendu FR/EN, vrai statut 404, refus d’un faux en-tête HTTPS venant d’un proxy non configuré.
- Changement de mot de passe, révocation d’une autre session, récupération par code, limitation des connexions, récupération possible avec un code valide malgré une limite de connexion atteinte.

## Interface navigateur : 29 contrôles réussis, aucune erreur JavaScript

Les écrans d’administration sont parcourus sur ordinateur (1 440 px) et mobile (390 px). Vérification des débordements horizontaux, des menus mobiles, de la palette Ctrl/⌘ K, des onglets linguistiques, de l’aperçu HTML nettoyé, de l’enregistrement d’un article depuis le navigateur et du rendu public français/anglais. Contrôle de conservation du texte de l’éditeur après déconnexion dans une autre requête.

Les captures d’écran de l’administration et du profil ont été inspectées visuellement pendant la préparation. Aucun screenshot contenant des identifiants ou des informations de paiement n’est livré.

## Vérifications du code et du paquet

- Analyse syntaxique de tous les fichiers PHP livrés.
- Analyse syntaxique des deux scripts JavaScript natifs.
- Contrôle de déplacement des références de médias entre sous-dossiers pour les sauvegardes portables.
- Vérification de l’archive ZIP finale et de ses empreintes SHA-256.
- Le dossier `storage` livré contient uniquement sa protection et son fichier index. Aucun compte, mot de passe, session, sauvegarde ou média de test n’est livré.

## Périmètre non exécuté

Le déploiement sur l’hébergement réel, Apache/Nginx avec certificat réel, Docker, le double-clic du lanceur sur macOS et le portage du moteur transactionnel PASS n’ont pas été exécutés dans cet environnement. Les modèles de déploiement et le guide sont fournis. Les tests ne constituent pas un audit externe de sécurité.
