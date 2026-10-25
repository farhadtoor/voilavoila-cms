# Migrate from WordPress — 1.2.0

Two tools ship: **content/media import** under Tools & backups, and **WordPress Porter** for static analysis and limited theme/plugin porting. The CMS does not run WordPress.

## Content, media and comments

1. Back up WordPress and the CMS.
2. In WordPress, choose **Tools → Export → All content** and keep the WXR XML.
3. Download `wp-content/uploads` over SFTP and ZIP it. Accepted paths include `2026/10/photo.jpg`, `uploads/2026/10/photo.jpg` or `wp-content/uploads/2026/10/photo.jpg`. Avoid macOS metadata; do not upload a whole WordPress backup.
4. Activate Comments in the CMS if wanted.
5. Submit **XML and uploads ZIP in the same import** under Tools & backups.
6. Defaults are drafts and private media. Options preserve public publication and/or make media public. Public files are available by URL even when used in private content.
7. Enter the owner password. A restore point precedes writes.
8. Read/download the report and URL mapping, review content, approve selected comments and test anonymously.

macOS ZIP example without hidden metadata:

```sh
cd "/path/to/the/folder/containing/uploads"
zip -r -X uploads.zip uploads -x '*/.*' '__MACOSX/*'
```

XML contains no media bytes. No attachment is fetched over HTTP; the local ZIP supplies files. Import without it removes external images from sanitized HTML. Adding the ZIP later does not rewrite existing content. Use a disposable migration or reinsert media.

## Mapping

| WordPress | CMS |
| --- | --- |
| WXR 1.0/1.1/1.2 | Checked namespaces, no DTD/entities |
| Posts/pages | Native IDs, titles, sanitized HTML, excerpts |
| Dates | Creation/modification/publication UTC; reported fallback for missing GMT |
| Private/password-protected content | Owner-only private content; original password not imported |
| Future content | Schedule retained with publication option |
| Categories/tags | Flat native tags, maximum 12 |
| Sticky posts | Pin retained |
| Supported media | Shared validation, original bytes/extensions; source/ID/hash deduplication |
| Resized `-300x200` images | Map to recognized available originals |
| Featured image | Native cover when the image is public |
| Internal links | Rewritten for content present in the export |
| `[caption]`, `[gallery ids="…"]` | Simple captions and recognized local-image galleries |
| Ordinary comments | Optional active-plugin import, all pending |
| Repeat import | Stable source URL + ID; existing content and local edits retained |
| Report | Counters, up to 100 displayed warnings, total count, URL mapping |

Keep the same source URL across imports; enter it if missing from XML. Recognized 1.1 imports receive stable provenance without replacing content. Changed bytes under an existing WordPress media ID produce a warning and retain the old file.

The URL map is informational: no 301 redirects are installed. Configure them on the old domain/server after review. Links to absent content are not guaranteed. The last report stays private on the server and is not in portable exports; content/media provenance is exported and restored.

## Limits

XML: 8 MiB, 2,000 items; body: 200 KB; ZIP: 64 MiB compressed/expanded, 2,200 entries, 8 MiB per file. Site limits: 10,000 contents and 2,000 media. Comments have separate limits.

Missing/rejected media are reported while valid content may still import. Invalid XML/ZIP or transaction failure prevents commit. New files from a failed import are removed. Existing data are retained. Serialized PHP is never deserialized.

Not migrated: users, roles, settings, complex menus, URL hierarchies, products/orders, PASS data, dynamic blocks, page builders or plugin business logic. Unknown shortcodes need adaptation. Saved static block HTML is sanitized; WordPress JavaScript is not executed.

## Themes/plugins: WordPress Porter

Activate **WordPress Porter** in Plugins, then open **WordPress porting**. Submit a WordPress ZIP. Results are **converted** for the supported subset, or **manual** with a draft/report.

Source PHP is analyzed as text and never executed. Review outputs before separate native installation. This is not universal conversion of WooCommerce, builders, `$wpdb`, authentication or payments.

```sh
php content/plugins/wp-porter/bin/porter.php analyze source.zip report.json
php content/plugins/wp-porter/bin/porter.php convert source.zip native.zip --id my-module
```

Porter 1.0.0 is unchanged. It needs ZIP and tokenizer. Detailed guide: `content/plugins/wp-porter/README.md`.

Reference: [official WordPress export](https://developer.wordpress.org/reference/functions/export_wp/).

[Documents](documents.md) · [Comments](comments.md)
