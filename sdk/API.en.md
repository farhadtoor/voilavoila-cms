# Develop a native module

[Français](API.md)

This is VoilaVoila CMS native API 1. It does not define WordPress functions. PHP modules are trusted code running with the host process privileges. Keep editorial data and settings in storage rather than theme files.

## Manifest and package

A module folder contains `cms.json`:

```json
{
  "api": 1,
  "id": "my-module",
  "type": "plugin",
  "name": "My module",
  "version": "1.0.0",
  "description": "Description française",
  "description_en": "English description"
}
```

The ID starts with a lowercase letter followed by lowercase letters, digits or hyphens; total length 2–51 characters. The folder name equals the ID. A plugin has `plugin.php`; a theme uses `"type": "theme"`, `index.php` and `style.css`.

Every PHP module file must start with a direct-access guard:

```php
<?php
declare(strict_types=1);
defined('VV_ROOT') || exit;
```

ZIP the complete folder, or its contents with the manifest at the archive root. The installer accepts one root folder, at most 400 files, 12 MiB compressed, 20 MiB expanded and 8 MiB per entry. Symlinks, hidden paths and unsafe traversal are rejected. Allowed file extensions are PHP, JSON, CSS, JS, PNG, JPG, WebP, WOFF2, MD and TXT. ZIP installation leaves a module inactive and does not replace an existing folder. Update custom code by SFTP after backup and deactivation.

## Actions, filters and shortcodes

```php
vv_action('head', function ($app, $current) {
    echo '<meta name="my-module" content="active">';
}, 10);

vv_action('content_html', function ($html, $content) {
    return $html;
});

vv_shortcode('hello', function () {
    return '<p>' . e(t('Bonjour !', 'Hello!')) . '</p>';
});
```

Shortcodes are simple tags such as `[hello]`, without attributes or user-provided code. Module HTML is the module author's responsibility. Escape text with `e()` and validate links with `VV\Content::link()`.

| Hook | Arguments | Purpose |
| --- | --- | --- |
| `init` | `$app` | After active plugins load |
| `public_route` | `$app` | Before the theme; a read-only route can emit XML and exit |
| `head` | `$app`, `$current` | Public metadata |
| `profile_links` | `$app` | Profile navigation links |
| `profile_after` | `$app` | Content under the profile |
| `sidebar` | `$app` | Context rail |
| `footer` | `$app` | End of public body |
| `admin_dashboard` | `$app` | Private dashboard widget |
| `content_html` | `$html`, `$content` | Public body filter |

`vv_action()` also registers filters. `vv_filter()` applies filters using the returned value; `vv_do()` executes actions. Lower priorities run first. Prefix any named functions/classes with a module-specific name to avoid collisions.

## Admin page and private data

```php
$vv->adminPage('my-module', t('Mon module', 'My module'),
    function ($app) {
        $note = (string) vv_option('my-module', 'note', '');
        echo '<form method="post">' . csrf_field();
        echo '<input type="hidden" name="action" value="plugin.save">';
        echo '<label>Note<textarea name="note">' . e($note) . '</textarea></label>';
        echo '<button class="button primary">' . e(t('Enregistrer', 'Save')) . '</button></form>';
    },
    function ($app, $post) {
        $note = VV\Content::text(val($post, 'note'), 500);
        $app->store->change(function (&$state) use ($note) {
            $state['plugin_data']['my-module']['note'] = $note;
            VV\Store::log($state, 'my_module_saved');
        });
    }
);
```

The central admin controller requires authentication, POST, origin and CSRF before calling the save callback. Exporting private data, deleting files or changing access also needs `VV\Security::reauth($app->store, $currentPassword)` and suitable local permission checks. Public callbacks do not inherit admin authorization.

`$app->state` is a read snapshot. Always write with `$app->store->change(...)`, which rereads current state under a lock and a SQLite transaction. Write only under `plugin_data[your-id]`. Do not open another transaction or call nested state operations inside that callback: the lock is not reentrant. Call `$app->refresh()` afterward if the same request needs the updated snapshot. Deactivation retains plugin data.

## Themes

The engine provides `$app`, `$current` (or null), `$items`, `$query`, `$page`, `$per`, `$total`, `$notFound`, `$owner` and `$preview` to the theme template.

Optional templates: `home.php`, `page.php`, `single.php`, `404.php`. A requested template falls back to theme `index.php`. Profil Or shares its structure with Profil Graphite. To make an independent theme, copy Profil Or and replace its manifest, templates and CSS.

