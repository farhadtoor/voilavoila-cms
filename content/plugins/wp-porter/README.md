# WordPress Porter 1.0.0

Native plugin for **VoilaVoila CMS 1.1.0+**. Analyze a WordPress theme/plugin ZIP and generate either a limited native port or an explicit porting draft with a file/API report. The imported PHP is parsed as data and never loaded or executed.

[Français](README.fr.md) · [Supported rules](docs/RULES.md) · [Validation](docs/VALIDATION.md)

## Install

1. Use VoilaVoila CMS 1.1.0 or later, PHP 8.3+, ZIP, Tokenizer, mbstring, DOM and fileinfo. The CMS already uses most of these; enable Tokenizer/ZIP if missing. Recommend 256 MiB PHP memory.
2. In the CMS owner administration, open **Plugins**, install `VoilaVoila-WP-Porter-1.0.0.zip` with your current password, then activate **WordPress Porter**. Do not upload this ZIP as a WordPress plugin: it is for the standalone CMS.
3. Open **WordPress porting** in the admin navigation.
4. Upload the ZIP of one WordPress theme or plugin. Do not upload a full WordPress installation, database backup or collection of several plugins. Optionally choose type, a unique native ID and a display name.
5. Read the status, unsupported dependencies and suggested native APIs. Enter the current owner password to download the result ZIP and/or JSON report.
6. On an isolated copy of the CMS, install the result under **Themes** or **Plugins**. Installation leaves it inactive. Review `porting/REPORT.md` before activation. Existing module IDs cannot be overwritten by the native installer.

The source ZIP remains unchanged. No source is sent to an API, and no WordPress runtime, AI service, Composer or Node build is used. There is no automatic installation, activation or production update.

## Two result types

**Limited native port (`converted`)**: the source fits the implemented rules. Shortcodes, simple content filters/footer hooks or supported classic templates are regenerated against the native API. This does not reproduce every WordPress behavior or guarantee identical design. Native content, menus, permissions and HTML sanitization apply. Test the visual result and behavior.

**Porting draft (`manual`)**: a blocker was detected. The ZIP contains a clearly marked starter module and inert source references, not the original business logic. A draft plugin only shows an admin notice; a draft theme displays an explicit public draft banner. Read the dependency list and complete the port before relying on it. Gutenberg, child themes, WooCommerce, database queries, accounts/roles, REST/AJAX, cron, email/payment flows, dynamic shortcodes and arbitrary PHP normally require this path.

There is no universal WordPress-to-native compatibility layer. The generated ZIP always includes `cms.json`, native entry files, a bilingual report and original-reference files stored as `.txt`. References are capped and may be incomplete; keep the full source archive. The WordPress author/license metadata and supplied root license files are retained, without assigning their rights to the converter.

## What is automatic

- Static `add_shortcode()` callbacks returning constant strings, including simple translation/escaping wrappers. No shortcode attributes or enclosed content.
- `add_filter('the_content', ...)` callbacks returning a constant prefix/suffix around their single content parameter, or a constant replacement.
- `add_action('wp_footer', ...)` callbacks echoing a constant string, mapped to the native footer hook.
- Simple classic PHP templates: native content fields, loops, basic conditions, literal template parts, native navigation/search, local styles and supported image/font assets. Imported template PHP becomes bounded JSON instructions rendered by owned native code.

WordPress `.mo`/`.po` translations, arbitrary JavaScript and WordPress menu/settings data are not migrated. Literal text is retained; the CMS continues to provide FR/EN content fields. See the exact limits in [RULES](docs/RULES.md).

## Private results and limits

At most three results are kept under the configured private storage in `wp-porter/`. Their JSON/ZIP bytes have the CMS PHP termination guard and are accessible only through authenticated owner requests. Downloads/deletions also require the current password. Native CSRF, TLS and reauthentication rate limits remain active (CMS 1.1: 10 reauth attempts per 15 minutes, including successful attempts). For a batch, use the local CLI rather than changing protections.

Results expire after 24 hours **when the screen or a result operation is next accessed**. There is no background cleanup task. If the extension is disabled and never used again, files remain until manual removal or later cleanup. Delete private results before deactivating if needed. They can contain private source code. They are not inserted into editorial backups or site content. No owner account, page, post, media or active theme is changed by analysis.

Input: 12 MiB ZIP, 1,200 entries, 32 MiB expanded, 8 MiB per entry. Links/special files, encryption, traversal, case-folded duplicates, extreme compression and private `.env` files are refused. Repository dotfiles, server dotfiles and macOS metadata are ignored. PHP analysis is capped at 256 KiB/30,000 significant tokens per file, 400 API names and bounded nesting. Hitting a parsing limit produces a manual-port issue, not a success label. Outputs fit native limits (400 entries, 20 MiB expanded, 12 MiB compressed); references are reduced accordingly.

## CLI without a CMS installation

Extract the plugin ZIP and run inside `wp-porter`:

```sh
php bin/porter.php analyze /path/theme.zip /path/report.json
php bin/porter.php convert /path/plugin.zip /path/native.zip --id ported-example --name "Example"
php bin/examples.php /path/new-examples-directory
php tests/run.php
```

Use `--type theme` or `--type plugin` if detection is ambiguous. Existing output files/directories are refused. CLI conversion initializes no CMS storage and never installs a module. Analysis limits also apply in CLI. Reports are bilingual; CLI operational messages are English. Exit code 0 means analysis/output succeeded; read status to distinguish a draft from a limited native port.

To move WordPress pages/posts separately, use the CMS's existing WXR importer. That importer does not fetch remote media or migrate plugin business data. This converter handles module code, not live data.

## Development and license

Code: MIT; see LICENSE.txt. Imported code/assets keep their original terms. The plugin ships a CLI unit suite and four synthetic WordPress examples generated on demand. Historical and current test coverage is detailed in the validation guide. This is not a vulnerability scanner or a security audit of third-party source.
