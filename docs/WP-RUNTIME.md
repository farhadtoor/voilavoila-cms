# WordPress Engine 0.2 — themes and plugins

The optional engine runs **genuine WordPress**, its themes and plugins in a separate PHP-FPM process. The native plugin retains ID `wp-runtime` for upgrades from 0.1. The larger offline pack contains official WordPress 7.1.3 and WooCommerce 11.2.1 inputs, checksums and licenses. Neither is installed or activated automatically; native CMS remains PHP + SQLite.

Native CMS owns editorial content, owner access and native media. WordPress themes receive a **copy** of published pages/posts. Plugin-specific business data, including WooCommerce products, carts, customers and orders, remains in a dedicated WordPress database: those APIs and tables are necessary for real module compatibility.

## Setup

1. Back up native code/storage and any existing WordPress files/SQL. The native 1.3.1 patch is required for OTP enforcement; Engine 0.2 also works on 1.3.0.
2. Install the **small** `VoilaVoila-WP-Engine-0.2.0.zip` in Plugins. Update an existing `content/plugins/wp-runtime/` by SFTP without deleting its private engine/database. The large offline pack is not a native module ZIP.
3. Provide two controlled HTTPS hostnames, for example `https://cms.example.com` and `https://legacy.example.com/compat`. WordPress must use a different hostname from native administration in production. Native CMS cookies are never forwarded.
4. Provide separate private trees outside webroot, such as `/srv/voilavoila-private/native` and `/srv/voilavoila-private/engine`. Create an empty dedicated MySQL/MariaDB database and a SQL user restricted to it. Install PHP-FPM with WordPress modules including `mysqli`; provide at least 256 MiB for WordPress/WooCommerce. Current WordPress/module requirements still apply.
5. Transfer `private-inputs/wordpress-7.1.3.zip` and `private-inputs/wordpress-verification.php` from the offline pack into a private server folder. Their exact SHA-256 values are pinned by the plugin, allowing those particular inputs without the 24-hour TTL of freshly downloaded inputs. Pinning verifies consistency with the prepared package, not the absence of newer security updates: check updates before production.
6. Adapt [wp-connector.php.example](examples/wp-connector.php.example) into a private `wp-connector.php` outside webroot. It is guarded JSON, never a PHP include. Set paths, URLs, socket and `verification_bundle`; generate its secret with `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`. Configure the native pool and CLI:

```sh
export VV_STORAGE_PATH=/srv/voilavoila-private/native
export VV_WP_CONNECTOR=/srv/voilavoila-private/wp-connector.php
export VV_CANONICAL_ORIGIN=https://cms.example.com
```

7. Activate Engine and prepare WordPress in its panel using the ZIP, dedicated credentials and `INSTALL`. CLI alternative:

```sh
php content/plugins/wp-runtime/bin/engine.php prepare /private/wordpress-7.1.3.zip < /private/db-input.json
php content/plugins/wp-runtime/bin/engine.php status
```

Private JSON contains `db_host`, `db_name`, `db_user`, `db_password`. Replace example paths; never put SQL passwords in command arguments. Preparation refuses to overwrite an existing engine. CMS does not connect to SQL itself.

8. Adapt [wp-fpm-pool.conf](examples/wp-fpm-pool.conf). Use a different system UID or a restricted container. The worker may read code/configuration but not modify it; only `vv-data/` and `wp-content/uploads/` are writable. Give CMS read access to uploads using suitable groups/ACLs. Keep the Unix socket private to CMS.
9. Configure `/compat/` and `/assets/{ui,mod,core,admin,media}/` rewrites. Apache rules are supplied in `.htaccess`; Nginx examples are [here](examples/wp-nginx.conf), alongside the [native denials](nginx.conf). Supply `VV_*` parameters to the normal native PHP handler too. Never expose the private WordPress tree or socket through the public server. Verify denials over HTTP.
10. Open the owner handoff under **WP themes and plugins**. First access initializes the empty dedicated database. The WP panel exposes themes, plugins and plugin business screens; ordinary editorial/owner menus are hidden to keep CMS as the primary interface.

## Install modules

Use the Engine panel, select theme/plugin and trusted ZIP, confirm `INSTALL`, then activate it in real WordPress. CLI:

```sh
php content/plugins/wp-runtime/bin/engine.php theme /private/theme.zip
php content/plugins/wp-runtime/bin/engine.php plugin /private/woocommerce-11.2.1.zip
```

Archives use one lowercase module folder. The wrapper validates paths, links, duplicates and bounds and records SHA-256 files. It reads entries individually rather than keeping the whole plugin in memory. Internal limits: 20,000 entries, 100 MiB compressed ZIP, 384 MiB expanded, 16 MiB per file. The panel caps uploads at 64 MiB and remains subject to hosting PHP limits. Existing modules are never automatically overwritten; deploy updates with backups/operator control.

## Two profiles

**Strict**, the default, retains 0.1's explicit typed public AJAX/REST rules and matching installed manifests. Suitable for small modules with a few public actions.

