# Upgrade 1.1.0 → 1.2.0

1. Export a portable backup and keep a code copy.
2. For a full rollback including credentials, secrets and revisions, make a **consistent** full storage copy with PHP processes/writes stopped. Do not copy a live SQLite file during writes.
3. Enable maintenance, close editor tabs and retain SFTP access.
4. Check PHP 8.3+, DOM, mbstring, fileinfo, PDO SQLite, GD JPEG/PNG/WebP and ZIP. WordPress Porter also uses tokenizer.

The full pack is a 1.2.0 installation. The supplied patch contains files added/changed since original 1.1.0 at the same relative paths.

Upload engine/new files into the existing folder, keeping `storage` and custom themes/plugins. Back up and compare any local modifications to files being replaced. The patch adds `content/plugins/comments` and `content/plugins/wp-porter`; inspect any existing custom folders with these names. Bundled Porter 1.0.0 bytes are unchanged.

Keep root/storage protection files. New rules deny development/test directories. Replace files together, then open `admin.php`. Do not reinstall or remove the database.

API 1 and the existing SQLite schema are retained. No content conversion, extra account or credential reset is needed from 1.1. New plugins are **available but inactive**; existing activation choices are retained.

```sh
php bin/console.php status
```

With the site's PHP, this should show 1.2.0. Like all native console commands it bootstraps the CMS, so use the correct storage path. SSH is optional.

Sign in with existing credentials. Verify old content/images, new document downloads and direct-storage denial. Activate Comments only if wanted, then leave maintenance mode.

Portable format remains 2 and now includes new document types and WordPress provenance. The old 1.1 engine cannot restore new document formats.

Rollback restores **both** old code and its consistent full storage copy. Portable backups do not restore owner credentials or every prior revision. Replacing engine files alone is insufficient after adding new document types. Plugin recovery: `admin.php?view=plugins&safe=1` or `php bin/console.php safe-mode`.

From 1.0, the existing automatic SQLite migration remains available; see [1.0 migration](upgrade.md), then perform the 1.2 checks.

[Documents](documents.md) · [Comments](comments.md) · [WordPress](wordpress.md)
