# Historical validation — supplied 1.0.0

[Original French report](VALIDATION.fr.md)

The original report dated 24 October 2026 records PHP CLI 8.3.6 with DOM, mbstring, fileinfo, GD and ZIP, plus automated Chromium on an isolated installation. It reports 50 HTTP and 29 browser checks, no JavaScript errors, PHP/JS syntax checks and ZIP/manifest validation.

HTTP coverage included server-key installation, single owner/recovery, CSRF/origin/session controls, drafts/previews/publication/revisions/conflicts/scheduling, converted PNG/private media, native themes/plugins and ZIP traversal rejection, private workspace, backup/restore exclusion of owner secrets, WXR import/deduplication/XXE rejection, FR/EN and true 404, untrusted HTTPS proxy headers, password/session revocation, recovery and rate limits.

Browser coverage included desktop 1,440 px/mobile 390 px admin screens, horizontal overflow, mobile navigation, command palette, language tabs, sanitized HTML preview, browser content save, public FR/EN and preserving editor text after session expiry. Screenshots were internally inspected, not shipped with private account information.

The supplied storage contained only its protection and index file. Real hosting, Apache/Nginx with a real certificate, Docker, macOS launcher double-click and a port of transactional PASS ticketing were not executed. These checks are not an independent security audit. They are historical evidence, not a new CI run.
