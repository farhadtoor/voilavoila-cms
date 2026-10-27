# VoilaVoila CMS 1.4.0

## Français

La 1.4.0 regroupe les fonctions techniques de la 1.4 et de la 1.5 prévues dans la feuille de route. Le CMS reste personnel, auto-hébergé, en PHP + SQLite et compatible avec les thèmes/extensions API 1.

- Administration : barre latérale repliable en icônes sur ordinateur, préférence mémorisée par navigateur et dossier, menus accessibles au clavier et tiroir mobile conservé. « VoilaVoila ! » utilise le dégradé doré/blanc demandé ; logo et drapeau français conservés.
- SEO 1.1 : champs français/anglais par contenu, réglages de l’accueil, image sociale publique, exclusion d’indexation, canonical et langues alternatives, Open Graph/Twitter, WebPage/BlogPosting, sitemap FR/EN paginé et RSS. Le noyau normalise les métadonnées des thèmes existants pour éviter les doublons sans réécrire leur JavaScript ni le corps de page.
- WordPress : analyse WXR avant import, collisions et médias manquants visibles, fichiers privés temporaires, contrôle d’intégrité et refus d’un rapport devenu obsolète. Les imports répétés préservent les modifications locales.
- Redirections : propositions issues des contenus importés, approbation séparée du propriétaire et cibles exclusivement natives/publiques, sans cible externe arbitraire ni chaîne.
- Exploitation : santé locale exportable sans secrets, taille de stockage bornée, dernier point de restauration, notifications et erreurs récentes, politique de rétention, maintenance manuelle ou CLI/cron avec verrou, budget coopératif et traitement limité des notifications.
- Développement et communauté : benchmark synthétique isolé, exemple SDK installable, contrats API 1 documentés, protocole pour cinq sites pilotes et procédures GitHub FR/EN. Aucune télémétrie ajoutée.

Pas de migration destructive de SQLite. Le propriétaire, ses clés OTP, les contenus, médias originaux et données des extensions sont conservés. Les sauvegardes éditoriales ne remplacent pas une sauvegarde complète des fichiers privés et bases séparées de plugins.

Les tests locaux couvrent installation, migration 1.3.1/retour arrière, métadonnées, import, redirections, rétention, concurrence des tâches et navigation navigateur. La CI GitHub est étendue ; son exécution distante dépendra du dépôt après envoi. Les installations réelles OVH/Infomaniak, la délivrabilité des mails et les cinq sites volontaires restent à qualifier sur leur infrastructure.

## English

Version 1.4.0 combines the roadmap’s 1.4 work with the technical features originally planned for 1.5. The CMS remains a personal, self-hosted PHP + SQLite system with API 1 theme/plugin compatibility.

- Admin: collapsible desktop icon rail, browser-local preference per installation, keyboard support and preserved mobile drawer. VoilaVoila ! uses the requested gold/white gradient, retaining the logo and French flag.
- SEO 1.1: bilingual per-content metadata, homepage settings, public social images, indexing exclusion, canonicals and language alternates, social cards, WebPage/BlogPosting, paginated bilingual sitemap and RSS. Core normalization avoids duplicate metadata while preserving exact script and body bytes in existing themes.
- WordPress: WXR review before import, visible collisions and missing media, private temporary staging, integrity validation and stale-review rejection. Repeat imports preserve local edits.
- Redirects: owner-reviewed proposals from imported content, separately approved, directly targeting public native content IDs, with no arbitrary external targets or chains.
- Operations: shareable local health report without secrets, bounded storage scan, recent backups, queued notifications/errors, retention policy and locked manual/CLI maintenance with a cooperative budget and limited notification processing.
- SDK/community: isolated synthetic benchmark, installable API 1 sample, compatibility policy, five-site pilot protocol and bilingual GitHub workflow. No telemetry is added.

No destructive SQLite migration. Owner credentials, OTP keys, content, original media and plugin data remain. Editorial backups do not replace complete private-file and separate-plugin-database backups.

Local checks cover install, 1.3.1 upgrade/rollback, metadata, import, redirects, retention, worker concurrency and browser navigation. GitHub CI is extended, but remote execution awaits the repository push. Real OVH/Infomaniak deployments, mail deliverability and volunteer pilots require qualification on their actual infrastructure.

See [upgrade guide](UPGRADE-1.4.0-FR-EN.md), [GitHub guide](GITHUB-1.4.0-FR-EN.md), [milestone scope](docs/MILESTONE-4-FR-EN.md) and [pilot protocol](docs/PILOT-1.4.0-FR-EN.md).
