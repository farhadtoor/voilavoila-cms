# Upgrade to 1.3.0

1. Test a private copy first. Download a native backup using your current password, and **also copy complete application code and private storage** by SFTP/SSH. Record PHP and `VV_STORAGE_PATH`. Back up any existing WordPress engine separately.
2. Verify the delivered ZIP SHA-256 using `sha256sum`, macOS `shasum -a 256` or PowerShell `Get-FileHash`. The ZIP is flat: `admin.php`, `core/`, `content/` are at its root.
3. Enable maintenance and replace application files. Preserve real storage, environment variables and custom themes/plugins. Do not delete complete module directories or copy test databases into production. Adapt Nginx denials for private engine templates; Apache rules are supplied in `.htaccess`.
4. Run `php bin/console.php doctor` and `php bin/console.php status`. Confirm 1.3.0, owner login, content, original media, comments and backup export. If activating Forms, create and submit a test form and inspect the private inbox before configuring real email delivery.
5. End maintenance. WordPress remains optional/inactive and requires [separate setup](docs/WP-RUNTIME.md). Updating CMS files never installs WordPress.

SQLite schema, pages/posts/revisions/media and API 1 zero-argument shortcodes remain compatible. Backup exports/downloads now require the current password because backups can contain private submissions. Forms notifications and Runtime do not turn on automatically. No native-to-WordPress content synchronization is performed.

Rollback requires **both** the old code and complete pre-upgrade private storage. Export newer data before rollback; it will not appear in the older snapshot. An editorial backup is not a substitute for full owner/storage backup. WordPress has its own code + SQL + configuration rollback.

[Français](MISE-A-JOUR-1.3.0.md) · [Forms](docs/FORMS.md) · [GitHub](GITHUB-v1.3.0-FR-EN.md)
