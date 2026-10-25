# Architecture and version history

[Français](../fr/architecture.md) · [Native SDK](../../sdk/API.en.md)

The engine owns data, authorization and routing. Themes render a read context. Plugins attach behavior to explicit hooks. There is one owner, no registration or public write API. PHP, JavaScript and CSS are shipped directly with no build process.

| Area | Responsibility |
| --- | --- |
| `index.php` | Public profile/feed, search, pages/posts, maintenance, owner previews, real 404 |
| `admin.php` | Authenticated POST writes and admin screen routing |
| `install.php`, `login.php` | One-time server-key installation, login and recovery |
| `media.php` | Public/private media authorization and byte delivery |
| `core/bootstrap.php`, `Security.php` | Requirements, headers, sessions, owner/CSRF/origin/rate checks |
| `core/Store.php` | PDO SQLite schema, state facade, transactions, locks and legacy migration |
| `core/Content.php`, `Media.php` | Editorial validation, HTML allowlist, versions and original file validation |
| `core/App.php`, `AdminViews.php` | Native modules, hooks and UI |
| `core/Logger.php`, `Tools.php` | Text log rotation; package, backup/restore and WXR operations |
| `content/themes`, `content/plugins` | Native `cms.json` API 1 modules |
| `storage` or `VV_STORAGE_PATH` | Private mutable installation data; excluded from Git |

## Read and write paths

Public routing loads an authorized content view after publication time and chooses `home`, `page`, `single` or `404`, falling back to theme `index.php`. Draft/private previews require the owner session. Scheduling is evaluated on reads; there is no mail job or required cron task.

Writes require the owner, HTTPS except localhost, POST, CSRF and origin checks. Sensitive operations also ask for the current password. `Store::change()` locks, rereads state, starts `BEGIN IMMEDIATE`, applies changes and commits. Only changed content rows and their revisions are rewritten. Failures roll back settings and content together. SQLite uses DELETE journal mode, FULL synchronous mode, foreign keys and a five-second busy timeout. The application snapshot API remains compatible with native plugins; do not nest transactions.

## Data model

`contents` has separate columns for `post`/`page`, unique slug, status, FR/EN titles/excerpts/bodies, publication and edit dates, cover, flags, order, version and JSON tags/extra fields. `revisions` references contents with cascading deletion and retains up to 15 snapshots. `cms_state` stores owner, settings, media metadata, plugins, plugin data, bounded audit and limits as JSON; content bodies are not stored in that row. IDs are random 96-bit values.

Media are raw original files under generated IDs with their validated extension. Sessions and internal snapshots retain the PHP termination guard. Logs and SQLite do not have such a guard: filesystem permissions and web-server denial are essential. Use private storage outside the document root when possible.

## 1.0.0 compared with 1.1.0

| Component | 1.0.0 | 1.1.0 |
| --- | --- | --- |
| Editorial storage | Guarded JSON `state.php`, atomic replacement | PDO SQLite content/revision tables and transactional state |
| Images | Rebuilt PNG, optionally reduced, stored with PHP guard | Validated original JPG/JPEG/PNG/WebP bytes, actual extensions |
| Technical logs | No dedicated access/error text files | Independent access/error text files and rotation |
| Existing modules | Native API 1 | Same native API 1 |
| Transition | Initial installation | Automatic migration; legacy sources retained |

Both original archives are preserved. Imported Git commits document snapshots, not the original development sequence or release publication dates. Do not use 1.0 for a new deployment.

## Limits and boundaries

This targets one PHP host and moderate traffic; state is still reconstructed in memory. Do not share storage across multiple servers with unreliable network filesystem locks. Media limits: 8 MiB per file, 8,000,000 pixels, 6,000 px per dimension, 2,000 media. Portable backup limits include 8 MiB editorial JSON, 64 MiB total expanded data and 10,000 restored contents. Check current validators before raising limits.

Two themes: Profil Or and Profil Graphite. Plugins: Atelier, SEO, Connexion PASS (external link only). WordPress WXR 1.2 import adds pages/posts, defaults to draft, deduplicates repeat imports and does not download remote files. No payments, orders, customer accounts, WordPress runtime compatibility, cloud sync or automatic remote updater is provided.