**Full WordPress compatibility**, enabled after owner reauthentication, preserves REST methods/JSON, AJAX, `admin-post.php`, customer sessions/cookies, cart tokens and nonce headers. WordPress/plugins enforce their own capabilities, nonces, signatures and business validation. They are not replaced with stub APIs. Native administration, sensitive core paths, XML-RPC and public cron remain closed. Interactive privileged WordPress access requires the native handoff; expired privileged cookies cannot preserve administrative REST access after native revocation. Store customers and independent service API credentials retain WordPress authentication.

This profile exposes installed plugins' public functions and requires trusted, patched modules. Process isolation protects native CMS; it does not repair vulnerabilities inside a plugin or merchant configuration.

Relayed cookies become host-only on the WordPress hostname; reserved CMS names are denied. HTTP statuses, including creation `201`, are preserved. Static assets use bounded extension/path checks without executing PHP. Static/dynamic responses are limited to 16 MiB; forwarded application bodies/uploads to 8 MiB. Large media, digital downloads, direct plugin PHP endpoints and alternative protocols need specific qualification/adaptation.

## Native editorial copies

```sh
php content/plugins/wp-runtime/bin/engine.php sync
```

The panel's **Sync published content** button performs the same operation. It copies title, slug, HTML, excerpt and publication date into WP posts/pages identified by native IDs. Relative media links point back to CMS. Repeated sync is idempotent; copies no longer public become drafts. Products, orders and unrelated business post types are untouched.

Repeat after publication or schedule the CLI with the same environment. Sync is one-way: edits to WP copies do not update SQLite and are replaced on the next sync. Native shortcodes, complex translations, revisions, custom menus and page-builder metadata are not automatically converted; retain their native pages or plan a dedicated migration. WXR import remains available for progressive migration into native content.

## WooCommerce and payments

The official WooCommerce ZIP is optional, never auto-activated. Install it, complete its setup wizard and configure products, taxes, shipping and payments in its business screens. Switch store visibility from Coming soon to Live when ready, and verify indexing settings before production. Enable full compatibility. Orders and stock remain in engine SQL.

The delivered test covers WooCommerce 11.2.1 activation, a genuine product, Store API, missing-nonce rejection, cart cookies and Cart-Token, an offline-payment order, persistence and stock reduction. Mail is intercepted and no actual payment is made. Stripe, PayPal, SumUp, tax/shipping rules, subscriptions and related extensions are not certified by that test.

External payments require merchant credentials, plugin webhooks on the WordPress origin and sandbox qualification. Configure necessary services explicitly:

- `outbound_hosts` in private `engine-config.php` permits the WordPress HTTP API; also configure system/container network rules.
- `browser_origins` in the connector permits exact HTTPS origins in compatibility CSP (scripts, connections, frames).
- `redirect_origins` permits exact HTTPS origins for hosted payment redirects.

Lists start empty and reject wildcards. Use domains specified by the actual integration, not an arbitrary copied allowlist. Relaxed inline/`eval` CSP applies only to the WordPress origin; native CMS keeps its strict CSP. No shared rendered-HTML cache is introduced, preserving per-session nonces and forms.

## Upgrade an existing 0.1 worker

Back up SQL, engine files, uploads, configuration and secrets. Stop the private pool, deploy the new small native plugin, then run:

```sh
php content/plugins/wp-runtime/bin/engine.php refresh --confirm=REFRESH
```

This replaces bridge `vv-*` files and the `wp-config.php` template, retaining credentials/salts in `engine-config.php`. It does not replace WordPress core, modules or SQL. Restart the pool, avoiding stale OPcache configuration. The panel also offers this explicit `REFRESH` operation.

WordPress core updates remain a separate controlled deployment. They are not automated by 0.2. Other official versions can use `tools/fetch_wordpress_fixture.py` with its fresh 24-hour bundle. Pinned offline versions do not imply that no subsequent patches exist.

## Operations and limits

Back up SQL, complete engine, uploads, configuration and secrets **separately**; native editorial ZIPs omit them. Clone existing sites into dedicated SQL, preserve table prefix, bind `owner_login`, replace salts and adapt serialized URLs with `wp search-replace --dry-run`. Stop/restart the pool and qualify the copy. Plugin business data does not automatically become native CMS data.

Run private `vv-Console.php version`, `upgrade`, `cron` helpers as the restricted worker user. `upgrade` changes SQL schema, not core code. Schedule cron explicitly.

`open_basedir` and WordPress HTTP constants are not comprehensive sandboxing. Strict quarantine also needs separate UIDs/restricted mounts and system network controls; PHP can use other network functions. On shared hosting without that separation, retain native CMS or use separate engine infrastructure. Local loopback/same-UID tests do not certify actual hosting, UID/container isolation or TLS.

This remains an experimental compatibility engine, without universal WordPress catalog compatibility or measured speed claims.

[Français](WP-RUNTIME.fr.md) · [WordPress](https://wordpress.org/about/requirements/) · [WooCommerce](https://woocommerce.com/document/server-requirements/) · [Store API nonces](https://developer.woocommerce.com/docs/apis/store-api/nonce-tokens/) · [PHP limitations](https://www.php.net/manual/en/ini.core.php#ini.open-basedir)
