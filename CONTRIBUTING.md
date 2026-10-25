# Contributing

[Français](CONTRIBUTING.fr.md)

Thank you for helping make a small, understandable CMS. Bug reports, documentation, translations, accessibility checks, native themes and scoped code changes are welcome.

## Before starting

Read the [scope and architecture](docs/en/architecture.md), [roadmap](ROADMAP.md), [Code of Conduct](CODE_OF_CONDUCT.md) and [security policy](SECURITY.md). Use Discussions for questions or early ideas. For a larger feature, describe the need in an issue before implementing it. A proposal is not a promise of acceptance.

## Local workflow

1. Fork the repository and clone your fork. Keep `main` as your base.
2. Create a focused branch: `git switch -c fix/short-description`.
3. Work on a disposable local installation. Never connect tests to production storage.
4. Make the smallest coherent change. Preserve single-owner behavior and native API 1 compatibility where possible.
5. Run the checks below and update the relevant English and French documentation. If you need help translating, say so in the PR rather than inventing a translation.
6. Open a pull request with the problem, resulting behavior, checks and migration impact. Reference the issue. Avoid unrelated formatting changes.

```sh
python3 tools/check_repo.py
python3 tests/run.py
node --check assets/admin.js
node --check assets/site.js
```

PHP and extensions are required for integration tests; Node is needed only for JavaScript checks. The [testing guide](docs/en/testing.md) explains supported commands. Include a focused regression check for a behavioral bug where useful. Documentation-only changes need link/content checks, not new runtime tests.

## Code and data conventions

Keep existing file conventions. Use prepared SQL, `Store::change()` for state writes, server-side validation and escaped HTML. Native PHP module files require the `VV_ROOT` direct-access guard. Do not nest store transactions. A plugin must write only in its own `plugin_data` namespace. Avoid new runtime dependencies without discussing their value and maintenance cost.

Do not commit `storage` data, database files, keys, session cookies, private images, raw logs or backups. Use fake examples on reserved domains such as `example.test`. Review `git diff --cached` before pushing. The hygiene script is not a complete secret scanner.

## Review and licensing

The maintainer may request changes, split a proposal or close work outside the current scope with a reason. Review is voluntary; no response deadline is guaranteed. Contributions must be yours to submit and may be distributed under the project code license. Do not add third-party assets without recording their origin and terms in NOTICE. No CLA or DCO workflow is imposed by this template.

Your first contribution can be a precise documentation correction, a confirmed hosting recipe, a keyboard accessibility fix or a test for a reported defect. See issues labeled `good first issue` after the maintainer has scoped them.
