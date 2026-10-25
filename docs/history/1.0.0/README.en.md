# VoilaVoila CMS 1.0.0 — historical guide

[Original French guide](README.fr.md) · [Current 1.1 guide](../../en/installation.md)

This English companion describes the first supplied release. Use 1.1.0 for a new installation; keep 1.0.0 as the migration/history reference. Paths below are relative to the root of the original application archive.

## Purpose and requirements

An autonomous personal CMS in PHP, JavaScript and CSS: one owner, a public profile, pages/posts, media, themes and native plugins, responsive dark UI and FR/EN content. No subscription, CDN, Composer, Node or database server is required. **This version uses guarded JSON, not SQLite.**

Use PHP 8.3+ with DOM, mbstring and fileinfo. GD is required for images and ZIP for backups/packages. Recommend 256 MiB memory and a maintained PHP branch with patches. Extract the contents of `voila-cms`, including dotfiles, into an empty PHP web folder. A subdirectory works without mandatory URL rewriting. Give PHP write access to storage; admin ZIP installs also require writable module folders. Do not use 777.

## Install

Visit `https://your-site.example/install.php`; initialization creates `storage/setup-key.php`. Read it by SFTP and copy the key after its first PHP line. Enter it into the installer, then set a site name, private management email and password of 14–72 bytes. Keep the one-time recovery code offline. The key is deleted and installation locks after completion. Configure profile, canonical HTTPS URL and legal pages using your own information.

Remote administration refuses plain HTTP. Serve public content with HTTPS too. Apache must apply the provided protection `.htaccess`; Nginx needs adapted rules from `docs/nginx.conf`, because it ignores `.htaccess`. Do not use static hosting. Optionally move all storage outside the web root through absolute `VV_STORAGE_PATH`; CLI and site must share it. Trust forwarded HTTPS only from exact proxy IPs configured in `VV_TRUSTED_PROXIES`.

Local use: run `php bin/console.php status`, then `php bin/console.php install-key`, then:

```sh
php -d memory_limit=256M -d upload_max_filesize=64M -d post_max_size=70M -S 127.0.0.1:8787 router.php
```

Open `http://127.0.0.1:8787`. The built-in server is for testing. The macOS launcher requires an already installed PHP; use `chmod +x Lancer-Mac.command` if needed. Optional Docker scaffold: `docker compose up -d --build`, then `docker compose exec cms php bin/console.php install-key`. Local port 8787 is bound to localhost and data/modules use named volumes. Docker and the macOS double-click path were not executed during original validation.

## Delivered behavior

- Dashboard with calculated counters, recent content and Ctrl/⌘K search.
- Pages/posts with simple HTML editing, sanitized preview, FR/EN fallback, slugs/tags/excerpts/covers, drafts, published/private/trash states, future scheduling, pin/menu/order, duplication and 15 revisions. Version checks reject stale editor writes.
- Profile cover/avatar/bio/links/location/contact, feed/search/share; true 404, owner previews and maintenance.
- JPG/PNG/WebP/PDF imports. **Images are decoded and rebuilt as PNG**, may be reduced to 2,400 px, and do not retain their original bytes or metadata. JPEG EXIF orientation is not automatically corrected. Media are stored as binary bytes following a PHP termination guard, served by `media.php`.
- Two native themes, three plugins, native API 1 actions/filters/simple shortcodes/admin pages, native ZIP install without activation. Inactive modules can be removed while retaining plugin data.
- Private workspace notes/tasks/shortcuts; SEO metadata/canonical/Open Graph/sitemap/RSS when active and configured; PASS link to an existing HTTPS ticketing installation, not a ticket/payment engine.
- One owner, hashed password, private expiring sessions, CSRF/origin checks, rate limits and offline recovery. Bounded audit history in state; no separate access/error text log feature yet.
- Portable backup/restore, three internal snapshots, WXR 1.2 pages/posts importer. Scheduled publication is evaluated on read, without cron or email.

Public media are public by link, even when attached to a draft. Private media require the owner session. PDFs download as attachments. PHP plugins are executable trusted code, not sandboxed by package validation.

## Backups, limits and compatibility

Portable exports include editorial content/profile/media/plugin data, but omit owner credentials, sessions, server secrets and executable files. They still contain private drafts and notes: store them privately. Restoration replaces content/media references, keeps the destination owner and creates a snapshot first. It does not reinsert previous content revisions. Unreferenced media bytes may remain to avoid destructive loss. Existing media IDs cannot receive different binary content.

Limits include 8 MiB editorial JSON per portable ZIP, 64 MiB total expanded data, 2,000 media and 10,000 restored contents. The guarded editorial file is capped at 32 MiB and rewritten atomically per change. This targets a personal site and moderate traffic, not a multi-server transactional engine. Keep a full private SFTP copy for complete recovery and an independent backup against hosting loss.

WordPress concepts inspired hooks/themes/content, but there is no WordPress API compatibility. `wp_*`, `$wpdb`, WordPress themes and plugins need porting. WXR imports only pages/posts and defaults to drafts. It deduplicates repeated exports, blocks XML DTDs and does not fetch remote media. Attachments/accounts/comments/settings/templates/interactive blocks and plugin business data are excluded. Reimport media, update links and adapt shortcodes manually. Existing legal texts must be reviewed, not invented by the CMS.

## Recovery and upgrading

Before changing files, export data and make a full engine/storage copy. Preserve storage and custom modules. For a custom module update, deactivate, back up and replace files by SFTP, then reactivate; ZIP installation does not overwrite an existing module. A captured plugin-load error disables it. Request `admin.php?view=plugins&safe=1` to skip plugins once, or run `php bin/console.php safe-mode` to disable plugins and restore Profil Or.

Use the offline recovery screen if access is lost; a used code is replaced. With server access, `php bin/console.php reset-owner` revokes sessions and sets a new password. No automatic remote updates or recovery emails are enabled.

Follow the current bilingual 1.0 → 1.1 migration guide to adopt SQLite, original media formats and independent text logs. A PNG already converted by 1.0 cannot become its previous JPEG again without your original file.