Useful functions (use the fully qualified `VV\Content` class or import it):

```php
e($text);                                // HTML escaping
t('Texte français', 'English text');       // Interface language
lang();                                  // fr or en
url('index.php', ['p' => 'about']);        // Local URL, including subdirectory
VV\Content::field($content, 'title');      // Translated field, with fallback
VV\Content::url($content);                // Content URL
$app->body($content);                     // Sanitized body + shortcodes + filters
$app->publicItems('post', $search);        // Published posts whose time has arrived
```

Load CSS/JS as local files through `<link>` and deferred external `<script src="...">`. Native CSP rejects inline event handlers and third-party services. Preserve `head`, `footer` and other hooks that your design uses.

`bonjour-plugin.zip` and `signature-theme.zip` are small installable examples, not engine dependencies. Native modules are executable code, not sandboxed user content.

## CMS 1.2 additions

Manifests may specify numeric minimum `requires_cms` and `requires_php` versions, for example `"1.2.0"` and `"8.3"`. Installation/activation check them. Incompatible plugins are disabled on loading; incompatible themes fall back to Profil Or. There is no dependency graph or signed update server yet.

`vv_action($name, $callback, $priority)` **registers** callbacks, including filters. `vv_filter($name, $value, ...$args)` **applies** a filter; it does not register one.

`wordpress_import_state_v1` receives `($state, $event)` and returns state. The event includes `import_comments`, normalized comments and `post_map`. It runs **inside the existing import transaction**: no nested `Store::change`, network requests or output. Exceptions abort import. Comments is its first consumer; other plugins must not edit that plugin's data.

Attachments use `Media::metadata` / `Media::upload` and `media.php` delivery. Internal filename/ZIP/XML validation is not an antivirus API. Legacy metadata mode is reserved for 1.0 data, not a MIME-check bypass.

The guest comment form uses `Security::guestToken` / `guestPost` separately from owner sessions. `postOrigin` alone is **neither** authorization nor CSRF verification. Admin actions must retain `Security::post` and owner checks.

## API 1 additions in 1.3 / Ajouts 1.3

`$app->currentContent()` exposes content only during `$app->body()` rendering; shortcode callbacks still take zero arguments. `backup_restore_data_v1` filters validated editorial backup data before any restore writes. Active plugins must reject incompatible schemas. `forms_received_v1` runs after commit with identifiers only; `forms_transport_v1` is a trusted deferred transport returning `accepted`, `failed` or `unknown`. See [Forms](../docs/FORMS.md).

## CMS 1.4.0

[Versioned contracts / Contrats versionnés](../docs/MILESTONE-4-FR-EN.md) · [Installable maintenance example / Exemple installable](operations-example/README.md).

Register value filters with `vv_action`; `vv_filter` applies them. `content_record_v1` runs inside the native transaction and must not start another transaction or external I/O. `maintenance_completed_v1` runs after commit; delivery is best effort. / Déclarez les filtres avec `vv_action`, gardez les callbacks de valeur purs et dédupliquez les événements avec leur identifiant.


## CMS 1.5.0 — native security and storage contracts

- `Store::read/change`, API 1 hooks, shortcodes and plugin_data remain the native boundary. Never query core SQL tables from a module. SQL now belongs to `DatabaseDriver` v1, with SQLite as default.
- `Media::upload` / `Content::upload` return a **pending quarantine ID**, not immediately published media. Do not write raw uploaded bytes into public content or assume `state[media][id]` already exists. An owner review, CSRF and current-password check release media. Custom native modules own their code and must follow this intake contract.
- `Quarantine::capture/validate/approved` protect incoming files, hashes and configured scan policy. Do not automatically approve untrusted code or replace scanner status with a clean verdict. Antivirus integration is not an antivirus engine.
- Installing or updating a native package now stages a review. An inactive module is required before swapping code. Code plus the module’s own plugin_data is backed up; separate private files/databases require the module’s own backup strategy.
- The bundled page-navigation editor adds `nav_label_fr/nav_label_en` to native content extras. `Navigation::items/label` expose published menu pages and labels. `Content::save` preserves these extras. Third-party themes can opt in without breaking the previous menu/order fields.
- Media `alt_en` complements legacy `alt` (French/default). Original stored bytes and extensions remain unchanged.
- WordPress execution, automatic code porter and proxy routes no longer exist. Offline editorial WXR import and historical import identifiers remain inert data.
