# Security and privacy design

[Français](../fr/securite.md) · [Private reporting](../../SECURITY.md)

## Owner boundary

Installation requires a random server-file key, not a public default password. There is one owner and no registration. Passwords use Argon2id where available, otherwise PHP's default password algorithm, with a 14–72-byte input limit. The recovery code is random, hashed, one-use and replaced. No automatic recovery email is sent.

Sessions use strict cookie-only mode, HttpOnly, SameSite=Strict and Secure over HTTPS; they expire after 30 minutes idle or 12 hours total. Password/reset operations revoke other sessions via a server epoch. Admin writes use POST, owner checks, CSRF and origin/cross-site checks. Sensitive actions reauthenticate. Login and recovery are rate limited. Forwarded HTTPS is accepted only from configured exact trusted proxy IPs.

## Content, files and extensions

Editorial HTML is parsed with DOM and an allowlist; scripts, events, forms, inline CSS and arbitrary remote images are removed. Text outputs are escaped. Native pages use CSP, framing denial and `nosniff` protections. These reduce specific attack surfaces; they do not certify arbitrary plugin output.

Uploads validate filename, MIME, dimensions/pixels, limits and GD decoding. Version 1.1 keeps originals including EXIF; it does not strip GPS, sanitize all possible file payloads or claim antivirus scanning. PDFs download as attachments and are not disarmed. Public media are public by URL and may be cached; use private visibility for drafts that need confidentiality.

SQLite, text logs and original media have **no PHP guard prefix**. The private directory must be denied by Apache/Nginx or moved outside the document root with `VV_STORAGE_PATH`. CLI/dev-router denial is not proof of a production server's configuration. Check it on the real host.

Native PHP themes/plugins are trusted executable code with the process permissions. Manifest/API/ZIP path and size checks are not sandboxing or an author signature. Install only reviewed sources. Never expose a deployment token, database or recovery code in a plugin package. No online PHP editor or automatic remote updater is provided.

## Logs and backups

`access.txt` records UTC time, request identifier, method, path without the query, status, direct IP and duration. It excludes query/form values and cookies, but paths/IPs can still be sensitive. `error.txt` contains captured PHP/application errors; third-party error messages may contain sensitive values. Both rotate at 5 MiB with three archives. Review retention, permissions and redaction for your deployment; do not attach full logs to public issues.

Portable backups omit owner credentials/code/server secrets/technical logs, but contain private articles and plugin notes. Keep them private. Full storage copies also contain owner and server secrets. Test recovery on a disposable installation. Same-server snapshots are not independent backups.

## Evidence and limits

Historical release reports and the new isolated checks are described in [testing](testing.md). The local router blocks raw storage, but real Apache/Nginx HTTPS, deployment Docker and macOS launcher paths have not been validated here. No external penetration test, independent audit or guarantee of absence of defects is claimed.
