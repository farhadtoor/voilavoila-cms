# Forms 1.0 — VoilaVoila CMS 1.3

Activate **VoilaVoila Forms** under Plugins. Open **Forms**, create a definition, set retention and privacy information, and publish it. Place its tag, such as `[form-contact]`, in a public page or post. Submission works without JavaScript.

Supported fields: text, email, telephone, textarea, select and checkbox. A blank label removes a field; order numbers move fields. Limits: 10 forms, 20 fields each, 500 requests, 2 MiB for Forms within the CMS-wide 8 MiB plugin-data limit. Form file attachments are not supported; use Media for published documents.

The private inbox provides search, filters, pagination and New, Read, Handled and Spam states. Every request retains a snapshot of its field definitions. Values are plain text, escaped when displayed. Removing a definition retains its requests. Deactivating the plugin retains its data.

Submissions use a separate visitor CSRF cookie/token, a signed expiring proof, a honeypot and rate limits of 5 per IP per 15 minutes and 100 per site per hour. Raw IP addresses are not added to inbox rows. Used proofs remain for two hours to reduce retries creating duplicates, including after a request is deleted. No external spam service is included.

## Notifications and retention

Notifications start disabled. Configure an owner recipient and a sender authorized by your hosting provider. Reauthenticate to process up to 10 pending notifications from the panel or use:

```sh
php bin/console.php forms:notifications
php bin/console.php forms:purge --confirm=PURGE
```

These can be scheduled using your provider's cron, the correct PHP executable and the same `VV_STORAGE_PATH`. Purging removes expired requests; retention is captured when each request is submitted. Commands require Forms to remain active. An explicit purge is also available in the owner panel.

`accepted` means transport acceptance, not delivery. `failed` and `unknown` are never retried automatically. A crash after transport acceptance can leave an unknown result; an explicit retry can therefore duplicate mail. No SQLite transaction remains open while calling the transport. Stale processing leases become unknown. Actual PHP mail delivery depends on your hosting configuration and is not certified by local tests.

Faithful JSON and formula-guarded CSV exports require the current password. CSV uses one row per field. Native backups also include Forms and require reauthentication. Restoring while Forms is active disables notifications and changes pending/processing jobs to unknown. Exports and historical backups need their own retention management: deleting inbox rows does not erase those copies.

## API 1

`$app->currentContent()` exposes content during rendering and restores the previous context afterwards. Shortcode callbacks still receive **zero arguments**. `forms_received_v1` runs after transaction commit and exposes identifiers, not private values. A trusted `forms_transport_v1` filter may return `accepted`, `failed` or `unknown`. `backup_restore_data_v1` validates active-plugin backup data before editorial writes.
