# Downloadable documents — 1.2.0

## Attach a document

1. In **Media**, upload a PDF, DOCX, ZIP or CSV.
2. Choose **public** for visitor downloads. Private documents remain owner-only even when linked from a public post.
3. In a page/post editor, select it under **Attach a public document**.
4. Click **Insert download link**, then **Save**. It inserts into the active language.
5. Test in a private browser window.

Original bytes and extensions are retained. Storage uses a name such as `storage/media/<id>.docx`; the upload filename is offered on download. Files go through `media.php`.

## Validation

| Format | Checks | Response |
| --- | --- | --- |
| PDF | Extension, actual `application/pdf` MIME, `%PDF-` signature | Forced download |
| DOCX | OOXML/recognized ZIP MIME; OOXML structure; XML without DTD/entities; existing internal relationship targets | Forced download |
| Document ZIP | Signature, structure, CRC, paths, duplicates, entry types and limits; no server extraction | Forced download |
| CSV | Recognized text/CSV MIME, UTF-8 with optional BOM, no binary controls or identified active markup | Forced download |
| JPG/JPEG, PNG, WebP | Extension/MIME, dimensions and GD decoding | Image or requested download |

Browser MIME is not trusted. `Media::metadata()` inspects bytes with `fileinfo` and format validators. Upload, WordPress import and restore share these checks. Legacy filename adaptation is restricted to 1.0 media/backups.

Documents use `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff` and a `sandbox; default-src 'none'` CSP. No document is included as PHP or executed.

## Policy and limits

- **8 MiB per file**, original bytes retained. Images: 8 megapixels and 6,000 px maximum.
- DOCX/ZIP: 200 entries, 32 MiB expanded, 8 MiB per entry. Extreme compression, links, special files, encryption and incorrect CRCs are rejected.
- Document ZIP: JPG/JPEG/PNG/WebP/PDF/CSV/TXT/MD only. No programs, hidden files or nested ZIP/DOCX. Modules use their separate installer.
- DOCX rejects macros, ActiveX, OLE, embedded binaries, `altChunk`, DDE/external fields and external relationships. Each XML part is capped at 1 MiB. Legitimate documents with external links, embedded fonts or unsupported image formats may be rejected. Remove these features or export to PDF.
- CSV: UTF-8, 100,000 lines and 65,536 bytes per line. Formula cells are not rewritten: take care in spreadsheet software.
- No antivirus, PDF disarming or file-safety certification is provided. Controls protect server execution, access boundaries and HTTP delivery.
- Original metadata, including EXIF, may contain personal information.

## Hosting and backups

Keep root `.htaccess` and `storage/.htaccess`. After startup, direct requests for existing database, logs and media must return 403/404 without exposing bytes. Nginx must apply `docs/nginx.conf` rules. `VV_STORAGE_PATH` can move storage outside the web root where permitted.

Backups include the original document extensions, private drafts and notes. Keep them private. Restore new formats with **1.2.0 or later**. A full rollback needs a consistent storage copy from the older version.

[Comments](comments.md) · [WordPress](wordpress.md) · [Security](security.md)
