# Testing 1.3 / Tests 1.3

No runtime dependency is added to the native CMS. Development checks require Python, PHP and optionally Node/Playwright. All integration tests use temporary synthetic private storage.

```sh
python3 tools/check_repo.py
python3 tests/run.py --php php --report test-results/http.json
python3 tests/release_tools.py --report test-results/release-tools.json
php content/plugins/wp-porter/tests/run.php
cd tests
npm ci --ignore-scripts
npx playwright install --with-deps --only-shell chromium
cd ..
python3 tests/browser_120.py --php php --report test-results/browser.json
python3 tests/browser_130.py --php php --report test-results/browser-forms.json
```

Real WordPress checks are opt-in and create their own dedicated MariaDB data directory and private FPM pool. They never connect to a configured production database. Prepare fresh official inputs outside the repository with `tools/fetch_wordpress_fixture.py` first. The current harness uses explicit loopback transport and the same UID for local compatibility tests; it **does not validate OS isolation**. Its development FPM pool runs as the test operator (root in the supplied local harness), never a production deployment template.

```sh
python3 tests/wordpress_runtime.py \
  --php /path/to/php \
  --fpm /path/to/php-fpm \
  --mariadb-root /path/to/mariadb/usr \
  --wordpress-zip /private/wordpress-VERSION.zip \
  --verification-bundle /private/wordpress-verification.php \
  --report test-results/wordpress.json
```

`mariadb-root` contains `bin/mariadb`, `bin/mariadb-install-db`, `sbin/mariadbd` and its share files. A normal Debian installation can use `/usr`; use an isolated test environment. Never expose the temporary development listeners publicly. The command shuts down its services afterwards. `--keep /new/private/path` retains synthetic debug files with secrets; never publish that tree.

`VV_WP_BROWSER_REPORT=/absolute/private/report.json` additionally runs the real WordPress Chromium journey when Playwright is installed. `VV_BROWSER_EXECUTABLE` may select an operator-installed Chromium binary.

The final validation report records executed versions and check names. Docker/UID isolation, TLS, actual email delivery, hosted GitHub Actions and OVH/Infomaniak pilots remain separate qualification work. Performance estimates in the original architecture proposal are not measured results.

## Authenticator and WooCommerce additions in 1.3.1

`tests/run.py` includes `tests/otp_131.py`: HTTP enforcement, recovery/backup/key handling and independent RFC 6238 vectors. Browser QR decoding uses the pinned development-only `jsqr` package installed by `npm ci`. Run `python3 tests/browser_otp.py --php php --report test-results/otp-browser.json`.

The real WordPress command also accepts `--woocommerce-zip /private/woocommerce-11.2.1.zip`. It installs official WooCommerce in its disposable engine, creates a synthetic product/cart, places an offline-payment order, verifies stock and intercepts all email. Optional `VV_WP_BROWSER_REPORT=/private/browser.json` runs the browser journey too. No production credential/database/payment is used.

Build separate addons/offline inputs with `tools/build_extensions.py`; see its `--help`. Do not put installed engines or large official inputs in source Git history.
