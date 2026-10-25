# Install and operate VoilaVoila CMS 1.1

[Français](../fr/installation.md) · [README](../../README.md)

## Requirements

PHP 8.3+ with DOM, mbstring, fileinfo and PDO SQLite. Images require GD with the relevant JPEG/PNG/WebP codecs; archives require ZIP. Recommend 256 MiB PHP memory. No MySQL, Composer, Node, CDN or URL rewriting is required for the application. Use a maintained PHP release and current security patches.

The owner administration requires HTTPS away from actual localhost. The public site must also be served over HTTPS at the server. Do not deploy the CMS to a static file host. GitHub hosts the source; GitHub Pages does not execute this PHP application.

## Local test

From the CMS directory, run `php bin/console.php status`, then `php bin/console.php install-key`. This initializes the empty storage and prints the installation key. Start:

```sh
php -d memory_limit=256M -d upload_max_filesize=64M -d post_max_size=70M -S 127.0.0.1:8787 router.php
```

Open [install.php](http://127.0.0.1:8787/install.php), enter the key, site name, private management email and a password of 14–72 bytes. Store the recovery code offline immediately; it is displayed once. There is no default password. Keep the terminal running. Use Ctrl+C to stop the local server.

On macOS, the supplied `Lancer-Mac.command` is an optional wrapper when PHP is already installed. It is not a bundled PHP executable. On Windows, use an installed PHP CLI or a local PHP environment with the required extensions. Commands in these guides use `python3`; use `python` or `py -3` if that is how Python is installed on your computer.

## Public PHP hosting

1. Download the current installable ZIP from Releases. Extract the **contents** of `voila-cms`, including dotfiles, to the intended empty web directory. A subdirectory is supported; links use `index.php?p=...`.
2. Configure PHP extensions, HTTPS and permissions. PHP must be able to write to `storage`; writing to `content/themes` and `content/plugins` is needed for admin ZIP installation. Use the actual server owner/group rather than permission 777.
3. Prefer an absolute data directory outside the document root through `VV_STORAGE_PATH`. If moving an existing installation, move **all** storage together and keep the same server configuration for CLI commands.
4. Apache must apply the supplied storage `.htaccess` denial. For Nginx, adapt `docs/nginx.conf` to your domain, document root, subdirectory, PHP-FPM socket and certificate paths. Nginx ignores `.htaccess`.
5. Visit `https://your-domain.example/install.php`. Retrieve `storage/setup-key.php` by SFTP or run the CLI `install-key` command using the same storage path. Read the key after its PHP guard line. Complete the one-owner installation.
6. Keep the recovery code offline. Configure profile, canonical HTTPS URL and theme. Test one public post, an anonymous private-media denial and both interface languages.
7. Verify that direct requests to `storage/cms.sqlite`, `storage/logs/access.txt` and a known raw media path return 403/404 and expose no bytes. If storage is outside the document root, no web mapping should expose it. Back up and test recovery before relying on the installation.

If using a trusted reverse proxy, set `VV_TRUSTED_PROXIES` to its exact IP addresses separated by commas; configure the proxy to replace forwarded protocol headers. Do not trust arbitrary client-supplied `X-Forwarded-Proto`.

## Docker

The root `compose.yaml` is an optional production-oriented scaffold, bound to localhost. `docker compose up -d --build` builds it; `docker compose exec cms php bin/console.php install-key` prints the key. Storage and modules use named volumes. `docker compose stop` stops it without deleting the volumes. Do not use `down -v` unless deliberately deleting those data volumes.

This deployment path was not executed during the release or this documentation preparation. The original root Dockerfile's GD codec configuration needs validation for JPEG/WebP before claiming full format support. The separate `tests/Dockerfile` explicitly enables those codecs for CI. Real HTTPS and proxy configuration remain deployment work.

## Daily operation and recovery

Public media can be read by anyone with its link, including media attached to a draft. Choose private visibility for work documents. PDFs download as attachments. New original images preserve EXIF metadata; remove location/private metadata before uploading when needed.

Export editorial backups in the admin tools and keep an independent private copy. These exports omit owner credentials, sessions, server secrets and executable modules; they include private content and plugin notes. Portable restoration keeps the destination owner, replaces editorial data and does not restore the content revision history. Full file/storage backups are needed for full rollback. Same-server snapshots alone do not cover hosting loss.

Use the offline code on the login recovery screen if access is lost; it is replaced after one use. Server recovery: `php bin/console.php reset-owner` changes the password and revokes sessions. `php bin/console.php safe-mode` disables plugins and restores Profil Or. `admin.php?view=plugins&safe=1` skips plugins for that request. Do not put command output containing keys or recovery codes in GitHub.
