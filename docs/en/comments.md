# Comments — native plugin 1.0.0

`content/plugins/comments/` is an optional API 1 plugin for **CMS 1.2.0+**. It ships inactive and creates no user accounts.

## Enable and moderate

1. Activate **Commentaires / Comments** in Plugins.
2. Open **Comments**. Public posts accept messages; pages are excluded by default.
3. Enable pages in settings if wanted.
4. Each editor has **Allow comments when the plugin is active**.
5. New messages are **Pending**. Approve, return to pending, mark spam or delete.

Approved messages appear on their published content, 20 per page. Draft/private/future content, previews, RSS and feed/search do not publish comments. Closing new comments retains approved messages. Disabling the global setting hides the whole module.

## Data and controls

- Nickname: 2–80 characters; plain-text message: 3–3,000. No active HTML, email or profile URL.
- Every new message requires moderation.
- Signed token, cookie separate from owner sessions, `HttpOnly`, `SameSite=Strict`, `Secure` over HTTPS, two-hour lifetime.
- POST, origin/cross-site checks, honeypot and explicit publication consent.
- Five attempts per IP per 15 minutes, 100 global attempts per hour; keyed rate-limit identities.
- Owner moderation with CSRF and stale-version rejection.
- No external service, remote avatar, CAPTCHA, public account, automatic email or third-party script.

Messages use SQLite `cms_state.plugin_data.comments`; pages/posts remain in `contents`. Native transactions are reused without a new table. Limits: 1,000 messages or 1 MiB, whichever comes first. Lists are paginated.

Messages contain neither email nor raw IP. Access logs can retain IP separately. The cookie secures forms and is not a marketing identifier.

## Retention and deletion

**Export comments as JSON** downloads settings/messages to the owner. Portable backups also include them.

Deactivation and plugin-file removal retain data. To erase it, use **Delete all comments**, enter `PURGE` and the current owner password. Individual deletion is available. Deleting a post never republishes its comments elsewhere; the owner can still export/delete them.

Imported WordPress comments are pending, even if previously approved. Spam, trash, pings and trackbacks are excluded. Reply threads are flattened. WordPress consent evidence is not invented: review the context before republication.

Limits suit a small personal site; they do not replace moderation or form a complete anti-spam system.

[WordPress](wordpress.md) · [Documents](documents.md)
