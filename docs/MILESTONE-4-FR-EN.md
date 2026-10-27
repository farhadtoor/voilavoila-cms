# 1.4.0 — J4 + technical J5 / J4 + J5 technique

## Delivered code / Code livré

| Roadmap / Feuille de route | Implementation / Implémentation |
| --- | --- |
| One SEO resolver, no duplicates / Résolveur SEO unique | `core/Metadata.php`, existing API 1 themes retained / thèmes existants conservés |
| Per-content bilingual metadata / Métadonnées FR/EN par contenu | Bundled SEO 1.1 / extension SEO 1.1 |
| Sitemap, social data, limited schemas / Sitemap, partage, schémas limités | Public visible content only; native URLs / contenus publics visibles, URL natives |
| WXR review and collisions / Analyse WXR et collisions | `WordPress::run` preview, `Migration` private staging / analyse et stockage privé |
| Approved migration redirects / Redirections approuvées | Content ID targets, public visibility check on each request / cibles par ID, visibilité vérifiée |
| Installation health / Santé de l’installation | Owner page and JSON export; no private paths or message data / page propriétaire et export nettoyé |
| Retention and useful jobs / Rétention et tâches utiles | `Operations::run`, Forms owns its rows, no public work / Forms garde la maîtrise de ses demandes |
| Work budgets and concurrency / Budget et concurrence | Non-blocking exclusive maintenance lock; bounded notification count / verrou exclusif et nombre d’envois borné |
| Performance measurements / Mesures | Isolated synthetic CLI benchmark; no page cache introduced / benchmark CLI isolé, pas de nouveau cache |
| SDK and compatibility / SDK et compatibilité | API 1 retained, versioned hooks and installable example / API 1, hooks versionnés, exemple installable |
| Community pilot / Pilote communautaire | Protocol and reporting kit ready; five real volunteers not claimed / kit prêt, cinq volontaires réels non revendiqués |

## Contracts / Contrats

`metadata_v1($data, $app, $content)` is a pure value filter registered with `vv_action`. It runs once per rendered head after the theme produces its fallback values. It controls title, description, noindex, the ID of a public social image and whether supported structured data is emitted. URLs remain core-generated.

`content_record_v1($record, $old, $input)` is a pure filter inside the existing content transaction. Do not open another transaction or perform network/disk side effects in this callback. Exceptions roll back content and revisions together. `editor_fields_v1` renders owner-only fields inside the editor form.

`maintenance_completed_v1($app, $event)` is emitted after the maintenance state commits. The event contains version 1, a unique run ID, timestamp, duration and counts. Delivery is best effort; consumers deduplicate IDs and own their own data. It is not a persistent message bus.

Ces contrats conservent API 1. Les filtres se déclarent avec `vv_action`, tandis que `vv_filter` applique une chaîne de filtres. Les callbacks de valeur restent purs. L’événement de maintenance est postérieur au commit, sans promesse de livraison durable. Les thèmes et extensions PHP restent du code de confiance, exécuté avec les droits de l’hébergement.

## Bounds and defaults / Bornes et valeurs par défaut

| Item / Élément | Policy / Politique |
| --- | --- |
| WXR / uploads ZIP | Existing 8 MiB / 64 MiB limits retained / limites conservées |
| Import preview / Prévisualisation | 2 reviews, 1 hour; guarded private files / 2 analyses, 1 h, fichiers privés protégés |
| Import count / Volume | Existing 2,000 WXR-item and media validation bounds retained / bornes existantes conservées |
| Redirects / Redirections | 6,000 rules maximum; exact local legacy keys, no external targets / correspondances exactes, cibles natives |
| Storage health / Mesure stockage | At most 5,000 entries or 0.2 s, partial results labelled / 5 000 entrées ou 0,2 s, résultat partiel indiqué |
| Log retention / Journaux | 1–365 days, default 30 / jours, défaut 30 |
| Backups / Sauvegardes | 1–365 days, 3–30 maximum points, 2 newest protected / jours, maximum de points, 2 plus récents protégés |
| Due backup / Sauvegarde à échéance | 1–168 h, default 24 h; automatic creation off / défaut 24 h, création automatique désactivée |
| Notifications | Mail processing off by default, 1–10 jobs/run; ambiguous outcomes not retried / désactivé par défaut, pas de renvoi aveugle |
| Time budget / Temps | 1–30 s, default 10, cooperative between tasks / défaut 10, coopératif entre tâches |
| Forms retention / Demandes | Existing per-form policy; only the active Forms plugin cleans its data / politique du formulaire, nettoyage par Forms actif |
| Export temporary files / Exports temporaires | Deleted after download; owner controls downloaded copies / suppression après téléchargement |

Editorial restore points include native content, media, settings, SEO metadata, redirects and supported plugin state. They do not contain owner credentials, OTP keys, executable code or unrelated business databases. Full recovery requires a separate complete filesystem/storage backup.

Les points éditoriaux ne remplacent pas une copie complète du site : code, clés et bases séparées de plugins restent à sauvegarder selon leurs propres procédures. Une expiration ne garantit pas un nettoyage à heure fixe sans cron actif. Le budget ne peut interrompre un transport e-mail déjà engagé.

## Evidence and remaining qualification / Preuves et qualification restante

Automated fixtures use synthetic data, fake mail transports and local browsers. They cover fresh installs, 1.3.1 upgrade/rollback, private access, preserved media, metadata deduplication and raw script bytes, stale/tampered WXR reviews, repeat import, redirect visibility, retention, lock contention and ambiguous notification leases.

No real merchant payment, mail delivery, hosting account or volunteer site is represented by these fixtures. Record OVH/Infomaniak offers and PHP settings and complete the [pilot protocol](PILOT-1.4.0-FR-EN.md). No SLA or performance multiplier is promised.
