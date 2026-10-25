# Upgrade 1.0.0 → 1.1.0

[Français](../fr/mise-a-jour.md) · [Architecture and version comparison](architecture.md)

## Before changing files

Export a portable ZIP from the 1.0 admin tools. Also make a complete private copy of the engine and all storage: only this full copy includes the owner access needed for full rollback. Enable maintenance, close editor tabs and stop writes during the update. Enable PDO SQLite in the same PHP runtime used by the website and CLI. DOM, mbstring and fileinfo remain required; GD and ZIP serve their existing purposes.

## Apply

1. Extract the 1.1.0 installation archive.
2. Replace engine files with those from `voila-cms`; **preserve the existing storage**, custom themes/plugins and data volumes. Update bundled native modules as appropriate. Do not overwrite the site with a demo storage folder.
3. Keep direct HTTP access to storage denied. Preserve `VV_STORAGE_PATH` and use the same path for CLI migration.
4. Open `admin.php`; initialization performs migration. Alternatively run `php bin/console.php migrate` in the CMS folder using the site's storage environment.
5. Log in with the same owner access, check profile/pages/posts/revisions/media and log downloads. Disable maintenance only after verifying the result. Do not run a new empty installation.

The migration keeps content IDs, slugs, status, FR/EN text, timestamps, tags, cover references, order, scheduling and revisions; owner password/recovery hashes, settings and native plugin data; media IDs, `media.php?id=...` links, visibility and alternative text.

## Storage changes

| 1.0.0 | 1.1.0 |
| --- | --- |
| Guarded JSON in `storage/state.php` | `storage/cms.sqlite`: `contents`, `revisions`, `cms_state` |
| Guarded media in `<id>.php` | Actual raw `<id>.jpg`, `.jpeg`, `.png`, `.webp` or `.pdf` |
| Bounded audit events in state | Audit remains; separate `logs/access.txt` and `logs/error.txt` are added |

The original state file is retained. `storage/legacy/state-v1-before-migration.php` stores a migration copy. Old guarded media are moved to `storage/legacy/media` after commit. SQLite becomes authoritative; stale JSON is not imported again on later visits. A failure rolls back database changes and keeps source data, allowing retry after the cause is corrected.

New media keep bytes, format, dimensions, extension and metadata. A 1.0 image that was already converted to PNG stays PNG: the old JPEG cannot be reconstructed. Import your own original again if needed. Existing media links stay stable. New originals remain behind `media.php` access control, and the server must deny raw storage access.

Version 1.0 portable backups remain importable. Current portable exports do not contain technical logs, owner secrets or executable modules. Logs rotate independently at 5 MiB, retaining the current file plus three archives. Only PHP application requests are logged by the CMS; static/server-blocked requests belong to the hosting logs.

## Rollback

Stop writes and restore the **matching 1.0 engine and complete pre-update storage copy together**. Replacing only code with 1.0 does not transfer new SQLite edits back to old JSON. Preserve a private copy of any post-upgrade data before a rollback. Keep legacy migration files until the migration and independent backup are verified.
