# Changelog / Historique

Versions below identify the supplied application archives. Git history is reconstructed from those snapshots at import time; it does not recreate the original development timeline. / Les versions correspondent aux archives fournies. L’historique Git est reconstruit lors de l’import et ne reconstitue pas les étapes de développement d’origine.

## Unreleased / Non publié — GitHub preparation

- English/French README, installation, architecture, security, upgrade, publishing and community guides.
- Contribution/support/conduct policies, release notes, issue/PR templates, ignore rules and review ownership.
- Portable isolated HTTP/migration test runner, repository hygiene checks and proposed PHP CI matrix.
- Original application PHP/JS/CSS files preserved. Original release ZIPs remain unchanged.

Préparation GitHub : documentation FR/EN, règles de contribution, notes de version, modèles, exclusions des données privées, tests rejouables et CI. Les fichiers du moteur et les ZIP de version restent inchangés.

## 1.1.0 — supplied release / version fournie

- SQLite PDO storage: typed `contents` rows for pages/posts, `revisions`, separate application state.
- Independent plain text access/error logs, rotation and authenticated downloads.
- Original JPG/JPEG/PNG/WebP/PDF bytes and extensions, stable media links and access rules.
- Automatic transactional 1.0 migration with legacy copies; legacy portable backup support.

Stockage SQLite des pages/articles et révisions ; journaux texte d’accès/erreur séparés ; médias originaux ; migration 1.0 avec conservation des sources et compatibilité des sauvegardes.

## 1.0.0 — supplied first release / première version fournie

- Single-owner profile CMS, pages/posts, media, two native themes and three plugins.
- FR/EN editing, drafts, scheduling, previews, revisions, backup/restore and WXR import.
- Guarded JSON state file and guarded media; images converted to PNG.

Premier CMS personnel, propriétaire unique, profil, pages/articles, thèmes/extensions natifs, FR/EN, sauvegardes et import WXR. État JSON protégé et médias enveloppés en PHP ; images reconstruites en PNG.
