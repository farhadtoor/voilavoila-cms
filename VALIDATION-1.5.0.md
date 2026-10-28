# Validation locale de VoilaVoila CMS 1.5.0

Validation du 28 octobre 2026. Données synthétiques uniquement. Aucun site, compte hébergeur ou dépôt GitHub de production n’a été modifié.

## Environnement exécuté

- PHP 8.3.6, DOM, mbstring, fileinfo, tokenizer, PDO SQLite, GD, ZIP et OpenSSL.
- Apache 2.4.58 et Nginx 1.24.0 avec PHP-FPM, appels HTTP réels sur loopback, à la racine et en sous-dossier.
- Chromium 153 / Playwright, sans désactivation de la sécurité Web.
- MariaDB 10.11.14 avec PDO mysql pour les transferts entre moteurs et le contrat transactionnel.

Le conteneur de test ne permet pas de changer vers les UID www-data/nobody. Apache a été exécuté en processus local unique avec un shim de test limité à l’identification de l’utilisateur ; les modules HTTP, rewrite, accès, en-têtes et proxy FastCGI sont restés ceux du binaire Apache. Nginx et FPM ont utilisé des processus locaux adaptés au même conteneur. Ce contrôle valide les routes et configurations, pas la séparation des utilisateurs Unix d’un hébergement réel.

## Contrôles réussis

| Groupe | Contrôles | Couverture |
| --- | ---: | --- |
| Intégration native et navigateur | 51 | Connexion, écrans, CSRF, quarantaine, PDF, médias, navigation FR/EN, mises à jour/retour arrière, sauvegardes, transfert de base et interface mobile |
| Apache/Nginx et navigateur de production | 130 | Quatre variantes racine/sous-dossier, refus des chemins sensibles, contrôleurs, assets, SVG, sitemap, exceptions ciblées, icônes et tiroir mobile accessible |
| Protocole antivirus | 7 | INSTREAM, sain/infecté/indisponible, analyse obligatoire, nouvelle analyse à la libération et détection d’altération |
| SQLite ↔ MariaDB | 6 | État complet, Unicode, contenus/révisions, rollback atomique, refus des transactions imbriquées et aller-retour entre moteurs |
| Mise à jour 1.4.0 → 1.5.0 | 12 | Paquet vérifié, propriétaire/OTP, révisions, médias originaux, modules personnalisés, moteur retiré, maintenance, sauvegarde complète et restauration du point 1.4 |
| Import éditorial hors ligne | 5 | Prévisualisation en quarantaine, confirmation séparée, consommation des sources et fermeture du chemin d’import direct |
| Première installation Web | 6 | Profils temporaires Apache/Nginx, création du propriétaire et fermeture/verrouillage de l’installateur |
| Contrats natifs rejouables | 15 | Pilote, transactions, HTML, navigation, originaux, PDF, faux uploads, transfert privé, anti-traversal et absence du moteur |

Les profils Web temporaires de première installation sont également vérifiés sous Apache et Nginx, puis refermés/verrouillés après création du propriétaire. Les fichiers PHP natifs de la distribution passent la vérification de syntaxe ; les ZIP et leurs manifestes passent les contrôles de CRC et SHA-256.

## Détails importants

- Un fichier média reçu n’existe pas dans la médiathèque publique avant une deuxième action autorisée. L’accès direct par identifiant de dépôt répond 404.
- Le remplacement d’un module actif est refusé. Un retour arrière rétablit le code et les données natives propres au module ; les autres contenus du site restent conservés.
- Le test de mise à jour utilise la véritable archive 1.4.0, une clé OTP chiffrée, un propriétaire, des révisions, un média original, un thème/module personnalisé et l’extension Atlas API 1 précédemment livrée. Après mise à jour, le mot de passe déclenche toujours OTP et le code TOTP permet une vraie connexion HTTP. Atlas rend sa fiche via l’API native conservée.
- La sauvegarde privée de mise à jour a été ouverte et utilisée pour retrouver le code 1.4.0 et une base SQLite dont `integrity_check` répond `ok`.
- Les captures de l’éditeur bureau et mobile ont été inspectées. Le tiroir mobile fermé est inerte ; il s’ouvre et se ferme au clavier. Les symboles SVG restent visibles sous la CSP de production.

## Limites de qualification

Les scénarios antivirus utilisent un interlocuteur synthétique du protocole clamd. Ils prouvent le traitement des verdicts et pannes, pas l’installation de ClamAV, la fraîcheur de signatures ou la détection de tout programme malveillant. Les contrôles de format PDF ne sont pas un moteur de désarmement de documents.

Le pilote SQLite et le pilote PDO mysql sur MariaDB 10.11.14 ont été exécutés. L’adaptateur PostgreSQL est fourni, mais **aucun serveur PostgreSQL n’a été qualifié ici** ; vérifiez-le sur une copie avant usage. MySQL Oracle et les autres versions/offres MariaDB ne sont pas couverts par le seul test MariaDB local.

La CI PHP 8.3/8.4/8.5 est fournie ; seule la version PHP locale ci-dessus a été exécutée. Le Dockerfile/Compose ont été préparés sans construction/exécution d’une image dans cet environnement. Aucun débit universel, SLA, audit indépendant, délivrabilité e-mail ou validation OVH/Infomaniak en production n’est revendiqué.

Le CMS reste mono-instance et propriétaire unique. Les bases propres aux modules ne sont pas automatiquement converties avec la base du cœur. L’import XML reste éditorial et ne migre pas des données métier de plugins.

## Rejouer les contrats de base

Avec PHP et les extensions requises : `php tests/native150.php`. Le test crée un stockage temporaire hors du site, utilise des données synthétiques et le supprime à la fin. Il ne doit pas être exécuté via HTTP. Le guide de déploiement décrit séparément les contrôles à refaire sur l’hébergeur.
