# Testing and evidence — 1.2.1

[Français](../fr/tests.md)

Use Python 3.9+ and PHP 8.3+ with DOM, mbstring, fileinfo, PDO SQLite, ZIP and GD JPEG/PNG/WebP. Git is required by the release-builder checks. Node/Playwright are development-only dependencies.

## Source checks

```bash
python3 tools/check_repo.py
python3 tests/release_tools.py --report test-results/release-tools.json
python3 tests/run.py --report test-results/http.json
php content/plugins/wp-porter/tests/run.php
```

The HTTP runner has 190 checks: 50 base HTTP, 35 SQLite/media/logging, two failed-migration/retry cases, 80 document/comment/WordPress scenarios and 23 hosting/subdirectory checks. The release-tool suite has 28 checks, WP Porter has 44, and each explicit previous-version upgrade/rollback suite has 11.

All application writes use temporary synthetic storage. Test passwords are fixtures, not a shipped account. Do not reuse them.

## Test the installable ZIP

Commit the intended source before packaging. The builder records the actual commit and refuses existing output files.

```bash
python3 tools/build_release.py --version 1.2.1
python3 tools/verify_release.py dist/VoilaVoila-CMS-1.2.1.zip --expected-version 1.2.1 --extract test-results/from-zip
python3 test-results/from-zip/tools/verify_release.py --directory test-results/from-zip
python3 test-results/from-zip/tests/run.py --report test-results/extracted-http.json
```

Use a new extraction destination if it already contains files. Installed verification checks declared application bytes and known residual corrections; runtime data and additional custom modules are outside the release manifest. Checksums provide integrity, not author authentication.

## Browser journey

Install development dependencies from the lockfile, then install Chromium:

```bash
cd tests
npm ci --ignore-scripts
npx playwright install --with-deps --only-shell chromium
cd ..
python3 tests/browser_120.py --report test-results/browser.json
```

The harness has 18 checks and defaults to a temporary `/cms` installation. It creates an owner, enables comments, uploads real documents and exercises desktop/mobile, FR/EN and no-JavaScript rendering. The test closes its server and removes its synthetic state. `VV_BROWSER_EXECUTABLE` can point to a compatible existing Chromium; the report records the actual browser.

## Upgrade and rollback

The workflow builds trusted source fixtures from the pinned 1.1 tag commit and corrected 1.2 main commit. These are source fixtures, not claims that they are byte-identical to historical published release ZIPs. Given those fixture archives:

```bash
python3 tests/upgrade_release.py --previous-zip /path/to/previous-1.1.zip --previous-version 1.1.0 --report test-results/upgrade-1.1.json
python3 tests/upgrade_release.py --previous-zip /path/to/previous-1.2.zip --previous-version 1.2.0 --report test-results/upgrade-1.2.json
```

Only run application-upgrade tests with trusted executable source fixtures. They verify owner/content/settings/custom-file preservation and restore both old code and compatible private state.

## CI and qualification

The workflow checks source/tooling, builds one immutable ZIP, validates it and shares it with PHP 8.3/8.4/8.5 and Chromium jobs. PHP containers are built from the extracted archive, and their declared code is checked before tests. SDK ZIPs are retained in the image.

The accompanying validation JSON records local runs and versions. A new hosted Actions run is a separate result; confirm it after pushing the commit. [Hosting pilots](../HOSTING-PILOT-FR-EN.md) remain pending on actual offers. Historical reports are linked from [history](../history/README.md); they do not substitute for current evidence.
