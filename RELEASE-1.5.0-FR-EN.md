# VoilaVoila CMS 1.5.0 — native, editorial and extensible

## Français

Cette version regroupe les évolutions proposées pour 1.5 et 1.6. Le cœur reste personnel, auto-hébergé, propriétaire unique, FR/EN et compatible avec les modules natifs API 1. SQLite demeure le choix par défaut.

- **Modules natifs** : dépôt ZIP en quarantaine, examen du manifeste et de la syntaxe PHP sans exécution, installation séparée, mises à jour de version, contrôle d’inactivité, sauvegarde privée du code et des données `plugin_data`, retour arrière vérifiant que le code courant n’a pas été modifié. La nouvelle version reste inactive. L’écran affiche les exigences CMS/PHP et les limites des sauvegardes éditoriales.
- **Publication** : comparaison FR/EN côte à côte sur grand écran et empilée sur mobile, compte de mots, estimation de lecture, indication des langues à compléter, copie volontaire entre langues avec confirmation avant écrasement. Les révisions, l’aperçu nettoyé, les avertissements de modifications non enregistrées et Ctrl/Cmd+S sont conservés.
- **Navigation** : écran dédié aux pages du menu, ordre et libellés FR/EN indépendants des titres ; contrôle de version pour éviter d’écraser une modification concurrente. Les deux thèmes fournis prennent en charge ces libellés.
- **Médias** : recherche et filtres format/accès, nom modifiable sans changer d’extension, textes alternatifs FR/EN, téléchargement contrôlé et conservation des octets originaux. Les nouveaux fichiers ne sont jamais publiés immédiatement.
- **Quarantaine** : enveloppes privées inertes, empreinte SHA-256, contrôle de format, examen et libération séparés. Médias, modules, sauvegardes reçues et sources d’import hors ligne passent par ce parcours. Les documents à télécharger restent servis via `media.php`, avec `nosniff`, disposition pièce jointe et CSP sandbox.
- **Antivirus** : connecteur ClamAV INSTREAM sur socket Unix ou TCP loopback. Une détection ou une indisponibilité d’un scanner configuré bloque le dépôt ou sa libération. Le mode `VV_QUARANTINE_REQUIRE_AV=1` refuse aussi l’absence d’antivirus. Sans scanner et sans ce mode, l’interface indique explicitement l’absence d’analyse antivirus et exige une approbation manuelle ; elle n’affiche pas un faux statut « sain ».
- **Serveurs** : liste blanche de quatre points d’entrée PHP (`index.php`, `admin.php`, `login.php`, `media.php`), refus des scripts internes, manifestes, archives et fichiers sensibles, profils Apache/Nginx pour racine et sous-dossier, SVG sandbox. `install.php` est fermé par défaut ; installation CLI ou profil Web temporaire documenté.
- **Base interchangeable** : contrat de pilote séparé du dépôt `Store`, adaptateurs PDO SQLite/MySQL/PostgreSQL, export/import privé complet pour préparer un changement de moteur. L’API `Store::read/change` des extensions reste disponible. La qualification des pilotes est détaillée dans `VALIDATION-1.5.0.md`.
- **Suppression du moteur WordPress** : Runtime, portage automatique de code, passerelles `wp.php`/`wp-assets.php` et JavaScript associé retirés. Les anciens modules concernés ne sont plus chargés, même s’ils restent dans une installation mise à jour manuellement. L’import éditorial WXR hors ligne est conservé ; il ne charge ni WordPress, ni ses thèmes/extensions. Les données déjà importées restent natives.

Aucun changement destructif du schéma SQLite 1.4.0. Les comptes, secrets, clés OTP, contenus, révisions, médias et données des modules personnalisés sont conservés. Les nouvelles exigences de quarantaine changent la sémantique d’un dépôt de média : son identifiant reste en attente jusqu’à libération. Consultez le SDK si une extension supposait une publication immédiate.

Les tests locaux ne valent pas qualification de votre offre d’hébergement, de la délivrabilité des courriels, d’un antivirus réel ou d’une exploitation répartie sur plusieurs serveurs. Voir le bilan de validation.

## English

Version 1.5 combines the proposed 1.5 and 1.6 work: reviewed native ZIP installation/update/rollback; bilingual editorial comparison and reading indicators; dedicated navigation management; searchable media and bilingual metadata; private upload quarantine and optional ClamAV INSTREAM; Apache/Nginx front-controller allowlists; interchangeable database drivers and complete private database transfer.

WordPress Runtime, automatic code porter and proxy entry points are removed. Offline editorial WXR import remains, without executing WordPress code. SQLite is the default; existing owner credentials, OTP keys, content, revisions, original media and native plugin data are retained. New media uploads stay pending until separately approved. No public accounts, marketplace, payment service or Pro licensing system is introduced.

Read `UPGRADE-1.5.0-FR-EN.md`, `docs/DEPLOYMENT-1.5.0.md`, `docs/DATABASE-1.5.0.md` and `VALIDATION-1.5.0.md` before deployment.
