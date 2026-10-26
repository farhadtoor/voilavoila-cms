# VoilaVoila CMS

**A small, self-hosted PHP + SQLite CMS for one owner.** Publish a personal profile, pages and posts; manage media; extend it with native themes and plugins.

[Français](README.fr.md) · [Install](docs/en/installation.md) · [Upgrade](UPGRADE-1.3.0.md) · [Contribute](CONTRIBUTING.md) · [Releases](https://github.com/farhadtoor/voilavoila-cms/releases)

The current application version is **1.3.0**. The `main` branch also includes the bilingual project documentation and development checks. Historical 1.0.0 and 1.1.0 snapshots remain documented separately.

## New in 1.3.0

Optional Forms with a private SQLite inbox, deferred notifications and exports. Original VoilaVoila logo and French design signature. Optional experimental WordPress Runtime, isolated from the CMS process.

[Forms](docs/FORMS.md) · [Runtime](docs/WP-RUNTIME.md) · [GitHub FR/EN](GITHUB-v1.3.0-FR-EN.md) · [Upgrade](UPGRADE-1.3.0.md)

## New in 1.2.1

Checked flat release archives, read-only hosting preflight, PHP 8.5 GD compatibility and package-based CI including real browser journeys. Owner data and SQLite schema are retained. See [upgrade](MISE-A-JOUR-1.2.1.md), [GitHub](GITHUB-v1.2.1-FR-EN.md) and [hosting pilots](docs/HOSTING-PILOT-FR-EN.md).

## What you can do

- Publish a public profile, French/English pages and posts, drafts, private previews and scheduled posts.
- Keep up to 15 content revisions and reject stale editor saves.
- Upload original JPG/JPEG/PNG/WebP and downloadable PDF/DOCX/ZIP/CSV documents; insert document links in the editor.
- Choose between two native themes; install native API 1 theme/plugin ZIPs.
- Use a private workspace, SEO metadata/sitemap/RSS and an optional external PASS link.
- Export/restore backups; migrate WXR 1.0–1.2 with a local uploads ZIP, media/link mapping and a report.
- Optional moderated guest comments without public accounts; JSON export and authenticated purge.
- Bundled inactive WordPress Porter for supported static conversions and manual-porting reports.
- Manage one owner with a server-side installation key, hashed password and offline recovery code.

Pages/posts and revisions live in SQLite. PHP application access and error logs are separate text files. The UI and assets are served locally; the CMS needs no Composer, npm build or external database server.

## Quick local start

Requirements: **PHP 8.3+** with DOM, mbstring, fileinfo and PDO SQLite; GD with the required image codecs for images; ZIP for archives. Use a maintained PHP branch and current patches. Python, Node and Docker are development options, not CMS runtime requirements.

From the application folder:

```sh
export VV_STORAGE_PATH="$HOME/voilavoila-cms-local-data"
php bin/console.php doctor
php bin/console.php install-key
php -d memory_limit=256M -d upload_max_filesize=64M -d post_max_size=70M -S 127.0.0.1:8787 router.php
```

Keep the terminal open, visit [the local installer](http://127.0.0.1:8787/install.php), enter the generated key, create your owner account and store the one-time recovery code offline. The built-in PHP server is for local testing. For public hosting, follow the [HTTPS and storage deployment steps](docs/en/installation.md).

[Version-by-version references in both languages](docs/history/README.md) include the first release guide and validation.

## Scope

This is a single-owner personal CMS for moderate traffic. It has no public registration, multi-user roles, ticket sales or payment engine. The PASS plugin opens an existing HTTPS ticketing site. WordPress inspired some concepts; WordPress themes/plugins and `wp_*` functions are not compatible. WXR uses a local media ZIP, never remote attachment downloads. Plugin business data are not migrated. WordPress Porter is a limited static converter.

Native PHP extensions run with the hosting process privileges. Only install code you trust. See [security design](docs/en/security.md) and the [reporting policy](SECURITY.md).

## Documentation and development

| Topic | English | Français |
| --- | --- | --- |
| Installation and recovery | [Guide](docs/en/installation.md) | [Guide](docs/fr/installation.md) |
| Upgrade 1.1 → 1.2 | [Guide](docs/en/upgrade-1.2.md) | [Guide](docs/fr/mise-a-jour-1.2.md) |
| Documents | [Guide](docs/en/documents.md) | [Guide](docs/fr/documents.md) |
| Comments | [Guide](docs/en/comments.md) | [Guide](docs/fr/commentaires.md) |
| WordPress | [Guide](docs/en/wordpress.md) | [Guide](docs/fr/wordpress.md) |
| Architecture and versions | [Guide](docs/en/architecture.md) | [Guide](docs/fr/architecture.md) |
| Security and privacy | [Guide](docs/en/security.md) | [Guide](docs/fr/securite.md) |
| Native theme/plugin API | [SDK](sdk/API.en.md) | [SDK](sdk/API.md) |
| Testing and limitations | [Guide](docs/en/testing.md) | [Guide](docs/fr/tests.md) |
| GitHub publication | [Steps](GITHUB-v1.2.1-FR-EN.md) | [Étapes](GITHUB-v1.2.1-FR-EN.md) |
| Community and maintenance | [Guide](docs/en/community.md) | [Guide](docs/fr/communaute.md) |

For development checks, run `python3 tools/check_repo.py` and `python3 tests/run.py`. The tests use temporary application copies and synthetic data. See the testing guide before running them. The 1.2.1 workflow builds and tests a shared release archive on PHP 8.3/8.4/8.5 and Chromium; confirm its hosted result after pushing the commit. Historical release reports and current reproducible checks are distinguished in the guide.

## Contribute and get help

Questions and project showcases: [Discussions](https://github.com/farhadtoor/voilavoila-cms/discussions). Reproducible bugs and scoped proposals: [Issues](https://github.com/farhadtoor/voilavoila-cms/issues). English and French are welcome. Read [CONTRIBUTING](CONTRIBUTING.md), [SUPPORT](SUPPORT.md), the [Code of Conduct](CODE_OF_CONDUCT.md) and [ROADMAP](ROADMAP.md).

The code is MIT-licensed. The supplied PASS logo is excluded from that license; see [LICENSE](LICENSE) and [NOTICE](NOTICE). No WordPress or PASS ticketing engine is bundled.

Configure distribution placeholders with `python3 tools/configure_project.py --help` before publishing.
