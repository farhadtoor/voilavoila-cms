# Jalon 1 / Milestone 1 — architecture et périmètre

## Décision / Decision

La demande explicite d’implémenter documents, commentaires et portabilité WordPress définit ce jalon. Les recommandations de stratégie ont servi à limiter le périmètre. La consigne de planification des pièces jointes n’est pas interprétée comme l’autorisation de réaliser tout leur catalogue.

The explicit request for documents, comments and WordPress portability defines this milestone. Strategic recommendations constrain scope; the attached planning prompt does not authorize the entire product catalogue.

## Architecture constatée / Observed architecture

| Sujet / Area | Implémentation / Implementation |
| --- | --- |
| Langage / Language | PHP 8.3+, JS/CSS natifs, sans framework ni build obligatoire / no required framework/build |
| Entrées / Entry points | `index.php`, `admin.php`, `install.php`, `login.php`, `media.php` ; bootstrap dans / in `core/bootstrap.php` |
| Identité / Identity | Propriétaire unique, sessions privées, CSRF, réauthentification, code hors ligne dans / in `core/Security.php` |
| Stockage / Storage | SQLite PDO, `contents` et `revisions` ; réglages/identité/données plugins dans / in `cms_state`, `core/Store.php` |
| Concurrence / Concurrency | Verrou fichier, transaction SQLite, versions éditoriales / file lock, SQL transaction, editorial versions |
| Modules / Packages | `cms.json` API 1, `content/themes`, `content/plugins` ; `App::packages` et / and `Tools::installPackage` |
| Hooks | `vv_action` enregistre / registers ; `vv_do` déclenche / dispatches ; `vv_filter` applique / applies |
| Contenus / Content | HTML autorisé, shortcodes simples et filtre / HTML allowlist, simple shortcodes and filter in `core/Content.php` / `core/App.php` |
| Médias / Media | `core/Media.php`, validation archive/XML dans / in `core/Security.php`, délivrance / delivery via `media.php` |
| Tests | Python stdlib + PHP dans / in `tests` ; CI PHP 8.3/8.4/8.5 proposée / supplied |
| Facturation/licence / Billing/licensing | Absentes / absent. Licence MIT du dépôt conservée / repository MIT license retained |

## Phase 0 : état réel / actual status

| Exigence / Requirement | État / Status | Suite / Follow-up |
| --- | --- | --- |
| Manifeste et compatibilité / manifest and compatibility | Partiel / partial | API 1 + minimum CMS/PHP contrôlés ; pas de graphe de dépendances / no dependency graph |
| Activation/désactivation/suppression / lifecycle | Partiel / partial | Gestion existante conservée ; pas de callbacks de mise à jour universels / no universal update callbacks |
| Migrations | Partiel / partial | Migration core 1.0 → SQLite ; Jalon 1 sans changement de schéma / milestone has no schema change |
| Hooks/filtres/events | Partiel / partial | API existante + filtre d’état import `wordpress_import_state_v1` ; pas de bus universel / no universal bus |
| Réglages / settings | Partiel / partial | Chaque plugin valide ses données dans ses transactions / plugin-owned validation |
| Permissions | Implémenté pour ce périmètre / implemented for scope | Propriétaire unique + formulaire visiteur séparé ; pas de rôles / no roles |
| Jobs/planification / scheduling | Différé / deferred | Publications évaluées à la lecture ; ni file de tâches ni worker / no queue/worker |
| Logs/diagnostics/récupération | Implémenté pour ce périmètre / implemented for scope | Journaux texte séparés, console, récupération hors ligne / separate logs, console, offline recovery |
| Mises à jour authentifiées / authenticated updates | Partiel / partial | Envoi local propriétaire + CSRF/réauth ; pas de serveur de mises à jour signé / no signed remote updater |
| Notifications/e-mail | Manquant pour Forms / missing for Forms | À cadrer quand Forms sera autorisé / scope when Forms is approved |
| Identité/routage | Implémenté pour ce périmètre / implemented for scope | Réutilisation de l’existant / reuse existing primitives |
| Contrat cache / cache invalidation | Différé / deferred | Pas de cache de pages partagé ; formulaires privés non mis en cache / no shared page cache |
| Import/export | Étendu / extended | WXR hors ligne, sauvegardes documents/provenance et commentaires / offline WXR, documents/provenance/comment backup |
| Paiement/licences/webhooks/secrets distants | Différé / deferred | Aucun consommateur autorisé / no authorized consumer |

## Jalons, risques et critères / Milestones, risks and acceptance

| Jalon / Milestone | Taille / Size | Résultat et preuve / Result and evidence |
| --- | --- | --- |
| 1A Documents | M | Même validation upload/import/restore ; originaux ; téléchargements privés/publics ; refus archives dangereuses / shared validation, original bytes, access and invalid-archive tests |
| 1B Commentaires | M | Module facultatif, modération, anti-CSRF public séparé, plafonds, export/purge / optional module, moderation, separate public CSRF, bounds, export/purge |
| 1C WordPress | L | WXR + ZIP local ; correspondances médias/liens ; répétition sans écraser ; rapport / local WXR+ZIP, link/media mapping, repeat import, report |
| 1D Livraison | M | Régression, migration réelle 1.1, navigateur, docs FR/EN, publication manuelle / regression, real 1.1 upgrade, browser, bilingual docs, manual publication |
| Prochain jalon proposé / next proposal | À estimer / TBD | MVP Forms : besoins, e-mail, rétention et tests à préciser avant code / Forms MVP requirements/email/retention/tests before implementation |

Risques concrets : DOCX stricts refusés, import partiel quand média absent, différences PHP/hébergement, spam, limites mémoire et reprise après déploiement interrompu. Ces points sont documentés avec procédures de contrôle et plafonds.

Concrete risks: strict DOCX rejection, missing-media imports, PHP/hosting differences, spam, memory limits and interrupted deployment. Guides document bounds and recovery.

## Hors périmètre / Out of scope

Forms, commerce, comptes multiples, marketplace, abonnement/licences, serveur de mise à jour, workers, CRM, SMTP, analytics hébergées, exécution universelle WordPress et migrations de commandes PASS. Les prix des recommandations ne sont ni repris ni codés.

Forms, commerce, multiple accounts, marketplace, subscriptions/licensing, update server, workers, CRM, SMTP, hosted analytics, universal WordPress execution and PASS order migration. Pricing hypotheses are not reproduced or hardcoded.

Avant un futur produit : valider public cible, distribution, licence commerciale éventuelle, support, prestataire e-mail et règles de rétention. Ces choix ne bloquent pas ce jalon sans service distant.

Before a future product: validate audience, distribution, any commercial licensing, support, email provider and retention. Those choices do not block this local-only milestone.
