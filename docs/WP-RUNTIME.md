# WordPress Runtime 0.1 — optional and experimental

The distribution includes an **inactive connector, without WordPress installed**. The native single-owner CMS remains PHP + SQLite. The optional worker runs genuine WordPress themes/plugins in another PHP process and a dedicated MySQL/MariaDB database. CMS SQLite, password hashes, sessions and signing secrets are never exposed as a WordPress data adapter.

Compatibility depends on each module, permitted routes, network access and CSP. Commerce, payments, public accounts, multisite, external jobs, complete business-data migration and plugins requiring direct public PHP endpoints are not qualified. There is no universal compatibility or measured speed claim. Rewriting asset paths does not make WordPress undetectable or repair vulnerable code.

## Setup

1. Back up native code and storage. Use separate private trees outside webroot, such as `/srv/voilavoila-private/native` and `/srv/voilavoila-private/engine`.
2. Provide two HTTPS hostnames you control, for example `cms.example.com` and `legacy.example.com`. Production rejects using the native administration hostname for WordPress. The same CMS public tree may serve both vhosts; `VV_CANONICAL_ORIGIN` gates native PHP administration on the legacy hostname.
3. Provide another system UID, `wp-engine`, or a restricted container. Install PHP-FPM with WordPress modules including `mysqli`, and an **empty dedicated database**, with a SQL account restricted to that database. WordPress currently recommends PHP 8.3+, MariaDB 10.11+ or MySQL 8.0+, and HTTPS.
4. Prepare official inputs outside the site:

