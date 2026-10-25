# Tests and evidence

[Français](../fr/tests.md)

## Run the supplied checks

Use Python 3.9+ and PHP 8.3+ with DOM, mbstring, fileinfo, PDO SQLite, ZIP and GD including JPEG/PNG/WebP support. No third-party Python package is required. Commands run from the repository root:

```sh
python3 tools/check_repo.py
python3 tests/run.py
node --check assets/admin.js
node --check assets/site.js
```

If PHP is not on PATH: `python3 tests/run.py --php /absolute/path/to/php`. Optional summary: `python3 tests/run.py --report test-results/report.json`. Node is needed only for the two JavaScript syntax checks, not to run the CMS or Python suite.

`tests/run.py` copies the application into an OS temporary directory, uses separate temporary storage and a localhost port, generates synthetic images with GD and cleans everything up. It never reads or modifies the real `storage` directory. Use only this runner; the component scripts depend on its temporary environment. Temporary owners/passwords are test fixtures, not credentials for a shipped account. Do not reuse their passwords.

The suite covers 50 HTTP scenarios, 35 SQLite/media/logging/migration scenarios and two migration failure/retry scenarios: **87 checks**. The migration fixture is synthetic schema-1 data based on the supplied format; it does not use a real owner's storage. It verifies original JPEG/PNG/WebP bytes and SHA-256, database rows/revisions, private access denial, log separation/rotation, rollback, old backup imports and idempotence. On 25 October 2026 this portable runner passed locally on PHP 8.3.6. See [the recorded summary](../verification-github-kit.json).

`tools/check_repo.py` detects unexpected runtime data, unfinished project tokens and broken local Markdown targets. Before configuring a distribution template only, `--allow-template` permits project tokens. It does not replace a complete secret scanner or a human staged-diff review. It expects a clean source tree: keep real deployment storage elsewhere.

## CI

`.github/workflows/ci.yml` runs repository/JavaScript checks and containerized PHP jobs for 8.3, 8.4 and 8.5 on pushes to `main`, pull requests and manual dispatch. Checkout is pinned to a verified full commit, credentials are not persisted, token permission is read-only and there are no deployment secrets or automatic releases. Dependabot proposes GitHub Action updates monthly. Review proposed changes before merging.

The `tests/Dockerfile` enables GD JPEG/WebP codecs. To reproduce with Docker: `docker build --build-arg PHP_VERSION=8.3 -f tests/Dockerfile -t vv-cms-tests .`, then `docker run --rm vv-cms-tests`. This environment had no Docker; the container build, PHP 8.4/8.5 jobs and actual GitHub workflow execution are **not yet validated**. Review the first results before describing those versions as tested. This test container is separate from the root hosting Dockerfile.

## Historical release validation

The supplied 1.0.0 reports record 50 HTTP and 29 browser checks. The supplied 1.1.0 reports record 50 HTTP, 35 feature/migration, two failure/retry and 29 browser checks: 116 total. Those reports describe the original preparation; they are not a new GitHub CI run. The browser automation used then is not included as a current portable browser suite. A fresh browser pass remains manual unless a future contribution adds one.

Production Apache/Nginx HTTPS, root Docker deployment, macOS double-click launcher, real hosting limits, independent security auditing and actual GitHub publishing were not executed in this preparation. The original application source was preserved; new testing covers the source copy, not an existing production site.