```sh
python3 tools/fetch_wordpress_fixture.py --directory /srv/voilavoila-private/official-input
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

The Python tool uses HTTPS, obtains the current official release, verifies every official file and records the complete ZIP SHA-256. Its private verification bundle expires after 24 hours. You may run it locally and transfer both files by SFTP when Python is unavailable on hosting. Keep the bundle operator-owned: it is trusted local verification data, not an independently signed distribution manifest.

5. Adapt [wp-connector.php.example](examples/wp-connector.php.example) into a private `wp-connector.php` outside webroot. It is guarded JSON, never a PHP include. Use the generated secret and exact verification-bundle path. Supply the following variables to the native pool and CLI:

```sh
export VV_STORAGE_PATH=/srv/voilavoila-private/native
export VV_WP_CONNECTOR=/srv/voilavoila-private/wp-connector.php
export VV_CANONICAL_ORIGIN=https://cms.example.com
```

6. Activate **WordPress Runtime** in CMS Plugins. Its panel accepts the official ZIP, dedicated SQL credentials and `INSTALL` confirmation. Alternatively prepare a private JSON containing `db_host`, `db_name`, `db_user`, `db_password`, and run:

```sh
php bin/console.php wp:quarantine:prepare /private/wordpress-VERSION.zip < /private/db-input.json
php bin/console.php wp:quarantine:status
```

Replace example paths. Do not put SQL passwords in command arguments/history. Remove the private input according to your retention policy. Preparation never overwrites an existing engine and never connects to SQL from the CMS process.

7. Adapt [wp-fpm-pool.conf](examples/wp-fpm-pool.conf). Code/configuration must be owned by the deployer/CMS, readable but **not writable** by `wp-engine`. Only `vv-data/` and `wp-content/uploads/` are writable by the worker. Give the CMS read access to uploads through suitable groups/ACLs. Restart the pool after private configuration or code changes.
8. Configure `/compat/` and `/assets/{ui,mod,core,admin,media}/` rewrites. Apache rules are supplied in `.htaccess`; enable rewriting and configure the native pool. For Nginx adapt [wp-nginx.conf](examples/wp-nginx.conf) and the [native vhost](nginx.conf). Never send public requests directly to the WordPress socket. Verify by HTTP that private storage, worker templates, configuration and WordPress PHP cannot be fetched or executed directly.
9. Open the owner handoff from the CMS Runtime panel. The first handoff initializes the empty dedicated database. Activate modules in the real WordPress dashboard. The `health` destination shows version/PID/UID and selected settings; it does not certify system isolation.

The CMS only connects to the private FastCGI listener. It never includes `wp-load.php`. Requests carry expiring HMAC context and replay protection. Owner tickets are single-use and expire in 60 seconds; bridge sessions expire after 30 minutes and follow native owner revocation. Native cookies are removed before relay, while WordPress retains its own capabilities/nonces and cookies.

`open_basedir` and disabled functions are additional restrictions, **not a comprehensive sandbox**. Strict quarantine also requires OS/container filesystem and outbound-network controls. WordPress HTTP constants only gate its HTTP API; trusted plugin code can use raw network functions. Same-UID processes on shared hosting do not provide this boundary. When your OVH/Infomaniak plan cannot provide it, use the standalone CMS and WXR import instead. No shared-host qualification is claimed.

## Modules and public routes

```sh
php bin/console.php wp:theme:wrap /private/trusted-theme.zip
php bin/console.php wp:plugin:wrap /private/trusted-plugin.zip
```

Each archive contains one lowercase module folder. Wrapping records SHA-256 files in `vv-manifests/`, without executing the module; public routes start closed. PHP modules are trusted code. Deploy updates as the operator, with backups and updated integrity manifests. WordPress file editing and code installation are disabled.

A public AJAX/REST rule must appear identically in connector `policy` and the installed plugin manifest `policy`. Example entry inside `ajax`:

```json
{"my_contact":{"plugin":"my-plugin","methods":["POST"],"fields":{"email":"email","message":"text"}}}
```

Under `rest`, use exact paths such as `/my-plugin/v1/contact`, without wildcards. Types: `text` (2,000 bytes), `email`, `integer`, `boolean` (`0`/`1`). Query and body parameters are checked together; conflicts and undeclared fields are denied. The installed plugin must be active and its files must match the manifest. Public POST accepts URL encoding only, no files, with `vv_guest` or `X-VV-Guest` supplied by the bridge. Modules must retain their own business and abuse checks. Owner requests retain normal WordPress capabilities/nonces and bounded 8 MiB uploads.

Static assets use extension allowlists, bounded sizes, path/symlink validation and no PHP execution. Compatibility CSP allows inline JavaScript and `eval` **only on the WordPress origin**; native CMS CSP remains strict. External resources are denied by default. This release does not cache rendered WordPress HTML, avoiding shared caches of nonces, forms and private sessions.

## Migration and operations

First keep WordPress on its dedicated origin. Back up its SQL database, complete private tree, uploads, configuration and secrets **separately**; native CMS backup ZIPs do not contain them. To trial an existing site, clone SQL/uploads into the dedicated engine/database, preserve its table prefix, bind `owner_login` in `engine-config.php` to the existing administrator, generate new salts and adapt serialized URLs using `wp search-replace --dry-run`. Stop the pool before changes and restart it afterwards. Never initialize over unrelated tables. Full commerce/business migration needs its own qualification and rollback procedure.

Run operator-installed WP-CLI as the **restricted engine user**, with `--path=/private/engine --require=/private/engine/vv-CLI.php`. Private helpers also support:

```sh
php /private/engine/vv-Console.php version
php /private/engine/vv-Console.php upgrade
php /private/engine/vv-Console.php cron
```

`upgrade` upgrades the WordPress database schema; it does not replace core code. Schedule cron explicitly. Apply WordPress security updates through controlled deployment, with backups and a stopped pool. Automatic core updating is not provided in 0.1.

To move to native content, export WXR from WordPress (bridge `export` destination), package uploads and use CMS Tools → WordPress import. Keep the **same source URL** across repeated imports to preserve idempotence. Check pages/posts/comments/documents, slugs and redirects. Plugin-specific data does not automatically become native. Switch the home page after validation, then deactivate Runtime. Native restoration with the plugin active resets its enabled/home settings to false. An unavailable optional home connector falls back to native rendering.

## Sources and evidence

- [WordPress requirements](https://wordpress.org/about/requirements/).
- [WordPress backups](https://developer.wordpress.org/advanced-administration/security/backup/).
- [PHP open_basedir limitations](https://www.php.net/manual/en/ini.core.php#ini.open-basedir).
- [WP-CLI search-replace](https://developer.wordpress.org/cli/commands/search-replace/).

The delivered report records executed versions and checks. Local loopback/same-UID tests do not certify distinct-UID/container isolation, TLS, actual hosting or performance.
